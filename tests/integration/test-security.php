<?php
/**
 * Integration tests for the security guarantees that need a real WordPress.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 */

declare( strict_types=1 );

use MOCAM\Capabilities;
use MOCAM\Layout_Repository;
use MOCAM\Menu_Reader;
use MOCAM\Plugin;
use MOCAM\Rest_Controller;

/**
 * Covers the capability gates and the zero-outbound-requests guarantee.
 *
 * These need a running WordPress because they exercise real roles, real user
 * meta, and the real REST server. The unit suite covers everything that can be
 * proven without one.
 *
 * @since 1.0.0
 */
class Test_Security extends WP_UnitTestCase {

	/**
	 * Requests intercepted during a test.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $intercepted = array();

	/**
	 * Installs the outbound-request tripwire.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->intercepted = array();

		add_filter( 'pre_http_request', array( $this, 'intercept' ), 10, 3 );

		Layout_Repository::flush();
	}

	/**
	 * Records an attempted outbound request and blocks it.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $preempt Short-circuit value.
	 * @param array  $args    Request arguments.
	 * @param string $url     Requested URL.
	 * @return \WP_Error
	 */
	public function intercept( $preempt, $args, $url ) {
		$this->intercepted[] = $url;

		return new WP_Error( 'mocam_test_blocked', 'Blocked by the test tripwire.' );
	}

	/**
	 * SPEC section 15: zero outbound HTTP requests, verified by logging
	 * pre_http_request.
	 *
	 * Exercises the paths that run on a normal admin page load, plus the REST
	 * endpoints, and asserts nothing tried to leave the server.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_no_outbound_http_requests(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		$reader = new Menu_Reader(
			array(
				array( 'Dashboard', 'read', 'index.php', '', '', '', 'dashicons-dashboard' ),
				array( 'Posts', 'edit_posts', 'edit.php', '', '', '', 'dashicons-admin-post' ),
				array( 'Settings', 'manage_options', 'options-general.php', '', '', '', '' ),
			)
		);

		// The full resolution and detection pipeline.
		$layout = Layout_Repository::resolve( $reader );
		Layout_Repository::save_site_layout( $layout, $reader );
		Layout_Repository::auto_detected( $reader );
		Layout_Repository::collapsed( get_current_user_id() );
		Layout_Repository::save_collapsed( get_current_user_id(), array( 'content' ) );

		set_current_screen( 'front' );

		$this->assertSame(
			array(),
			$this->intercepted,
			'The plugin attempted an outbound request to: ' . implode( ', ', $this->intercepted )
		);
	}

	/**
	 * A subscriber cannot write the site-wide layout.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_subscriber_cannot_save_the_site_layout(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$result = Rest_Controller::can_save_site_layout();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'mocam_forbidden', $result->get_error_code() );
	}

	/**
	 * An editor cannot write the site-wide layout either.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_editor_cannot_save_the_site_layout(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertInstanceOf( WP_Error::class, Rest_Controller::can_save_site_layout() );
	}

	/**
	 * An administrator can.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_administrator_can_save_the_site_layout(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue( Rest_Controller::can_save_site_layout() );
	}

	/**
	 * A logged-out visitor cannot save collapsed state.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_logged_out_visitor_cannot_save_state(): void {
		wp_set_current_user( 0 );

		$this->assertInstanceOf( WP_Error::class, Rest_Controller::can_save_state() );
	}

	/**
	 * A subscriber can save their own collapsed state, which is legitimate: it is
	 * their own cosmetic preference.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_subscriber_can_save_their_own_state(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertTrue( Rest_Controller::can_save_state() );
	}

	/**
	 * Collapsed state is written against the acting user and never against a user
	 * ID taken from the request, so one user cannot rewrite another's.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_state_is_written_against_the_acting_user(): void {
		$victim   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$attacker = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		update_user_meta( $victim, Plugin::META_COLLAPSED, array( 'content' ) );

		wp_set_current_user( $attacker );

		$request = new WP_REST_Request( 'POST', '/mocam/v1/state' );
		$request->set_param( 'collapsed', array( 'commerce' ) );
		$request->set_param( 'user_id', $victim );

		Rest_Controller::save_state( $request );

		$this->assertSame(
			array( 'content' ),
			get_user_meta( $victim, Plugin::META_COLLAPSED, true ),
			"The victim's state was modified."
		);
		$this->assertSame(
			array( 'commerce' ),
			get_user_meta( $attacker, Plugin::META_COLLAPSED, true )
		);
	}

	/**
	 * Turning personalisation off denies the capability, whatever the role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_personalisation_switch_denies_the_capability(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		Capabilities::register();

		update_option( Plugin::OPTION_SETTINGS, array( 'personalisation' => true ) );
		$this->assertTrue( current_user_can( Capabilities::PERSONALISE ) );

		update_option( Plugin::OPTION_SETTINGS, array( 'personalisation' => false ) );
		$this->assertFalse( current_user_can( Capabilities::PERSONALISE ) );
	}

	/**
	 * The plugin adds no capability to any role, so uninstalling leaves no
	 * capability residue. SPEC section 4.5 makes it a meta capability for exactly
	 * this reason.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_no_real_capability_is_ever_stored(): void {
		$roles = wp_roles();

		foreach ( $roles->roles as $role_name => $role ) {
			$this->assertArrayNotHasKey(
				Capabilities::PERSONALISE,
				(array) $role['capabilities'],
				"Role {$role_name} has a stored mocam capability."
			);
		}
	}

	/**
	 * A stored layout naming a slug that is not in the live menu does not
	 * resurrect it. SPEC section 8.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_stale_slugs_do_not_survive_a_round_trip(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$reader = new Menu_Reader(
			array(
				array( 'Posts', 'edit_posts', 'edit.php', '', '', '', '' ),
				array( 'Settings', 'manage_options', 'options-general.php', '', '', '', '' ),
			)
		);

		$saved = Layout_Repository::save_site_layout(
			array(
				'schema' => 1,
				'groups' => array(
					array(
						'id'    => 'content',
						'items' => array( 'edit.php', 'a-plugin-that-was-deactivated' ),
					),
				),
			),
			$reader
		);

		$this->assertSame( array( 'edit.php' ), $saved['groups'][0]['items'] );
	}

	/**
	 * Uninstall removes every option and every user meta key the plugin writes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_uninstall_removes_everything(): void {
		$user = self::factory()->user->create();

		foreach ( Plugin::ALL_OPTIONS as $option ) {
			update_option( $option, array( 'x' => 1 ) );
		}

		foreach ( Plugin::ALL_USER_META as $meta_key ) {
			update_user_meta( $user, $meta_key, array( 'x' => 1 ) );
		}

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', MOCAM_PLUGIN_BASENAME );
		}

		require MOCAM_PLUGIN_DIR . 'uninstall.php';

		foreach ( Plugin::ALL_OPTIONS as $option ) {
			$this->assertFalse( get_option( $option ), "Option {$option} survived uninstall." );
		}

		foreach ( Plugin::ALL_USER_META as $meta_key ) {
			$this->assertSame(
				'',
				get_user_meta( $user, $meta_key, true ),
				"User meta {$meta_key} survived uninstall."
			);
		}
	}
}
