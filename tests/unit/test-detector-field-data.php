<?php
/**
 * Detector regression tests built from a real production site.
 *
 * @package AdminMenuOrganizer
 * @since   1.0.0
 */

declare( strict_types=1 );

use AMORG\Detector;
use AMORG\Menu_Reader;
use PHPUnit\Framework\TestCase;

/**
 * Every slug here was read off a live WooCommerce marketplace running WoodMart,
 * MultiVendorX, YITH, WPML, Elementor, Yoast, Wordfence, WP Rocket, CookieYes,
 * Ninja Forms and more.
 *
 * Four of them originally fell through to the catch-all group and had to be
 * moved by hand, which is the only reason this file exists: the fixtures were
 * written from knowledge of how plugins *usually* register menus, and these are
 * the cases where that knowledge was simply wrong. Field data beats a plausible
 * fixture.
 *
 * @since 1.0.0
 */
final class Test_Detector_Field_Data extends TestCase {

	/**
	 * Builds a detector over the bundled data files.
	 *
	 * @since 1.0.0
	 *
	 * @return Detector
	 */
	private function detector(): Detector {
		$root = dirname( __DIR__, 2 );

		return new Detector(
			require $root . '/includes/data/known-slugs.php',
			require $root . '/includes/data/keyword-map.php',
			array_column( require $root . '/includes/data/categories.php', 'id' )
		);
	}

	/**
	 * Runs one menu item through detection.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title      Menu title as registered.
	 * @param string $slug       Menu slug as registered.
	 * @param string $capability Capability guarding the item.
	 * @param string $icon       Dashicon or icon URL.
	 * @return string Detected group ID.
	 */
	private function detect( string $title, string $slug, string $capability = 'manage_options', string $icon = '' ): string {
		$reader = new Menu_Reader( array( array( $title, $capability, $slug, '', '', '', $icon ) ) );
		$result = $this->detector()->detect_all( $reader );

		return $result[ $slug ] ?? 'MISSING';
	}

