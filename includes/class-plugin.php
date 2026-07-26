<?php
/**
 * Plugin container and hook registration.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 */

namespace MOCAM;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton container responsible for wiring the plugin's hooks.
 *
 * Holds no rendering or data logic of its own. Its job is to decide whether the
 * plugin should act on the current request at all, and to delegate to the
 * appropriate collaborators when it should.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Option holding the site-wide layout.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_LAYOUT = 'mocam_layout';

	/**
	 * Option holding the map of role slug to layout.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_ROLE_LAYOUTS = 'mocam_role_layouts';

	/**
	 * Option holding plugin-wide settings, such as the personalisation toggle.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_SETTINGS = 'mocam_settings';

	/**
	 * Option holding the installed plugin version, used to trigger migrations.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_VERSION = 'mocam_version';

	/**
	 * User meta holding a personal layout override.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_USER_LAYOUT = 'mocam_user_layout';

	/**
	 * User meta holding the list of group IDs the user has collapsed.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_COLLAPSED = 'mocam_collapsed';

	/**
	 * Every option key the plugin creates. Used by the uninstaller.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const ALL_OPTIONS = array(
		self::OPTION_LAYOUT,
		self::OPTION_ROLE_LAYOUTS,
		self::OPTION_SETTINGS,
		self::OPTION_VERSION,
	);

	/**
	 * Every user meta key the plugin creates. Used by the uninstaller.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const ALL_USER_META = array(
		self::META_USER_LAYOUT,
		self::META_COLLAPSED,
	);

	/**
	 * Sole instance of this class.
	 *
	 * @since 1.0.0
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() has already run.
	 *
	 * Guards against a double boot if another plugin fires plugins_loaded twice
	 * or calls our bootstrap directly.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Cached result of is_disabled(), which cannot change within a request.
	 *
	 * @since 1.0.0
	 * @var bool|null
	 */
	private $disabled = null;

	/**
	 * Private constructor. Use instance().
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Prevents cloning of the singleton.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Returns the sole instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the plugin's hooks.
	 *
	 * Hooks are registered in two tiers. The first tier is always registered,
	 * because it must respond on request types where the sidebar is never
	 * rendered, such as the REST call that persists collapsed state. The second
	 * tier touches the admin menu and is registered only when
	 * should_organize_menu() allows it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		if ( $this->is_disabled() ) {
			return;
		}

		// Tier one: always registered. Populated from Phase 7 onward.

		if ( ! $this->should_organize_menu() ) {
			return;
		}

		// Tier two: menu decoration. Populated from Phase 5 onward.
	}

	/**
	 * Determines whether the plugin has been switched off for this request.
	 *
	 * Two escape hatches are supported, both of which must keep working so that
	 * an administrator can always recover a broken sidebar:
	 *
	 * - The MOCAM_DISABLE constant, normally set in wp-config.php, disables the
	 *   plugin entirely.
	 * - The mocam=off query parameter disables it for a single request.
	 *
	 * The query parameter is read for its presence and value only. It is never
	 * persisted, never echoed, and drives no database write, so no nonce is
	 * required. It is deliberately available to any logged-in user, because a
	 * user locked out of their own sidebar needs it to reach the settings screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when the plugin should take no action at all.
	 */
	public function is_disabled(): bool {
		if ( null !== $this->disabled ) {
			return $this->disabled;
		}

		if ( defined( 'MOCAM_DISABLE' ) && MOCAM_DISABLE ) {
			$this->disabled = true;

			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only kill switch, see docblock.
		$off = isset( $_GET['mocam'] ) ? sanitize_key( wp_unslash( $_GET['mocam'] ) ) : '';

		$this->disabled = ( 'off' === $off );

		return $this->disabled;
	}

	/**
	 * Determines whether the admin menu should be organized on this request.
	 *
	 * Deliberately conservative. Any request type where the sidebar is not
	 * rendered, or where reordering it could interfere with another process, is
	 * excluded. Multisite network and user admin screens are left completely
	 * untouched in this version.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when it is safe to decorate the menu.
	 */
	public function should_organize_menu(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		if ( is_network_admin() || is_user_admin() ) {
			return false;
		}

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) {
			return false;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			return false;
		}

		return true;
	}
}
