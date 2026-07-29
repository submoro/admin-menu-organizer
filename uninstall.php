<?php
/**
 * Uninstall routine.
 *
 * Removes every option and every user meta key the plugin has ever written, so
 * that uninstalling leaves no residue. Runs only when WordPress itself deletes
 * the plugin.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Load the container purely for its key constants, so that the list of things
 * to delete has a single source of truth and cannot drift from the list of
 * things the plugin creates. This file defines a class and nothing else, so
 * requiring it boots nothing.
 */
require_once __DIR__ . '/includes/class-plugin.php';

/**
 * Deletes every option the plugin owns on the current site.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wpamo_uninstall_site() {
	foreach ( WPAMO\Plugin::ALL_OPTIONS as $option ) {
		delete_option( $option );
	}
}

/**
 * Deletes every user meta key the plugin owns, for every user.
 *
 * User meta is global on multisite, so this runs once rather than per site.
 * Uses the metadata API rather than a direct query, per the plugin's own rules.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wpamo_uninstall_user_meta() {
	foreach ( WPAMO\Plugin::ALL_USER_META as $meta_key ) {
		delete_metadata( 'user', 0, $meta_key, '', true );
	}
}

if ( is_multisite() ) {
	$wpamo_paged = 1;

	do {
		$wpamo_site_ids = get_sites(
			array(
				'fields'                 => 'ids',
				'number'                 => 100,
				'paged'                  => $wpamo_paged,
				'update_site_meta_cache' => false,
			)
		);

		$wpamo_batch_size = count( $wpamo_site_ids );

		foreach ( $wpamo_site_ids as $wpamo_site_id ) {
			switch_to_blog( (int) $wpamo_site_id );
			wpamo_uninstall_site();
			restore_current_blog();
		}

		++$wpamo_paged;
	} while ( 100 === $wpamo_batch_size );

	unset( $wpamo_paged, $wpamo_site_ids, $wpamo_site_id, $wpamo_batch_size );
} else {
	wpamo_uninstall_site();
}

wpamo_uninstall_user_meta();
