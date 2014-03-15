<?php

// uncomment this line for testing
set_site_transient( 'update_plugins', false );


/**
 * Allows plugins to use our own repo
 *
 * @author Andrew Norcross
 * @version 0.0.1
 */
class RKV_Remote_Updater {

	private $api_url	= '';
	private $api_data	= array();
	private $name		= '';
	private $unique		= '';
	private $slug		= '';

	/**
	 * Class constructor.
	 *
	 * @uses plugin_basename()
	 * @uses hook()
	 *
	 * @param string $_api_url The URL pointing to the custom API endpoint.
	 * @param string $_plugin_file Path to the plugin file.
	 * @param array $_api_data Optional data to send with API calls.
	 * @return void
	 */

	function __construct( $_api_url, $_plugin_file, $_api_data = null ) {

		$this->api_url	= trailingslashit( $_api_url );
		$this->api_data	= urlencode_deep( $_api_data );
		$this->name		= plugin_basename( $_plugin_file );
		$this->slug		= basename( $_plugin_file, '.php');
		$this->version	= $_api_data['version'];
		$this->unique	= $_api_data['unique'];

		// Set up hooks.
		$this->hook();

	}

	/**
	 * Set up Wordpress filters to hook into WP's update process.
	 *
	 * @uses add_filter()
	 *
	 * @return void
	 */

	private function hook() {

		add_filter	(	'pre_set_site_transient_update_plugins',	array(	$this,	'api_check'			)			);
		add_filter	(	'plugins_api',								array(	$this,	'api_data'			),	10,	3	);
		add_filter	(	'upgrader_post_install',					array(	$this,	'run_remote_count'	),	10,	3	);
		add_filter	(	'http_request_args',						array(	$this,	'disable_wporg'		),	5,	2	);

		register_activation_hook	( __FILE__,						array(	$this,	'clear_transient'	)			);

	}

	/**
	 * delete the transient check on initial install
	 * @return void
	 */
	public function clear_transient() {

		delete_transient( 'update_plugins' );
	}

	/**
	 * run transient check
	 * @param  [type] $transient [description]
	 * @return [type]            [description]
	 */

	public function api_check( $_transient_data ) {

		if( empty( $_transient_data ) )
			return $_transient_data;

		$to_send = array( 'slug' => $this->slug );

		$api_response = $this->api_request( 'plugin_latest_version', $to_send );

		if( false !== $api_response && is_object( $api_response ) ) {

			if( version_compare( $this->version, $api_response->new_version, '<' ) ) {
				$_transient_data->response[$this->name] = $api_response;

				// add our update row piece
				$name	= $this->name;
				add_action( "after_plugin_row_$name", 'wp_plugin_update_row', 10, 2 );
			}

		}

		return $_transient_data;

	}

	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @uses api_request()
	 *
	 * @param mixed $_data
	 * @param string $_action
	 * @param object $_args
	 * @return object $_data
	 */
	function api_data( $_data, $_action = '', $_args = null ) {

		if ( ( $_action != 'plugin_information' ) || !isset( $_args->slug ) || ( $_args->slug != $this->slug ) )
			return $_data;

		$to_send = array( 'slug' => $this->slug );

		$api_response = $this->api_request( 'plugin_information', $to_send );

		if ( false !== $api_response )
			$_data = $api_response;

		return $_data;
	}

	/**
	 * Calls the API and, if successfull, returns the object delivered by the API.
	 *
	 * @uses wp_remote_post()
	 * @uses is_wp_error()
	 *
	 * @param string $_action The requested action.
	 * @param array $_data Parameters for the API action.
	 * @return false||object
	 */
	private function api_request( $_action, $_data ) {

		$data = array_merge( $this->api_data, $_data );

		// make sure we're checking the right thing
		if( ! isset( $data['slug'] ) || $data['slug'] != $this->slug )
			return;

		// check for array elements, bail if missing
		if ( ! isset( $data['unique'] ) || ! isset( $data['version'] ) )
			return;

		// build array
		$api_args = array(
			'method'	=> 'POST',
			'timeout'	=> 15,
			'sslverify' => false,
			'body'		=> array(
				'action'	=> $_action,
				'unique'	=> $data['unique'],
				'version'	=> $data['version'],
				'slug' 		=> $data['slug'],
			),
		);

		// send request
		$request = wp_remote_post( $this->api_url, $api_args );

		// bail if my request errors out
		if ( is_wp_error( $request ) )
			return false;

		// bail if my request can't connect
		if ( ! isset( $request['response'] ) || $request['response']['code'] != 200 )
			return false;

		// decode the JSON I sent back and make sure it's an array instead of object
		$response = json_decode( wp_remote_retrieve_body( $request ), true );

		// bail if I don't have an array, or if its empty
		if ( ! is_array( $response ) || is_array( $response ) && empty( $response ) )
			return false;

		// bail if the success is false
		if ( ! isset( $response['success'] ) || ! $response['success'] )
			return false;

		// bail if the success is false
		if ( ! isset( $response['fields'] ) || isset( $response['fields'] ) && empty( $response['fields'] ) )
			return false;

		// now run the update return based on request
		$updates	= false;

		// build and return the basic info
		if ( $_action == 'plugin_latest_version' )
			$updates	= self::get_version_response( $response['fields'] );

		// build the larger return for updates
		if ( $_action == 'plugin_information' )
			$updates	= self::get_information_response( $response['fields'] );

		// bail if we have nothing
		if ( ! $updates )
			return false;

		// send it back
		return $updates;

	}

