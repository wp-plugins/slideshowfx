/*
	Plugin Name: SlideshowFx
	Plugin URI: http://wordpress.org/extend/plugins/slideshowfx/
	Description: Insert Google+, Picasa or Flickr albums and photo thumbnail inside WordPress articles.
	Author: Joël VALLIER
	Version: 1.0
	Author URI: http://www.oopstouch.com/
	Plugin URI: http://wordpress.org/extend/plugins/slideshowfx/
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
/*  Copyright 2012  Joël VALLIER  (email : contact@oopstouch.com)

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
var sfxPageWidth = [];
jQuery.noConflict();
(function($) { 
  $(function() {
			 
	// launch initializer on DOM ready
	$(document).ready(sfxInitialize);
	
	// SlideshowFx initializer
	function sfxInitialize() {
		// resize containers
		$('.sfx_container').each(sfxResizeContainer);
	}
	
	function sfxResizeContainer(index, element) {
		// get container
		container = $(element);
		// read container parameters
		params = sfxPageWidth[index];
		// get content and exit if not existing
		var content = container.find('.sfx_content');
		if (content.length == 0) { return; }
		// get page width
		var curr_width = (content.width()-1);
		if (curr_width < 0) { curr_width = container.width()-1; } // Workaround for MagicTab (offsetWidth returns 0 if element is inside a div width set to 0)
		// calculate number of thumbnail per row
		if (params.thumbnail_row > 39) {
			params.thumbnail_row = Math.floor(curr_width / params.thumbnail_row);
			if (params.thumbnail_row == 0) { params.thumbnail_row = 1 }
		}
		params.thumbnail_div_width = sfxGetElementWidth(container, '.sfx');
		params.thumbnail_img_width = sfxGetElementWidth(container, '.sfx_img');
		params.content_width = sfxGetElementWidth(container, '.sfx_content');
		if (params.length < params.thumbnail_row) { params.thumbnail_row = params.length; }
		params.size = Math.floor(curr_width / params.thumbnail_row) - (params.thumbnail_div_width+params.thumbnail_img_width);
		m = 30;
		if (params.size > params.max_thumbnail_width) { params.size = params.max_thumbnail_width; }
			content.css('width', params.thumbnail_row * (params.size + (params.thumbnail_div_width+params.thumbnail_img_width))+'px');
		info_size  = container.find('.sfx_caption').outerHeight(true);
		info_size += container.find('.sfx_title').outerHeight(true);
		info_size += container.find('.sfx_info').outerHeight(true);
		m*=100;
		// resize thumbnail
		sfxResizeThumbnail(container, params);
		// hide some thumbnails
		container.find('.sfx').each( function (index, element) { if (index >= m) { $(element).css('display', 'none'); }});
		if (content.width() == 0) { container.css('width', (container.width()) + 'px'); } // Workaround for MagicTab (offsetWidth return 0 if element is inside a div width set to 0)
		else { container.css('width', (content.width()) + 'px'); }
		// display result
		container.css({opacity: 0.0, visibility: "visible"}).animate({opacity: 1.0}, 'slow');
	}
	
	function sfxResizeThumbnail(container, params) {
		var div  = container.find('.sfx');
		var img  = container.find('.sfx_img');
		var info = container.find('.sfx_info');
		if (params.download) {
			img.bind('contextmenu', 
			function() {
				var id = this.id;
				id = id.replace('sfx_img_', '');
				var pos = id.indexOf('_');
				id = id.substr(0, pos);
				sfxDownload(id, this.src) ;
				return false;
			});
		} else {
			img.bind('contextmenu', function() { return false; });
		}
		if (info.length > 0) { info.css('height', (info.offsetHeight-1)+'px'); }
			div.css('width', (params.size+params.thumbnail_img_width)+'px');
			div.css('height', (params.size+params.thumbnail_img_width+info_size)+'px');
		img.css('width', (params.size)+'px');
		img.css('height', (params.size)+'px');
	}
	
	function sfxGetElementWidth(container, name) {
		var element = $(container).find(name);
		var width = 0;
		if (element.length > 0) {
			width = element.outerWidth(true)-element.width();
		}
		return width;
	}
  });
})(jQuery);
