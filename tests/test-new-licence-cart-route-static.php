<?php
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert(
    false !== strpos( $flags, "remove_action( 'admin_post_ufsc_add_licence', array( 'UFSC_Unified_Handlers', 'handle_add_licence' ) )" ) &&
    false !== strpos( $flags, "UFSC_Unified_Handlers::handle_save_licence();" ),
    'new licence endpoint is routed through the canonical save/cart workflow'
);

$assert(
    false !== strpos( $flags, "array( 'add_to_cart', 'submit_for_validation' )" ) &&
    false !== strpos( $flags, "add_filter( 'ufsc_role_requires_honorability'" ),
    'honorability is checkout/validation-submission nonblocking while remaining part of final validation'
);

$assert(
    false === strpos( $flags, "remove_action( 'admin_post_ufsc_renew_licence'" ) &&
    false === strpos( $flags, "remove_action( 'admin_post_ufsc_bulk_renew_licences'" ),
    'renewal handlers remain untouched'
);

echo "OK: new licence cart route and honorability checkout safeguards\n";
