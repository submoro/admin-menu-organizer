<?php
/**
 * Unit tests for grouped reordering.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

declare( strict_types=1 );

use AMORG\Menu_Order;
use AMORG\Menu_Reader;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/fixtures/menus.php';

/**
 * Covers the never-lose-a-slug guarantee, group contiguity, and preservation of
 * an upstream plugin's ordering.
 *
 * The permutation assertions here are the most important in the suite. SPEC
 * section 15 requires that no menu item is ever lost, hidden or made
 * inaccessible in any configuration, and reordering is the one place where that
 * could silently happen.
 *
 * @since 1.0.0
 */
final class Test_Menu_Order extends TestCase {

	/**
	 * Asserts the output is a permutation of the input: same members, same count,
	 * no duplicates, nothing invented.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $in  Input slugs.
	 * @param string[] $out Output slugs.
	 * @return void
	 */
	private function assertIsPermutationOf( array $in, array $out ): void {
		$unique_in = array_values( array_unique( $in ) );

		$this->assertCount( count( $unique_in ), $out, 'Slug count changed.' );
		$this->assertSame( array_values( array_unique( $out ) ), $out, 'Output contains a duplicate slug.' );

		$missing = array_diff( $unique_in, $out );
		$added   = array_diff( $out, $unique_in );

		$this->assertSame( array(), array_values( $missing ), 'Slugs were lost: ' . implode( ', ', $missing ) );
		$this->assertSame( array(), array_values( $added ), 'Slugs were invented: ' . implode( ', ', $added ) );
	}

	/**
	 * A layout built from a slug-to-group map.
	 *
	 * @since 1.0.0
	 *
	 * @param array $assignments Map of group ID to a list of slugs.
	 * @return array
	 */
	private function layout( array $assignments ): array {
		$groups = array();

		foreach ( $assignments as $id => $items ) {
			$groups[] = array(
				'id'    => $id,
				'items' => $items,
			);
		}

		return array(
			'schema' => 1,
			'groups' => $groups,
		);
	}

	// ------------------------------------------------ The core guarantee.

