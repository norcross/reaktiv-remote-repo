<?php

// Start up the engine
class RKV_Remote_Repo_Admin
{


	/**
	 * This is our constructor
	 *
	 * @return
	 */
	public function __construct() {

		add_action		(	'init',									array(	$this,	'_register_types'		) 			);
		add_action		(	'admin_init',							array(	$this,	'secure_upload_dir'		)			);
		add_action		(	'admin_enqueue_scripts',				array(	$this,	'admin_enqueue'			),	10		);
		add_action		(	'manage_posts_custom_column',			array(	$this,	'display_columns'		),	10,	2	);

		add_filter		(	'custom_menu_order',					array(	$this,	'menu_order'			)           );
		add_filter		(	'menu_order',							array(	$this,	'menu_order'			)           );
		add_filter		(	'manage_edit-repo-items_columns',		array(	$this,	'repo_columns'			)			);
		add_filter		(	'upload_dir',							array(	$this,	'create_repo_dir'		),	999		);
	}

	/**
	 * create a custom folder inside uploads to store protected content
	 * @param  [type] $args [description]
	 * @return [type]       [description]
	 */
	public function create_repo_dir( $args ) {

		// bail if no post type is present
		if( ! isset( $_REQUEST['post_id'] ) )
			return $args;

    	// Get and set the current post_id
		$post_id	= (int)$_REQUEST['post_id'];

		$post_type	= get_post_type( $post_id );

		if( 'repo-items' != $post_type )
			return $args;

		// Set the new path depends on current post_type
		$custom	= self::get_custom_dir();

		$args['path']    = str_replace( $args['subdir'], '', $args['path'] ); //remove default subdir
		$args['url']     = str_replace( $args['subdir'], '', $args['url'] );
		$args['subdir']  = $custom;
		$args['path']   .= $custom;
		$args['url']    .= $custom;

		return $args;

	}

	/**
	 * [set_custom_dir description]
	 */
	static function get_custom_dir() {

		return apply_filters( 'rkv_remote_repo_custom_dir', '/rkv-repo' );

	}

	/**
	 * confirm the folder exists and it writeable
	 * @return [string] the custom upload path
	 */
	static function get_upload_dir() {

		// get current upload directory
		$upload	= wp_upload_dir();

		// get our custom directory
		$custom	= self::get_custom_dir();

		// check and make our folder if need be
		wp_mkdir_p( $upload['basedir'] .$custom );

		// set our new path
		$path	= $upload['basedir'] . $custom;

		return $path;

	}

	/**
	 * confirm the htaccess file exists
	 * @return [bool] true on file existence
	 */
	static function htaccess_exists() {
		$upload_path = self::get_upload_dir();

		return file_exists( $upload_path . '/.htaccess' );
	}

	/**
	 * the htaccess file rules to be written for filter protection
	 * @return [string] the rules
	 */
	static function get_htaccess_rules() {

		$rules = "Options -Indexes\n";
		$rules .= "<FilesMatch '\.(zip)$'>\n";
		    $rules .= "Order Allow,Deny\n";
		    $rules .= "Allow from all\n";
		$rules .= "</FilesMatch>\n";

		return $rules;
	}

	/**
	 * Creates blank index.php and .htaccess files
	 *
	 * This function runs approximately once per month in order to ensure all folders
	 * have their necessary protection files
	 *
	 * @return void
	 */
	public function secure_upload_dir() {

//		if ( false === get_transient( 'rkv_check_protection_files' ) ) {

			$upload_path = self::get_upload_dir();

			// Make sure the /edd folder is created
			wp_mkdir_p( $upload_path );

			// Top level .htaccess file
			$rules = self::get_htaccess_rules();
			if ( self::htaccess_exists() ) {
				$contents = @file_get_contents( $upload_path . '/.htaccess' );
				if ( $contents !== $rules || ! $contents ) {
					// Update the .htaccess rules if they don't match
					@file_put_contents( $upload_path . '/.htaccess', $rules );
				}
			} elseif( wp_is_writable( $upload_path ) ) {
				// Create the file if it doesn't exist
				@file_put_contents( $upload_path . '/.htaccess', $rules );
			}

			// Top level blank index.php
			if ( ! file_exists( $upload_path . '/index.php' ) && wp_is_writable( $upload_path ) ) {
				@file_put_contents( $upload_path . '/index.php', '<?php' . PHP_EOL . '// Silence is golden.' );
			}

			// Check for the files once per day
//			set_transient( 'rkv_check_protection_files', true, 3600 * 24 );
//		}
	}

	/**
	 * Scripts and stylesheets
	 *
	 * @return
	 */

