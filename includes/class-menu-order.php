<?php
/**
 * Grouped reordering of the top-level admin menu.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 */

namespace MOCAM;

defined( 'ABSPATH' ) || exit;

/**
 * Produces the slug order that makes each group contiguous.
 *
 * The ordering itself is a pure static function, order(), so the guarantees
 * below are provable by unit test rather than by inspection. Three of them
 * matter enough to state plainly:
 *
 * 1. **The output is a permutation of the input.** Every slug that came in goes
 *    out, exactly once, with nothing added. Verified by assertion in the tests
 *    for every fixture and every malformed layout.
 * 2. **Unrecognised slugs keep their relative order.** SPEC section 3.5 requires
 *    this so that a menu item another plugin has deliberately positioned is not
 *    scrambled. Within a group, items the layout does not mention fall back to
 *    the incoming order rather than to alphabetical or arbitrary order.
 * 3. **A missing slug is an ordering bug, not a data-loss bug.** Core's
 *    menu_order filter cannot delete a menu item: omitted slugs survive and sort
 *    to the bottom of the sidebar. See docs/core-notes.md section 4. Returning
 *    every slug is still required, because being silently shunted to the bottom
 *    is a visible defect even though the item still exists.
 *
 * Separators are a special case, explained at separator handling below.
 *
 * @since 1.0.0
 */
final class Menu_Order {

	/**
	 * Priority for the custom_menu_order and menu_order filters.
	 *
	 * Deliberately late. SPEC section 3.5 requires running after any other plugin
	 * that reorders the menu, so that its decisions are visible in the array we
	 * receive and can be preserved.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const PRIORITY = 9999;

	/**
	 * Reorders slugs so that every group's members are contiguous.
	 *
	 * @since 1.0.0
	 *
	 * @param string[]    $incoming Slug order as received, possibly already
	 *                              reordered by another plugin.
	 * @param Menu_Reader $reader   Reader over the live menu.
	 * @param array       $layout   Sanitised layout.
	 * @param array       $detected Map of slug to group ID for anything the layout
	 *                              does not place. Typically from Detector.
	 * @return string[] A permutation of $incoming.
	 */
	public static function order( array $incoming, Menu_Reader $reader, array $layout, array $detected = array() ): array {
		// Positions in the incoming array, used as the tie-break so that another
		// plugin's ordering survives wherever we have no opinion.
		$position = array();
		$slugs    = array();

		foreach ( $incoming as $index => $slug ) {
			if ( ! is_string( $slug ) || '' === $slug || isset( $position[ $slug ] ) ) {
				continue;
			}

			$position[ $slug ] = (int) $index;
			$slugs[]           = $slug;
		}

		if ( empty( $slugs ) ) {
			return array();
		}

		$group_ids = self::group_ids( $layout );

		// Bucket every slug. Separators are held back; see below.
		$buckets    = array_fill_keys( $group_ids, array() );
		$separators = array();
		$assigned   = array();

		foreach ( self::layout_assignments( $layout, $group_ids ) as $slug => $group_id ) {
			if ( isset( $position[ $slug ] ) && ! self::is_separator( $reader, $slug ) ) {
				$buckets[ $group_id ][] = $slug;
				$assigned[ $slug ]      = true;
			}
		}

		foreach ( $slugs as $slug ) {
			if ( isset( $assigned[ $slug ] ) ) {
				continue;
			}

			/*
			 * Separator handling. Separators are core's own visual spacers: they
			 * carry no label, no link and no capability, and core renders them
			 * aria-hidden. Once the menu is grouped they mean nothing, and leaving
			 * them interleaved would draw lines through the middle of a group.
			 *
			 * They are therefore emitted last rather than dropped, which keeps the
			 * permutation guarantee intact, and hidden by CSS while the plugin is
			 * active. Nothing is lost: they are decoration, not menu items, so
			 * SPEC section 1.4's rule against hiding items does not apply, and
			 * they reappear the instant the plugin is deactivated.
			 */
			if ( self::is_separator( $reader, $slug ) ) {
				$separators[] = $slug;
				continue;
			}

			$group_id = isset( $detected[ $slug ] ) && isset( $buckets[ $detected[ $slug ] ] )
				? $detected[ $slug ]
				: Categories::UNGROUPED;

			if ( ! isset( $buckets[ $group_id ] ) ) {
				$group_id = Categories::UNGROUPED;
			}

			$buckets[ $group_id ][] = $slug;
		}

		// Flatten in group order, sorting each bucket by incoming position so an
		// upstream plugin's arrangement survives where the layout is silent.
		$ordered = array();

		foreach ( $group_ids as $group_id ) {
			$bucket = $buckets[ $group_id ];

			usort(
				$bucket,
				static function ( $a, $b ) use ( $position, $layout, $group_id ) {
					$explicit = self::explicit_index( $layout, $group_id );

					$a_explicit = $explicit[ $a ] ?? null;
					$b_explicit = $explicit[ $b ] ?? null;

					// Both placed by hand: honour the layout.
					if ( null !== $a_explicit && null !== $b_explicit ) {
						return $a_explicit <=> $b_explicit;
					}

					// One placed by hand: it leads.
					if ( null !== $a_explicit ) {
						return -1;
					}

					if ( null !== $b_explicit ) {
						return 1;
					}

					// Neither: keep whatever order we were handed.
					return ( $position[ $a ] ?? 0 ) <=> ( $position[ $b ] ?? 0 );
				}
			);

			foreach ( $bucket as $slug ) {
				$ordered[] = $slug;
			}
		}

		foreach ( $separators as $slug ) {
			$ordered[] = $slug;
		}

		/*
		 * Belt and braces. If any of the bucketing above ever dropped a slug, the
		 * bug would show as a menu item silently jumping to the bottom of the
		 * sidebar, which is exactly the failure this class exists to prevent.
		 * Appending anything unaccounted for makes the guarantee unconditional
		 * rather than dependent on the logic above being correct.
		 */
		if ( count( $ordered ) !== count( $slugs ) ) {
			$present = array_flip( $ordered );

			foreach ( $slugs as $slug ) {
				if ( ! isset( $present[ $slug ] ) ) {
					$ordered[] = $slug;
				}
			}
		}

		return $ordered;
	}

