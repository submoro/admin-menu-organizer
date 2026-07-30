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
 * @package AdminMenuCategories
 * @since   1.0.0
 *
 * @var array               $amorg_layout Layout being edited.
 * @var array               $amorg_items  Index of every organizable menu item.
 * @var \AMORG\Menu_Reader  $reader       Reader over the live menu.
 */

use AMORG\Admin\Settings_Page;
use AMORG\Categories;

defined( 'ABSPATH' ) || exit;

$amorg_export = wp_json_encode( $amorg_layout, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
?>

<h2><?php echo esc_html__( 'Reset', 'admin-menu-categories' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=advanced' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="amorg_action" value="reset_layout">

	<p class="description">
		<?php echo esc_html__( 'Discards the site-wide arrangement and returns to the automatically detected grouping. Personal layouts and role presets are left alone.', 'admin-menu-categories' ); ?>
	</p>

	<?php submit_button( __( 'Reset to detected default', 'admin-menu-categories' ), 'secondary' ); ?>
</form>

<hr>

<h2><?php echo esc_html__( 'Export', 'admin-menu-categories' ); ?></h2>

<p class="description">
	<?php echo esc_html__( 'Copy this to move the arrangement to another site.', 'admin-menu-categories' ); ?>
</p>

<label for="amorg-export" class="screen-reader-text">
	<?php echo esc_html__( 'Layout as JSON', 'admin-menu-categories' ); ?>
</label>
<textarea id="amorg-export" class="large-text code" rows="10" readonly><?php echo esc_textarea( (string) $amorg_export ); ?></textarea>

<hr>

<h2><?php echo esc_html__( 'Import', 'admin-menu-categories' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=advanced' ) ); ?>">
	<?php wp_nonce_field( Settings_Page::NONCE_SITE ); ?>
	<input type="hidden" name="amorg_action" value="import_layout">

	<p class="description">
		<?php echo esc_html__( 'Paste an exported layout. Anything it refers to that does not exist on this site is ignored rather than imported, so a layout from a site with different plugins is safe to use.', 'admin-menu-categories' ); ?>
	</p>

	<label for="amorg-import" class="screen-reader-text">
		<?php echo esc_html__( 'Layout JSON to import', 'admin-menu-categories' ); ?>
	</label>
	<textarea id="amorg-import" name="amorg_import" class="large-text code" rows="8"></textarea>

	<?php submit_button( __( 'Import layout', 'admin-menu-categories' ), 'secondary' ); ?>
</form>

<hr>

<h2><?php echo esc_html__( 'If something goes wrong', 'admin-menu-categories' ); ?></h2>

<p>
	<?php echo esc_html__( 'Two escape hatches always work, and both leave your saved arrangement intact:', 'admin-menu-categories' ); ?>
</p>

<ol>
	<li>
		<?php
		printf(
			/* translators: %s: The query parameter, wrapped in a code element. */
			esc_html__( 'Add %s to any admin URL to turn the plugin off for that one page load.', 'admin-menu-categories' ),
			'<code>?amorg=off</code>'
		);
		?>
	</li>
	<li>
		<?php
		printf(
			/* translators: 1: The constant to define, 2: The file to define it in. */
			esc_html__( 'Add %1$s to %2$s to turn it off entirely.', 'admin-menu-categories' ),
			'<code>define( \'AMORG_DISABLE\', true );</code>',
			'<code>wp-config.php</code>'
		);
		?>
	</li>
</ol>

<hr>

<h2><?php echo esc_html__( 'Why items were grouped this way', 'admin-menu-categories' ); ?></h2>

<p class="description">
	<?php echo esc_html__( 'What the automatic rules decided for every top-level item currently in your sidebar, and which rule decided it. Anything you have moved by hand overrides this.', 'admin-menu-categories' ); ?>
</p>

<table class="widefat striped">
	<caption class="screen-reader-text">
		<?php echo esc_html__( 'Automatic grouping decisions', 'admin-menu-categories' ); ?>
	</caption>
	<thead>
		<tr>
			<th scope="col"><?php echo esc_html__( 'Menu item', 'admin-menu-categories' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Slug', 'admin-menu-categories' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Detected group', 'admin-menu-categories' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Decided by', 'admin-menu-categories' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Matched on', 'admin-menu-categories' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $amorg_items as $amorg_slug => $amorg_item ) : ?>
			<?php $amorg_definition = Categories::get( $amorg_item['detected'] ); ?>
			<tr>
				<td><?php echo esc_html( $amorg_item['label'] ); ?></td>
				<td><code><?php echo esc_html( $amorg_slug ); ?></code></td>
				<td><?php echo esc_html( $amorg_definition['label'] ?? $amorg_item['detected'] ); ?></td>
				<td><code><?php echo esc_html( $amorg_item['layer'] ); ?></code></td>
				<td><?php echo esc_html( $amorg_item['evidence'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<p class="description">
	<?php
	printf(
		/* translators: 1: Number of items grouped automatically, 2: Total number of items. */
		esc_html__( '%1$d of %2$d items were grouped automatically.', 'admin-menu-categories' ),
		(int) count(
			array_filter(
				$amorg_items,
				static function ( $item ) {
					return Categories::UNGROUPED !== $item['detected'];
				}
			)
		),
		(int) count( $amorg_items )
	);
	?>
</p>
