<?php
/**
 * Unit tests for the detection cascade.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 */

declare( strict_types=1 );

use WPAMO\Detector;
use WPAMO\Menu_Reader;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/fixtures/menus.php';

/**
 * Covers each layer of the cascade, the confidence threshold, and a sweep over
 * the whole production fixture.
 *
 * @since 1.0.0
 */
final class Test_Detector extends TestCase {

	/**
	 * The group IDs the bundled data uses.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const GROUPS = array(
		'dashboard',
		'content',
		'commerce',
		'design',
		'seo',
		'security',
		'performance',
		'users',
		'integrations',
		'tools',
		'ungrouped',
	);

	/**
	 * Builds a detector over the bundled data tables.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $filter Optional resolve filter.
	 * @return Detector
	 */
	private function detector( ?callable $filter = null ): Detector {
		$root = dirname( __DIR__, 2 ) . '/includes/data/';

		return new Detector(
			require $root . 'known-slugs.php',
			require $root . 'keyword-map.php',
			self::GROUPS,
			$filter
		);
	}

	/**
	 * Builds a normalized item by hand, for testing a single signal.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug       Menu slug.
	 * @param string $title      Plain title.
	 * @param string $capability Capability.
	 * @param string $icon       Dashicon or URL.
	 * @return array
	 */
	private function item( string $slug, string $title = '', string $capability = '', string $icon = '' ): array {
		return array(
			'slug'        => $slug,
			'plain_title' => $title,
			'capability'  => $capability,
			'icon'        => $icon,
		);
	}

	// -------------------------------------------------------- Layer 1: exact.

