<?php
/**
 * Decoration of the rendered admin menu.
 *
 * @package AdminMenuOrganizer
 * @since   1.0.0
 */

namespace AMORG;

defined( 'ABSPATH' ) || exit;

/**
 * Injects group classes and accordion header rows into the menu WordPress renders.
 *
 * The header carrier is the finding that shaped this whole class. SPEC section
 * 3.3 step 5 proposed a wp-menu-separator item, which core renders as an empty
 * aria-hidden div with the title and icon discarded, and which core's own
 * adjacent-separator cleanup can delete. See docs/core-notes.md section 2.
 *
 * What works instead: menu-header.php gates its link branch on
 * `! empty( $item[2] ) && current_user_can( $item[1] )`. Failing the capability
 * half, with no submenu present, makes every render branch fall through, and core
 * emits a bare <li> that still carries our classes and id and is *not*
 * aria-hidden. The capability used is do_not_allow, core's canonical never-cap.
 *
 * Injection happens on add_menu_classes rather than admin_menu, because
 * admin_menu is followed by a loop in includes/menu.php that deletes exactly this
 * kind of item. add_menu_classes is the last filter to touch $menu, which has the
 * further benefit that our synthetic rows are invisible to every other plugin.
 *
 * @since 1.0.0
 */
final class Menu_Renderer {

	/**
	 * Capability given to header rows so core renders them without a link.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const HEADER_CAP = 'do_not_allow';

	/**
	 * Prefix for the synthetic slug each header row carries.
	 *
	 * Header rows need a unique, non-empty slug: an empty one would make them
	 * collide with each other in core's array_flip() based ordering.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const HEADER_SLUG_PREFIX = 'amorg-header-';

	/**
	 * Priority for the add_menu_classes filter.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const PRIORITY = 9999;

	/**
	 * Reader over the live menu.
	 *
	 * @since 1.0.0
	 * @var Menu_Reader
	 */
	private $reader;

	/**
	 * Resolved layout.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $layout;

	/**
	 * Group IDs the current user has collapsed.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private $collapsed;

	/**
	 * Groups actually emitted, in order, with their rendering metadata.
	 *
	 * @since 1.0.0
	 * @var array<int, array>|null
	 */
	private $groups = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Menu_Reader $reader    Reader over the live menu.
	 * @param array       $layout    Resolved, sanitised layout.
	 * @param string[]    $collapsed Group IDs the user has collapsed.
	 */
	public function __construct( Menu_Reader $reader, array $layout, array $collapsed = array() ) {
		$this->reader    = $reader;
		$this->layout    = $layout;
		$this->collapsed = $collapsed;
	}

