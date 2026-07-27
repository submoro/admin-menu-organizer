<?php
/**
 * Minimal POT generator.
 *
 * WP-CLI is not installed on this machine and cannot be, so `wp i18n make-pot`
 * is unavailable. This extracts the same calls WP-CLI would: the gettext family
 * with a literal text domain, plus translator comments and JS strings.
 *
 * It is a build tool, not a replacement for WP-CLI. The generated POT is checked
 * against the source by tests/unit/test-i18n.php, which is what actually
 * guarantees no string was missed.
 */

$root   = $argv[1];
$domain = $argv[2];
$out    = $argv[3];

$functions = array(
	'__'          => array( 1, null ),
	'_e'          => array( 1, null ),
	'esc_html__'  => array( 1, null ),
	'esc_html_e'  => array( 1, null ),
	'esc_attr__'  => array( 1, null ),
	'esc_attr_e'  => array( 1, null ),
	'_x'          => array( 1, null ),
	'esc_html_x'  => array( 1, null ),
	'esc_attr_x'  => array( 1, null ),
	'_n'          => array( 1, 2 ),
	'_nx'         => array( 1, 2 ),
);

$entries = array();
$skipped = array();

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	$path = str_replace( '\\', '/', (string) $file );
	$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	if ( ! in_array( $ext, array( 'php', 'js' ), true ) ) {
		continue;
	}

	foreach ( array( '/vendor/', '/node_modules/', '/tests/', '/bin/', '/docs/', '/build/' ) as $exclude ) {
		if ( false !== strpos( $path, $exclude ) ) {
			continue 2;
		}
	}

	$source   = file_get_contents( $path );
	$relative = ltrim( substr( $path, strlen( str_replace( '\\', '/', $root ) ) ), '/' );
	$lines    = explode( "\n", $source );

	foreach ( $functions as $fn => $forms ) {
		// Match fn( 'single'[, 'plural'][, 'context'], 'domain' )
		$pattern = '/(?<![\w$>])' . preg_quote( $fn, '/' ) . '\s*\(\s*(.*?)\s*\)/s';

		if ( ! preg_match_all( $pattern, $source, $matches, PREG_OFFSET_CAPTURE ) ) {
			continue;
		}

		foreach ( $matches[1] as $index => $capture ) {
			$args   = $capture[0];
			$offset = $matches[0][ $index ][1];
			$line   = substr_count( substr( $source, 0, $offset ), "\n" ) + 1;

			// Pull the quoted string literals out of the argument list.
			if ( ! preg_match_all( "/(['\"])((?:\\\\.|(?!\\1).)*)\\1/s", $args, $strings ) ) {
				continue;
			}

			$values = $strings[2];

			// The domain must be present and literal, else WP-CLI would skip it too.
			if ( ! in_array( $domain, $values, true ) ) {
				$skipped[] = "$relative:$line  $fn(" . substr( $args, 0, 60 ) . ')';
				continue;
			}

			$singular = $values[0];
			$plural   = ( null !== $forms[1] && isset( $values[1] ) ) ? $values[1] : null;

			$key = $singular . "\0" . (string) $plural;

			if ( ! isset( $entries[ $key ] ) ) {
				$entries[ $key ] = array(
					'singular'  => $singular,
					'plural'    => $plural,
					'refs'      => array(),
					'comment'   => '',
				);
			}

			$entries[ $key ]['refs'][] = "$relative:$line";

			// Translator comment on the preceding lines.
			for ( $back = $line - 2; $back >= max( 0, $line - 5 ); $back-- ) {
				if ( ! isset( $lines[ $back ] ) ) {
					continue;
				}

				if ( preg_match( '#translators:\s*(.+?)\s*(\*/)?$#i', $lines[ $back ], $cm ) ) {
					$entries[ $key ]['comment'] = trim( $cm[1] );
					break;
				}
			}
		}
	}
}

ksort( $entries );

$pot  = "# Copyright (C) 2026 Moamen Elabd\n";
$pot .= "# This file is distributed under the GPLv2 or later.\n";
$pot .= "msgid \"\"\nmsgstr \"\"\n";
$pot .= "\"Project-Id-Version: Menu Organizer - Collapsible Admin Menu 1.0.0\\n\"\n";
$pot .= "\"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/$domain/\\n\"\n";
$pot .= "\"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n\"\n";
$pot .= "\"Language-Team: LANGUAGE <LL@li.org>\\n\"\n";
$pot .= "\"MIME-Version: 1.0\\n\"\n";
$pot .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$pot .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$pot .= "\"POT-Creation-Date: 2026-07-27T00:00:00+00:00\\n\"\n";
$pot .= "\"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n\"\n";
$pot .= "\"X-Domain: $domain\\n\"\n\n";

foreach ( $entries as $entry ) {
	if ( '' !== $entry['comment'] ) {
		$pot .= '#. translators: ' . $entry['comment'] . "\n";
	}

	foreach ( array_unique( $entry['refs'] ) as $ref ) {
		$pot .= "#: $ref\n";
	}

	$pot .= 'msgid "' . addcslashes( $entry['singular'], "\"\\" ) . "\"\n";

	if ( null !== $entry['plural'] ) {
		$pot .= 'msgid_plural "' . addcslashes( $entry['plural'], "\"\\" ) . "\"\n";
		$pot .= "msgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
	} else {
		$pot .= "msgstr \"\"\n\n";
	}
}

file_put_contents( $out, $pot );

printf( "Wrote %d unique strings to %s\n", count( $entries ), $out );

if ( $skipped ) {
	echo "\nCalls with a missing or non-literal text domain (these would NOT be translatable):\n";
	foreach ( array_unique( $skipped ) as $s ) {
		echo "  $s\n";
	}
	exit( 1 );
}

echo "Every gettext call carries the literal text domain.\n";
