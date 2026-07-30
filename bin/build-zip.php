<?php
/**
 * Builds the submission zip from .distignore.
 *
 * Reads the same .distignore that `wp dist-archive` would, so the archive this
 * produces matches what WP-CLI would produce on a machine that has it.
 *
 * The zip's single top-level directory must be the plugin slug, because
 * WordPress installs a plugin into a directory of that name and the text domain,
 * the readme and the directory listing all key off it.
 *
 * Usage: php bin/build-zip.php [output-dir]
 */

if ( ! extension_loaded( 'zip' ) ) {
	echo "The zip extension is required.\n";
	exit( 1 );
}

$root = str_replace( '\\', '/', dirname( __DIR__ ) );
$dest = rtrim( $argv[1] ?? ( $root . '/build' ), '/\\' );

/*
 * The slug comes from the Text Domain header, not from the directory name.
 *
 * WordPress.org requires the text domain to equal the plugin slug, so that header
 * is the authoritative statement of what the slug is. Reading basename( $root )
 * instead tied the build to whatever the checkout happened to be called — which
 * broke the moment the plugin was renamed and the repository was not, and would
 * equally break for anyone who cloned into a differently named directory.
 *
 * Every file whose name carries the slug is found through this: the main plugin
 * file, the POT, and the zip's single top-level directory.
 */
$slug       = '';
$main_files = glob( $root . '/*.php' ) ?: array();

foreach ( $main_files as $candidate ) {
	$head = (string) file_get_contents( $candidate, false, null, 0, 8192 );

	if ( ! preg_match( '/^\s*\*\s*Plugin Name:\s*\S/m', $head ) ) {
		continue;
	}

	if ( preg_match( '/^\s*\*\s*Text Domain:\s*(.+)$/m', $head, $domain ) ) {
		$slug = trim( $domain[1] );
	}

	break;
}

if ( '' === $slug ) {
	echo "Could not read a Text Domain header from the main plugin file.\n";
	exit( 1 );
}

if ( ! is_file( "{$root}/{$slug}.php" ) ) {
	echo "Text Domain is '{$slug}' but {$slug}.php does not exist. "
		. "WordPress.org requires the main file, the text domain and the slug to agree.\n";
	exit( 1 );
}

if ( ! is_dir( $dest ) ) {
	mkdir( $dest, 0755, true );
}

/**
 * Reads .distignore into a list of patterns.
 *
 * @param string $path Path to .distignore.
 * @return string[]
 */
function read_distignore( string $path ): array {
	if ( ! is_readable( $path ) ) {
		return array();
	}

	$patterns = array();

	foreach ( file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$line = trim( $line );

		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			continue;
		}

		$patterns[] = rtrim( $line, '/' );
	}

	return $patterns;
}

/**
 * Whether a repo-relative path is excluded.
 *
 * Matches a pattern against any path segment as well as against the whole path,
 * which is how .distignore is understood: a bare "tests" excludes the directory
 * wherever it appears.
 *
 * @param string   $relative Repo-relative path with forward slashes.
 * @param string[] $patterns Patterns from .distignore.
 * @return bool
 */
function is_excluded( string $relative, array $patterns ): bool {
	$segments = explode( '/', $relative );

	foreach ( $patterns as $pattern ) {
		if ( $relative === $pattern || 0 === strpos( $relative, $pattern . '/' ) ) {
			return true;
		}

		foreach ( $segments as $segment ) {
			if ( $segment === $pattern || fnmatch( $pattern, $segment ) ) {
				return true;
			}
		}
	}

	return false;
}

$patterns = read_distignore( $root . '/.distignore' );
$version  = '0.0.0';

if ( preg_match( '/^\s*\*\s*Version:\s*(.+)$/m', (string) file_get_contents( $root . "/{$slug}.php" ), $m ) ) {
	$version = trim( $m[1] );
}

$zip_path = "{$dest}/{$slug}.{$version}.zip";

if ( file_exists( $zip_path ) ) {
	unlink( $zip_path );
}

$zip = new ZipArchive();

if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
	echo "Could not create {$zip_path}\n";
	exit( 1 );
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

$included = array();
$skipped  = 0;

foreach ( $iterator as $item ) {
	$path     = str_replace( '\\', '/', (string) $item );
	$relative = ltrim( substr( $path, strlen( $root ) ), '/' );

	if ( '' === $relative ) {
		continue;
	}

	if ( is_excluded( $relative, $patterns ) ) {
		++$skipped;
		continue;
	}

	if ( is_dir( $path ) ) {
		$zip->addEmptyDir( "{$slug}/{$relative}" );
		continue;
	}

	$zip->addFile( $path, "{$slug}/{$relative}" );
	$included[] = $relative;
}

$zip->close();

sort( $included );

printf( "Built %s\n", basename( $zip_path ) );
printf( "  %d files, %.1f KB\n", count( $included ), filesize( $zip_path ) / 1024 );
printf( "  %d paths excluded by .distignore\n\n", $skipped );

echo "Contents:\n";
foreach ( $included as $file ) {
	echo "  {$slug}/{$file}\n";
}

// Fail loudly if anything that must never ship has crept in.
$forbidden = array( 'vendor/', 'tests/', 'docs/', 'bin/', 'node_modules/', '.git', 'composer.json', 'phpcs.xml.dist', 'phpunit', 'SPEC.md', '.distignore', '.wordpress-org' );
$leaked    = array();

foreach ( $included as $file ) {
	foreach ( $forbidden as $needle ) {
		if ( 0 === strpos( $file, $needle ) ) {
			$leaked[] = $file;
		}
	}
}

// Anything the plugin cannot run without.
$required = array(
	"{$slug}.php",
	'uninstall.php',
	'readme.txt',
	'LICENSE',
	'includes/class-plugin.php',
	'assets/css/admin-menu.css',
	'assets/js/admin-menu.js',
	'languages/' . $slug . '.pot',
);

$missing = array_values( array_diff( $required, $included ) );

echo "\n";

if ( $leaked ) {
	echo "LEAKED into the zip:\n";
	foreach ( $leaked as $file ) {
		echo "  {$file}\n";
	}
}

if ( $missing ) {
	echo "MISSING from the zip:\n";
	foreach ( $missing as $file ) {
		echo "  {$file}\n";
	}
}

if ( $leaked || $missing ) {
	exit( 1 );
}

echo "Zip contents verified: nothing leaked, nothing missing.\n";
