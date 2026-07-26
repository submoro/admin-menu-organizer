<?php
/**
 * Safe reading of the WordPress admin menu globals.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 */

namespace MOCAM;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes $GLOBALS['menu'] and $GLOBALS['submenu'] into a shape the rest of
 * the plugin can rely on.
 *
 * Every other class reads the menu through this one, and never touches the
 * globals directly. Two reasons. First, third-party plugins register menu items
 * carelessly: short arrays, missing indices, integers where strings belong,
 * duplicate slugs. Reading defensively in one place beats scattering isset()
 * checks everywhere. Second, this class is a pure function of its constructor
 * arguments, so the whole read layer is unit-testable without WordPress.
 *
 * Index positions are taken from wp-admin/menu-header.php line 77:
 * 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes,
 * 5 = hookname, 6 = icon_url. See docs/core-notes.md section 1.
 *
 * @since 1.0.0
 */
final class Menu_Reader {

	/**
	 * Raw top-level menu array, as WordPress built it.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $menu;

	/**
	 * Raw submenu array, keyed by parent slug.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $submenu;

	/**
	 * Memoised normalized items, keyed by slug.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $items = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $menu    Top-level menu array in WordPress's own format.
	 * @param array $submenu Submenu array keyed by parent slug.
	 */
	public function __construct( array $menu = array(), array $submenu = array() ) {
		$this->menu    = $menu;
		$this->submenu = $submenu;
	}

	/**
	 * Builds a reader from the live WordPress globals.
	 *
	 * @since 1.0.0
	 *
	 * @return Menu_Reader
	 */
	public static function from_globals(): Menu_Reader {
		$menu    = isset( $GLOBALS['menu'] ) && is_array( $GLOBALS['menu'] ) ? $GLOBALS['menu'] : array();
		$submenu = isset( $GLOBALS['submenu'] ) && is_array( $GLOBALS['submenu'] ) ? $GLOBALS['submenu'] : array();

		return new self( $menu, $submenu );
	}

	/**
	 * Whether the menu is worth acting on.
	 *
	 * SPEC section 3.5 requires bailing silently on an empty or malformed menu.
	 * A menu with fewer than two organizable items cannot meaningfully be
	 * grouped, so treat that as not usable either.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_usable(): bool {
		return count( $this->organizable_slugs() ) >= 2;
	}

	/**
	 * Returns every normalized menu item, keyed by slug, in menu order.
	 *
	 * Items that cannot be normalized are dropped from this view, but they are
	 * never removed from the menu itself; see slugs() for the distinction.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array> Normalized items keyed by slug.
	 */
	public function items(): array {
		if ( null !== $this->items ) {
			return $this->items;
		}

		$items = array();

		foreach ( $this->menu as $position => $raw ) {
			$item = self::normalize( $raw, $position );

			if ( null === $item ) {
				continue;
			}

			/*
			 * A duplicate slug is a bug in whichever plugin registered it, but
			 * it happens. Keep the first occurrence: that is the one core's
			 * array_flip() in the menu_order pipeline will honour, so matching
			 * that behaviour keeps our ordering consistent with core's.
			 */
			if ( isset( $items[ $item['slug'] ] ) ) {
				continue;
			}

			$items[ $item['slug'] ] = $item;
		}

		$this->items = $items;

		return $this->items;
	}

	/**
	 * Returns every slug present in the menu, in menu order, separators included.
	 *
	 * This is the authoritative list for the menu_order filter. It must contain
	 * every slug WordPress knows about, because omitting one silently drops it
	 * to the bottom of the sidebar. See docs/core-notes.md section 4.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function slugs(): array {
		return array_keys( $this->items() );
	}

	/**
	 * Returns the slugs of items that may be placed into a group.
	 *
	 * Separators are excluded: they are core's own visual spacers, they carry no
	 * label, and core deletes adjacent ones. Grouping them would be meaningless.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function organizable_slugs(): array {
		$slugs = array();

		foreach ( $this->items() as $slug => $item ) {
			if ( ! $item['is_separator'] ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}

	/**
	 * Whether a slug exists in the live menu.
	 *
	 * SPEC section 8 requires that a stored slug is validated against the live
	 * menu before it is trusted. This is that check.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Menu slug to look for.
	 * @return bool
	 */
	public function has( string $slug ): bool {
		return isset( $this->items()[ $slug ] );
	}

	/**
	 * Returns a single normalized item, or null when the slug is unknown.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Menu slug to fetch.
	 * @return array|null
	 */
	public function get( string $slug ): ?array {
		return $this->items()[ $slug ] ?? null;
	}

	/**
	 * Filters a list of slugs down to those that exist and may be grouped.
	 *
	 * Used wherever stored data meets the live menu, so that a layout saved when
	 * a plugin was active does not resurrect its menu item after deactivation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $slugs Candidate slugs, from stored data or elsewhere.
	 * @return string[] Those that are present and organizable, order preserved.
	 */
	public function filter_known( array $slugs ): array {
		$organizable = array_flip( $this->organizable_slugs() );
		$known       = array();

		foreach ( $slugs as $slug ) {
			if ( ! is_string( $slug ) || '' === $slug ) {
				continue;
			}

			if ( isset( $organizable[ $slug ] ) && ! in_array( $slug, $known, true ) ) {
				$known[] = $slug;
			}
		}

		return $known;
	}

