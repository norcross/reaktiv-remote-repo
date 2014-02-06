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

		add_action		(	'init',									array(	$this, '_register_types'		) 			);
		add_action		(	'admin_enqueue_scripts',				array(	$this, 'admin_enqueue'			),	10		);
		add_action		(	'manage_posts_custom_column',			array(	$this, 'display_columns'		),	10,	2	);

		add_filter		(	'custom_menu_order',					array(	$this, 'menu_order'				)           );
		add_filter		(	'menu_order',							array(	$this, 'menu_order'				)           );
		add_filter		(	'manage_edit-repo-items_columns',		array(	$this, 'repo_columns'			)			);

	}


	/**
	 * Scripts and stylesheets
	 *
	 * @return
	 */

	public function admin_enqueue() {

		$screen = get_current_screen();

		if ( is_object( $screen ) && $screen->post_type == 'repo-items' ) :

			wp_enqueue_style( 'rkv-repo',	plugins_url( '/css/rkv.repo.admin.css', __FILE__ ), array(), null,	'all' );

			wp_enqueue_media();
			wp_enqueue_script( 'datepick',	plugins_url( '/js/jquery.datepick.min.js', __FILE__ ),	array('jquery'),	null, true	);
			wp_enqueue_script( 'rkv-repo', plugins_url( '/js/rkv.repo.admin.js', __FILE__ ) , array( 'jquery', 'jquery-ui-sortable' ), null, true );
			wp_localize_script( 'rkv-repo', 'rkvAsset', array(
				'icon' => '<i class="dashicons dashicons-calendar rkv-cal-icon"></i>',
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

		$cpt_args = apply_filters( 'reaktiv_remote_repo_type_args', $cpt_args );

		register_post_type( 'repo-items', $cpt_args );

	}

/// end class
}


// Instantiate our class
new RKV_Remote_Repo_Admin();
