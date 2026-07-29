<?php
/**
 * Layout tab: the drag-and-drop editor.
 *
 * Also serves the Personalise tab, which is the same editor scoped to the current
 * user's own meta rather than to the site option.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 *
 * @var string $tab          Current tab slug.
 * @var array  $wpamo_layout Layout being edited.
 * @var array  $wpamo_items  Index of every organizable menu item.
 */

use WPAMO\Admin\Settings_Page;
use WPAMO\Categories;
use WPAMO\Layout_Repository;

defined( 'ABSPATH' ) || exit;

$wpamo_personal = ( 'personal' === $tab );
$wpamo_action   = $wpamo_personal ? 'save_personal' : 'save_layout';
$wpamo_nonce    = $wpamo_personal ? Settings_Page::NONCE_PERSONAL : Settings_Page::NONCE_SITE;

// Which group each item currently sits in, so unplaced items can be pooled.
$wpamo_placed = array();

foreach ( (array) ( $wpamo_layout['groups'] ?? array() ) as $wpamo_group ) {
	foreach ( (array) ( $wpamo_group['items'] ?? array() ) as $wpamo_slug ) {
		$wpamo_placed[ $wpamo_slug ] = $wpamo_group['id'];
	}
}

$wpamo_group_choices = array();

foreach ( (array) ( $wpamo_layout['groups'] ?? array() ) as $wpamo_group ) {
	$wpamo_definition                          = Categories::get( $wpamo_group['id'] );
	$wpamo_group_choices[ $wpamo_group['id'] ] = $wpamo_group['label']
		?? ( $wpamo_definition['label'] ?? $wpamo_group['id'] );
}
?>

<?php if ( $wpamo_personal ) : ?>
	<p class="wpamo-intro">
		<?php echo esc_html__( 'Arrange your own sidebar. This affects only your account; everyone else keeps the site default.', 'wp-admin-menu-organizer' ); ?>
	</p>
<?php else : ?>
	<p class="wpamo-intro">
		<?php echo esc_html__( 'Drag items between groups to arrange the sidebar for everyone. Every item stays reachable: anything you do not place appears in the Other group at the bottom.', 'wp-admin-menu-organizer' ); ?>
	</p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=' . $tab ) ); ?>" class="wpamo-editor-form">
	<?php wp_nonce_field( $wpamo_nonce ); ?>
	<input type="hidden" name="wpamo_action" value="<?php echo esc_attr( $wpamo_action ); ?>">

	<div class="wpamo-editor">
		<?php foreach ( (array) ( $wpamo_layout['groups'] ?? array() ) as $wpamo_group ) : ?>
			<?php
			$wpamo_id         = (string) $wpamo_group['id'];
			$wpamo_definition = Categories::get( $wpamo_id );
			$wpamo_label      = $wpamo_group['label'] ?? ( $wpamo_definition['label'] ?? $wpamo_id );
			$wpamo_icon       = $wpamo_group['icon'] ?? ( $wpamo_definition['icon'] ?? 'dashicons-menu-alt' );
			$wpamo_members    = (array) ( $wpamo_group['items'] ?? array() );
			?>
			<section class="wpamo-group" data-wpamo-group="<?php echo esc_attr( $wpamo_id ); ?>">
				<h2 class="wpamo-group-title">
					<span class="dashicons <?php echo esc_attr( $wpamo_icon ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $wpamo_label ); ?>
					<?php if ( Categories::UNGROUPED === $wpamo_id ) : ?>
						<span class="wpamo-badge-permanent"><?php echo esc_html__( 'always visible', 'wp-admin-menu-organizer' ); ?></span>
					<?php endif; ?>
				</h2>

				<ul
					class="wpamo-droplist"
					data-wpamo-group="<?php echo esc_attr( $wpamo_id ); ?>"
					aria-label="
					<?php
					echo esc_attr(
						sprintf(
							/* translators: %s: Group name. */
							__( 'Items in %s', 'wp-admin-menu-organizer' ),
							$wpamo_label
						)
					);
					?>
					"
				>
					<?php foreach ( $wpamo_members as $wpamo_slug ) : ?>
						<?php
						if ( ! isset( $wpamo_items[ $wpamo_slug ] ) ) {
							continue;
						}

						$wpamo_item = $wpamo_items[ $wpamo_slug ];
						?>
						<li class="wpamo-chip" data-wpamo-slug="<?php echo esc_attr( $wpamo_slug ); ?>">
							<span class="wpamo-chip-handle dashicons dashicons-menu" aria-hidden="true"></span>
							<span class="wpamo-chip-label"><?php echo esc_html( $wpamo_item['label'] ); ?></span>
							<code class="wpamo-chip-slug"><?php echo esc_html( $wpamo_slug ); ?></code>

							<?php /* SPEC 7.2: every drag must have a keyboard equivalent. */ ?>
							<label class="wpamo-chip-move">
								<span class="screen-reader-text">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: Menu item name. */
											__( 'Move %s to group', 'wp-admin-menu-organizer' ),
											$wpamo_item['label']
										)
									);
									?>
								</span>
								<select class="wpamo-move-select" data-wpamo-slug="<?php echo esc_attr( $wpamo_slug ); ?>">
									<?php foreach ( $wpamo_group_choices as $wpamo_choice_id => $wpamo_choice_label ) : ?>
										<option
											value="<?php echo esc_attr( $wpamo_choice_id ); ?>"
											<?php selected( $wpamo_choice_id, $wpamo_id ); ?>
										><?php echo esc_html( $wpamo_choice_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>

				<input
					type="hidden"
					name="wpamo_group_items[<?php echo esc_attr( $wpamo_id ); ?>]"
					value="<?php echo esc_attr( implode( ',', $wpamo_members ) ); ?>"
					data-wpamo-group-input="<?php echo esc_attr( $wpamo_id ); ?>"
				>
			</section>
		<?php endforeach; ?>
	</div>

	<p class="submit">
		<?php submit_button( __( 'Save layout', 'wp-admin-menu-organizer' ), 'primary', 'submit', false ); ?>

		<?php if ( $wpamo_personal && Layout_Repository::has_user_layout( get_current_user_id() ) ) : ?>
			<button
				type="submit"
				class="button button-secondary wpamo-reset"
				name="wpamo_action"
				value="reset_personal"
			><?php echo esc_html__( 'Reset to site default', 'wp-admin-menu-organizer' ); ?></button>
		<?php endif; ?>
	</p>
</form>
