<?php
/**
 * Automatic category detection for admin menu items.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

namespace AMORG;

defined( 'ABSPATH' ) || exit;

/**
 * Decides which category a top-level menu item belongs in.
 *
 * An exact slug table can never cover the whole plugin directory, so this works
 * as a cascade of increasingly general signals. Each layer is tried in turn and
 * the first to produce an answer wins:
 *
 *   1. Exact menu slug, case-sensitive, then case-insensitive.
 *   2. Registered post type, read out of an edit.php?post_type=X slug.
 *   3. A distinctive brand token appearing anywhere in the slug.
 *   4. A shorter vendor prefix at the start of the slug, boundary-checked.
 *   5. The capability guarding the item, which vendors namespace.
 *   6. Weighted keywords scored against the title and slug, above a threshold.
 *   7. The Dashicon the vendor chose, as a last weak hint.
 *   8. Fall back to ungrouped, which is always visible.
 *
 * Layers 1 to 5 are treated as decisive because a namespaced capability or a
 * vendor prefix is evidence rather than a guess. Only layer 6 is scored, and it
 * declines to answer unless one category clearly leads, so an ambiguous item
 * lands in the always-visible Other group rather than somewhere wrong.
 *
 * This class is a pure function of its constructor arguments. It calls no
 * WordPress function, so it is fully unit-testable. Use create() to build one
 * with the bundled data and the extension filters applied.
 *
 * @since 1.0.0
 */
final class Detector {

	/**
	 * The group every unmatched item falls into.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const FALLBACK = 'ungrouped';

	/**
	 * Minimum keyword score before the scoring layer will commit to an answer.
	 *
	 * Expressed on the doubled scale produced by score(), where a title match
	 * counts twice and a slug match once. Eight is therefore "one weight-4
	 * keyword in the title", which is the weakest evidence worth acting on.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MIN_SCORE = 8;

	/**
	 * Multiplier applied to keywords found in the menu title.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TITLE_WEIGHT = 2;

	/**
	 * Multiplier applied to keywords found in the menu slug.
	 *
	 * Slugs are noisier than titles. "custom-login-page" contributes the word
	 * "page", which is a real content keyword but says nothing about a login
	 * customiser. Halving the slug's contribution relative to the title stops
	 * that incidental noise outvoting the actual subject.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const SLUG_WEIGHT = 1;

	/**
	 * Exact menu slug to group ID.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	private $known_slugs;

	/**
	 * Lowercased copy of the slug table, for the case-insensitive pass.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	private $known_slugs_lower;

	/**
	 * Heuristic signal tables, as returned by data/keyword-map.php.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $heuristics;

	/**
	 * Group IDs that may be returned, as a lookup.
	 *
	 * @since 1.0.0
	 * @var array<string, true>
	 */
	private $valid_groups;

	/**
	 * Optional callback applied to every decision.
	 *
	 * Injected rather than calling apply_filters() directly, so this class stays
	 * free of WordPress. create() supplies a callback that runs the
	 * amorg_resolve_group filter.
	 *
	 * @since 1.0.0
	 * @var callable|null
	 */
	private $filter;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array         $known_slugs  Exact menu slug to group ID.
	 * @param array         $heuristics   Signal tables, as per data/keyword-map.php.
	 * @param string[]      $valid_groups Group IDs that may be returned.
	 * @param callable|null $filter       Optional callback( string $group, array $item ): string.
	 */
	public function __construct(
		array $known_slugs,
		array $heuristics,
		array $valid_groups,
		?callable $filter = null
	) {
		$this->known_slugs = $known_slugs;
		$this->heuristics  = $heuristics;
		$this->filter      = $filter;

		$this->known_slugs_lower = array();
		foreach ( $known_slugs as $slug => $group ) {
			$this->known_slugs_lower[ strtolower( (string) $slug ) ] = $group;
		}

		$this->valid_groups = array();
		foreach ( $valid_groups as $group_id ) {
			$this->valid_groups[ (string) $group_id ] = true;
		}

		// The fallback must always be available, or an unmatched item would have
		// nowhere to go.
		$this->valid_groups[ self::FALLBACK ] = true;
	}

