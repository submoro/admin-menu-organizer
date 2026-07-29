<?php
/**
 * Layout tab: the drag-and-drop editor.
 *
 * Also serves the Personalise tab, which is the same editor scoped to the current
 * user's own meta rather than to the site option.
 *
 * @package AdminMenuOrganizer
 * @since   1.0.0
 *
 * @var string $tab          Current tab slug.
 * @var array  $amorg_layout Layout being edited.
 * @var array  $amorg_items  Index of every organizable menu item.
 */

use AMORG\Admin\Settings_Page;
use AMORG\Categories;
use AMORG\Layout_Repository;

defined( 'ABSPATH' ) || exit;

$amorg_personal = ( 'personal' === $tab );
$amorg_action   = $amorg_personal ? 'save_personal' : 'save_layout';
$amorg_nonce    = $amorg_personal ? Settings_Page::NONCE_PERSONAL : Settings_Page::NONCE_SITE;

// Which group each item currently sits in, so unplaced items can be pooled.
$amorg_placed = array();

foreach ( (array) ( $amorg_layout['groups'] ?? array() ) as $amorg_group ) {
	foreach ( (array) ( $amorg_group['items'] ?? array() ) as $amorg_slug ) {
		$amorg_placed[ $amorg_slug ] = $amorg_group['id'];
	}
}

$amorg_group_choices = array();

foreach ( (array) ( $amorg_layout['groups'] ?? array() ) as $amorg_group ) {
	$amorg_definition                          = Categories::get( $amorg_group['id'] );
	$amorg_group_choices[ $amorg_group['id'] ] = $amorg_group['label']
		?? ( $amorg_definition['label'] ?? $amorg_group['id'] );
}
?>

<?php if ( $amorg_personal ) : ?>
	<p class="amorg-intro">
		<?php echo esc_html__( 'Arrange your own sidebar. This affects only your account; everyone else keeps the site default.', 'admin-menu-organizer' ); ?>
	</p>
<?php else : ?>
	<p class="amorg-intro">
		<?php echo esc_html__( 'Drag items between groups to arrange the sidebar for everyone. Every item stays reachable: anything you do not place appears in the Other group at the bottom.', 'admin-menu-organizer' ); ?>
	</p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=' . $tab ) ); ?>" class="amorg-editor-form">
	<?php wp_nonce_field( $amorg_nonce ); ?>
	<input type="hidden" name="amorg_action" value="<?php echo esc_attr( $amorg_action ); ?>">

	<div class="amorg-editor">
		<?php foreach ( (array) ( $amorg_layout['groups'] ?? array() ) as $amorg_group ) : ?>
			<?php
			$amorg_id         = (string) $amorg_group['id'];
			$amorg_definition = Categories::get( $amorg_id );
			$amorg_label      = $amorg_group['label'] ?? ( $amorg_definition['label'] ?? $amorg_id );
			$amorg_icon       = $amorg_group['icon'] ?? ( $amorg_definition['icon'] ?? 'dashicons-menu-alt' );
			$amorg_members    = (array) ( $amorg_group['items'] ?? array() );
			?>
			<section class="amorg-group" data-amorg-group="<?php echo esc_attr( $amorg_id ); ?>">
				<h2 class="amorg-group-title">
					<span class="dashicons <?php echo esc_attr( $amorg_icon ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $amorg_label ); ?>
					<?php if ( Categories::UNGROUPED === $amorg_id ) : ?>
						<span class="amorg-badge-permanent"><?php echo esc_html__( 'always visible', 'admin-menu-organizer' ); ?></span>
					<?php endif; ?>
				</h2>

				<ul
					class="amorg-droplist"
					data-amorg-group="<?php echo esc_attr( $amorg_id ); ?>"
					aria-label="
					<?php
					echo esc_attr(
						sprintf(
							/* translators: %s: Group name. */
							__( 'Items in %s', 'admin-menu-organizer' ),
							$amorg_label
						)
					);
					?>
					"
				>
					<?php foreach ( $amorg_members as $amorg_slug ) : ?>
						<?php
						if ( ! isset( $amorg_items[ $amorg_slug ] ) ) {
							continue;
						}

						$amorg_item = $amorg_items[ $amorg_slug ];
						?>
						<li class="amorg-chip" data-amorg-slug="<?php echo esc_attr( $amorg_slug ); ?>">
							<span class="amorg-chip-handle dashicons dashicons-menu" aria-hidden="true"></span>
							<span class="amorg-chip-label"><?php echo esc_html( $amorg_item['label'] ); ?></span>
							<code class="amorg-chip-slug"><?php echo esc_html( $amorg_slug ); ?></code>

							<?php /* SPEC 7.2: every drag must have a keyboard equivalent. */ ?>
							<label class="amorg-chip-move">
								<span class="screen-reader-text">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: Menu item name. */
											__( 'Move %s to group', 'admin-menu-organizer' ),
											$amorg_item['label']
										)
									);
									?>
								</span>
								<select class="amorg-move-select" data-amorg-slug="<?php echo esc_attr( $amorg_slug ); ?>">
									<?php foreach ( $amorg_group_choices as $amorg_choice_id => $amorg_choice_label ) : ?>
										<option
											value="<?php echo esc_attr( $amorg_choice_id ); ?>"
											<?php selected( $amorg_choice_id, $amorg_id ); ?>
										><?php echo esc_html( $amorg_choice_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>

				<input
					type="hidden"
					name="amorg_group_items[<?php echo esc_attr( $amorg_id ); ?>]"
					value="<?php echo esc_attr( implode( ',', $amorg_members ) ); ?>"
					data-amorg-group-input="<?php echo esc_attr( $amorg_id ); ?>"
				>
			</section>
		<?php endforeach; ?>
	</div>

	<p class="submit">
		<?php submit_button( __( 'Save layout', 'admin-menu-organizer' ), 'primary', 'submit', false ); ?>

		<?php if ( $amorg_personal && Layout_Repository::has_user_layout( get_current_user_id() ) ) : ?>
			<button
				type="submit"
				class="button button-secondary amorg-reset"
				name="amorg_action"
				value="reset_personal"
			><?php echo esc_html__( 'Reset to site default', 'admin-menu-organizer' ); ?></button>
		<?php endif; ?>
	</p>
</form>
