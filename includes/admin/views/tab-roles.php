<?php
/**
 * Roles tab: per-role presets and the personalisation switch.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 */

use WPAMO\Admin\Settings_Page;
use WPAMO\Capabilities;
use WPAMO\Layout_Repository;

defined( 'ABSPATH' ) || exit;

$wpamo_roles     = wp_roles();
$wpamo_role_list = $wpamo_roles instanceof WP_Roles ? $wpamo_roles->roles : array();
$wpamo_presets   = get_option( \WPAMO\Plugin::OPTION_ROLE_LAYOUTS, array() );
$wpamo_presets   = is_array( $wpamo_presets ) ? $wpamo_presets : array();
?>

<p class="wpamo-intro">
	<?php echo esc_html__( 'Give a role its own arrangement, or let it inherit the site-wide layout. A role with its own copy can then be edited independently on the Layout tab while that role is selected.', 'wp-admin-menu-organizer' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=roles' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="wpamo_action" value="save_roles">

	<table class="widefat striped">
		<caption class="screen-reader-text">
			<?php echo esc_html__( 'Menu layout for each role', 'wp-admin-menu-organizer' ); ?>
		</caption>
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Role', 'wp-admin-menu-organizer' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Menu layout', 'wp-admin-menu-organizer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $wpamo_role_list as $wpamo_slug => $wpamo_role ) : ?>
				<?php $wpamo_has_preset = isset( $wpamo_presets[ $wpamo_slug ] ) && ! empty( $wpamo_presets[ $wpamo_slug ]['groups'] ); ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( translate_user_role( $wpamo_role['name'] ) ); ?>
						<p class="description"><code><?php echo esc_html( $wpamo_slug ); ?></code></p>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: Role name. */
										__( 'Menu layout for %s', 'wp-admin-menu-organizer' ),
										translate_user_role( $wpamo_role['name'] )
									)
								);
								?>
							</legend>

							<label>
								<input
									type="radio"
									name="wpamo_role_source[<?php echo esc_attr( $wpamo_slug ); ?>]"
									value="site"
									<?php checked( ! $wpamo_has_preset ); ?>
								>
								<?php echo esc_html__( 'Use the site-wide layout', 'wp-admin-menu-organizer' ); ?>
							</label>
							<br>
							<label>
								<input
									type="radio"
									name="wpamo_role_source[<?php echo esc_attr( $wpamo_slug ); ?>]"
									value="copy"
									<?php checked( $wpamo_has_preset ); ?>
								>
								<?php echo esc_html__( 'Give this role its own copy', 'wp-admin-menu-organizer' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php submit_button( __( 'Save roles', 'wp-admin-menu-organizer' ) ); ?>
</form>

<hr>

<h2><?php echo esc_html__( 'Personal layouts', 'wp-admin-menu-organizer' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=roles' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="wpamo_action" value="save_settings">

	<p>
		<label>
			<input
				type="checkbox"
				name="wpamo_personalisation"
				value="1"
				<?php checked( Capabilities::personalisation_enabled() ); ?>
			>
			<?php echo esc_html__( 'Let each user arrange their own sidebar', 'wp-admin-menu-organizer' ); ?>
		</label>
	</p>

	<p class="description">
		<?php echo esc_html__( 'When this is off, everyone sees the layout you have set here and the "Personalise my menu" tab disappears. Any personal arrangements already saved are kept, and take effect again if you switch this back on.', 'wp-admin-menu-organizer' ); ?>
	</p>

	<?php submit_button( __( 'Save', 'wp-admin-menu-organizer' ) ); ?>
</form>
