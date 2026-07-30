<?php
/**
 * REST endpoints.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

namespace AMORG;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the routes the sidebar and settings screen write through.
 *
 * Every route declares a permission_callback that calls current_user_can(). SPEC
 * section 8 forbids __return_true there, and for good reason: a
 * permission_callback is the only authentication a REST route has, so omitting or
 * weakening it exposes the endpoint to anyone who can reach the site.
 *
 * @since 1.0.0
 */
final class Rest_Controller {

	/**
	 * REST namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NAMESPACE_V1 = 'amorg/v1';

	/**
	 * Registers the REST hook.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers every route.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/state',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'save_state' ),
				'permission_callback' => array( __CLASS__, 'can_save_state' ),
				'args'                => array(
					'collapsed' => array(
						'required'    => true,
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Group IDs the user currently has collapsed.',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/layout',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_layout' ),
					'permission_callback' => array( __CLASS__, 'can_save_site_layout' ),
					'args'                => array(
						'layout' => array(
							'required'    => true,
							'type'        => 'object',
							'description' => 'The layout to store.',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/personal-layout',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_personal_layout' ),
					'permission_callback' => array( __CLASS__, 'can_personalise' ),
					'args'                => array(
						'layout' => array(
							'required'    => true,
							'type'        => 'object',
							'description' => 'The personal layout to store.',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'reset_personal_layout' ),
					'permission_callback' => array( __CLASS__, 'can_personalise' ),
				),
			)
		);
	}

	/**
	 * Whether the current user may save their collapsed state.
	 *
	 * Only requires being a logged-in user who can reach the admin at all. The
	 * value is a cosmetic per-user preference stored against that same user, so a
	 * subscriber writing their own is entirely legitimate. What matters is that
	 * they cannot write anybody else's, which is guaranteed by the callback using
	 * get_current_user_id() rather than accepting a user ID from the request.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error
	 */
	public static function can_save_state() {
		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			return new \WP_Error(
				'amorg_forbidden',
				__( 'You must be signed in to save your menu state.', 'admin-menu-categories' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Whether the current user may change the site-wide layout.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error
	 */
	public static function can_save_site_layout() {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return new \WP_Error(
				'amorg_forbidden',
				__( 'You are not allowed to change the site-wide menu layout.', 'admin-menu-categories' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Whether the current user may change their own layout.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error
	 */
	public static function can_personalise() {
		if ( ! Capabilities::current_user_can_personalise() ) {
			return new \WP_Error(
				'amorg_forbidden',
				__( 'Personalising your menu is not available on this site.', 'admin-menu-categories' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Saves the current user's collapsed groups.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public static function save_state( \WP_REST_Request $request ): \WP_REST_Response {
		$collapsed = Layout_Repository::save_collapsed(
			get_current_user_id(),
			$request->get_param( 'collapsed' )
		);

		return new \WP_REST_Response(
			array(
				'collapsed' => $collapsed,
			),
			200
		);
	}

	/**
	 * Saves the site-wide layout.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public static function save_layout( \WP_REST_Request $request ): \WP_REST_Response {
		$reader = Menu_Reader::from_globals();

		$layout = Layout_Repository::save_site_layout( $request->get_param( 'layout' ), $reader );

		return new \WP_REST_Response(
			array(
				'layout' => $layout,
			),
			200
		);
	}

	/**
	 * Saves the current user's personal layout.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public static function save_personal_layout( \WP_REST_Request $request ): \WP_REST_Response {
		$layout = Layout_Repository::save_user_layout(
			get_current_user_id(),
			$request->get_param( 'layout' ),
			Menu_Reader::from_globals()
		);

		return new \WP_REST_Response(
			array(
				'layout' => $layout,
			),
			200
		);
	}

	/**
	 * Removes the current user's personal layout.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_REST_Response
	 */
	public static function reset_personal_layout(): \WP_REST_Response {
		Layout_Repository::delete_user_layout( get_current_user_id() );

		return new \WP_REST_Response( array( 'reset' => true ), 200 );
	}
}
