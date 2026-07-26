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
	 * Reader over the live menu, built once the menu exists.
	 *
	 * @since 1.0.0
	 * @var Menu_Reader|null
	 */
	private $reader = null;

	/**
	 * The layout resolved for this request.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $layout = null;

	/**
	 * The renderer, once the menu has been built.
	 *
	 * @since 1.0.0
	 * @var Menu_Renderer|null
	 */
	private $renderer = null;

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

		/*
		 * Tier one: always registered, because these must answer on request types
		 * where the sidebar is never rendered. The REST routes in particular are
		 * reached by a REST request, which should_organize_menu() excludes.
		 */
		Capabilities::register();
		Rest_Controller::register();

		if ( is_admin() ) {
			Admin\Settings_Page::register();
		}

		if ( ! $this->should_organize_menu() ) {
			return;
		}

		// Tier two: menu decoration.
		add_filter( 'custom_menu_order', array( $this, 'enable_custom_menu_order' ), Menu_Order::PRIORITY );
		add_filter( 'menu_order', array( $this, 'filter_menu_order' ), Menu_Order::PRIORITY );

		/*
		 * The renderer is built on admin_menu at a late priority, because it needs
		 * the finished menu to know which groups have members. It registers its own
		 * hooks, all of which fire later still.
		 */
		add_action( 'admin_menu', array( $this, 'register_renderer' ), Menu_Order::PRIORITY );
	}

	/**
	 * Builds and registers the renderer once the menu is final.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_renderer(): void {
		$reader = $this->reader();

		if ( ! $reader->is_usable() ) {
			return;
		}

		$this->renderer = new Menu_Renderer(
			$reader,
			$this->layout(),
			Layout_Repository::collapsed( get_current_user_id() )
		);

		$this->renderer->register();
	}

	/**
	 * Returns the renderer, or null when the menu was not worth decorating.
	 *
	 * @since 1.0.0
	 *
	 * @return Menu_Renderer|null
	 */
	public function renderer(): ?Menu_Renderer {
		return $this->renderer;
	}

	/**
	 * Unlocks reordering by returning true from custom_menu_order.
	 *
	 * Preserves an upstream true rather than overriding it, so another plugin
	 * that has already unlocked reordering is not contradicted.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $enabled Whatever the previous filter returned.
	 * @return bool
	 */
	public function enable_custom_menu_order( $enabled ): bool {
		return $this->reader()->is_usable() ? true : (bool) $enabled;
	}

	/**
	 * Reorders the top-level menu so each group's members are contiguous.
	 *
	 * Returns the incoming array untouched on any doubt. That matters more than it
	 * looks: core runs array_flip() on this return value, which raises a fatal
	 * TypeError on PHP 8 if handed anything but an array, white-screening every
	 * admin page. See docs/core-notes.md section 4.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $menu_order Slug order as received, possibly already reordered.
	 * @return array Always an array.
	 */
	public function filter_menu_order( $menu_order ): array {
		if ( ! is_array( $menu_order ) ) {
			return array();
		}

		$reader = $this->reader();

		if ( ! $reader->is_usable() ) {
			return $menu_order;
		}

		$ordered = Menu_Order::order( $menu_order, $reader, $this->layout(), $this->detected() );

		// A last guard against ever shipping a shorter array than we received.
		return count( $ordered ) >= count( array_unique( $menu_order ) ) ? $ordered : $menu_order;
	}

	/**
	 * Returns the reader over the live menu, building it once per request.
	 *
	 * @since 1.0.0
	 *
	 * @return Menu_Reader
	 */
	public function reader(): Menu_Reader {
		if ( null === $this->reader ) {
			$this->reader = Menu_Reader::from_globals();
		}

		return $this->reader;
	}

	/**
	 * Returns the layout resolved for this request.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function layout(): array {
		if ( null === $this->layout ) {
			$this->layout = Layout_Repository::resolve( $this->reader() );
		}

		return $this->layout;
	}

	/**
	 * Returns detected group assignments for items the layout does not place.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function detected(): array {
		return Detector::create()->detect_all( $this->reader() );
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
