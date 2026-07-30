<?php
/**
 * Settings screen shell.
 *
 * @package AdminMenuCategories
 * @since   1.0.0
 *
 * @var string                    $tab    Current tab slug, from Settings_Page::render().
 * @var \AMORG\Menu_Reader        $reader Reader over the live menu.
 */

use AMORG\Admin\Settings_Page;
use AMORG\Capabilities;

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status keyword used to pick a notice.
$amorg_status = isset( $_GET['amorg-status'] ) ? sanitize_key( wp_unslash( $_GET['amorg-status'] ) ) : '';
$amorg_notice = '' !== $amorg_status ? Settings_Page::notice_for( $amorg_status ) : null;

$amorg_tabs   = array();
$amorg_layout = Settings_Page::editing_layout( $tab, $reader );
$amorg_items  = Settings_Page::item_index( $reader );

if ( Capabilities::current_user_can_manage() ) {
	$amorg_tabs['layout']   = __( 'Layout', 'admin-menu-categories' );
	$amorg_tabs['groups']   = __( 'Groups', 'admin-menu-categories' );
	$amorg_tabs['roles']    = __( 'Roles', 'admin-menu-categories' );
	$amorg_tabs['advanced'] = __( 'Advanced', 'admin-menu-categories' );
}

if ( Capabilities::current_user_can_personalise() ) {
	$amorg_tabs['personal'] = __( 'Personalise my menu', 'admin-menu-categories' );
}
?>
<div class="wrap amorg-settings">
	<h1><?php echo esc_html__( 'Menu Categories', 'admin-menu-categories' ); ?></h1>

	<?php if ( null !== $amorg_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $amorg_notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $amorg_notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php echo esc_attr__( 'Menu Categories sections', 'admin-menu-categories' ); ?>">
		<?php foreach ( $amorg_tabs as $amorg_slug => $amorg_label ) : ?>
			<?php $amorg_is_current = ( $tab === $amorg_slug ); ?>
			<a
				href="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=' . $amorg_slug ) ); ?>"
				class="<?php echo esc_attr( $amorg_is_current ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
				<?php
				if ( $amorg_is_current ) {
					echo 'aria-current="page"';
				}
				?>
			><?php echo esc_html( $amorg_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php
	switch ( $tab ) {
		case 'groups':
			require __DIR__ . '/tab-groups.php';
			break;

		case 'roles':
			require __DIR__ . '/tab-roles.php';
			break;

		case 'advanced':
			require __DIR__ . '/tab-advanced.php';
			break;

		case 'personal':
			require __DIR__ . '/tab-layout.php';
			break;

		default:
			require __DIR__ . '/tab-layout.php';
			break;
	}
	?>
</div>
