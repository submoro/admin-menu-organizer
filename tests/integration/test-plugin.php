<?php
/**
 * Tests for the plugin container, its guards and its activation routine.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 */

declare( strict_types=1 );

use WPAMO\Activator;
use WPAMO\Plugin;

/**
 * Covers the Phase 2 skeleton: constants, autoloading, the two escape hatches
 * and the request-context guard.
 *
 * @since 1.0.0
 */
class Test_Plugin extends WP_UnitTestCase {

	/**
	 * Resets the container between tests.
	 *
	 * Plugin is a singleton that memoises its disabled state for the lifetime of
	 * a request, which is correct in production but would leak between tests.
	 * Reflection is used rather than adding a reset method that exists only for
	 * the benefit of the test suite.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$instance = new ReflectionProperty( Plugin::class, 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		unset( $_GET['wpamo'] );
	}

	/**
	 * Cleans up request superglobals the tests write to.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $_GET['wpamo'] );

		parent::tear_down();
	}

	/**
	 * The bootstrap defines every constant the rest of the plugin relies on.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_bootstrap_defines_its_constants(): void {
		$this->assertTrue( defined( 'WPAMO_VERSION' ) );
		$this->assertTrue( defined( 'WPAMO_MIN_PHP' ) );
		$this->assertTrue( defined( 'WPAMO_MIN_WP' ) );
		$this->assertTrue( defined( 'WPAMO_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'WPAMO_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'WPAMO_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'WPAMO_PLUGIN_BASENAME' ) );
	}

	/**
	 * The version constant matches the version in the plugin header, which is
	 * what WordPress.org treats as authoritative.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_version_constant_matches_the_plugin_header(): void {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data( WPAMO_PLUGIN_FILE, false, false );

		$this->assertSame( WPAMO_VERSION, $data['Version'] );
	}

	/**
	 * The version constant also matches the readme's stable tag.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_version_constant_matches_the_readme_stable_tag(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in a test, not a remote fetch.
		$readme = file_get_contents( WPAMO_PLUGIN_DIR . 'readme.txt' );

		$this->assertIsString( $readme );
		$this->assertSame( 1, preg_match( '/^Stable tag:\s*(.+)$/m', $readme, $matches ) );
		$this->assertSame( WPAMO_VERSION, trim( $matches[1] ) );
	}

	/**
	 * The autoloader resolves namespaced classes to hyphenated file names.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_autoloader_resolves_plugin_classes(): void {
		$this->assertTrue( class_exists( Plugin::class ) );
		$this->assertTrue( class_exists( Activator::class ) );
	}

	/**
	 * The instance() method always hands back the same object.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_instance_is_a_singleton(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}

	/**
	 * The uninstaller's key lists cover every key the plugin actually writes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_uninstall_key_lists_are_complete(): void {
		$this->assertContains( Plugin::OPTION_LAYOUT, Plugin::ALL_OPTIONS );
		$this->assertContains( Plugin::OPTION_ROLE_LAYOUTS, Plugin::ALL_OPTIONS );
		$this->assertContains( Plugin::OPTION_SETTINGS, Plugin::ALL_OPTIONS );
		$this->assertContains( Plugin::OPTION_VERSION, Plugin::ALL_OPTIONS );

		$this->assertContains( Plugin::META_USER_LAYOUT, Plugin::ALL_USER_META );
		$this->assertContains( Plugin::META_COLLAPSED, Plugin::ALL_USER_META );
	}

	/**
	 * Every key the plugin owns carries the reserved prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_every_stored_key_is_prefixed(): void {
		$keys = array_merge( Plugin::ALL_OPTIONS, Plugin::ALL_USER_META );

		foreach ( $keys as $key ) {
			$this->assertStringStartsWith( 'wpamo_', $key );
		}
	}

	/**
	 * The plugin runs normally when neither escape hatch is engaged.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_not_disabled_by_default(): void {
		$this->assertFalse( Plugin::instance()->is_disabled() );
	}

	/**
	 * The query-parameter escape hatch disables the plugin for the request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_query_parameter_escape_hatch_disables_the_plugin(): void {
		$_GET['wpamo'] = 'off';

		$this->assertTrue( Plugin::instance()->is_disabled() );
	}

	/**
	 * Only the exact value "off" trips the escape hatch.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_query_parameter_ignores_other_values(): void {
		$_GET['wpamo'] = 'on';

		$this->assertFalse( Plugin::instance()->is_disabled() );
	}

	/**
	 * A disabled plugin registers no hooks at all.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_boot_registers_nothing_when_disabled(): void {
		$_GET['wpamo'] = 'off';

		Plugin::instance()->boot();

		$this->assertFalse( has_filter( 'custom_menu_order' ) );
		$this->assertFalse( has_filter( 'menu_order' ) );
		$this->assertFalse( has_filter( 'add_menu_classes' ) );
	}

	/**
	 * Booting twice is a no-op, so a duplicated plugins_loaded cannot
	 * double-register the plugin's hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_boot_is_idempotent(): void {
		$plugin = Plugin::instance();

		$plugin->boot();
		$plugin->boot();

		$booted = new ReflectionProperty( Plugin::class, 'booted' );
		$booted->setAccessible( true );

		$this->assertTrue( $booted->getValue( $plugin ) );
	}

	/**
	 * The menu is never touched on a front-end request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_does_not_organize_on_the_front_end(): void {
		$this->assertFalse( is_admin() );
		$this->assertFalse( Plugin::instance()->should_organize_menu() );
	}

	/**
	 * The menu is never touched for a logged-out visitor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_does_not_organize_for_a_logged_out_user(): void {
		wp_set_current_user( 0 );
		set_current_screen( 'dashboard' );

		$this->assertFalse( Plugin::instance()->should_organize_menu() );

		set_current_screen( 'front' );
	}

	/**
	 * An ordinary logged-in user on an admin screen is organized normally.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_organizes_for_a_logged_in_user_in_the_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		set_current_screen( 'dashboard' );

		$this->assertTrue( Plugin::instance()->should_organize_menu() );

		set_current_screen( 'front' );
	}

	/**
	 * Activation records the installed version so migrations have a floor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_activation_records_the_installed_version(): void {
		delete_option( Plugin::OPTION_VERSION );

		Activator::activate();

		$this->assertSame( WPAMO_VERSION, get_option( Plugin::OPTION_VERSION ) );
	}

	/**
	 * Activation does not clobber a version already recorded by an older install.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_activation_preserves_an_existing_version(): void {
		update_option( Plugin::OPTION_VERSION, '0.9.0' );

		Activator::activate();

		$this->assertSame( '0.9.0', get_option( Plugin::OPTION_VERSION ) );
	}

	/**
	 * Deactivation leaves the saved arrangement alone.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_deactivation_preserves_saved_data(): void {
		update_option( Plugin::OPTION_VERSION, WPAMO_VERSION );

		Activator::deactivate();

		$this->assertSame( WPAMO_VERSION, get_option( Plugin::OPTION_VERSION ) );
	}
}
