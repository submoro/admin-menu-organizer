<?php
/**
 * Menu fixtures used across the unit suite.
 *
 * These model what $GLOBALS['menu'] actually looks like on a production site,
 * including the awkward parts: separators, update-count markup embedded in
 * titles, base64 SVG icons, post-type slugs carrying query strings, and items
 * registered by plugins that do not follow the documented array shape.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

declare( strict_types=1 );

/**
 * A stock WordPress menu with no plugins active.
 *
 * Index order is 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title,
 * 4 = classes, 5 = hookname, 6 = icon_url.
 *
 * @since 1.0.0
 *
 * @return array
 */
function amorg_fixture_core_menu(): array {
	return array(
		2  => array( 'Dashboard', 'read', 'index.php', '', 'menu-top menu-top-first menu-icon-dashboard', 'menu-dashboard', 'dashicons-dashboard' ),
		4  => array( '', 'read', 'separator1', '', 'wp-menu-separator' ),
		5  => array( 'Posts', 'edit_posts', 'edit.php', '', 'open-if-no-js menu-top menu-icon-post', 'menu-posts', 'dashicons-admin-post' ),
		10 => array( 'Media', 'upload_files', 'upload.php', '', 'menu-top menu-icon-media', 'menu-media', 'dashicons-admin-media' ),
		20 => array( 'Pages', 'edit_pages', 'edit.php?post_type=page', '', 'menu-top menu-icon-page', 'menu-pages', 'dashicons-admin-page' ),
		25 => array( 'Comments <span class="awaiting-mod count-4"><span class="pending-count">4</span></span>', 'edit_posts', 'edit-comments.php', '', 'menu-top menu-icon-comments', 'menu-comments', 'dashicons-admin-comments' ),
		59 => array( '', 'read', 'separator2', '', 'wp-menu-separator' ),
		60 => array( 'Appearance', 'switch_themes', 'themes.php', '', 'menu-top menu-icon-appearance', 'menu-appearance', 'dashicons-admin-appearance' ),
		65 => array( 'Plugins <span class="update-plugins count-7"><span class="plugin-count">7</span></span>', 'activate_plugins', 'plugins.php', '', 'menu-top menu-icon-plugins', 'menu-plugins', 'dashicons-admin-plugins' ),
		70 => array( 'Users', 'list_users', 'users.php', '', 'menu-top menu-icon-users', 'menu-users', 'dashicons-admin-users' ),
		75 => array( 'Tools', 'edit_posts', 'tools.php', '', 'menu-top menu-icon-tools', 'menu-tools', 'dashicons-admin-tools' ),
		80 => array( 'Settings', 'manage_options', 'options-general.php', '', 'menu-top menu-icon-settings', 'menu-settings', 'dashicons-admin-settings' ),
		99 => array( '', 'read', 'separator-last', '', 'wp-menu-separator' ),
	);
}

/**
 * A realistic production menu: core plus a typical commercial plugin stack.
 *
 * This is the case SPEC section 12.2 calls "the real-world case": WooCommerce,
 * a page builder, a security plugin and an SEO plugin all installed together,
 * alongside forms, caching, backup, mail delivery and translation.
 *
 * @since 1.0.0
 *
 * @return array
 */
