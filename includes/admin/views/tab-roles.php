<?php
/**
 * Roles tab: per-role presets and the personalisation switch.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 */

use MOCAM\Admin\Settings_Page;
use MOCAM\Capabilities;
use MOCAM\Layout_Repository;

defined( 'ABSPATH' ) || exit;

$mocam_roles     = wp_roles();
$mocam_role_list = $mocam_roles instanceof WP_Roles ? $mocam_roles->roles : array();
$mocam_presets   = get_option( \MOCAM\Plugin::OPTION_ROLE_LAYOUTS, array() );
$mocam_presets   = is_array( $mocam_presets ) ? $mocam_presets : array();
?>

<p class="mocam-intro">
	<?php echo esc_html__( 'Give a role its own arrangement, or let it inherit the site-wide layout. A role with its own copy can then be edited independently on the Layout tab while that role is selected.', 'menu-organizer-collapsible-admin-menu' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=roles' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="mocam_action" value="save_roles">

	<table class="widefat striped">
		<caption class="screen-reader-text">
			<?php echo esc_html__( 'Menu layout for each role', 'menu-organizer-collapsible-admin-menu' ); ?>
		</caption>
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Role', 'menu-organizer-collapsible-admin-menu' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Menu layout', 'menu-organizer-collapsible-admin-menu' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $mocam_role_list as $mocam_slug => $mocam_role ) : ?>
				<?php $mocam_has_preset = isset( $mocam_presets[ $mocam_slug ] ) && ! empty( $mocam_presets[ $mocam_slug ]['groups'] ); ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( translate_user_role( $mocam_role['name'] ) ); ?>
						<p class="description"><code><?php echo esc_html( $mocam_slug ); ?></code></p>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: Role name. */
										__( 'Menu layout for %s', 'menu-organizer-collapsible-admin-menu' ),
										translate_user_role( $mocam_role['name'] )
									)
								);
								?>
							</legend>

							<label>
								<input
									type="radio"
									name="mocam_role_source[<?php echo esc_attr( $mocam_slug ); ?>]"
									value="site"
									<?php checked( ! $mocam_has_preset ); ?>
								>
								<?php echo esc_html__( 'Use the site-wide layout', 'menu-organizer-collapsible-admin-menu' ); ?>
							</label>
							<br>
							<label>
								<input
									type="radio"
									name="mocam_role_source[<?php echo esc_attr( $mocam_slug ); ?>]"
									value="copy"
									<?php checked( $mocam_has_preset ); ?>
								>
								<?php echo esc_html__( 'Give this role its own copy', 'menu-organizer-collapsible-admin-menu' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php submit_button( __( 'Save roles', 'menu-organizer-collapsible-admin-menu' ) ); ?>
</form>

<hr>

<h2><?php echo esc_html__( 'Personal layouts', 'menu-organizer-collapsible-admin-menu' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=roles' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="mocam_action" value="save_settings">

	<p>
		<label>
			<input
				type="checkbox"
				name="mocam_personalisation"
				value="1"
				<?php checked( Capabilities::personalisation_enabled() ); ?>
			>
			<?php echo esc_html__( 'Let each user arrange their own sidebar', 'menu-organizer-collapsible-admin-menu' ); ?>
		</label>
	</p>

	<p class="description">
		<?php echo esc_html__( 'When this is off, everyone sees the layout you have set here and the "Personalise my menu" tab disappears. Any personal arrangements already saved are kept, and take effect again if you switch this back on.', 'menu-organizer-collapsible-admin-menu' ); ?>
	</p>

	<?php submit_button( __( 'Save', 'menu-organizer-collapsible-admin-menu' ) ); ?>
</form>
