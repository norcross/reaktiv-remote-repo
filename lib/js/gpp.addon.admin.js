/**
 * jQuery flexText: Auto-height textareas
 * --------------------------------------
 * Requires: jQuery 1.7+
 * Usage example: $('textarea').flexText()
 * Info: https://github.com/alexdunphy/flexible-textareas
 */
 ;(function(b){function a(c){this.$textarea=b(c);this._init()}a.prototype={_init:function(){var c=this;this.$textarea.wrap('<div class="flex-text-wrap" />').before("<pre><span /><br /><br /></pre>");this.$span=this.$textarea.prev().find("span");this.$textarea.on("input propertychange keyup change",function(){c._mirror()});b.valHooks.textarea={get:function(d){return d.value.replace(/\r?\n/g,"\r\n")}};this._mirror()},_mirror:function(){this.$span.text(this.$textarea.val())}};b.fn.flexText=function(){return this.each(function(){if(!b.data(this,"flexText")){b.data(this,"flexText",new a(this))}})}})(jQuery);

//********************************************************
// now start the engine
//********************************************************
jQuery(document).ready( function($) {

// **************************************************************
//  load datepicker
// **************************************************************

	var icon = gpaddAsset.icon;

	$( 'input#addon-updated' ).datepick({
		defaultDate:		null,
		selectDefaultDate:	true,
		dateFormat:			'yyyy-mm-dd',
		showTrigger:		icon,
		altField:			'#addon-stamp',
		altFormat:			'@',
    });


	// addon-changelog-field

    $( 'textarea.gppr-textarea' ).flexText()


//********************************************************
// media uploader for 3.5
//********************************************************

	// Uploading files
	var file_frame;

	jQuery( 'tr.addon-package-field' ).on('click', 'input.addon-file-upload', function( event ){

		event.preventDefault();

		//get my field ID for later
		var fieldbox = jQuery( 'tr.addon-package-field' ).find( 'input#addon-package' );

		// If the media frame already exists, reopen it.
		if ( file_frame ) {
			file_frame.open();
			return;
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: jQuery( this ).data( 'uploader_title' ),
			button: {
				text: jQuery( this ).data( 'uploader_button' )
			},
			multiple: false
		});

		// When an image is selected, run a callback.
		file_frame.on( 'select', function() {
			// We set multiple to false so only get one item from the uploader
			attachment = file_frame.state().get('selection').first().toJSON();

			// clear the existing value
			jQuery( fieldbox ).val( '' );

			// Populate the field with the file URL
			jQuery( fieldbox ).val( attachment.url );

		});

		// Finally, open the modal
		file_frame.open();
	});

//********************************************************
// that's all folks. we're done here
//********************************************************

});