	/**
	 * Nothing is lost when reordering the stock core menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_core_menu_keeps_every_slug(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );
		$in     = $reader->slugs();

		$out = Menu_Order::order(
			$in,
			$reader,
			$this->layout(
				array(
					'content' => array( 'edit.php', 'upload.php' ),
					'tools'   => array( 'tools.php', 'options-general.php' ),
				)
			)
		);

		$this->assertIsPermutationOf( $in, $out );
	}

	/**
	 * Nothing is lost on a realistic thirty-plus item production menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_production_menu_keeps_every_slug(): void {
		$reader = new Menu_Reader( amorg_fixture_production_menu() );
		$in     = $reader->slugs();

		$out = Menu_Order::order(
			$in,
			$reader,
			$this->layout(
				array(
					'commerce' => array( 'woocommerce', 'edit.php?post_type=product' ),
					'design'   => array( 'elementor', 'revslider' ),
					'security' => array( 'Wordfence', 'updraftplus' ),
				)
			)
		);

		$this->assertIsPermutationOf( $in, $out );
	}

	/**
	 * Nothing is lost with an empty layout, which is the auto-detect case.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_empty_layout_keeps_every_slug(): void {
		$reader = new Menu_Reader( amorg_fixture_production_menu() );
		$in     = $reader->slugs();

		$this->assertIsPermutationOf( $in, Menu_Order::order( $in, $reader, array() ) );
		$this->assertIsPermutationOf( $in, Menu_Order::order( $in, $reader, array( 'groups' => array() ) ) );
	}

	/**
	 * Nothing is lost when the layout is actively hostile.
	 *
	 * Every entry here has been chosen to break a different assumption: unknown
	 * groups, duplicate groups, a slug claimed twice, slugs that do not exist,
	 * non-string values, and nested arrays where a slug belongs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_hostile_layouts_keep_every_slug(): void {
		$reader = new Menu_Reader( amorg_fixture_production_menu() );
		$in     = $reader->slugs();

		$hostile = array(
			array( 'groups' => 'not an array' ),
			array( 'groups' => array( 'not an array' ) ),
			array( 'groups' => array( array() ) ),
			array( 'groups' => array( array( 'id' => '' ) ) ),
			array(
				'groups' => array(
					array(
						'id'    => 'nope',
						'items' => array( 'edit.php' ),
					),
				),
			),
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( 'edit.php' ),
					),
					array(
						'id'    => 'content',
						'items' => array( 'upload.php' ),
					),
				),
			),
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( 'edit.php' ),
					),
					array(
						'id'    => 'tools',
						'items' => array( 'edit.php' ),
					),
				),
			),
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( 'ghost.php', 'gone.php' ),
					),
				),
			),
			array(
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( null, 42, array( 'x' ), '' ),
					),
				),
			),
			array(
				'groups' => array(
					array(
						'id'    => 'ungrouped',
						'items' => array( 'edit.php' ),
					),
				),
			),
		);

		foreach ( $hostile as $index => $layout ) {
			$out = Menu_Order::order( $in, $reader, $layout );

			$this->assertIsPermutationOf( $in, $out );
			$this->assertCount( count( $in ), $out, "Hostile layout {$index} changed the slug count." );
		}
	}

	/**
	 * Nothing is lost or invented for randomised menus and layouts.
	 *
	 * A property-based sweep. Hand-written cases only cover the failures somebody
	 * thought of; this covers combinations nobody did.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_permutation_holds_for_randomised_input(): void {
		$groups = array( 'content', 'commerce', 'design', 'tools', 'ungrouped' );

		// Deterministic seed, so a failure is reproducible rather than a ghost.
		mt_srand( 20260726 );

		for ( $round = 0; $round < 200; $round++ ) {
			$count = mt_rand( 1, 30 );
			$menu  = array();

			for ( $i = 0; $i < $count; $i++ ) {
				$slug = 'slug-' . $i;

				// Sprinkle in separators, which take a different path.
				if ( 0 === mt_rand( 0, 5 ) ) {
					$menu[ $i ] = array( '', 'read', 'separator' . $i, '', 'wp-menu-separator' );
					continue;
				}

				$menu[ $i ] = array( 'Item ' . $i, 'read', $slug, '', '', '', '' );
			}

			$reader = new Menu_Reader( $menu );
			$in     = $reader->slugs();

			// A layout referencing a random mixture of real and imaginary slugs.
			$layout_groups = array();

			foreach ( $groups as $group_id ) {
				$items      = array();
				$item_count = mt_rand( 0, 5 );

				for ( $i = 0; $i < $item_count; $i++ ) {
					$items[] = 0 === mt_rand( 0, 3 )
						? 'imaginary-' . mt_rand( 1, 99 )
						: 'slug-' . mt_rand( 0, $count );
				}

				$layout_groups[] = array(
					'id'    => $group_id,
					'items' => $items,
				);
			}

			$out = Menu_Order::order( $in, $reader, array( 'groups' => $layout_groups ) );

			$this->assertIsPermutationOf( $in, $out );
		}
	}

	// ---------------------------------------------------- Group contiguity.

	/**
	 * Every group's members end up adjacent, which is what makes an accordion
	 * possible at all.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_group_members_are_contiguous(): void {
		$reader = new Menu_Reader( amorg_fixture_production_menu() );

		$assignments = array(
			'content'  => array( 'edit.php', 'upload.php', 'edit-comments.php' ),
			'commerce' => array( 'woocommerce', 'edit.php?post_type=product', 'woocommerce-marketing' ),
			'design'   => array( 'elementor', 'revslider' ),
		);

		$out      = Menu_Order::order( $reader->slugs(), $reader, $this->layout( $assignments ) );
		$position = array_flip( $out );

		foreach ( $assignments as $group_id => $items ) {
			$indexes = array();

			foreach ( $items as $slug ) {
				$indexes[] = $position[ $slug ];
			}

			sort( $indexes );

			$this->assertSame(
				range( $indexes[0], $indexes[0] + count( $indexes ) - 1 ),
				$indexes,
				"Group {$group_id} is not contiguous."
			);
		}
	}

	/**
	 * Groups are emitted in the order the layout lists them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_groups_are_emitted_in_layout_order(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );

		$out = Menu_Order::order(
			$reader->slugs(),
			$reader,
			$this->layout(
				array(
					'tools'   => array( 'tools.php' ),
					'content' => array( 'edit.php' ),
					'design'  => array( 'themes.php' ),
				)
			)
		);

		$position = array_flip( $out );

		$this->assertLessThan( $position['edit.php'], $position['tools.php'] );
		$this->assertLessThan( $position['themes.php'], $position['edit.php'] );
	}

	/**
	 * Items are ordered within a group as the layout lists them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_items_follow_layout_order_within_a_group(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );

		$out = Menu_Order::order(
			$reader->slugs(),
			$reader,
			$this->layout( array( 'content' => array( 'edit-comments.php', 'upload.php', 'edit.php' ) ) )
		);

		$position = array_flip( $out );

		$this->assertLessThan( $position['upload.php'], $position['edit-comments.php'] );
		$this->assertLessThan( $position['edit.php'], $position['upload.php'] );
	}

	/**
	 * The catch-all group is always last, so anything unplaced sits at the bottom
	 * rather than interrupting a real group.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_ungrouped_is_always_last(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );

		$out = Menu_Order::order(
			$reader->slugs(),
			$reader,
			$this->layout(
				array(
					// Deliberately listed first, to prove position in the layout
					// cannot promote the catch-all above a real group.
					'ungrouped' => array( 'edit.php' ),
					'content'   => array( 'upload.php' ),
				)
			)
		);

		$position = array_flip( $out );

		// content is emitted before the catch-all despite being listed after it.
		$this->assertLessThan( $position['edit.php'], $position['upload.php'] );

		/*
		 * Everything the layout did not place also lands in the catch-all, since
		 * no detected map was supplied. So the assertion worth making is that the
		 * catch-all forms one unbroken block at the end, immediately after the
		 * single content item, rather than that any particular slug is last.
		 */
		$non_separator = array_values(
			array_filter(
				$out,
				static function ( $slug ) use ( $reader ) {
					$item = $reader->get( $slug );

					return null !== $item && ! $item['is_separator'];
				}
			)
		);

