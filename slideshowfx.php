<?php
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
// include Joomla wrapper and picasa api
include 'includes/curl.php';
include 'includes/picasa.php';
include 'includes/flickr.php';

// definition of some constants
static $sfx_counter = '';

// translation for site and admin part
function _s($str) { return translate($str, 'wp-sfx'); }
function _a($str) { return translate($str, 'wp-sfx'); }

// entry point from WordPress
add_shortcode('sfx', array('SlideshowFx', 'exec'));

// init code
add_action('init', array('SlideshowFx','add_init'));
add_action( 'admin_init', array('SlideshowFx','add_admin_init'));

// add headers
add_action( 'wp_print_scripts', array('SlideshowFx','add_header_scripts') );
add_action( 'wp_print_styles', array('SlideshowFx','add_header_styles') );

// add query vars
add_filter('query_vars', array('SlideshowFx','add_vars'));

// add menu for options page
add_action( 'admin_menu', array('SlideshowFx','add_settings') );

// class definition
class SlideshowFx {
	/**
	 * List of parameters taken from the plugin settings with default value
	 */
	static $default = array(
		'user' => 'contact@oopstouch.com',
		'flickr_api_key' => '4d9a55cf8028ed51fe6de1c9f8dbf5e2',
		'album' => '',
		'albums_row' => '150',
		'force_gallery_width' => '',
		'pictures_row' => '150',
		'force_album_width' => ''
	);
	
	// some static variables
	static $sfx_index;
	
