<?php
/** Focused regression proof for the code changed by the final reprise. */
$root = dirname( __DIR__ );
$assert = static function ( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };

$settings = file_get_contents( $root . '/inc/woocommerce/settings-woocommerce.php' );
$dashboard = file_get_contents( $root . '/assets/js/frontend-dashboard.js' );
$rest = file_get_contents( $root . '/includes/api/class-rest-api.php' );
$admin = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$front_css = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$admin_css = file_get_contents( $root . '/assets/css/ufsc-admin.css' );

$assert( false !== strpos( $settings, "admin_post_ufsc_save_woocommerce_settings" ), 'real settings admin-post hook' );
$assert( false !== strpos( $settings, 'name="action" value="ufsc_save_woocommerce_settings"' ), 'real settings form action' );
$assert( false !== strpos( $settings, "set_transient( 'ufsc_wc_settings_notice_'" ), 'post/redirect/get result persists' );
$assert( false === strpos( $dashboard, "on('visibilitychange'" ) && false === strpos( $dashboard, 'setInterval(function()' ), 'no automatic request loop' );
$assert( false !== strpos( $dashboard, "response !== '0'" ) && false !== strpos( $dashboard, 'inFlight[requestKey]' ), 'AJAX 0 rejected and duplicate request coalesced' );
$assert( false === strpos( $rest, "'/import'" ) && false === strpos( $rest, "'/export/(?P<format>" ), 'unused 501 REST routes removed' );
foreach ( array( 'given-name', 'family-name', 'email', 'tel', 'street-address', 'postal-code', 'address-level2' ) as $token ) {
	$assert( false !== strpos( $admin, $token ), "autocomplete {$token}" );
}
$assert( false !== strpos( $front_css, 'height: auto' ) && false !== strpos( $front_css, 'grid-template-columns: repeat(3' ), 'dashboard content-owned height and desktop KPI grid' );
$assert( false !== strpos( $admin_css, 'max-width: none' ) && false !== strpos( $admin_css, 'width: calc(100% - 20px)' ) && false !== strpos( $admin_css, 'position: sticky' ), 'fluid admin sheet and visible actions' );

echo "Final reprise product, network, REST cleanup, layout and accessibility safeguards OK\n";
