<?php
/**
 * Exact menu-slug to category map.
 *
 * These are *menu* slugs, meaning the value at index 2 of a $menu entry, not
 * plugin directory slugs. The two frequently differ: the plugin directory slug
 * for Yoast SEO is "wordpress-seo" but its menu slug is "wpseo_dashboard".
 * Getting this wrong is the single easiest way to make the detector look broken,
 * so every entry here is a menu slug.
 *
 * This table cannot possibly enumerate every plugin in the directory, and it is
 * not meant to. It covers the plugins common enough to appear on a large share
 * of real sites, and everything it misses falls through to the pattern,
 * capability, icon and keyword layers in Detector. See docs/decisions.md D-008
 * for how the layers fit together.
 *
 * Returned as a PHP array rather than JSON, per SPEC section 5.3, so opcache
 * holds it and nothing is decoded per request.
 *
 * Extend through the amorg_known_slugs filter rather than editing this file.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

return array(

	// ---------------------------------------------------------------- Dashboard.
	'index.php'                                     => 'dashboard',
	'site-health.php'                               => 'dashboard',
	'my-sites.php'                                  => 'dashboard',

	// ------------------------------------------------------------------ Content.
	'edit.php'                                      => 'content',
	'upload.php'                                    => 'content',
	'edit-comments.php'                             => 'content',
	'edit.php?post_type=page'                       => 'content',
	'link-manager.php'                              => 'content',

	// Custom fields and content modelling.
	'edit.php?post_type=acf-field-group'            => 'content',
	'acf-field-group'                               => 'content',
	'cptui_main_menu'                               => 'content',
	'pods'                                          => 'content',
	'meta-box'                                      => 'content',
	'mb-post-types'                                 => 'content',
	'toolset-dashboard'                             => 'content',
	'edit.php?post_type=wp_block'                   => 'content',

	// Tables, galleries and media organisation.
	'tablepress'                                    => 'content',
	'wp-table-builder'                              => 'content',
	'filebird-settings'                             => 'content',
	'rml-folders'                                   => 'content',
	'nggallery-manage'                              => 'content',
	'foogallery'                                    => 'content',
	'modula'                                        => 'content',
	'envira-gallery-lite'                           => 'content',

	// Events, forums and directories.
	'edit.php?post_type=tribe_events'               => 'content',
	'tribe_events_page_tribe-common'                => 'content',
	'edit.php?post_type=forum'                      => 'content',
	'edit.php?post_type=mec-events'                 => 'content',
	'edit.php?post_type=testimonial'                => 'content',
	'edit.php?post_type=portfolio'                  => 'content',

	// ----------------------------------------------------------------- Commerce.
	'woocommerce'                                   => 'commerce',
	'wc-admin'                                      => 'commerce',
	'wc-settings'                                   => 'commerce',
	'wc-status'                                     => 'commerce',
	'wc-reports'                                    => 'commerce',
	'wc-orders'                                     => 'commerce',
	'woocommerce-marketing'                         => 'commerce',

	/*
	 * Multi-vendor marketplaces. Every slug below was read off a live
	 * MultiVendorX and YITH install where the cascade had missed it.
	 */
	'multivendorx'                                  => 'commerce',
	'mvx-setting'                                   => 'commerce',
	'yith_plugin_panel'                             => 'commerce',
	'yith_plugin_panel_wc'                          => 'commerce',
	'edit.php?post_type=product'                    => 'commerce',
	'edit.php?post_type=shop_order'                 => 'commerce',
	'edit.php?post_type=shop_coupon'                => 'commerce',

	// Other storefronts.
	'edit.php?post_type=download'                   => 'commerce',
	'edd-settings'                                  => 'commerce',
	'edd-reports'                                   => 'commerce',
	'wpsc-dashboard'                                => 'commerce',
	'surecart'                                      => 'commerce',
	'shopengine'                                    => 'commerce',

	// Multivendor and marketplaces.
	'dokan'                                         => 'commerce',
	'wcfm-layout'                                   => 'commerce',
	'wc-vendors'                                    => 'commerce',
	'wcv-vendor-settings'                           => 'commerce',

	// Payments, invoicing and funnels.
	'wpo_wcpdf_options_page'                        => 'commerce',
	'simpay'                                        => 'commerce',
	'wp-simple-pay'                                 => 'commerce',
	'cartflows'                                     => 'commerce',
	'wfacp'                                         => 'commerce',
	'woo-order-bump'                                => 'commerce',

	// Donations, affiliates and pricing.
	'give-forms'                                    => 'commerce',
	'edit.php?post_type=give_forms'                 => 'commerce',
	'charitable'                                    => 'commerce',
	'affiliate-wp'                                  => 'commerce',
	'affiliates-admin'                              => 'commerce',
	'pretty-link'                                   => 'commerce',
	'edit.php?post_type=thirstylink'                => 'commerce',
	'easy-pricing-tables'                           => 'commerce',

	// Bookings and reservations.
	'wc-bookings'                                   => 'commerce',
	'booking'                                       => 'commerce',
	'bookly-menu'                                   => 'commerce',
	'amelia'                                        => 'commerce',

	// ------------------------------------------------------------ Design/Layout.
	'themes.php'                                    => 'design',
	'widgets.php'                                   => 'design',
	'nav-menus.php'                                 => 'design',
	'customize.php'                                 => 'design',
	'site-editor.php'                               => 'design',

	// Page builders.
	'elementor'                                     => 'design',
	'elementor-home'                                => 'design',
	'elementor-app'                                 => 'design',

	/*
	 * XTemos WoodMart. Registers a dashboard and a separate theme settings
	 * screen, both of which fell through to the catch-all on a live install.
	 */
	'xts_dashboard'                                 => 'design',
	'xts_theme_settings'                            => 'design',
	'woodmart'                                      => 'design',
	'elementor-tools'                               => 'design',
	'elementor-system-info'                         => 'design',
	'edit.php?post_type=elementor_library'          => 'design',
	'fl-builder-settings'                           => 'design',
	'fl-builder-multisite-settings'                 => 'design',
	'et_divi_options'                               => 'design',
	'et_theme_builder'                              => 'design',
	'vc-general'                                    => 'design',
	'vc-welcome'                                    => 'design',
	'brizy'                                         => 'design',
	'ct_dashboard_page'                             => 'design',
	'bricks'                                        => 'design',
	'themify'                                       => 'design',
	'siteorigin-panels'                             => 'design',
	'so-css'                                        => 'design',

	// Theme dashboards.
	'astra'                                         => 'design',
	'generatepress'                                 => 'design',
	'generate-options'                              => 'design',
	'oceanwp-panel'                                 => 'design',
	'kadence'                                       => 'design',
	'kadence-starter-templates'                     => 'design',
	'nv-welcome'                                    => 'design',
	'blocksy-dashboard'                             => 'design',
	'hello-elementor'                               => 'design',

	// Block and builder addon suites.
	'eael-settings'                                 => 'design',
	'happy-addons'                                  => 'design',
	'premium_addons'                                => 'design',
	'uag'                                           => 'design',
	'stackable'                                     => 'design',
	'kadence-blocks'                                => 'design',
	'otter'                                         => 'design',
	'spectra'                                       => 'design',
	'greenshift'                                    => 'design',
	'gutenkit'                                      => 'design',
	'jet-dashboard'                                 => 'design',
	'jet-engine'                                    => 'design',

	// Sliders and popups.
	'revslider'                                     => 'design',
	'layerslider'                                   => 'design',
	'smart-slider3'                                 => 'design',
	'master-slider'                                 => 'design',
	'metaslider'                                    => 'design',
	'soliloquy-lite'                                => 'design',
	'edit.php?post_type=popup'                      => 'design',
	'popup-maker'                                   => 'design',
	'white-label-cms'                               => 'design',

	// ------------------------------------------------------------- SEO/Marketing.
	'wpseo_dashboard'                               => 'seo',
	'wpseo_workouts'                                => 'seo',
	'wpseo_redirects'                               => 'seo',
	'rank-math'                                     => 'seo',
	'rank-math-options-general'                     => 'seo',
	'aioseo'                                        => 'seo',
	'aioseo-settings'                               => 'seo',
	'seopress-option'                               => 'seo',
	'sq_dashboard'                                  => 'seo',
	'slim-seo'                                      => 'seo',
	'autodescription-settings'                      => 'seo',
	'smartcrawl'                                    => 'seo',

	// Redirects and schema.
	'redirection.php'                               => 'seo',
	'eps-redirects'                                 => 'seo',
	'aiosrs-pro-menu'                               => 'seo',
	'wp-schema-pro'                                 => 'seo',

	// Analytics and tracking.
	'googlesitekit-dashboard'                       => 'seo',
	'googlesitekit-splash'                          => 'seo',
	'monsterinsights_settings'                      => 'seo',
	'monsterinsights_reports'                       => 'seo',
	'exactmetrics_settings'                         => 'seo',
	'analytify'                                     => 'seo',
	'wps_overview_page'                             => 'seo',
	'burst'                                         => 'seo',
	'independent-analytics'                         => 'seo',
	'koko-analytics'                                => 'seo',
	'matomo-analytics'                              => 'seo',

	// Email marketing and CRM.
	'mailchimp-for-wp'                              => 'seo',
	'mailchimp-woocommerce'                         => 'seo',
	'mailpoet-newsletters'                          => 'seo',
	'newsletter_main_index'                         => 'seo',
	'fluentcrm-admin'                               => 'seo',
	'gh_dashboard'                                  => 'seo',
	'leadin'                                        => 'seo',
	'klaviyo_settings'                              => 'seo',
	'omnisend'                                      => 'seo',
	'sendinblue'                                    => 'seo',
	'brevo'                                         => 'seo',
	'activecampaign'                                => 'seo',
	'convertkit'                                    => 'seo',

	// Lead capture and social.
	'optin-monster-overview'                        => 'seo',
	'thrive-dashboard'                              => 'seo',
	'et_bloom_options'                              => 'seo',
	'social-warfare'                                => 'seo',
	'add-to-any'                                    => 'seo',
	'blog2social'                                   => 'seo',
	'PostsRevive'                                   => 'seo',
	'facebook-for-woocommerce'                      => 'seo',
	'pinterest-for-woocommerce'                     => 'seo',
	'google-listings-and-ads'                       => 'seo',

	// ---------------------------------------------------------- Security/Backup.
	'Wordfence'                                     => 'security',
	'WordfenceWAF'                                  => 'security',
	'WordfenceScan'                                 => 'security',
	'sucuriscan'                                    => 'security',
	'itsec'                                         => 'security',
	'better-wp-security'                            => 'security',
	'icwp-wpsf-plugin_admin'                        => 'security',
	'aiowpsec'                                      => 'security',
	'ninjafirewall'                                 => 'security',
	'malcare'                                       => 'security',
	'blogvault'                                     => 'security',
	'GOTMLS-settings'                               => 'security',
	'wp-cerber'                                     => 'security',
	'wdf'                                           => 'security',
	'hide-my-wp'                                    => 'security',
	'really-simple-security'                        => 'security',
	'rlrsssl_really_simple_ssl'                     => 'security',

	// Login hardening.
	'limit-login-attempts'                          => 'security',
	'loginizer'                                     => 'security',
	'wp-2fa'                                        => 'security',
	'two-factor'                                    => 'security',

	// Spam.
	'akismet-key-config'                            => 'security',
	'antispam-bee'                                  => 'security',
	'cleantalk'                                     => 'security',

	// Audit logs.
	'wsal-auditlog'                                 => 'security',
	'simple_history_admin_menu_page'                => 'security',
	'activity_log'                                  => 'security',

	// Backup and migration.
	'updraftplus'                                   => 'security',
	'backwpup'                                      => 'security',
	'duplicator'                                    => 'security',
	'wpvivid'                                       => 'security',
	'ai1wm_export'                                  => 'security',
	'migrate_guru'                                  => 'security',
	'pb_backupbuddy'                                => 'security',
	'wpstg_clone'                                   => 'security',
	'jetpack-backup'                                => 'security',
	'vaultpress'                                    => 'security',

	// -------------------------------------------------------------- Performance.
	'wprocket'                                      => 'performance',
	'w3tc_dashboard'                                => 'performance',
	'wpsupercache'                                  => 'performance',
	'litespeed'                                     => 'performance',
	'litespeed-cache'                               => 'performance',
	'autoptimize'                                   => 'performance',
	'WP-Optimize'                                   => 'performance',
	'wp-optimize'                                   => 'performance',
	'swift-performance'                             => 'performance',
	'cache-enabler'                                 => 'performance',
	'comet-cache'                                   => 'performance',
	'wphb'                                          => 'performance',
	'perfmatters'                                   => 'performance',
	'wpassetcleanup_getting_started'                => 'performance',
	'flying-press'                                  => 'performance',
	'nitropack'                                     => 'performance',
	'redis-cache'                                   => 'performance',
	'jetpack-boost'                                 => 'performance',

	// Image optimisation and delivery.
	'smush'                                         => 'performance',
	'wp-smush-bulk'                                 => 'performance',
	'wp-shortpixel'                                 => 'performance',
	'imagify'                                       => 'performance',
	'ewww-image-optimizer'                          => 'performance',
	'optimole-dashboard'                            => 'performance',
	'tinify'                                        => 'performance',
	'webp-express'                                  => 'performance',
	'cloudflare'                                    => 'performance',

	// Database hygiene.
	'advanced_db_cleaner'                           => 'performance',
	'wp-sweep'                                      => 'performance',

	// ------------------------------------------------------------ Users/Access.
	'users.php'                                     => 'users',
	'roles'                                         => 'users',
	'users_user_role_editor'                        => 'users',
	'pp-capabilities'                               => 'users',
	'capsman'                                       => 'users',

	// Memberships. SPEC section 5.1 places memberships here rather than in
	// Commerce, so LMS and membership dashboards follow suit.
	'memberpress'                                   => 'users',
	'pmpro-dashboard'                               => 'users',
	'rcp-members'                                   => 'users',
	'simple-membership'                             => 'users',
	'wp-members'                                    => 'users',
	'learndash-lms'                                 => 'users',
	'lifterlms'                                     => 'users',
	'tutor'                                         => 'users',
	'sensei'                                        => 'users',

	// Communities and profiles.
	'bp-components'                                 => 'users',
	'buddyboss-platform'                            => 'users',
	'ultimatemember'                                => 'users',
	'profile-builder-general-settings'              => 'users',
	'wp-user-frontend'                              => 'users',

	// Login experience.
	'theme-my-login'                                => 'users',
	'loginpress'                                    => 'users',
	'login-designer'                                => 'users',
	'nextend-social-login'                          => 'users',
	'tlwp-users'                                    => 'users',
	'user-switching'                                => 'users',

	// ------------------------------------------------------------ Integrations.
	// Forms.
	'wpcf7'                                         => 'integrations',
	'wpforms-overview'                              => 'integrations',
	'gf_edit_forms'                                 => 'integrations',
	'ninja-forms'                                   => 'integrations',
	'click-to-chat'                                 => 'integrations',
	'ht_ctc_tabs'                                   => 'integrations',

	/*
	 * WPML publishes its top-level item as a path rather than a slug, and the
	 * path differs between the core plugin and Translation Management. Both
	 * forms are listed because a live WPML site produced the second one, which
	 * nothing in the cascade recognised.
	 */
	'tm/menu/main.php'                              => 'integrations',
	'wpml-package-management'                       => 'integrations',

	/*
	 * Cookie consent and privacy compliance. Filed under Tools rather than
	 * Integrations: these are site-wide compliance utilities, closer in spirit
	 * to Settings than to a third-party service connection.
	 */
	'cookie-law-info'                               => 'tools',
	'cookieyes'                                     => 'tools',
	'gdpr-cookie-compliance'                        => 'tools',
	'complianz'                                     => 'tools',
	'formidable'                                    => 'integrations',
	'fluent_forms'                                  => 'integrations',
	'forminator'                                    => 'integrations',
	'happyforms'                                    => 'integrations',
	'caldera-forms'                                 => 'integrations',
	'everest-forms'                                 => 'integrations',
	'weforms'                                       => 'integrations',
	'metform-settings'                              => 'integrations',
	'quform'                                        => 'integrations',

	// Mail delivery.
	'wp-mail-smtp'                                  => 'integrations',
	'postman'                                       => 'integrations',
	'swpsmtp_settings'                              => 'integrations',
	'fluent-smtp'                                   => 'integrations',
	'mailgun'                                       => 'integrations',
	'sendgrid-settings'                             => 'integrations',
	'wp-ses'                                        => 'integrations',
	'wp-mail-log'                                   => 'integrations',

	// Translation.
	'sitepress-multilingual-cms/menu/languages.php' => 'integrations',
	'mlang'                                         => 'integrations',
	'trp_translate_press'                           => 'integrations',
	'weglot-settings'                               => 'integrations',
	'loco'                                          => 'integrations',
	'gtranslate'                                    => 'integrations',

	// Chat and helpdesk.
	'tidio-chat'                                    => 'integrations',
	'crisp'                                         => 'integrations',
	'livechat'                                      => 'integrations',
	'tawkto'                                        => 'integrations',
	'joinchat'                                      => 'integrations',
	'zendesk'                                       => 'integrations',
	'intercom'                                      => 'integrations',
	'freshdesk'                                     => 'integrations',
	'awesome-support'                               => 'integrations',

	// Maps and location.
	'gmw-add-ons'                                   => 'integrations',
	'wp-google-maps'                                => 'integrations',
	'wpsl_store_editor'                             => 'integrations',
	'google-maps-easy'                              => 'integrations',

	// Automation and misc services.
	'uncanny-automator'                             => 'integrations',
	'automatorwp'                                   => 'integrations',
	'wp-webhooks'                                   => 'integrations',
	'zoom-video-conferencing'                       => 'integrations',
	'anr_admin_settings'                            => 'integrations',

	// ----------------------------------------------------------- Tools/System.
	'tools.php'                                     => 'tools',
	'options-general.php'                           => 'tools',
	'plugins.php'                                   => 'tools',
	'update-core.php'                               => 'tools',
	'import.php'                                    => 'tools',
	'export.php'                                    => 'tools',
	'theme-editor.php'                              => 'tools',
	'plugin-editor.php'                             => 'tools',
	'options-permalink.php'                         => 'tools',
	'options-privacy.php'                           => 'tools',

	// Diagnostics and logs.
	'health-check'                                  => 'tools',
	'crontrol_admin_manage_page'                    => 'tools',
	'wp-crontrol'                                   => 'tools',
	'error-log-monitor'                             => 'tools',
	'log-viewer'                                    => 'tools',
	'string-locator'                                => 'tools',
	'debug-bar'                                     => 'tools',

	// Data movement and bulk editing.
	'pmxi-admin-home'                               => 'tools',
	'pmxe-admin-home'                               => 'tools',
	'better-search-replace'                         => 'tools',
	'wp-migrate-db'                                 => 'tools',
	'wp-sheet-editor'                               => 'tools',
	'codepress-admin-columns'                       => 'tools',
	'blc_local'                                     => 'tools',
	'regenerate-thumbnails'                         => 'tools',

	// Code and site management.
	'wp_file_manager'                               => 'tools',
	'snippets'                                      => 'tools',
	'wpcode'                                        => 'tools',
	'wpcode-settings'                               => 'tools',
	'ahc_head_footer'                               => 'tools',
	'wp-reset'                                      => 'tools',
	'mpsum-update-options'                          => 'tools',
	'companion-auto-update'                         => 'tools',
	'git-updater'                                   => 'tools',
	'plugin-organizer'                              => 'tools',
	'adminimize'                                    => 'tools',
	'menu_editor'                                   => 'tools',
	'classic-editor-settings'                       => 'tools',
);
