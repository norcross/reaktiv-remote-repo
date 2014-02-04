/*!
	Autosize v1.18.4 - 2014-01-11
	Automatically adjust textarea height based on user input.
	(c) 2014 Jack Moore - http://www.jacklmoore.com/autosize
	license: http://www.opensource.org/licenses/mit-license.php
*/
!function(a){var b,c={className:"autosizejs",append:"",callback:!1,resizeDelay:10,placeholder:!0},d='<textarea tabindex="-1" style="position:absolute; top:-999px; left:0; right:auto; bottom:auto; border:0; padding: 0; -moz-box-sizing:content-box; -webkit-box-sizing:content-box; box-sizing:content-box; word-wrap:break-word; height:0 !important; min-height:0 !important; overflow:hidden; transition:none; -webkit-transition:none; -moz-transition:none;"/>',e=["fontFamily","fontSize","fontWeight","fontStyle","letterSpacing","textTransform","wordSpacing","textIndent"],f=a(d).data("autosize",!0)[0];f.style.lineHeight="99px","99px"===a(f).css("lineHeight")&&e.push("lineHeight"),f.style.lineHeight="",a.fn.autosize=function(d){return this.length?(d=a.extend({},c,d||{}),f.parentNode!==document.body&&a(document.body).append(f),this.each(function(){function c(){var b,c=window.getComputedStyle?window.getComputedStyle(m,null):!1;c?(b=m.getBoundingClientRect().width,0===b&&(b=parseInt(c.width,10)),a.each(["paddingLeft","paddingRight","borderLeftWidth","borderRightWidth"],function(a,d){b-=parseInt(c[d],10)})):b=Math.max(n.width(),0),f.style.width=b+"px"}function g(){var g={};if(b=m,f.className=d.className,j=parseInt(n.css("maxHeight"),10),a.each(e,function(a,b){g[b]=n.css(b)}),a(f).css(g),c(),window.chrome){var h=m.style.width;m.style.width="0px";{m.offsetWidth}m.style.width=h}}function h(){var e,h;b!==m?g():c(),f.value=!m.value&&d.placeholder?(a(m).attr("placeholder")||"")+d.append:m.value+d.append,f.style.overflowY=m.style.overflowY,h=parseInt(m.style.height,10),f.scrollTop=0,f.scrollTop=9e4,e=f.scrollTop,j&&e>j?(m.style.overflowY="scroll",e=j):(m.style.overflowY="hidden",k>e&&(e=k)),e+=o,h!==e&&(m.style.height=e+"px",p&&d.callback.call(m,m))}function i(){clearTimeout(l),l=setTimeout(function(){var a=n.width();a!==r&&(r=a,h())},parseInt(d.resizeDelay,10))}var j,k,l,m=this,n=a(m),o=0,p=a.isFunction(d.callback),q={height:m.style.height,overflow:m.style.overflow,overflowY:m.style.overflowY,wordWrap:m.style.wordWrap,resize:m.style.resize},r=n.width();n.data("autosize")||(n.data("autosize",!0),("border-box"===n.css("box-sizing")||"border-box"===n.css("-moz-box-sizing")||"border-box"===n.css("-webkit-box-sizing"))&&(o=n.outerHeight()-n.height()),k=Math.max(parseInt(n.css("minHeight"),10)-o||0,n.height()),n.css({overflow:"hidden",overflowY:"hidden",wordWrap:"break-word",resize:"none"===n.css("resize")||"vertical"===n.css("resize")?"none":"horizontal"}),"onpropertychange"in m?"oninput"in m?n.on("input.autosize keyup.autosize",h):n.on("propertychange.autosize",function(){"value"===event.propertyName&&h()}):n.on("input.autosize",h),d.resizeDelay!==!1&&a(window).on("resize.autosize",i),n.on("autosize.resize",h),n.on("autosize.resizeIncludeStyle",function(){b=null,h()}),n.on("autosize.destroy",function(){b=null,clearTimeout(l),a(window).off("resize",i),n.off("autosize").off(".autosize").css(q).removeData("autosize")}),h())})):this}}(window.jQuery||window.$);


