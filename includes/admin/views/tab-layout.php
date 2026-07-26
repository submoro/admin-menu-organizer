<?php
/**
 * Layout tab: the drag-and-drop editor.
 *
 * Also serves the Personalise tab, which is the same editor scoped to the current
 * user's own meta rather than to the site option.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 *
 * @var string $tab          Current tab slug.
 * @var array  $mocam_layout Layout being edited.
 * @var array  $mocam_items  Index of every organizable menu item.
 */

use MOCAM\Admin\Settings_Page;
use MOCAM\Categories;
use MOCAM\Layout_Repository;

defined( 'ABSPATH' ) || exit;

$mocam_personal = ( 'personal' === $tab );
$mocam_action   = $mocam_personal ? 'save_personal' : 'save_layout';
$mocam_nonce    = $mocam_personal ? Settings_Page::NONCE_PERSONAL : Settings_Page::NONCE_SITE;

// Which group each item currently sits in, so unplaced items can be pooled.
$mocam_placed = array();

foreach ( (array) ( $mocam_layout['groups'] ?? array() ) as $mocam_group ) {
	foreach ( (array) ( $mocam_group['items'] ?? array() ) as $mocam_slug ) {
		$mocam_placed[ $mocam_slug ] = $mocam_group['id'];
	}
}

$mocam_group_choices = array();

foreach ( (array) ( $mocam_layout['groups'] ?? array() ) as $mocam_group ) {
	$mocam_definition                          = Categories::get( $mocam_group['id'] );
	$mocam_group_choices[ $mocam_group['id'] ] = $mocam_group['label']
		?? ( $mocam_definition['label'] ?? $mocam_group['id'] );
}
?>

<?php if ( $mocam_personal ) : ?>
	<p class="mocam-intro">
		<?php echo esc_html__( 'Arrange your own sidebar. This affects only your account; everyone else keeps the site default.', 'menu-organizer-collapsible-admin-menu' ); ?>
	</p>
<?php else : ?>
	<p class="mocam-intro">
		<?php echo esc_html__( 'Drag items between groups to arrange the sidebar for everyone. Every item stays reachable: anything you do not place appears in the Other group at the bottom.', 'menu-organizer-collapsible-admin-menu' ); ?>
	</p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=' . $tab ) ); ?>" class="mocam-editor-form">
	<?php wp_nonce_field( $mocam_nonce ); ?>
	<input type="hidden" name="mocam_action" value="<?php echo esc_attr( $mocam_action ); ?>">

	<div class="mocam-editor">
		<?php foreach ( (array) ( $mocam_layout['groups'] ?? array() ) as $mocam_group ) : ?>
			<?php
			$mocam_id         = (string) $mocam_group['id'];
			$mocam_definition = Categories::get( $mocam_id );
			$mocam_label      = $mocam_group['label'] ?? ( $mocam_definition['label'] ?? $mocam_id );
			$mocam_icon       = $mocam_group['icon'] ?? ( $mocam_definition['icon'] ?? 'dashicons-menu-alt' );
			$mocam_members    = (array) ( $mocam_group['items'] ?? array() );
			?>
			<section class="mocam-group" data-mocam-group="<?php echo esc_attr( $mocam_id ); ?>">
				<h2 class="mocam-group-title">
					<span class="dashicons <?php echo esc_attr( $mocam_icon ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $mocam_label ); ?>
					<?php if ( Categories::UNGROUPED === $mocam_id ) : ?>
						<span class="mocam-badge-permanent"><?php echo esc_html__( 'always visible', 'menu-organizer-collapsible-admin-menu' ); ?></span>
					<?php endif; ?>
				</h2>

				<ul
					class="mocam-droplist"
					data-mocam-group="<?php echo esc_attr( $mocam_id ); ?>"
					aria-label="
					<?php
					echo esc_attr(
						sprintf(
							/* translators: %s: Group name. */
							__( 'Items in %s', 'menu-organizer-collapsible-admin-menu' ),
							$mocam_label
						)
					);
					?>
					"
				>
					<?php foreach ( $mocam_members as $mocam_slug ) : ?>
						<?php
						if ( ! isset( $mocam_items[ $mocam_slug ] ) ) {
							continue;
						}

						$mocam_item = $mocam_items[ $mocam_slug ];
						?>
						<li class="mocam-chip" data-mocam-slug="<?php echo esc_attr( $mocam_slug ); ?>">
							<span class="mocam-chip-handle dashicons dashicons-menu" aria-hidden="true"></span>
							<span class="mocam-chip-label"><?php echo esc_html( $mocam_item['label'] ); ?></span>
							<code class="mocam-chip-slug"><?php echo esc_html( $mocam_slug ); ?></code>

							<?php /* SPEC 7.2: every drag must have a keyboard equivalent. */ ?>
							<label class="mocam-chip-move">
								<span class="screen-reader-text">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: Menu item name. */
											__( 'Move %s to group', 'menu-organizer-collapsible-admin-menu' ),
											$mocam_item['label']
										)
									);
									?>
								</span>
								<select class="mocam-move-select" data-mocam-slug="<?php echo esc_attr( $mocam_slug ); ?>">
									<?php foreach ( $mocam_group_choices as $mocam_choice_id => $mocam_choice_label ) : ?>
										<option
											value="<?php echo esc_attr( $mocam_choice_id ); ?>"
											<?php selected( $mocam_choice_id, $mocam_id ); ?>
										><?php echo esc_html( $mocam_choice_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>

				<input
					type="hidden"
					name="mocam_group_items[<?php echo esc_attr( $mocam_id ); ?>]"
					value="<?php echo esc_attr( implode( ',', $mocam_members ) ); ?>"
					data-mocam-group-input="<?php echo esc_attr( $mocam_id ); ?>"
				>
			</section>
		<?php endforeach; ?>
	</div>

	<p class="submit">
		<?php submit_button( __( 'Save layout', 'menu-organizer-collapsible-admin-menu' ), 'primary', 'submit', false ); ?>

		<?php if ( $mocam_personal && Layout_Repository::has_user_layout( get_current_user_id() ) ) : ?>
			<button
				type="submit"
				class="button button-secondary mocam-reset"
				name="mocam_action"
				value="reset_personal"
			><?php echo esc_html__( 'Reset to site default', 'menu-organizer-collapsible-admin-menu' ); ?></button>
		<?php endif; ?>
	</p>
</form>
