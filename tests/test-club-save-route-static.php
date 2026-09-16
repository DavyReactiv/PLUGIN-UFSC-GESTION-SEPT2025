<?php
/**
 * Static regression guard for the affiliation club creation route.
 */
$bridge = file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-club-save-route-bridge.php' );
$handler = file_get_contents( dirname( __DIR__ ) . '/includes/frontend/class-club-form-handler.php' );

$assert = static function( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert(
    false !== strpos( $bridge, "remove_action( 'admin_post_ufsc_save_club', array( 'UFSC_Unified_Handlers', 'handle_save_club' ) )" ),
    'duplicate unified club-save handler must be removed'
);
$assert(
    false !== strpos( $bridge, "add_action( 'admin_post_ufsc_save_club', array( 'UFSC_CL_Club_Form_Handler', 'handle_save_club' ), 10 )" ),
    'canonical front club handler must remain registered'
);
$assert(
    false !== strpos( $handler, 'ufsc_add_affiliation_to_cart( $club_id )' ) &&
    false !== strpos( $handler, "wc_get_cart_url" ) &&
    false !== strpos( $handler, "wp_safe_redirect" ),
    'canonical club handler must add affiliation to cart and redirect'
);

echo "OK: club save route is canonical and cart redirect is preserved.\n";