	// front-end init function
	function add_init() {
		// update default parameter with stored parameters
		$options = get_option( 'sfx_options' );
		if (!empty($options)) {
			self::$default = array_merge(self::$default, $options);
		}
		// load site language file
		load_plugin_textdomain( 'wp-sfx', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/site/' );
	}
	
	// register and define the settings
	function add_admin_init() {
		// load site language file
		load_plugin_textdomain( 'wp-sfx', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/admin/' );
		// register SlideshowFx parameters
		register_setting( 'sfx_options', 'sfx_options', array('SlideshowFx','sfx_validate_options') );
	}
	
	static function add_settings() {
		add_options_page( 'SlideshowFx settings', 'SlideshowFx', 'manage_options', 'sfx', array('SlideshowFx','options_page') );
	}
	
	/**
	 * Properly enqueue scripts for front-end
	 */
	static function add_header_scripts() {
		if ( ! is_admin() ) {
			// install javascripts
			if( function_exists( 'wp_register_script' ) ) {
				// install SlideshowFs javascripts
				wp_register_script( 'sfx', plugins_url('js/sfx.js',__FILE__), array('jquery'), 1 );
				if( function_exists( 'wp_enqueue_script' ) ) {
					wp_enqueue_script( 'sfx' );
				}
			}
		}
	}
	
	/**
	 * Properly enqueue styles for front-end
	 */	
	static function add_header_styles() {
		
		if ( ! is_admin() ) {
			if( function_exists( 'wp_register_style' ) ) {
				wp_register_style( 'sfx', plugins_url('css/sfx.css',__FILE__), '');
				if ( function_exists( 'wp_enqueue_style' ) ) {
					wp_enqueue_style( 'sfx' );
				}
			}
			if (file_exists(dirname( './wp-content/plugins/'.plugin_basename( __FILE__ ) ) . '/extra/css/custom.css')) {
				// add custom CSS declaration
				if( function_exists( 'wp_register_style' ) ) {
					wp_register_style( 'sfx-custom', plugins_url('extra/css/custom.css',__FILE__), '' );
					if ( function_exists( 'wp_enqueue_style' ) ) {
						wp_enqueue_style( 'sfx-custom' );
					}
				}
			}
		}
	}
	
	static function add_vars($public_query_vars) {
		$public_query_vars[] = 'albid';
		$public_query_vars[] = 'slideshow';
		$public_query_vars[] = 'sort';
		return $public_query_vars;
	}
	
	// output the options page
	static function options_page() {
		if ( !current_user_can( 'manage_options') ){ wp_die( __( 'You do not have sufficient permissions to access this page.' ) ); }
		self::setting_input();
	}
	
	static function get_initial_admin_uri() {
		$uri = $_SERVER['REQUEST_URI'];
		$pos = strpos($uri, '&api=');
		$pos = strpos($uri, '&cancel');
		if ($pos > 0) { $uri = substr($uri, 0, $pos); }
		return $uri;
	}
	
	static function display_oopstouch_links() {
	?>
	<div style="width:20%;" class="postbox-container side">
		<div class="metabox-holder">
			<div class="postbox" id="donate">
				<h3><span><?php echo _a('Useful links'); ?></span></h3>
					<div class="inside">
                        <p><a href='http://www.oopstouch.com/flickr/search' target='_blank'><?php echo _a('Search for Flickr user ID'); ?></a></p>
						<p><a href='http://wp.oopstouch.com/?page_id=22' target='_blank'><?php echo _a('Demo SlideshowFx Pro'); ?></a></p>
						<p><a href='http://www.oopstouch.com/download/slideshowfx/wordpress' target='_blank'><?php echo _a('Get SlideshowFx Pro'); ?></a></p>
					</div>
			</div>
		</div>
	</div>
	<?php
	}
	
	// add spacer in parameters
	static function param_spacer($label) {
		?>
		<tr valign="top" style="cursor:default;">
			<th scope="row" colspan="2"><h2><?php echo $label; ?></h2></th>
			
		</tr>
		<?php
	}
	
	// add input text in parameters
	static function param_text($options, $name, $label, $description, $size=84, $featured=false) {
		if (!isset(self::$default[$name])) {
			return;
		}
		$extra = '';
		if ($featured) {
			$extra = ' readonly="readonly" onclick="javascript:this.focus();this.select();" style="cursor:pointer;"';
		}
		?>
		<tr valign="top" style="cursor:default;">
			<th scope="row" title="<?php echo $description; ?>"><?php echo $label; ?></th>
			<td><input type="text" name="sfx_options[<?php echo $name; ?>]" value="<?php echo $options[$name]; ?>" size="<?php echo $size; ?>"<?php echo $extra; ?> /></td>
		</tr>
		<?php
	}
	
	// Display and fill the form field
	static function setting_input() {
		global $get_page;
		
		// get options (default merged with stored parameters in add_init)
		$options = self::$default;
		?>
		<div class="wrap">
		<?php screen_icon(); ?>
		<h2>SlideshowFx Settings Page</h2>
		<form method="post" action="options.php">
		<?php
		if( function_exists('settings_fields') ) {
			settings_fields( 'sfx_options' ); // needs to match register_settings()
		}
		// grab the form
		?>
		<div style="width: 65%;" class="postbox-container">
			<div class="metabox-holder">
				<div class="postbox" id="settings">
					<table class="form-table">
                    <?php
						self::param_spacer(_a('User account'));
                    	self::param_text($options, 'user', _a('Picasa/Google+ username').'<br />'._a('Flickr user ID'), _a('Enter a Picasa/Google+ username (contact@oopstouch.com for instance) or Flickr user ID (57325797@N02 for instance).'));
					?>
					</table>
				</div>
				<input type="submit" name="submit" class="button-primary" value="<?php echo _a('Save Changes') ?>" />
                <input type="reset" name="submit" class="button-primary" value="<?php echo _a('Cancel Changes') ?>" onclick="window.location = '<?php echo self::get_initial_admin_uri(); ?>&cancel'" />
			</div>
		</div>
        <?php self::display_oopstouch_links(); ?>
		</form>
		</div>
		<?php
	}
	
	// validate user input
	static function sfx_validate_options( $input ) {
		// retrieve and return all parameters from the form
		foreach (self::$default as $key => $value) {
			if (isset($input[$key])) {
				$valid[$key] = esc_attr( $input[$key] );
			}
		}
		return $valid;
	}
	
	static function isFlickrUserID($user)
	{
		// Flickr user ID is xxxx@Xxxxx or xxxx[at]Xxxxx with xxxx numeric and X alphabetic
		$user = str_replace('[at]', '@', $user);
		$pos  = strpos($user, '@');
		if ($pos === false) {
			return false;
		}
		$word = substr($user, 0, $pos);
		if (!is_numeric($word)) {
			return false;
		}
		$pos += 1;
		$word = substr($user, $pos, 1);
		if (is_numeric($word)) {
			return false;
		}
		$pos += 1;
		$word = substr($user, $pos);
		if (!is_numeric($word)) {
			return false;
		}
		// looks like Flickr user ID
		return true;
	}
		
	static function exec($atts)
	{
		global $sfx_counter;
		
		// start result buffering
		ob_start();
		
		// Make sure we're running an up-to-date version of PHP
		$phpVersion = phpversion();
		$verArray = explode('.', $phpVersion);
		if( (int)$verArray[0] < 5 )
		{
			echo "'SlideshowFx' requires PHP version 5 or newer.<br>\n";
			echo "Your server is running version $phpVersion<br>\n";
			exit;
		}
		
		// Check if cURL extention is loaded
		if  (!in_array  ('curl', get_loaded_extensions(), true)) {
			echo "'SlideshowFx' requires cURL library.<br>\n";
			echo "This library is not loaded / activated on your server.<br>\n";
			exit;
		}
		
		// get parameters (merge between database and inline parameters except user and token)
		$inline = shortcode_atts(self::$default, $atts);
		
		// set default parameters
		if (empty($inline['display'])) {
			if (empty($inline['album'])) { $inline['display'] = 'gallery'; }
			else { $inline['display'] = 'album'; }
		}
		
		// get album id from query vars
		if (empty($inline['album'])) {
			$inline['album'] = get_query_var('albid');
		}
		
		// initialize sfx_counter and sfx_index
		if (empty($sfx_counter)) { $sfx_counter = 1; }
		self::$sfx_index = 1;
		
		// define image size
		$img_size = '';
		
		// initialize the API and get album info
		$status = '';
		if (self::isFlickrUserID($inline['user'])) {
			$result = sfxFlickr::init($inline['user'], $inline['flickr_api_key']);
			$albumInfos = sfxFlickr::getAlbumInfos(1, $inline['album'], $status);
		} else {
			$result = sfxPicasa::init($inline['user'], $inline['flickr_api_key']);
			$albumInfos = sfxPicasa::getAlbumInfos(1, $inline['album'], $status);
		}
		
		// check album
		if ($albumInfos == null) {
			echo 'Gallery not found!!!<br />';
			exit;
		}
		
		// get photos
		if ($inline['album']) {
			// get photos
			if (self::isFlickrUserID($inline['user'])) {
				$albumPhotos = sfxFlickr::getAlbumPhotos($inline['album']);
			} else {
				$albumPhotos = sfxPicasa::getAlbumPhotos($inline['album']);
			}
			$inline['display'] = 'album';
		} else {
			$inline['display'] = 'gallery';
			$albumPhotos = NULL;
		}
		
		// display result
		if (isset($albumPhotos)) {
			// display album
			self::insertAlbum($inline, $albumInfos[0], $albumPhotos);
		} else {
			// display gallery
			self::insertGallery($inline, $albumInfos);
		}
		
		// return buffer
		return ob_get_clean();
	}
		
	static function insertGallery($format, $galleryInfos)
	{
		// check gallery album list
		if (empty($galleryInfos)) {
			echo 'Empty gallery received from Picasa.<br />Check visibility in Picasa albums propertises.';
			return;
		}
				
		// set gallery width
		$force_width = $format['force_gallery_width'];
		if (!empty($force_width)) {
			if ((JString::strpos($force_width, "%") === false) && (JString::strpos($force_width, "px") === false)) { $force_width .= 'px'; }
			$force_width = ' style="width: ' . $force_width . '; margin-left: auto; margin-right: auto;"';
		}
		
		// global container
		echo '<div class="sfx_container" ' . $force_width . '>';
		
		// set target option
		$target = '';
		
		// insert content
		echo '<div class="sfx_content">';
		$i = 1;
		foreach ($galleryInfos as $albumInfos) {
			// prepare title
			$title = htmlspecialchars($albumInfos['albumTitle']);
			
			// prepare thumbnail
			$thumbURL = $albumInfos['thumbURL'];
			
			// Prepare album link
			$link = self::buildURL($albumInfos['albumID']);
			
			// write thumbnail
			echo '<div class="sfx">';
			echo '<a href="' . $link . '"'. $target. ((!empty($title)) ? ' title="' . $title . '"' : '') . '>';
			echo '<img class="sfx_img" src="' . $thumbURL . '" alt="' . $title . '" />';
			echo '</a>';
			echo '</div>';
			
			// next album
			$i++;
		}
		// add padding
		echo '<div class="sfx_content_padding"></div>';
			
		// end of gallery
		echo '</div>';
		
		// end of container
		echo '</div>';
		
		// write parameters
		self::insertParameters($format , $i-1);
	}
	
	static function insertAlbum($format, $albumInfos, $albumPhotos)
	{
		// check album photo list
		if (empty($albumPhotos['photosList'])) {
			echo 'Empty album received from Picasa<br />Check visibility in Picasa album propertises';
			return;
		}
		
		// set gallery width
		$force_width = $format['force_album_width'];
		if (!empty($force_width)) {
			if ((JString::strpos($force_width, '%') === false) && (JString::strpos($force_width, 'px') === false)) { $force_width .= 'px'; }
			$force_width = ' style="width:' . $force_width . '; margin-left: auto; margin-right: auto;"';
		}
		
		// global container
		echo '<div class="sfx_container" ' . $force_width . '>';
		
		// insert content
		echo '<div class="sfx_content">';
		$i = 1;
		foreach ($albumPhotos['photosList'] as $photo) {
			// prepare caption
			$caption = $photo['caption'];
			
			// prepare thumbnail
			$thumbURL = $photo['thumbURL'];
			$thumb  = '<img class="sfx_img" src="' . $thumbURL . '" alt="'. $caption .'" />';
			
			// write thumbnail
			echo '<div class="sfx">'.$thumb.'</div>';
			
			// next
			$i++;
		}
		// add padding
		echo '<div class="sfx_content_padding"></div>';
		
		// end of thumbnail
		echo '</div>';
		
		// end of container
		echo '</div>';
		
		// write parameters
		self::insertParameters($format , $i-1);
	}
	
	static function buildURL($album) {
		// get page
		$page = get_query_var('page_id');
		
		// build base link
		$link = './?page_id='.$page.'&albid=' . $album;
				
		// return link newly created
		return $link;
	}
	
	static function insertParameters($format, $length)
	{
		global $sfx_counter;
		
		// determine view mode
		if (empty($format['album'])) { $view = 'gallery'; } 
		else { $view = 'album'; }
		
		// by default do not attach download button
		$download = 'false';
		
		// get parameters
		if ($view == 'gallery') {
			$thumbnail_row = $format['albums_row'];
			$max_thumbnail_width = 200;
		} else {
			$thumbnail_row = $format['pictures_row'];
			$max_thumbnail_width = 200;
		}
		
		// set default parameters
		if (empty($rows_per_page)) { $rows_per_page = 0; }
		if (empty($thumbnail_row)) { $thumbnail_row = 5; }
		if (empty($max_thumbnail_width)) { $max_thumbnail_width = 160; }
		
		// write javascript
		echo '<script type="text/javascript">';
		echo 'sfxPageWidth[sfxPageWidth.length] = {';
		echo ' id:' . $sfx_counter;
		echo ',length:' . $length;
		echo ',thumbnail_row:' . $thumbnail_row;
		echo ',max_thumbnail_width:' . $max_thumbnail_width;
		echo '};';
        echo '</script>';
	}
}
?>