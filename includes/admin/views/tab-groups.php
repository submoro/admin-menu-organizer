<?php
/**
 * Groups tab: rename, reorder, set icon and default state.
 *
 * Deleting a group is offered as "empty into Other" rather than as a delete,
 * because the categories themselves are defined in code and filterable. Emptying
 * has the outcome an administrator actually wants, and cannot orphan an item.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 *
 * @var array $mocam_layout Layout being edited.
 */

use MOCAM\Admin\Settings_Page;
use MOCAM\Categories;

defined( 'ABSPATH' ) || exit;

$mocam_dashicons = array(
	'dashicons-menu-alt'         => __( 'Menu', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-dashboard'        => __( 'Dashboard', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-admin-post'       => __( 'Post', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-admin-page'       => __( 'Page', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-admin-media'      => __( 'Media', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-cart'             => __( 'Cart', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-products'         => __( 'Products', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-admin-appearance' => __( 'Appearance', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-chart-line'       => __( 'Chart', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-megaphone'        => __( 'Megaphone', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-shield'           => __( 'Shield', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-backup'           => __( 'Backup', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-performance'      => __( 'Performance', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-groups'           => __( 'Groups', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-admin-links'      => __( 'Links', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-admin-tools'      => __( 'Tools', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-admin-settings'   => __( 'Settings', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-translation'      => __( 'Translation', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-email'            => __( 'Email', 'menu-organizer-collapsible-admin-menu' ),
	'dashicons-book'             => __( 'Book', 'menu-organizer-collapsible-admin-menu' ),
);
?>

<p class="mocam-intro">
	<?php echo esc_html__( 'Rename groups, choose their icons, set whether they start open, and drag them into the order you want them to appear in the sidebar.', 'menu-organizer-collapsible-admin-menu' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=groups' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="mocam_action" value="save_groups">

	<table class="widefat striped mocam-groups-table">
		<caption class="screen-reader-text">
			<?php echo esc_html__( 'Menu groups and their settings', 'menu-organizer-collapsible-admin-menu' ); ?>
		</caption>
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Order', 'menu-organizer-collapsible-admin-menu' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Name', 'menu-organizer-collapsible-admin-menu' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Icon', 'menu-organizer-collapsible-admin-menu' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Starts open', 'menu-organizer-collapsible-admin-menu' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Items', 'menu-organizer-collapsible-admin-menu' ); ?></th>
			</tr>
		</thead>
		<tbody class="mocam-group-rows">
			<?php foreach ( (array) ( $mocam_layout['groups'] ?? array() ) as $mocam_index => $mocam_group ) : ?>
				<?php
				$mocam_id         = (string) $mocam_group['id'];
				$mocam_definition = Categories::get( $mocam_id );
				$mocam_label      = $mocam_group['label'] ?? ( $mocam_definition['label'] ?? $mocam_id );
				$mocam_icon       = $mocam_group['icon'] ?? ( $mocam_definition['icon'] ?? 'dashicons-menu-alt' );
				$mocam_permanent  = ( Categories::UNGROUPED === $mocam_id );
				$mocam_open       = array_key_exists( 'default_open', $mocam_group )
					? ! empty( $mocam_group['default_open'] )
					: ! empty( $mocam_definition['default_open'] );
				?>
				<tr data-mocam-group="<?php echo esc_attr( $mocam_id ); ?>">
					<td class="mocam-order-cell">
						<span class="mocam-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>

						<?php /* Keyboard equivalent for reordering, per SPEC 7.2. */ ?>
						<label>
							<span class="screen-reader-text">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: Group name. */
										__( 'Position of %s', 'menu-organizer-collapsible-admin-menu' ),
										$mocam_label
									)
								);
								?>
							</span>
							<input
								type="number"
								class="small-text mocam-position"
								value="<?php echo esc_attr( (string) ( (int) $mocam_index + 1 ) ); ?>"
								min="1"
								step="1"
							>
						</label>
					</td>

					<td>
						<label>
							<span class="screen-reader-text">
								<?php echo esc_html__( 'Group name', 'menu-organizer-collapsible-admin-menu' ); ?>
							</span>
							<input
								type="text"
								class="regular-text"
								name="mocam_group_label[<?php echo esc_attr( $mocam_id ); ?>]"
								value="<?php echo esc_attr( $mocam_label ); ?>"
								maxlength="60"
							>
						</label>
						<p class="description"><code><?php echo esc_html( $mocam_id ); ?></code></p>
					</td>

					<td>
						<label>
							<span class="screen-reader-text">
								<?php echo esc_html__( 'Group icon', 'menu-organizer-collapsible-admin-menu' ); ?>
							</span>
							<select name="mocam_group_icon[<?php echo esc_attr( $mocam_id ); ?>]">
								<?php foreach ( $mocam_dashicons as $mocam_choice => $mocam_choice_label ) : ?>
									<option value="<?php echo esc_attr( $mocam_choice ); ?>" <?php selected( $mocam_choice, $mocam_icon ); ?>>
										<?php echo esc_html( $mocam_choice_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<span class="dashicons <?php echo esc_attr( $mocam_icon ); ?>" aria-hidden="true"></span>
					</td>

					<td>
						<?php if ( $mocam_permanent ) : ?>
							<em><?php echo esc_html__( 'Always open', 'menu-organizer-collapsible-admin-menu' ); ?></em>
						<?php else : ?>
							<label>
								<input
									type="checkbox"
									name="mocam_group_open[<?php echo esc_attr( $mocam_id ); ?>]"
									value="1"
									<?php checked( $mocam_open ); ?>
								>
								<span class="screen-reader-text">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: Group name. */
											__( '%s starts open', 'menu-organizer-collapsible-admin-menu' ),
											$mocam_label
										)
									);
									?>
								</span>
							</label>
						<?php endif; ?>
					</td>

					<td><?php echo esc_html( (string) count( (array) ( $mocam_group['items'] ?? array() ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<input
		type="hidden"
		name="mocam_group_order"
		id="mocam-group-order"
		value="<?php echo esc_attr( implode( ',', array_column( (array) ( $mocam_layout['groups'] ?? array() ), 'id' ) ) ); ?>"
	>

	<?php submit_button( __( 'Save groups', 'menu-organizer-collapsible-admin-menu' ) ); ?>
</form>
