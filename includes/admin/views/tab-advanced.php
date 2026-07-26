<?php
/**
 * Advanced tab: reset, export, import, recovery, and detection diagnostics.
 *
 * The diagnostics table is where SPEC section 13 phase 3's "debug screen dumping
 * the resolved menu" ended up. A standalone debug screen would have added a menu
 * item, which is what this plugin exists to reduce, and would have breached SPEC
 * section 10.6's rule against shipping placeholder screens. Here it earns its
 * place: it answers "why did my plugin land in that group", which is the support
 * question this plugin will generate most.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 *
 * @var array               $mocam_layout Layout being edited.
 * @var array               $mocam_items  Index of every organizable menu item.
 * @var \MOCAM\Menu_Reader  $reader       Reader over the live menu.
 */

use MOCAM\Admin\Settings_Page;
use MOCAM\Categories;

defined( 'ABSPATH' ) || exit;

$mocam_export = wp_json_encode( $mocam_layout, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
?>

<h2><?php echo esc_html__( 'Reset', 'menu-organizer-collapsible-admin-menu' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=advanced' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="mocam_action" value="reset_layout">

	<p class="description">
		<?php echo esc_html__( 'Discards the site-wide arrangement and returns to the automatically detected grouping. Personal layouts and role presets are left alone.', 'menu-organizer-collapsible-admin-menu' ); ?>
	</p>

	<?php submit_button( __( 'Reset to detected default', 'menu-organizer-collapsible-admin-menu' ), 'secondary' ); ?>
</form>

<hr>

<h2><?php echo esc_html__( 'Export', 'menu-organizer-collapsible-admin-menu' ); ?></h2>

<p class="description">
	<?php echo esc_html__( 'Copy this to move the arrangement to another site.', 'menu-organizer-collapsible-admin-menu' ); ?>
</p>

<label for="mocam-export" class="screen-reader-text">
	<?php echo esc_html__( 'Layout as JSON', 'menu-organizer-collapsible-admin-menu' ); ?>
</label>
<textarea id="mocam-export" class="large-text code" rows="10" readonly><?php echo esc_textarea( (string) $mocam_export ); ?></textarea>

<hr>

<h2><?php echo esc_html__( 'Import', 'menu-organizer-collapsible-admin-menu' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=advanced' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="mocam_action" value="import_layout">

	<p class="description">
		<?php echo esc_html__( 'Paste an exported layout. Anything it refers to that does not exist on this site is ignored rather than imported, so a layout from a site with different plugins is safe to use.', 'menu-organizer-collapsible-admin-menu' ); ?>
	</p>

	<label for="mocam-import" class="screen-reader-text">
		<?php echo esc_html__( 'Layout JSON to import', 'menu-organizer-collapsible-admin-menu' ); ?>
	</label>
	<textarea id="mocam-import" name="mocam_import" class="large-text code" rows="8"></textarea>

	<?php submit_button( __( 'Import layout', 'menu-organizer-collapsible-admin-menu' ), 'secondary' ); ?>
</form>

<hr>

<h2><?php echo esc_html__( 'If something goes wrong', 'menu-organizer-collapsible-admin-menu' ); ?></h2>

<p>
	<?php echo esc_html__( 'Two escape hatches always work, and both leave your saved arrangement intact:', 'menu-organizer-collapsible-admin-menu' ); ?>
</p>

<ol>
	<li>
		<?php
		printf(
			/* translators: %s: The query parameter, wrapped in a code element. */
			esc_html__( 'Add %s to any admin URL to turn the plugin off for that one page load.', 'menu-organizer-collapsible-admin-menu' ),
			'<code>?mocam=off</code>'
		);
		?>
	</li>
	<li>
		<?php
		printf(
			/* translators: 1: The constant to define, 2: The file to define it in. */
			esc_html__( 'Add %1$s to %2$s to turn it off entirely.', 'menu-organizer-collapsible-admin-menu' ),
			'<code>define( \'MOCAM_DISABLE\', true );</code>',
			'<code>wp-config.php</code>'
		);
		?>
	</li>
</ol>

<hr>

<h2><?php echo esc_html__( 'Why items were grouped this way', 'menu-organizer-collapsible-admin-menu' ); ?></h2>

<p class="description">
	<?php echo esc_html__( 'What the automatic rules decided for every top-level item currently in your sidebar, and which rule decided it. Anything you have moved by hand overrides this.', 'menu-organizer-collapsible-admin-menu' ); ?>
</p>

<table class="widefat striped">
	<caption class="screen-reader-text">
		<?php echo esc_html__( 'Automatic grouping decisions', 'menu-organizer-collapsible-admin-menu' ); ?>
	</caption>
	<thead>
		<tr>
			<th scope="col"><?php echo esc_html__( 'Menu item', 'menu-organizer-collapsible-admin-menu' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Slug', 'menu-organizer-collapsible-admin-menu' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Detected group', 'menu-organizer-collapsible-admin-menu' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Decided by', 'menu-organizer-collapsible-admin-menu' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Matched on', 'menu-organizer-collapsible-admin-menu' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $mocam_items as $mocam_slug => $mocam_item ) : ?>
			<?php $mocam_definition = Categories::get( $mocam_item['detected'] ); ?>
			<tr>
				<td><?php echo esc_html( $mocam_item['label'] ); ?></td>
				<td><code><?php echo esc_html( $mocam_slug ); ?></code></td>
				<td><?php echo esc_html( $mocam_definition['label'] ?? $mocam_item['detected'] ); ?></td>
				<td><code><?php echo esc_html( $mocam_item['layer'] ); ?></code></td>
				<td><?php echo esc_html( $mocam_item['evidence'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<p class="description">
	<?php
	printf(
		/* translators: 1: Number of items grouped automatically, 2: Total number of items. */
		esc_html__( '%1$d of %2$d items were grouped automatically.', 'menu-organizer-collapsible-admin-menu' ),
		(int) count(
			array_filter(
				$mocam_items,
				static function ( $item ) {
					return Categories::UNGROUPED !== $item['detected'];
				}
			)
		),
		(int) count( $mocam_items )
	);
	?>
</p>
