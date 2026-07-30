<?php
/**
 * Unit tests pinning the sidebar's label and hierarchy CSS.
 *
 * @package AdminMenuOrganizer
 * @since   1.1.1
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * Guards the group label rules, in both of the places they are written.
 *
 * Menu_Renderer::print_inline_styles() deliberately repeats a handful of
 * declarations that also live in assets/css/admin-menu.css, so that the
 * hierarchy survives a stale copy of the stylesheet. That duplication is a trap
 * as well as a safety net: the inline block is printed on admin_head, which is
 * after the enqueued stylesheet, so on a specificity tie the inline copy is the
 * one that wins. A correction made in the stylesheet alone is therefore silently
 * overridden by the stale value inline — which is exactly how
 * `overflow-wrap: anywhere` survived a release that had already been "fixed".
 *
 * These tests read both sources as text. That is deliberate: the point is to
 * catch the two drifting apart, which is a property of the sources rather than
 * of any rendered output, and it keeps the check in the WordPress-free suite
 * where it gates every local run.
 *
 * @since 1.1.1
 */
final class Test_Sidebar_CSS extends TestCase {

	/**
	 * The stylesheet, with comments removed and whitespace collapsed.
	 *
	 * @since 1.1.1
	 *
	 * @return string
	 */
	private function stylesheet(): string {
		$path = dirname( __DIR__, 2 ) . '/assets/css/admin-menu.css';

		$this->assertFileExists( $path, 'The sidebar stylesheet is missing.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in a test that runs without WordPress loaded.
		$css = (string) file_get_contents( $path );

		return self::normalize( self::strip_css_comments( $css ) );
	}

	/**
	 * The CSS that print_inline_styles() builds, recovered from the source.
	 *
	 * Only the static rules are recoverable this way, which is all that needs
	 * pinning: the per-group rules are built from stored labels and carry no
	 * declarations of their own.
	 *
	 * @since 1.1.1
	 *
	 * @return string
	 */
	private function inline_rules(): string {
		$path = dirname( __DIR__, 2 ) . '/includes/class-menu-renderer.php';

		$this->assertFileExists( $path, 'The renderer is missing.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in a test that runs without WordPress loaded.
		$source = (string) file_get_contents( $path );

		// Each rule is one `$rules[] = ...;` statement, possibly concatenated
		// across several lines.
		preg_match_all( '/\$rules\[\]\s*=\s*(.*?);\s*$/ms', $source, $statements );

		$this->assertNotEmpty( $statements[1], 'No $rules[] statements found in the renderer.' );

		$css = '';

		foreach ( $statements[1] as $statement ) {
			// Pull the single-quoted literals out of the concatenation and drop
			// any interpolated variable, which contributes no declarations.
			preg_match_all( "/'([^']*)'/", $statement, $literals );

			$css .= implode( '', $literals[1] );
		}

		return self::normalize( $css );
	}

	/**
	 * Removes CSS block comments.
	 *
	 * @since 1.1.1
	 *
	 * @param string $css Raw stylesheet.
	 * @return string
	 */
	private static function strip_css_comments( string $css ): string {
		return (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	}

	/**
	 * Collapses a CSS fragment to a whitespace-free, lower-case form.
	 *
	 * Comparing declarations by substring is only reliable once formatting is out
	 * of the way, since the same declaration is written one-per-line in the
	 * stylesheet and minified in the inline block.
	 *
	 * @since 1.1.1
	 *
	 * @param string $css CSS fragment.
	 * @return string
	 */
	private static function normalize( string $css ): string {
		return strtolower( (string) preg_replace( '/\s+/', '', $css ) );
	}

	/**
	 * The declarations that must appear in both sources, and agree.
	 *
	 * @since 1.1.1
	 *
	 * @return array<string, array{0: string}>
	 */
	public function label_declarations(): array {
		return array(
			// Breaks between words, and inside one only when it cannot fit alone.
			// `anywhere` breaks mid-character regardless, which is what turned
			// "Integrations" into "INTEGRATION" over a lone "S".
			'overflow-wrap' => array( 'overflow-wrap:break-word' ),

			// Core sets word-break: break-word and hyphens: auto on
			// div.wp-menu-name. Inheriting either would undo the above, so both
			// are stated rather than left to the default.
			'word-break'    => array( 'word-break:normal' ),
			'hyphens'       => array( 'hyphens:none' ),

			// Wrapping has no upper bound on its own, and an unbounded header row
			// pushes the rest of the menu down the page. Two lines is the cap.
			'line-clamp'    => array( 'line-clamp:2' ),

			// The clamp only takes effect on a -webkit-box, so the display type is
			// load-bearing rather than stylistic.
			'display'       => array( 'display:-webkit-box' ),
		);
	}

	/**
	 * Each label declaration is present in the stylesheet.
	 *
	 * @since 1.1.1
	 *
	 * @dataProvider label_declarations
	 *
	 * @param string $declaration Normalised declaration.
	 * @return void
	 */
	public function test_stylesheet_declares_label_wrapping( string $declaration ): void {
		$this->assertStringContainsString(
			$declaration,
			$this->stylesheet(),
			'assets/css/admin-menu.css must declare ' . $declaration . ' on the group label.'
		);
	}

	/**
	 * Each label declaration is repeated in the inline block.
	 *
	 * @since 1.1.1
	 *
	 * @dataProvider label_declarations
	 *
	 * @param string $declaration Normalised declaration.
	 * @return void
	 */
	public function test_inline_block_repeats_label_wrapping( string $declaration ): void {
		$this->assertStringContainsString(
			$declaration,
			$this->inline_rules(),
			'print_inline_styles() wins the cascade tie, so it must also declare ' . $declaration . '.'
		);
	}

	/**
	 * Neither source may reintroduce mid-word breaking.
	 *
	 * Asserted over the whole of both sources rather than the label rule alone,
	 * because `overflow-wrap: anywhere` inherited from any ancestor of the label
	 * would have the same effect.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_neither_source_uses_overflow_wrap_anywhere(): void {
		$this->assertStringNotContainsString(
			'overflow-wrap:anywhere',
			$this->stylesheet(),
			'overflow-wrap: anywhere breaks group labels mid-character; use break-word.'
		);

		$this->assertStringNotContainsString(
			'overflow-wrap:anywhere',
			$this->inline_rules(),
			'overflow-wrap: anywhere breaks group labels mid-character; use break-word.'
		);
	}

	/**
	 * A truncating label rule must not come back.
	 *
	 * The clamp uses text-overflow implicitly via -webkit-line-clamp, so an
	 * explicit ellipsis paired with nowrap is the old single-line truncation that
	 * clipped "Design & Layout" to "DESIGN & LA...".
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_label_is_not_single_line_truncated(): void {
		$stylesheet = $this->stylesheet();

		$this->assertStringNotContainsString(
			'white-space:nowrap',
			$stylesheet,
			'A nowrap group label truncates instead of wrapping.'
		);

		$this->assertStringContainsString(
			'white-space:normal',
			$stylesheet,
			'The group label must be allowed to wrap.'
		);
	}

	/**
	 * The indent and its connecting rule agree between the two sources.
	 *
	 * The indent is the whole of the hierarchy: without it the sidebar is a flat
	 * list with headings in it. A disagreement here means the sidebar renders one
	 * way with a fresh stylesheet and another with a stale one, which is far
	 * harder to diagnose than either being wrong on its own.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_indent_and_rule_agree_across_sources(): void {
		$stylesheet = $this->stylesheet();
		$inline     = $this->inline_rules();

		// The stylesheet defines the custom property; the inline block repeats it
		// as a fallback, so the two values have to match.
		$this->assertStringContainsString( '--amorg-indent:14px', $stylesheet );
		$this->assertStringContainsString( 'var(--amorg-indent,14px)', $inline );

		$this->assertStringContainsString( '--amorg-rule-inset:7px', $stylesheet );
		$this->assertStringContainsString( 'var(--amorg-rule-inset,7px)', $inline );

		// The connecting rule's opacity. At 0.16 it was invisible against the
		// Fresh scheme, so the hierarchy rested on the indent alone.
		$this->assertStringContainsString( 'opacity:0.28', $stylesheet );
		$this->assertStringContainsString( 'opacity:.28', $inline );
	}

	/**
	 * The filter box no longer hides every group header.
	 *
	 * Hiding them all flattened the results into an unlabelled list, which
	 * answered "is it here" but not "which group is it in" — the question someone
	 * hunting through collapsed groups actually has.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_filtering_keeps_group_headers(): void {
		$stylesheet = $this->stylesheet();

		$this->assertStringNotContainsString(
			'#adminmenu.amorg-filteringli.amorg-group-header{display:none;}',
			$stylesheet,
			'Headers are hidden per group by the script now, not wholesale by CSS.'
		);

		// Rows of a collapsed group are still revealed while a query is active,
		// which is the point of the box.
		$this->assertStringContainsString(
			'#adminmenu.amorg-filteringli.amorg-collapsed-member:not(.amorg-filter-hidden){display:block;}',
			$stylesheet,
			'A collapsed group\'s matching rows must be revealed while filtering.'
		);
	}
}