	/**
	 * Returns the total number of pending updates advertised across the menu.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $slugs Slugs to total up.
	 * @return int
	 */
	public function update_count_for( array $slugs ): int {
		$total = 0;

		foreach ( $slugs as $slug ) {
			$item = $this->get( (string) $slug );

			if ( null !== $item ) {
				$total += $item['update_count'];
			}
		}

		return $total;
	}

	/**
	 * Normalizes one raw menu item.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed      $raw      Raw entry from the menu array. Expected to be an array.
	 * @param int|string $position The entry's key in the menu array, which is its position.
	 * @return array|null Normalized item, or null when the entry is unusable.
	 */
	private static function normalize( $raw, $position ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		// Index 2 is the slug, and it is the item's identity. Without it there
		// is nothing to key on and nothing to reorder.
		if ( ! isset( $raw[2] ) || ! is_scalar( $raw[2] ) ) {
			return null;
		}

		$slug = (string) $raw[2];

		if ( '' === $slug ) {
			return null;
		}

		$title      = isset( $raw[0] ) && is_scalar( $raw[0] ) ? (string) $raw[0] : '';
		$capability = isset( $raw[1] ) && is_scalar( $raw[1] ) ? (string) $raw[1] : '';
		$classes    = isset( $raw[4] ) && is_scalar( $raw[4] ) ? (string) $raw[4] : '';
		$hookname   = isset( $raw[5] ) && is_scalar( $raw[5] ) ? (string) $raw[5] : '';
		$icon       = isset( $raw[6] ) && is_scalar( $raw[6] ) ? (string) $raw[6] : '';

		return array(
			'slug'         => $slug,
			'title'        => $title,
			'plain_title'  => self::plain_title( $title ),
			'capability'   => $capability,
			'classes'      => $classes,
			'hookname'     => $hookname,
			'icon'         => $icon,
			'position'     => $position,
			'is_separator' => self::is_separator( $slug, $classes ),
			'update_count' => self::update_count( $title ),
		);
	}

	/**
	 * Whether an item is one of core's visual separators.
	 *
	 * Mirrors both tests core itself applies: the substring test on the class
	 * string in menu-header.php line 120, and the slug prefix test in
	 * includes/menu.php line 246.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug    Menu slug.
	 * @param string $classes Class string from index 4.
	 * @return bool
	 */
	public static function is_separator( string $slug, string $classes ): bool {
		if ( false !== stripos( $classes, 'wp-menu-separator' ) ) {
			return true;
		}

		return 0 === strpos( $slug, 'separator' );
	}

	/**
	 * Extracts the pending-update count advertised in a menu title.
	 *
	 * Core embeds counts as markup inside the title, for example
	 * <span class="update-plugins count-3">. The title is emitted raw, which is
	 * how those bubbles render at all; see docs/core-notes.md section 3.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title Raw menu title, possibly containing count markup.
	 * @return int Total advertised across every bubble in the title.
	 */
	public static function update_count( string $title ): int {
		if ( false === strpos( $title, 'count-' ) ) {
			return 0;
		}

		if ( ! preg_match_all( '/\bcount-(\d+)\b/', $title, $matches ) ) {
			return 0;
		}

		$total = 0;

		foreach ( $matches[1] as $count ) {
			$total += (int) $count;
		}

		return $total;
	}

	/**
	 * Strips markup from a menu title, leaving readable text.
	 *
	 * Used for keyword matching in the detector and for accessible labels. The
	 * result is plain text and still needs escaping at the point of output.
	 *
	 * Deliberately implemented without wp_strip_all_tags(), so that this class
	 * stays a pure function of its inputs and the whole read layer remains
	 * unit-testable without loading WordPress. The behaviour is the same:
	 * script and style bodies are removed rather than merely untagged, which
	 * matters because a menu title containing a script block would otherwise
	 * leak its source into the plain text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title Raw menu title.
	 * @return string
	 */
	public static function plain_title( string $title ): string {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $title );

		/*
		 * Drop update-count bubbles wholesale rather than merely untagging them.
		 * Simply stripping tags would leave the digit behind, turning "Plugins"
		 * into "Plugins 7" and feeding a meaningless number into the detector's
		 * keyword matching and into accessible labels. The count is still
		 * available separately through update_count().
		 */
		$text = preg_replace(
			'@<span[^>]*\bclass=(["\'])[^"\']*\bcount-\d+\b[^"\']*\1[^>]*>.*?</span>@si',
			'',
			(string) $text
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- wp_strip_all_tags() would couple this pure class to WordPress; script and style bodies are already removed above, which is the only behavioural difference.
		$text = strip_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}
}
