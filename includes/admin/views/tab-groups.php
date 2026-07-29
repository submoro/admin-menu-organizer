<?php
/**
 * Groups tab: rename, reorder, set icon and default state.
 *
 * Deleting a group is offered as "empty into Other" rather than as a delete,
 * because the categories themselves are defined in code and filterable. Emptying
 * has the outcome an administrator actually wants, and cannot orphan an item.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 *
 * @var array $wpamo_layout Layout being edited.
 */

use WPAMO\Admin\Settings_Page;
use WPAMO\Categories;

defined( 'ABSPATH' ) || exit;

$wpamo_dashicons = array(
	'dashicons-menu-alt'         => __( 'Menu', 'wp-admin-menu-organizer' ),
	'dashicons-dashboard'        => __( 'Dashboard', 'wp-admin-menu-organizer' ),
	'dashicons-admin-post'       => __( 'Post', 'wp-admin-menu-organizer' ),
	'dashicons-admin-page'       => __( 'Page', 'wp-admin-menu-organizer' ),
	'dashicons-admin-media'      => __( 'Media', 'wp-admin-menu-organizer' ),
	'dashicons-cart'             => __( 'Cart', 'wp-admin-menu-organizer' ),
	'dashicons-products'         => __( 'Products', 'wp-admin-menu-organizer' ),
	'dashicons-admin-appearance' => __( 'Appearance', 'wp-admin-menu-organizer' ),
	'dashicons-chart-line'       => __( 'Chart', 'wp-admin-menu-organizer' ),
	'dashicons-megaphone'        => __( 'Megaphone', 'wp-admin-menu-organizer' ),
	'dashicons-shield'           => __( 'Shield', 'wp-admin-menu-organizer' ),
	'dashicons-backup'           => __( 'Backup', 'wp-admin-menu-organizer' ),
	'dashicons-performance'      => __( 'Performance', 'wp-admin-menu-organizer' ),
	'dashicons-groups'           => __( 'Groups', 'wp-admin-menu-organizer' ),
	'dashicons-admin-links'      => __( 'Links', 'wp-admin-menu-organizer' ),
	'dashicons-admin-tools'      => __( 'Tools', 'wp-admin-menu-organizer' ),
	'dashicons-admin-settings'   => __( 'Settings', 'wp-admin-menu-organizer' ),
	'dashicons-translation'      => __( 'Translation', 'wp-admin-menu-organizer' ),
	'dashicons-email'            => __( 'Email', 'wp-admin-menu-organizer' ),
	'dashicons-book'             => __( 'Book', 'wp-admin-menu-organizer' ),
);
?>

<p class="wpamo-intro">
	<?php echo esc_html__( 'Rename groups, choose their icons, set whether they start open, and drag them into the order you want them to appear in the sidebar.', 'wp-admin-menu-organizer' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=groups' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="wpamo_action" value="save_groups">

	<table class="widefat striped wpamo-groups-table">
		<caption class="screen-reader-text">
			<?php echo esc_html__( 'Menu groups and their settings', 'wp-admin-menu-organizer' ); ?>
		</caption>
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Order', 'wp-admin-menu-organizer' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Name', 'wp-admin-menu-organizer' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Icon', 'wp-admin-menu-organizer' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Starts open', 'wp-admin-menu-organizer' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Items', 'wp-admin-menu-organizer' ); ?></th>
			</tr>
		</thead>
		<tbody class="wpamo-group-rows">
			<?php foreach ( (array) ( $wpamo_layout['groups'] ?? array() ) as $wpamo_index => $wpamo_group ) : ?>
				<?php
				$wpamo_id         = (string) $wpamo_group['id'];
				$wpamo_definition = Categories::get( $wpamo_id );
				$wpamo_label      = $wpamo_group['label'] ?? ( $wpamo_definition['label'] ?? $wpamo_id );
				$wpamo_icon       = $wpamo_group['icon'] ?? ( $wpamo_definition['icon'] ?? 'dashicons-menu-alt' );
				$wpamo_permanent  = ( Categories::UNGROUPED === $wpamo_id );
				$wpamo_open       = array_key_exists( 'default_open', $wpamo_group )
					? ! empty( $wpamo_group['default_open'] )
					: ! empty( $wpamo_definition['default_open'] );
				?>
				<tr data-wpamo-group="<?php echo esc_attr( $wpamo_id ); ?>">
					<td class="wpamo-order-cell">
						<span class="wpamo-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>

						<?php /* Keyboard equivalent for reordering, per SPEC 7.2. */ ?>
						<label>
							<span class="screen-reader-text">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: Group name. */
										__( 'Position of %s', 'wp-admin-menu-organizer' ),
										$wpamo_label
									)
								);
								?>
							</span>
							<input
								type="number"
								class="small-text wpamo-position"
								value="<?php echo esc_attr( (string) ( (int) $wpamo_index + 1 ) ); ?>"
								min="1"
								step="1"
							>
						</label>
					</td>

					<td>
						<label>
							<span class="screen-reader-text">
								<?php echo esc_html__( 'Group name', 'wp-admin-menu-organizer' ); ?>
							</span>
							<input
								type="text"
								class="regular-text"
								name="wpamo_group_label[<?php echo esc_attr( $wpamo_id ); ?>]"
								value="<?php echo esc_attr( $wpamo_label ); ?>"
								maxlength="60"
							>
						</label>
						<p class="description"><code><?php echo esc_html( $wpamo_id ); ?></code></p>
					</td>

					<td>
						<label>
							<span class="screen-reader-text">
								<?php echo esc_html__( 'Group icon', 'wp-admin-menu-organizer' ); ?>
							</span>
							<select name="wpamo_group_icon[<?php echo esc_attr( $wpamo_id ); ?>]">
								<?php foreach ( $wpamo_dashicons as $wpamo_choice => $wpamo_choice_label ) : ?>
									<option value="<?php echo esc_attr( $wpamo_choice ); ?>" <?php selected( $wpamo_choice, $wpamo_icon ); ?>>
										<?php echo esc_html( $wpamo_choice_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<span class="dashicons <?php echo esc_attr( $wpamo_icon ); ?>" aria-hidden="true"></span>
					</td>

					<td>
						<?php if ( $wpamo_permanent ) : ?>
							<em><?php echo esc_html__( 'Always open', 'wp-admin-menu-organizer' ); ?></em>
						<?php else : ?>
							<label>
								<input
									type="checkbox"
									name="wpamo_group_open[<?php echo esc_attr( $wpamo_id ); ?>]"
									value="1"
									<?php checked( $wpamo_open ); ?>
								>
								<span class="screen-reader-text">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: Group name. */
											__( '%s starts open', 'wp-admin-menu-organizer' ),
											$wpamo_label
										)
									);
									?>
								</span>
							</label>
						<?php endif; ?>
					</td>

					<td><?php echo esc_html( (string) count( (array) ( $wpamo_group['items'] ?? array() ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<input
		type="hidden"
		name="wpamo_group_order"
		id="wpamo-group-order"
		value="<?php echo esc_attr( implode( ',', array_column( (array) ( $wpamo_layout['groups'] ?? array() ), 'id' ) ) ); ?>"
	>

	<?php submit_button( __( 'Save groups', 'wp-admin-menu-organizer' ) ); ?>
</form>
