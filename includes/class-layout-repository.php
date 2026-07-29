<?php
/**
 * Reading, resolving and saving layouts.
 *
 * @package AdminMenuOrganizer
 * @since   1.0.0
 */

namespace AMORG;

defined( 'ABSPATH' ) || exit;

/**
 * The only class that reads or writes a stored layout.
 *
 * Everything that comes out of here has been through Migrations then
 * Layout_Sanitizer, in that order, so callers never see a raw stored value. That
 * is the whole point: SPEC section 4.6 forbids assuming a shape, and section 8
 * requires stored slugs to be validated against the live menu, and doing both in
 * one place is the only way to be sure it always happens.
 *
 * @since 1.0.0
 */
final class Layout_Repository {

	/**
	 * Memoised resolved layout, keyed by user ID.
	 *
	 * Resolution runs on every admin page load and involves several option reads
	 * plus a full detection pass, so it is worth not repeating within a request.
	 *
	 * @since 1.0.0
	 * @var array<int, array>
	 */
	private static $resolved = array();

	/**
	 * Resolves the layout that should be used for a user.
	 *
	 * Resolution order is SPEC section 4.4: a personal layout if the user has one
	 * and is allowed one, then a preset for their primary role, then the
	 * site-wide layout, then auto-detection.
	 *
	 * Whatever the source, any live menu item the layout does not mention is
	 * appended to its detected group. SPEC section 5.4 requires that: detection
	 * continues to place newly seen items, but must never re-sort or overwrite
	 * something a human has moved.
	 *
	 * @since 1.0.0
	 *
	 * @param Menu_Reader $reader  Reader over the live menu.
	 * @param int|null    $user_id User to resolve for. Defaults to the current user.
	 * @return array Sanitised, complete layout.
	 */
	public static function resolve( Menu_Reader $reader, ?int $user_id = null ): array {
		$user_id = null === $user_id ? get_current_user_id() : $user_id;

		if ( isset( self::$resolved[ $user_id ] ) ) {
			return self::$resolved[ $user_id ];
		}

		$stored = self::stored_for( $user_id, $reader );

		// An empty layout means nothing usable was stored, so start from what
		// detection would produce rather than from an empty sidebar.
		$layout = empty( $stored['groups'] )
			? self::auto_detected( $reader )
			: $stored;

		$layout = self::absorb_new_items( $layout, $reader );

		/**
		 * Filters the final layout immediately before it is used to render.
		 *
		 * The value has already been migrated and sanitised. Anything returned
		 * here is used as-is, so a filter that introduces an unknown group ID or
		 * a stale slug will produce an item in the always-visible Other group
		 * rather than a fatal error, but it is the filter's job to be sensible.
		 *
		 * @since 1.0.0
		 *
		 * @param array       $layout  The resolved layout.
		 * @param Menu_Reader $reader  Reader over the live menu.
		 * @param int         $user_id User the layout was resolved for.
		 */
		$layout = apply_filters( 'amorg_resolved_layout', $layout, $reader, $user_id );

		self::$resolved[ $user_id ] = is_array( $layout ) ? $layout : array(
			'schema' => 1,
			'groups' => array(),
		);

		return self::$resolved[ $user_id ];
	}

	/**
	 * Returns the highest-priority stored layout for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $user_id User ID.
	 * @param Menu_Reader $reader  Reader over the live menu.
	 * @return array Sanitised layout, possibly with no groups.
	 */
	private static function stored_for( int $user_id, Menu_Reader $reader ): array {
		if ( $user_id > 0 && user_can( $user_id, Capabilities::PERSONALISE ) ) {
			$personal = self::user_layout( $user_id, $reader );

			if ( ! empty( $personal['groups'] ) ) {
				return $personal;
			}
		}

		foreach ( self::roles_for( $user_id ) as $role ) {
			$preset = self::role_layout( $role, $reader );

			if ( ! empty( $preset['groups'] ) ) {
				return $preset;
			}
		}

		return self::site_layout( $reader );
	}

	/**
	 * Returns a user's roles, primary first.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return string[]
	 */
	private static function roles_for( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$user = get_userdata( $user_id );

		if ( ! $user || ! is_array( $user->roles ) ) {
			return array();
		}

		return array_values( array_filter( $user->roles, 'is_string' ) );
	}

	/**
	 * Reads the site-wide layout.
	 *
	 * @since 1.0.0
	 *
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return array
	 */
	public static function site_layout( Menu_Reader $reader ): array {
		return self::prepare( get_option( Plugin::OPTION_LAYOUT, array() ), $reader );
	}

