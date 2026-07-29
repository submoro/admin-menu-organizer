<?php
/**
 * Unit tests for the menu read layer.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 */

declare( strict_types=1 );

use WPAMO\Menu_Reader;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/fixtures/menus.php';

/**
 * Covers normalization, separator detection, update counts and the
 * validate-against-the-live-menu guarantee.
 *
 * @since 1.0.0
 */
final class Test_Menu_Reader extends TestCase {

	/**
	 * Reader over the stock core menu.
	 *
	 * @since 1.0.0
	 *
	 * @return Menu_Reader
	 */
	private function core(): Menu_Reader {
		return new Menu_Reader( wpamo_fixture_core_menu(), wpamo_fixture_core_submenu() );
	}

	/**
	 * Reader over the realistic production menu.
	 *
	 * @since 1.0.0
	 *
	 * @return Menu_Reader
	 */
	private function production(): Menu_Reader {
		return new Menu_Reader( wpamo_fixture_production_menu(), wpamo_fixture_core_submenu() );
	}

	/**
	 * An empty menu is not usable, and does not explode.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_empty_menu_is_not_usable(): void {
		$reader = new Menu_Reader();

		$this->assertFalse( $reader->is_usable() );
		$this->assertSame( array(), $reader->items() );
		$this->assertSame( array(), $reader->slugs() );
	}

	/**
	 * A real menu is usable.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_core_menu_is_usable(): void {
		$this->assertTrue( $this->core()->is_usable() );
	}

	/**
	 * Every slug in the fixture survives normalization, in menu order.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_every_slug_is_read_in_order(): void {
		$expected = array(
			'index.php',
			'separator1',
			'edit.php',
			'upload.php',
			'edit.php?post_type=page',
			'edit-comments.php',
			'separator2',
			'themes.php',
			'plugins.php',
			'users.php',
			'tools.php',
			'options-general.php',
			'separator-last',
		);

		$this->assertSame( $expected, $this->core()->slugs() );
	}

	/**
	 * Separators are excluded from the organizable set but kept in the full set.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_separators_are_readable_but_not_organizable(): void {
		$reader = $this->core();

		$this->assertContains( 'separator1', $reader->slugs() );
		$this->assertNotContains( 'separator1', $reader->organizable_slugs() );
		$this->assertNotContains( 'separator-last', $reader->organizable_slugs() );

		$this->assertTrue( $reader->get( 'separator1' )['is_separator'] );
		$this->assertFalse( $reader->get( 'edit.php' )['is_separator'] );
	}

	/**
	 * Separator detection mirrors both tests core itself applies.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_separator_detection_matches_core(): void {
		// By class, which is what menu-header.php line 120 tests.
		$this->assertTrue( Menu_Reader::is_separator( 'anything', 'wp-menu-separator' ) );
		$this->assertTrue( Menu_Reader::is_separator( 'anything', 'foo wp-menu-separator bar' ) );
		$this->assertTrue( Menu_Reader::is_separator( 'anything', 'WP-MENU-SEPARATOR' ) );

		// By slug prefix, which is what includes/menu.php line 246 tests.
		$this->assertTrue( Menu_Reader::is_separator( 'separator1', '' ) );
		$this->assertTrue( Menu_Reader::is_separator( 'separator-woocommerce', '' ) );

		$this->assertFalse( Menu_Reader::is_separator( 'edit.php', 'menu-top' ) );
		$this->assertFalse( Menu_Reader::is_separator( 'my-separator-plugin', 'menu-top' ) );
	}

	/**
	 * Normalized items carry every field the rest of the plugin relies on.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_items_are_fully_normalized(): void {
		$item = $this->core()->get( 'edit.php' );

		$this->assertSame( 'edit.php', $item['slug'] );
		$this->assertSame( 'Posts', $item['title'] );
		$this->assertSame( 'Posts', $item['plain_title'] );
		$this->assertSame( 'edit_posts', $item['capability'] );
		$this->assertSame( 'menu-posts', $item['hookname'] );
		$this->assertSame( 'dashicons-admin-post', $item['icon'] );
		$this->assertFalse( $item['is_separator'] );
		$this->assertSame( 0, $item['update_count'] );
	}

	/**
	 * Update-count markup embedded in a title is read as a number.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_update_counts_are_extracted_from_titles(): void {
		$reader = $this->core();

		$this->assertSame( 7, $reader->get( 'plugins.php' )['update_count'] );
		$this->assertSame( 4, $reader->get( 'edit-comments.php' )['update_count'] );
		$this->assertSame( 0, $reader->get( 'upload.php' )['update_count'] );
	}

	/**
	 * Count extraction handles the shapes core and plugins actually emit.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_update_count_parsing(): void {
		$this->assertSame( 0, Menu_Reader::update_count( 'Plugins' ) );
		$this->assertSame( 0, Menu_Reader::update_count( '' ) );
		$this->assertSame( 3, Menu_Reader::update_count( 'Plugins <span class="update-plugins count-3"><span class="plugin-count">3</span></span>' ) );
		$this->assertSame( 5, Menu_Reader::update_count( 'Comments <span class="awaiting-mod count-5">5</span>' ) );
		$this->assertSame( 0, Menu_Reader::update_count( 'Discount codes' ) );
		$this->assertSame( 0, Menu_Reader::update_count( 'Account-manager' ) );

		// Two bubbles in one title are summed.
		$this->assertSame( 9, Menu_Reader::update_count( '<span class="count-4"></span><span class="count-5"></span>' ) );
	}

	/**
	 * Titles are reduced to readable text for matching and labelling.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_plain_title_strips_markup(): void {
		$this->assertSame(
			'Plugins',
			Menu_Reader::plain_title( 'Plugins <span class="update-plugins count-7"><span class="plugin-count">7</span></span>' )
		);
		$this->assertSame( 'Tools & Settings', Menu_Reader::plain_title( 'Tools &amp; Settings' ) );
		$this->assertSame( 'Spaced out', Menu_Reader::plain_title( "  Spaced\n\tout  " ) );
		$this->assertSame( '', Menu_Reader::plain_title( '' ) );
	}

	/**
	 * A script block in a title does not leak its source into the plain text.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_plain_title_removes_script_bodies(): void {
		$this->assertSame(
			'Safe',
			Menu_Reader::plain_title( 'Safe<script>alert("xss")</script>' )
		);
		$this->assertSame(
			'Styled',
			Menu_Reader::plain_title( 'Styled<style>.a{color:red}</style>' )
		);
	}

	/**
	 * Malformed entries are skipped without notices, and never invent an item.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_malformed_entries_are_survived(): void {
		$reader = new Menu_Reader( wpamo_fixture_malformed_menu() );

		$this->assertSame(
			array( 'index.php', 'short.php', 'numeric-title.php', 'options-general.php' ),
			$reader->slugs()
		);
	}

	/**
	 * A duplicate slug keeps the first occurrence, matching how core's
	 * array_flip() resolves the collision in the menu_order pipeline.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_duplicate_slugs_keep_the_first_occurrence(): void {
		$reader = new Menu_Reader( wpamo_fixture_malformed_menu() );

		$this->assertSame( 'Dashboard', $reader->get( 'index.php' )['title'] );
		$this->assertSame( 1, count( array_keys( $reader->slugs(), 'index.php', true ) ) );
	}

	/**
	 * A non-string title is coerced rather than causing a type error.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_numeric_titles_are_coerced(): void {
		$reader = new Menu_Reader( wpamo_fixture_malformed_menu() );

		$this->assertSame( '12345', $reader->get( 'numeric-title.php' )['title'] );
	}

	/**
	 * Membership is answered against the live menu, which is the guard SPEC
	 * section 8 requires before any stored slug is trusted.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_has_validates_against_the_live_menu(): void {
		$reader = $this->core();

		$this->assertTrue( $reader->has( 'edit.php' ) );
		$this->assertFalse( $reader->has( 'woocommerce' ) );
		$this->assertFalse( $reader->has( '' ) );
		$this->assertNull( $reader->get( 'does-not-exist' ) );
	}

	/**
	 * Filtering drops slugs that are absent, non-string, empty or duplicated,
	 * and preserves the order of those that survive.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_filter_known_rejects_anything_not_in_the_live_menu(): void {
		$reader = $this->core();

		$filtered = $reader->filter_known(
			array(
				'options-general.php',
				'woocommerce',        // Plugin since deactivated.
				'edit.php',
				'separator1',         // Present, but not organizable.
				'',
				null,
				42,
				array( 'nested' ),
				'edit.php',           // Duplicate.
			)
		);

		$this->assertSame( array( 'options-general.php', 'edit.php' ), $filtered );
	}

	/**
	 * Update counts aggregate across a group, which is what a collapsed group
	 * header badge shows under SPEC section 6.3.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_update_counts_aggregate_across_slugs(): void {
		$reader = $this->core();

		$this->assertSame( 11, $reader->update_count_for( array( 'plugins.php', 'edit-comments.php' ) ) );
		$this->assertSame( 0, $reader->update_count_for( array( 'upload.php' ) ) );
		$this->assertSame( 0, $reader->update_count_for( array() ) );
		$this->assertSame( 7, $reader->update_count_for( array( 'plugins.php', 'not-a-real-slug' ) ) );
	}

	/**
	 * The realistic production menu reads cleanly, including base64 SVG icons
	 * and post-type slugs that carry a query string.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_production_menu_reads_cleanly(): void {
		$reader = $this->production();

		$this->assertTrue( $reader->is_usable() );
		$this->assertGreaterThan( 25, count( $reader->organizable_slugs() ) );

		$this->assertTrue( $reader->has( 'woocommerce' ) );
		$this->assertTrue( $reader->has( 'edit.php?post_type=product' ) );
		$this->assertTrue( $reader->has( 'sitepress-multilingual-cms/menu/languages.php' ) );
		$this->assertTrue( $reader->has( 'wc-admin&path=/analytics/overview' ) );

		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $reader->get( 'woocommerce' )['icon'] );
		$this->assertSame( 'WooCommerce', $reader->get( 'woocommerce' )['plain_title'] );
		$this->assertSame( 2, $reader->get( 'woocommerce' )['update_count'] );
	}

	/**
	 * Reading the same menu twice yields identical results, so memoisation has
	 * not introduced state that leaks between calls.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_reads_are_stable(): void {
		$reader = $this->production();

		$this->assertSame( $reader->items(), $reader->items() );
		$this->assertSame( $reader->slugs(), $reader->slugs() );
	}
}
