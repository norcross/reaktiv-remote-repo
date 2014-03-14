<?php

// Start up the engine
class RKV_Remote_Repo_PostMeta
{


	/**
	 * This is our constructor
	 *
	 * @return
	 */
	public function __construct() {
		add_action		(	'edit_form_after_title',				array(	$this,	'item_unique_id'			)			);
		add_action		(	'add_meta_boxes',						array(	$this,	'create_metaboxes'			)			);
		add_action		(	'save_post',							array(	$this,	'save_repo_item_meta'		),	1		);

	}


	/**
	 * [item_unique_id description]
	 * @return [type] [description]
	 */
	public function item_unique_id() {

		global $post;

		// only show for list items
		if ( $post->post_type != 'repo-items' )
			return;

		// fetch the unique ID to make sure we have some
		$unique	= get_post_meta( $post->ID, '_rkv_repo_unique_id', true );

		// display the code if already saved
		if ( ! empty( $unique ) ) :
			echo '<div id="repo-unique-id"><p>';
			echo '<p class="code-item"><code>' . esc_attr( $unique ) . '</code></p>';
			echo '<p class="code-label">' . __( 'Use this unique ID on the plugin updater class', '' ) . '</p>';
			echo '<input type="hidden" id="repo-item-unique-id" name="repo-meta[unique-id]" value="' . esc_attr( $unique ) . '">';
			echo '</div>';
		else:
			// generate a key and load the hidden field
			$unique	= wp_generate_password( 16, false, false );
			echo '<input type="hidden" id="repo-item-unique-id" name="repo-meta[unique-id]" value="' . esc_attr( $unique ) . '">';
		endif;

	}

	/**
	 * call metabox
	 *
	 * @return
	 */

	public function create_metaboxes() {

		add_meta_box( 'rp-file', 	__( 'File Details', '' ),	array( $this, 'file_details' ),		'repo-items', 'normal', 'high'	);
		add_meta_box( 'rp-readme', 	__( 'Readme Details', '' ),	array( $this, 'readme_details' ),	'repo-items', 'normal', 'high'	);
		add_meta_box( 'rp-side', 	__( 'Version Info', '' ),	array( $this, 'version_details' ),	'repo-items', 'side',	'low'	);
		add_meta_box( 'rp-author', 	__( 'Author Info', '' ),	array( $this, 'author_details' ),	'repo-items', 'side',	'low'	);
		add_meta_box( 'rp-rating', 	__( 'Rating Info', '' ),	array( $this, 'rating_details' ),	'repo-items', 'side',	'low'	);

	}

