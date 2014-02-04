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
		add_action		(	'save_post',							array(	$this, 'save_repo_item_meta'	),	1		);
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
		$columns['title']		= 'Item Name';
		$columns['package']		= 'Package File';
		$columns['version']		= 'Version';
		$columns['updated']		= 'Updated';

		return $columns;

	}


	/**
	 * Column mods
	 *
	 * @return
	 */

	public function display_columns( $column, $post_id ) {

		$meta	= get_post_meta( $post_id, '_rkv_repo_data', true );

		switch ( $column ) {

		case 'package':
			$name	= !empty( $meta['package'] ) ? '<a href="'.esc_url( $meta['package'] ).'">Download</a>' : '(none entered)';

			echo $name;

			break;

		case 'version':
			$vers	= !empty( $meta['version'] ) ? $meta['version'] : '(none entered)';

			echo $vers;

			break;

		case 'updated':
			$vers	= !empty( $meta['updated'] ) ? date( 'Y-m-d', floatval( $meta['updated'] ) ) : '(none entered)';

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

		add_meta_box( 'rp-items-file', 	__( 'File Details', '' ),	array( $this, 'file_details' ),		'repo-items', 'normal', 'high'	);
		add_meta_box( 'rp-items-side', 	__( 'Version Info', '' ),	array( $this, 'version_details' ),	'repo-items', 'side',	'low'	);

	}

	/**
	 * build metabox for quote meta
	 *
	 * @return
	 */

	public function file_details( $post ) {

		// Use nonce for verification
		wp_nonce_field( 'rkv_repo_meta_nonce', 'rkv_repo_meta_nonce' );

		// get the data array
		$data		= get_post_meta( $post->ID, '_rkv_repo_data', true );

		$package		= isset( $data['package'] )		&& ! empty( $data['package'] )		? $data['package']		: '';
		$location		= isset( $data['location'] )	&& ! empty( $data['location'] )		? $data['location']		: '';
		$description	= isset( $data['description'] )	&& ! empty( $data['description'] )	? $data['description']	: '';
		$changelog		= isset( $data['changelog'] )	&& ! empty( $data['changelog'] )	? $data['changelog']	: '';
		$faq			= isset( $data['faq'] )			&& ! empty( $data['faq'] )			? $data['faq']			: '';
		$screenshots	= isset( $data['screenshots'] )	&& ! empty( $data['screenshots'] )	? $data['screenshots']	: '';
		$other_notes	= isset( $data['other_notes'] )	&& ! empty( $data['other_notes'] )	? $data['other_notes']	: '';


		// build table
		echo '<table id="repo-meta-table" class="form-table">';
		echo '<tbody>';

		// setup each field
		echo '<tr class="repo-package-field">';
			echo '<th>';
				echo '<label for="repo-package">'.__( 'Package File', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<input type="url" name="repo-meta[package]" id="repo-package" class="repo-item-file-text" value="'. esc_url( $package ).'">';
				echo '<input data-uploader_title="'.__( 'Select A File', '' ).'" data-uploader_button="'.__( 'Select', '' ).'" class="button button-secondary repo-file-upload" type="button" value="'.__( 'Add File', '' ).'">';

				echo '<p class="description">'.__( 'The zipped package file to serve for updating.', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-location-field">';
			echo '<th>';
				echo '<label for="repo-location">'.__( 'Download Location', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<input type="url" name="repo-meta[location]" id="repo-location" class="repo-item-file-text" value="'.esc_url( $location ).'">';
				echo '<p class="description">'.__( 'The external location for download or purchase', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-description-field">';
			echo '<th>';
				echo '<label for="repo-description">'.__( 'Item Description', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<input type="text" name="repo-meta[description]" id="repo-description" class="repo-item-file-text" value="'.esc_attr( $description ).'">';
				echo '<p class="description">'.__( 'Short description to include in update', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-changelog-field">';
			echo '<th>';
				echo '<label for="repo-changelog">'.__( 'Changelog', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<textarea name="repo-meta[changelog]" id="repo-changelog" class="repo-item-textarea code">'.esc_attr( $changelog ).'</textarea>';
				echo '<p class="description">'.__( 'Changelog updates', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-faq-field">';
			echo '<th>';
				echo '<label for="repo-faq">'.__( 'FAQ', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<textarea name="repo-meta[faq]" id="repo-faq" class="repo-item-textarea code">'.esc_attr( $faq ).'</textarea>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-screenshots-field">';
			echo '<th>';
				echo '<label for="repo-screenshots">'.__( 'Screenshots', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<p class="uploader-info">';
					echo '<span class="dashicons dashicons-upload rkv-screenshot-uploader"></span>';
					echo __( 'Click to upload screenshots', '' );
					echo '<span class="spinner screenshot-spinner"></span>';
				echo '</p>';

				echo '<div class="repo-screenshot-gallery">';
				if ( ! empty ( $screenshots ) ) :
				foreach ( $screenshots as $image_id ) :
					$image	= wp_get_attachment_image_src( $image_id, 'thumbnail' );
					echo '<div class="screenshot-item" data-image="'.absint( $image_id ).'">';
						echo '<img class="screenshot-image" src="'. esc_url( $image[0] ) .'" />';
						echo '<span class="dashicons dashicons-no-alt screenshot-remove"></span>';
					echo '</div>';
				endforeach;
				endif;
				echo '</div>';

				echo '<div class="repo-screenshot-ids">';
				if ( ! empty ( $screenshots ) ) :
				foreach ( $screenshots as $image_id ) :
					echo '<input type="hidden" class="screenshot-id" name="repo-meta[screenshots][]" value="'.absint( $image_id ).'" data-image="'.absint( $image_id ).'" />';
				endforeach;
				endif;
				echo '</div>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-othernotes-field">';
			echo '<th>';
				echo '<label for="repo-othernotes">'.__( 'Other Notes', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<textarea name="repo-meta[other_notes]" id="repo-othernotes" class="repo-item-textarea code">'.esc_attr( $other_notes ).'</textarea>';
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

		$data		= get_post_meta( $post->ID, '_rkv_repo_data', true );

		$version	= isset( $data['version'] )		&& ! empty( $data['version'] )	? $data['version']	: '';
		$requires	= isset( $data['requires'] )	&& ! empty( $data['requires'] )	? $data['requires']	: '';
		$tested		= isset( $data['tested'] )		&& ! empty( $data['tested'] )	? $data['tested']	: '';
		$upd_stamp	= isset( $data['updated'] )		&& ! empty( $data['updated'] )	? $data['updated']	: '';
		$upd_show	= ! empty( $upd_stamp ) ? date( 'Y-m-d', floatval( $upd_stamp ) ) : '';

		echo '<p class="repo-side-field repo-version-field">';
			echo '<input type="text" name="repo-meta[version]" id="repo-version" class="repo-item-num-text" value="'.esc_attr( $version ).'">';
			echo '&nbsp;<label class="repo-item-label" for="repo-version">'.__( 'Current Version', '' ).'</label>';
		echo '</p>';

		echo '<p class="repo-side-field repo-requires-field">';
			echo '<input type="text" name="repo-meta[requires]" id="repo-requires" class="repo-item-num-text" value="'.esc_attr( $requires ).'">';
			echo '&nbsp;<label class="repo-item-label" for="repo-requires">'.__( 'Minimum WP Version', '' ).'</label>';
		echo '</p>';

		echo '<p class="repo-side-field repo-tested-field">';
			echo '<input type="text" name="repo-meta[tested]" id="repo-tested" class="repo-item-num-text" value="'.esc_attr( $tested ).'">';
			echo '&nbsp;<label class="repo-item-label" for="repo-tested">'.__( 'Tested Up To', '' ).'</label>';
		echo '</p>';

		echo '<p class="repo-side-field repo-updated-field">';
			echo '<input type="text" name="" id="repo-updated" class="repo-item-num-text" value="'.$upd_show.'">';
			echo '<input type="hidden" name="repo-meta[updated]" id="repo-stamp" value="'.floatval( $upd_stamp ).'">';
			echo '&nbsp;<label class="repo-item-label" for="repo-updated">'.__( 'Updated', '' ).'</label>';
		echo '</p>';

	}

	/**
	 * save quote metadata
	 *
	 * @return
	 */

	public function save_repo_item_meta( $post_id ) {

		// run various checks to make sure we aren't doing anything weird
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( ! isset( $_POST['rkv_repo_meta_nonce'] ) || ! wp_verify_nonce( $_POST['rkv_repo_meta_nonce'], 'rkv_repo_meta_nonce' ) )
			return $post_id;

		if ( 'repo-items' !== $_POST['post_type'] )
			return $post_id;

		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;


		// get data via $_POST and store it
		$data	= $_POST['repo-meta'];

		if ( isset( $data ) && ! empty( $data ) )
			update_post_meta( $post_id, '_rkv_repo_data', $data );

		if ( ! isset( $data ) || isset( $data ) && empty( $data ) )
			delete_post_meta( $post_id, '_rkv_repo_data' );



	}

	/**
	 * build out post type
	 *
	 * @return
	 */

	public function _register_types() {

		register_post_type( 'repo-items',
			array(
				'labels'	=> array(
					'name' 					=> __( 'Repository',				'' ),
					'singular_name' 		=> __( 'Item',						'' ),
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
					'show_in_nav_menus'		=> false,
					'show_ui'				=> true,
					'publicly_queryable'	=> false,
					'exclude_from_search'	=> true,
				'hierarchical'		=> false,
				'menu_position'		=> null,
				'capability_type'	=> 'post',
				'menu_icon'			=> 'dashicons-share-alt',
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