	/**
	 * Registers the rendering hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'add_menu_classes', array( $this, 'decorate' ), self::PRIORITY );
		add_filter( 'admin_body_class', array( $this, 'body_classes' ) );
		add_action( 'admin_head', array( $this, 'print_inline_styles' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the sidebar stylesheet.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue(): void {
		wp_enqueue_style(
			'amorg-admin-menu',
			AMORG_PLUGIN_URL . 'assets/css/admin-menu.css',
			array( 'dashicons' ),
			Plugin::asset_version( 'assets/css/admin-menu.css' )
		);

		wp_enqueue_script(
			'amorg-admin-menu',
			AMORG_PLUGIN_URL . 'assets/js/admin-menu.js',
			array( 'wp-i18n' ),
			Plugin::asset_version( 'assets/js/admin-menu.js' ),
			true
		);

		wp_set_script_translations(
			'amorg-admin-menu',
			'admin-menu-organizer',
			AMORG_PLUGIN_DIR . 'languages'
		);

		$data = $this->script_data();

		$data['restUrl'] = rest_url( Rest_Controller::NAMESPACE_V1 . '/state' );
		$data['nonce']   = wp_create_nonce( 'wp_rest' );

		wp_add_inline_script(
			'amorg-admin-menu',
			'window.amorgMenu = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Prints the stylesheet that restores every group when scripting is off.
	 *
	 * Collapsed state is applied server-side, which is what makes the accordion
	 * flash-free. The cost is that a browser with JavaScript disabled would be
	 * left with closed groups and no control to open them, which would make menu
	 * items unreachable and break SPEC section 15 outright.
	 *
	 * A noscript block resolves it exactly: the rule applies only when scripting
	 * is unavailable, so the flash-free path is untouched for everyone else. With
	 * scripting off the sidebar degrades to a grouped, fully expanded, fully
	 * usable menu whose headers are still labelled, because the label is drawn by
	 * a pseudo-element rather than by script.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function print_noscript_styles(): void {
		echo '<noscript><style id="amorg-noscript">'
			. '#adminmenu li.amorg-collapsed-member{display:block}'
			. '#adminmenu .amorg-group-toggle{display:none}'
			. '</style></noscript>' . "\n";
	}

	/**
	 * Adds group classes to every item and splices in the header rows.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $menu The menu array, as core hands it over.
	 * @return array Always an array, since core assigns this straight to $menu.
	 */
	public function decorate( $menu ): array {
		if ( ! is_array( $menu ) || empty( $menu ) ) {
			return is_array( $menu ) ? $menu : array();
		}

		$group_of  = $this->group_of_slug();
		$collapsed = $this->collapsed_group_ids();
		$emitted   = array();
		$seen      = array();
		$last_of   = array();

		foreach ( $menu as $key => $item ) {
			if ( ! is_array( $item ) || ! isset( $item[2] ) || ! is_scalar( $item[2] ) ) {
				$emitted[ $key ] = $item;
				continue;
			}

			$slug = (string) $item[2];

			$group_id = $group_of[ $slug ] ?? null;

			if ( null !== $group_id && ! isset( $seen[ $group_id ] ) ) {
				// First member of this group, so the header belongs immediately
				// before it. Using a string key keeps it distinct from core's own
				// numeric position keys.
				$seen[ $group_id ] = true;

				$emitted[ self::HEADER_SLUG_PREFIX . $group_id ] = $this->header_row( $group_id );
			}

			if ( null !== $group_id ) {
				$classes = 'amorg-item amorg-group-' . $group_id;

				/*
				 * Whether a row is hidden is decided here, on the server, rather
				 * than by a CSS selector pairing a body class with an item class.
				 * Group IDs are user-creatable, so such selectors would have to be
				 * generated per request, which would make the stylesheet
				 * uncacheable. Marking the row directly keeps admin-menu.css static
				 * and still paints the correct state on first frame.
				 */
				if ( isset( $collapsed[ $group_id ] ) ) {
					$classes .= ' amorg-collapsed-member';
				}

				$item[4] = self::add_class(
					isset( $item[4] ) && is_scalar( $item[4] ) ? (string) $item[4] : '',
					$classes
				);

				// Remember the most recent member of this group. Because the
				// reorder guarantees members are contiguous, whatever this holds
				// once the loop ends is genuinely the group's last row.
				$last_of[ $group_id ] = $key;
			}

			$emitted[ $key ] = $item;
		}

		/*
		 * Mark each group's final row so the stylesheet can stop the connecting
		 * rule halfway down it, closing the branch instead of running the line
		 * into the next group's header.
		 */
		foreach ( $last_of as $key ) {
			if ( ! isset( $emitted[ $key ] ) || ! is_array( $emitted[ $key ] ) ) {
				continue;
			}

			$emitted[ $key ][4] = self::add_class(
				isset( $emitted[ $key ][4] ) && is_scalar( $emitted[ $key ][4] ) ? (string) $emitted[ $key ][4] : '',
				'amorg-group-last'
			);
		}

		return $emitted;
	}

	/**
	 * Maps every slug the layout places to its group ID.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function group_of_slug(): array {
		$map = array();

		foreach ( $this->groups() as $group ) {
			if ( $group['count_members'] < 1 ) {
				continue;
			}

			foreach ( $group['items'] as $slug ) {
				if ( ! isset( $map[ $slug ] ) ) {
					$map[ $slug ] = $group['id'];
				}
			}
		}

		return $map;
	}

	/**
	 * Returns a lookup of the group IDs whose rows should start hidden.
	 *
	 * The group containing the current page is never included, per SPEC section
	 * 6.3, and neither is the catch-all, per section 5.1.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, true>
	 */
	private function collapsed_group_ids(): array {
		$active = $this->active_group();
		$hidden = array();

		foreach ( $this->groups() as $group ) {
			if ( $group['open'] || $group['id'] === $active || Categories::UNGROUPED === $group['id'] ) {
				continue;
			}

			$hidden[ $group['id'] ] = true;
		}

		return $hidden;
	}

