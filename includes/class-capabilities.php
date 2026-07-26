<?php
/**
 * Capability mapping.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 */

namespace MOCAM;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the plugin's meta capability.
 *
 * SPEC section 4.5 defines one new capability, mocam_personalise_menu, granted
 * by default to any role that can read, and switchable off globally by an
 * administrator. It is a meta capability rather than a real one, so nothing is
 * written to the roles table and uninstalling leaves no capability residue on
 * any user.
 *
 * Editing the site-wide layout and the role presets continues to require
 * manage_options. This capability governs only whether a user may arrange
 * *their own* sidebar.
 *
 * @since 1.0.0
 */
final class Capabilities {

	/**
	 * The meta capability that allows a user to personalise their own menu.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PERSONALISE = 'mocam_personalise_menu';

	/**
	 * The capability required to change site-wide settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const MANAGE = 'manage_options';

	/**
	 * Registers the capability mapping.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 10, 2 );
	}

	/**
	 * Resolves the personalisation meta capability to real capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $caps Primitive capabilities required of the user.
	 * @param string   $cap  Capability being checked.
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap ): array {
		$caps = is_array( $caps ) ? $caps : array();

		if ( self::PERSONALISE !== $cap ) {
			return $caps;
		}

		/*
		 * do_not_allow is core's canonical "never" capability. Returning it
		 * denies the check outright, which is how the global off switch works:
		 * no role gains or loses a stored capability, the answer simply changes.
		 */
		if ( ! self::personalisation_enabled() ) {
			return array( 'do_not_allow' );
		}

		return array( 'read' );
	}

	/**
	 * Whether per-user personalisation is enabled site-wide.
	 *
	 * Defaults to enabled, so a fresh install behaves as SPEC section 4.5
	 * describes without an administrator having to opt in.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function personalisation_enabled(): bool {
		$settings = get_option( Plugin::OPTION_SETTINGS, array() );

		if ( ! is_array( $settings ) || ! array_key_exists( 'personalisation', $settings ) ) {
			return true;
		}

		return Layout_Sanitizer::sanitize_bool( $settings['personalisation'] );
	}

	/**
	 * Whether the current user may personalise their own menu.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function current_user_can_personalise(): bool {
		return is_user_logged_in() && current_user_can( self::PERSONALISE );
	}

	/**
	 * Whether the current user may change site-wide settings.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( self::MANAGE );
	}
}