	/**
	 * Core's own menu items land where SPEC section 5.2 step 3 says they should.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_core_slugs_are_mapped(): void {
		$detector = $this->detector();

		$expected = array(
			'index.php'               => 'dashboard',
			'edit.php'                => 'content',
			'upload.php'              => 'content',
			'edit-comments.php'       => 'content',
			'edit.php?post_type=page' => 'content',
			'themes.php'              => 'design',
			'plugins.php'             => 'tools',
			'users.php'               => 'users',
			'tools.php'               => 'tools',
			'options-general.php'     => 'tools',
		);

		foreach ( $expected as $slug => $group ) {
			$this->assertSame(
				$group,
				$detector->detect( $this->item( $slug ) ),
				"Core slug {$slug} was misfiled."
			);
		}
	}

	/**
	 * Well-known plugin slugs are recognised exactly.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_known_plugin_slugs_are_mapped(): void {
		$detector = $this->detector();

		$expected = array(
			'woocommerce'     => 'commerce',
			'wpseo_dashboard' => 'seo',
			'Wordfence'       => 'security',
			'elementor'       => 'design',
			'wp-mail-smtp'    => 'integrations',
			'sitepress-multilingual-cms/menu/languages.php' => 'integrations',
			'updraftplus'     => 'security',
			'wprocket'        => 'performance',
			'memberpress'     => 'users',
			'tablepress'      => 'content',
			'wpcf7'           => 'integrations',
			'rank-math'       => 'seo',
			'litespeed'       => 'performance',
			'dokan'           => 'commerce',
			'roles'           => 'users',
		);

		foreach ( $expected as $slug => $group ) {
			$this->assertSame(
				$group,
				$detector->detect( $this->item( $slug ) ),
				"Known slug {$slug} was misfiled."
			);
		}
	}

	/**
	 * A capitalised slug still matches, since some plugins ship one.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_exact_match_falls_back_to_case_insensitive(): void {
		$detector = $this->detector();

		$this->assertSame( 'security', $detector->detect( $this->item( 'wordfence' ) ) );
		$this->assertSame( 'exact-slug', $detector->explain( $this->item( 'Wordfence' ) )['layer'] );
		$this->assertSame( 'exact-slug-ci', $detector->explain( $this->item( 'WORDFENCE' ) )['layer'] );
	}

	// ---------------------------------------------------- Layer 2: post types.

	/**
	 * A known post type is filed by what it is, not by its label.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_known_post_types_are_mapped(): void {
		$detector = $this->detector();

		$this->assertSame( 'commerce', $detector->detect( $this->item( 'edit.php?post_type=product' ) ) );
		$this->assertSame( 'commerce', $detector->detect( $this->item( 'edit.php?post_type=shop_order' ) ) );
		$this->assertSame( 'design', $detector->detect( $this->item( 'edit.php?post_type=elementor_library' ) ) );
		$this->assertSame( 'content', $detector->detect( $this->item( 'edit.php?post_type=tribe_events' ) ) );
	}

	/**
	 * An unrecognised custom post type is content, because that is what a post
	 * type is. This is the single most valuable generalisation in the cascade:
	 * every custom post type on every site lands correctly without being listed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_unknown_post_types_default_to_content(): void {
		$detector = $this->detector();

		foreach ( array( 'recipe', 'property', 'vehicle', 'zzz_made_up' ) as $post_type ) {
			$outcome = $detector->explain( $this->item( 'edit.php?post_type=' . $post_type ) );

			$this->assertSame( 'content', $outcome['group'], "Post type {$post_type} was misfiled." );
			$this->assertSame( 'post-type-default', $outcome['layer'] );
		}
	}

	/**
	 * Post type extraction copes with the query-string shapes core produces.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_post_type_extraction(): void {
		$this->assertSame( 'product', Detector::post_type_from_slug( 'edit.php?post_type=product' ) );
		$this->assertSame( 'product', Detector::post_type_from_slug( 'edit.php?foo=1&post_type=product' ) );
		$this->assertSame( 'product', Detector::post_type_from_slug( 'edit.php?post_type=product&paged=2' ) );
		$this->assertSame( '', Detector::post_type_from_slug( 'edit.php' ) );
		$this->assertSame( '', Detector::post_type_from_slug( 'woocommerce' ) );
	}

	// ------------------------------------------------- Layers 3 and 4: vendors.

	/**
	 * An unlisted item from a recognised plugin family is filed by its family.
	 *
	 * These slugs are deliberately absent from known-slugs.php: they represent
	 * the sub-pages and add-ons that no table could ever enumerate.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_vendor_tokens_generalise_to_unlisted_slugs(): void {
		$detector = $this->detector();

		$expected = array(
			'woocommerce-brand-importer'  => 'commerce',
			'wc-admin&path=/analytics/x'  => 'commerce',
			'yith_wcwl_panel'             => 'commerce',
			'elementor-custom-icons'      => 'design',
			'et_pb_options_generator'     => 'design',
			'wpseo_titles'                => 'seo',
			'monsterinsights_growth_tool' => 'seo',
			'wordfence-something-new'     => 'security',
			'updraftplus_addons'          => 'security',
			'litespeed-crawler'           => 'performance',
			'w3tc_extensions'             => 'performance',
			'shortpixel-bulk'             => 'performance',
			'learndash-something'         => 'users',
			'pmpro_reports'               => 'users',
			'wpforms-entries'             => 'integrations',
			'gf_export'                   => 'integrations',
			'trp_translation_logs'        => 'integrations',
		);

		foreach ( $expected as $slug => $group ) {
			$this->assertSame(
				$group,
				$detector->detect( $this->item( $slug ) ),
				"Vendor slug {$slug} was misfiled."
			);
		}
	}

	/**
	 * A vendor prefix does not run into an unrelated word.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_vendor_prefixes_respect_word_boundaries(): void {
		$detector = $this->detector();

		// "wc-" is delimited already, so it matches.
		$this->assertSame( 'commerce', $detector->detect( $this->item( 'wc-settings-extra' ) ) );

		// "acf" must not swallow an unrelated slug that merely starts with it.
		$outcome = $detector->explain( $this->item( 'acfoobar-widget' ) );
		$this->assertNotSame( 'slug-prefix', $outcome['layer'] );

		// "um-" must not swallow "umbrella".
		$outcome = $detector->explain( $this->item( 'umbrella-corp' ) );
		$this->assertNotSame( 'slug-prefix', $outcome['layer'] );
	}

	// ------------------------------------------------------ Layer 5: capability.

	/**
	 * A namespaced capability places an item nothing else recognises.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_capability_places_otherwise_unknown_items(): void {
		$detector = $this->detector();

		$outcome = $detector->explain( $this->item( 'zzz-unknown-panel', '', 'manage_woocommerce' ) );
		$this->assertSame( 'commerce', $outcome['group'] );
		$this->assertSame( 'capability', $outcome['layer'] );

		$outcome = $detector->explain( $this->item( 'zzz-another', '', 'wpml_manage_languages' ) );
		$this->assertSame( 'integrations', $outcome['group'] );

		$outcome = $detector->explain( $this->item( 'zzz-third', '', 'list_roles' ) );
		$this->assertSame( 'users', $outcome['group'] );
	}

	/**
	 * The manage_options capability is too common to be a signal, and must not be
	 * treated as one.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_manage_options_is_not_a_signal(): void {
		$detector = $this->detector();

		$outcome = $detector->explain( $this->item( 'zzz-mystery-panel', '', 'manage_options' ) );

		$this->assertSame( 'ungrouped', $outcome['group'] );
		$this->assertSame( 'fallback', $outcome['layer'] );
	}

	// -------------------------------------------------------- Layer 6: keywords.

	/**
	 * A plugin nobody has heard of is placed by what its name describes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_keywords_place_unheard_of_plugins(): void {
		$detector = $this->detector();

		$expected = array(
			'Turbo Cache Booster'       => 'performance',
			'Acme Firewall Pro'         => 'security',
			'Super Sitemap Generator'   => 'seo',
			'Fancy Checkout Fields'     => 'commerce',
			'Mega Page Builder'         => 'design',
			'Ultra SMTP Mailer'         => 'integrations',
			'Simple Membership Manager' => 'users',
			'Cron Debug Inspector'      => 'tools',
		);

		foreach ( $expected as $title => $group ) {
			$outcome = $detector->explain( $this->item( 'zzz-vendor-' . md5( $title ), $title ) );

			$this->assertSame( $group, $outcome['group'], "\"{$title}\" was misfiled." );
			$this->assertSame( 'keywords', $outcome['layer'] );
		}
	}

	/**
	 * Scoring declines rather than guessing when nothing clearly leads.
	 *
	 * An item in the always-visible Other group is one the administrator will
	 * notice and can move. An item filed somewhere plausible but wrong is one
	 * they will never think to look for.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_scoring_declines_when_unclear(): void {
		$detector = $this->detector();

		foreach ( array( 'Zarqawi Widgets', 'Blorp', 'Acme', '', 'XYZ Panel' ) as $title ) {
			$outcome = $detector->explain( $this->item( 'zzz-' . md5( (string) $title ), $title ) );

			$this->assertSame( 'ungrouped', $outcome['group'], "\"{$title}\" should not have been placed." );
		}
	}

	/**
	 * A tied score is not a decision.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_tie_is_not_a_decision(): void {
		$detector = new Detector(
			array(),
			array(
				'keywords' => array(
					'commerce' => array( 'alpha' => 5 ),
					'design'   => array( 'beta' => 5 ),
				),
			),
			self::GROUPS
		);

		$this->assertSame( 'ungrouped', $detector->detect( $this->item( 'x', 'alpha beta' ) ) );
		$this->assertSame( 'commerce', $detector->detect( $this->item( 'x', 'alpha' ) ) );
	}

	/**
	 * A score below the threshold is not a decision either.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_weak_score_is_not_a_decision(): void {
		$detector = new Detector(
			array(),
			array( 'keywords' => array( 'commerce' => array( 'faint' => 1 ) ) ),
			self::GROUPS
		);

		$this->assertSame( 'ungrouped', $detector->detect( $this->item( 'x', 'faint signal' ) ) );
	}

	/**
	 * Keyword matching is boundary-aware at the start of a word but not the end,
	 * so plurals match and unrelated words containing the token do not.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_keyword_boundaries(): void {
		$detector = new Detector(
			array(),
			array( 'keywords' => array( 'commerce' => array( 'tax' => 9 ) ) ),
			self::GROUPS
		);

		$this->assertSame( 'commerce', $detector->detect( $this->item( 'x', 'Tax Settings' ) ) );
		$this->assertSame( 'commerce', $detector->detect( $this->item( 'x', 'Taxes' ) ) );
		$this->assertSame( 'ungrouped', $detector->detect( $this->item( 'x', 'Syntax Highlighter' ) ) );
	}

	/**
	 * Slugs contribute words to matching, not punctuation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_slug_humanisation(): void {
		$this->assertSame( 'wp mail smtp', Detector::humanize_slug( 'wp-mail-smtp' ) );
		$this->assertSame( 'edit product', Detector::humanize_slug( 'edit.php?post_type=product' ) );
		$this->assertSame( 'options general', Detector::humanize_slug( 'options-general.php' ) );
		$this->assertSame( 'wc admin analytics overview', Detector::humanize_slug( 'wc-admin&path=/analytics/overview' ) );
	}

	// ----------------------------------------------------------- Layer 7: icon.

	/**
	 * The icon is consulted only after scoring has declined.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_icon_is_a_last_resort(): void {
		$detector = $this->detector();

		$outcome = $detector->explain( $this->item( 'zzz-mystery', 'Blorp', '', 'dashicons-shield' ) );
		$this->assertSame( 'security', $outcome['group'] );
		$this->assertSame( 'icon', $outcome['layer'] );

		// A confident keyword match beats the icon, even a contradictory one.
		$outcome = $detector->explain( $this->item( 'zzz-mystery2', 'Turbo Cache Booster', '', 'dashicons-shield' ) );
		$this->assertSame( 'performance', $outcome['group'] );
		$this->assertSame( 'keywords', $outcome['layer'] );
	}

	/**
	 * A base64 SVG icon is not mistaken for a signal.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_svg_icons_are_ignored(): void {
		$detector = $this->detector();

		$outcome = $detector->explain(
			$this->item( 'zzz-mystery', 'Blorp', '', 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=' )
		);

		$this->assertSame( 'ungrouped', $outcome['group'] );
	}

	// ------------------------------------------------------------- Fallback.

	/**
	 * Detection always returns a valid group, never an empty string.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_detection_always_returns_a_valid_group(): void {
		$detector = $this->detector();

		foreach ( array( '', 'x', '???', '../../etc/passwd', str_repeat( 'a', 500 ) ) as $slug ) {
			$group = $detector->detect( $this->item( $slug ) );

			$this->assertContains( $group, self::GROUPS );
			$this->assertNotSame( '', $group );
		}
	}

	/**
	 * A group ID the detector does not know about is never returned, even if the
	 * data tables name one.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_unknown_group_ids_in_data_are_ignored(): void {
		$detector = new Detector(
			array( 'my-slug' => 'a-group-that-does-not-exist' ),
			array(),
			array( 'commerce', 'ungrouped' )
		);

		$this->assertSame( 'ungrouped', $detector->detect( $this->item( 'my-slug' ) ) );
	}

	// --------------------------------------------------------------- Filter.

	/**
	 * The resolve filter can override any decision.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_filter_overrides_a_decision(): void {
		$detector = $this->detector(
			static function ( string $group, array $item ): string {
				return 'woocommerce' === $item['slug'] ? 'tools' : $group;
			}
		);

		$outcome = $detector->explain( $this->item( 'woocommerce' ) );

		$this->assertSame( 'tools', $outcome['group'] );
		$this->assertSame( 'filter', $outcome['layer'] );
	}

	/**
	 * The filter cannot invent a group that does not exist.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_filter_cannot_invent_a_group(): void {
		$detector = $this->detector(
			static function (): string {
				return 'not-a-real-group';
			}
		);

		$this->assertSame( 'commerce', $detector->detect( $this->item( 'woocommerce' ) ) );
	}

	// ------------------------------------------------------ Whole-menu sweep.

	/**
	 * Every organizable item in the production fixture is detected, and the
	 * result is a valid group.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_detects_every_item_in_the_production_menu(): void {
		$reader   = new Menu_Reader( wpamo_fixture_production_menu() );
		$detected = $this->detector()->detect_all( $reader );

		$this->assertSame( $reader->organizable_slugs(), array_keys( $detected ) );

		foreach ( $detected as $slug => $group ) {
			$this->assertContains( $group, self::GROUPS, "{$slug} produced an invalid group." );
		}
	}

	/**
	 * The production fixture is filed the way a person would file it.
	 *
	 * This is the assertion that matters most. It is written out item by item so
	 * that a regression in any single mapping is visible, rather than hidden
	 * behind an aggregate count.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_production_menu_is_filed_sensibly(): void {
		$reader   = new Menu_Reader( wpamo_fixture_production_menu() );
		$detected = $this->detector()->detect_all( $reader );

		$expected = array(
			'index.php'                            => 'dashboard',
			'edit.php'                             => 'content',
			'upload.php'                           => 'content',
			'edit.php?post_type=page'              => 'content',
			'edit-comments.php'                    => 'content',
			'themes.php'                           => 'design',
			'plugins.php'                          => 'tools',
			'users.php'                            => 'users',
			'tools.php'                            => 'tools',
			'options-general.php'                  => 'tools',
			'woocommerce'                          => 'commerce',
			'edit.php?post_type=product'           => 'commerce',
			'wc-admin&path=/analytics/overview'    => 'commerce',
			'woocommerce-marketing'                => 'commerce',
			'elementor'                            => 'design',
			'edit.php?post_type=elementor_library' => 'design',
			'revslider'                            => 'design',
			'wpseo_dashboard'                      => 'seo',
			'redirection.php'                      => 'seo',
			'mailchimp-woocommerce'                => 'seo',
			'Wordfence'                            => 'security',
			'updraftplus'                          => 'security',
			'ai1wm_export'                         => 'security',
			'wprocket'                             => 'performance',
			'smush'                                => 'performance',
			'wpcf7'                                => 'integrations',
			'wp-mail-smtp'                         => 'integrations',
			'sitepress-multilingual-cms/menu/languages.php' => 'integrations',
			'gmw-add-ons'                          => 'integrations',
			'roles'                                => 'users',

			// Unknown vendor, but "Widgets" is a real signal, so the keyword
			// layer places it rather than giving up.
			'zarqawi-widgets-pro'                  => 'design',

			// Nothing to reason from at all, so it must not be guessed at.
			'blorpex-panel'                        => 'ungrouped',
		);

		foreach ( $expected as $slug => $group ) {
			$this->assertArrayHasKey( $slug, $detected, "{$slug} was not detected at all." );
			$this->assertSame( $group, $detected[ $slug ], "{$slug} was misfiled." );
		}
	}

	/**
	 * At most one item in the production fixture needs manual filing.
	 *
	 * The point of the cascade is that a realistic site is mostly sorted without
	 * intervention. If this number climbs, the cascade has regressed even though
	 * every individual assertion above may still pass.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_production_menu_needs_almost_no_manual_filing(): void {
		$reader   = new Menu_Reader( wpamo_fixture_production_menu() );
		$detected = $this->detector()->detect_all( $reader );

		$ungrouped = array_keys( $detected, 'ungrouped', true );
		$total     = count( $detected );

		$this->assertLessThanOrEqual(
			1,
			count( $ungrouped ),
			sprintf(
				'%d of %d items fell through to Other: %s',
				count( $ungrouped ),
				$total,
				implode( ', ', $ungrouped )
			)
		);
	}

	/**
	 * Every group named in the bundled data is one the categories file defines.
	 *
	 * A typo in either data file would otherwise silently send items to the
	 * fallback with no indication why.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_bundled_data_only_references_defined_groups(): void {
		$root       = dirname( __DIR__, 2 ) . '/includes/data/';
		$categories = array_column( require $root . 'categories.php', 'id' );

		foreach ( require $root . 'known-slugs.php' as $slug => $group ) {
			$this->assertContains( $group, $categories, "known-slugs.php: {$slug} names an undefined group." );
		}

		$heuristics = require $root . 'keyword-map.php';

		foreach ( array( 'post_types', 'slug_contains', 'slug_prefixes', 'capabilities', 'icons' ) as $table ) {
			foreach ( $heuristics[ $table ] as $token => $group ) {
				$this->assertContains( $group, $categories, "keyword-map.php {$table}: {$token} names an undefined group." );
			}
		}

		foreach ( array_keys( $heuristics['keywords'] ) as $group ) {
			$this->assertContains( $group, $categories, "keyword-map.php keywords: {$group} is not a defined group." );
		}
	}

	/**
	 * The bundled tables contain no duplicate keys that would silently shadow
	 * each other, and no empty keys.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_bundled_data_is_well_formed(): void {
		$root = dirname( __DIR__, 2 ) . '/includes/data/';

		foreach ( require $root . 'known-slugs.php' as $slug => $group ) {
			$this->assertNotSame( '', (string) $slug );
			$this->assertIsString( $group );
		}

		$categories = require $root . 'categories.php';
		$ids        = array_column( $categories, 'id' );

		$this->assertSame( array_unique( $ids ), $ids, 'categories.php contains a duplicate id.' );
		$this->assertContains( 'ungrouped', $ids, 'categories.php must define the ungrouped catch-all.' );

		foreach ( $categories as $definition ) {
			$this->assertArrayHasKey( 'label', $definition );
			$this->assertArrayHasKey( 'icon', $definition );
			$this->assertArrayHasKey( 'default_open', $definition );
			$this->assertStringStartsWith( 'dashicons-', $definition['icon'] );
		}
	}
}
