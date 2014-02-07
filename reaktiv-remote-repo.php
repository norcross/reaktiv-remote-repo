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



include( 'lib/admin.php' );
include( 'lib/postmeta.php' );
include( 'lib/parse.php' );


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

		add_action(	'init',                    array(	$this,	'add_endpoint'			)		);
		add_action( 'template_redirect',       array(	$this,	'process_query'			),	1	);
		add_filter( 'query_vars',              array(	$this,	'query_vars'			)		);

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
		$endpoint	= sanitize_html_class( $endpoint, 'update' );

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

		$vars[] = 'item';
		$vars[] = 'version';
		$vars[] = 'action';
		$vars[] = 'slug';

		// add custom vars
		$vars	= apply_filters( 'rkv_remote_repo_vars', $vars );

		return $vars;
	}

	/**
	 * confirm the product being passed exists
	 * @param  string $name product name
	 * @return string product ID or null
	 */
	static function get_product_id( $name ) {

		$data	= get_page_by_title( urldecode( $name ), OBJECT, 'repo-items' );

		if ( ! $data )
			return;

		return $data->ID;

	}

	/**
	 * run various checks on the purchase request
	 * @param  array $wp_query API query being passed
	 * @return bool
	 */
	public function validate_request( $wp_query ) {

		// check for missing action
		if ( ! isset( $wp_query->query_vars['action'] ) ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'ACTION_MISSING',
				'message'		=> 'No action was declared.'
			);

			$this->output( $response );
			return false;

		endif;

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
		$product_id	= self::get_product_id( $wp_query->query_vars['item'] );
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
		$product_data	= $this->get_product_data( $product_id );

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

		/* TODO add custom validation method */

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
		$allowedtags = array(
			'a' => array( 'href' => array(), 'title' => array(), 'target' => array() ),
			'abbr' => array( 'title' => array() ), 'acronym' => array( 'title' => array() ),
			'code' => array(), 'pre' => array(), 'em' => array(), 'strong' => array(),
			'div' => array(), 'p' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(),
			'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'h5' => array(), 'h6' => array(),
			'img' => array( 'src' => array(), 'class' => array(), 'alt' => array() )
		);

		$content = wp_kses( $section, $allowedtags );

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
			return;

		// run my validation checks
		$data	= $this->validate_request( $wp_query );
		if ( ! $data )
			return;

		$process	= false;

		if ( $wp_query->query_vars['action']	== 'plugin_latest_version' )
			$process	= $this->process_plugin_version( $data, $wp_query->query_vars['slug'] );

		if ( $wp_query->query_vars['action']	== 'plugin_information' )
			$process	= $this->process_plugin_details( $data, $wp_query->query_vars['slug'] );

		if ( $wp_query->query_vars['action']	== 'update_counts' )
			$process	= $this->process_plugin_counts( $data );

		if ( ! $process )
			return;

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


}

new Reaktiv_Remote_Repo();