	/**
	 * Builds a detector from the bundled data, with extension filters applied.
	 *
	 * @since 1.0.0
	 *
	 * @return Detector
	 */
	public static function create(): Detector {
		/**
		 * Filters the exact menu slug to category map.
		 *
		 * @since 1.0.0
		 *
		 * @param array $known_slugs Map of menu slug to group ID.
		 */
		$known_slugs = apply_filters( 'amorg_known_slugs', require AMORG_PLUGIN_DIR . 'includes/data/known-slugs.php' );

		/**
		 * Filters the heuristic signal tables.
		 *
		 * @since 1.0.0
		 *
		 * @param array $heuristics Signal tables, as per includes/data/keyword-map.php.
		 */
		$heuristics = apply_filters( 'amorg_keyword_map', require AMORG_PLUGIN_DIR . 'includes/data/keyword-map.php' );

		$filter = static function ( string $group, array $item ): string {
			/**
			 * Filters the group resolved for a single menu item.
			 *
			 * @since 1.0.0
			 *
			 * @param string $group The resolved group ID.
			 * @param array  $item  Normalized menu item from Menu_Reader.
			 */
			return (string) apply_filters( 'amorg_resolve_group', $group, $item );
		};

		return new self(
			is_array( $known_slugs ) ? $known_slugs : array(),
			is_array( $heuristics ) ? $heuristics : array(),
			Categories::ids(),
			$filter
		);
	}

	/**
	 * Detects the group for one normalized menu item.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Normalized item from Menu_Reader::items().
	 * @return string A valid group ID. Never empty.
	 */
	public function detect( array $item ): string {
		return $this->explain( $item )['group'];
	}

	/**
	 * Detects the group for every organizable item in a menu.
	 *
	 * @since 1.0.0
	 *
	 * @param Menu_Reader $reader Reader over the live or fixture menu.
	 * @return array<string, string> Map of menu slug to group ID.
	 */
	public function detect_all( Menu_Reader $reader ): array {
		$detected = array();

		foreach ( $reader->organizable_slugs() as $slug ) {
			$item = $reader->get( $slug );

			if ( null !== $item ) {
				$detected[ $slug ] = $this->detect( $item );
			}
		}

		return $detected;
	}

	/**
	 * Detects the group and reports which layer decided, and on what evidence.
	 *
	 * Surfaced in the Advanced tab's diagnostics panel, so that an administrator
	 * puzzled by a placement can see why it happened rather than guessing.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Normalized item from Menu_Reader::items().
	 * @return array{group: string, layer: string, evidence: string, scores: array}
	 */
	public function explain( array $item ): array {
		$slug       = isset( $item['slug'] ) ? (string) $item['slug'] : '';
		$title      = isset( $item['plain_title'] ) ? (string) $item['plain_title'] : '';
		$capability = isset( $item['capability'] ) ? (string) $item['capability'] : '';
		$icon       = isset( $item['icon'] ) ? (string) $item['icon'] : '';

		$result = $this->resolve( $slug, $title, $capability, $icon );

		// The filter may override any decision, including the fallback, but it
		// cannot invent a group that does not exist.
		if ( null !== $this->filter ) {
			$filtered = ( $this->filter )( $result['group'], $item );

			if ( $filtered !== $result['group'] && $this->is_valid( $filtered ) ) {
				$result['group']    = $filtered;
				$result['layer']    = 'filter';
				$result['evidence'] = 'amorg_resolve_group';
			}
		}

		return $result;
	}

