<?php
/**
 * Default category definitions.
 *
 * Returned as a PHP array rather than JSON so that opcache keeps it in memory
 * and no decoding happens per request. Extend or replace through the
 * amorg_category_definitions filter rather than editing this file.
 *
 * Labels are translated at the point they are read, not here, because this file
 * may be loaded before the text domain is available and calling a translation
 * function that early triggers WordPress's _load_textdomain_just_in_time notice.
 * Each entry therefore carries an untranslated label plus a context-free key
 * that Categories::labels() maps to a translated string.
 *
 * @package AdminMenuOrganizer
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

return array(
	array(
		'id'           => 'dashboard',
		'label'        => 'Dashboard',
		'icon'         => 'dashicons-dashboard',
		'default_open' => true,
	),
	array(
		'id'           => 'content',
		'label'        => 'Content',
		'icon'         => 'dashicons-admin-post',
		'default_open' => true,
	),
	array(
		'id'           => 'commerce',
		'label'        => 'Commerce',
		'icon'         => 'dashicons-cart',
		'default_open' => true,
	),
	array(
		'id'           => 'design',
		'label'        => 'Design & Layout',
		'icon'         => 'dashicons-admin-appearance',
		'default_open' => false,
	),
	array(
		'id'           => 'seo',
		'label'        => 'SEO & Marketing',
		'icon'         => 'dashicons-chart-line',
		'default_open' => false,
	),
	array(
		'id'           => 'security',
		'label'        => 'Security & Backup',
		'icon'         => 'dashicons-shield',
		'default_open' => false,
	),
	array(
		'id'           => 'performance',
		'label'        => 'Performance',
		'icon'         => 'dashicons-performance',
		'default_open' => false,
	),
	array(
		'id'           => 'users',
		'label'        => 'Users & Access',
		'icon'         => 'dashicons-groups',
		'default_open' => false,
	),
	array(
		'id'           => 'integrations',
		'label'        => 'Integrations',
		'icon'         => 'dashicons-admin-links',
		'default_open' => false,
	),
	array(
		'id'           => 'tools',
		'label'        => 'Tools & System',
		'icon'         => 'dashicons-admin-tools',
		'default_open' => false,
	),

	/*
	 * The catch-all. SPEC section 5.1 requires it to be always present and
	 * always visible, so that an item the detector cannot place is never hidden.
	 * Layout_Sanitizer refuses to delete it and refuses to mark it collapsible.
	 */
	array(
		'id'           => 'ungrouped',
		'label'        => 'Other',
		'icon'         => 'dashicons-menu-alt',
		'default_open' => true,
	),
);
