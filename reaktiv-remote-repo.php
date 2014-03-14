<?php
/*
Plugin Name: Reaktiv Remote Repo
Plugin URI: https://reaktivstudios.com
Description: Provides an API endpoint for handling product updates
Version: 0.0.1
Author: Reaktiv Studios
Author URI:  http://andrewnorcross.com
Contributors: norcross
*/

if ( ! defined( 'RKV_REPO_PLUGIN_DIR' ) )
	define( 'RKV_REPO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );



/**
 * Reaktiv_Remote_Repo Class
 *
 * Build out API functionality for updates
 *
 * @since  0.0.1
 */
class Reaktiv_Remote_Repo {

	/**
	 * API Version
	 */
	const VERSION = '0.0.1';

	/**
	 * Setup the construct
	 *
	 * @author Andrew Norcross
	 * @since 0.0.1
	 */
	public function __construct() {

		add_action			(	'plugins_loaded', 					array(	$this,	'load_files'			) 			);

		add_action			(	'init',								array(	$this,	'add_endpoint'			)			);
		add_action			(	'init',								array(	$this,	'process_download'		),	100		);
		add_action			(	'template_redirect',				array(	$this,	'process_query'			),	1		);
		add_filter			(	'query_vars',						array(	$this,	'query_vars'			)			);

		register_activation_hook	(	__FILE__,					array(	$this,	'activate'				)			);
		register_deactivation_hook	(	__FILE__,					array(	$this,	'deactivate'			)			);

	}

	 /**
	  * [activate description]
	  * @return [type] [description]
	  */
	public function activate() {

		flush_rewrite_rules();

	}

	/**
	 * [deactivate description]
	 * @return [type] [description]
	 */
	public function deactivate() {

		flush_rewrite_rules();
	}

	/**
	 * [load_files description]
	 * @return [type] [description]
	 */
	public function load_files() {

		include( 'lib/admin.php' );
		include( 'lib/postmeta.php' );
		include( 'lib/parse.php' );

	}

	/**
	 * Registers a new rewrite endpoint for accessing the API
	 *
	 * @access public
	 * @author Andrew Norcross
	 * @param array $rewrite_rules WordPress Rewrite Rules
	 * @since 0.0.1
	 */
	public function add_endpoint( $rewrite_rules ) {

		// run the endpoint filter with sanitization
		$endpoint	= apply_filters( 'rkv_remote_repo_endpoint', 'update' );
		$endpoint	= sanitize_key( $endpoint, 'update' );

		add_rewrite_endpoint( $endpoint, EP_ALL );
	}

	/**
	 * Registers query vars for API access
	 *
	 * @access public
	 * @since 0.0.1
	 * @author Andrew Norcross
	 * @param array $vars Query vars
	 * @return array $vars New query vars
	 */
	public function query_vars( $vars ) {

		$vars[] = 'version';
		$vars[] = 'unique';
		$vars[] = 'action';
		$vars[] = 'slug';

		// add custom vars
		$vars	= apply_filters( 'rkv_remote_repo_vars', $vars );

		return $vars;
	}

	/**
	 * confirm the product being passed exists
	 * @param  string $unique unique product ID
	 * @return string product ID or null
	 */
	static function get_product_id( $unique ) {

//		if( false === get_transient( 'rkv_repo_search_'.$unique ) ) :

			global $wpdb;

			$meta_key = '_rkv_repo_unique_id';

			$products = $wpdb->get_col( $wpdb->prepare(
				"
				SELECT      post_id
				FROM        $wpdb->postmeta
				WHERE       meta_key = %s
				AND 		meta_value = %s
				",
				$meta_key,
				$unique
			) );

			if ( ! $products ) {
				return false;
			}

			$product_id	= $products[0];

//			set_transient( 'rkv_repo_search_'.$unique, $product_id, 0 );

//		endif;

//		$product_id = get_transient( 'rkv_repo_search_'.$unique );

		return $product_id;

	}

	/**
	 * run various checks on the purchase request
	 * @param  array $wp_query API query being passed
	 * @return bool
	 */
	public function validate_request( $wp_query ) {

		do_action( 'rkv_remote_repo_before_validation', $wp_query );

		// check if action isnt one of our allowed
		if ( ! in_array( $wp_query->query_vars['action'], array( 'plugin_latest_version', 'plugin_information', 'update_counts' ) ) ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'ACTION_INCORRECT',
				'message'		=> 'The declared action was not understood.'
			);

			$this->output( $response );
			return false;

		endif;

		// check for missing slug
		if ( ! isset( $wp_query->query_vars['slug'] ) ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'SLUG_MISSING',
				'message'		=> 'The required slug was not provided.'
			);

			$this->output( $response );
			return false;

		endif;

		// check for missing product name
		if ( ! isset( $wp_query->query_vars['name'] ) ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'ITEM_NAME_MISSING',
				'message'		=> 'No item name has been supplied.'
			);

			$this->output( $response );
			return false;

		endif;

		// check for missing product unique
		if ( ! isset( $wp_query->query_vars['unique'] ) ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'ITEM_UNIQUE_MISSING',
				'message'		=> 'No unique ID has been supplied.'
			);

			$this->output( $response );
			return false;

		endif;

		// check for missing product version
		if ( ! isset( $wp_query->query_vars['version'] ) ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'ITEM_VERSION_MISSING',
				'message'		=> 'No item version has been supplied.'
			);

			$this->output( $response );
			return false;

		endif;

		// check if the product exists
		$product_id	= self::get_product_id( $wp_query->query_vars['unique'] );

		if ( ! $product_id ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'NOT_VALID_ITEM',
				'message'		=> 'The provided name does not match a valid item.'
			);

			$this->output( $response );
			return false;

		endif;

		// fetch product data for the rest
		$product_data	= $this->get_product_data( absint( $product_id ) );

		// check if the product has a version
		if ( ! $product_data['version'] ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'NO_PRODUCT_VERSION',
				'message'		=> 'The provided product has no available version.'
			);

			$this->output( $response );
			return false;

		endif;

		// check if the product file exists
		if ( ! $product_data['package'] ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'NO_PRODUCT_FILE',
				'message'		=> 'The provided product has no available file.'
			);

			$this->output( $response );
			return false;

		endif;

		/* TODO add custom validation methods */
		do_action( 'rkv_remote_repo_after_validation', $wp_query );

		// add some shit to the array
		$product_data['item_id']	= $product_id;
		$product_data['name']		= get_the_title( $product_id );

		// all checks passed, return product file
		return $product_data;

	}

	/**
	 * [screenshot_layout description]
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	static function screenshot_data( $data ) {

		if ( ! isset( $data['screenshots'] ) || isset( $data['screenshots'] ) && empty( $data['screenshots'] ) )
			return;

		// make sure we have an array first
		$screenshots	= (array) $data['screenshots'];

		// set the image size to return via filter
		$image_size	= apply_filters( 'rkv_remote_repo_screenshot_size', 'medium' );

		foreach ( $screenshots as $image_id ) :
			$file_data	= wp_get_attachment_image_src( $image_id, $image_size );
			$file_name	= get_the_title( $image_id );

			$image_data[]	= array(
				'url'	=> $file_data[0],
				'name'	=> $file_name
			);
		endforeach;


		$display	= '';

		$display	.= '<ol>';
		foreach ( $image_data as $image ) :
			$display	.= '<li>';
			$display	.= '<img src="'.esc_url( $image['url'] ).'">';
			$display	.= '<p>'.esc_attr( $image['name'] ).'</p>';
			$display	.= '</li>';
		endforeach;
		$display	.= '</ol>';

		return $display;

	}


	/**
	 * Sanitize Plugin Sections Data just to make sure it's proper data.
	 * This is a helper function to sanitize plugin sections data to send to user site.
	 * @since 0.1.0
	 * @return string of sanitized section data
	 */
	static function sanitize_section_data( $section ) {

		/* allowed tags */
		$allowed = array(
			'a' => array( 'href' => array(), 'title' => array(), 'target' => array() ),
			'abbr' => array( 'title' => array() ), 'acronym' => array( 'title' => array() ),
			'code' => array(), 'pre' => array(), 'em' => array(), 'strong' => array(),
			'div' => array(), 'p' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(),
			'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'h5' => array(), 'h6' => array(),
			'img' => array( 'src' => array(), 'class' => array(), 'alt' => array() )
		);

		$allowed	= apply_filters( 'rkv_remote_repo_allowed_tags', $allowed );

		$content = wp_kses( $section, $allowed );

		return $content;
	}

	/**
	 * [get_parse_data description]
	 * @param  [type] $section [description]
	 * @param  [type] $data    [description]
	 * @return [type]          [description]
	 */
	static function get_parse_data( $file, $key ) {

		// load the WP Readme Parser and parse
		$parser		= new WordPress_Readme_Parser();
		$readme		= $parser->parse_readme( $file );

		if ( ! $readme )
			return;

		if ( ! isset( $readme[ $key ] ) || isset( $readme[ $key ] ) && empty( $readme[ $key ] ) )
			return;

		return $readme[ $key ];

	}

	/**
	 * [get_section_data description]
	 * @param  [type] $product_id [description]
	 * @param  [type] $data       [description]
	 * @param  [type] $key        [description]
	 * @return [type]             [description]
	 */
	static function get_section_data( $product_id, $data, $key ) {

		// set an initial fallback for the item being requested
		$item	= isset( $data[ $key ] ) && ! empty( $data[ $key ] ) ? $data[ $key ] : '';

		// run a check for the readme file
		$check	= get_post_meta( $product_id, '_rkv_repo_readme_file', true );
		$file	= isset( $data['readme'] ) && ! empty( $data['readme'] ) ? esc_url( $data['readme'] ) : '';

		// if no file, return the backup data
		if ( ! $check || empty ( $file ) )
			return $item;

		// check to make sure the readme parse is there
		if ( ! class_exists( 'WordPress_Readme_Parser' ) )
			return $item;

		$sections	= self::get_parse_data( $file, 'sections' );

		// bail if we don't have any sections
		if ( ! isset( $sections ) )
			return $item;

		// bail if we don't have the section we want
		if ( ! isset( $sections[ $key ] ) )
			return $item;

		// run the section content through the filter
		$content	= self::sanitize_section_data( $sections[ $key ] );

		// return the section being requested
		return $content;

	}

	/**
	 * [get_lineitem_data description]
	 * @param  [type] $product_id [description]
	 * @param  [type] $data       [description]
	 * @param  [type] $key        [description]
	 * @return [type]             [description]
	 */
	static function get_lineitem_data( $product_id, $data, $key ) {

		// set an initial fallback for the item being requested
		$item	= isset( $data[ $key ] ) && ! empty( $data[ $key ] ) ? $data[ $key ] : '';

		// run a check for the readme file
		$check	= get_post_meta( $product_id, '_rkv_repo_readme_file', true );
		$file	= isset( $data['readme'] ) && ! empty( $data['readme'] ) ? esc_url( $data['readme'] ) : '';

		// if no file, return the backup data
		if ( ! $check || empty ( $file ) )
			return $item;

		// check to make sure the readme parse is there
		if ( ! class_exists( 'WordPress_Readme_Parser' ) )
			return $item;

		// do some key swapping
		$rpkey	= array( 'requires', 'tested' );
		$rdkey	= array( 'requires_at_least', 'tested_up_to' );

		$key	= str_replace( $rpkey, $rdkey, $key );

		$data	= self::get_parse_data( $file, $key );

		// bail if we don't have anything
		if ( ! isset( $data ) )
			return $item;

		return $data;

	}

	/**
	 * fetch the product data
	 * @param  int $product_id product ID
	 * @return array
	 */
	public function get_product_data( $product_id, $meta = false ) {

		// get the data array
		$data		= get_post_meta( $product_id, '_rkv_repo_data', true );

		// get the readme parsed items
		$description	= self::get_section_data( $product_id, $data, 'description' );
		$faq			= self::get_section_data( $product_id, $data, 'frequently_asked_questions' );
		$changelog		= self::get_section_data( $product_id, $data, 'changelog' );
		$installation	= self::get_section_data( $product_id, $data, 'installation' );
		$requires		= self::get_lineitem_data( $product_id, $data, 'requires' );
		$tested			= self::get_lineitem_data( $product_id, $data, 'tested' );

		// pull items from post meta
		$package	= isset( $data['package'] )			? esc_url( $data['package'] )			: '';
		$homepage	= isset( $data['homepage'] )		? esc_url( $data['homepage'] )			: '';
		$version	= isset( $data['version'] )			? esc_html( $data['version'] )			: '';
		$author		= isset( $data['author'] )			? esc_html( $data['author'] )			: '';
		$profile	= isset( $data['author_profile'] )	? esc_url( $data['author_profile'] )	: '';

		$rating		= isset( $data['rating'] )			? $data['rating']		: '';
		$rcount		= isset( $data['num_ratings'] )		? $data['num_ratings']	: '';
		$dcount		= isset( $data['downloaded'] )		? $data['downloaded']	: '';

		// some fancy contributor stuff
		$contributors	= isset( $data['contributors'] )	? esc_html( $data['contributors'] )		: '';
		if ( ! empty( $contributors ) )
			$contributors	= explode( ',', $contributors );

		// do some fancy timestamp stuff
		$add_stamp		= isset( $data['added'] )			? $data['added']		: '';
		$upd_stamp		= isset( $data['last_updated'] )	? $data['last_updated']	: '';

		$add_show		= ! empty( $add_stamp ) ? date( 'Y-m-d', floatval( $add_stamp ) ) : '';
		$upd_show		= ! empty( $upd_stamp ) ? date( 'Y-m-d', floatval( $upd_stamp ) ) : '';

		// fetch our screenshots
		$screenshots	= self::screenshot_data( $data );

		// build out the big array
		$product_data	= array(
			'package'		=> $package,
			'homepage'		=> $homepage,
			'description'	=> $description,
			'faq'			=> $faq,
			'installation'	=> $installation,
			'screenshots'	=> $screenshots,
			'changelog'		=> $changelog,
			'version'		=> $version,
			'tested'		=> $tested,
			'requires'		=> $requires,
			'added'			=> $add_show,
			'updated'		=> $upd_show,
			'author'		=> $author,
			'profile'		=> $profile,
			'contributors'	=> $contributors,
			'rating'		=> $rating,
			'num_ratings'	=> $rcount,
			'downloaded'	=> $dcount,
		);

		$product_data	= apply_filters( 'rkv_remote_repo_product_data', $product_data, $product_id );

		// return single bit of info if requested
		if ( $meta && isset( $product_data[ $meta ] ) )
			return $product_data[ $meta ];

		return $product_data;

	}

	/**
	 * [get_display_sections description]
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	static function get_display_sections( $data ) {

		// build sections
		$sections = array(
			'description'	=> $data['description'],
			'installation'	=> $data['installation'],
			'screenshots'	=> $data['screenshots'],
			'changelog'		=> $data['changelog'],
			'faq'			=> $data['faq'],
		);

		$sections	= apply_filters( 'rkv_remote_repo_display_sections', $sections, $data );

		return $sections;

	}


	/**
	 * [process_plugin_version description]
	 * @param  [type] $data [description]
	 * @param  [type] $slug [description]
	 * @return [type]       [description]
	 */
	public function process_plugin_version( $data, $slug ) {

		$fields	= array(
			'slug'					=> $slug,
			'new_version'			=> $data['version'],
			'url'					=> $data['homepage'],
			'package'				=> $data['package'],
		);

		$fields	= apply_filters( 'rkv_remote_repo_plugin_version', $fields, $slug );

		return array(
			'success'	=> true,
			'fields'	=> $fields
		);

	}

	/**
	 * [process_plugin_details description]
	 * @param  [type] $data [description]
	 * @param  [type] $slug [description]
	 * @return [type]       [description]
	 */
	public function process_plugin_details( $data, $slug ) {

		$sections	= self::get_display_sections( $data );

		$fields	= array(
			'name'					=> $data['name'],
			'slug'					=> $slug,
			'version'				=> $data['version'],
			'author'				=> $data['author'],
			'author_profile'		=> $data['profile'],
			'contributors'			=> $data['contributors'],
			'requires'				=> $data['requires'],
			'tested'				=> $data['tested'],
			'rating'				=> $data['rating'],
			'num_ratings'			=> $data['num_ratings'],
			'downloaded'			=> $data['downloaded'],
			'added'					=> $data['added'],
			'last_updated'			=> $data['updated'],
			'homepage'				=> $data['homepage'],
			'download_link'			=> $data['package'],
			'sections'				=> $sections,
		);


		$fields	= apply_filters( 'rkv_remote_repo_plugin_details', $fields, $slug );

		return array(
			'success'	=> true,
			'fields'	=> $fields
		);

	}

	/**
	 * [process_plugin_counts description]
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	public function process_plugin_counts( $data ) {

		// check our bypass filter first
		if ( false === apply_filters( 'rkv_remote_repo_plugin_counts', true ) )
			return false;

		// pull the current item meta
		$meta		= get_post_meta( $data['item_id'], '_rkv_repo_data', true );

		// get our count (or set to zero)
		$current	= isset( $meta['downloaded'] ) ? absint( $meta['downloaded'] ) : absint( 0 );

		// increase it by one
		$update		= $current + 1;

		// add it back into the array
		$meta['downloaded']	= absint( $update );

		// update item with new count
		update_post_meta( $data['item_id'], '_rkv_repo_data', $meta );

		// get out
		return true;

	}

	/**
	 * Listens for the API and then processes the API requests
	 *
	 * @access public
	 * @author Andrew Norcross
	 * @global $wp_query
	 * @since 0.0.1
	 * @return void
	 */
	public function process_query() {

		global $wp_query;

		// Check for update var. Get out if not present
		if ( ! isset( $wp_query->query_vars['update'] ) )
			return false;

		// bail if no action is set
		if ( ! isset( $wp_query->query_vars['action'] ) )
			return false;

		// run my validation checks
		$data	= $this->validate_request( $wp_query );
		if ( ! $data )
			return false;

		$process	= false;

		if ( $wp_query->query_vars['action']	== 'plugin_latest_version' )
			$process	= $this->process_plugin_version( $data, $wp_query->query_vars['slug'] );

		if ( $wp_query->query_vars['action']	== 'plugin_information' )
			$process	= $this->process_plugin_details( $data, $wp_query->query_vars['slug'] );

		if ( $wp_query->query_vars['action']	== 'update_counts' )
			$process	= $this->process_plugin_counts( $data );

		if ( ! $process )
			return false;

		// Send out data to the output function
		$this->output( $process );

	}

	/**
	 * Output data in JSON
	 *
	 * @author Andrew Norcross
	 * @since 0.0.1
	 * @global $wp_query
	 *
	 * @param int $process
	 */

	public function output( $process ) {

		header( 'HTTP/1.1 200' );
		header( 'Content-type: application/json; charset=utf-8' );
		echo json_encode( $process );

		die();

	}

	public function process_download() {

		$args = apply_filters( 'rkv_remote_repo_process_download_args', array(
			'download_key' => ( isset( $_GET['download_key'] ) )  ? $_GET['download_key'] : false,
		) );

		if ( ! isset( $_GET['download_key'] ) ) {
			return false;
		}

		extract( $args );

		// Verify that the download key exists
		$product_id = self::get_product_id( $download_key );

		if ( $product_id ) {

			$product = get_post( $product_id );

			if ( ! $product ) {
				wp_die(	'No download exists.' );
			}

			$package = $this->get_product_data( $product_id, 'package' );

			$file_extension = $this->get_file_extension( $package );
			$ctype          = $this->get_file_ctype( $file_extension );

			@session_write_close();
			if( function_exists( 'apache_setenv' ) ) @apache_setenv('no-gzip', 1);
			@ini_set( 'zlib.output_compression', 'Off' );

			nocache_headers();
			header("Robots: none");
			header("Content-Type: " . $ctype . "");
			header("Content-Description: File Transfer");
			header("Content-Disposition: attachment; filename=\"" . apply_filters( 'rkv_remote_repo_requested_file_name', basename( $package ) ) . "\";");
			header("Content-Transfer-Encoding: binary");

			header("Location: " . $package);

			exit();

		} else {
			wp_die( 'No download exists.' );
		}

		exit();

	}

	/**
	 * Get File Extension
	 *
	 * Returns the file extension of a filename.
	 *
	 * (From EDD 1.9.8)
	 *
	 * @since 1.0
	 *
	 * @param unknown $str File name
	 *
	 * @return mixed File extension
	 */
	private function get_file_extension( $str ) {
		$parts = explode( '.', $str );
		return end( $parts );
	}

	/**
	 * Get the file content type
	 *
	 * (From EDD 1.9.8)
	 *
	 * @access   public
	 * @param    string    file extension
	 * @return   string
	 */
	function get_file_ctype( $extension ) {
		switch( $extension ):
			case 'ac'       : $ctype = "application/pkix-attr-cert"; break;
			case 'adp'      : $ctype = "audio/adpcm"; break;
			case 'ai'       : $ctype = "application/postscript"; break;
			case 'aif'      : $ctype = "audio/x-aiff"; break;
			case 'aifc'     : $ctype = "audio/x-aiff"; break;
			case 'aiff'     : $ctype = "audio/x-aiff"; break;
			case 'air'      : $ctype = "application/vnd.adobe.air-application-installer-package+zip"; break;
			case 'apk'      : $ctype = "application/vnd.android.package-archive"; break;
			case 'asc'      : $ctype = "application/pgp-signature"; break;
			case 'atom'     : $ctype = "application/atom+xml"; break;
			case 'atomcat'  : $ctype = "application/atomcat+xml"; break;
			case 'atomsvc'  : $ctype = "application/atomsvc+xml"; break;
			case 'au'       : $ctype = "audio/basic"; break;
			case 'aw'       : $ctype = "application/applixware"; break;
			case 'avi'      : $ctype = "video/x-msvideo"; break;
			case 'bcpio'    : $ctype = "application/x-bcpio"; break;
			case 'bin'      : $ctype = "application/octet-stream"; break;
			case 'bmp'      : $ctype = "image/bmp"; break;
			case 'boz'      : $ctype = "application/x-bzip2"; break;
			case 'bpk'      : $ctype = "application/octet-stream"; break;
			case 'bz'       : $ctype = "application/x-bzip"; break;
			case 'bz2'      : $ctype = "application/x-bzip2"; break;
			case 'ccxml'    : $ctype = "application/ccxml+xml"; break;
			case 'cdmia'    : $ctype = "application/cdmi-capability"; break;
			case 'cdmic'    : $ctype = "application/cdmi-container"; break;
			case 'cdmid'    : $ctype = "application/cdmi-domain"; break;
			case 'cdmio'    : $ctype = "application/cdmi-object"; break;
			case 'cdmiq'    : $ctype = "application/cdmi-queue"; break;
			case 'cdf'      : $ctype = "application/x-netcdf"; break;
			case 'cer'      : $ctype = "application/pkix-cert"; break;
			case 'cgm'      : $ctype = "image/cgm"; break;
			case 'class'    : $ctype = "application/octet-stream"; break;
			case 'cpio'     : $ctype = "application/x-cpio"; break;
			case 'cpt'      : $ctype = "application/mac-compactpro"; break;
			case 'crl'      : $ctype = "application/pkix-crl"; break;
			case 'csh'      : $ctype = "application/x-csh"; break;
			case 'css'      : $ctype = "text/css"; break;
			case 'cu'       : $ctype = "application/cu-seeme"; break;
			case 'davmount' : $ctype = "application/davmount+xml"; break;
			case 'dbk'      : $ctype = "application/docbook+xml"; break;
			case 'dcr'      : $ctype = "application/x-director"; break;
			case 'deploy'   : $ctype = "application/octet-stream"; break;
			case 'dif'      : $ctype = "video/x-dv"; break;
			case 'dir'      : $ctype = "application/x-director"; break;
			case 'dist'     : $ctype = "application/octet-stream"; break;
			case 'distz'    : $ctype = "application/octet-stream"; break;
			case 'djv'      : $ctype = "image/vnd.djvu"; break;
			case 'djvu'     : $ctype = "image/vnd.djvu"; break;
			case 'dll'      : $ctype = "application/octet-stream"; break;
			case 'dmg'      : $ctype = "application/octet-stream"; break;
			case 'dms'      : $ctype = "application/octet-stream"; break;
			case 'doc'      : $ctype = "application/msword"; break;
			case 'docx'     : $ctype = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"; break;
			case 'dotx'     : $ctype = "application/vnd.openxmlformats-officedocument.wordprocessingml.template"; break;
			case 'dssc'     : $ctype = "application/dssc+der"; break;
			case 'dtd'      : $ctype = "application/xml-dtd"; break;
			case 'dump'     : $ctype = "application/octet-stream"; break;
			case 'dv'       : $ctype = "video/x-dv"; break;
			case 'dvi'      : $ctype = "application/x-dvi"; break;
			case 'dxr'      : $ctype = "application/x-director"; break;
			case 'ecma'     : $ctype = "application/ecmascript"; break;
			case 'elc'      : $ctype = "application/octet-stream"; break;
			case 'emma'     : $ctype = "application/emma+xml"; break;
			case 'eps'      : $ctype = "application/postscript"; break;
			case 'epub'     : $ctype = "application/epub+zip"; break;
			case 'etx'      : $ctype = "text/x-setext"; break;
			case 'exe'      : $ctype = "application/octet-stream"; break;
			case 'exi'      : $ctype = "application/exi"; break;
			case 'ez'       : $ctype = "application/andrew-inset"; break;
			case 'f4v'      : $ctype = "video/x-f4v"; break;
			case 'fli'      : $ctype = "video/x-fli"; break;
			case 'flv'      : $ctype = "video/x-flv"; break;
			case 'gif'      : $ctype = "image/gif"; break;
			case 'gml'      : $ctype = "application/srgs"; break;
			case 'gpx'      : $ctype = "application/gml+xml"; break;
			case 'gram'     : $ctype = "application/gpx+xml"; break;
			case 'grxml'    : $ctype = "application/srgs+xml"; break;
			case 'gtar'     : $ctype = "application/x-gtar"; break;
			case 'gxf'      : $ctype = "application/gxf"; break;
			case 'hdf'      : $ctype = "application/x-hdf"; break;
			case 'hqx'      : $ctype = "application/mac-binhex40"; break;
			case 'htm'      : $ctype = "text/html"; break;
			case 'html'     : $ctype = "text/html"; break;
			case 'ice'      : $ctype = "x-conference/x-cooltalk"; break;
			case 'ico'      : $ctype = "image/x-icon"; break;
			case 'ics'      : $ctype = "text/calendar"; break;
			case 'ief'      : $ctype = "image/ief"; break;
			case 'ifb'      : $ctype = "text/calendar"; break;
			case 'iges'     : $ctype = "model/iges"; break;
			case 'igs'      : $ctype = "model/iges"; break;
			case 'ink'      : $ctype = "application/inkml+xml"; break;
			case 'inkml'    : $ctype = "application/inkml+xml"; break;
			case 'ipfix'    : $ctype = "application/ipfix"; break;
			case 'jar'      : $ctype = "application/java-archive"; break;
			case 'jnlp'     : $ctype = "application/x-java-jnlp-file"; break;
			case 'jp2'      : $ctype = "image/jp2"; break;
			case 'jpe'      : $ctype = "image/jpeg"; break;
			case 'jpeg'     : $ctype = "image/jpeg"; break;
			case 'jpg'      : $ctype = "image/jpeg"; break;
			case 'js'       : $ctype = "application/javascript"; break;
			case 'json'     : $ctype = "application/json"; break;
			case 'jsonml'   : $ctype = "application/jsonml+json"; break;
			case 'kar'      : $ctype = "audio/midi"; break;
			case 'latex'    : $ctype = "application/x-latex"; break;
			case 'lha'      : $ctype = "application/octet-stream"; break;
			case 'lrf'      : $ctype = "application/octet-stream"; break;
			case 'lzh'      : $ctype = "application/octet-stream"; break;
			case 'lostxml'  : $ctype = "application/lost+xml"; break;
			case 'm3u'      : $ctype = "audio/x-mpegurl"; break;
			case 'm4a'      : $ctype = "audio/mp4a-latm"; break;
			case 'm4b'      : $ctype = "audio/mp4a-latm"; break;
			case 'm4p'      : $ctype = "audio/mp4a-latm"; break;
			case 'm4u'      : $ctype = "video/vnd.mpegurl"; break;
			case 'm4v'      : $ctype = "video/x-m4v"; break;
			case 'm21'      : $ctype = "application/mp21"; break;
			case 'ma'       : $ctype = "application/mathematica"; break;
			case 'mac'      : $ctype = "image/x-macpaint"; break;
			case 'mads'     : $ctype = "application/mads+xml"; break;
			case 'man'      : $ctype = "application/x-troff-man"; break;
			case 'mar'      : $ctype = "application/octet-stream"; break;
			case 'mathml'   : $ctype = "application/mathml+xml"; break;
			case 'mbox'     : $ctype = "application/mbox"; break;
			case 'me'       : $ctype = "application/x-troff-me"; break;
			case 'mesh'     : $ctype = "model/mesh"; break;
			case 'metalink' : $ctype = "application/metalink+xml"; break;
			case 'meta4'    : $ctype = "application/metalink4+xml"; break;
			case 'mets'     : $ctype = "application/mets+xml"; break;
			case 'mid'      : $ctype = "audio/midi"; break;
			case 'midi'     : $ctype = "audio/midi"; break;
			case 'mif'      : $ctype = "application/vnd.mif"; break;
			case 'mods'     : $ctype = "application/mods+xml"; break;
			case 'mov'      : $ctype = "video/quicktime"; break;
			case 'movie'    : $ctype = "video/x-sgi-movie"; break;
			case 'm1v'      : $ctype = "video/mpeg"; break;
			case 'm2v'      : $ctype = "video/mpeg"; break;
			case 'mp2'      : $ctype = "audio/mpeg"; break;
			case 'mp2a'     : $ctype = "audio/mpeg"; break;
			case 'mp21'     : $ctype = "application/mp21"; break;
			case 'mp3'      : $ctype = "audio/mpeg"; break;
			case 'mp3a'     : $ctype = "audio/mpeg"; break;
			case 'mp4'      : $ctype = "video/mp4"; break;
			case 'mp4s'     : $ctype = "application/mp4"; break;
			case 'mpe'      : $ctype = "video/mpeg"; break;
			case 'mpeg'     : $ctype = "video/mpeg"; break;
			case 'mpg'      : $ctype = "video/mpeg"; break;
			case 'mpg4'     : $ctype = "video/mpeg"; break;
			case 'mpga'     : $ctype = "audio/mpeg"; break;
			case 'mrc'      : $ctype = "application/marc"; break;
			case 'mrcx'     : $ctype = "application/marcxml+xml"; break;
			case 'ms'       : $ctype = "application/x-troff-ms"; break;
			case 'mscml'    : $ctype = "application/mediaservercontrol+xml"; break;
			case 'msh'      : $ctype = "model/mesh"; break;
			case 'mxf'      : $ctype = "application/mxf"; break;
			case 'mxu'      : $ctype = "video/vnd.mpegurl"; break;
			case 'nc'       : $ctype = "application/x-netcdf"; break;
			case 'oda'      : $ctype = "application/oda"; break;
			case 'oga'      : $ctype = "application/ogg"; break;
			case 'ogg'      : $ctype = "application/ogg"; break;
			case 'ogx'      : $ctype = "application/ogg"; break;
			case 'omdoc'    : $ctype = "application/omdoc+xml"; break;
			case 'onetoc'   : $ctype = "application/onenote"; break;
			case 'onetoc2'  : $ctype = "application/onenote"; break;
			case 'onetmp'   : $ctype = "application/onenote"; break;
			case 'onepkg'   : $ctype = "application/onenote"; break;
			case 'opf'      : $ctype = "application/oebps-package+xml"; break;
			case 'oxps'     : $ctype = "application/oxps"; break;
			case 'p7c'      : $ctype = "application/pkcs7-mime"; break;
			case 'p7m'      : $ctype = "application/pkcs7-mime"; break;
			case 'p7s'      : $ctype = "application/pkcs7-signature"; break;
			case 'p8'       : $ctype = "application/pkcs8"; break;
			case 'p10'      : $ctype = "application/pkcs10"; break;
			case 'pbm'      : $ctype = "image/x-portable-bitmap"; break;
			case 'pct'      : $ctype = "image/pict"; break;
			case 'pdb'      : $ctype = "chemical/x-pdb"; break;
			case 'pdf'      : $ctype = "application/pdf"; break;
			case 'pki'      : $ctype = "application/pkixcmp"; break;
			case 'pkipath'  : $ctype = "application/pkix-pkipath"; break;
			case 'pfr'      : $ctype = "application/font-tdpfr"; break;
			case 'pgm'      : $ctype = "image/x-portable-graymap"; break;
			case 'pgn'      : $ctype = "application/x-chess-pgn"; break;
			case 'pgp'      : $ctype = "application/pgp-encrypted"; break;
			case 'pic'      : $ctype = "image/pict"; break;
			case 'pict'     : $ctype = "image/pict"; break;
			case 'pkg'      : $ctype = "application/octet-stream"; break;
			case 'png'      : $ctype = "image/png"; break;
			case 'pnm'      : $ctype = "image/x-portable-anymap"; break;
			case 'pnt'      : $ctype = "image/x-macpaint"; break;
			case 'pntg'     : $ctype = "image/x-macpaint"; break;
			case 'pot'      : $ctype = "application/vnd.ms-powerpoint"; break;
			case 'potx'     : $ctype = "application/vnd.openxmlformats-officedocument.presentationml.template"; break;
			case 'ppm'      : $ctype = "image/x-portable-pixmap"; break;
			case 'pps'      : $ctype = "application/vnd.ms-powerpoint"; break;
			case 'ppsx'     : $ctype = "application/vnd.openxmlformats-officedocument.presentationml.slideshow"; break;
			case 'ppt'      : $ctype = "application/vnd.ms-powerpoint"; break;
			case 'pptx'     : $ctype = "application/vnd.openxmlformats-officedocument.presentationml.presentation"; break;
			case 'prf'      : $ctype = "application/pics-rules"; break;
			case 'ps'       : $ctype = "application/postscript"; break;
			case 'psd'      : $ctype = "image/photoshop"; break;
			case 'qt'       : $ctype = "video/quicktime"; break;
			case 'qti'      : $ctype = "image/x-quicktime"; break;
			case 'qtif'     : $ctype = "image/x-quicktime"; break;
			case 'ra'       : $ctype = "audio/x-pn-realaudio"; break;
			case 'ram'      : $ctype = "audio/x-pn-realaudio"; break;
			case 'ras'      : $ctype = "image/x-cmu-raster"; break;
			case 'rdf'      : $ctype = "application/rdf+xml"; break;
			case 'rgb'      : $ctype = "image/x-rgb"; break;
			case 'rm'       : $ctype = "application/vnd.rn-realmedia"; break;
			case 'rmi'      : $ctype = "audio/midi"; break;
			case 'roff'     : $ctype = "application/x-troff"; break;
			case 'rss'      : $ctype = "application/rss+xml"; break;
			case 'rtf'      : $ctype = "text/rtf"; break;
			case 'rtx'      : $ctype = "text/richtext"; break;
			case 'sgm'      : $ctype = "text/sgml"; break;
			case 'sgml'     : $ctype = "text/sgml"; break;
			case 'sh'       : $ctype = "application/x-sh"; break;
			case 'shar'     : $ctype = "application/x-shar"; break;
			case 'sig'      : $ctype = "application/pgp-signature"; break;
			case 'silo'     : $ctype = "model/mesh"; break;
			case 'sit'      : $ctype = "application/x-stuffit"; break;
			case 'skd'      : $ctype = "application/x-koan"; break;
			case 'skm'      : $ctype = "application/x-koan"; break;
			case 'skp'      : $ctype = "application/x-koan"; break;
			case 'skt'      : $ctype = "application/x-koan"; break;
			case 'sldx'     : $ctype = "application/vnd.openxmlformats-officedocument.presentationml.slide"; break;
			case 'smi'      : $ctype = "application/smil"; break;
			case 'smil'     : $ctype = "application/smil"; break;
			case 'snd'      : $ctype = "audio/basic"; break;
			case 'so'       : $ctype = "application/octet-stream"; break;
			case 'spl'      : $ctype = "application/x-futuresplash"; break;
			case 'spx'      : $ctype = "audio/ogg"; break;
			case 'src'      : $ctype = "application/x-wais-source"; break;
			case 'stk'      : $ctype = "application/hyperstudio"; break;
			case 'sv4cpio'  : $ctype = "application/x-sv4cpio"; break;
			case 'sv4crc'   : $ctype = "application/x-sv4crc"; break;
			case 'svg'      : $ctype = "image/svg+xml"; break;
			case 'swf'      : $ctype = "application/x-shockwave-flash"; break;
			case 't'        : $ctype = "application/x-troff"; break;
			case 'tar'      : $ctype = "application/x-tar"; break;
			case 'tcl'      : $ctype = "application/x-tcl"; break;
			case 'tex'      : $ctype = "application/x-tex"; break;
			case 'texi'     : $ctype = "application/x-texinfo"; break;
			case 'texinfo'  : $ctype = "application/x-texinfo"; break;
			case 'tif'      : $ctype = "image/tiff"; break;
			case 'tiff'     : $ctype = "image/tiff"; break;
			case 'torrent'  : $ctype = "application/x-bittorrent"; break;
			case 'tr'       : $ctype = "application/x-troff"; break;
			case 'tsv'      : $ctype = "text/tab-separated-values"; break;
			case 'txt'      : $ctype = "text/plain"; break;
			case 'ustar'    : $ctype = "application/x-ustar"; break;
			case 'vcd'      : $ctype = "application/x-cdlink"; break;
			case 'vrml'     : $ctype = "model/vrml"; break;
			case 'vsd'      : $ctype = "application/vnd.visio"; break;
			case 'vss'      : $ctype = "application/vnd.visio"; break;
			case 'vst'      : $ctype = "application/vnd.visio"; break;
			case 'vsw'      : $ctype = "application/vnd.visio"; break;
			case 'vxml'     : $ctype = "application/voicexml+xml"; break;
			case 'wav'      : $ctype = "audio/x-wav"; break;
			case 'wbmp'     : $ctype = "image/vnd.wap.wbmp"; break;
			case 'wbmxl'    : $ctype = "application/vnd.wap.wbxml"; break;
			case 'wm'       : $ctype = "video/x-ms-wm"; break;
			case 'wml'      : $ctype = "text/vnd.wap.wml"; break;
			case 'wmlc'     : $ctype = "application/vnd.wap.wmlc"; break;
			case 'wmls'     : $ctype = "text/vnd.wap.wmlscript"; break;
			case 'wmlsc'    : $ctype = "application/vnd.wap.wmlscriptc"; break;
			case 'wmv'      : $ctype = "video/x-ms-wmv"; break;
			case 'wmx'      : $ctype = "video/x-ms-wmx"; break;
			case 'wrl'      : $ctype = "model/vrml"; break;
			case 'xbm'      : $ctype = "image/x-xbitmap"; break;
			case 'xdssc'    : $ctype = "application/dssc+xml"; break;
			case 'xer'      : $ctype = "application/patch-ops-error+xml"; break;
			case 'xht'      : $ctype = "application/xhtml+xml"; break;
			case 'xhtml'    : $ctype = "application/xhtml+xml"; break;
			case 'xla'      : $ctype = "application/vnd.ms-excel"; break;
			case 'xlam'     : $ctype = "application/vnd.ms-excel.addin.macroEnabled.12"; break;
			case 'xlc'      : $ctype = "application/vnd.ms-excel"; break;
			case 'xlm'      : $ctype = "application/vnd.ms-excel"; break;
			case 'xls'      : $ctype = "application/vnd.ms-excel"; break;
			case 'xlsx'     : $ctype = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"; break;
			case 'xlsb'     : $ctype = "application/vnd.ms-excel.sheet.binary.macroEnabled.12"; break;
			case 'xlt'      : $ctype = "application/vnd.ms-excel"; break;
			case 'xltx'     : $ctype = "application/vnd.openxmlformats-officedocument.spreadsheetml.template"; break;
			case 'xlw'      : $ctype = "application/vnd.ms-excel"; break;
			case 'xml'      : $ctype = "application/xml"; break;
			case 'xpm'      : $ctype = "image/x-xpixmap"; break;
			case 'xsl'      : $ctype = "application/xml"; break;
			case 'xslt'     : $ctype = "application/xslt+xml"; break;
			case 'xul'      : $ctype = "application/vnd.mozilla.xul+xml"; break;
			case 'xwd'      : $ctype = "image/x-xwindowdump"; break;
			case 'xyz'      : $ctype = "chemical/x-xyz"; break;
			case 'zip'      : $ctype = "application/zip"; break;
			default         : $ctype = "application/force-download";
		endswitch;

		return apply_filters( 'rkv_remote_repo_file_ctype', $ctype );
	}

}

new Reaktiv_Remote_Repo();