		$this->assertSame( 'upload.php', $non_separator[0], 'The content group should lead.' );

		// edit.php was placed by hand, so it leads the catch-all block.
		$this->assertSame( 'edit.php', $non_separator[1], 'Explicit placement should lead its group.' );

		// Every remaining item is in the catch-all, so nothing from content
		// reappears after the block starts.
		$this->assertCount( count( $reader->organizable_slugs() ), $non_separator );
	}

	/**
	 * The group IDs a layout defines always end with the catch-all, exactly once.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_group_ids_always_end_with_ungrouped(): void {
		$this->assertSame( array( 'ungrouped' ), Menu_Order::group_ids( array() ) );

		$ids = Menu_Order::group_ids(
			$this->layout(
				array(
					'content'   => array(),
					'ungrouped' => array(),
					'tools'     => array(),
				)
			)
		);

		$this->assertSame( array( 'content', 'tools', 'ungrouped' ), $ids );
		$this->assertSame( 1, count( array_keys( $ids, 'ungrouped', true ) ) );
	}

	// ------------------------------------------------------- Detection input.

	/**
	 * Slugs the layout does not mention are placed by the detected map.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_detected_assignments_place_unlisted_slugs(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );

		$out = Menu_Order::order(
			$reader->slugs(),
			$reader,
			$this->layout(
				array(
					'content' => array(),
					'tools'   => array(),
				)
			),
			array(
				'edit.php'            => 'content',
				'upload.php'          => 'content',
				'tools.php'           => 'tools',
				'options-general.php' => 'tools',
			)
		);

		$position = array_flip( $out );

		$this->assertLessThan( $position['tools.php'], $position['edit.php'] );
		$this->assertLessThan( $position['tools.php'], $position['upload.php'] );
	}

	/**
	 * A detected group that the layout does not define is ignored, and the item
	 * falls to the catch-all rather than vanishing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_detected_group_outside_the_layout_falls_to_ungrouped(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );
		$in     = $reader->slugs();

		$out = Menu_Order::order(
			$in,
			$reader,
			$this->layout( array( 'content' => array() ) ),
			array( 'tools.php' => 'a-group-that-is-not-in-the-layout' )
		);

		$this->assertIsPermutationOf( $in, $out );
		$this->assertContains( 'tools.php', $out );
	}

	// ------------------------------------------- Coexistence with other plugins.

	/**
	 * Where the layout is silent, the incoming order is preserved. This is SPEC
	 * section 3.5's requirement that another plugin's positioning survives.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_incoming_order_is_preserved_within_a_group(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );

		// Another plugin has already put comments before media before posts.
		$incoming = array( 'edit-comments.php', 'upload.php', 'edit.php', 'tools.php' );

		$out = Menu_Order::order(
			$incoming,
			$reader,
			// The layout names the group but does not order its items.
			$this->layout( array( 'content' => array() ) ),
			array(
				'edit.php'          => 'content',
				'upload.php'        => 'content',
				'edit-comments.php' => 'content',
			)
		);

		$this->assertSame( array( 'edit-comments.php', 'upload.php', 'edit.php', 'tools.php' ), $out );
	}

	/**
	 * An explicitly placed item leads the items that were merely detected.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_explicit_placement_beats_incoming_order(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );

		$incoming = array( 'edit-comments.php', 'upload.php', 'edit.php' );

		$out = Menu_Order::order(
			$incoming,
			$reader,
			$this->layout( array( 'content' => array( 'edit.php' ) ) ),
			array(
				'upload.php'        => 'content',
				'edit-comments.php' => 'content',
			)
		);

		$this->assertSame( 'edit.php', $out[0] );
	}

	/**
	 * A duplicate in the incoming array is collapsed rather than emitted twice.
	 *
	 * Core array_flip()s our return value, so a duplicate would corrupt every
	 * subsequent position. See docs/core-notes.md section 4.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_incoming_duplicates_are_collapsed(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );

		$out = Menu_Order::order(
			array( 'edit.php', 'upload.php', 'edit.php', 'tools.php', 'upload.php' ),
			$reader,
			array()
		);

		$this->assertSame( array( 'edit.php', 'upload.php', 'tools.php' ), $out );
	}

	/**
	 * Non-string entries in the incoming array are discarded, not coerced.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_incoming_junk_is_discarded(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );

		$out = Menu_Order::order(
			array( 'edit.php', null, 42, array( 'x' ), '', false, 'tools.php' ),
			$reader,
			array()
		);

		$this->assertSame( array( 'edit.php', 'tools.php' ), $out );
	}

	/**
	 * An empty incoming array yields an empty result rather than an error.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_empty_input_is_safe(): void {
		$this->assertSame( array(), Menu_Order::order( array(), new Menu_Reader(), array() ) );
	}

	// ------------------------------------------------------------ Separators.

	/**
	 * Separators are emitted last, never dropped, so the permutation guarantee
	 * holds while grouped items stay visually uninterrupted.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_separators_move_to_the_end(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );
		$in     = $reader->slugs();

		$out = Menu_Order::order( $in, $reader, $this->layout( array( 'content' => array( 'edit.php' ) ) ) );

		$this->assertIsPermutationOf( $in, $out );

		$separators = array( 'separator1', 'separator2', 'separator-last' );
		$tail       = array_slice( $out, -3 );

		sort( $tail );
		sort( $separators );

		$this->assertSame( $separators, $tail, 'Separators did not all move to the end.' );
	}

	/**
	 * A separator named in a layout is still treated as a separator, so a crafted
	 * layout cannot drag one into the middle of a group.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_layout_cannot_place_a_separator_inside_a_group(): void {
		$reader = new Menu_Reader( amorg_fixture_core_menu() );

		$out = Menu_Order::order(
			$reader->slugs(),
			$reader,
			$this->layout( array( 'content' => array( 'separator1', 'edit.php' ) ) )
		);

		$this->assertSame( 'edit.php', $out[0] );
		$this->assertContains( 'separator1', array_slice( $out, -3 ) );
	}
}
