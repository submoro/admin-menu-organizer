<?php
/**
 * Unit tests asserting the plugin's version is stated consistently.
 *
 * WordPress.org treats the plugin header's Version and the readme's Stable tag
 * as separate sources of truth, and a mismatch between them is one of the most
 * common ways a release goes wrong: the directory serves whichever the stable
 * tag points at, regardless of what the header claims. WPAMO_VERSION is a third
 * copy that the plugin's own migration chain depends on.
 *
 * These parse the files directly, so they need no WordPress at all.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * Covers agreement between the three places the version is written down.
 *
 * @since 1.0.0
 */
final class Test_Version_Consistency extends TestCase {

	/**
	 * Absolute path to the plugin root, with a trailing slash.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $root;

	/**
	 * Resolves the plugin root before each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->root = dirname( __DIR__, 2 ) . '/';
	}

	/**
	 * Reads a file from the plugin root.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string
	 */
	private function read( string $relative ): string {
		$path = $this->root . $relative;

		$this->assertFileExists( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in a test that runs without WordPress loaded.
		$contents = file_get_contents( $path );

		$this->assertIsString( $contents, "Could not read {$relative}." );

		return $contents;
	}

	/**
	 * Extracts the first capture of a pattern, failing the test if absent.
	 *
	 * @since 1.0.0
	 *
	 * @param string $pattern  Regular expression with one capturing group.
	 * @param string $subject  Text to search.
	 * @param string $what_for Human-readable description used in the failure message.
	 * @return string
	 */
	private function capture( string $pattern, string $subject, string $what_for ): string {
		$this->assertSame(
			1,
			preg_match( $pattern, $subject, $matches ),
			"Could not find {$what_for}."
		);

		return trim( $matches[1] );
	}

	/**
	 * The plugin header, the WPAMO_VERSION constant and the readme's stable tag
	 * all state the same version.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_all_three_version_declarations_agree(): void {
		$plugin_file = $this->read( 'wp-admin-menu-organizer.php' );
		$readme      = $this->read( 'readme.txt' );

		$header   = $this->capture( '/^\s*\*\s*Version:\s*(.+)$/m', $plugin_file, 'the Version plugin header' );
		$constant = $this->capture( "/define\(\s*'WPAMO_VERSION',\s*'([^']+)'/", $plugin_file, 'the WPAMO_VERSION constant' );
		$stable   = $this->capture( '/^Stable tag:\s*(.+)$/m', $readme, 'the readme stable tag' );

		$this->assertSame( $header, $constant, 'Plugin header Version and WPAMO_VERSION disagree.' );
		$this->assertSame( $header, $stable, 'Plugin header Version and readme Stable tag disagree.' );
	}

	/**
	 * The version is a plain three-part number, which is what the directory's
	 * version comparison expects.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_version_is_semantic(): void {
		$plugin_file = $this->read( 'wp-admin-menu-organizer.php' );
		$header      = $this->capture( '/^\s*\*\s*Version:\s*(.+)$/m', $plugin_file, 'the Version plugin header' );

		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $header );
	}

	/**
	 * The minimum PHP and WordPress versions are stated identically in the
	 * plugin header and the readme.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_minimum_requirements_agree_across_header_and_readme(): void {
		$plugin_file = $this->read( 'wp-admin-menu-organizer.php' );
		$readme      = $this->read( 'readme.txt' );

		$header_php = $this->capture( '/^\s*\*\s*Requires PHP:\s*(.+)$/m', $plugin_file, 'the Requires PHP header' );
		$readme_php = $this->capture( '/^Requires PHP:\s*(.+)$/m', $readme, 'the readme Requires PHP field' );
		$this->assertSame( $header_php, $readme_php );

		$header_wp = $this->capture( '/^\s*\*\s*Requires at least:\s*(.+)$/m', $plugin_file, 'the Requires at least header' );
		$readme_wp = $this->capture( '/^Requires at least:\s*(.+)$/m', $readme, 'the readme Requires at least field' );
		$this->assertSame( $header_wp, $readme_wp );
	}

	/**
	 * The WPAMO_MIN_PHP and WPAMO_MIN_WP constants match the declared headers,
	 * so the runtime guard cannot drift from what WordPress enforces at install.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_runtime_guard_constants_match_the_headers(): void {
		$plugin_file = $this->read( 'wp-admin-menu-organizer.php' );

		$header_php   = $this->capture( '/^\s*\*\s*Requires PHP:\s*(.+)$/m', $plugin_file, 'the Requires PHP header' );
		$constant_php = $this->capture( "/define\(\s*'WPAMO_MIN_PHP',\s*'([^']+)'/", $plugin_file, 'the WPAMO_MIN_PHP constant' );
		$this->assertSame( $header_php, $constant_php );

		$header_wp   = $this->capture( '/^\s*\*\s*Requires at least:\s*(.+)$/m', $plugin_file, 'the Requires at least header' );
		$constant_wp = $this->capture( "/define\(\s*'WPAMO_MIN_WP',\s*'([^']+)'/", $plugin_file, 'the WPAMO_MIN_WP constant' );
		$this->assertSame( $header_wp, $constant_wp );
	}

	/**
	 * The text domain in the plugin header matches the plugin's directory slug,
	 * which WordPress.org requires.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_text_domain_matches_the_plugin_slug(): void {
		$plugin_file = $this->read( 'wp-admin-menu-organizer.php' );

		$domain = $this->capture( '/^\s*\*\s*Text Domain:\s*(.+)$/m', $plugin_file, 'the Text Domain header' );
		$slug   = basename( rtrim( $this->root, '/' ) );

		$this->assertSame( $slug, $domain );
		$this->assertSame( 'wp-admin-menu-organizer', $domain );
	}

	/**
	 * The changelog documents the version being shipped.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_changelog_documents_the_current_version(): void {
		$readme = $this->read( 'readme.txt' );
		$stable = $this->capture( '/^Stable tag:\s*(.+)$/m', $readme, 'the readme stable tag' );

		$this->assertMatchesRegularExpression(
			'/^== Changelog ==$/m',
			$readme
		);
		$this->assertStringContainsString(
			'= ' . $stable . ' =',
			$readme,
			"The changelog has no entry for version {$stable}."
		);
	}
}
