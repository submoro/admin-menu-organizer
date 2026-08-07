/**
 * Menu Categories - sidebar accordion behaviour.
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
 * @package AdminMenuCategories
 */

( function () {
	'use strict';

	var data = window.amorgMenu || {};

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

	var COLLAPSED_CLASS = 'amorg-collapsed-member';
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
			menu.querySelectorAll( 'li.amorg-group-' + cssEscape( groupId ) )
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
				var toggle = document.getElementById( 'amorg-toggle-' + group.id );

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
		var groupId = toggle.getAttribute( 'data-amorg-group' );

		toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );

		rowsFor( groupId ).forEach( function ( row ) {
			if ( row.classList.contains( 'amorg-group-header' ) ) {
				return;
			}

			row.classList.toggle( COLLAPSED_CLASS, ! expanded );
		} );

		var state = toggle.querySelector( '.amorg-sr-state' );

		if ( state ) {
			state.textContent = expanded ? __( 'expanded', 'admin-menu-categories' ) : __( 'collapsed', 'admin-menu-categories' );
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
		var header = document.getElementById( 'amorg-group-' + group.id );

		if ( ! header ) {
			return;
		}

		// SPEC section 6.1: a header with nothing behind it must not be focusable.
		// The server already omits empty groups, so this is a second guard.
		var members = rowsFor( group.id ).filter( function ( row ) {
			return ! row.classList.contains( 'amorg-group-header' );
		} );

		if ( ! members.length ) {
			header.classList.add( 'amorg-group-empty' );

			return;
		}

		// Whether the group starts open is read from what the server rendered, not
		// from the group descriptor, so the button can never contradict the paint.
		var startsCollapsed = members.some( function ( row ) {
			return row.classList.contains( COLLAPSED_CLASS );
		} );

		var toggle = document.createElement( 'button' );

		toggle.type = 'button';
		toggle.className = 'amorg-group-toggle';
		toggle.id = 'amorg-toggle-' + group.id;
		toggle.setAttribute( 'data-amorg-group', group.id );
		toggle.setAttribute( 'aria-expanded', startsCollapsed ? 'false' : 'true' );

		var icon = document.createElement( 'span' );
		icon.className = 'amorg-toggle-icon';
		icon.setAttribute( 'aria-hidden', 'true' );
		toggle.appendChild( icon );

		var label = document.createElement( 'span' );
		label.className = 'amorg-toggle-label';
		label.textContent = group.label;

		/*
		 * The stylesheet clamps the label to two lines, so a label long enough to
		 * exceed that is ellipsised. The title attribute is what keeps the full
		 * text reachable in that case. Set unconditionally rather than only when
		 * the clamp bites, because measuring for overflow here would mean reading
		 * layout during setup for every group, and a title on a short label costs
		 * nothing. The button's accessible name is unaffected either way: it comes
		 * from the label's text content, which is complete.
		 */
		label.setAttribute( 'title', group.label );
		toggle.appendChild( label );

		if ( group.updates > 0 ) {
			var badge = document.createElement( 'span' );
			badge.className = 'amorg-toggle-badge';
			badge.textContent = String( group.updates );
			badge.setAttribute(
				'aria-label',
				sprintf(
					/* translators: %s: Number of pending updates. */
					__( '%s pending updates in this group', 'admin-menu-categories' ),
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
		state.className = 'amorg-sr-only amorg-sr-state';
		state.textContent = startsCollapsed ? __( 'collapsed', 'admin-menu-categories' ) : __( 'expanded', 'admin-menu-categories' );
		toggle.appendChild( state );

		var chevron = document.createElement( 'span' );
		chevron.className = 'amorg-toggle-chevron';
		chevron.setAttribute( 'aria-hidden', 'true' );
		toggle.appendChild( chevron );

		// The catch-all is never collapsible, so it gets a label and no control.
		if ( group.permanent ) {
			var plain = document.createElement( 'span' );
			plain.className = 'amorg-group-toggle amorg-group-static';
			plain.appendChild( icon );
			plain.appendChild( label );
			header.appendChild( plain );
			header.classList.add( 'amorg-enhanced' );

			return;
		}

		toggle.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			/*
			 * Inert while a query is active. The stylesheet drops pointer-events on
			 * the header for the same reason, but that only stops the mouse — a
			 * <button> is still activated by Enter and Space from the keyboard, so
			 * without this a keyboard user could collapse a group mid-filter and
			 * have that state saved, while a mouse user could not. The visible
			 * result would barely change, since matches stay revealed regardless;
			 * the saved preference is the part worth not writing.
			 */
			if ( menu.classList.contains( 'amorg-filtering' ) ) {
				return;
			}

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

			/*
			 * Only headers that are actually on screen are traversable. A hidden
			 * one still matches the selector, and calling focus() on a
			 * display:none element does nothing and reports no error, so without
			 * this filter arrowing past a filtered-out group silently stranded
			 * focus where it was. Tested by class rather than by measuring, so a
			 * keypress does not force layout.
			 */
			toggles = Array.prototype.slice
				.call( menu.querySelectorAll( '.amorg-group-toggle[data-amorg-group]' ) )
				.filter( function ( candidate ) {
					var row = candidate.parentNode;

					return row && ! row.classList.contains( 'amorg-filter-hidden' ) &&
						! row.classList.contains( 'amorg-group-empty' );
				} );

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
		header.classList.add( 'amorg-enhanced' );
	}

	data.groups.forEach( enhance );

	/**
	 * Scrolls a group's header to the top of the scrollable sidebar.
	 *
	 * A long sidebar with several groups expanded means opening one near the
	 * bottom reveals its members below the fold, so the click appears to do
	 * nothing. Bringing the header to the top puts what was just opened where the
	 * eye already is.
	 *
	 * Only ever called on expand. Doing it on collapse would yank the page around
	 * for no reason.
	 *
	 * @param {HTMLElement} header The group's list item.
	 */
	function revealAtTop( header ) {
		var wrap = document.getElementById( 'adminmenuwrap' );
		var scroller;
		var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		if ( ! header ) {
			return;
		}

		/*
		 * Which element actually scrolls depends on the viewport. On a tall
		 * desktop nothing does, and scrolling would be wrong; below that the
		 * document scrolls; core's own sticky-menu handling can make the wrap
		 * scroll instead. Pick whichever is genuinely overflowing.
		 */
		if ( wrap && wrap.scrollHeight > wrap.clientHeight + 4 ) {
			scroller = wrap;
		}

		if ( scroller ) {
			scroller.scrollTo( {
				top: header.offsetTop - scroller.offsetTop,
				behavior: reduced ? 'auto' : 'smooth'
			} );

			return;
		}

		// Nothing to do if the whole menu already fits on screen.
		if ( header.getBoundingClientRect().top >= 0 && document.documentElement.scrollHeight <= window.innerHeight ) {
			return;
		}

		header.scrollIntoView( {
			block: 'start',
			behavior: reduced ? 'auto' : 'smooth'
		} );
	}

	/*
	 * Reveal on expand. Bound after enhance() has created every button, and via
	 * delegation so it cannot double-bind.
	 */
	menu.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest ? event.target.closest( '.amorg-group-toggle[data-amorg-group]' ) : null;

		if ( ! toggle ) {
			return;
		}

		// Read after the handler that flips it has run.
		window.setTimeout( function () {
			if ( 'true' === toggle.getAttribute( 'aria-expanded' ) ) {
				revealAtTop( toggle.parentNode );
			}
		}, 0 );
	} );

	/* ---------------------------------------------------------------------
	 * Filter box.
	 *
	 * A grouped sidebar trades scanning for clicking: an item you cannot see is
	 * behind a collapsed header, and on a thirty-plugin site you may not know
	 * which. Typing is faster than guessing.
	 *
	 * Injected by script rather than rendered server-side for the same reason the
	 * group headers are: core gives no hook that can place markup inside
	 * #adminmenu, and the one field it emits unescaped must never carry input.
	 * With JavaScript off there is no box, and the sidebar is a plain grouped
	 * menu, which is a fair degradation for a convenience.
	 * ------------------------------------------------------------------ */

	/**
	 * Returns every real menu row, group members and ungrouped items alike.
	 *
	 * @return {HTMLElement[]} The rows.
	 */
	function allRows() {
		return Array.prototype.slice.call(
			menu.querySelectorAll( 'li.menu-top:not(.amorg-group-header)' )
		);
	}

	/**
	 * Builds the filter box and wires it up.
	 *
	 * @return {void}
	 */
	function buildFilter() {
		var item = document.createElement( 'li' );
		var input = document.createElement( 'input' );
		var label = document.createElement( 'label' );
		var status = document.createElement( 'span' );
		var inputId = 'amorg-filter-input';
		var rows = null;
		var groupIndex = null;

		item.className = 'amorg-filter';

		label.className = 'amorg-sr-only';
		label.setAttribute( 'for', inputId );
		label.textContent = __( 'Filter menu items', 'admin-menu-categories' );

		input.type = 'search';
		input.id = inputId;
		input.className = 'amorg-filter-input';
		input.autocomplete = 'off';
		input.setAttribute( 'placeholder', __( 'Filter menu…', 'admin-menu-categories' ) );

		/*
		 * A live region, so a screen-reader user hears how many items matched.
		 * Without it, typing silently rearranges the menu underneath them.
		 */
		status.className = 'amorg-sr-only';
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );

		item.appendChild( label );
		item.appendChild( input );
		item.appendChild( status );

		menu.insertBefore( item, menu.firstChild );

		/**
		 * Indexes the rows once, and the header each group's rows belong to.
		 *
		 * Built on first use rather than at setup, because the menu does not change
		 * after load and a query may never be typed.
		 *
		 * @return {void}
		 */
		function buildCache() {
			rows = allRows().map( function ( row ) {
				var name = row.querySelector( '.wp-menu-name' );

				return {
					el: row,
					text: ( name ? name.textContent : row.textContent ).trim().toLowerCase()
				};
			} );

			/*
			 * classList.contains() takes a literal token, so the group ID needs no
			 * selector escaping here — unlike rowsFor(), which builds a selector.
			 */
			groupIndex = data.groups
				.map( function ( group ) {
					var header = document.getElementById( 'amorg-group-' + group.id );

					return {
						header: header,
						toggle: header ? header.querySelector( '.amorg-group-toggle[data-amorg-group]' ) : null,
						rows: rows.filter( function ( row ) {
							return row.el.classList.contains( 'amorg-group-' + group.id );
						} )
					};
				} )
				.filter( function ( entry ) {
					return !! entry.header;
				} );
		}

		/**
		 * Applies the current query.
		 *
		 * @return {void}
		 */
		function apply() {
			var query = input.value.trim().toLowerCase();
			var matches = 0;

			if ( null === rows ) {
				buildCache();
			}

			menu.classList.toggle( 'amorg-filtering', '' !== query );

			if ( '' === query ) {
				rows.forEach( function ( row ) {
					row.el.classList.remove( 'amorg-filter-hidden' );
				} );
				groupIndex.forEach( function ( entry ) {
					entry.header.classList.remove( 'amorg-filter-hidden' );

					if ( entry.toggle ) {
						entry.toggle.removeAttribute( 'aria-disabled' );
					}
				} );
				status.textContent = '';

				return;
			}

			rows.forEach( function ( row ) {
				var hit = -1 !== row.text.indexOf( query );

				row.el.classList.toggle( 'amorg-filter-hidden', ! hit );

				if ( hit ) {
					matches++;
				}
			} );

			/*
			 * Headers are kept so a match is shown under the group it belongs to,
			 * which is the question someone hunting through collapsed groups
			 * actually has. A header whose members all filtered out would be
			 * stranded above nothing, so that one is hidden instead.
			 */
			groupIndex.forEach( function ( entry ) {
				var kept = entry.rows.some( function ( row ) {
					return ! row.el.classList.contains( 'amorg-filter-hidden' );
				} );

				entry.header.classList.toggle( 'amorg-filter-hidden', ! kept );

				/*
				 * The header stops acting while a query is active, so it has to say
				 * so. aria-disabled rather than the disabled attribute: disabled
				 * would drop the button out of the focus order and, in some screen
				 * readers, out of the accessibility tree altogether, which is a
				 * larger change than "this control is inert for now" and would move
				 * focus unpredictably mid-typing. The heading text stays announced
				 * either way, which is the part carrying the information.
				 */
				if ( entry.toggle ) {
					entry.toggle.setAttribute( 'aria-disabled', 'true' );
				}
			} );

			status.textContent = sprintf(
				/* translators: %s: Number of matching menu items. */
				__( '%s items match', 'admin-menu-categories' ),
				String( matches )
			);
		}

		input.addEventListener( 'input', apply );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				input.value = '';
				apply();

				return;
			}

			/*
			 * Enter follows the first match, which is what anyone who has used a
			 * command palette expects, and saves a reach for the mouse.
			 */
			if ( 'Enter' === event.key ) {
				var first = menu.querySelector(
					'li.menu-top:not(.amorg-group-header):not(.amorg-filter-hidden) > a'
				);

				if ( first ) {
					event.preventDefault();
					first.click();
				}
			}
		} );
	}

	// Not worth a filter box for a menu short enough to read at a glance.
	if ( allRows().length >= 8 ) {
		buildFilter();
	}

	/*
	 * Core re-runs its own menu sizing when the sidebar is folded or the viewport
	 * crosses a breakpoint. Nothing here needs to react to that, because the
	 * folded and responsive presentations are pure CSS, which is deliberate: a
	 * resize handler that mutates the menu would fight core's own.
	 */
}() );