	/**
	 * Reads a role preset.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $role   Role slug.
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return array
	 */
	public static function role_layout( string $role, Menu_Reader $reader ): array {
		$presets = get_option( Plugin::OPTION_ROLE_LAYOUTS, array() );

		if ( ! is_array( $presets ) || ! isset( $presets[ $role ] ) ) {
			return Migrations::empty_layout();
		}

		return self::prepare( $presets[ $role ], $reader );
	}

	/**
	 * Reads a user's personal layout.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $user_id User ID.
	 * @param Menu_Reader $reader  Reader over the live menu.
	 * @return array
	 */
	public static function user_layout( int $user_id, Menu_Reader $reader ): array {
		if ( $user_id <= 0 ) {
			return Migrations::empty_layout();
		}

		return self::prepare( get_user_meta( $user_id, Plugin::META_USER_LAYOUT, true ), $reader );
	}

	/**
	 * Whether a user has a personal layout stored.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_user_layout( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		$stored = get_user_meta( $user_id, Plugin::META_USER_LAYOUT, true );

		return is_array( $stored ) && ! empty( $stored );
	}

	/**
	 * Saves the site-wide layout.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed       $raw    Untrusted layout, typically from a form or REST body.
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return array The sanitised layout as stored.
	 */
	public static function save_site_layout( $raw, Menu_Reader $reader ): array {
		$layout = self::prepare( $raw, $reader );

		update_option( Plugin::OPTION_LAYOUT, $layout );
		self::flush();

		return $layout;
	}

	/**
	 * Saves a role preset.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $role   Role slug.
	 * @param mixed       $raw    Untrusted layout.
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return array The sanitised layout as stored.
	 */
	public static function save_role_layout( string $role, $raw, Menu_Reader $reader ): array {
		$role = Layout_Sanitizer::sanitize_key( $role );

		if ( '' === $role || ! self::role_exists( $role ) ) {
			return Migrations::empty_layout();
		}

		$presets = get_option( Plugin::OPTION_ROLE_LAYOUTS, array() );
		$presets = is_array( $presets ) ? $presets : array();

		$layout           = self::prepare( $raw, $reader );
		$presets[ $role ] = $layout;

		update_option( Plugin::OPTION_ROLE_LAYOUTS, $presets );
		self::flush();

		return $layout;
	}

	/**
	 * Deletes a role preset, so that role falls back to the site-wide layout.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role Role slug.
	 * @return void
	 */
	public static function delete_role_layout( string $role ): void {
		$presets = get_option( Plugin::OPTION_ROLE_LAYOUTS, array() );

		if ( ! is_array( $presets ) ) {
			return;
		}

		unset( $presets[ Layout_Sanitizer::sanitize_key( $role ) ] );

		update_option( Plugin::OPTION_ROLE_LAYOUTS, $presets );
		self::flush();
	}

	/**
	 * Saves a user's personal layout.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $user_id User ID.
	 * @param mixed       $raw     Untrusted layout.
	 * @param Menu_Reader $reader  Reader over the live menu.
	 * @return array The sanitised layout as stored.
	 */
	public static function save_user_layout( int $user_id, $raw, Menu_Reader $reader ): array {
		if ( $user_id <= 0 ) {
			return Migrations::empty_layout();
		}

		$layout = self::prepare( $raw, $reader );

		update_user_meta( $user_id, Plugin::META_USER_LAYOUT, $layout );
		self::flush();

		return $layout;
	}

	/**
	 * Removes a user's personal layout, returning them to the site default.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function delete_user_layout( int $user_id ): void {
		if ( $user_id > 0 ) {
			delete_user_meta( $user_id, Plugin::META_USER_LAYOUT );
			self::flush();
		}
	}

	/**
	 * Reads a user's collapsed group IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return string[]
	 */
	public static function collapsed( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		return Layout_Sanitizer::sanitize_collapsed(
			get_user_meta( $user_id, Plugin::META_COLLAPSED, true ),
			Categories::ids()
		);
	}

	/**
	 * Saves a user's collapsed group IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id User ID.
	 * @param mixed $raw     Untrusted list of group IDs.
	 * @return string[] The list as stored.
	 */
	public static function save_collapsed( int $user_id, $raw ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$collapsed = Layout_Sanitizer::sanitize_collapsed( $raw, Categories::ids() );

		update_user_meta( $user_id, Plugin::META_COLLAPSED, $collapsed );

		return $collapsed;
	}