	public function admin_enqueue() {

		$screen = get_current_screen();

		if ( is_object( $screen ) && $screen->post_type == 'repo-items' ) :

			global $post;

			wp_enqueue_style( 'rkv-repo',	plugins_url( '/css/rkv.repo.admin.css', __FILE__ ), array(), null,	'all' );

			wp_enqueue_media( array( 'post' => $post->ID ) );
			wp_enqueue_script( 'datepick',	plugins_url( '/js/jquery.datepick.min.js', __FILE__ ),	array('jquery'),	null, true	);
			wp_enqueue_script( 'rkv-repo', plugins_url( '/js/rkv.repo.admin.js', __FILE__ ) , array( 'jquery', 'jquery-ui-sortable' ), null, true );
			wp_localize_script( 'rkv-repo', 'rkvAsset', array(
				'icon' => '<i class="dashicons dashicons-calendar rkv-cal-icon"></i>',
				'uptitle'	=> 'Upload or select a file',
				'upbutton'	=> 'Add This File',
				'upcheck'	=> '<i class="dashicons dashicons-yes niica-mpc-yes"></i>',
			));

		endif;

	}

	/**
	 * reorder menu
	 *
	 * @return
	 */

	public function menu_order( $menu_ord ) {

		if ( ! $menu_ord )
			return true;

		return array(
			'index.php',						// this represents the dashboard link
			'edit.php?post_type=repo-items',	// custom post type
			'edit.php',							// this is the default POST admin menu
		);
	}

	/**
	 * adjust admin display columns
	 *
	 * @return
	 */

	public function repo_columns( $columns ) {

		// remove stuff
		unset( $columns['date'] );

		// now add the custom stuff
		$columns['title']		= __( 'Item Name', '' );
		$columns['package']		= __( 'Package File', '' );
		$columns['version']		= __( 'Version', '' );
		$columns['added']		= __( 'Added', '' );
		$columns['updated']		= __( 'Updated', '' );
		$columns['readme']		= __( 'Readme', '' );

		$columns	= apply_filters( 'rkv_remote_repo_admin_columns', $columns );

		return $columns;

	}


	/**
	 * Column mods
	 *
	 * @return
	 */

	public function display_columns( $column, $post_id ) {

		$meta	= get_post_meta( $post_id, '_rkv_repo_data', true );
		$none	= '('.__( 'none entered', '' ).')';

		switch ( $column ) {

		case 'package':
			$data	= !empty( $meta['package'] ) ? '<a href="'.esc_url( $meta['package'] ).'">'.__( 'Download', '' ).'</a>' : $none;

			echo $data;

			break;

		case 'version':
			$data	= !empty( $meta['version'] ) ? $meta['version'] : $none;

			echo $data;

			break;

		case 'added':
			$data	= !empty( $meta['added'] ) ? date( 'Y-m-d', floatval( $meta['added'] ) ) : $none;

			echo $data;

			break;

		case 'updated':
			$data	= !empty( $meta['last_updated'] ) ? date( 'Y-m-d', floatval( $meta['last_updated'] ) ) : $none;

			echo $data;

			break;

		case 'readme':
			$file	= get_post_meta( $post_id, '_rkv_repo_readme_file', true );
			$data	= ! $file ? 'dashicons-no meta-column-no' : 'dashicons-yes meta-column-yes';

			echo '<span class="meta-column-item dashicons '.$data.'"></span>';

			break;

		// end all case breaks
		}

	}


	/**
	 * build out post type
	 *
	 * @return
	 */

	public function _register_types() {

		$labels	= array(
			'name'					=> __( 'Repository',				'' ),
			'menu_name'				=> __( 'Repository',				'' ),
			'all_items'				=> __( 'Items',						'' ),
			'singular_name'			=> __( 'Item',						'' ),
			'add_new'				=> __( 'Add New Item',				'' ),
			'add_new_item'			=> __( 'Add New Item',				'' ),
			'edit'					=> __( 'Edit Item',					'' ),
			'edit_item'				=> __( 'Edit Item',					'' ),
			'new_item'				=> __( 'New Item',					'' ),
			'view'					=> __( 'View Item',					'' ),
			'view_item'				=> __( 'View Item',					'' ),
			'search_items'			=> __( 'Search Repository',			'' ),
			'not_found'				=> __( 'No Items found',			'' ),
			'not_found_in_trash'	=> __( 'No Items found in Trash',	'' ),
		);

		$cpt_args	= array(
			'labels'				=> $labels,
			'public'				=> true,
			'show_in_menu'			=> true,
			'show_in_nav_menus'		=> false,
			'show_ui'				=> true,
			'publicly_queryable'	=> true,
			'exclude_from_search'	=> true,
			'hierarchical'			=> false,
			'menu_position'			=> null,
			'capability_type'		=> 'post',
			'query_var'				=> true,
			'menu_icon'				=> 'dashicons-share-alt',
			'rewrite'				=> false,
			'has_archive'			=> false,
			'supports'				=> array( 'title' ),
		);

		$cpt_args = apply_filters( 'rkv_remote_repo_type_args', $cpt_args );

		register_post_type( 'repo-items', $cpt_args );

	}

/// end class
}


// Instantiate our class
new RKV_Remote_Repo_Admin();
