<?php
/**
 * Category definitions and their translated labels.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

namespace AMORG;

defined( 'ABSPATH' ) || exit;

/**
 * Provides the set of available categories.
 *
 * Labels live here rather than in the data file because translating them
 * requires calling __() with a literal text domain, and doing that inside a
 * returned array literal would run the translation the moment the file is
 * required, which can be before the text domain is ready. WordPress 6.7 and
 * later emits a _load_textdomain_just_in_time notice for exactly that, and SPEC
 * section 15 requires zero notices.
 *
 * @since 1.0.0
 */
final class Categories {

	/**
	 * The catch-all group, which can never be deleted or hidden.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const UNGROUPED = 'ungrouped';

	/**
	 * Memoised definitions for the current request.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private static $definitions = null;

	/**
	 * Returns every category definition, with translated labels.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{id: string, label: string, icon: string, default_open: bool}>
	 */
	public static function definitions(): array {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		$bundled = require AMORG_PLUGIN_DIR . 'includes/data/categories.php';
		$labels  = self::labels();

		$defaults = array();

		foreach ( (array) $bundled as $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['id'] ) ) {
				continue;
			}

			$id = (string) $definition['id'];

			$defaults[] = array(
				'id'           => $id,
				'label'        => $labels[ $id ] ?? (string) ( $definition['label'] ?? $id ),
				'icon'         => (string) ( $definition['icon'] ?? 'dashicons-menu-alt' ),
				'default_open' => ! empty( $definition['default_open'] ),
			);
		}

		/**
		 * Filters the available category definitions.
		 *
		 * Adding a category here makes it available to the detector and to the
		 * settings screen. Removing the ungrouped category has no effect: it is
		 * restored, because an item the detector cannot place must always have
		 * somewhere visible to go.
		 *
		 * @since 1.0.0
		 *
		 * @param array $defaults List of category definitions.
		 */
		$filtered = apply_filters( 'amorg_category_definitions', $defaults );

		self::$definitions = self::ensure_ungrouped( is_array( $filtered ) ? $filtered : $defaults );

		return self::$definitions;
	}

	/**
	 * Returns every valid category ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function ids(): array {
		return array_column( self::definitions(), 'id' );
	}

	/**
	 * Returns one definition, or null when the ID is unknown.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Category ID.
	 * @return array|null
	 */
	public static function get( string $id ): ?array {
		foreach ( self::definitions() as $definition ) {
			if ( $definition['id'] === $id ) {
				return $definition;
			}
		}

		return null;
	}

	/**
	 * Whether a category ID exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Category ID.
	 * @return bool
	 */
	public static function exists( string $id ): bool {
		return null !== self::get( $id );
	}

	/**
	 * Clears the memoised definitions.
	 *
	 * Needed by the test suite, and by any caller that registers a
	 * amorg_category_definitions filter after the definitions were first read.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$definitions = null;
	}

	/**
	 * Translated labels for the bundled categories.
	 *
	 * Each call passes the text domain as a literal string, as SPEC section 9
	 * requires.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private static function labels(): array {
		return array(
			'dashboard'    => __( 'Dashboard', 'admin-menu-categories' ),
			'content'      => __( 'Content', 'admin-menu-categories' ),
			'commerce'     => __( 'Commerce', 'admin-menu-categories' ),
			'design'       => __( 'Design & Layout', 'admin-menu-categories' ),
			'seo'          => __( 'SEO & Marketing', 'admin-menu-categories' ),
			'security'     => __( 'Security & Backup', 'admin-menu-categories' ),
			'performance'  => __( 'Performance', 'admin-menu-categories' ),
			'users'        => __( 'Users & Access', 'admin-menu-categories' ),
			'integrations' => __( 'Integrations', 'admin-menu-categories' ),
			'tools'        => __( 'Tools & System', 'admin-menu-categories' ),
			'ungrouped'    => __( 'Other', 'admin-menu-categories' ),
		);
	}

	/**
	 * Guarantees the catch-all category is present, appended last.
	 *
	 * @since 1.0.0
	 *
	 * @param array $definitions Candidate definitions.
	 * @return array
	 */
	private static function ensure_ungrouped( array $definitions ): array {
		$clean = array();
		$seen  = array();

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['id'] ) ) {
				continue;
			}

			$id = (string) $definition['id'];

			if ( self::UNGROUPED === $id || isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;

			$clean[] = array(
				'id'           => $id,
				'label'        => (string) ( $definition['label'] ?? $id ),
				'icon'         => (string) ( $definition['icon'] ?? 'dashicons-menu-alt' ),
				'default_open' => ! empty( $definition['default_open'] ),
			);
		}

		$clean[] = array(
			'id'           => self::UNGROUPED,
			'label'        => self::labels()[ self::UNGROUPED ],
			'icon'         => 'dashicons-menu-alt',
			'default_open' => true,
		);

		return $clean;
	}
}