	/**
	 * The four items that a live site put in the catch-all group, which a human
	 * then had to move by hand. Each one is now detected.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_the_four_items_that_needed_moving_by_hand(): void {
		$this->assertSame(
			'commerce',
			$this->detect( 'MultiVendorX', 'multivendorx', 'manage_woocommerce' ),
			'MultiVendorX is a marketplace plugin.'
		);

		$this->assertSame(
			'design',
			$this->detect( 'WoodMart', 'xts_dashboard' ),
			'WoodMart is a theme, registered under the XTemos xts_ prefix.'
		);

		$this->assertSame(
			'integrations',
			$this->detect( 'WPML', 'tm/menu/main.php', 'wpml_manage_languages' ),
			'WPML registers a path rather than a conventional slug.'
		);

		$this->assertSame(
			'tools',
			$this->detect( 'CookieYes', 'cookie-law-info' ),
			'Cookie consent is a site-wide compliance utility.'
		);
	}

	/**
	 * The XTemos prefix generalises, so the theme's other screens land correctly
	 * without each being listed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_xtemos_prefix_generalises(): void {
		foreach ( array( 'xts_theme_settings', 'xts_header_builder', 'xts_presets', 'xts-something-new' ) as $slug ) {
			$this->assertSame( 'design', $this->detect( 'Theme settings', $slug ), $slug );
		}
	}

	/**
	 * Everything else observed on that site, which the cascade already handled.
	 *
	 * Kept as a regression net: these are real registrations, and a future change
	 * to the scoring could plausibly break one of them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_the_rest_of_the_live_menu(): void {
		$cases = array(
			// Commerce.
			array( 'WooCommerce', 'woocommerce', 'commerce', 'manage_woocommerce' ),
			array( 'Products', 'edit.php?post_type=product', 'commerce', 'edit_products' ),
			array( 'Marketing', 'woocommerce-marketing', 'commerce', 'manage_woocommerce' ),
			array( 'Analytics', 'wc-admin&path=/analytics/overview', 'commerce', 'manage_woocommerce' ),
			array( 'YITH', 'yith_plugin_panel', 'commerce', 'manage_options' ),

			// Design.
			array( 'Appearance', 'themes.php', 'design', 'switch_themes' ),
			array( 'Elementor', 'elementor', 'design', 'manage_options' ),
			array( 'Elementor', 'elementor-home', 'design', 'manage_options' ),

			// Content.
			array( 'Posts', 'edit.php', 'content', 'edit_posts' ),
			array( 'Media', 'upload.php', 'content', 'upload_files' ),
			array( 'Pages', 'edit.php?post_type=page', 'content', 'edit_pages' ),

			// Security.
			array( 'Wordfence', 'Wordfence', 'security', 'activate_plugins' ),
			array( 'All-in-One WP Migration', 'ai1wm_export', 'security', 'export' ),
			array( 'Activity Log', 'activity_log', 'security', 'manage_options' ),

			// Integrations.
			array( 'Contact', 'wpcf7', 'integrations', 'wpcf7_read_contact_forms' ),
			array( 'Ninja Forms', 'ninja-forms', 'integrations', 'manage_options' ),
			array( 'Click to Chat', 'click-to-chat', 'integrations', 'manage_options' ),
			array( 'WP Mail SMTP', 'wp-mail-smtp', 'integrations', 'manage_options' ),
			array( 'GEO my WP', 'gmw-add-ons', 'integrations', 'manage_options' ),

			// Dashboard and system.
			array( 'Dashboard', 'index.php', 'dashboard', 'read' ),
			array( 'Settings', 'options-general.php', 'tools', 'manage_options' ),
			array( 'Plugins', 'plugins.php', 'tools', 'activate_plugins' ),
			array( 'Users', 'users.php', 'users', 'list_users' ),
		);

		$wrong = array();

		foreach ( $cases as $case ) {
			list( $title, $slug, $expected, $capability ) = $case;
			$actual                                       = $this->detect( $title, $slug, $capability );

			if ( $actual !== $expected ) {
				$wrong[] = sprintf( '%s (%s): expected %s, got %s', $title, $slug, $expected, $actual );
			}
		}

		$this->assertSame( array(), $wrong, "Misdetected:\n  " . implode( "\n  ", $wrong ) );
	}

	/**
	 * Nothing from that site now falls through to the catch-all group.
	 *
	 * The point of the exercise: a real 30-plugin marketplace should produce an
	 * empty Other bucket.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_nothing_from_the_live_site_lands_in_the_catch_all(): void {
		$menu = array(
			array( 'Dashboard', 'read', 'index.php', '', '', '', 'dashicons-dashboard' ),
			array( 'Media', 'upload_files', 'upload.php', '', '', '', 'dashicons-admin-media' ),
			array( 'Posts', 'edit_posts', 'edit.php', '', '', '', 'dashicons-admin-post' ),
			array( 'Products', 'edit_products', 'edit.php?post_type=product', '', '', '', 'dashicons-products' ),
			array( 'WooCommerce', 'manage_woocommerce', 'woocommerce', '', '', '', 'dashicons-cart' ),
			array( 'MultiVendorX', 'manage_woocommerce', 'multivendorx', '', '', '', 'dashicons-groups' ),
			array( 'YITH', 'manage_options', 'yith_plugin_panel', '', '', '', 'dashicons-admin-generic' ),
			array( 'Appearance', 'switch_themes', 'themes.php', '', '', '', 'dashicons-admin-appearance' ),
			array( 'WoodMart', 'manage_options', 'xts_dashboard', '', '', '', 'dashicons-admin-appearance' ),
			array( 'Theme settings', 'manage_options', 'xts_theme_settings', '', '', '', 'dashicons-admin-generic' ),
			array( 'Elementor', 'manage_options', 'elementor', '', '', '', 'dashicons-admin-generic' ),
			array( 'Yoast SEO', 'wpseo_manage_options', 'wpseo_dashboard', '', '', '', 'dashicons-chart-bar' ),
			array( 'Wordfence', 'activate_plugins', 'Wordfence', '', '', '', 'dashicons-shield' ),
			array( 'WP Rocket', 'manage_options', 'wprocket', '', '', '', 'dashicons-performance' ),
			array( 'WPML', 'wpml_manage_languages', 'tm/menu/main.php', '', '', '', 'dashicons-translation' ),
			array( 'CookieYes', 'manage_options', 'cookie-law-info', '', '', '', 'dashicons-privacy' ),
			array( 'Ninja Forms', 'manage_options', 'ninja-forms', '', '', '', 'dashicons-feedback' ),
			array( 'WP Mail SMTP', 'manage_options', 'wp-mail-smtp', '', '', '', 'dashicons-email' ),
			array( 'Users', 'list_users', 'users.php', '', '', '', 'dashicons-admin-users' ),
			array( 'Settings', 'manage_options', 'options-general.php', '', '', '', 'dashicons-admin-settings' ),
		);

		$reader   = new Menu_Reader( $menu );
		$detected = $this->detector()->detect_all( $reader );

		$ungrouped = array_keys( $detected, 'ungrouped', true );

		$this->assertSame(
			array(),
			$ungrouped,
			'These still fall through to the catch-all: ' . implode( ', ', $ungrouped )
		);
	}
}
