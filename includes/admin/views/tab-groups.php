<?php
/**
 * Groups tab: rename, reorder, set icon and default state.
 *
 * Deleting a group is offered as "empty into Other" rather than as a delete,
 * because the categories themselves are defined in code and filterable. Emptying
 * has the outcome an administrator actually wants, and cannot orphan an item.
 *
 * @package AdminMenuOrganizer
 * @since   1.0.0
 *
 * @var array $amorg_layout Layout being edited.
 */

use AMORG\Admin\Settings_Page;
use AMORG\Categories;

defined( 'ABSPATH' ) || exit;

$amorg_dashicons = array(
	'dashicons-menu-alt'         => __( 'Menu', 'admin-menu-organizer' ),
	'dashicons-dashboard'        => __( 'Dashboard', 'admin-menu-organizer' ),
	'dashicons-admin-post'       => __( 'Post', 'admin-menu-organizer' ),
	'dashicons-admin-page'       => __( 'Page', 'admin-menu-organizer' ),
	'dashicons-admin-media'      => __( 'Media', 'admin-menu-organizer' ),
	'dashicons-cart'             => __( 'Cart', 'admin-menu-organizer' ),
	'dashicons-products'         => __( 'Products', 'admin-menu-organizer' ),
	'dashicons-admin-appearance' => __( 'Appearance', 'admin-menu-organizer' ),
	'dashicons-chart-line'       => __( 'Chart', 'admin-menu-organizer' ),
	'dashicons-megaphone'        => __( 'Megaphone', 'admin-menu-organizer' ),
	'dashicons-shield'           => __( 'Shield', 'admin-menu-organizer' ),
	'dashicons-backup'           => __( 'Backup', 'admin-menu-organizer' ),
	'dashicons-performance'      => __( 'Performance', 'admin-menu-organizer' ),
	'dashicons-groups'           => __( 'Groups', 'admin-menu-organizer' ),
	'dashicons-admin-links'      => __( 'Links', 'admin-menu-organizer' ),
	'dashicons-admin-tools'      => __( 'Tools', 'admin-menu-organizer' ),
	'dashicons-admin-settings'   => __( 'Settings', 'admin-menu-organizer' ),
	'dashicons-translation'      => __( 'Translation', 'admin-menu-organizer' ),
	'dashicons-email'            => __( 'Email', 'admin-menu-organizer' ),
	'dashicons-book'             => __( 'Book', 'admin-menu-organizer' ),
);
?>

<p class="amorg-intro">
	<?php echo esc_html__( 'Rename groups, choose their icons, set whether they start open, and drag them into the order you want them to appear in the sidebar.', 'admin-menu-organizer' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=groups' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="amorg_action" value="save_groups">

	<table class="widefat striped amorg-groups-table">
		<caption class="screen-reader-text">
			<?php echo esc_html__( 'Menu groups and their settings', 'admin-menu-organizer' ); ?>
		</caption>
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Order', 'admin-menu-organizer' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Name', 'admin-menu-organizer' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Icon', 'admin-menu-organizer' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Starts open', 'admin-menu-organizer' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Items', 'admin-menu-organizer' ); ?></th>
			</tr>
		</thead>
		<tbody class="amorg-group-rows">
			<?php foreach ( (array) ( $amorg_layout['groups'] ?? array() ) as $amorg_index => $amorg_group ) : ?>
				<?php
				$amorg_id         = (string) $amorg_group['id'];
				$amorg_definition = Categories::get( $amorg_id );
				$amorg_label      = $amorg_group['label'] ?? ( $amorg_definition['label'] ?? $amorg_id );
				$amorg_icon       = $amorg_group['icon'] ?? ( $amorg_definition['icon'] ?? 'dashicons-menu-alt' );
				$amorg_permanent  = ( Categories::UNGROUPED === $amorg_id );
				$amorg_open       = array_key_exists( 'default_open', $amorg_group )
					? ! empty( $amorg_group['default_open'] )
					: ! empty( $amorg_definition['default_open'] );
				?>
				<tr data-amorg-group="<?php echo esc_attr( $amorg_id ); ?>">
					<td class="amorg-order-cell">
						<span class="amorg-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>

						<?php /* Keyboard equivalent for reordering, per SPEC 7.2. */ ?>
						<label>
							<span class="screen-reader-text">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: Group name. */
										__( 'Position of %s', 'admin-menu-organizer' ),
										$amorg_label
									)
								);
								?>
							</span>
							<input
								type="number"
								class="small-text amorg-position"
								value="<?php echo esc_attr( (string) ( (int) $amorg_index + 1 ) ); ?>"
								min="1"
								step="1"
							>
						</label>
					</td>

					<td>
						<label>
							<span class="screen-reader-text">
								<?php echo esc_html__( 'Group name', 'admin-menu-organizer' ); ?>
							</span>
							<input
								type="text"
								class="regular-text"
								name="amorg_group_label[<?php echo esc_attr( $amorg_id ); ?>]"
								value="<?php echo esc_attr( $amorg_label ); ?>"
								maxlength="60"
							>
						</label>
						<p class="description"><code><?php echo esc_html( $amorg_id ); ?></code></p>
					</td>

					<td>
						<label>
							<span class="screen-reader-text">
								<?php echo esc_html__( 'Group icon', 'admin-menu-organizer' ); ?>
							</span>
							<select name="amorg_group_icon[<?php echo esc_attr( $amorg_id ); ?>]">
								<?php foreach ( $amorg_dashicons as $amorg_choice => $amorg_choice_label ) : ?>
									<option value="<?php echo esc_attr( $amorg_choice ); ?>" <?php selected( $amorg_choice, $amorg_icon ); ?>>
										<?php echo esc_html( $amorg_choice_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<span class="dashicons <?php echo esc_attr( $amorg_icon ); ?>" aria-hidden="true"></span>
					</td>

					<td>
						<?php if ( $amorg_permanent ) : ?>
							<em><?php echo esc_html__( 'Always open', 'admin-menu-organizer' ); ?></em>
						<?php else : ?>
							<label>
								<input
									type="checkbox"
									name="amorg_group_open[<?php echo esc_attr( $amorg_id ); ?>]"
									value="1"
									<?php checked( $amorg_open ); ?>
								>
								<span class="screen-reader-text">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: Group name. */
											__( '%s starts open', 'admin-menu-organizer' ),
											$amorg_label
										)
									);
									?>
								</span>
							</label>
						<?php endif; ?>
					</td>

					<td><?php echo esc_html( (string) count( (array) ( $amorg_group['items'] ?? array() ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<input
		type="hidden"
		name="amorg_group_order"
		id="amorg-group-order"
		value="<?php echo esc_attr( implode( ',', array_column( (array) ( $amorg_layout['groups'] ?? array() ), 'id' ) ) ); ?>"
	>

	<?php submit_button( __( 'Save groups', 'admin-menu-organizer' ) ); ?>
</form>
