<?php
/**
 * PHPUnit bootstrap for the WordPress test library.
 *
 * Resolves the location of the WordPress test suite, loads the Yoast PHPUnit
 * polyfills so the same tests run on PHP 7.4 through 8.3, then loads this
 * plugin into the test instance before WordPress finishes booting.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 */

declare( strict_types=1 );

$mocam_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $mocam_autoload ) ) {
	echo 'Composer dependencies are not installed. Run "composer install" first.' . PHP_EOL;
	exit( 1 );
}

require_once $mocam_autoload;

/*
 * WP_TESTS_DIR is set by wp-env and by the standard install-wp-tests.sh script.
 * Fall back to the conventional temp location so a plain local checkout works.
 */
$mocam_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $mocam_tests_dir ) {
	$mocam_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}

if ( ! $mocam_tests_dir ) {
	$mocam_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$mocam_tests_dir = rtrim( $mocam_tests_dir, '/\\' );

if ( ! file_exists( $mocam_tests_dir . '/includes/functions.php' ) ) {
	printf(
		'Could not find the WordPress test library at %s.' . PHP_EOL
			. 'Set WP_TESTS_DIR, or run the suite through "wp-env run tests-cli".' . PHP_EOL,
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic, never rendered in a browser.
		$mocam_tests_dir
	);
	exit( 1 );
}

require_once $mocam_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin into the test instance.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mocam_manually_load_plugin() {
	require dirname( __DIR__ ) . '/menu-organizer-collapsible-admin-menu.php';
}

tests_add_filter( 'muplugins_loaded', 'mocam_manually_load_plugin' );

require $mocam_tests_dir . '/includes/bootstrap.php';
