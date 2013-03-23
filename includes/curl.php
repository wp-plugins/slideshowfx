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
// Class used to interface with cURL
class sfxCurl {
	static function request($url, $authid) {
		// init curl
		$ch = curl_init($url);
		// set authid
		if (!empty($authid)) {
			$header[] = 'Authorization: AuthSub token="'.$authid.'"';
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_HEADER, false);
		}
		@curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // avoid warning for web hoster who do not support option CURLOPT_IPRESOLV
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// send request
		$result = curl_exec($ch);
		// check result
		if ($result === false) {
			$errno  = curl_errno($ch);
			$error  = curl_error($ch);
			$result = "CURL error " . $errno .": " .$error;
		}
		// close cURL
		curl_close($ch);
		// return result
		return $result;
	}
}
?>