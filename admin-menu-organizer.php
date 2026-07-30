<?php
/**
 * Plugin Name:       Admin Menu Organizer
 * Plugin URI:        https://www.archinest.com/wp-plugins
 * Description:       Groups WordPress admin menu items into named, collapsible categories. Auto-sorts known plugins and lets you rearrange everything by drag and drop.
 * Version:           1.1.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Moamen Elabd
 * Author URI:        https://www.archinest.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       admin-menu-organizer
 * Domain Path:       /languages
 *
 * @package AdminMenuOrganizer
 *
 * Admin Menu Organizer is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */

/*
 * NOTE ON SYNTAX: this bootstrap is deliberately written in PHP 5.6-compatible
 * syntax. It has to parse on unsupported versions in order to display the
 * "requires PHP 7.4" notice rather than causing a parse error. Everything under
 * includes/ is loaded only after the guard passes and targets PHP 7.4+.
 */

defined( 'ABSPATH' ) || exit;

define( 'AMORG_VERSION', '1.1.1' );
define( 'AMORG_MIN_PHP', '7.4' );
define( 'AMORG_MIN_WP', '6.4' );
define( 'AMORG_PLUGIN_FILE', __FILE__ );
define( 'AMORG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AMORG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AMORG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Checks whether the host meets the plugin's minimum PHP and WordPress versions.
 *
 * @since 1.0.0
 *
 * @return string Empty string when requirements are met, otherwise the name of
 *                the unmet requirement, either 'php' or 'wp'.
 */
function amorg_unmet_requirement() {
	if ( version_compare( PHP_VERSION, AMORG_MIN_PHP, '<' ) ) {
		return 'php';
	}

	if ( version_compare( get_bloginfo( 'version' ), AMORG_MIN_WP, '<' ) ) {
		return 'wp';
	}

	return '';
}

/**
 * Renders an admin notice explaining why the plugin did not load.
 *
 * @since 1.0.0
 *
 * @return void
 */
function amorg_render_requirement_notice() {
	$unmet = amorg_unmet_requirement();

	if ( '' === $unmet || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	if ( 'php' === $unmet ) {
		$message = sprintf(
			/* translators: 1: Required PHP version, 2: PHP version running on the server. */
			__( 'Menu Organizer requires PHP %1$s or newer. This site is running PHP %2$s, so the plugin has not been loaded.', 'admin-menu-organizer' ),
			AMORG_MIN_PHP,
			PHP_VERSION
		);
	} else {
		$message = sprintf(
			/* translators: 1: Required WordPress version, 2: WordPress version running on the site. */
			__( 'Menu Organizer requires WordPress %1$s or newer. This site is running WordPress %2$s, so the plugin has not been loaded.', 'admin-menu-organizer' ),
			AMORG_MIN_WP,
			get_bloginfo( 'version' )
		);
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html( $message )
	);
}

/**
 * Registers the PSR-4-style autoloader for the AMORG namespace.
 *
 * Maps namespaced class names onto the WordPress file-naming convention, so
 * AMORG\Menu_Reader resolves to includes/class-menu-reader.php and
 * AMORG\Admin\Settings_Page resolves to includes/admin/class-settings-page.php.
 *
 * @since 1.0.0
 *
 * @param string $class_name Fully qualified class name being autoloaded.
 * @return void
 */
function amorg_autoload( $class_name ) {
	if ( 0 !== strpos( $class_name, 'AMORG\\' ) ) {
		return;
	}

	$relative = substr( $class_name, strlen( 'AMORG\\' ) );
	$parts    = explode( '\\', $relative );
	$class    = array_pop( $parts );

	$directory = '';
	foreach ( $parts as $part ) {
		$directory .= strtolower( str_replace( '_', '-', $part ) ) . '/';
	}

	$file = AMORG_PLUGIN_DIR . 'includes/' . $directory . 'class-'
		. strtolower( str_replace( '_', '-', $class ) ) . '.php';

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}

spl_autoload_register( 'amorg_autoload' );

/**
 * Boots the plugin once all other plugins have loaded.
 *
 * @since 1.0.0
 *
 * @return void
 */
function amorg_bootstrap() {
	if ( '' !== amorg_unmet_requirement() ) {
		add_action( 'admin_notices', 'amorg_render_requirement_notice' );
		return;
	}

	AMORG\Plugin::instance()->boot();
}

add_action( 'plugins_loaded', 'amorg_bootstrap' );

register_activation_hook( __FILE__, array( 'AMORG\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AMORG\Activator', 'deactivate' ) );