	/**
	 * [file_details description]
	 * @param  [type] $post [description]
	 * @return [type]       [description]
	 */
	public function file_details( $post ) {

		// Use nonce for verification
		wp_nonce_field( 'rkv_repo_meta_nonce', 'rkv_repo_meta_nonce' );

		// get the data array
		$data		= get_post_meta( $post->ID, '_rkv_repo_data', true );

		$package		= isset( $data['package'] )			? $data['package']						: '';
		$readme			= isset( $data['readme'] )			? $data['readme']						: '';
		$homepage		= isset( $data['homepage'] )		? $data['homepage']						: '';
		$screenshots	= isset( $data['screenshots'] )		? $data['screenshots']					: '';

		// build table
		echo '<table id="repo-meta-table" class="form-table">';
		echo '<tbody>';

		do_action( 'rkv_remote_repo_before_fileinfo_meta', $post, $data );

		// setup each field
		echo '<tr class="repo-package-field repo-upload-item-field">';
			echo '<th>';
				echo '<label for="repo-package">'.__( 'Package File', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<input type="url" name="repo-meta[package]" id="repo-package" class="repo-item-file-text repo-item-file-upload" value="'. esc_url( $package ).'">';
				echo '<input data-uploader_title="'.__( 'Select A File', '' ).'" data-uploader_button="'.__( 'Select', '' ).'" class="button button-secondary repo-file-upload" type="button" value="'.__( 'Add Download File', '' ).'">';

				echo '<p class="description">'.__( 'The zipped package file to serve for updating.', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-readme-upload-field repo-upload-item-field">';
			echo '<th>';
				echo '<label for="repo-readme">'.__( 'Readme File', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<input type="url" name="repo-meta[readme]" id="repo-readme" class="repo-item-file-text repo-item-file-upload" value="'. esc_url( $readme ).'">';
				echo '<input data-uploader_title="'.__( 'Select A File', '' ).'" data-uploader_button="'.__( 'Select', '' ).'" class="button button-secondary repo-file-upload" type="button" value="'.__( 'Add Readme.txt File', '' ).'">';

				echo '<p class="description">'.__( 'A markdown formatted readme file', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-homepage-field">';
			echo '<th>';
				echo '<label for="repo-homepage">'.__( 'Item Home Page', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				echo '<input type="url" name="repo-meta[homepage]" id="repo-homepage" class="repo-item-file-text" value="'.esc_url( $homepage ).'">';
				echo '<p class="description">'.__( 'The external location for download or purchase', '' ).'</p>';
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
				// info about how they are used
				echo '<p class="description">'.__( 'Note: the attachment name will be displayed below the image.', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		do_action( 'rkv_remote_repo_after_fileinfo_meta', $post, $data );

		echo '</tbody>';
		echo '</table>';

	}


	/**
	 * [readme_details description]
	 * @param  [type] $post [description]
	 * @return [type]       [description]
	 */
	public function readme_details( $post ) {

		$data		= get_post_meta( $post->ID, '_rkv_repo_data', true );

		$description	= isset( $data['description'] )					? $data['description']					: '';
		$installation	= isset( $data['installation'] )				? $data['installation']					: '';
		$changelog		= isset( $data['changelog'] )					? $data['changelog']					: '';
		$faqs			= isset( $data['frequently_asked_questions'] )	? $data['frequently_asked_questions']	: '';

		// build table
		echo '<table id="repo-meta-table" class="form-table">';
		echo '<tbody>';

		echo self::readme_notice();

		do_action( 'rkv_remote_repo_before_readme_meta', $post, $data );

		echo '<tr class="repo-description-field">';
			echo '<th>';
				echo '<label for="repo-description">'.__( 'Item Description', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				$args	= self::repo_editor_load( 'description' );
				wp_editor( $description, 'description', $args );
				echo '<p class="description">'.__( 'Short description to include in update', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-installation-field">';
			echo '<th>';
				echo '<label for="repo-installation">'.__( 'Installation Notes', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				$args	= self::repo_editor_load( 'installation' );
				wp_editor( $installation, 'installation', $args );
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-faqs-field">';
			echo '<th>';
				echo '<label for="repo-faqs">'.__( 'FAQs', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				$args	= self::repo_editor_load( 'frequently_asked_questions' );
				wp_editor( $faqs, 'faqs', $args );
			echo '</td>';
		echo '</tr>';

		echo '<tr class="repo-changelog-field">';
			echo '<th>';
				echo '<label for="repo-changelog">'.__( 'Changelog', '' ).'</label>';
			echo '</th>';
			echo '<td>';
				$args	= self::repo_editor_load( 'changelog' );
				wp_editor( $changelog, 'changelog', $args );
				echo '<p class="description">'.__( 'Changelog updates', '' ).'</p>';
			echo '</td>';
		echo '</tr>';

		do_action( 'rkv_remote_repo_after_readme_meta', $post, $data );

		echo '</tbody>';
		echo '</table>';

	}

	/**
	 * [version_details description]
	 * @param  [type] $post [description]
	 * @return [type]       [description]
	 */
	public function version_details( $post ) {

		$data		= get_post_meta( $post->ID, '_rkv_repo_data', true );

		$version	= isset( $data['version'] )			? $data['version']		: '';
		$requires	= isset( $data['requires'] )		? $data['requires']		: '';
		$tested		= isset( $data['tested'] )			? $data['tested']		: '';
		$add_stamp	= isset( $data['added'] )			? $data['added']		: '';
		$upd_stamp	= isset( $data['last_updated'] )	? $data['last_updated']	: '';

		$add_show	= ! empty( $add_stamp ) ? date( 'Y-m-d', floatval( $add_stamp ) ) : '';
		$upd_show	= ! empty( $upd_stamp ) ? date( 'Y-m-d', floatval( $upd_stamp ) ) : '';

		do_action( 'rkv_remote_repo_before_version_meta', $post, $data );

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

		echo '<p class="repo-side-field repo-added-field repo-cal-field">';
			echo '<input type="text" name="" id="repo-added" class="repo-item-num-text repo-cal-text" value="'.$add_show.'">';
			echo '<input type="hidden" name="repo-meta[added]" id="repo-add-stamp" class="repo-cal-stamp" value="'.floatval( $add_stamp ).'">';
			echo '&nbsp;<label class="repo-item-label" for="repo-added">'.__( 'Released', '' ).'</label>';
		echo '</p>';

		echo '<p class="repo-side-field repo-updated-field repo-cal-field">';
			echo '<input type="text" name="" id="repo-updated" class="repo-item-num-text repo-cal-text" value="'.$upd_show.'">';
			echo '<input type="hidden" name="repo-meta[last_updated]" id="repo-update-stamp" class="repo-cal-stamp" value="'.floatval( $upd_stamp ).'">';
			echo '&nbsp;<label class="repo-item-label" for="repo-updated">'.__( 'Updated', '' ).'</label>';
		echo '</p>';

		do_action( 'rkv_remote_repo_after_version_meta', $post, $data );

	}


	/**
	 * [author_details description]
	 * @param  [type] $post [description]
	 * @return [type]       [description]
	 */
	public function author_details( $post ) {

		$data		= get_post_meta( $post->ID, '_rkv_repo_data', true );

		$author		= isset( $data['author'] )			? $data['author']			: '';
		$profile	= isset( $data['author_profile'] )	? $data['author_profile']	: '';
		$contribs	= isset( $data['contributors'] )	? $data['contributors']		: '';

		do_action( 'rkv_remote_repo_before_author_meta', $post, $data );

		echo '<p class="repo-side-field repo-author-field">';
			echo '<input type="text" name="repo-meta[author]" id="repo-author" class="widefat" value="'.esc_attr( $author ).'">';
			echo '<label for="repo-author">'.__( 'Author Name', '' ).'</label>';
		echo '</p>';

		echo '<p class="repo-side-field repo-profile-field">';
			echo '<input type="url" name="repo-meta[author_profile]" id="repo-profile" class="widefat" value="'.esc_attr( $profile ).'">';
			echo '<label for="repo-profile">'.__( 'Author Profile URL', '' ).'</label>';
		echo '</p>';

		echo '<p class="repo-side-field repo-contribs-field">';
			echo '<textarea name="repo-meta[contributors]" id="repo-contribs" class="widefat repo-item-textarea">'.esc_attr( $contribs ).'</textarea>';
			echo '<label for="repo-contribs">'.__( 'Contributors <em>(separate by comma)</em>', '' ).'</label>';
		echo '</p>';

		do_action( 'rkv_remote_repo_after_author_meta', $post, $data );

	}

	/**
	 * [rating_details description]
	 * @param  [type] $post [description]
	 * @return [type]       [description]
	 */
	public function rating_details( $post ) {

		$data		= get_post_meta( $post->ID, '_rkv_repo_data', true );

		$rating		= isset( $data['rating'] )			&& ! empty( $data['rating'] )		? $data['rating']	: '';
		$rcount		= isset( $data['num_ratings'] )		&& ! empty( $data['num_ratings'] )	? $data['num_ratings']	: '';
		$dcount		= isset( $data['downloaded'] )		&& ! empty( $data['downloaded'] )	? $data['downloaded']	: '';

		do_action( 'rkv_remote_repo_before_rating_meta', $post, $data );

		echo '<p class="repo-side-field repo-rating-field">';
			echo '<input type="text" name="repo-meta[rating]" id="repo-rating" class="repo-item-num-text" value="'.absint( $rating ).'">';
			echo '&nbsp;<label class="repo-item-label" for="repo-rating">'.__( 'Rating (out of 100)', '' ).'</label>';
		echo '</p>';

		echo '<p class="repo-side-field repo-rcount-field">';
			echo '<input type="text" name="repo-meta[num_ratings]" id="repo-rcount" class="repo-item-num-text" value="'.absint( $rcount ).'">';
			echo '&nbsp;<label class="repo-item-label" for="repo-rcount">'.__( 'Total Ratings', '' ).'</label>';
		echo '</p>';

		echo '<p class="repo-side-field repo-dcount-field">';
			echo '<input type="text" name="repo-meta[downloaded]" id="repo-dcount" class="repo-item-num-text" value="'.absint( $dcount ).'">';
			echo '&nbsp;<label class="repo-item-label" for="repo-dcount">'.__( 'Download Count', '' ).'</label>';
		echo '</p>';

		do_action( 'rkv_remote_repo_after_rating_meta', $post, $data );

	}



	/**
	 * [save_repo_item_meta description]
	 * @param  [type] $post_id [description]
	 * @return [type]          [description]
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


		// fetch the unique ID to make sure we have one
		$unique	= isset( $data['unique-id'] ) && ! empty( $data['unique-id'] ) ? esc_attr( $data['unique-id'] ) : wp_generate_password( 16, false, false );

		update_post_meta( $post_id, '_rkv_repo_unique_id', $unique );

		// trim my upgrade notices
		if ( isset( $data['upgrade_notice'] ) ) {
			$filter_array	= array_map( 'array_filter', $data['upgrade_notice'] );
			$data['upgrade_notice']	= array_filter( $filter_array );
		}


		if ( isset( $data ) && ! empty( $data ) )
			update_post_meta( $post_id, '_rkv_repo_data', $data );

		if ( ! isset( $data ) || isset( $data ) && empty( $data ) )
			delete_post_meta( $post_id, '_rkv_repo_data' );

		// do a quick check for a readme and set a flag
		if ( isset( $data['readme'] ) && ! empty( $data['readme'] ) )
			update_post_meta( $post_id, '_rkv_repo_readme_file', 1 );

		if ( ! isset( $data['readme'] ) || isset( $data['readme'] ) && empty( $data['readme'] ) )
			delete_post_meta( $post_id, '_rkv_repo_readme_file' );


	}

	/**
	 * [repo_editor_load description]
	 * @param  [type] $key [description]
	 * @return [type]      [description]
	 */
	static function repo_editor_load( $key ) {

		$args	= array(
			'media_buttons'		=> false,
			'textarea_name'		=> 'repo-meta['.$key.']',
			'textarea_rows'		=> 6,
			'teeny'				=> true
		);

		$args	= apply_filters( 'rkv_remote_repo_editor_args', $args, $key );

		return $args;

	}

	/**
	 * [readme_notice description]
	 * @return [type] [description]
	 */
	static function readme_notice() {

		if ( false === apply_filters( 'rkv_remote_repo_readme_notice', true ) )
			return;

		$show	= '';

		$show	.= '<tr class="repo-readme-notice">';
			$show	.= '<th>&nbsp;</th>';
			$show	.= '<td>';
				$show	.= '<h4 class="readme-info-text">'.__( '<strong>Note:</strong> If you have uploaded a markdown-formatted readme file, that will take priority over anything in this section', '' ).'</h4>';
			$show	.= '</td>';
		$show	.= '</tr>';

		return $show;

	}

/// end class
}


// Instantiate our class
new RKV_Remote_Repo_PostMeta();
