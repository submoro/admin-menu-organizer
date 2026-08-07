<?php
/**
 * The settings screen.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

namespace AMORG\Admin;

use AMORG\Capabilities;
use AMORG\Categories;
use AMORG\Detector;
use AMORG\Layout_Repository;
use AMORG\Layout_Sanitizer;
use AMORG\Menu_Reader;
use AMORG\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders Settings > Menu Categories.
 *
 * Four tabs, per SPEC section 7.1: the drag-and-drop layout editor, group
 * management, role presets, and an advanced tab holding reset, export, import and
 * the recovery instructions.
 *
 * Every write goes through a nonce check and a capability check, in that order,
 * and every value through Layout_Sanitizer. The capability checked depends on what
 * is being written: the site-wide layout, groups and role presets need
 * manage_options, while a personal layout needs only amorg_personalise_menu, so a
 * subscriber can arrange their own sidebar without being able to touch anyone
 * else's.
 *
 * @since 1.0.0
 */
final class Settings_Page {

	/**
	 * The screen's page slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SLUG = 'amorg';

	/**
	 * Nonce action for site-wide writes.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE_SITE = 'amorg_save_site';

	/**
	 * Nonce action for personal writes.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE_PERSONAL = 'amorg_save_personal';

	/**
	 * Registers the screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . AMORG_PLUGIN_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adds the screen under Settings.
	 *
	 * Visible to anyone who may either manage the site layout or personalise their
	 * own, so a subscriber granted personalisation has somewhere to do it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function add_page(): void {
		if ( ! Capabilities::current_user_can_manage() && ! Capabilities::current_user_can_personalise() ) {
			return;
		}

		/*
		 * The capability passed to add_options_page() is the lower of the two, so
		 * the page is reachable by a personalising subscriber. Each tab then
		 * re-checks what it actually needs, which is where the real gate is: a
		 * capability on the menu item alone would be trivially bypassed by
		 * visiting the URL directly.
		 */
		$capability = Capabilities::current_user_can_manage() ? Capabilities::MANAGE : Capabilities::PERSONALISE;

		add_options_page(
			__( 'Menu Categories', 'admin-menu-categories' ),
			__( 'Menu Categories', 'admin-menu-categories' ),
			$capability,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $links Existing action links.
	 * @return array
	 */
	public static function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();

		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ),
			esc_html__( 'Settings', 'admin-menu-categories' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Enqueues the editor assets, only on this screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ): void {
		if ( 'settings_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'amorg-settings',
			AMORG_PLUGIN_URL . 'assets/css/settings.css',
			array( 'dashicons' ),
			Plugin::asset_version( 'assets/css/settings.css' )
		);

		/*
		 * jquery-ui-sortable ships with WordPress. SPEC section 7.2 forbids
		 * bundling SortableJS or React, which is also a directory requirement: a
		 * plugin must not ship a copy of a library core already provides.
		 */
		wp_enqueue_script(
			'amorg-settings',
			AMORG_PLUGIN_URL . 'assets/js/settings.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-i18n' ),
			Plugin::asset_version( 'assets/js/settings.js' ),
			true
		);

