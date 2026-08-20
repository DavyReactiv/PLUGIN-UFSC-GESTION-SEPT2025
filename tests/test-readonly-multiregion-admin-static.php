<?php
$root   = dirname( __DIR__ );
$module = file_get_contents( $root . '/inc/common/readonly-multiregion-admin.php' );
$flags  = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, "'/readonly-multiregion-admin.php'" ), 'feature composition must load the read-only multi-region layer' );
$assert( false !== strpos( $module, "'ufsc_region_viewer'" ), 'read-only access must reuse the existing viewer role' );
$assert( false !== strpos( $module, "'Responsable de ligue – Consultation'" ), 'regional read-only profile must have an administrator-facing label' );
$assert( false !== strpos( $module, "'Responsable national – Consultation'" ), 'national read-only profile must be available' );
$assert( false !== strpos( $module, "'ufsc-readonly-access'" ), 'administrator assignment page must be registered inside UFSC admin' );
$assert( false !== strpos( $module, "'manage_options'" ), 'only WordPress administrators may configure read-only access' );
$assert( false !== strpos( $module, 'ufsc_set_user_regions' ), 'regional profile must use the canonical multi-region storage helper' );
$assert( false !== strpos( $module, "UFSC_Permissions::META_ALL_REGIONS, '1'" ), 'national viewer must support all-region scope' );
$assert( false !== strpos( $module, 'UFSC_Permissions::CAP_GESTION_READ' ), 'viewer must retain management read capability' );
$assert( false !== strpos( $module, 'UFSC_Permissions::CAP_LICENCES_READ' ), 'viewer must retain licence read capability' );
$assert( false !== strpos( $module, 'UFSC_Permissions::CAP_GESTION_MANAGE' ), 'read-only guard must explicitly handle management write capability' );
$assert( false !== strpos( $module, "'manage_woocommerce'" ), 'read-only profile must deny WooCommerce management capability' );
$assert( false !== strpos( $module, "'view_woocommerce_reports'" ), 'read-only profile must deny WooCommerce reporting capability' );
$assert( false !== strpos( $module, "'POST' === \$method" ), 'server guard must block UFSC POST writes' );
$assert( false !== strpos( $module, "'ufsc-exports'" ), 'exports must be hidden/blocked for strict consultation accounts' );
$assert( false !== strpos( $module, "'ufsc-woocommerce'" ), 'UFSC WooCommerce settings must be hidden/blocked' );
$assert( false !== strpos( $module, 'remove_menu_page( \'woocommerce\' )' ), 'WooCommerce menu must be removed for read-only users' );
$assert( false !== strpos( $module, 'ufsc_readonly_access_render_dashboard' ), 'read-only users must receive a dedicated non-accounting dashboard' );
$assert( false !== strpos( $module, 'Aucune commande, aucun paiement et aucune donnée comptable' ), 'read-only dashboard must state its non-accounting boundary' );
$assert( false !== strpos( $module, "'action=edit'" ), 'existing edit routes must be presented as consultation details rather than mutation entry points' );
$assert( false === strpos( $module, 'UFSC_Unified_Handlers::' ), 'read-only layer must not call licence business handlers' );
$assert( false === strpos( $module, 'WC()->cart' ), 'read-only layer must not touch WooCommerce cart business logic' );

echo "Read-only multi-region admin safeguards OK\n";
