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
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 *
 * @var array               $wpamo_layout Layout being edited.
 * @var array               $wpamo_items  Index of every organizable menu item.
 * @var \WPAMO\Menu_Reader  $reader       Reader over the live menu.
 */

use WPAMO\Admin\Settings_Page;
use WPAMO\Categories;

defined( 'ABSPATH' ) || exit;

$wpamo_export = wp_json_encode( $wpamo_layout, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
?>

<h2><?php echo esc_html__( 'Reset', 'wp-admin-menu-organizer' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=advanced' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="wpamo_action" value="reset_layout">

	<p class="description">
		<?php echo esc_html__( 'Discards the site-wide arrangement and returns to the automatically detected grouping. Personal layouts and role presets are left alone.', 'wp-admin-menu-organizer' ); ?>
	</p>

	<?php submit_button( __( 'Reset to detected default', 'wp-admin-menu-organizer' ), 'secondary' ); ?>
</form>

<hr>

<h2><?php echo esc_html__( 'Export', 'wp-admin-menu-organizer' ); ?></h2>

<p class="description">
	<?php echo esc_html__( 'Copy this to move the arrangement to another site.', 'wp-admin-menu-organizer' ); ?>
</p>

<label for="wpamo-export" class="screen-reader-text">
	<?php echo esc_html__( 'Layout as JSON', 'wp-admin-menu-organizer' ); ?>
</label>
<textarea id="wpamo-export" class="large-text code" rows="10" readonly><?php echo esc_textarea( (string) $wpamo_export ); ?></textarea>

<hr>

<h2><?php echo esc_html__( 'Import', 'wp-admin-menu-organizer' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=advanced' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="wpamo_action" value="import_layout">

	<p class="description">
		<?php echo esc_html__( 'Paste an exported layout. Anything it refers to that does not exist on this site is ignored rather than imported, so a layout from a site with different plugins is safe to use.', 'wp-admin-menu-organizer' ); ?>
	</p>

	<label for="wpamo-import" class="screen-reader-text">
		<?php echo esc_html__( 'Layout JSON to import', 'wp-admin-menu-organizer' ); ?>
	</label>
	<textarea id="wpamo-import" name="wpamo_import" class="large-text code" rows="8"></textarea>

	<?php submit_button( __( 'Import layout', 'wp-admin-menu-organizer' ), 'secondary' ); ?>
</form>

<hr>

<h2><?php echo esc_html__( 'If something goes wrong', 'wp-admin-menu-organizer' ); ?></h2>

<p>
	<?php echo esc_html__( 'Two escape hatches always work, and both leave your saved arrangement intact:', 'wp-admin-menu-organizer' ); ?>
</p>

<ol>
	<li>
		<?php
		printf(
			/* translators: %s: The query parameter, wrapped in a code element. */
			esc_html__( 'Add %s to any admin URL to turn the plugin off for that one page load.', 'wp-admin-menu-organizer' ),
			'<code>?wpamo=off</code>'
		);
		?>
	</li>
	<li>
		<?php
		printf(
			/* translators: 1: The constant to define, 2: The file to define it in. */
			esc_html__( 'Add %1$s to %2$s to turn it off entirely.', 'wp-admin-menu-organizer' ),
			'<code>define( \'WPAMO_DISABLE\', true );</code>',
			'<code>wp-config.php</code>'
		);
		?>
	</li>
</ol>

<hr>

<h2><?php echo esc_html__( 'Why items were grouped this way', 'wp-admin-menu-organizer' ); ?></h2>

<p class="description">
	<?php echo esc_html__( 'What the automatic rules decided for every top-level item currently in your sidebar, and which rule decided it. Anything you have moved by hand overrides this.', 'wp-admin-menu-organizer' ); ?>
</p>

<table class="widefat striped">
	<caption class="screen-reader-text">
		<?php echo esc_html__( 'Automatic grouping decisions', 'wp-admin-menu-organizer' ); ?>
	</caption>
	<thead>
		<tr>
			<th scope="col"><?php echo esc_html__( 'Menu item', 'wp-admin-menu-organizer' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Slug', 'wp-admin-menu-organizer' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Detected group', 'wp-admin-menu-organizer' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Decided by', 'wp-admin-menu-organizer' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Matched on', 'wp-admin-menu-organizer' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $wpamo_items as $wpamo_slug => $wpamo_item ) : ?>
			<?php $wpamo_definition = Categories::get( $wpamo_item['detected'] ); ?>
			<tr>
				<td><?php echo esc_html( $wpamo_item['label'] ); ?></td>
				<td><code><?php echo esc_html( $wpamo_slug ); ?></code></td>
				<td><?php echo esc_html( $wpamo_definition['label'] ?? $wpamo_item['detected'] ); ?></td>
				<td><code><?php echo esc_html( $wpamo_item['layer'] ); ?></code></td>
				<td><?php echo esc_html( $wpamo_item['evidence'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<p class="description">
	<?php
	printf(
		/* translators: 1: Number of items grouped automatically, 2: Total number of items. */
		esc_html__( '%1$d of %2$d items were grouped automatically.', 'wp-admin-menu-organizer' ),
		(int) count(
			array_filter(
				$wpamo_items,
				static function ( $item ) {
					return Categories::UNGROUPED !== $item['detected'];
				}
			)
		),
		(int) count( $wpamo_items )
	);
	?>
</p>