	/**
	 * Runs the detection cascade.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug       Menu slug.
	 * @param string $title      Plain-text menu title.
	 * @param string $capability Capability from index 1.
	 * @param string $icon       Icon from index 6.
	 * @return array{group: string, layer: string, evidence: string, scores: array}
	 */
	private function resolve( string $slug, string $title, string $capability, string $icon ): array {
		if ( '' === $slug ) {
			return $this->outcome( self::FALLBACK, 'fallback', 'empty slug' );
		}

		// 1. Exact slug, case-sensitive then case-insensitive. Some plugins ship
		// a capitalised slug, Wordfence being the well-known example.
		if ( isset( $this->known_slugs[ $slug ] ) && $this->is_valid( $this->known_slugs[ $slug ] ) ) {
			return $this->outcome( $this->known_slugs[ $slug ], 'exact-slug', $slug );
		}

		$lower = strtolower( $slug );

		if ( isset( $this->known_slugs_lower[ $lower ] ) && $this->is_valid( $this->known_slugs_lower[ $lower ] ) ) {
			return $this->outcome( $this->known_slugs_lower[ $lower ], 'exact-slug-ci', $slug );
		}

		// 2. Registered post type. A custom post type nothing recognises is
		// content by definition, which is a safe and useful default.
		$post_type = self::post_type_from_slug( $slug );

		if ( '' !== $post_type ) {
			$map = $this->table( 'post_types' );

			if ( isset( $map[ $post_type ] ) && $this->is_valid( $map[ $post_type ] ) ) {
				return $this->outcome( $map[ $post_type ], 'post-type', $post_type );
			}

			if ( $this->is_valid( 'content' ) ) {
				return $this->outcome( 'content', 'post-type-default', $post_type );
			}
		}

		// 3. Distinctive brand token anywhere in the slug.
		foreach ( $this->table( 'slug_contains' ) as $token => $group ) {
			if ( '' !== (string) $token && false !== strpos( $lower, strtolower( (string) $token ) ) && $this->is_valid( $group ) ) {
				return $this->outcome( $group, 'slug-contains', (string) $token );
			}
		}

		// 4. Vendor prefix at the start of the slug.
		foreach ( $this->table( 'slug_prefixes' ) as $prefix => $group ) {
			if ( self::has_prefix( $lower, strtolower( (string) $prefix ) ) && $this->is_valid( $group ) ) {
				return $this->outcome( $group, 'slug-prefix', (string) $prefix );
			}
		}

		// 5. Namespaced capability.
		$capabilities = $this->table( 'capabilities' );

		if ( '' !== $capability && isset( $capabilities[ $capability ] ) && $this->is_valid( $capabilities[ $capability ] ) ) {
			return $this->outcome( $capabilities[ $capability ], 'capability', $capability );
		}

		// 6. Weighted keyword scoring.
		$scores = $this->score( $title, $slug );
		$winner = $this->pick_winner( $scores );

		if ( null !== $winner ) {
			return $this->outcome( $winner, 'keywords', $title . ' | ' . $slug, $scores );
		}

		// 7. Dashicon, the weakest signal, tried only once scoring has declined.
		$icons = $this->table( 'icons' );

		if ( '' !== $icon && isset( $icons[ $icon ] ) && $this->is_valid( $icons[ $icon ] ) ) {
			return $this->outcome( $icons[ $icon ], 'icon', $icon, $scores );
		}

		// 8. Always-visible catch-all.
		return $this->outcome( self::FALLBACK, 'fallback', 'no signal', $scores );
	}

	/**
	 * Scores every category against the item's title and slug.
	 *
	 * The title and the slug are scored separately and combined, with the title
	 * weighted higher. See SLUG_WEIGHT for why.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title Plain-text title.
	 * @param string $slug  Menu slug.
	 * @return array<string, int> Group ID to score, descending, zero scores omitted.
	 */
	public function score( string $title, string $slug ): array {
		$title_text = self::normalize_haystack( $title );
		$slug_text  = self::normalize_haystack( self::humanize_slug( $slug ) );

		if ( '' === $title_text && '' === $slug_text ) {
			return array();
		}

		$scores = array();

		foreach ( $this->table( 'keywords' ) as $group => $keywords ) {
			if ( ! is_array( $keywords ) || ! $this->is_valid( (string) $group ) ) {
				continue;
			}

			$total = 0;

			foreach ( $keywords as $keyword => $weight ) {
				$keyword = (string) $keyword;

				if ( '' !== $title_text && self::matches_keyword( $title_text, $keyword ) ) {
					$total += (int) $weight * self::TITLE_WEIGHT;
				}

				if ( '' !== $slug_text && self::matches_keyword( $slug_text, $keyword ) ) {
					$total += (int) $weight * self::SLUG_WEIGHT;
				}
			}

			if ( $total > 0 ) {
				$scores[ (string) $group ] = $total;
			}
		}

		arsort( $scores );

		return $scores;
	}

	/**
	 * Lowercases and collapses whitespace ready for keyword matching.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Any text.
	 * @return string
	 */
	private static function normalize_haystack( string $text ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', strtolower( $text ) ) );
	}

	/**
	 * Picks a winner from a score table, or declines when the result is unclear.
	 *
	 * Declining matters. An item the detector is unsure about belongs in the
	 * always-visible Other group, where the administrator will see it and can
	 * move it, rather than being filed somewhere plausible but wrong where they
	 * will never think to look for it.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, int> $scores Score table from score().
	 * @return string|null Winning group ID, or null when not confident.
	 */
	private function pick_winner( array $scores ): ?string {
		if ( empty( $scores ) ) {
			return null;
		}

		$groups = array_keys( $scores );
		$values = array_values( $scores );

		if ( $values[0] < self::MIN_SCORE ) {
			return null;
		}

		// A tie is not a decision.
		if ( isset( $values[1] ) && $values[0] === $values[1] ) {
			return null;
		}

		return $groups[0];
	}

