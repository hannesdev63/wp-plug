/* global wp */
( function ( $ ) {
	'use strict';

	// Image picker via WP Media Library.
	$( document ).on( 'click', '.ms365-image-select', function ( e ) {
		e.preventDefault();

		var $btn     = $( this );
		var targetId = $btn.data( 'target' );
		var previewId = $btn.data( 'preview' );

		var frame = wp.media( {
			title:    $btn.data( 'title' ) || 'Select Image',
			button:   { text: 'Use this image' },
			multiple: false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url = attachment.url;

			$( '#' + targetId ).val( url ).trigger( 'change' );

			var $preview = $( '#' + previewId );
			$preview.html( '<img src="' + url + '" alt="" style="max-width:200px;max-height:80px;display:block;" />' );

			// Size warning: the image is rendered as a small icon (20×20 px).
			// Flag anything larger than 200×200 px so the admin knows.
			var $warning = $preview.siblings( '.ms365-image-size-warning' );
			if ( attachment.width > 200 || attachment.height > 200 ) {
				if ( ! $warning.length ) {
					$warning = $( '<p class="ms365-image-size-warning" style="color:#b32d2e;margin-top:4px;"></p>' );
					$preview.after( $warning );
				}
				$warning.text(
					'The selected image is ' + attachment.width + '×' + attachment.height + ' px. ' +
					'For best results use an image no larger than 200×200 px — it is rendered as a small icon (20×20 px).'
				).show();
			} else {
				$warning.hide();
			}

			// Show the Remove button if not already visible.
			$btn.siblings( '.ms365-image-remove' ).show();
		} );

		frame.open();
	} );

	// Remove button.
	$( document ).on( 'click', '.ms365-image-remove', function ( e ) {
		e.preventDefault();

		var $btn     = $( this );
		var targetId = $btn.data( 'target' );
		var previewId = $btn.data( 'preview' );

		$( '#' + targetId ).val( '' ).trigger( 'change' );
		$( '#' + previewId ).empty();
		$( '#' + previewId ).siblings( '.ms365-image-size-warning' ).hide();
		$btn.hide();
	} );

} )( jQuery );
