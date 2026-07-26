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
 * @package MenuOrganizerCollapsibleAdminMenu
 */

( function ( $ ) {
	'use strict';

	var i18n = ( window.wp && window.wp.i18n ) || null;

	/**
	 * Translates a string, falling back to the original when wp.i18n is absent.
	 *
	 * @param {string} text Untranslated text.
	 * @return {string} Translated text.
	 */
	function __( text ) {
		return i18n ? i18n.__( text, 'menu-organizer-collapsible-admin-menu' ) : text;
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
		var $list = $( '.mocam-droplist[data-mocam-group="' + groupId + '"]' );
		var $input = $( '[data-mocam-group-input="' + groupId + '"]' );

		if ( ! $list.length || ! $input.length ) {
			return;
		}

		var slugs = $list
			.children( '.mocam-chip' )
			.map( function () {
				return $( this ).attr( 'data-mocam-slug' );
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
		$( '.mocam-droplist' ).each( function () {
			syncGroup( $( this ).attr( 'data-mocam-group' ) );
		} );
	}

	/**
	 * Updates the visible item count on each group heading.
	 *
	 * @return {void}
	 */
	function refreshCounts() {
		$( '.mocam-group' ).each( function () {
			var $group = $( this );
			var count = $group.find( '.mocam-droplist > .mocam-chip' ).length;
			var $empty = $group.find( '.mocam-empty-note' );

			if ( 0 === count ) {
				if ( ! $empty.length ) {
					$group.find( '.mocam-droplist' ).after(
						$( '<p/>', {
							'class': 'mocam-empty-note description',
							text: __( 'Empty. This group will not appear in the sidebar.' )
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
		var $chip = $( '.mocam-chip[data-mocam-slug="' + slug + '"]' );
		var $target = $( '.mocam-droplist[data-mocam-group="' + groupId + '"]' );

		if ( ! $chip.length || ! $target.length ) {
			return;
		}

		var from = $chip.closest( '.mocam-droplist' ).attr( 'data-mocam-group' );

		$target.append( $chip );
		$chip.find( '.mocam-move-select' ).val( groupId );

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
		$chip.find( '.mocam-move-select' ).trigger( 'focus' );

		announce(
			__( 'Moved to group' ) + ': ' + $target.closest( '.mocam-group' ).find( '.mocam-group-title' ).text().trim()
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
		if ( $( '.mocam-droplist' ).length ) {
			$( '.mocam-droplist' ).sortable( {
				connectWith: '.mocam-droplist',
				handle: '.mocam-chip-handle',
				placeholder: 'mocam-chip-placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				update: function () {
					syncAll();
					refreshCounts();
					markDirty();
				},
				receive: function ( event, ui ) {
					var groupId = $( this ).attr( 'data-mocam-group' );

					// Keep the item's own select honest after a drag, so the two
					// input methods never disagree about where the item is.
					ui.item.find( '.mocam-move-select' ).val( groupId );
				}
			} );

			$( document ).on( 'change', '.mocam-move-select', function () {
				moveChip( $( this ).attr( 'data-mocam-slug' ), $( this ).val() );
			} );

			syncAll();
			refreshCounts();
		}

		// The groups table.
		if ( $( '.mocam-group-rows' ).length ) {
			$( '.mocam-group-rows' ).sortable( {
				handle: '.mocam-drag-handle',
				axis: 'y',
				containment: 'parent',
				update: function () {
					syncGroupOrder();
					markDirty();
				}
			} );

			$( document ).on( 'change', '.mocam-position', function () {
				var $row = $( this ).closest( 'tr' );
				var target = parseInt( $( this ).val(), 10 );
				var $rows = $( '.mocam-group-rows tr' );

				if ( isNaN( target ) || target < 1 || target > $rows.length ) {
					syncGroupOrder();

					return;
				}

				var $reference = $rows.not( $row ).eq( target - 1 );

				if ( ! $reference.length ) {
					$( '.mocam-group-rows' ).append( $row );
				} else if ( target - 1 >= $rows.index( $row ) ) {
					$reference.after( $row );
				} else {
					$reference.before( $row );
				}

				syncGroupOrder();
				markDirty();
				$row.find( '.mocam-position' ).trigger( 'focus' );
			} );

			syncGroupOrder();
		}

		$( '.mocam-settings form' ).on( 'submit', function () {
			dirty = false;
		} );

		$( '.mocam-settings' ).on( 'change input', 'input, select, textarea', markDirty );
	} );

	/**
	 * Rewrites the group order field and renumbers the position inputs.
	 *
	 * @return {void}
	 */
	function syncGroupOrder() {
		var ids = $( '.mocam-group-rows tr' )
			.map( function () {
				return $( this ).attr( 'data-mocam-group' );
			} )
			.get();

		$( '#mocam-group-order' ).val( ids.join( ',' ) );

		$( '.mocam-group-rows tr' ).each( function ( index ) {
			$( this ).find( '.mocam-position' ).val( index + 1 );
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
