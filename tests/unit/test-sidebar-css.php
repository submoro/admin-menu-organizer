<?php
/**
 * Unit tests pinning the sidebar's label and hierarchy CSS.
 *
 * @package AdminMenuCategories
 * @since   1.1.1
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * Guards the group label rules, in both of the places they are written.
 *
 * Menu_Renderer::inline_styles() deliberately repeats a handful of
 * declarations that also live in assets/css/admin-menu.css, so that the
 * hierarchy survives a stale copy of the stylesheet. That duplication is a trap
 * as well as a safety net: wp_add_inline_style() prints the block immediately
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
	 * The CSS that inline_styles() builds, recovered from the source.
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
			//
			// All four of these are load-bearing, not one declaration plus
			// decoration. The clamp only engages on a -webkit-box, only counts
			// lines with box-orient vertical, and only ellipsises what it hides
			// with overflow hidden. Omitting any one of them leaves a rule that
			// still parses and no longer clamps, so each is pinned separately.
			// -webkit-line-clamp is listed as well as the unprefixed property
			// because no browser yet honours the unprefixed one on its own, and
			// `line-clamp:2` is a substring of `-webkit-line-clamp:2` — asserting
			// only the former would pass on a rule that had dropped it.
			'line-clamp'    => array( 'line-clamp:2' ),
			'webkit-clamp'  => array( '-webkit-line-clamp:2' ),
			'box-orient'    => array( '-webkit-box-orient:vertical' ),
			'display'       => array( 'display:-webkit-box' ),
			'overflow'      => array( 'overflow:hidden' ),
			'white-space'   => array( 'white-space:normal' ),

			/*
			 * The flex sizing, which the subset check cannot cover: that only
			 * proves the inline copy says nothing extra, so if both sources lost
			 * these the tests would still pass.
			 *
			 * min-width: 0 is the load-bearing one. A flex item will not shrink
			 * below its own content width by default, so without it the label
			 * refuses to narrow and therefore never wraps at all — the clamp and
			 * the break-word rules above would have nothing to act on.
			 *
			 * Written as `flex:11auto` because normalize() strips every space, so
			 * `flex: 1 1 auto` collapses to that. Ugly, and exact.
			 */
			'flex'          => array( 'flex:11auto' ),
			'min-width'     => array( 'min-width:0' ),
		);
	}

	/**
	 * Extracts the declarations of the first rule whose selector ends in a class.
	 *
	 * @since 1.1.1
	 *
	 * @param string $css      Normalised CSS.
	 * @param string $selector Trailing selector fragment, e.g. '.amorg-toggle-label'.
	 * @return string[] Declarations, without the surrounding braces.
	 */
	private static function declarations_of( string $css, string $selector ): array {
		$start = strpos( $css, $selector . '{' );

		if ( false === $start ) {
			return array();
		}

		$open  = $start + strlen( $selector );
		$close = strpos( $css, '}', $open );

		if ( false === $close ) {
			return array();
		}

		$body = substr( $css, $open + 1, $close - $open - 1 );

		return array_values( array_filter( explode( ';', $body ) ) );
	}

	/**
	 * The inline label rule says nothing the stylesheet does not.
	 *
	 * Stronger than checking declarations one at a time, and it is the property
	 * that actually matters. The inline block wins the cascade tie, so any
	 * declaration it carries that the stylesheet does not is a value that
	 * overrides the file while being invisible when reading it. The reverse is
	 * allowed: the stylesheet legitimately carries extra declarations, such as the
	 * flex sizing, that the inline copy has no need to repeat.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_inline_label_rule_is_a_subset_of_the_stylesheet(): void {
		$inline     = self::declarations_of( $this->inline_rules(), '.amorg-toggle-label' );
		$stylesheet = self::declarations_of( $this->stylesheet(), '.amorg-toggle-label' );

		$this->assertNotEmpty( $inline, 'No inline .amorg-toggle-label rule found.' );
		$this->assertNotEmpty( $stylesheet, 'No stylesheet .amorg-toggle-label rule found.' );

		$this->assertSame(
			array(),
			array_values( array_diff( $inline, $stylesheet ) ),
			'These declarations are printed inline but absent from admin-menu.css, so they '
				. 'silently override the stylesheet. Add them there or drop them here.'
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
		$this->assertContains(
			$declaration,
			self::declarations_of( $this->stylesheet(), '.amorg-toggle-label' ),
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
		$this->assertContains(
			$declaration,
			self::declarations_of( $this->inline_rules(), '.amorg-toggle-label' ),
			'The inline block wins the cascade tie, so it must also declare ' . $declaration . '.'
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
	 * Scoped to the label rule, unlike
	 * test_neither_source_uses_overflow_wrap_anywhere below. `white-space` is not
	 * the kind of declaration another rule has any business being judged on — a
	 * nowrap somewhere else in the sidebar is legitimate and unrelated — whereas
	 * `overflow-wrap: anywhere` would reach the label by inheritance from any
	 * ancestor, so that one is deliberately checked over the whole file.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_label_is_not_single_line_truncated(): void {
		$label = self::declarations_of( $this->stylesheet(), '.amorg-toggle-label' );

		$this->assertNotEmpty( $label, 'No stylesheet .amorg-toggle-label rule found.' );

		$this->assertNotContains(
			'white-space:nowrap',
			$label,
			'A nowrap group label truncates instead of wrapping.'
		);

		$this->assertContains(
			'white-space:normal',
			$label,
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
	 * The inset and opacity are read out of the connector rule itself, so that an
	 * unrelated rule carrying the same opacity cannot stand in for a connector that
	 * has lost it. The two custom properties are the exception and are checked
	 * globally on purpose: they are declared once on #adminmenu, not on the
	 * connector, and the connector's job is only to reference them.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_indent_and_rule_agree_across_sources(): void {
		$stylesheet = $this->stylesheet();
		$inline     = $this->inline_rules();

		// The stylesheet defines the custom properties; the inline block repeats
		// them as fallbacks, so the two values have to match.
		$this->assertStringContainsString( '--amorg-indent:14px', $stylesheet );
		$this->assertStringContainsString( '--amorg-rule-inset:7px', $stylesheet );

		$indent_rule = self::declarations_of( $stylesheet, 'li.amorg-item>a' );
		$this->assertContains( 'padding-inline-start:var(--amorg-indent)', $indent_rule );

		$inline_indent = self::declarations_of( $inline, 'li.amorg-item>a' );
		$this->assertContains( 'padding-inline-start:var(--amorg-indent,14px)', $inline_indent );

		// The connecting rule itself: where it sits, and how visible it is. At
		// opacity 0.16 it was invisible against the Fresh scheme, so the hierarchy
		// rested on the indent alone.
		$connector = self::declarations_of( $stylesheet, 'li.amorg-item::before' );
		$this->assertNotEmpty( $connector, 'No stylesheet connector rule found.' );
		$this->assertContains( 'inset-inline-start:var(--amorg-rule-inset)', $connector );
		$this->assertContains( 'opacity:0.28', $connector );

		$inline_connector = self::declarations_of( $inline, 'li.amorg-item::before' );
		$this->assertNotEmpty( $inline_connector, 'No inline connector rule found.' );
		$this->assertContains( 'inset-inline-start:var(--amorg-rule-inset,7px)', $inline_connector );
		$this->assertContains( 'opacity:.28', $inline_connector );
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
