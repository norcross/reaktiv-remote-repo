<?php

// Start up the engine
class GP_Pro_Remote_Updater_Admin
{


	/**
	 * This is our constructor
	 *
	 * @return
	 */
	public function __construct() {

		add_action		(	'init',									array(	$this, '_register_types'		) 			);
		add_action		(	'admin_enqueue_scripts',				array(	$this, 'admin_enqueue'			),	10		);
		add_action		(	'add_meta_boxes',						array(	$this, 'create_metaboxes'		)			);
		add_action		(	'save_post',							array(	$this, 'save_addon_meta'		),	1		);
		add_action		(	'manage_posts_custom_column',			array(	$this, 'display_columns'		),	10,	2	);

		add_filter		(	'custom_menu_order',					array(	$this, 'menu_order'				)           );
		add_filter		(	'menu_order',							array(	$this, 'menu_order'				)           );
		add_filter		(	'manage_edit-gppro-addons_columns',		array(	$this, 'addon_columns'			)			);

	}


	/**
	 * Scripts and stylesheets
	 *
	 * @return
	 */

	public function admin_enqueue() {

		$screen = get_current_screen();

		if ( is_object( $screen ) && $screen->post_type == 'gppro-addons' ) :

			wp_enqueue_style( 'gpadd-admin',	plugins_url( '/css/gpp.addon.admin.css', __FILE__ ), array(), null,	'all' );

			wp_enqueue_media();
			wp_enqueue_script( 'datepick',	plugins_url( '/js/jquery.datepick.min.js', __FILE__ ),	array('jquery'),	null, true	);
			wp_enqueue_script( 'gpadd-admin', plugins_url( '/js/gpp.addon.admin.js', __FILE__ ) , array( 'jquery' ), null, true );
			wp_localize_script( 'gpadd-admin', 'gpaddAsset', array(
				'icon' => '<i class="dashicons dashicons-calendar gppr-cal-icon"></i>',
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
			'edit.php?post_type=gppro-addons',	// custom post type
			'edit.php',							// this is the default POST admin menu
		);
	}

	/**
	 * adjust admin display columns
	 *
	 * @return
	 */

	public function addon_columns( $columns ) {

		// remove stuff
		unset( $columns['date'] );

		// now add the custom stuff
		$columns['title']			= 'Item Name';
		$columns['file-name']		= 'File Name';
		$columns['file-version']	= 'Version';

		return $columns;

	}


	/**
	 * Column mods
	 *
	 * @return
	 */

	public function display_columns( $column, $post_id ) {

		switch ( $column ) {

		case 'file-name':
			$meta	= get_post_meta( $post_id, '_gpp_addon_file', true );
			$name	= !empty( $meta ) ? $meta : '(none entered)';

			echo esc_attr( $name );

			break;

		case 'file-version':
			$meta	= get_post_meta( $post_id, '_gpp_addon_version', true );
			$vers	= !empty( $meta ) ? $meta : '(none entered)';

			echo $vers;

			break;

		// end all case breaks
		}

	}

	/**
	 * call metabox
	 *
	 * @return
	 */

	public function create_metaboxes() {

		add_meta_box( 'add-on-file', __( 'File Details', '' ), array( $this, 'file_details' ), 'gppro-addons', 'normal', 'high' );
		add_meta_box( 'add-on-side', __( 'Version Details', '' ), array( $this, 'version_details' ), 'gppro-addons', 'side', 'low' );

	}

	/**
	 * build metabox for quote meta
	 *
	 * @return
	 */

	public function file_details( $post ) {

		// Use nonce for verification
		wp_nonce_field( 'gpadd_meta_nonce', 'gpadd_meta_nonce' );

		// get the data array
		$data		= get_post_meta( $post->ID, '_gpp_addon_data', true );

		$package		= isset( $data['package'] )		&& ! empty( $data['package'] )		? $data['package']		: '';
		$location		= isset( $data['location'] )	&& ! empty( $data['location'] )		? $data['location']		: '';
		$description	= isset( $data['description'] )	&& ! empty( $data['description'] )	? $data['description']	: '';
		$changelog		= isset( $data['changelog'] )	&& ! empty( $data['changelog'] )	? $data['changelog']	: '';


		// build table
		echo '<table id="addon-meta-table" class="form-table">';
		echo '<tbody>';

		// setup each field
		echo '<tr class="addon-package-field">';
			echo '<th>';
				echo '<label for="addon-package">'.__( 'Package File', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<input type="url" name="addon-meta[package]" id="addon-package" class="gppr-file-text" value="'. esc_url( $package ).'">';
				echo '<input data-uploader_title="'.__( 'Select A File', '' ).'" data-uploader_button="'.__( 'Select', '' ).'" class="button button-secondary addon-file-upload" type="button" value="'.__( 'Add File', '' ).'">';

				echo '<p class="description">'.__( 'The zipped package file to serve for updating.', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="addon-location-field">';
			echo '<th>';
				echo '<label for="addon-location">'.__( 'Download Location', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<input type="url" name="addon-meta[location]" id="addon-location" class="gppr-file-text" value="'.esc_url( $location ).'">';
				echo '<p class="description">'.__( 'The external location for download or purchase', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="addon-description-field">';
			echo '<th>';
				echo '<label for="addon-description">'.__( 'Item Description', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<input type="text" name="addon-meta[description]" id="addon-description" class="gppr-file-text" value="'.esc_attr( $description ).'">';
				echo '<p class="description">'.__( 'Short description to include in update', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="addon-changelog-field">';
			echo '<th>';
				echo '<label for="addon-changelog">'.__( 'Changelog', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<textarea name="addon-meta[changelog]" id="addon-changelog" class="gppr-textarea code">'.esc_attr( $changelog ).'</textarea>';
				echo '<p class="description">'.__( 'Changelog updates', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';

	}

	/**
	 * build metabox for side data
	 *
	 * @return
	 */

	public function version_details( $post ) {

		$data		= get_post_meta( $post->ID, '_gpp_addon_data', true );

		$version	= isset( $data['version'] )		&& ! empty( $data['version'] )	? $data['version']	: '';
		$requires	= isset( $data['requires'] )	&& ! empty( $data['requires'] )	? $data['requires']	: '';
		$tested		= isset( $data['tested'] )		&& ! empty( $data['tested'] )	? $data['tested']	: '';
		$upd_stamp	= isset( $data['updated'] )		&& ! empty( $data['updated'] )	? $data['updated']	: '';
		$upd_show	= ! empty( $upd_stamp ) ? date( 'Y-m-d', floatval( $upd_stamp ) ) : '';

		echo '<p class="addon-side-field addon-version-field">';
			echo '<input type="text" name="addon-meta[version]" id="addon-version" class="gppr-num-text" value="'.esc_attr( $version ).'">';
			echo '&nbsp;<label class="gppr-num-label" for="addon-version">'.__( 'Current Version', '' ).'</label>';
		echo '</p>';

		echo '<p class="addon-side-field addon-requires-field">';
			echo '<input type="text" name="addon-meta[requires]" id="addon-requires" class="gppr-num-text" value="'.esc_attr( $requires ).'">';
			echo '&nbsp;<label class="gppr-num-label" for="addon-requires">'.__( 'Minimum WP Version', '' ).'</label>';
		echo '</p>';

		echo '<p class="addon-side-field addon-tested-field">';
			echo '<input type="text" name="addon-meta[tested]" id="addon-tested" class="gppr-num-text" value="'.esc_attr( $tested ).'">';
			echo '&nbsp;<label class="gppr-num-label" for="addon-tested">'.__( 'Tested Up To', '' ).'</label>';
		echo '</p>';

		echo '<p class="addon-side-field addon-updated-field">';
			echo '<input type="text" name="" id="addon-updated" class="gppr-num-text" value="'.$upd_show.'">';
			echo '<input type="hidden" name="addon-meta[updated]" id="addon-stamp" value="'.floatval( $upd_stamp ).'">';
			echo '&nbsp;<label class="gppr-num-label" for="addon-updated">'.__( 'Updated', '' ).'</label>';
		echo '</p>';

	}

	/**
	 * save quote metadata
	 *
	 * @return
	 */

	public function save_addon_meta( $post_id ) {

		// run various checks to make sure we aren't doing anything weird
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( ! isset( $_POST['gpadd_meta_nonce'] ) || ! wp_verify_nonce( $_POST['gpadd_meta_nonce'], 'gpadd_meta_nonce' ) )
			return $post_id;

		if ( 'gppro-addons' !== $_POST['post_type'] )
			return $post_id;

		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;


		// get data via $_POST and store it
		$data	= $_POST['addon-meta'];

		if ( isset( $data ) && ! empty( $data ) )
			update_post_meta( $post_id, '_gpp_addon_data', $data );

		if ( ! isset( $data ) || isset( $data ) && empty( $data ) )
			delete_post_meta( $post_id, '_gpp_addon_data' );



	}

	/**
	 * build out post type
	 *
	 * @return
	 */

	public function _register_types() {

		register_post_type( 'gppro-addons',
			array(
				'labels'	=> array(
					'name' 					=> __( 'Add Ons',					'' ),
					'singular_name' 		=> __( 'Add On',					'' ),
					'add_new'				=> __( 'Add New Item',				'' ),
					'add_new_item'			=> __( 'Add New Item',				'' ),
					'edit'					=> __( 'Edit',						'' ),
					'edit_item'				=> __( 'Edit Item',					'' ),
					'new_item'				=> __( 'New Item',					'' ),
					'view'					=> __( 'View Item',					'' ),
					'view_item'				=> __( 'View Item',					'' ),
					'search_items'			=> __( 'Search Items',				'' ),
					'not_found'				=> __( 'No Items found',			'' ),
					'not_found_in_trash'	=> __( 'No Items found in Trash',	'' ),
				),
				'public'	=> true,
					'show_in_nav_menus'		=> true,
					'show_ui'				=> true,
					'publicly_queryable'	=> true,
					'exclude_from_search'	=> true,
				'hierarchical'		=> false,
				'menu_position'		=> null,
				'capability_type'	=> 'post',
				'menu_icon'			=> 'dashicons-art',
				'query_var'			=> true,
				'rewrite'			=> false,
				'has_archive'		=> false,
				'supports'			=> array( 'title' ),
			)
		);
	}

/// end class
}


// Instantiate our class
new GP_Pro_Remote_Updater_Admin();
