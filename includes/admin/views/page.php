<?php
/**
 * Settings screen shell.
 *
 * @package MenuOrganizerCollapsibleAdminMenu
 * @since   1.0.0
 *
 * @var string                    $tab    Current tab slug, from Settings_Page::render().
 * @var \MOCAM\Menu_Reader        $reader Reader over the live menu.
 */

use MOCAM\Admin\Settings_Page;
use MOCAM\Capabilities;

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status keyword used to pick a notice.
$mocam_status = isset( $_GET['mocam-status'] ) ? sanitize_key( wp_unslash( $_GET['mocam-status'] ) ) : '';
$mocam_notice = '' !== $mocam_status ? Settings_Page::notice_for( $mocam_status ) : null;

$mocam_tabs   = array();
$mocam_layout = Settings_Page::editing_layout( $tab, $reader );
$mocam_items  = Settings_Page::item_index( $reader );

if ( Capabilities::current_user_can_manage() ) {
	$mocam_tabs['layout']   = __( 'Layout', 'menu-organizer-collapsible-admin-menu' );
	$mocam_tabs['groups']   = __( 'Groups', 'menu-organizer-collapsible-admin-menu' );
	$mocam_tabs['roles']    = __( 'Roles', 'menu-organizer-collapsible-admin-menu' );
	$mocam_tabs['advanced'] = __( 'Advanced', 'menu-organizer-collapsible-admin-menu' );
}

if ( Capabilities::current_user_can_personalise() ) {
	$mocam_tabs['personal'] = __( 'Personalise my menu', 'menu-organizer-collapsible-admin-menu' );
}
?>
<div class="wrap mocam-settings">
	<h1><?php echo esc_html__( 'Menu Organizer', 'menu-organizer-collapsible-admin-menu' ); ?></h1>

	<?php if ( null !== $mocam_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $mocam_notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $mocam_notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php echo esc_attr__( 'Menu Organizer sections', 'menu-organizer-collapsible-admin-menu' ); ?>">
		<?php foreach ( $mocam_tabs as $mocam_slug => $mocam_label ) : ?>
			<?php $mocam_is_current = ( $tab === $mocam_slug ); ?>
			<a
				href="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=' . $mocam_slug ) ); ?>"
				class="<?php echo esc_attr( $mocam_is_current ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
				<?php
				if ( $mocam_is_current ) {
					echo 'aria-current="page"';
				}
				?>
			><?php echo esc_html( $mocam_label ); ?></a>
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