	/**
	 * Builds a layout purely from auto-detection.
	 *
	 * @since 1.0.0
	 *
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return array
	 */
	public static function auto_detected( Menu_Reader $reader ): array {
		$detected = Detector::create()->detect_all( $reader );

		$groups = array();

		foreach ( Categories::definitions() as $definition ) {
			$items = array_keys( $detected, $definition['id'], true );

			// An empty group is still emitted, so its position and settings
			// survive a plugin being temporarily deactivated.
			$groups[] = array(
				'id'           => $definition['id'],
				'label'        => $definition['label'],
				'icon'         => $definition['icon'],
				'default_open' => $definition['default_open'],
				'items'        => array_values( $items ),
			);
		}

		return array(
			'schema' => Migrations::CURRENT,
			'groups' => $groups,
		);
	}

	/**
	 * Appends any live menu item the layout does not mention to its detected group.
	 *
	 * This is what makes a newly activated plugin appear in a sensible place
	 * without disturbing anything already arranged. SPEC section 5.4 requires it
	 * to be additive only, so existing groups are never re-sorted and an item a
	 * human moved is never moved back.
	 *
	 * Nothing is written to the database here. Persisting on a page load would
	 * mean a GET request with a side effect, and the appended placement is
	 * recalculated identically on the next load anyway. The settings screen
	 * persists it when the administrator saves.
	 *
	 * @since 1.0.0
	 *
	 * @param array       $layout Sanitised layout.
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return array
	 */
	private static function absorb_new_items( array $layout, Menu_Reader $reader ): array {
		$placed = array();

		$groups = isset( $layout['groups'] ) && is_array( $layout['groups'] ) ? $layout['groups'] : array();

		foreach ( $groups as $group ) {
			foreach ( (array) ( $group['items'] ?? array() ) as $slug ) {
				if ( is_string( $slug ) ) {
					$placed[ $slug ] = true;
				}
			}
		}

		$missing = array();

		foreach ( $reader->organizable_slugs() as $slug ) {
			if ( ! isset( $placed[ $slug ] ) ) {
				$missing[] = $slug;
			}
		}

		if ( empty( $missing ) ) {
			return $layout;
		}

		$detector = Detector::create();
		$by_group = array();

		foreach ( $missing as $slug ) {
			$item = $reader->get( $slug );

			if ( null === $item ) {
				continue;
			}

			$group_id = $detector->detect( $item );

			$by_group[ $group_id ][] = $slug;
		}

		$existing = array_column( $groups, 'id' );

		foreach ( $by_group as $group_id => $slugs ) {
			$index = array_search( $group_id, $existing, true );

			if ( false !== $index ) {
				$groups[ $index ]['items'] = array_merge(
					(array) ( $groups[ $index ]['items'] ?? array() ),
					$slugs
				);

				continue;
			}

			// The detected group is not in this layout at all, which happens when
			// an administrator deleted it. Create it from its definition so the
			// items have a home rather than silently falling to the catch-all.
			$definition = Categories::get( (string) $group_id );

			if ( null === $definition ) {
				$group_id   = Categories::UNGROUPED;
				$definition = Categories::get( Categories::UNGROUPED );
			}

			$catch_all = array_search( $group_id, $existing, true );

			if ( false !== $catch_all ) {
				$groups[ $catch_all ]['items'] = array_merge(
					(array) ( $groups[ $catch_all ]['items'] ?? array() ),
					$slugs
				);

				continue;
			}

			$groups[]   = array(
				'id'           => $group_id,
				'label'        => $definition['label'] ?? $group_id,
				'icon'         => $definition['icon'] ?? 'dashicons-menu-alt',
				'default_open' => ! empty( $definition['default_open'] ),
				'items'        => $slugs,
			);
			$existing[] = $group_id;
		}

		$layout['groups'] = $groups;

		return $layout;
	}

	/**
	 * Migrates then sanitises a raw stored value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed       $raw    Untrusted value.
	 * @param Menu_Reader $reader Reader over the live menu.
	 * @return array
	 */
	private static function prepare( $raw, Menu_Reader $reader ): array {
		return Layout_Sanitizer::sanitize(
			Migrations::migrate( $raw ),
			Categories::ids(),
			$reader->organizable_slugs()
		);
	}

	/**
	 * Whether a role exists on this site.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role Role slug.
	 * @return bool
	 */
	private static function role_exists( string $role ): bool {
		$roles = wp_roles();

		return $roles instanceof \WP_Roles && isset( $roles->roles[ $role ] );
	}

	/**
	 * Clears the memoised resolution.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$resolved = array();
	}
}
