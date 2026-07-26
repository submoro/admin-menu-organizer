<?php
/**
 * PHPUnit bootstrap for the unit suite.
 *
 * The unit suite covers logic that has no WordPress dependency, so it needs no
 * database, no test library and no WordPress install. It runs anywhere PHP and
 * Composer are available, which means it can gate every phase locally rather
 * than only in CI.
 *
 * Anything that reads an option, writes user meta, or calls a WordPress
 * function belongs in tests/integration/ instead.
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

if ( ! defined( 'MOCAM_PLUGIN_DIR' ) ) {
	define( 'MOCAM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
