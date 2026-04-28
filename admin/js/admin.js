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
		$btn.hide();
	} );

} )( jQuery );