//********************************************************
// now start the engine
//********************************************************
jQuery(document).ready( function($) {

// **************************************************************
//  load datepicker
// **************************************************************

	var icon = rkvAsset.icon;

	$( 'input#repo-updated' ).datepick({
		defaultDate:		null,
		selectDefaultDate:	true,
		dateFormat:			'yyyy-mm-dd',
		showTrigger:		icon,
		altField:			'#repo-stamp',
		altFormat:			'@',
    });


	// addon-changelog-field

	$( 'textarea.repo-item-textarea' ).autosize();


//********************************************************
// media uploader for package file
//********************************************************

	// Uploading files
	var file_frame;

	jQuery( 'tr.repo-package-field' ).on('click', 'input.repo-file-upload', function( event ){

		event.preventDefault();

		//get my field ID for later
		var fieldbox = jQuery( 'tr.repo-package-field' ).find( 'input#repo-package' );

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
// load the uploader
//********************************************************

	jQuery( 'tr.repo-screenshots-field' ).on( 'click', 'p.uploader-info', function( event ) {

		event.preventDefault();

		// If the media frame already exists, reopen it.
		if ( file_frame ) {
			file_frame.open();
			return;
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: jQuery( this ).data( 'uploader_title' ),
			button: {
				text: jQuery( this ).data( 'uploader_button_text' )
			},
			multiple: false // Set to true to allow multiple files to be selected
		});

		// When an image is selected, run a callback.
		file_frame.on( 'select', function() {
			// We set multiple to false so only get one image from the uploader
			attachment = file_frame.state().get('selection').first().toJSON();

			// fetch my variables
			var image_id	= attachment.id;
			var thumb_url	= attachment.sizes.thumbnail.url;

			var thumb_show	= '<div class="screenshot-item" data-image="' + image_id + '"><img class="screenshot-image" src="' + thumb_url + '"><span class="dashicons dashicons-no-alt screenshot-remove"></span></div>';

			// Populate the field with the URL and show a preview below it
			jQuery( 'div.repo-screenshot-gallery' ).append( thumb_show );

			// add a hidden field for my images
			jQuery( 'div.repo-screenshot-ids' ).append( '<input type="hidden" class="screenshot-id" name="repo-meta[screenshots][]" value="' + image_id + '" data-image="' + image_id + '" />' );

		});

		// Finally, open the modal
		file_frame.open();


	});


//********************************************************
// sortable screenshots
//********************************************************

	$( 'div.repo-screenshot-gallery' ).each(function() {
		$( this ).sortable({
			cursor: 'move',
		});
	});

//********************************************************
// update IDs on sort
//********************************************************

	$( 'div.repo-screenshot-gallery' ).on( 'sortupdate', function ( event, ui ) {

		// show my spinner
		$( 'p.uploader-info' ).find( 'span.screenshot-spinner' ).css( 'visibility', 'visible' );

		// set array
		var order = {};

		// create array of new items
		$( 'div.repo-screenshot-gallery img' ).each(function(index){
			order[ index ] = $( this ).data( 'image' );
		});

		// clear out existing
		$( 'div.repo-screenshot-ids' ).find( 'input.screenshot-id' ).remove();

		// rebuild item order
		$.each( order, function( key, value ) {
			$( '<input type="hidden" class="screenshot-id" name="repo-meta[screenshots][]" value="' + value + '" data-image="' + value + '" />').appendTo( 'div.repo-screenshot-ids' );
		});

		// hide my spinner, with a delay
		$( 'p.uploader-info' ).find( 'span.screenshot-spinner' ).delay( 800 ).queue( function( next ){
			$( this ).css( 'visibility', 'hidden' );
			next();
		});

	});

//********************************************************
// remove item on click
//********************************************************

	$( 'div.screenshot-item' ).on( 'click', 'span.screenshot-remove', function( event ) {

		var remove_id	= $( this ).parents( 'div.screenshot-item' ).data( 'image' );

		// show my spinner
		$( 'p.uploader-info' ).find( 'span.screenshot-spinner' ).css( 'visibility', 'visible' );

		// remove the hidden field
		$( 'div.repo-screenshot-ids' ).find( 'input.screenshot-id[data-image="' + remove_id + '"]' ).remove();

		// fadeout the actual image then remove it completely
		$( 'div.repo-screenshot-gallery' ).find( 'div.screenshot-item[data-image="' + remove_id + '"]' ).fadeOut( 700, function() {
			$( this ).remove();
    	});

		// hide my spinner, with a delay
		$( 'p.uploader-info' ).find( 'span.screenshot-spinner' ).delay( 800 ).queue( function( next ){
			$( this ).css( 'visibility', 'hidden' );
			next();
		});

	});

//********************************************************
// that's all folks. we're done here
//********************************************************

});
