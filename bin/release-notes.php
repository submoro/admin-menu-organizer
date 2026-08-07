<?php
/**
 * Extracts one version's changelog entry from readme.txt.
 *
 * The release workflow uses this so a GitHub release and the WordPress.org
 * changelog cannot describe the same version differently. readme.txt is the
 * source of truth, because that is the copy users actually read in the
 * directory, and it is already validated by bin/check-readme.php.
 *
 * Usage: php bin/release-notes.php <version> [readme-path]
 *
 * Exits non-zero when the version has no entry, so a tag pushed before the
 * changelog was written fails the release rather than publishing empty notes.
 *
 * @package AdminMenuCategories
 */

$version = $argv[1] ?? '';
$readme  = $argv[2] ?? ( dirname( __DIR__ ) . '/readme.txt' );

if ( '' === $version ) {
	fwrite( STDERR, "Usage: php bin/release-notes.php <version> [readme-path]\n" );
	exit( 1 );
}

if ( ! is_readable( $readme ) ) {
	fwrite( STDERR, "Cannot read {$readme}\n" );
	exit( 1 );
}

$lines = preg_split( '/\R/', (string) file_get_contents( $readme ) );

$in_changelog = false;
$in_version   = false;
$collected    = array();

foreach ( $lines as $line ) {
	$trimmed = trim( $line );

	// Section headings are == Name ==. Only the Changelog section is of
	// interest, and reaching any later one ends the search.
	if ( preg_match( '/^==\s*(.+?)\s*==$/', $trimmed, $section ) ) {
		if ( 0 === strcasecmp( 'changelog', $section[1] ) ) {
			$in_changelog = true;
			continue;
		}

		if ( $in_changelog ) {
			break;
		}

		continue;
	}

	if ( ! $in_changelog ) {
		continue;
	}

	// Version headings are = 1.2.3 =.
	if ( preg_match( '/^=\s*(.+?)\s*=$/', $trimmed, $heading ) ) {
		if ( $in_version ) {
			// The next version's heading: this entry is complete.
			break;
		}

		$in_version = ( $heading[1] === $version );
		continue;
	}

	if ( $in_version ) {
		$collected[] = $line;
	}
}

$body = trim( implode( "\n", $collected ) );

if ( '' === $body ) {
	fwrite( STDERR, "No changelog entry for version {$version} in {$readme}\n" );
	exit( 1 );
}

/*
 * readme.txt uses `* ` for list items, which is already valid Markdown, so the
 * body passes through unchanged apart from the heading added above it.
 */
echo "## What changed\n\n{$body}\n";