	/**
	 * Whether a keyword is present in a haystack.
	 *
	 * Multi-word keywords are matched as substrings. Single words are matched at
	 * a leading word boundary but with a free tail, so "cache" also matches
	 * "caches" and "cached", while "tax" does not match "syntax".
	 *
	 * @since 1.0.0
	 *
	 * @param string $haystack Lowercased title and slug.
	 * @param string $keyword  Lowercased keyword or phrase.
	 * @return bool
	 */
	private static function matches_keyword( string $haystack, string $keyword ): bool {
		if ( '' === $keyword ) {
			return false;
		}

		if ( false !== strpos( $keyword, ' ' ) ) {
			return false !== strpos( $haystack, $keyword );
		}

		return 1 === preg_match( '/\b' . preg_quote( $keyword, '/' ) . '/', $haystack );
	}

	/**
	 * Whether a slug starts with a vendor prefix, at a clean boundary.
	 *
	 * A prefix ending in punctuation, such as "wc-", is already delimited and
	 * needs no further check. A prefix ending in a letter or digit, such as
	 * "acf", must not run into the next word, so that it matches
	 * "acf-field-group" but not "acfoobar".
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug   Lowercased menu slug.
	 * @param string $prefix Lowercased prefix.
	 * @return bool
	 */
	private static function has_prefix( string $slug, string $prefix ): bool {
		if ( '' === $prefix || 0 !== strpos( $slug, $prefix ) ) {
			return false;
		}

		$length = strlen( $prefix );

		// Prefix already ends in a delimiter, so the boundary is implicit.
		if ( 1 !== preg_match( '/[a-z0-9]$/', $prefix ) ) {
			return true;
		}

		// Prefix consumed the whole slug.
		if ( strlen( $slug ) === $length ) {
			return true;
		}

		return 1 !== preg_match( '/[a-z0-9]/', $slug[ $length ] );
	}

	/**
	 * Extracts a post type from an edit.php?post_type=X style slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Menu slug.
	 * @return string Post type, or an empty string when the slug is not one.
	 */
	public static function post_type_from_slug( string $slug ): string {
		if ( false === strpos( $slug, 'post_type=' ) ) {
			return '';
		}

		if ( 1 !== preg_match( '/[?&]post_type=([^&]+)/', $slug, $matches ) ) {
			return '';
		}

		return (string) $matches[1];
	}

	/**
	 * Turns a menu slug into words that keyword matching can work with.
	 *
	 * Drops the file extension and any query string keys, so that
	 * "edit.php?post_type=product" contributes "edit product" rather than a wall
	 * of punctuation, and "wp-mail-smtp" contributes "wp mail smtp".
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Menu slug.
	 * @return string
	 */
	public static function humanize_slug( string $slug ): string {
		$text = preg_replace( '/\.(php|html?)\b/i', ' ', $slug );
		$text = str_replace( array( 'post_type=', 'page=', 'path=' ), ' ', (string) $text );
		$text = preg_replace( '/[^A-Za-z0-9]+/', ' ', (string) $text );

		return trim( (string) $text );
	}

	/**
	 * Returns one of the heuristic tables, or an empty array when absent.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Table key.
	 * @return array
	 */
	private function table( string $name ): array {
		return isset( $this->heuristics[ $name ] ) && is_array( $this->heuristics[ $name ] )
			? $this->heuristics[ $name ]
			: array();
	}

	/**
	 * Whether a group ID is one this detector may return.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $group_id Candidate group ID.
	 * @return bool
	 */
	private function is_valid( $group_id ): bool {
		return is_string( $group_id ) && isset( $this->valid_groups[ $group_id ] );
	}

	/**
	 * Builds a decision record.
	 *
	 * @since 1.0.0
	 *
	 * @param string $group    Resolved group ID.
	 * @param string $layer    Which layer decided.
	 * @param string $evidence What that layer matched on.
	 * @param array  $scores   Keyword scores, when the scoring layer ran.
	 * @return array{group: string, layer: string, evidence: string, scores: array}
	 */
	private function outcome( string $group, string $layer, string $evidence, array $scores = array() ): array {
		return array(
			'group'    => $group,
			'layer'    => $layer,
			'evidence' => $evidence,
			'scores'   => $scores,
		);
	}
}
