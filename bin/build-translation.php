<?php
/**
 * Builds a .po and a compiled .mo from the POT plus a translation map.
 *
 * Exists because neither WP-CLI nor gettext's msgfmt is available on the build
 * machine. The MO writer implements the format directly: a magic number, a
 * revision, a count, two offset tables, then the original and translation
 * blocks, all little-endian. GNU gettext documents it, and WordPress's own
 * MO reader in wp-includes/pomo/mo.php reads exactly this.
 *
 * Usage: php bin/build-translation.php <pot> <locale> <map.php> <out-dir> <domain>
 */

list( , $pot_path, $locale, $map_path, $out_dir, $domain ) = $argv;

$pot = file_get_contents( $pot_path );
$map = require $map_path;

/**
 * Parses msgid entries out of a POT file.
 *
 * @param string $pot POT contents.
 * @return string[] The msgids, in file order.
 */
function parse_msgids( string $pot ): array {
	$ids   = array();
	$lines = preg_split( '/\R/', $pot );
	$i     = 0;
	$count = count( $lines );

	while ( $i < $count ) {
		if ( 0 !== strpos( $lines[ $i ], 'msgid ' ) ) {
			++$i;
			continue;
		}

		$value = unquote( substr( $lines[ $i ], 6 ) );
		++$i;

		// Continuation lines.
		while ( $i < $count && preg_match( '/^"/', $lines[ $i ] ) ) {
			$value .= unquote( $lines[ $i ] );
			++$i;
		}

		if ( '' !== $value ) {
			$ids[] = $value;
		}
	}

	return $ids;
}

/**
 * Strips the surrounding quotes and unescapes a PO string literal.
 *
 * @param string $raw Raw literal including quotes.
 * @return string
 */
function unquote( string $raw ): string {
	$raw = trim( $raw );

	if ( '' === $raw || '"' !== $raw[0] ) {
		return '';
	}

	$raw = substr( $raw, 1, -1 );

	return str_replace(
		array( '\\n', '\\t', '\\"', '\\\\' ),
		array( "\n", "\t", '"', '\\' ),
		$raw
	);
}

/**
 * Escapes a string for a PO literal.
 *
 * @param string $value Raw value.
 * @return string
 */
function quote( string $value ): string {
	return str_replace(
		array( '\\', '"', "\n", "\t" ),
		array( '\\\\', '\\"', '\\n', '\\t' ),
		$value
	);
}

/**
 * Writes a binary MO file.
 *
 * @param string                $path         Destination path.
 * @param array<string, string> $translations Msgid to msgstr, non-empty only.
 * @param string                $headers      The MO header block.
 * @return void
 */
function write_mo( string $path, array $translations, string $headers ): void {
	// The header entry is the one with an empty msgid, and must sort first.
	$entries = array( '' => $headers ) + $translations;
	ksort( $entries );

	$count            = count( $entries );
	$originals        = '';
	$translated       = '';
	$original_table   = '';
	$translated_table = '';

	/*
	 * Layout: 7 words of header, the originals index, the translations index,
	 * then the string blocks.
	 *
	 * hash_addr must point at the end of the translations index even when there
	 * is no hash table, because WordPress validates the file by computing
	 * hash_addr - translations_lengths_addr and requiring it to equal total * 8.
	 * Leaving hash_addr at zero makes that difference negative and
	 * MO::import_from_reader() rejects the file outright. Core's own writer sets
	 * it the same way; see wp-includes/pomo/mo.php.
	 */
	$header_size               = 7 * 4;
	$table_size                = $count * 8;
	$originals_lengths_addr    = $header_size;
	$translations_lengths_addr = $originals_lengths_addr + $table_size;
	$hash_addr                 = $translations_lengths_addr + $table_size;
	$originals_start           = $hash_addr;

	$offset = 0;
	foreach ( $entries as $msgid => $msgstr ) {
		$original_table .= pack( 'VV', strlen( $msgid ), $originals_start + $offset );
		$originals      .= $msgid . "\0";
		$offset         += strlen( $msgid ) + 1;
	}

	$translated_start = $originals_start + strlen( $originals );

	$offset = 0;
	foreach ( $entries as $msgstr ) {
		$translated_table .= pack( 'VV', strlen( $msgstr ), $translated_start + $offset );
		$translated       .= $msgstr . "\0";
		$offset           += strlen( $msgstr ) + 1;
	}

	$mo = pack(
		'VVVVVVV',
		0x950412de,                 // Magic, little-endian.
		0,                          // Revision. WordPress supports 0 only.
		$count,
		$originals_lengths_addr,
		$translations_lengths_addr,
		0,                          // Hash table length; none is written.
		$hash_addr
	);

	file_put_contents( $path, $mo . $original_table . $translated_table . $originals . $translated );
}

$msgids   = parse_msgids( $pot );
$done     = array();
$missing  = array();

foreach ( $msgids as $msgid ) {
	if ( isset( $map[ $msgid ] ) && '' !== $map[ $msgid ] ) {
		$done[ $msgid ] = $map[ $msgid ];
	} else {
		$missing[] = $msgid;
	}
}

$headers = "Project-Id-Version: WP Admin Menu Organizer 1.0.0\n"
	. "MIME-Version: 1.0\n"
	. "Content-Type: text/plain; charset=UTF-8\n"
	. "Content-Transfer-Encoding: 8bit\n"
	. "Language: {$locale}\n"
	. "Plural-Forms: nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);\n"
	. "X-Generator: bin/build-translation.php\n";

// The .po, for translators to continue from.
$po  = "# Arabic translation for WP Admin Menu Organizer.\n";
$po .= "# Copyright (C) 2026 Moamen Elabd\n";
$po .= "# This file is distributed under the GPLv2 or later.\n";
$po .= "msgid \"\"\nmsgstr \"\"\n";

foreach ( explode( "\n", trim( $headers ) ) as $line ) {
	$po .= '"' . quote( $line ) . '\n"' . "\n";
}

$po .= "\n";

foreach ( $msgids as $msgid ) {
	$po .= 'msgid "' . quote( $msgid ) . "\"\n";
	$po .= 'msgstr "' . quote( $done[ $msgid ] ?? '' ) . "\"\n\n";
}

$po_path = rtrim( $out_dir, '/\\' ) . "/{$domain}-{$locale}.po";
$mo_path = rtrim( $out_dir, '/\\' ) . "/{$domain}-{$locale}.mo";

file_put_contents( $po_path, $po );
write_mo( $mo_path, $done, $headers );

printf(
	"%s: %d of %d strings translated (%.0f%%)\n",
	$locale,
	count( $done ),
	count( $msgids ),
	count( $msgids ) ? ( count( $done ) / count( $msgids ) ) * 100 : 0
);
printf( "  %s (%d bytes)\n", basename( $po_path ), filesize( $po_path ) );
printf( "  %s (%d bytes)\n", basename( $mo_path ), filesize( $mo_path ) );

if ( $missing && in_array( '--verbose', $argv, true ) ) {
	echo "\nUntranslated:\n";
	foreach ( $missing as $m ) {
		echo '  ' . $m . "\n";
	}
}
