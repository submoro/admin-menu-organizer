<?php
/**
 * Recon harness: runs the REAL core _wp_menu_output() (extracted verbatim from
 * wp-admin/menu-header.php, WP 7.0.2, lines 73-289) against fixtures, to verify
 * SPEC.md section 3.2 claims by execution rather than by reading.
 */

define( 'ABSPATH', __DIR__ . '/wpstub/' );
define( 'WP_PLUGIN_DIR', __DIR__ . '/wpstub/wp-content/plugins' );

// --- Minimal stubs for the WP functions _wp_menu_output() touches. ---
function wptexturize( $t ) { return str_replace( "'", '&#8217;', $t ); }
function esc_attr( $t ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $t ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $t ) { return esc_attr( $t ); }
function __( $t ) { return $t; }
function sanitize_html_class( $t ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $t ); }
function current_user_can( $cap ) { return 'do_not_allow' !== $cap; }
function get_plugin_page_hook( $page, $parent ) { return null; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }

require __DIR__ . '/core-fn.php';

$GLOBALS['self']         = 'index.php';
$GLOBALS['parent_file']  = '';
$GLOBALS['submenu_file'] = null;
$GLOBALS['plugin_page']  = null;
$GLOBALS['typenow']      = '';

function probe( $label, array $menu, array $submenu = array() ) {
	ob_start();
	_wp_menu_output( $menu, $submenu );
	$html = ob_get_clean();
	// Drop the trailing collapse-menu <li> that core always appends.
	$html = preg_replace( '#<li id="collapse-menu".*$#s', '', $html );
	echo "\n=== $label ===\n" . trim( $html ) . "\n";
}

// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url

// CLAIM 1: an item carrying 'wp-menu-separator' in index 4 discards its title
// and is marked aria-hidden. (SPEC 3.3 step 5 proposes exactly this as the
// accordion header carrier.)
probe( 'CLAIM 1 - separator-class item WITH a title and icon', array(
	array( 'MY GROUP HEADER', 'read', 'amorg-group-content', '', 'amorg-group-header wp-menu-separator', 'amorg-hook', 'dashicons-admin-post' ),
) );

// CLAIM 2: menu titles are emitted RAW (never escaped) on output.
probe( 'CLAIM 2 - raw HTML in the title, item with no submenu', array(
	array( 'Plugins <span class="update-plugins count-3"><span class="plugin-count">3</span></span><b>RAW</b>', 'read', 'plugins.php', '', '', '', 'dashicons-admin-plugins' ),
) );

// CLAIM 2b: same, but for an item that HAS a submenu (core uses $title there,
// not $item[0] - a different code path).
probe( 'CLAIM 2b - raw HTML in title, item WITH submenu', array(
	array( 'Tools <b>RAW</b>', 'read', 'tools.php', '', '', '', 'dashicons-admin-tools' ),
), array( 'tools.php' => array( array( 'Available Tools', 'read', 'tools.php', '' ) ) ) );

// CLAIM 3: an item with an EMPTY slug and no submenu renders as an empty <li>
// that still carries our classes and id, and is NOT aria-hidden.
probe( 'CLAIM 3 - empty slug, custom class, no submenu', array(
	array( 'MY GROUP HEADER', 'read', '', '', 'amorg-group-header amorg-group-content', 'amorg_group_content', '' ),
) );

// CLAIM 4: index 4 (classes) IS escaped, so no HTML can be smuggled through it.
probe( 'CLAIM 4 - attempted HTML injection via index 4 (classes)', array(
	array( 'Title', 'read', 'edit.php', '', 'cls"><button>INJECTED</button><li class="', '', '' ),
) );

// CLAIM 5: capability at index 1 is honoured at render time - an item the user
// cannot access emits a bare <li> with classes but no link.
probe( 'CLAIM 5 - item whose capability the user lacks', array(
	array( 'Secret', 'do_not_allow', 'secret.php', '', 'amorg-item', '', '' ),
) );
