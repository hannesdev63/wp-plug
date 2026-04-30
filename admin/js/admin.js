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

	// ------------------------------------------------------------------
	// Custom template toggle: pre-fill textarea on first activation.
	//
	// Rules:
	// ------------------------------------------------------------------
	// Named templates repeater: add/remove rows with re-indexing.
	// ------------------------------------------------------------------

	function getPlaceholderOptions( $wrap ) {
		var raw = $wrap.attr( 'data-placeholder-options' ) || '[]';
		try {
			var parsed = JSON.parse( raw );
			return Array.isArray( parsed ) ? parsed : [];
		} catch ( e ) {
			return [];
		}
	}

	function buildPlaceholderPicker( $wrap ) {
		var i18n = {
			prompt: $wrap.data( 'i18n-ph-prompt' ) || 'Insert placeholder...',
			insert: $wrap.data( 'i18n-ph-insert' ) || 'Insert',
			groupGeneral: $wrap.data( 'i18n-ph-group-general' ) || 'Template-level placeholders',
			groupList: $wrap.data( 'i18n-ph-group-list' ) || 'Item-list placeholders',
			listHint: $wrap.data( 'i18n-ph-list-hint' ) || '',
		};

		var options = getPlaceholderOptions( $wrap );
		var general = [];
		var listOnly = [];
		options.forEach( function ( opt ) {
			if ( opt && opt.token ) {
				if ( opt.list_only ) {
					listOnly.push( opt );
				} else {
					general.push( opt );
				}
			}
		} );

		var $tools = $( '<div class="ms365-named-templates__placeholder-tools"></div>' );
		var $select = $( '<select class="ms365-named-templates__placeholder-picker"></select>' );
		$select.append( $( '<option value=""></option>' ).text( i18n.prompt ) );

		if ( general.length ) {
			var $groupGeneral = $( '<optgroup></optgroup>' ).attr( 'label', i18n.groupGeneral );
			general.forEach( function ( opt ) {
				$groupGeneral.append(
					$( '<option></option>' )
						.attr( 'value', opt.token )
						.attr( 'data-list-only', '0' )
						.text( opt.label + ' (' + opt.token + ')' )
				);
			} );
			$select.append( $groupGeneral );
		}

		if ( listOnly.length ) {
			var $groupList = $( '<optgroup></optgroup>' ).attr( 'label', i18n.groupList );
			listOnly.forEach( function ( opt ) {
				$groupList.append(
					$( '<option></option>' )
						.attr( 'value', opt.token )
						.attr( 'data-list-only', '1' )
						.text( opt.label + ' (' + opt.token + ')' )
				);
			} );
			$select.append( $groupList );
		}

		$tools.append( $select );
		$tools.append( $( '<button type="button" class="button button-small ms365-named-templates__placeholder-insert"></button>' ).text( i18n.insert ) );
		if ( i18n.listHint ) {
			$tools.append( $( '<p class="description ms365-named-templates__placeholder-hint"></p>' ).text( i18n.listHint ) );
		}

		return $tools;
	}

	function ensureRowPicker( $row, $wrap ) {
		if ( $row.find( '.ms365-named-templates__placeholder-tools' ).length ) {
			return;
		}
		$row.append( buildPlaceholderPicker( $wrap ) );
	}

	function cursorInsideItemsBlock( text, cursorPos ) {
		var before = text.slice( 0, cursorPos );
		var openIdx = before.lastIndexOf( '{{#items}}' );
		if ( openIdx < 0 ) {
			return false;
		}
		var closeIdx = before.lastIndexOf( '{{/items}}' );
		return closeIdx < openIdx;
	}

	function insertIntoTextarea( $textarea, insertion ) {
		var el = $textarea.get( 0 );
		if ( ! el || typeof el.selectionStart !== 'number' || typeof el.selectionEnd !== 'number' ) {
			$textarea.val( ( $textarea.val() || '' ) + insertion );
			return;
		}

		var value = $textarea.val() || '';
		var start = el.selectionStart;
		var end = el.selectionEnd;
		var updated = value.slice( 0, start ) + insertion + value.slice( end );
		$textarea.val( updated );

		var next = start + insertion.length;
		el.focus();
		el.setSelectionRange( next, next );
	}

	$( '.ms365-named-templates' ).each( function () {
		var $wrap = $( this );
		$wrap.find( '.ms365-named-templates__row' ).each( function () {
			ensureRowPicker( $( this ), $wrap );
		} );
	} );

	$( document ).on( 'click', '.ms365-named-templates__add', function () {
		var $btn   = $( this );
		var $wrap  = $btn.closest( '.ms365-named-templates' );
		var $list  = $wrap.find( '.ms365-named-templates__list' );
		var max    = parseInt( $wrap.data( 'max' ), 10 ) || 20;
		var scope  = $wrap.data( 'scope' );
		var count  = $list.find( '.ms365-named-templates__row' ).length;

		if ( count >= max ) { return; }

		var base   = 'wp_ms365_settings[shortcode_render_' + scope + '_named_templates]';
		var i18n   = {
			key:    $wrap.data( 'i18n-key' ),
			keyPh:  $wrap.data( 'i18n-key-ph' ),
			remove: $wrap.data( 'i18n-remove' ),
			tplPh:  $wrap.data( 'i18n-tpl-ph' ),
		};

		// Build row via DOM to avoid any attribute-injection risk.
		var $row = $( '<div class="ms365-named-templates__row">' +
			'<div class="ms365-named-templates__row-header">' +
				'<label>' +
					'<span class="ms365-named-templates__key-label"></span>' +
					'<input type="text" class="regular-text" />' +
				'</label>' +
				'<button type="button" class="button-link ms365-named-templates__remove"></button>' +
			'</div>' +
			'<textarea class="large-text code ms365-named-templates__textarea" rows="8"></textarea>' +
		'</div>' );

		$row.find( '.ms365-named-templates__key-label' ).text( i18n.key + ': ' );
		$row.find( 'input' )
			.attr( 'name', base + '[' + count + '][key]' )
			.attr( 'placeholder', i18n.keyPh );
		$row.find( '.ms365-named-templates__remove' ).text( i18n.remove );
		$row.find( 'textarea' )
			.attr( 'name', base + '[' + count + '][template]' )
			.attr( 'placeholder', i18n.tplPh )
			.val( i18n.tplPh );
		ensureRowPicker( $row, $wrap );

		$list.append( $row );
		count++;
		$btn.prop( 'disabled', count >= max );
	} );

	$( document ).on( 'click', '.ms365-named-templates__remove', function () {
		var $row  = $( this ).closest( '.ms365-named-templates__row' );
		var $wrap = $row.closest( '.ms365-named-templates' );
		var $list = $wrap.find( '.ms365-named-templates__list' );
		var scope = $wrap.data( 'scope' );
		var max   = parseInt( $wrap.data( 'max' ), 10 ) || 20;
		var base  = 'wp_ms365_settings[shortcode_render_' + scope + '_named_templates]';

		$row.remove();

		// Re-index remaining rows so PHP receives a clean 0-based array.
		$list.find( '.ms365-named-templates__row' ).each( function ( i ) {
			$( this ).find( 'input[type="text"]' ).attr( 'name', base + '[' + i + '][key]' );
			$( this ).find( 'textarea' ).attr( 'name', base + '[' + i + '][template]' );
		} );

		$wrap.find( '.ms365-named-templates__add' ).prop( 'disabled', false );
	} );

	$( document ).on( 'click', '.ms365-named-templates__placeholder-insert', function () {
		var $btn = $( this );
		var $row = $btn.closest( '.ms365-named-templates__row' );
		var $textarea = $row.find( '.ms365-named-templates__textarea' );
		var $select = $row.find( '.ms365-named-templates__placeholder-picker' );
		var $selected = $select.find( 'option:selected' );
		var token = $selected.val() || '';

		if ( ! token ) {
			return;
		}

		var el = $textarea.get( 0 );
		var value = $textarea.val() || '';
		var pos = ( el && typeof el.selectionStart === 'number' ) ? el.selectionStart : value.length;
		var isListOnly = String( $selected.data( 'list-only' ) ) === '1';

		if ( isListOnly && ! cursorInsideItemsBlock( value, pos ) ) {
			insertIntoTextarea( $textarea, '{{#items}}\n  ' + token + '\n{{/items}}' );
		} else {
			insertIntoTextarea( $textarea, token );
		}

		$select.val( '' );
	} );

	$( document ).on( 'change', '.ms365-named-templates__picker', function () {
		var $select = $( this );
		var key = ( $select.val() || '' ).trim();
		var $wrap = $select.closest( '.ms365-named-templates__picker-wrap' );
		var $snippet = $wrap.find( '.ms365-named-templates__picker-snippet' );
		var base = $snippet.data( 'default-snippet' ) || '';

		if ( ! key ) {
			$snippet.val( base );
			return;
		}

		var snippet;
		if ( base.slice( -1 ) === ']' ) {
			snippet = base.slice( 0, -1 ) + ' template="' + key + '"]';
		} else {
			snippet = base + ' template="' + key + '"';
		}

		$snippet.val( snippet );
	} );

} )( jQuery );
