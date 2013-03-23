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
// Class used to get Flickr Albums and Pictures
class sfxFlickr {
	
	static $_param = array(
	'username' => '',
	'password' => '',
	'authmode' => '',
	'authid' => '',
	'api_key' => '',
	'api_secret' => '',
	'thumbnailimgmax' => 160,
	'imgmax' => 1600
	);
	static $_albumPhotos = array();

	static function init($username, $api_key='', $api_secret='', $authid='', $imgmax='1600', $thumbnailimgmax='160') {
		// Check if cURL extention is loaded
		if  (!in_array  ('curl', get_loaded_extensions(), true)) {
			return -1;
		}
		
		// save parameters
		if (!empty($username)) self::$_param['username'] = $username;
		if (!empty($api_key)) self::$_param['api_key'] = $api_key;
		if (!empty($api_secret)) self::$_param['api_secret'] = $api_secret;
		if (!empty($authid)) self::$_param['authid'] = $authid;
		if (!empty($imgmax)) self::$_param['imgmax'] = $imgmax;
		if (!empty($thumbnailimgmax)) self::$_param['thumbnailimgmax'] = $thumbnailimgmax;
		if (empty(self::$_param['api_secret'])) { self::$_param['authid'] = ''; }
		// no error
		return 0;
	}

	static function extPhotoSize() {
		$size = self::$_param['imgmax'];
		if ($size == 'd') return '_b'; /* _o can be used with Flickr Pro but cause error with Free Flickr... */
		elseif ($size >= 1024) return '_b';
		elseif ($size >= 640) return '_z';
		elseif ($size >= 500) return '';
		elseif ($size >= 320) return '_n';
		elseif ($size >= 240) return '_m';
		elseif ($size >= 100) return '_t';
		return '_s';
	}
	
	static function extThumbSize() {
		if (self::$_param['thumbnailimgmax'] > 75) return '_q';
		return '_s';
	}
	
	static function request($query, &$status) {
		// check API key
		if (empty(self::$_param['api_key'])) {
			$status = 'Flickr API key required';
			return null;
		}
		
		// add standard parameters
		$query['api_key'] = self::$_param['api_key'];
		$query['format']  = 'php_serial';
		if (!empty(self::$_param['authid'])) {
			if (empty(self::$_param['api_secret'])) {
				$status = 'Flickr secret code required to sign request';
				return null;
			}
			$query['auth_token'] = self::$_param['authid'];
		}
		
		// sort params by key
		ksort($query);
		
		// sign request if secret code provided
		if (!empty(self::$_param['api_secret'])) {
			// prepare signature
			$api_sig = self::$_param['api_secret'];
			foreach ($query as $k => $v){ $api_sig .= $k.$v; }
			$query['api_sig'] = md5($api_sig);
		}
		// encode params
		$encoded_params = array();
		foreach ($query as $k => $v){ $encoded_params[] = urlencode($k).'='.urlencode($v); }
		$url = 'http://api.flickr.com/services/rest/?'.implode('&', $encoded_params);
		
		// send request
		$file_contents = sfxCurl::request($url, '');
		
		// check status
		if (strpos($file_contents, 'CURL error') === 0) {
			$status = $file_contents;
			return null;
		}
		
		// get result
		$rsp_obj = unserialize($file_contents);
		
		// check return code
		if ($rsp_obj['stat'] != 'ok') {
			$status = $rsp_obj['message'];
			return null;
		}
		// return result
		return $rsp_obj;
	}
	
	static function getAlbumInfos($idx, $albumId, &$status) {
		// build request
		$query = array(
			'method'	=> 'flickr.photosets.getList',
			'user_id'	=> self::$_param['username']
		);
		
		// send request and get and check result
		$rsp_obj = self::request($query, $status);
		if ($rsp_obj == null) { return null; }
		
		// browse result
		$albumList = array();
		$pos = 0;
		foreach ($rsp_obj['photosets']['photoset'] as $key => $value) {
			if (($albumId == 0) || ($albumId == $value['id'])) {
				$albumObject = 	array (
					'pos' => $pos++,
					'idx' => $idx,
					'albumTitle'  => $value['title']['_content'],
					'thumbURL'	  => 'http://farm'.$value['farm'].'.static.flickr.com/'.$value['server'].'/'.$value['primary'].'_'.$value['secret'].self::extThumbSize().'.jpg',
					'albumID'	  => $value['id'],
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
		if (count($albumList) == 0) {
			$status = 'No album available or Token missing';
			return null;
		}
		// return result
		return $albumList;
	}
	
	static function getAlbumPhotos($albumid,$start=0,$length=0) {
		// clear status
		$status = '';
		
		// build request
		$query = array(
			'method'		=> 'flickr.photosets.getPhotos',
			'photoset_id'	=> $albumid,
			'media' 		=> 'photo',
			'extras'		=> 'description,date_upload,last_update'
		);
		
		// get extra data in case of download
		if (self::$_param['imgmax'] == 'd') {
			$query['extras'] .= ',originalsecret,originalformat';
		}
		
		// send request and get and check result
		$rsp_obj = self::request($query, $status);
		if ($rsp_obj == null) { return null; }
		
		// browse result
		$numPhotos = 0;
		$numVideos = 0;
		$pos = 0;
		foreach ($rsp_obj['photoset']['photo'] as $key => $value) {
			// build extension according to image size requested
			if ((self::$_param['imgmax'] == 'd') && (!empty($value['originalsecret'])) && (!empty($value['originalformat']))) {
				// never tested since Flickr Pro is required to get original file...
				$extension = $value['originalsecret'].'_o.'.$value['originalformat'];
			} else {
				$extension = $value['secret'].self::extPhotoSize().'.jpg';
			}
			// build photo record
			$photo = array (
				'pos' 			=> $pos++,
				'caption'		=> $value['title'],
				'thumbURL'		=> 'http://farm'.$value['farm'].'.static.flickr.com/'.$value['server'].'/'.$value['id'].'_'.$value['secret'].self::extThumbSize().'.jpg',
			);
			$photos[] = $photo;
			$numPhotos += 1;
		}
		
		// get album infos
		$query = array(
			'method'		=> 'flickr.photosets.getInfo',
			'photoset_id'	=> $albumid
		);
		
		// send request and get and check result
		$rsp_obj = self::request($query, $status);
		if ($rsp_obj == null) { return null; }
		
		$albumcache = array(
			'albumTitle' => $rsp_obj['photoset']['title']['_content'],
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