	/**
	 * Returns the group IDs a layout defines, with the catch-all guaranteed last.
	 *
	 * @since 1.0.0
	 *
	 * @param array $layout Sanitised layout.
	 * @return string[]
	 */
	public static function group_ids( array $layout ): array {
		$ids = array();

		$groups = isset( $layout['groups'] ) && is_array( $layout['groups'] ) ? $layout['groups'] : array();

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || empty( $group['id'] ) || ! is_string( $group['id'] ) ) {
				continue;
			}

			if ( Categories::UNGROUPED === $group['id'] || in_array( $group['id'], $ids, true ) ) {
				continue;
			}

			$ids[] = $group['id'];
		}

		// The catch-all is always present and always last, so anything the layout
		// does not place has somewhere visible to go, at the bottom.
		$ids[] = Categories::UNGROUPED;

		return $ids;
	}

	/**
	 * Maps every slug the layout explicitly places to its group.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $layout    Sanitised layout.
	 * @param string[] $group_ids Group IDs in emission order.
	 * @return array<string, string> Slug to group ID.
	 */
	private static function layout_assignments( array $layout, array $group_ids ): array {
		$valid       = array_flip( $group_ids );
		$assignments = array();

		$groups = isset( $layout['groups'] ) && is_array( $layout['groups'] ) ? $layout['groups'] : array();

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || empty( $group['id'] ) || ! is_string( $group['id'] ) ) {
				continue;
			}

			$group_id = $group['id'];

			if ( ! isset( $valid[ $group_id ] ) ) {
				continue;
			}

			$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();

			foreach ( $items as $slug ) {
				// First claim wins, so a slug listed under two groups cannot be
				// emitted twice.
				if ( is_string( $slug ) && '' !== $slug && ! isset( $assignments[ $slug ] ) ) {
					$assignments[ $slug ] = $group_id;
				}
			}
		}

		return $assignments;
	}

	/**
	 * Returns the explicit item order for one group, as slug to index.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $layout   Sanitised layout.
	 * @param string $group_id Group to look up.
	 * @return array<string, int>
	 */
	private static function explicit_index( array $layout, string $group_id ): array {
		$groups = isset( $layout['groups'] ) && is_array( $layout['groups'] ) ? $layout['groups'] : array();

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || ( $group['id'] ?? null ) !== $group_id ) {
				continue;
			}

			$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();

			$index    = array();
			$position = 0;

			foreach ( $items as $slug ) {
				if ( is_string( $slug ) && '' !== $slug && ! isset( $index[ $slug ] ) ) {
					$index[ $slug ] = $position;
					++$position;
				}
			}

			return $index;
		}

		return array();
	}

	/**
	 * Whether a slug belongs to a separator, according to the live menu.
	 *
	 * Falls back to the slug-prefix test when the reader has never seen the slug,
	 * which can happen if an upstream plugin added one to the order array without
	 * registering it.
	 *
	 * @since 1.0.0
	 *
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @param string      $slug   Menu slug.
	 * @return bool
	 */
	private static function is_separator( Menu_Reader $reader, string $slug ): bool {
		$item = $reader->get( $slug );

		if ( null !== $item ) {
			return (bool) $item['is_separator'];
		}

		return Menu_Reader::is_separator( $slug, '' );
	}
}