		wp_set_script_translations(
			'amorg-settings',
			'admin-menu-categories',
			AMORG_PLUGIN_DIR . 'languages'
		);
	}

	/**
	 * Returns the tab currently being viewed.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection, drives no write.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		$available = array_keys( self::tabs() );

		return in_array( $tab, $available, true ) ? $tab : (string) reset( $available );
	}

	/**
	 * Returns the tabs available to the current user.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Tab slug to label.
	 */
	private static function tabs(): array {
		$tabs = array();

		if ( Capabilities::current_user_can_manage() ) {
			$tabs['layout']   = __( 'Layout', 'admin-menu-categories' );
			$tabs['groups']   = __( 'Groups', 'admin-menu-categories' );
			$tabs['roles']    = __( 'Roles', 'admin-menu-categories' );
			$tabs['advanced'] = __( 'Advanced', 'admin-menu-categories' );
		}

		if ( Capabilities::current_user_can_personalise() ) {
			$tabs['personal'] = __( 'Personalise my menu', 'admin-menu-categories' );
		}

		return $tabs;
	}

	/**
	 * Renders the screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! Capabilities::current_user_can_manage() && ! Capabilities::current_user_can_personalise() ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'admin-menu-categories' ), 403 );
		}

		$tab    = self::current_tab();
		$reader = Menu_Reader::from_globals();

		require AMORG_PLUGIN_DIR . 'includes/admin/views/page.php';
	}

	/**
	 * Handles every form submission from this screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_post(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The action is only used to route; each branch verifies its own nonce before doing anything.
		$action = isset( $_POST['amorg_action'] ) ? sanitize_key( wp_unslash( $_POST['amorg_action'] ) ) : '';

		if ( '' === $action ) {
			return;
		}

		$reader = Menu_Reader::from_globals();

		switch ( $action ) {
			case 'save_layout':
				self::require_site_nonce();
				Layout_Repository::save_site_layout( self::posted_layout(), $reader );
				self::redirect( 'layout', 'saved' );
				break;

			case 'save_groups':
				self::require_site_nonce();
				Layout_Repository::save_site_layout( self::posted_groups( $reader ), $reader );
				self::redirect( 'groups', 'saved' );
				break;

			case 'save_roles':
				self::require_site_nonce();
				self::save_roles( $reader );
				self::redirect( 'roles', 'saved' );
				break;

			case 'save_settings':
				self::require_site_nonce();
				self::save_settings();
				self::redirect( 'roles', 'saved' );
				break;

			case 'reset_layout':
				self::require_site_nonce();
				delete_option( Plugin::OPTION_LAYOUT );
				Layout_Repository::flush();
				self::redirect( 'advanced', 'reset' );
				break;

			case 'import_layout':
				self::require_site_nonce();
				self::redirect( 'advanced', self::import_layout( $reader ) ? 'imported' : 'import-failed' );
				break;

			case 'save_personal':
				self::require_personal_nonce();
				Layout_Repository::save_user_layout( get_current_user_id(), self::posted_layout(), $reader );
				self::redirect( 'personal', 'saved' );
				break;

			case 'reset_personal':
				self::require_personal_nonce();
				Layout_Repository::delete_user_layout( get_current_user_id() );
				self::redirect( 'personal', 'reset' );
				break;
		}
	}

	/**
	 * Verifies the nonce and capability for a site-wide write.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function require_site_nonce(): void {
		check_admin_referer( self::NONCE_SITE );

		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'admin-menu-categories' ), 403 );
		}
	}

	/**
	 * Verifies the nonce and capability for a personal write.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function require_personal_nonce(): void {
		check_admin_referer( self::NONCE_PERSONAL );

		if ( ! Capabilities::current_user_can_personalise() ) {
			wp_die( esc_html__( 'You are not allowed to personalise your menu.', 'admin-menu-categories' ), 403 );
		}
	}

	/**
	 * Reads a posted map of scalars, keys and values both sanitised.
	 *
	 * Everything this screen writes also passes through Layout_Sanitizer, which is
	 * the real whitelist. Sanitising here as well is deliberate belt and braces:
	 * SPEC section 8 asks for wp_unslash() followed by a type-specific sanitiser at
	 * the point of input, and satisfying that literally means a future change to a
	 * caller cannot accidentally hand raw request data to something that trusts it.
	 *
	 * Every caller verifies its nonce before reaching here, in
	 * require_site_nonce() or require_personal_nonce().
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Request key to read.
	 * @return array<string, string>
	 */
	private static function posted_map( string $key ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the caller; see docblock.
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Both key and value are sanitised in the loop immediately below.
		$raw   = wp_unslash( $_POST[ $key ] );
		$clean = array();

		foreach ( (array) $raw as $raw_key => $raw_value ) {
			if ( ! is_scalar( $raw_value ) ) {
				continue;
			}

			$clean_key = Layout_Sanitizer::sanitize_key( (string) $raw_key );

			if ( '' === $clean_key ) {
				continue;
			}

			$clean[ $clean_key ] = sanitize_text_field( (string) $raw_value );
		}

		return $clean;
	}

	/**
	 * Reads a posted scalar as sanitised text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Request key to read.
	 * @return string
	 */
	private static function posted_text( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the caller.
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the caller.
		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Reads a posted layout out of the assignment fields.
	 *
	 * The editor posts one hidden field per group holding a comma-separated slug
	 * list, which survives both dragging and the keyboard "Move to group" control
	 * without either needing to know about the other. Menu slugs legitimately
	 * contain query strings, so sanitize_text_field is the right sanitiser: it
	 * strips tags and control bytes while leaving ?, & and = intact.
	 *
	 * @since 1.0.0
	 *
	 * @return array Raw layout, for Layout_Sanitizer to validate.
	 */
	private static function posted_layout(): array {
		$groups = array();

		foreach ( self::posted_map( 'amorg_group_items' ) as $group_id => $slug_list ) {
			$groups[] = array(
				'id'    => $group_id,
				'items' => array_values( array_filter( array_map( 'trim', explode( ',', $slug_list ) ) ) ),
			);
		}

		return array(
			'schema' => 1,
			'groups' => $groups,
		);
	}

	/**
	 * Reads posted group settings, merging them over the current assignments.
	 *
	 * @since 1.0.0
	 *
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return array Raw layout, for Layout_Sanitizer to validate.
	 */
	private static function posted_groups( Menu_Reader $reader ): array {
		$current = Layout_Repository::site_layout( $reader );
		$items   = array();

		foreach ( (array) ( $current['groups'] ?? array() ) as $group ) {
			if ( isset( $group['id'] ) ) {
				$items[ $group['id'] ] = (array) ( $group['items'] ?? array() );
			}
		}

		$order  = self::posted_text( 'amorg_group_order' );
		$labels = self::posted_map( 'amorg_group_label' );
		$icons  = self::posted_map( 'amorg_group_icon' );
		$open   = self::posted_map( 'amorg_group_open' );

		$ids = array_values(
			array_filter(
				array_map(
					array( Layout_Sanitizer::class, 'sanitize_key' ),
					explode( ',', $order )
				)
			)
		);

		if ( empty( $ids ) ) {
			$ids = Categories::ids();
		}

		$groups = array();

		foreach ( $ids as $group_id ) {
			$groups[] = array(
				'id'           => $group_id,
				'label'        => $labels[ $group_id ] ?? '',
				'icon'         => $icons[ $group_id ] ?? '',
				'default_open' => ! empty( $open[ $group_id ] ),
				'items'        => $items[ $group_id ] ?? array(),
			);
		}

		return array(
			'schema' => 1,
			'groups' => $groups,
		);
	}

	/**
	 * Saves the role presets.
	 *
	 * @since 1.0.0
	 *
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return void
	 */
	private static function save_roles( Menu_Reader $reader ): void {
		foreach ( self::posted_map( 'amorg_role_source' ) as $role => $source ) {
			$source = Layout_Sanitizer::sanitize_key( $source );

			if ( '' === $role ) {
				continue;
			}

			if ( 'site' === $source ) {
				Layout_Repository::delete_role_layout( $role );
				continue;
			}

			if ( 'copy' === $source ) {
				// Snapshot the current site-wide layout into this role, giving the
				// administrator a starting point to diverge from.
				Layout_Repository::save_role_layout( $role, Layout_Repository::site_layout( $reader ), $reader );
			}
		}
	}

	/**
	 * Saves the plugin-wide settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function save_settings(): void {
		$settings = get_option( Plugin::OPTION_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verified the nonce.
		$settings['personalisation'] = isset( $_POST['amorg_personalisation'] );

		update_option( Plugin::OPTION_SETTINGS, $settings );
	}

	/**
	 * Imports a layout from pasted JSON.
	 *
	 * Decoded then handed straight to the sanitiser, so an import can no more
	 * introduce an invalid group or a stale slug than a form post can.
	 *
	 * @since 1.0.0
	 *
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return bool Whether anything usable was imported.
	 */
	private static function import_layout( Menu_Reader $reader ): bool {
		/*
		 * sanitize_textarea_field rather than sanitize_text_field, because the
		 * latter collapses newlines and the export is pretty-printed. Neither
		 * touches the braces, quotes or colons JSON needs.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the caller.
		$json = isset( $_POST['amorg_import'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the caller.
			? trim( sanitize_textarea_field( wp_unslash( $_POST['amorg_import'] ) ) )
			: '';

		if ( '' === $json ) {
			return false;
		}

		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			return false;
		}

		$saved = Layout_Repository::save_site_layout( $decoded, $reader );

		return ! empty( $saved['groups'] );
	}

	/**
	 * Redirects back to a tab with a status message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tab    Tab to return to.
	 * @param string $status Status keyword.
	 * @return void
	 */
	private static function redirect( string $tab, string $status ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::SLUG,
					'tab'          => $tab,
					'amorg-status' => $status,
				),
				admin_url( 'options-general.php' )
			)
		);

		exit;
	}

	/**
	 * Returns the notice text for a status keyword.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Status keyword from the query string.
	 * @return array{message: string, type: string}|null
	 */
	public static function notice_for( string $status ): ?array {
		$notices = array(
			'saved'         => array(
				'message' => __( 'Your changes have been saved.', 'admin-menu-categories' ),
				'type'    => 'success',
			),
			'reset'         => array(
				'message' => __( 'The layout has been reset to the automatically detected default.', 'admin-menu-categories' ),
				'type'    => 'success',
			),
			'imported'      => array(
				'message' => __( 'The layout was imported.', 'admin-menu-categories' ),
				'type'    => 'success',
			),
			'import-failed' => array(
				'message' => __( 'That layout could not be imported. Check that it is the JSON produced by the export button.', 'admin-menu-categories' ),
				'type'    => 'error',
			),
		);

		return $notices[ $status ] ?? null;
	}

	/**
	 * Returns the layout the editor should display for the current tab.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $tab    Current tab.
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return array
	 */
	public static function editing_layout( string $tab, Menu_Reader $reader ): array {
		if ( 'personal' === $tab ) {
			$personal = Layout_Repository::user_layout( get_current_user_id(), $reader );

			if ( ! empty( $personal['groups'] ) ) {
				return Layout_Repository::resolve( $reader );
			}
		}

		$site = Layout_Repository::site_layout( $reader );

		return empty( $site['groups'] ) ? Layout_Repository::auto_detected( $reader ) : $site;
	}

	/**
	 * Describes every organizable menu item, for the editor.
	 *
	 * @since 1.0.0
	 *
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return array<string, array{label: string, icon: string, detected: string}>
	 */
	public static function item_index( Menu_Reader $reader ): array {
		$detector = Detector::create();
		$index    = array();

		foreach ( $reader->organizable_slugs() as $slug ) {
			$item = $reader->get( $slug );

			if ( null === $item ) {
				continue;
			}

			$explained = $detector->explain( $item );

			$index[ $slug ] = array(
				'label'    => '' !== $item['plain_title'] ? $item['plain_title'] : $slug,
				'icon'     => $item['icon'],
				'detected' => $explained['group'],
				'layer'    => $explained['layer'],
				'evidence' => $explained['evidence'],
			);
		}

		return $index;
	}
}
