<?php
/**
 * Roles tab: per-role presets and the personalisation switch.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 */

use AMORG\Admin\Settings_Page;
use AMORG\Capabilities;
use AMORG\Layout_Repository;

defined( 'ABSPATH' ) || exit;

$amorg_roles     = wp_roles();
$amorg_role_list = $amorg_roles instanceof WP_Roles ? $amorg_roles->roles : array();
$amorg_presets   = get_option( \AMORG\Plugin::OPTION_ROLE_LAYOUTS, array() );
$amorg_presets   = is_array( $amorg_presets ) ? $amorg_presets : array();
?>

<p class="amorg-intro">
	<?php echo esc_html__( 'Give a role its own arrangement, or let it inherit the site-wide layout. A role with its own copy can then be edited independently on the Layout tab while that role is selected.', 'admin-menu-categories' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=roles' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="amorg_action" value="save_roles">

	<table class="widefat striped">
		<caption class="screen-reader-text">
			<?php echo esc_html__( 'Menu layout for each role', 'admin-menu-categories' ); ?>
		</caption>
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Role', 'admin-menu-categories' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Menu layout', 'admin-menu-categories' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $amorg_role_list as $amorg_slug => $amorg_role ) : ?>
				<?php $amorg_has_preset = isset( $amorg_presets[ $amorg_slug ] ) && ! empty( $amorg_presets[ $amorg_slug ]['groups'] ); ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( translate_user_role( $amorg_role['name'] ) ); ?>
						<p class="description"><code><?php echo esc_html( $amorg_slug ); ?></code></p>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: Role name. */
										__( 'Menu layout for %s', 'admin-menu-categories' ),
										translate_user_role( $amorg_role['name'] )
									)
								);
								?>
							</legend>

							<label>
								<input
									type="radio"
									name="amorg_role_source[<?php echo esc_attr( $amorg_slug ); ?>]"
									value="site"
									<?php checked( ! $amorg_has_preset ); ?>
								>
								<?php echo esc_html__( 'Use the site-wide layout', 'admin-menu-categories' ); ?>
							</label>
							<br>
							<label>
								<input
									type="radio"
									name="amorg_role_source[<?php echo esc_attr( $amorg_slug ); ?>]"
									value="copy"
									<?php checked( $amorg_has_preset ); ?>
								>
								<?php echo esc_html__( 'Give this role its own copy', 'admin-menu-categories' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php submit_button( __( 'Save roles', 'admin-menu-categories' ) ); ?>
</form>

<hr>

<h2><?php echo esc_html__( 'Personal layouts', 'admin-menu-categories' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=roles' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="amorg_action" value="save_settings">

	<p>
		<label>
			<input
				type="checkbox"
				name="amorg_personalisation"
				value="1"
				<?php checked( Capabilities::personalisation_enabled() ); ?>
			>
			<?php echo esc_html__( 'Let each user arrange their own sidebar', 'admin-menu-categories' ); ?>
		</label>
	</p>

	<p class="description">
		<?php echo esc_html__( 'When this is off, everyone sees the layout you have set here and the "Personalise my menu" tab disappears. Any personal arrangements already saved are kept, and take effect again if you switch this back on.', 'admin-menu-categories' ); ?>
	</p>

	<?php submit_button( __( 'Save', 'admin-menu-categories' ) ); ?>
</form>