	/**
	 * [get_version_response description]
	 * @param  [type] $response [description]
	 * @return [type]           [description]
	 */
	static function get_version_response( $response ) {

		// Create response data object
		$updates = new stdClass;

		$updates->slug				= $response['slug'];
		$updates->new_version		= $response['new_version'];
		$updates->url				= $response['url'];
		$updates->package			= $response['package'];

		return $updates;

	}

	/**
	 * [get_information_response description]
	 * @param  [type] $response [description]
	 * @return [type]           [description]
	 */
	static function get_information_response( $response ) {

		// Create response data object
		$updates = new stdClass;

		// build out our huge goddamn array
		$updates->name				= $response['name'];
		$updates->slug				= $response['slug'];
		$updates->version			= $response['version'];
		$updates->author			= $response['author'];
		$updates->author_profile	= $response['author_profile'];
		$updates->contributors		= $response['contributors'];
		$updates->requires			= $response['requires'];
		$updates->tested			= $response['tested'];
		$updates->rating			= $response['rating'];
		$updates->num_ratings		= $response['num_ratings'];
		$updates->downloaded		= $response['downloaded'];
		$updates->last_updated		= $response['last_updated'];
		$updates->added				= $response['added'];
		$updates->homepage			= $response['homepage'];
		$updates->download_link		= $response['download_link'];
		$updates->sections			= $response['sections'];

		return $updates;

	}

	/**
	 * [update_remote_count description]
	 * @return [type] [description]
	 */
	public function update_remote_count( $data ) {

		// build array
		$api_args = array(
			'method'	=> 'POST',
			'timeout'	=> 15,
			'sslverify' => false,
			'body'		=> array(
				'action'	=> 'update_counts',
				'unique'	=> $data['unique'],
				'version'	=> $data['version'],
				'slug' 		=> $data['slug'],
			),
		);

		// send request
		$request = wp_remote_post( $this->api_url, $api_args );

		// bail now, because we don't really care what the outcome was
		return;

	}

	/**
	 * [run_remote_count description]
	 * @param  [type] $true       [description]
	 * @param  [type] $hook_extra [description]
	 * @param  [type] $result     [description]
	 * @return [type]             [description]
	 */
	public function run_remote_count( $true, $hook_extra, $result ) {

		// first check our bypass filter
		if ( false === apply_filters( 'rkv_remote_repo_update_count', true ) )
			return $result;

		// make sure there's data to check
		if ( ! isset( $hook_extra ) )
			return $result;

		// make sure we're dealing with our plugin
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] != $this->name )
			return $result;

		// build our data array
		$data	= array(
			'unique'	=> $this->unique,
			'version'	=> $this->version,
			'slug'		=> $this->slug,
		);

		// run our callback counter
		$this->update_remote_count( $data );

		return $result;

	}

	/**
	 * Disable request to wp.org plugin repository
	 * this function is to remove update request data of this plugin to wp.org
	 * so wordpress would not do update check for this plugin.
	 *
	 * @link http://markjaquith.wordpress.com/2009/12/14/excluding-your-plugin-or-theme-from-update-checks/
	 * @since 0.1.2
	 */
	public function disable_wporg( $r, $url ){

		/* WP.org plugin update check URL */
		$wp_url_string = 'api.wordpress.org/plugins/update-check';

		/* If it's not a plugin update check request, bail early */
		if ( false === strpos( $url, $wp_url_string ) )
			return $r;


		/* Get this plugin slug */
		$plugin_slug = dirname( $this->slug );

		/* Get response body (json/serialize data) */
		$r_body = wp_remote_retrieve_body( $r );

		/* Get plugins request */
		$r_plugins = '';
		$r_plugins_json = false;
		if( isset( $r_body['plugins'] ) ) {

			/* Check if data can be serialized */
			if ( is_serialized( $r_body['plugins'] ) ) {

				/* unserialize data ( PRE WP 3.7 ) */
				$r_plugins = @unserialize( $r_body['plugins'] );
				$r_plugins = (array) $r_plugins; // convert object to array
			}

			/* if unserialize didn't work ( POST WP.3.7 using json ) */
			else {
				/* use json decode to make body request to array */
				$r_plugins = json_decode( $r_body['plugins'], true );
				$r_plugins_json = true;
			}
		}

		/* this plugin */
		$to_disable = '';

		/* check if plugins request is not empty */
		if  ( !empty( $r_plugins ) ) {

			/* All plugins */
			$all_plugins = $r_plugins['plugins'];

			/* Loop all plugins */
			foreach ( $all_plugins as $plugin_base => $plugin_data ){

				/* Only if the plugin have the same folder, because plugins can have different main file. */
				if ( dirname( $plugin_base ) == $plugin_slug ){

					/* get plugin to disable */
					$to_disable = $plugin_base;
				}
			}

			/* Unset this plugin only */
			if ( !empty( $to_disable ) ){
				unset(  $all_plugins[ $to_disable ] );
			}

			/* Merge plugins request back to request */
			if ( true === $r_plugins_json ){ // json encode data
				$r_plugins['plugins'] = $all_plugins;
				$r['body']['plugins'] = json_encode( $r_plugins );
			}
			else{ // serialize data
				$r_plugins['plugins'] = $all_plugins;
				$r_plugins_object = (object) $r_plugins;
				$r['body']['plugins'] = serialize( $r_plugins_object );
			}
		}

		/* return the request */
		return $r;
	}

}