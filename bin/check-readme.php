<?php
/**
 * Local readme.txt sanity check against the WordPress.org readme standard.
 */

$path   = $argv[1];
$readme = file_get_contents( $path );
$fail   = 0;

function check( $label, $ok, $detail = '' ) {
	global $fail;
	if ( ! $ok ) {
		$fail++;
	}
	printf( "%-46s %s%s\n", $label, $ok ? 'PASS' : 'FAIL', $detail ? "  ($detail)" : '' );
}

preg_match( '/^=== (.+) ===$/m', $readme, $m );
check( 'Plugin name header', ! empty( $m[1] ), $m[1] ?? '' );

foreach ( array( 'Contributors', 'Tags', 'Requires at least', 'Tested up to', 'Requires PHP', 'Stable tag', 'License', 'License URI' ) as $field ) {
	$found = preg_match( '/^' . preg_quote( $field, '/' ) . ':\s*(.*)$/m', $readme, $fm );
	check( "Header: $field", (bool) $found, $found ? trim( $fm[1] ) : 'missing' );
}

preg_match( '/^Tags:\s*(.*)$/m', $readme, $tm );
$tags = array_filter( array_map( 'trim', explode( ',', $tm[1] ?? '' ) ) );
check( 'Tags count <= 5', count( $tags ) <= 5, count( $tags ) . ' tags' );

/*
 * "Tested up to" takes a major version only. Plugin Check rejects a patch
 * number here: "Tested up to: 7.0.2 The version number should only include
 * major versions 7.0."
 */
preg_match( '/^Tested up to:\s*(.*)$/m', $readme, $tu );
$tested = trim( $tu[1] ?? '' );
check(
	'Tested up to is a major version only',
	1 === preg_match( '/^\d+\.\d+$/', $tested ),
	$tested
);

/*
 * WordPress.org forbids these terms outright in the plugin name and slug, for
 * new submissions, regardless of what older grandfathered plugins do.
 */
preg_match( '/^=== (.+) ===$/m', $readme, $nm );
$plugin_name = strtolower( $nm[1] ?? '' );
$restricted  = array( 'wordpress', 'wp', 'plugin', 'woocommerce' );
$found       = array();

foreach ( $restricted as $term ) {
	if ( preg_match( '/\b' . preg_quote( $term, '/' ) . '\b/', $plugin_name ) ) {
		$found[] = $term;
	}
}

check(
	'Plugin name uses no restricted term',
	array() === $found,
	$found ? 'found: ' . implode( ', ', $found ) : $nm[1] ?? ''
);

// Short description: the first non-empty line after the header block.
if ( preg_match( '/^License URI:.*$\R+(.+?)$/m', $readme, $sm ) ) {
	$len = strlen( trim( $sm[1] ) );
	check( 'Short description < 150 chars', $len < 150, "$len chars" );
} else {
	check( 'Short description present', false );
}

foreach ( array( 'Description', 'Installation', 'Frequently Asked Questions', 'Screenshots', 'Changelog', 'Upgrade Notice' ) as $section ) {
	check( "Section: $section", (bool) preg_match( '/^== ' . preg_quote( $section, '/' ) . ' ==$/m', $readme ) );
}

check( 'States no external requests', (bool) preg_match( '/no external HTTP requests/i', $readme ) );
check( 'States no data collected', (bool) preg_match( '/collects no data/i', $readme ) );

/*
 * Upgrade notices are capped at 300 characters by the directory, and anything
 * longer is silently truncated where users read it. Plugin Check reports this as
 * upgrade_notice_limit; it is checked here so it fails locally and in CI rather
 * than only after a build has been uploaded, which is how a 341-character 1.1.1
 * notice reached the directory's checker.
 */
$notice_limit = 300;

if ( preg_match( '/^== Upgrade Notice ==$(.*?)(?=^== |\z)/ms', $readme, $un ) ) {
	preg_match_all( '/^=\s*(.+?)\s*=\s*$(.*?)(?=^=\s|\z)/ms', $un[1], $notices, PREG_SET_ORDER );

	foreach ( $notices as $notice ) {
		$version = $notice[1];
		$length  = strlen( trim( $notice[2] ) );

		check(
			"Upgrade notice $version <= {$notice_limit} chars",
			$length <= $notice_limit,
			"$length chars"
		);
	}
}

// Screenshot captions must be numbered contiguously from 1.
preg_match( '/^== Screenshots ==$(.*?)^== /ms', $readme, $ss );
preg_match_all( '/^(\d+)\.\s+/m', $ss[1] ?? '', $nums );
$expected = range( 1, count( $nums[1] ) );
check( 'Screenshot captions numbered 1..n', array_map( 'intval', $nums[1] ) === $expected, count( $nums[1] ) . ' captions' );

echo "\n" . ( $fail ? "$fail CHECK(S) FAILED\n" : "readme.txt: all checks passed\n" );
exit( $fail ? 1 : 0 );
