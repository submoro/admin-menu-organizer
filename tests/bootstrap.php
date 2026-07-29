<?php
/**
 * PHPUnit bootstrap for the WordPress test library.
 *
 * Resolves the location of the WordPress test suite, loads the Yoast PHPUnit
 * polyfills so the same tests run on PHP 7.4 through 8.3, then loads this
 * plugin into the test instance before WordPress finishes booting.
 *
 * @package AdminMenuOrganizer
 * @since   1.0.0
 */

declare( strict_types=1 );

$amorg_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $amorg_autoload ) ) {
	echo 'Composer dependencies are not installed. Run "composer install" first.' . PHP_EOL;
	exit( 1 );
}

require_once $amorg_autoload;

/*
 * WP_TESTS_DIR is set by wp-env and by the standard install-wp-tests.sh script.
 * Fall back to the conventional temp location so a plain local checkout works.
 */
$amorg_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $amorg_tests_dir ) {
	$amorg_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}

if ( ! $amorg_tests_dir ) {
	$amorg_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$amorg_tests_dir = rtrim( $amorg_tests_dir, '/\\' );

if ( ! file_exists( $amorg_tests_dir . '/includes/functions.php' ) ) {
	printf(
		'Could not find the WordPress test library at %s.' . PHP_EOL
			. 'Set WP_TESTS_DIR, or run the suite through "wp-env run tests-cli".' . PHP_EOL,
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic, never rendered in a browser.
		$amorg_tests_dir
	);
	exit( 1 );
}

require_once $amorg_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin into the test instance.
 *
 * @since 1.0.0
 *
 * @return void
 */
function amorg_manually_load_plugin() {
	require dirname( __DIR__ ) . '/admin-menu-organizer.php';
}

tests_add_filter( 'muplugins_loaded', 'amorg_manually_load_plugin' );

require $amorg_tests_dir . '/includes/bootstrap.php';
