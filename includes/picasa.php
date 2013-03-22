<?php
/*
	Plugin Name: SlideshowFx
	Plugin URI: http://wordpress.org/extend/plugins/slideshowfx/
	Description: Insert Google+, Picasa or Flickr albums and photo thumbnail inside WordPress articles.
	Author: Joel VALLIER
	Version: 1.0
	Author URI: http://www.oopstouch.com/
	Plugin URI: http://wordpress.org/extend/plugins/slideshowfx/
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
/*  Copyright 2012  Joel VALLIER  (email : contact@oopstouch.com)

    This file is part of SlideshowFx plugin for WordPress.

    SlideshowFx is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    SlideshowFx is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with SlideshowFx.  If not, see <http://www.gnu.org/licenses/>.
*/
// Class used to get Picasa Albums and Pictures
class sfxPicasa {

	static $_param = array(
	'username' => '',
	'authid' => '',
	'thumbnailimgmax' => 160,
	'imgmax' => 1600
	);
	static $_albumPhotos = array();

	static function init($username, $api_key='', $api_secret='', $authid='', $imgmax='1024', $thumbnailimgmax='200') {
		// Check if cURL extention is loaded
		if  (!in_array  ('curl', get_loaded_extensions(), true)) {
			return -1;
		}
		
		// save parameters
		if (!empty($username)) self::$_param['username'] = $username;
		if (!empty($authid)) self::$_param['authid'] = $authid;
		if (!empty($imgmax)) self::$_param['imgmax'] = $imgmax;
		if (!empty($thumbnailimgmax)) self::$_param['thumbnailimgmax'] = $thumbnailimgmax;
		
		// no error
		return 0;
	}

	static function getAlbumInfos($idx, $albumId, &$status) {
		$url = "http://picasaweb.google.com/data/feed/api/user/" . self::$_param['username']. '?thumbsize=' . self::$_param['thumbnailimgmax'] . 'c';
		$xmldata = sfxCurl::request($url, self::$_param['authid']);
		$albumList = array();
		$status = '';
		if(preg_match("@<\?xml version='1\.0' encoding='UTF-8'\?>@",$xmldata)) {
			// get data
			$data = simplexml_load_string($xmldata);
			$namespace = $data->getDocNamespaces();
			$pos = 0;
			foreach ($data->entry as $entry) {
				// Gphoto namespace data
				$ns_gphoto = $entry->children($namespace['gphoto']);
				
				// Media namespace data
				$ns_media = $entry->children($namespace['media']);
				
				// Media thumbnail attributes
				$thb_attr = $ns_media->group->thumbnail->attributes();
				
				// Media content attributes
				$con_attr = $ns_media->group->content->attributes();
				
				if (($albumId == 0) || (strcmp($albumId, (string)$ns_gphoto->id)) == 0) {
					$numphotos = (string)$ns_gphoto->numphotos;
					if ($numphotos == 0) {
						continue;
					}
					$albumObject = 	array (
					'pos' => $pos++,
					'idx' => $idx,
					'albumTitle' 	=> (string)$entry->title,
					'thumbURL' 		=> (string)$thb_attr['url'],
					'albumID' 		=> (string)$ns_gphoto->id,
					);
					if ($albumId != 0) {
						// return album info
						$status = '';
						$albumList[] = $albumObject;
						return $albumList;
					} else {
						$albumList[] = $albumObject;
					}
				}
			}
			if ($albumId != 0) {
				$status = 'Unable to find album '.$albumId.' in '.self::$_param['username'];
				if (empty(self::$_param['authid'])) $status .= '<br />(Please, check album id and access rights)';
				return null;
			} else {
				if ($albumList == null) {$status = 'Gallery not found<br />(Please, check username and access rights)'; }
				else { $status = ''; }
				return $albumList;
			}
		}
		// album not found
		$status =  htmlentities($xmldata);
		return null;
	}
	
	static function getAlbumPhotos($albumid,$start=0,$length=0) {
		$imgmax=self::$_param['imgmax'];
		$url  = 'http://picasaweb.google.com/data/feed/api/user/' . self::$_param['username'] . '/albumid/' . $albumid;
		$url .= '?imgmax=' . $imgmax . '&thumbsize=' .self::$_param['thumbnailimgmax'].'c';
		$xmldata = sfxCurl::request($url, self::$_param['authid']);
		$numPhotos = 0;
		$numVideos = 0;
		$albumTitle = '';
		$photos = array();
		if (preg_match("@<\?xml version='1\.0' encoding='UTF-8'\?>@",$xmldata)) {

			$data = simplexml_load_string($xmldata);
			$namespace = $data->getDocNamespaces();
			$albumTitle = (string)$data->title[0];
			$pos = 0;
			foreach ($data->entry as $entry) {
				// Collect for each photo;
				// - title
				// - url
				// - thumbnail url
				
				// Gphoto namespace data
				$ns_gphoto = $entry->children($namespace['gphoto']);
				// Media namespace data
				$ns_media = $entry->children($namespace['media']);
				// Media thumbnail attributes
				// Google API workaround Mai 2011 now removed: $thb_attr = ((isset($ns_media->group->thumbnail[1])) ? $ns_media->group->thumbnail[1]->attributes() : $ns_media->group->thumbnail[0]->attributes());
				$thb_attr = $ns_media->group->thumbnail->attributes();
				// Media content attributes
				$con_attr = $ns_media->group->content->attributes();
				// get photo url
				$photoURL = (string)$con_attr['url'];
				// get photo title (filename in fact)
				$photoTitle = (string)$entry->title[0];
				// rename file (replace video extension with photo extension)
				// this allows video rating and download will return associated picture
				// (no possibility to download video from Picasa)
				$pos = strrpos($photoTitle, '.');
				$photoTitle = substr($photoTitle, 0, $pos);
				$pos = strrpos($photoURL, '.');
				$photoTitle .= substr($photoURL, $pos);
				$photo = array (
				'pos' => $pos++,
				'caption'     => (string)$entry->summary,
				'thumbURL'    => (string)$thb_attr['url'],
				);
				$photos[] = $photo;
				$numPhotos += 1;
			}
		} else {
			// no photo found
			return null;
		}
		
		$albumcache = array(
			'albumTitle' => $albumTitle,
			'numPhotos'  => $numPhotos,
			'numVideos'  => $numVideos,
			'photosList' => $photos
		);
		
		// load specific page
		$photos = $albumcache['photosList'];
		$numPhotos = $albumcache['numPhotos'];
		if ($length==0 || ($start+$length)>$numPhotos) $length = $numPhotos - $start;
		$photoList = array();
		for ($i=$start;$i<($start + $length);$i++) {
			$photoList[] = $photos[$i];
		}

		$photoAlbum = array(
			'albumTitle' => $albumcache['albumTitle'],
			'numPhotos' => $numPhotos,
			'photosList' => $photoList);
		
		return $photoAlbum;
	}
}
?>