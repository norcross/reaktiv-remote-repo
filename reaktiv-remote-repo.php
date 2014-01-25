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



include( 'lib/admin.php' );

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
		$vars[] = 'product';
		$vars[] = 'version';
		$vars[] = 'key';
		$vars[] = 'action';
		$vars[] = 'slug';

		// add custom vars
		$vars	= apply_filters( 'rkv_remote_repo_vars', $vars );

		return $vars;
	}

	/**
	 * confirm the product being passed exists
	 * @param  string $product product name
	 * @return string product ID or null
	 */
	public function get_product_id( $product ) {

		$data	= get_page_by_title( urldecode( $product ), OBJECT, 'repo-items' );

		if ( ! $data )
			return;

		return $data->ID;

	}

	/**
	 * fetch the product data
	 * @param  int $product_id product ID
	 * @return array
	 */
	public function get_product_data( $product_id, $meta = false ) {

		// get the data array
		$data		= get_post_meta( $product_id, '_rkv_repo_data', true );

		$package		= isset( $data['package'] )		&& ! empty( $data['package'] )		? esc_url( $data['package'] )		: '';
		$location		= isset( $data['location'] )	&& ! empty( $data['location'] )		? esc_url( $data['location'] )		: '';
		$description	= isset( $data['description'] )	&& ! empty( $data['description'] )	? esc_attr( $data['description'] )	: '';
		$changelog		= isset( $data['changelog'] )	&& ! empty( $data['changelog'] )	? esc_attr( $data['changelog'] )	: '';
		$version		= isset( $data['version'] )		&& ! empty( $data['version'] )		? esc_attr( $data['version'] )		: '';
		$requires		= isset( $data['requires'] )	&& ! empty( $data['requires'] )		? esc_attr( $data['requires'] )		: '';
		$tested			= isset( $data['tested'] )		&& ! empty( $data['tested'] )		? esc_attr( $data['tested'] )		: '';
		$upd_stamp		= isset( $data['updated'] )		&& ! empty( $data['updated'] )		? intval( $data['updated'] )		: '';
		$upd_show		= ! empty( $upd_stamp ) ? date( 'Y-m-d', $upd_stamp ) : '';


		$product_data	= array(
			'package'		=> $package,
			'location'		=> $location,
			'description'	=> $description,
			'changelog'		=> $changelog,
			'version'		=> $version,
			'tested'		=> $tested,
			'requires'		=> $requires,
			'updated'		=> $upd_show,
		);

		$product_data	= apply_filters( 'rkv_remote_repo_product_data', $product_data, $product_id );

		// return single bit of info if requested
		if ( $meta && isset( $product_data[ $meta ] ) )
			return $product_data[ $meta ];

		return $product_data;

	}


	/**
	 * check the product version being passed
	 * @param  string $version      version being checked
	 * @param  array $product_data  array of item data
	 * @return bool
	 */
	public function compare_versions( $version, $product_vers ) {

		return version_compare( $product_vers, $version , '>' );

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
		if ( ! in_array( $wp_query->query_vars['action'], array( 'plugin_latest_version', 'plugin_information' ) ) ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'ACTION_INCORRECT',
				'message'		=> 'The declared action was not understood.'
			);

			$this->output( $response );
			return false;

		endif;

		// check for missing key
		if ( ! isset( $wp_query->query_vars['key'] ) ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'KEY_MISSING',
				'message'		=> 'The required API key was not provided.'
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
		if ( ! isset( $wp_query->query_vars['product'] ) ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'PRODUCT_NAME_MISSING',
				'message'		=> 'No product name has been supplied.'
			);

			$this->output( $response );
			return false;

		endif;

		// check for missing product version
		if ( ! isset( $wp_query->query_vars['version'] ) ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'PRODUCT_VERSION_MISSING',
				'message'		=> 'No product version has been supplied.'
			);

			$this->output( $response );
			return false;

		endif;

		// check if the product exists
		$product_id	= $this->get_product_id( $wp_query->query_vars['product'] );
		if ( ! $product_id ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'NOT_VALID_PRODUCT',
				'message'		=> 'The provided name does not match a valid product.'
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

		// check if the product version requires an update
		$version_compare	= $this->compare_versions( $wp_query->query_vars['version'], $product_data['version'] );
		if ( ! $version_compare ) :

			$response	= array(
				'success'		=> false,
				'error_code'	=> 'NO_UPDATE_REQUIRED',
				'message'		=> 'The product is up to date.'
			);

			$this->output( $response );
			return false;

		endif;

		/* TODO add custom validation method */

		// all checks passed, return product file
		return $product_data;

	}

	/**
	 * [process_update_check description]
	 * @return [type] [description]
	 */
	public function process_update_check( $product_data, $slug ) {

		$response	= array(
			'slug'			=> $slug,
			'new_version'	=> $product_data['version'],
			'url'			=> $product_data['location'],
			'package'		=> $product_data['package'],
		);

		$response	= apply_filters( 'rkv_remote_repo_update_check', $response );

		return $response;

	}

	/**
	 * [process_plugin_details description]
	 * @return [type] [description]
	 */
	public function process_plugin_details( $product_data, $slug ) {

		$response	= array(
			'slug'			=> $slug,
			'plugin_name'	=> $slug,
			'new_version'	=> $product_data['version'],
			'requires'		=> $product_data['requires'],
			'tested'		=> $product_data['tested'],
			'last_updated'	=> $product_data['updated'],
			'sections' 		=> array(
				'description'	=> $product_data['description'],
				'changelog'		=> $product_data['changelog'],
			),
			'download_link' => $product_data['package'],
		);

		$response	= apply_filters( 'rkv_remote_repo_plugin_details', $response );

		return $response;

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
		$product_data	= $this->validate_request( $wp_query );
		if ( ! $product_data )
			return;

		// run process based on action request
		if ( $wp_query->query_vars['action'] == 'plugin_latest_version' )
			$process	= $this->process_update_check( $product_data, $wp_query->query_vars['slug'] );

		if ( $wp_query->query_vars['action'] == 'plugin_information' )
			$process	= $this->process_plugin_details( $product_data, $wp_query->query_vars['slug'] );


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
	 * @param int $status_code
	 */

	public function output( $process ) {

		header( 'HTTP/1.1 200' );
		header( 'Content-type: application/json; charset=utf-8' );
		echo json_encode( $process );

		die();

	}


}

new Reaktiv_Remote_Repo();