	/**
	 * Builds one header row.
	 *
	 * Index 0 is left empty on purpose. Core never renders it for this item,
	 * because the capability check fails, and leaving it empty means a group label
	 * can never reach the one field core emits unescaped. The label travels
	 * through CSS custom properties instead, where it is escaped for a CSS string
	 * context. See print_inline_styles().
	 *
	 * @since 1.0.0
	 *
	 * @param string $group_id Group the header belongs to.
	 * @return array A $menu entry.
	 */
	private function header_row( string $group_id ): array {
		$classes = 'amorg-group-header amorg-header-' . $group_id;

		if ( Categories::UNGROUPED === $group_id ) {
			$classes .= ' amorg-header-permanent';
		}

		return array(
			'',                                          // 0: menu_title, never rendered.
			self::HEADER_CAP,                            // 1: capability, deliberately unsatisfiable.
			self::HEADER_SLUG_PREFIX . $group_id,        // 2: unique slug.
			'',                                          // 3: page_title, unused by the renderer.
			$classes,                                    // 4: classes, escaped by core.
			'amorg-group-' . $group_id,                  // 5: hookname, becomes the li id.
			'',                                          // 6: icon_url.
		);
	}

	/**
	 * Emits per-group state as body classes.
	 *
	 * Doing this server-side is what makes the accordion flash-free: the browser
	 * already knows which groups are closed before it paints, so nothing expands
	 * and then snaps shut.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $classes Existing body classes.
	 * @return string
	 */
	public function body_classes( $classes ): string {
		$classes = is_string( $classes ) ? $classes : '';

		$added = array( 'amorg-active' );

		$active = $this->active_group();

		foreach ( $this->groups() as $group ) {
			$group_id = $group['id'];

			if ( $group['count_members'] < 1 ) {
				// An empty group has no header and no rows, so it is neither open
				// nor closed. SPEC section 6.1 requires it hidden entirely.
				$added[] = 'amorg-empty-' . $group_id;
				continue;
			}

			/*
			 * SPEC section 6.3: the group holding the current page is always
			 * expanded, whatever the saved state says. Otherwise a user who
			 * collapsed a group and then navigated into it via a bookmark would
			 * see no indication of where they are.
			 */
			if ( $group_id === $active ) {
				$added[] = 'amorg-current-' . $group_id;
				continue;
			}

			if ( ! $group['open'] ) {
				$added[] = 'amorg-collapsed-' . $group_id;
			}
		}

		return trim( $classes . ' ' . implode( ' ', $added ) );
	}

	/**
	 * Prints the inline CSS that carries each group's label, icon and badge.
	 *
	 * Labels are user input and must not reach the one field core emits raw, so
	 * they travel as CSS custom properties on a per-group selector. That has a
	 * second benefit: the label is visible with no JavaScript at all, because a
	 * pseudo-element renders it, which is what keeps a JS-less sidebar usable.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function print_inline_styles(): void {
		$groups = $this->groups();

		if ( empty( $groups ) ) {
			return;
		}

		$rules = array();

		/*
		 * The member indent and its connecting rule are printed inline, not left
		 * to admin-menu.css alone.
		 *
		 * This looks like duplication and is deliberate. A site reported that
		 * after updating, the Plugins screen showed the new version while the
		 * sidebar still rendered without the indent. The cause was a stale
		 * OPcache: the old compiled PHP was still running, so the version
		 * constant evaluated to the previous release, the stylesheet was
		 * requested at its old URL, and the browser served a cached copy that
		 * predated these rules. Nothing in a plugin can flush someone's OPcache.
		 *
		 * What a plugin can do is stop depending on a cacheable file for the part
		 * that matters. This block is emitted fresh on every admin request, so
		 * the hierarchy is correct even when the external stylesheet is stale,
		 * and identical declarations in the two places are harmless.
		 */
		$rules[] = '#adminmenu li.amorg-item>a{padding-inline-start:var(--amorg-indent,14px)}';
		$rules[] = '#adminmenu li.amorg-item{position:relative}';
		$rules[] = '#adminmenu li.amorg-item::before{content:"";position:absolute;'
			. 'inset-block:0;inset-inline-start:var(--amorg-rule-inset,7px);'
			. 'width:1px;background:currentColor;opacity:.16;pointer-events:none}';
		$rules[] = '#adminmenu li.amorg-group-last::before{inset-block-end:50%}';
		$rules[] = '.folded #adminmenu li.amorg-item>a{padding-inline-start:0}';
		$rules[] = '.folded #adminmenu li.amorg-item::before{display:none}';

		// Long labels wrap instead of being clipped. See admin-menu.css for why.
		$rules[] = '#adminmenu .amorg-toggle-label{min-width:0;white-space:normal;'
			. 'overflow-wrap:anywhere;word-break:break-word}';

