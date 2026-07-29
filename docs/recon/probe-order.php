<?php
/**
 * Recon harness 2: replicates the custom_menu_order / menu_order pipeline
 * verbatim from wp-admin/includes/menu.php (WP 7.0.2, lines 291-345) to
 * establish what the menu_order filter can and cannot do to the menu.
 */

$GLOBALS['stock'] = array(
	array( 'Dashboard', 'read', 'index.php' ),
	array( 'Posts', 'edit_posts', 'edit.php' ),
	array( 'Media', 'upload_files', 'upload.php' ),
	array( 'WooCommerce', 'manage_woocommerce', 'woocommerce' ),
	array( 'Yoast SEO', 'manage_options', 'wpseo_dashboard' ),
	array( 'Settings', 'manage_options', 'options-general.php' ),
);

/** Verbatim reproduction of core's reorder block. */
function core_reorder( array $menu, callable $filter ) {
	$menu_order = array();
	foreach ( $menu as $menu_item ) {
		$menu_order[] = $menu_item[2];
	}
	$default_menu_order = $menu_order;

	$menu_order = $filter( $menu_order );          // the 'menu_order' filter
	$menu_order = array_flip( $menu_order );        // <-- core line 314
	$default_menu_order = array_flip( $default_menu_order );

	$sort = function ( $a, $b ) use ( $menu_order, $default_menu_order ) {
		$a = $a[2];
		$b = $b[2];
		if ( isset( $menu_order[ $a ] ) && ! isset( $menu_order[ $b ] ) ) {
			return -1;
		} elseif ( ! isset( $menu_order[ $a ] ) && isset( $menu_order[ $b ] ) ) {
			return 1;
		} elseif ( isset( $menu_order[ $a ] ) && isset( $menu_order[ $b ] ) ) {
			return $menu_order[ $a ] <=> $menu_order[ $b ];
		} else {
			return $default_menu_order[ $a ] <=> $default_menu_order[ $b ];
		}
	};
	usort( $menu, $sort );
	return $menu;
}

function report( $label, callable $filter ) {
	$before = array_column( $GLOBALS['stock'], 2 );
	$after  = array_column( core_reorder( $GLOBALS['stock'], $filter ), 2 );
	$lost   = array_diff( $before, $after );
	echo "\n=== $label ===\n";
	echo "  in  : " . implode( ', ', $before ) . "\n";
	echo "  out : " . implode( ', ', $after ) . "\n";
	echo "  count in/out: " . count( $before ) . '/' . count( $after )
		. ' | lost: ' . ( $lost ? implode( ',', $lost ) : 'NONE' ) . "\n";
}

// A: a well-formed grouped reorder.
report( 'A - full reorder, every slug returned', function ( $o ) {
	return array( 'index.php', 'edit.php', 'upload.php', 'wpseo_dashboard', 'woocommerce', 'options-general.php' );
} );

// B: filter DROPS two slugs it did not recognise. Does the menu lose them?
report( 'B - filter returns only 4 of 6 slugs', function ( $o ) {
	return array( 'index.php', 'edit.php', 'upload.php', 'woocommerce' );
} );

// C: filter returns an entirely empty array.
report( 'C - filter returns empty array', function ( $o ) {
	return array();
} );

// D: filter returns DUPLICATE slugs (the array_flip hazard).
report( 'D - filter returns duplicates', function ( $o ) {
	return array( 'index.php', 'edit.php', 'edit.php', 'upload.php', 'woocommerce', 'wpseo_dashboard', 'options-general.php' );
} );

// E: filter injects slugs that do not exist in $menu (e.g. our group headers).
report( 'E - filter injects phantom slugs', function ( $o ) {
	return array( 'wpamo-hdr-content', 'index.php', 'edit.php', 'upload.php',
		'wpamo-hdr-commerce', 'woocommerce', 'wpseo_dashboard', 'options-general.php' );
} );

// F: filter returns a non-array (a broken third-party filter downstream of us).
echo "\n=== F - filter returns a string (hostile/broken downstream filter) ===\n";
try {
	report( 'F', function ( $o ) { return 'garbage'; } );
} catch ( \Throwable $e ) {
	echo '  ' . get_class( $e ) . ': ' . $e->getMessage() . "\n";
}
