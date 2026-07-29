/**
 * Menu Organizer - settings screen editor.
 *
 * Built on jquery-ui-sortable, which ships with WordPress. Bundling SortableJS or
 * React would breach both SPEC section 7.2 and the directory's rule against
 * shipping a copy of a library core already provides.
 *
 * Dragging is an enhancement, never the only route. Every item carries a
 * "Move to group" select and every group a position field, both of which work
 * with no pointer at all and write to exactly the same hidden fields the drag
 * handler does. That is a hard accessibility requirement, not a nicety: a
 * drag-only editor is unusable with a keyboard and would fail review.
 *
 * @package AdminMenuOrganizer
 */

( function ( $ ) {
	'use strict';

	/**
	 * Translates a string, falling back to the original when wp.i18n is absent.
	 *
	 * The text domain is passed by every caller rather than being supplied here,
	 * because string extraction is static: `wp i18n make-pot` reads the literal
	 * arguments at each call site and skips any call whose domain it cannot see.
	 * Hiding the domain inside this function would leave every JavaScript string
	 * out of the POT file and therefore permanently untranslatable, which SPEC
	 * section 9 forbids.
	 *
	 * @param {string} text   Untranslated text.
	 * @param {string} domain Text domain, always passed literally.
	 * @return {string} Translated text.
	 */
	function __( text, domain ) {
		if ( window.wp && window.wp.i18n ) {
			return window.wp.i18n.__( text, domain );
		}

		return text;
	}

	var dirty = false;

	/**
	 * Marks the form as having unsaved changes.
	 *
	 * @return {void}
	 */
	function markDirty() {
		dirty = true;
	}

	/**
	 * Rewrites the hidden field for one group from the chips it now contains.
	 *
	 * The hidden fields are the single source of truth for what gets submitted, so
	 * both the drag handler and the keyboard controls funnel through here. That is
	 * what keeps the two input methods from drifting apart.
	 *
	 * @param {string} groupId Group identifier.
	 * @return {void}
	 */
	function syncGroup( groupId ) {
		var $list = $( '.amorg-droplist[data-amorg-group="' + groupId + '"]' );
		var $input = $( '[data-amorg-group-input="' + groupId + '"]' );

		if ( ! $list.length || ! $input.length ) {
			return;
		}

		var slugs = $list
			.children( '.amorg-chip' )
			.map( function () {
				return $( this ).attr( 'data-amorg-slug' );
			} )
			.get();

		$input.val( slugs.join( ',' ) );
	}

	/**
	 * Rewrites every group's hidden field.
	 *
	 * @return {void}
	 */
	function syncAll() {
		$( '.amorg-droplist' ).each( function () {
			syncGroup( $( this ).attr( 'data-amorg-group' ) );
		} );
	}

	/**
	 * Updates the visible item count on each group heading.
	 *
	 * @return {void}
	 */
	function refreshCounts() {
		$( '.amorg-group' ).each( function () {
			var $group = $( this );
			var count = $group.find( '.amorg-droplist > .amorg-chip' ).length;
			var $empty = $group.find( '.amorg-empty-note' );

			if ( 0 === count ) {
				if ( ! $empty.length ) {
					$group.find( '.amorg-droplist' ).after(
						$( '<p/>', {
							'class': 'amorg-empty-note description',
							text: __( 'Empty. This group will not appear in the sidebar.', 'admin-menu-organizer' )
						} )
					);
				}
			} else {
				$empty.remove();
			}
		} );
	}

	/**
	 * Moves a chip into a different group, keeping its select in step.
	 *
	 * @param {string} slug    Menu slug to move.
	 * @param {string} groupId Destination group.
	 * @return {void}
	 */
	function moveChip( slug, groupId ) {
		var $chip = $( '.amorg-chip[data-amorg-slug="' + slug + '"]' );
		var $target = $( '.amorg-droplist[data-amorg-group="' + groupId + '"]' );

		if ( ! $chip.length || ! $target.length ) {
			return;
		}

		var from = $chip.closest( '.amorg-droplist' ).attr( 'data-amorg-group' );

		$target.append( $chip );
		$chip.find( '.amorg-move-select' ).val( groupId );

		syncGroup( from );
		syncGroup( groupId );
		refreshCounts();
		markDirty();

		/*
		 * Keyboard users get no visual continuity from a move the way a dragger
		 * does, so focus follows the item to its new home. Without this, focus
		 * would land back at the top of the document and the user would have to
		 * find their place again.
		 */
		$chip.find( '.amorg-move-select' ).trigger( 'focus' );

		announce(
			__( 'Moved to group', 'admin-menu-organizer' ) + ': ' + $target.closest( '.amorg-group' ).find( '.amorg-group-title' ).text().trim()
		);
	}

	/**
	 * Announces a message to assistive technology.
	 *
	 * Uses core's own wp.a11y.speak when available, since it already owns a live
	 * region and adding a second one would make announcements compete.
	 *
	 * @param {string} message Text to announce.
	 * @return {void}
	 */
	function announce( message ) {
		if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( message, 'polite' );
		}
	}

	$( function () {
		// The layout editor.
		if ( $( '.amorg-droplist' ).length ) {
			$( '.amorg-droplist' ).sortable( {
				connectWith: '.amorg-droplist',
				handle: '.amorg-chip-handle',
				placeholder: 'amorg-chip-placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				update: function () {
					syncAll();
					refreshCounts();
					markDirty();
				},
				receive: function ( event, ui ) {
					var groupId = $( this ).attr( 'data-amorg-group' );

					// Keep the item's own select honest after a drag, so the two
					// input methods never disagree about where the item is.
					ui.item.find( '.amorg-move-select' ).val( groupId );
				}
			} );

			$( document ).on( 'change', '.amorg-move-select', function () {
				moveChip( $( this ).attr( 'data-amorg-slug' ), $( this ).val() );
			} );

			syncAll();
			refreshCounts();
		}

		// The groups table.
		if ( $( '.amorg-group-rows' ).length ) {
			$( '.amorg-group-rows' ).sortable( {
				handle: '.amorg-drag-handle',
				axis: 'y',
				containment: 'parent',
				update: function () {
					syncGroupOrder();
					markDirty();
				}
			} );

			$( document ).on( 'change', '.amorg-position', function () {
				var $row = $( this ).closest( 'tr' );
				var target = parseInt( $( this ).val(), 10 );
				var $rows = $( '.amorg-group-rows tr' );

				if ( isNaN( target ) || target < 1 || target > $rows.length ) {
					syncGroupOrder();

					return;
				}

				var $reference = $rows.not( $row ).eq( target - 1 );

				if ( ! $reference.length ) {
					$( '.amorg-group-rows' ).append( $row );
				} else if ( target - 1 >= $rows.index( $row ) ) {
					$reference.after( $row );
				} else {
					$reference.before( $row );
				}

				syncGroupOrder();
				markDirty();
				$row.find( '.amorg-position' ).trigger( 'focus' );
			} );

			syncGroupOrder();
		}

		$( '.amorg-settings form' ).on( 'submit', function () {
			dirty = false;
		} );

		$( '.amorg-settings' ).on( 'change input', 'input, select, textarea', markDirty );
	} );

	/**
	 * Rewrites the group order field and renumbers the position inputs.
	 *
	 * @return {void}
	 */
	function syncGroupOrder() {
		var ids = $( '.amorg-group-rows tr' )
			.map( function () {
				return $( this ).attr( 'data-amorg-group' );
			} )
			.get();

		$( '#amorg-group-order' ).val( ids.join( ',' ) );

		$( '.amorg-group-rows tr' ).each( function ( index ) {
			$( this ).find( '.amorg-position' ).val( index + 1 );
		} );
	}

	/*
	 * SPEC section 7.2 requires an unsaved-changes warning. Modern browsers ignore
	 * any custom message and show their own, so returnValue is set purely to
	 * trigger the prompt.
	 */
	window.addEventListener( 'beforeunload', function ( event ) {
		if ( ! dirty ) {
			return undefined;
		}

		event.preventDefault();
		event.returnValue = '';

		return '';
	} );
}( window.jQuery ) );
