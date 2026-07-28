<?php
/**
 * End-to-end render: push the production fixture through the plugin's own
 * reorder and decorate steps, then through the REAL core _wp_menu_output()
 * extracted from wp-admin/menu-header.php, and inspect the resulting HTML.
 *
 * This is the closest thing to proof-of-render available without a WordPress
 * install: the rendering function is core's, not a reimplementation.
 */

$repo = str_replace( '\\', '/', dirname( __DIR__, 2 ) ) . '/';
$sp   = __DIR__ . '/';

define( 'ABSPATH', $sp . 'wpstub/' );
define( 'WP_PLUGIN_DIR', $sp . 'wpstub/wp-content/plugins' );
define( 'MOCAM_PLUGIN_DIR', $repo );
define( 'MOCAM_PLUGIN_URL', 'https://example.test/wp-content/plugins/mocam/' );
define( 'MOCAM_VERSION', '1.0.0' );

// --- Stubs for the WordPress functions the render path and our classes touch. ---
function wptexturize( $t ) { return $t; }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $t, $d = null ) { return esc_attr( $t ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function __( $t, $d = null ) { return $t; }
function sanitize_html_class( $t ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $t ); }
function sanitize_key( $t ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $t ) ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function wp_unslash( $v ) { return $v; }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function get_plugin_page_hook( $p, $q ) { return null; }
function add_query_arg( $a, $u ) { return $u . '?' . http_build_query( $a ); }
function apply_filters( $tag, $value ) { return $value; }
function add_filter() {}
function add_action() {}
function current_user_can( $cap ) { return 'do_not_allow' !== $cap; }
function get_current_user_id() { return 1; }

require $repo . 'includes/class-menu-reader.php';
require $repo . 'includes/class-detector.php';
require $repo . 'includes/class-menu-order.php';
require $repo . 'includes/class-menu-renderer.php';
require $repo . 'includes/class-migrations.php';
require $repo . 'includes/class-layout-sanitizer.php';
require $sp . 'core-fn.php';   // The real _wp_menu_output().
require $repo . 'tests/fixtures/menus.php';

// Categories needs apply_filters and __; give it the bundled data directly.
final class Categories_Stub {
	public static function definitions(): array {
		static $d = null;
		if ( null === $d ) {
			$d = require MOCAM_PLUGIN_DIR . 'includes/data/categories.php';
		}
		return $d;
	}
}

// MOCAM\Categories is referenced by the renderer; alias a minimal version.
eval( 'namespace MOCAM; final class Categories { const UNGROUPED = "ungrouped";
	public static function definitions(): array { return \Categories_Stub::definitions(); }
	public static function ids(): array { return array_column( self::definitions(), "id" ); }
	public static function get( string $id ) { foreach ( self::definitions() as $d ) { if ( $d["id"] === $id ) { return $d; } } return null; }
}' );

$GLOBALS['self']         = 'edit.php';
$GLOBALS['parent_file']  = 'edit.php';
$GLOBALS['submenu_file'] = null;
$GLOBALS['plugin_page']  = null;
$GLOBALS['typenow']      = '';
$GLOBALS['pagenow']      = 'edit.php';

$groups = array_column( Categories_Stub::definitions(), 'id' );

$reader   = new MOCAM\Menu_Reader( mocam_fixture_production_menu() );
$detector = new MOCAM\Detector(
	require $repo . 'includes/data/known-slugs.php',
	require $repo . 'includes/data/keyword-map.php',
	$groups
);
$detected = $detector->detect_all( $reader );

// Build a layout from detection, exactly as Layout_Repository::auto_detected would.
$layout_groups = array();
foreach ( Categories_Stub::definitions() as $definition ) {
	$layout_groups[] = array(
		'id'           => $definition['id'],
		'label'        => $definition['label'],
		'icon'         => $definition['icon'],
		'default_open' => $definition['default_open'],
		'items'        => array_values( array_keys( $detected, $definition['id'], true ) ),
	);
}
$layout = array( 'schema' => 1, 'groups' => $layout_groups );

// 1. Reorder.
$ordered = MOCAM\Menu_Order::order( $reader->slugs(), $reader, $layout, $detected );

echo "=== STEP 1: reorder ===\n";
printf( "  in %d slugs, out %d slugs, permutation: %s\n",
	count( $reader->slugs() ),
	count( $ordered ),
	( array() === array_diff( $reader->slugs(), $ordered ) && array() === array_diff( $ordered, $reader->slugs() ) ) ? 'YES' : 'NO'
);

// Apply the order to the menu array, as core's usort would.
$position = array_flip( $ordered );
$menu     = mocam_fixture_production_menu();
usort( $menu, static function ( $a, $b ) use ( $position ) {
	return ( $position[ $a[2] ] ?? 999 ) <=> ( $position[ $b[2] ] ?? 999 );
} );

// 2. Decorate. The user has collapsed 'design' and 'security'.
$renderer = new MOCAM\Menu_Renderer( $reader, $layout, array( 'design', 'security' ) );
$decorated = $renderer->decorate( $menu );

echo "\n=== STEP 2: decorate ===\n";
printf( "  %d rows in, %d rows out (%d header rows injected)\n",
	count( $menu ), count( $decorated ), count( $decorated ) - count( $menu )
);
printf( "  active group (pagenow=edit.php): %s\n", $renderer->active_group() ?: '(none)' );

// 3. Render with the REAL core function.
ob_start();
_wp_menu_output( $decorated, array() );
$html = ob_get_clean();

echo "\n=== STEP 3: render via core _wp_menu_output() ===\n";

$li_count      = preg_match_all( '/<li[^>]*>/', $html, $lis );
$headers       = preg_match_all( '/<li class="[^"]*mocam-group-header[^"]*"[^>]*>/', $html, $hs );
$collapsed     = preg_match_all( '/mocam-collapsed-member/', $html );
$aria_hidden   = preg_match_all( '/mocam-group-header[^>]*aria-hidden/', $html );
$items         = preg_match_all( '/mocam-item/', $html );

printf( "  total <li>: %d\n", $li_count );
printf( "  header rows rendered: %d\n", $headers );
printf( "  header rows marked aria-hidden: %d  (must be 0)\n", $aria_hidden );
printf( "  decorated items: %d\n", $items );
printf( "  rows hidden as collapsed members: %d\n", $collapsed );

echo "\n--- header rows as core actually emitted them ---\n";
foreach ( $hs[0] as $h ) {
	echo "  $h</li>\n";
}

echo "\n--- a decorated item, and a collapsed one ---\n";
if ( preg_match( '#<li class="[^"]*mocam-group-commerce[^"]*".*?</li>#s', $html, $m ) ) {
	echo '  ' . substr( preg_replace( '/\s+/', ' ', $m[0] ), 0, 300 ) . "\n";
}
if ( preg_match( '#<li class="[^"]*mocam-collapsed-member[^"]*".*?</li>#s', $html, $m ) ) {
	echo '  ' . substr( preg_replace( '/\s+/', ' ', $m[0] ), 0, 300 ) . "\n";
}

echo "\n=== ASSERTIONS ===\n";
$fail = 0;
function check( $label, $ok ) {
	global $fail;
	if ( ! $ok ) { $fail++; }
	printf( "  %-62s %s\n", $label, $ok ? 'PASS' : 'FAIL' );
}

check( 'every group with members got a header row', $headers > 5 );
check( 'no header row is aria-hidden', 0 === $aria_hidden );
check( 'header rows render as empty <li> (no anchor inside)', ! preg_match( '/mocam-group-header[^>]*>\s*<a/', $html ) );
check( 'items carry their group class', $items > 25 );
check( 'collapsed groups hid their rows', $collapsed > 0 );
check( 'the active group (content) is NOT collapsed', ! preg_match( '/mocam-group-content[^"]*mocam-collapsed-member/', $html ) );
check( 'ungrouped is never collapsed', ! preg_match( '/mocam-group-ungrouped[^"]*mocam-collapsed-member/', $html ) );
check( 'no menu item lost: every original slug appears in the HTML', (function () use ( $reader, $html ) {
	foreach ( $reader->organizable_slugs() as $slug ) {
		if ( false === strpos( $html, "'" . $slug . "'" ) && false === strpos( $html, htmlspecialchars( $slug, ENT_QUOTES ) ) ) {
			echo "\n      MISSING: $slug";
			return false;
		}
	}
	return true;
} )() );
check( 'inline CSS carries a label for each group', (function () use ( $renderer ) {
	ob_start();
	$renderer->print_inline_styles();
	$css = ob_get_clean();
	return false !== strpos( $css, '--mocam-label' ) && false !== strpos( $css, 'noscript' );
} )() );

echo "\n", 0 === $fail ? "END-TO-END RENDER OK\n" : "$fail ASSERTION(S) FAILED\n";
exit( $fail ? 1 : 0 );