		foreach ( $groups as $group ) {
			if ( $group['count_members'] < 1 ) {
				continue;
			}

			$selector = '#adminmenu .amorg-header-' . $group['id'];

			$declarations = array(
				'--amorg-label: "' . self::css_string( $group['label'] ) . '"',
				'--amorg-icon: "' . self::dashicon_glyph( $group['icon'] ) . '"',
			);

			if ( $group['count_updates'] > 0 ) {
				$declarations[] = '--amorg-badge: "' . self::css_string( (string) $group['count_updates'] ) . '"';
			}

			$rules[] = $selector . '{' . implode( ';', $declarations ) . '}';
		}

		$this->print_noscript_styles();

		if ( empty( $rules ) ) {
			return;
		}

		/*
		 * The style element is printed rather than enqueued because it must land
		 * in the document head before the sidebar paints; an enqueued stylesheet
		 * would be fine too, but this content is per-request and unique per user,
		 * so it must never be cached as a static file.
		 *
		 * Every interpolated value has been through css_string(), which escapes to
		 * a CSS string context. wp_strip_all_tags is applied as a second, redundant
		 * guard on the whole block.
		 */
		printf(
			"<style id='amorg-inline'>%s</style>\n",
			wp_strip_all_tags( implode( "\n", $rules ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS block, not HTML; values individually escaped by css_string(), see docblock.
		);
	}

	/**
	 * Returns the groups that will be rendered, with their metadata.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{id: string, label: string, icon: string, open: bool, count_members: int, count_updates: int}>
	 */
	public function groups(): array {
		if ( null !== $this->groups ) {
			return $this->groups;
		}

		$collapsed = array_flip( $this->collapsed );
		$groups    = array();

		$raw_groups = isset( $this->layout['groups'] ) && is_array( $this->layout['groups'] )
			? $this->layout['groups']
			: array();

		foreach ( $raw_groups as $group ) {
			if ( ! is_array( $group ) || empty( $group['id'] ) || ! is_string( $group['id'] ) ) {
				continue;
			}

			$group_id   = $group['id'];
			$definition = Categories::get( $group_id );
			$items      = $this->reader->filter_known( (array) ( $group['items'] ?? array() ) );

			$default_open = array_key_exists( 'default_open', $group )
				? (bool) $group['default_open']
				: ! empty( $definition['default_open'] );

			// The catch-all is never collapsible, per SPEC section 5.1.
			$open = Categories::UNGROUPED === $group_id
				? true
				: ( isset( $collapsed[ $group_id ] ) ? false : $default_open );

			$label = '';

			if ( isset( $group['label'] ) && is_string( $group['label'] ) && '' !== $group['label'] ) {
				$label = $group['label'];
			} elseif ( null !== $definition ) {
				$label = $definition['label'];
			} else {
				$label = $group_id;
			}

			if ( Categories::UNGROUPED === $group_id
				&& isset( $this->layout['ungrouped_label'] )
				&& is_string( $this->layout['ungrouped_label'] )
				&& '' !== $this->layout['ungrouped_label']
			) {
				$label = $this->layout['ungrouped_label'];
			}

			$icon = '';

			if ( isset( $group['icon'] ) && is_string( $group['icon'] ) && '' !== $group['icon'] ) {
				$icon = $group['icon'];
			} elseif ( null !== $definition ) {
				$icon = $definition['icon'];
			}

			$groups[] = array(
				'id'            => $group_id,
				'label'         => $label,
				'icon'          => $icon,
				'open'          => $open,
				'count_members' => count( $items ),
				'count_updates' => $this->reader->update_count_for( $items ),
				'items'         => $items,
			);
		}

		$this->groups = $groups;

		return $this->groups;
	}

	/**
	 * Returns the group containing the page currently being viewed.
	 *
	 * Determined from the request rather than from core's $parent_file, because
	 * $parent_file is not populated until menu-header.php runs, which is after
	 * admin_body_class has already been filtered.
	 *
	 * @since 1.0.0
	 *
	 * @return string Group ID, or an empty string when it cannot be determined.
	 */
	public function active_group(): string {
		$slug = self::current_slug();

		if ( '' === $slug ) {
			return '';
		}

		foreach ( $this->groups() as $group ) {
			if ( in_array( $slug, $group['items'], true ) ) {
				return $group['id'];
			}
		}

		return '';
	}

	/**
	 * Works out which menu slug the current request corresponds to.
	 *
	 * Mirrors how core builds a top-level slug: a plugin page is identified by its
	 * page query argument, a post type listing by pagenow plus post_type, and
	 * anything else by pagenow alone.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function current_slug(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only inspection of the current screen, drives no write.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( '' !== $page ) {
			return $page;
		}

		$pagenow = isset( $GLOBALS['pagenow'] ) && is_string( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';

		if ( '' === $pagenow ) {
			return '';
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' !== $post_type && in_array( $pagenow, array( 'edit.php', 'post-new.php', 'edit-tags.php' ), true ) ) {
			return 'edit.php?post_type=' . $post_type;
		}

		return $pagenow;
	}

	/**
	 * Appends a class to a class string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $existing Existing class string.
	 * @param string $addition Classes to append.
	 * @return string
	 */
	public static function add_class( string $existing, string $addition ): string {
		$existing = trim( $existing );

		return '' === $existing ? $addition : $existing . ' ' . $addition;
	}

	/**
	 * Escapes a value for use inside a double-quoted CSS string.
	 *
	 * Backslashes and double quotes are escaped, since those are the only
	 * characters that can terminate or extend a CSS string. Angle brackets are
	 * removed as a second line of defence, so that no combination of stored data
	 * could close the style element early, even though Layout_Sanitizer already
	 * strips them.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Plain-text value.
	 * @return string
	 */
	public static function css_string( string $value ): string {
		$value = str_replace( array( '<', '>' ), '', $value );
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( '"', '\\"', $value );

		// Newlines are invalid inside a CSS string, and a stray one would break
		// the whole declaration block rather than just this value.
		return (string) preg_replace( '/[\r\n]+/', ' ', $value );
	}

	/**
	 * Maps a Dashicon class to the character it renders.
	 *
	 * The header's icon is drawn by a pseudo-element, which needs the glyph rather
	 * than the class. Falling back to the generic menu glyph keeps a header from
	 * rendering an empty box when a layout names an icon this map does not know.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dashicon Dashicon class name.
	 * @return string A CSS escape sequence for the glyph.
	 */
	public static function dashicon_glyph( string $dashicon ): string {
		$map = array(
			'dashicons-dashboard'        => 'f226',
			'dashicons-admin-post'       => 'f109',
			'dashicons-admin-page'       => 'f105',
			'dashicons-admin-media'      => 'f104',
			'dashicons-admin-comments'   => 'f101',
			'dashicons-admin-appearance' => 'f100',
			'dashicons-admin-plugins'    => 'f106',
			'dashicons-admin-users'      => 'f110',
			'dashicons-admin-tools'      => 'f107',
			'dashicons-admin-settings'   => 'f108',
			'dashicons-admin-links'      => 'f103',
			'dashicons-cart'             => 'f174',
			'dashicons-products'         => 'f312',
			'dashicons-store'            => 'f513',
			'dashicons-chart-line'       => 'f238',
			'dashicons-chart-bar'        => 'f185',
			'dashicons-chart-area'       => 'f239',
			'dashicons-chart-pie'        => 'f184',
			'dashicons-megaphone'        => 'f488',
			'dashicons-shield'           => 'f332',
			'dashicons-shield-alt'       => 'f334',
			'dashicons-lock'             => 'f160',
			'dashicons-backup'           => 'f321',
			'dashicons-performance'      => 'f311',
			'dashicons-groups'           => 'f307',
			'dashicons-businessman'      => 'f338',
			'dashicons-translation'      => 'f326',
			'dashicons-location'         => 'f230',
			'dashicons-email'            => 'f465',
			'dashicons-feedback'         => 'f175',
			'dashicons-format-chat'      => 'f125',
			'dashicons-book'             => 'f330',
			'dashicons-calendar'         => 'f145',
			'dashicons-portfolio'        => 'f322',
			'dashicons-images-alt2'      => 'f234',
			'dashicons-database'         => 'f17e',
			'dashicons-editor-code'      => 'f475',
			'dashicons-hammer'           => 'f308',
			'dashicons-menu-alt'         => 'f228',
			'dashicons-screenoptions'    => 'f540',
			'dashicons-generic'          => 'f18e',
		);

		$code = $map[ $dashicon ] ?? $map['dashicons-menu-alt'];

		return '\\' . $code;
	}

	/**
	 * Builds a payload describing the rendered groups, for the front-end script.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function script_data(): array {
		$groups = array();

		foreach ( $this->groups() as $group ) {
			if ( $group['count_members'] < 1 ) {
				continue;
			}

			$groups[] = array(
				'id'        => $group['id'],
				'label'     => $group['label'],
				'icon'      => $group['icon'],
				'open'      => $group['open'],
				'members'   => $group['count_members'],
				'updates'   => $group['count_updates'],
				'permanent' => Categories::UNGROUPED === $group['id'],
			);
		}

		return array(
			'groups'  => $groups,
			'current' => $this->active_group(),
		);
	}
}
