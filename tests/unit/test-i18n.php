<?php
/**
 * Unit tests for internationalisation.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * Covers the things that silently make a string untranslatable.
 *
 * All three of these have bitten this plugin during development. The text domain
 * was once supplied inside a JavaScript helper rather than at each call site,
 * which meant `wp i18n make-pot` extracted none of the JavaScript strings and
 * every one of them would have shipped permanently untranslatable. Nothing about
 * the running plugin looked wrong, which is exactly why it needs a test.
 *
 * @since 1.0.0
 */
final class Test_I18n extends TestCase {

	/**
	 * The plugin's text domain.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const DOMAIN = 'menu-organizer-collapsible-admin-menu';

	/**
	 * Gettext function names that take a text domain.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const FUNCTIONS = array(
		'__',
		'_e',
		'esc_html__',
		'esc_html_e',
		'esc_attr__',
		'esc_attr_e',
		'_x',
		'esc_html_x',
		'esc_attr_x',
		'_n',
		'_nx',
	);

	/**
	 * Returns the plugin root with a trailing slash.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function root(): string {
		return dirname( __DIR__, 2 ) . '/';
	}

	/**
	 * Returns every shipped PHP and JS file.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Relative path to absolute path.
	 */
	private function shipped_files(): array {
		$root     = str_replace( '\\', '/', rtrim( $this->root(), '/' ) );
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);

		$files = array();

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