function amorg_fixture_production_menu(): array {
	$menu = amorg_fixture_core_menu();

	/*
	 * Fractional positions are string keys, not floats. That is what core does:
	 * add_menu_page() casts a decimal position with (string) before using it as
	 * an array key, precisely so two plugins asking for position 56 do not
	 * collide. Declaring them as floats here would trigger PHP 8.1's implicit
	 * float-to-int key conversion and silently misrepresent the fixture.
	 */
	$plugins = array(
		// Commerce.
		'56'   => array( 'WooCommerce <span class="awaiting-mod count-2"><span class="processing-count">2</span></span>', 'manage_woocommerce', 'woocommerce', '', 'menu-top toplevel_page_woocommerce', 'toplevel_page_woocommerce', 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciLz4=' ),
		'56.5' => array( 'Products', 'edit_products', 'edit.php?post_type=product', '', 'menu-top menu-icon-product', 'menu-posts-product', 'dashicons-products' ),
		'56.6' => array( 'Analytics', 'view_woocommerce_reports', 'wc-admin&path=/analytics/overview', '', 'menu-top toplevel_page_wc-admin', 'toplevel_page_wc-admin-path--analytics-overview', 'dashicons-chart-bar' ),
		'58'   => array( 'Marketing', 'view_woocommerce_reports', 'woocommerce-marketing', '', 'menu-top toplevel_page_woocommerce-marketing', 'toplevel_page_woocommerce-marketing', 'dashicons-megaphone' ),

		// Design.
		'58.5' => array( 'Elementor', 'manage_options', 'elementor', '', 'menu-top toplevel_page_elementor', 'toplevel_page_elementor', 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciLz4=' ),
		'58.6' => array( 'Templates', 'edit_posts', 'edit.php?post_type=elementor_library', '', 'menu-top', 'menu-posts-elementor_library', 'dashicons-admin-page' ),
		'58.7' => array( 'Slider Revolution', 'manage_options', 'revslider', '', 'menu-top toplevel_page_revslider', 'toplevel_page_revslider', 'dashicons-update' ),

		// SEO and marketing.
		'81'   => array( 'Yoast SEO <span class="update-plugins count-1"><span class="plugin-count">1</span></span>', 'wpseo_manage_options', 'wpseo_dashboard', '', 'menu-top toplevel_page_wpseo_dashboard', 'toplevel_page_wpseo_dashboard', 'dashicons-yoast' ),
		'81.1' => array( 'Redirection', 'manage_options', 'redirection.php', '', 'menu-top', 'tools_page_redirection', 'dashicons-randomize' ),
		'81.2' => array( 'Mailchimp', 'manage_options', 'mailchimp-woocommerce', '', 'menu-top', 'toplevel_page_mailchimp-woocommerce', 'dashicons-email' ),

		// Security and backup.
		'82'   => array( 'Wordfence <span class="update-plugins count-3"><span class="plugin-count">3</span></span>', 'activate_plugins', 'Wordfence', '', 'menu-top toplevel_page_Wordfence', 'toplevel_page_Wordfence', 'dashicons-shield' ),
		'82.1' => array( 'UpdraftPlus', 'manage_options', 'updraftplus', '', 'menu-top', 'settings_page_updraftplus', 'dashicons-backup' ),
		'82.2' => array( 'All-in-One WP Migration', 'export', 'ai1wm_export', '', 'menu-top toplevel_page_ai1wm_export', 'toplevel_page_ai1wm_export', 'dashicons-migrate' ),

		// Performance.
		'83'   => array( 'WP Rocket', 'manage_options', 'wprocket', '', 'menu-top', 'settings_page_wprocket', 'dashicons-performance' ),
		'83.1' => array( 'Smush', 'manage_options', 'smush', '', 'menu-top toplevel_page_smush', 'toplevel_page_smush', 'dashicons-images-alt2' ),

		// Integrations.
		'84'   => array( 'Contact', 'wpcf7_read_contact_forms', 'wpcf7', '', 'menu-top toplevel_page_wpcf7', 'toplevel_page_wpcf7', 'dashicons-email' ),
		'84.1' => array( 'WP Mail SMTP', 'manage_options', 'wp-mail-smtp', '', 'menu-top toplevel_page_wp-mail-smtp', 'toplevel_page_wp-mail-smtp', 'dashicons-email-alt' ),
		'84.2' => array( 'WPML', 'wpml_manage_languages', 'sitepress-multilingual-cms/menu/languages.php', '', 'menu-top', 'toplevel_page_sitepress-multilingual-cms', 'dashicons-translation' ),
		'84.3' => array( 'GEO my WP', 'manage_options', 'gmw-add-ons', '', 'menu-top toplevel_page_gmw-add-ons', 'toplevel_page_gmw-add-ons', 'dashicons-location' ),

		// Users and access.
		'84.4' => array( 'Members', 'list_roles', 'roles', '', 'menu-top toplevel_page_roles', 'toplevel_page_roles', 'dashicons-groups' ),

		/*
		 * An obscure plugin with a describable purpose. Nothing recognises the
		 * vendor, but "Widgets" is a real signal, so the keyword layer should
		 * still place it. This is what the cascade exists for.
		 */
		'90'   => array( 'Zarqawi Widgets', 'manage_options', 'zarqawi-widgets-pro', '', 'menu-top', 'toplevel_page_zarqawi-widgets-pro', 'dashicons-screenoptions' ),

		/*
		 * A genuinely opaque item: invented vendor, meaningless name, generic
		 * capability, no icon. There is nothing here to reason from, so it must
		 * land in the always-visible Other group rather than being guessed at.
		 */
		'91'   => array( 'Blorpex', 'manage_options', 'blorpex-panel', '', 'menu-top', 'toplevel_page_blorpex-panel', '' ),
	);

	foreach ( $plugins as $position => $item ) {
		$menu[ $position ] = $item;
	}

	return $menu;
}

/**
 * A menu containing entries that break the documented array shape.
 *
 * Every one of these has been seen in the wild. The reader must survive all of
 * them without notices and without inventing items.
 *
 * @since 1.0.0
 *
 * @return array
 */
function amorg_fixture_malformed_menu(): array {
	return array(
		0  => array( 'Dashboard', 'read', 'index.php', '', '', 'menu-dashboard', 'dashicons-dashboard' ),
		1  => 'not an array at all',
		2  => array(),
		3  => array( 'Missing slug', 'read' ),
		4  => array( 'Null slug', 'read', null ),
		5  => array( 'Empty slug', 'read', '' ),
		6  => array( 'Array slug', 'read', array( 'nope' ) ),
		7  => array( 'Short but valid', 'read', 'short.php' ),
		8  => null,
		9  => array( 'Duplicate', 'read', 'index.php', '', '', '', '' ),
		10 => array( 12345, 'read', 'numeric-title.php', '', '', '', '' ),
		11 => array( 'Settings', 'manage_options', 'options-general.php', '', '', '', '' ),
	);
}

/**
 * A submenu array matching the core fixture.
 *
 * @since 1.0.0
 *
 * @return array
 */
function amorg_fixture_core_submenu(): array {
	return array(
		'index.php'           => array(
			array( 'Home', 'read', 'index.php' ),
			array( 'Updates <span class="update-plugins count-7"><span class="update-count">7</span></span>', 'update_core', 'update-core.php' ),
		),
		'edit.php'            => array(
			array( 'All Posts', 'edit_posts', 'edit.php' ),
			array( 'Add New Post', 'edit_posts', 'post-new.php' ),
			array( 'Categories', 'manage_categories', 'edit-tags.php?taxonomy=category' ),
		),
		'options-general.php' => array(
			array( 'General', 'manage_options', 'options-general.php' ),
			array( 'Writing', 'manage_options', 'options-writing.php' ),
		),
	);
}
