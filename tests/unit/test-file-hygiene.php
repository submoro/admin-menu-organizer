<?php
/**
 * Unit tests guarding against file-level defects that break WordPress silently.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * Covers the classes of defect that produce output before headers are sent.
 *
 * A byte order mark ahead of the opening PHP tag, or any stray whitespace after
 * a closing tag, is emitted as body content. In WordPress that surfaces as
 * "Cannot modify header information - headers already sent", which breaks
 * redirects, cookies and the REST API, and is maddening to trace back to its
 * cause. It is also trivially avoidable, so it is worth a test.
 *
 * This is not hypothetical: a byte order mark was introduced into
 * includes/data/keyword-map.php during development by a tool that defaulted to
 * BOM-prefixed UTF-8, and the unit suite caught it. These tests exist so it is
 * caught deliberately next time.
 *
 * @since 1.0.0
 */
final class Test_File_Hygiene extends TestCase {

	/**
	 * Every PHP file that ships with or supports the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{0: string, 1: string}> Relative path and absolute path.
	 */
	public function php_files(): array {
		$root = dirname( __DIR__, 2 );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);

		$files = array();

		foreach ( $iterator as $file ) {
			$path = str_replace( '\\', '/', (string) $file );

			if ( 'php' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
				continue;
			}

			if ( false !== strpos( $path, '/vendor/' ) || false !== strpos( $path, '/node_modules/' ) ) {
				continue;
			}

			$relative = ltrim( substr( $path, strlen( str_replace( '\\', '/', $root ) ) ), '/' );

			$files[ $relative ] = array( $relative, $path );
		}

		ksort( $files );

		return $files;
	}

	/**
	 * No PHP file begins with a byte order mark.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider php_files
	 *
	 * @param string $relative Relative path, used in the failure message.
	 * @param string $absolute Absolute path to read.
	 * @return void
	 */
	public function test_no_byte_order_mark( string $relative, string $absolute ): void {
		$this->assertNotSame(
			"\xEF\xBB\xBF",
			$this->head( $absolute, 3 ),
			"{$relative} starts with a UTF-8 byte order mark, which WordPress will emit before headers."
		);
	}

	/**
	 * Reads the first bytes of a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $absolute Absolute path.
	 * @param int    $length   Number of bytes to read.
	 * @return string
	 */
	private function head( string $absolute, int $length ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in a test that runs without WordPress loaded.
		return (string) file_get_contents( $absolute, false, null, 0, $length );
	}

	/**
	 * Every PHP file opens with the PHP tag on its very first byte.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider php_files
	 *
	 * @param string $relative Relative path, used in the failure message.
	 * @param string $absolute Absolute path to read.
	 * @return void
	 */
	public function test_opens_with_the_php_tag( string $relative, string $absolute ): void {
		$this->assertSame(
			'<?php',
			$this->head( $absolute, 5 ),
			"{$relative} does not open with <?php on its first byte."
		);
	}

	/**
	 * No PHP file ends with a closing tag, so nothing can follow it.
	 *
	 * Omitting the closing tag is the WordPress convention precisely because it
	 * makes trailing whitespace impossible.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider php_files
	 *
	 * @param string $relative Relative path, used in the failure message.
	 * @param string $absolute Absolute path to read.
	 * @return void
	 */
	public function test_has_no_closing_tag( string $relative, string $absolute ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in a test that runs without WordPress loaded.
		$contents = (string) file_get_contents( $absolute );

		$this->assertNotSame(
			'?>',
			substr( rtrim( $contents ), -2 ),
			"{$relative} ends with a closing PHP tag; omit it so trailing whitespace cannot be emitted."
		);
	}

	/**
	 * Every shipped PHP file guards against direct access.
	 *
	 * SPEC section 8 requires this on every file. Test files are exempt, since
	 * they run under PHPUnit and never over HTTP, and bin/ is CLI-only tooling
	 * excluded from the distribution zip.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider php_files
	 *
	 * @param string $relative Relative path, used in the failure message.
	 * @param string $absolute Absolute path to read.
	 * @return void
	 */
	public function test_shipped_files_guard_direct_access( string $relative, string $absolute ): void {
		foreach ( array( 'tests/', 'bin/', 'docs/' ) as $exempt ) {
			if ( 0 === strpos( $relative, $exempt ) ) {
				$this->assertTrue( true, 'Not a shipped file.' );

				return;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in a test that runs without WordPress loaded.
		$contents = (string) file_get_contents( $absolute );

		// uninstall.php guards on WP_UNINSTALL_PLUGIN instead, which is stricter:
		// it runs only when WordPress itself is deleting the plugin.
		$guarded = false !== strpos( $contents, "defined( 'ABSPATH' ) || exit;" )
			|| false !== strpos( $contents, "defined( 'WP_UNINSTALL_PLUGIN' ) || exit;" );

		$this->assertTrue( $guarded, "{$relative} has no direct-access guard." );
	}

	/**
	 * The data files return an array and produce no output when required.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_data_files_return_arrays_silently(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
		}

		$root = dirname( __DIR__, 2 ) . '/includes/data/';

		foreach ( array( 'categories.php', 'known-slugs.php', 'keyword-map.php' ) as $file ) {
			ob_start();
			$data   = require $root . $file;
			$output = ob_get_clean();

			$this->assertSame( '', $output, "{$file} produced output when required." );
			$this->assertIsArray( $data, "{$file} did not return an array." );
			$this->assertNotEmpty( $data, "{$file} returned an empty array." );
		}
	}
}