			$files[ ltrim( substr( $path, strlen( $root ) ), '/' ) ] = $path;
		}

		ksort( $files );

		return $files;
	}

	/**
	 * Reads a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	private function read( string $path ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in a test that runs without WordPress loaded.
		return (string) file_get_contents( $path );
	}

	/**
	 * Every gettext call in shipped code passes the text domain as a literal.
	 *
	 * String extraction is static: a call whose domain is a variable, a constant,
	 * or absent altogether is skipped by the extractor and its string never
	 * reaches the POT file.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_every_gettext_call_has_a_literal_text_domain(): void {
		$offenders = array();

		foreach ( $this->shipped_files() as $relative => $absolute ) {
			$source = $this->read( $absolute );

			foreach ( self::FUNCTIONS as $function ) {
				$pattern = '/(?<![\w$>])' . preg_quote( $function, '/' ) . '\s*\(\s*(.*?)\s*\)/s';

				if ( ! preg_match_all( $pattern, $source, $matches, PREG_OFFSET_CAPTURE ) ) {
					continue;
				}

				foreach ( $matches[1] as $index => $capture ) {
					$args = $capture[0];

					// The function definition and the delegating call inside it are
					// not translation call sites.
					if ( false !== strpos( $args, '$text' ) || false !== strpos( $args, 'text, domain' ) ) {
						continue;
					}

					if ( ! preg_match_all( "/(['\"])((?:\\\\.|(?!\\1).)*)\\1/s", $args, $strings ) ) {
						continue;
					}

					if ( in_array( self::DOMAIN, $strings[2], true ) ) {
						continue;
					}

					$line = substr_count( substr( $source, 0, $matches[0][ $index ][1] ), "\n" ) + 1;

					$offenders[] = sprintf( '%s:%d %s(%s)', $relative, $line, $function, substr( $args, 0, 50 ) );
				}
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			"These calls would not be extracted into the POT file:\n  " . implode( "\n  ", $offenders )
		);
	}

	/**
	 * No gettext call builds its string by concatenation.
	 *
	 * SPEC section 9 forbids it, because a translator receiving half a sentence
	 * cannot reorder it for a language with different word order, and Arabic is
	 * one of them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_no_translated_string_is_concatenated(): void {
		$offenders = array();

		foreach ( $this->shipped_files() as $relative => $absolute ) {
			$source = $this->read( $absolute );

			// A quoted string followed by a concatenation operator, inside a call.
			if ( preg_match_all( '/(?:__|_e|esc_html__|esc_attr__)\s*\(\s*[\'"][^\'"]*[\'"]\s*\./', $source, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[0] as $match ) {
					$line        = substr_count( substr( $source, 0, $match[1] ), "\n" ) + 1;
					$offenders[] = "{$relative}:{$line}";
				}
			}
		}

		$this->assertSame( array(), $offenders, 'Concatenated translation: ' . implode( ', ', $offenders ) );
	}

	/**
	 * The POT file exists, is current, and declares the right domain.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_pot_file_is_present_and_well_formed(): void {
		$path = $this->root() . 'languages/' . self::DOMAIN . '.pot';

		$this->assertFileExists( $path, 'The POT file has not been generated.' );

		$pot = $this->read( $path );

		$this->assertStringContainsString( 'X-Domain: ' . self::DOMAIN, $pot );
		$this->assertStringContainsString( 'Content-Type: text/plain; charset=UTF-8', $pot );
		$this->assertGreaterThan( 50, preg_match_all( '/^msgid "/m', $pot ), 'The POT file looks far too small.' );
	}

	/**
	 * The Arabic starter translation ships as both a .po and a compiled .mo.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_arabic_translation_ships(): void {
		$base = $this->root() . 'languages/' . self::DOMAIN . '-ar';

		$this->assertFileExists( $base . '.po' );
		$this->assertFileExists( $base . '.mo' );
	}

	/**
	 * The compiled .mo has a structurally valid header.
	 *
	 * Checked byte by byte against the format WordPress's own reader validates.
	 * The two length checks matter most: WordPress computes each index table's
	 * size by subtracting addresses and refuses the file unless both come to
	 * total * 8. An hash_addr left at zero makes one of them negative, which is
	 * how the first build of this file silently failed to load.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compiled_mo_header_is_valid(): void {
		$mo = $this->read( $this->root() . 'languages/' . self::DOMAIN . '-ar.mo' );

		$this->assertGreaterThan( 28, strlen( $mo ), 'The .mo is too short to hold a header.' );

		$header = unpack(
			'Vmagic/Vrevision/Vtotal/Voriginals_addr/Vtranslations_addr/Vhash_length/Vhash_addr',
			substr( $mo, 0, 28 )
		);

		$this->assertIsArray( $header );
		$this->assertSame( 0x950412de, $header['magic'], 'Wrong magic number.' );
		$this->assertSame( 0, $header['revision'], 'WordPress supports MO revision 0 only.' );
		$this->assertGreaterThan( 0, $header['total'] );
		$this->assertSame( 28, $header['originals_addr'] );

		$this->assertSame(
			$header['total'] * 8,
			$header['translations_addr'] - $header['originals_addr'],
			'The originals index is not total * 8 bytes; WordPress would reject this file.'
		);

		$this->assertSame(
			$header['total'] * 8,
			$header['hash_addr'] - $header['translations_addr'],
			'The translations index is not total * 8 bytes; WordPress would reject this file.'
		);
	}

	/**
	 * Every translation in the .po is valid UTF-8 and the Arabic entries really
	 * contain Arabic, so the RTL pass is exercising real text.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_arabic_translations_are_real_arabic(): void {
		$po = $this->read( $this->root() . 'languages/' . self::DOMAIN . '-ar.po' );

		$this->assertTrue( mb_check_encoding( $po, 'UTF-8' ), 'The .po is not valid UTF-8.' );

		preg_match_all( '/^msgstr "(.+)"$/m', $po, $matches );

		$arabic = 0;

		foreach ( $matches[1] as $translation ) {
			if ( preg_match( '/\p{Arabic}/u', $translation ) ) {
				++$arabic;
			}
		}

		$this->assertGreaterThan(
			50,
			$arabic,
			'The Arabic translation has too few Arabic strings to be a useful RTL test.'
		);
	}

	/**
	 * Translator comments accompany every string containing a placeholder.
	 *
	 * A translator seeing "%1$s of %2$d" with no explanation cannot know what
	 * either stands for, and WordPress.org's own review flags it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_placeholder_strings_have_translator_comments(): void {
		$pot     = $this->read( $this->root() . 'languages/' . self::DOMAIN . '.pot' );
		$blocks  = preg_split( '/\n\n+/', $pot );
		$missing = array();

		foreach ( (array) $blocks as $block ) {
			if ( ! preg_match( '/^msgid "(.*)"$/m', $block, $match ) ) {
				continue;
			}

			$msgid = $match[1];

			if ( '' === $msgid || ! preg_match( '/%[0-9]*\$?[sd]/', $msgid ) ) {
				continue;
			}

			if ( false === strpos( $block, '#. translators:' ) ) {
				$missing[] = $msgid;
			}
		}

		$this->assertSame(
			array(),
			$missing,
			"Placeholder strings without a translators comment:\n  " . implode( "\n  ", $missing )
		);
	}
}
