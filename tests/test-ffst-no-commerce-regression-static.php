<?php
/**
 * Static scope guard: the FFST profile/form modules must stay isolated from
 * WooCommerce, pack quotas and licence creation/renewal business logic.
 */
$root = dirname( __DIR__ );
$files = array(
    $root . '/includes/admin/class-ffst-club-profile-fields.php',
    $root . '/includes/admin/class-ffst-club-admin-layout.php',
);

$forbidden = array(
    'WC()',
    'wc_get_cart_url',
    'wc_get_checkout_url',
    'add_to_cart',
    'ufsc_add_affiliation_to_cart',
    'ufsc_allocate_pack_credit',
    'pack_credit',
    'included_licence',
    'included_license',
    'quota',
    'renew_licence',
    'renew_affiliation',
    'ufsc_handle_bulk_renew_licences',
    'woocommerce_',
);

$failures = 0;
foreach ( $files as $file ) {
    $content = file_get_contents( $file );
    if ( false === $content ) {
        fwrite( STDERR, "FAIL: impossible de lire {$file}\n" );
        $failures++;
        continue;
    }

    foreach ( $forbidden as $needle ) {
        if ( false !== stripos( $content, $needle ) ) {
            fwrite( STDERR, "FAIL: le module FFST ne doit pas référencer la logique métier '{$needle}' dans {$file}\n" );
            $failures++;
        }
    }
}

if ( $failures ) {
    exit( 1 );
}

echo "OK: les évolutions FFST restent isolées du panier, des quotas et des renouvellements.\n";
