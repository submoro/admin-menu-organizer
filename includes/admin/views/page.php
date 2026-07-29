<?php
/**
 * Settings screen shell.
 *
 * @package WPAdminMenuOrganizer
 * @since   1.0.0
 *
 * @var string                    $tab    Current tab slug, from Settings_Page::render().
 * @var \WPAMO\Menu_Reader        $reader Reader over the live menu.
 */

use WPAMO\Admin\Settings_Page;
use WPAMO\Capabilities;

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status keyword used to pick a notice.
$wpamo_status = isset( $_GET['wpamo-status'] ) ? sanitize_key( wp_unslash( $_GET['wpamo-status'] ) ) : '';
$wpamo_notice = '' !== $wpamo_status ? Settings_Page::notice_for( $wpamo_status ) : null;

$wpamo_tabs   = array();
$wpamo_layout = Settings_Page::editing_layout( $tab, $reader );
$wpamo_items  = Settings_Page::item_index( $reader );

if ( Capabilities::current_user_can_manage() ) {
	$wpamo_tabs['layout']   = __( 'Layout', 'wp-admin-menu-organizer' );
	$wpamo_tabs['groups']   = __( 'Groups', 'wp-admin-menu-organizer' );
	$wpamo_tabs['roles']    = __( 'Roles', 'wp-admin-menu-organizer' );
	$wpamo_tabs['advanced'] = __( 'Advanced', 'wp-admin-menu-organizer' );
}

if ( Capabilities::current_user_can_personalise() ) {
	$wpamo_tabs['personal'] = __( 'Personalise my menu', 'wp-admin-menu-organizer' );
}
?>
<div class="wrap wpamo-settings">
	<h1><?php echo esc_html__( 'Menu Organizer', 'wp-admin-menu-organizer' ); ?></h1>

	<?php if ( null !== $wpamo_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $wpamo_notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $wpamo_notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php echo esc_attr__( 'Menu Organizer sections', 'wp-admin-menu-organizer' ); ?>">
		<?php foreach ( $wpamo_tabs as $wpamo_slug => $wpamo_label ) : ?>
			<?php $wpamo_is_current = ( $tab === $wpamo_slug ); ?>
			<a
				href="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG . '&tab=' . $wpamo_slug ) ); ?>"
				class="<?php echo esc_attr( $wpamo_is_current ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
				<?php
				if ( $wpamo_is_current ) {
					echo 'aria-current="page"';
				}
				?>
			><?php echo esc_html( $wpamo_label ); ?></a>
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
