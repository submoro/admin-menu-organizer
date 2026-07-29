<?php
/**
 * Activation and deactivation routines.
 *
 * @package AdminMenuOrganizer
 * @since   1.0.0
 */

namespace AMORG;

defined( 'ABSPATH' ) || exit;

/**
 * Handles what happens when the plugin is switched on and off.
 *
 * Deactivation is intentionally almost empty. The plugin decorates the admin
 * menu entirely through runtime filters and writes nothing into the menu itself,
 * so removing those filters restores the stock sidebar exactly. Saved layouts
 * survive deactivation on purpose, so that reactivating does not discard an
 * administrator's arrangement. Everything is removed on uninstall instead.
 *
 * @since 1.0.0
 */
final class Activator {

	/**
	 * Runs when the plugin is activated.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $network_wide Whether the plugin was network-activated on multisite.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			self::activate_network();

			return;
		}

		self::activate_site();
	}

	/**
	 * Runs when the plugin is deactivated.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		/*
		 * Nothing to tear down. No scheduled events, no rewrite rules, no
		 * database tables, and no persistent change to the menu. Saved layouts
		 * are deliberately preserved; see the class docblock.
		 */
	}

	/**
	 * Seeds every site on a network activation.
	 *
	 * Large networks are skipped deliberately. Iterating tens of thousands of
	 * sites inside an activation request would time out, so those sites seed
	 * themselves on first admin load instead.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function activate_network(): void {
		if ( wp_is_large_network() ) {
			return;
		}

		$site_ids = get_sites(
			array(
				'fields'                 => 'ids',
				'number'                 => 0,
				'update_site_meta_cache' => false,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::activate_site();
			restore_current_blog();
		}
	}

	/**
	 * Seeds a single site.
	 *
	 * Seeding the initial auto-detected layout is deliberately not done here.
	 * The admin menu does not exist during activation, so there is nothing to
	 * detect against yet. The layout is seeded on the first admin page load,
	 * once $GLOBALS['menu'] is populated. This method only records the installed
	 * version so that the migration chain has a starting point.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function activate_site(): void {
		if ( ! get_option( Plugin::OPTION_VERSION ) ) {
			add_option( Plugin::OPTION_VERSION, AMORG_VERSION );
		}
	}
}
