/**
 * Menu Organizer - sidebar accordion behaviour.
 *
 * Progressive enhancement only. Grouping, ordering and the initial open or closed
 * state are all rendered by the server, so this file never decides what the
 * sidebar looks like on load. Its job is to turn each header row into a real
 * button, toggle groups, and remember the result.
 *
 * The header rows arrive as empty <li> elements with their label and icon
 * supplied as CSS custom properties, which is why a header is already readable
 * before this runs. See includes/class-menu-renderer.php for why core cannot be
 * made to emit a button itself.
 *
 * @package WPAdminMenuOrganizer
 */

( function () {
	'use strict';

	var data = window.wpamoMenu || {};

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

	/**
	 * Substitutes a single %s placeholder.
	 *
	 * @param {string} template Template containing %s.
	 * @param {string} value    Replacement.
	 * @return {string} Interpolated string.
	 */
	function sprintf( template, value ) {
		return template.replace( '%s', value );
	}

	var COLLAPSED_CLASS = 'wpamo-collapsed-member';
	var SAVE_DEBOUNCE_MS = 400;

	var menu = document.getElementById( 'adminmenu' );

	if ( ! menu || ! data.groups || ! data.groups.length ) {
		return;
	}

	var saveTimer = null;
	var retried = false;

	/**
	 * Returns the rows belonging to a group.
	 *
	 * @param {string} groupId Group identifier.
	 * @return {Element[]} Matching row elements.
	 */
	function rowsFor( groupId ) {
		return Array.prototype.slice.call(
			menu.querySelectorAll( 'li.wpamo-group-' + cssEscape( groupId ) )
		);
	}

	/**
	 * Escapes a value for use in a CSS selector.
	 *
	 * Group IDs are sanitised server-side to [a-z0-9_-], so this is belt and
	 * braces rather than the only defence, but building a selector from stored
	 * data without escaping is a habit worth not forming.
	 *
	 * @param {string} value Raw value.
	 * @return {string} Escaped value.
	 */
	function cssEscape( value ) {
		if ( window.CSS && typeof window.CSS.escape === 'function' ) {
			return window.CSS.escape( value );
		}

		return String( value ).replace( /[^a-zA-Z0-9_-]/g, '' );
	}

	/**
	 * Collects the group IDs currently collapsed, in DOM order.
	 *
	 * Read from the DOM rather than from a variable, so the saved value always
	 * matches what the user can see even if something else changed the classes.
	 *
	 * @return {string[]} Collapsed group IDs.
	 */
	function collapsedIds() {
		return data.groups
			.filter( function ( group ) {
				var toggle = document.getElementById( 'wpamo-toggle-' + group.id );

				return toggle && 'false' === toggle.getAttribute( 'aria-expanded' );
			} )
			.map( function ( group ) {
				return group.id;
			} );
	}

	/**
	 * Persists the collapsed state.
	 *
	 * Debounced, optimistic and deliberately quiet. The visual state has already
	 * changed by the time this runs, and a failure to store a cosmetic preference
	 * is not worth interrupting anyone for, so there is one retry and then
	 * silence. SPEC section 6.2.
	 *
	 * @return {void}
	 */
	function save() {
		if ( ! data.restUrl || ! window.fetch ) {
			return;
		}

		window.clearTimeout( saveTimer );

		saveTimer = window.setTimeout( function () {
			window
				.fetch( data.restUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': data.nonce
					},
					body: JSON.stringify( { collapsed: collapsedIds() } )
				} )
				.then( function ( response ) {
					if ( response.ok ) {
						retried = false;

						return;
					}

					throw new Error( 'save failed' );
				} )
				.catch( function () {
					if ( retried ) {
						return;
					}

					retried = true;
					save();
				} );
		}, SAVE_DEBOUNCE_MS );
	}

	/**
	 * Opens or closes a group.
	 *
	 * @param {Element} toggle   The group's button.
	 * @param {boolean} expanded Whether the group should end up open.
	 * @param {boolean} persist  Whether to save the change.
	 * @return {void}
	 */
	function setExpanded( toggle, expanded, persist ) {
		var groupId = toggle.getAttribute( 'data-wpamo-group' );

		toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );

		rowsFor( groupId ).forEach( function ( row ) {
			if ( row.classList.contains( 'wpamo-group-header' ) ) {
				return;
			}

			row.classList.toggle( COLLAPSED_CLASS, ! expanded );
		} );

		var state = toggle.querySelector( '.wpamo-sr-state' );

		if ( state ) {
			state.textContent = expanded ? __( 'expanded', 'wp-admin-menu-organizer' ) : __( 'collapsed', 'wp-admin-menu-organizer' );
		}

		if ( persist ) {
			save();
		}
	}

	/**
	 * Builds the button for one group header and wires it up.
	 *
	 * @param {Object} group Group descriptor from the server.
	 * @return {void}
	 */
	function enhance( group ) {
		var header = document.getElementById( 'wpamo-group-' + group.id );

		if ( ! header ) {
			return;
		}

		// SPEC section 6.1: a header with nothing behind it must not be focusable.
		// The server already omits empty groups, so this is a second guard.
		var members = rowsFor( group.id ).filter( function ( row ) {
			return ! row.classList.contains( 'wpamo-group-header' );
		} );

		if ( ! members.length ) {
			header.classList.add( 'wpamo-group-empty' );

			return;
		}

		// Whether the group starts open is read from what the server rendered, not
		// from the group descriptor, so the button can never contradict the paint.
		var startsCollapsed = members.some( function ( row ) {
			return row.classList.contains( COLLAPSED_CLASS );
		} );

		var toggle = document.createElement( 'button' );

		toggle.type = 'button';
		toggle.className = 'wpamo-group-toggle';
		toggle.id = 'wpamo-toggle-' + group.id;
		toggle.setAttribute( 'data-wpamo-group', group.id );
		toggle.setAttribute( 'aria-expanded', startsCollapsed ? 'false' : 'true' );

		var icon = document.createElement( 'span' );
		icon.className = 'wpamo-toggle-icon';
		icon.setAttribute( 'aria-hidden', 'true' );
		toggle.appendChild( icon );

		var label = document.createElement( 'span' );
		label.className = 'wpamo-toggle-label';
		label.textContent = group.label;
		toggle.appendChild( label );

		if ( group.updates > 0 ) {
			var badge = document.createElement( 'span' );
			badge.className = 'wpamo-toggle-badge';
			badge.textContent = String( group.updates );
			badge.setAttribute(
				'aria-label',
				sprintf(
					/* translators: %s: Number of pending updates. */
					__( '%s pending updates in this group', 'wp-admin-menu-organizer' ),
					String( group.updates )
				)
			);
			toggle.appendChild( badge );
		}

		/*
		 * The group's own name is already in the button's text, so the
		 * screen-reader addition is only the state word. aria-expanded conveys it
		 * too, but not every screen reader announces it on a non-native
		 * disclosure, and the redundancy costs nothing.
		 */
		var state = document.createElement( 'span' );
		state.className = 'wpamo-sr-only wpamo-sr-state';
		state.textContent = startsCollapsed ? __( 'collapsed', 'wp-admin-menu-organizer' ) : __( 'expanded', 'wp-admin-menu-organizer' );
		toggle.appendChild( state );

		var chevron = document.createElement( 'span' );
		chevron.className = 'wpamo-toggle-chevron';
		chevron.setAttribute( 'aria-hidden', 'true' );
		toggle.appendChild( chevron );

		// The catch-all is never collapsible, so it gets a label and no control.
		if ( group.permanent ) {
			var plain = document.createElement( 'span' );
			plain.className = 'wpamo-group-toggle wpamo-group-static';
			plain.appendChild( icon );
			plain.appendChild( label );
			header.appendChild( plain );
			header.classList.add( 'wpamo-enhanced' );

			return;
		}

		toggle.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			setExpanded( toggle, 'false' === toggle.getAttribute( 'aria-expanded' ), true );
		} );

		/*
		 * Enter and Space are handled natively by a real <button>, which is the
		 * main reason this is a button rather than a div with a role. Home and End
		 * are added because a long sidebar of groups is easier to traverse with
		 * them, and they are what a native disclosure list would support.
		 */
		toggle.addEventListener( 'keydown', function ( event ) {
			var toggles;

			if ( 'ArrowDown' !== event.key && 'ArrowUp' !== event.key ) {
				return;
			}

			toggles = Array.prototype.slice.call( menu.querySelectorAll( '.wpamo-group-toggle[data-wpamo-group]' ) );

			var index = toggles.indexOf( toggle );

			if ( -1 === index ) {
				return;
			}

			var next = 'ArrowDown' === event.key ? index + 1 : index - 1;

			if ( next < 0 || next >= toggles.length ) {
				return;
			}

			event.preventDefault();
			toggles[ next ].focus();
		} );

		header.appendChild( toggle );
		header.classList.add( 'wpamo-enhanced' );
	}

	data.groups.forEach( enhance );

	/*
	 * Core re-runs its own menu sizing when the sidebar is folded or the viewport
	 * crosses a breakpoint. Nothing here needs to react to that, because the
	 * folded and responsive presentations are pure CSS, which is deliberate: a
	 * resize handler that mutates the menu would fight core's own.
	 */
}() );
