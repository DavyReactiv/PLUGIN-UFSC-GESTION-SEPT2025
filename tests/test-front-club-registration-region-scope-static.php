<?php
$root    = dirname( __DIR__ );
$hotfix  = file_get_contents( $root . '/inc/common/front-club-registration-scope-hotfix.php' );
$flags   = file_get_contents( $root . '/inc/common/feature-flags.php' );
$handler = file_get_contents( $root . '/includes/frontend/class-club-form-handler.php' );
$utils   = file_get_contents( $root . '/includes/core/class-utils.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, "front-club-registration-scope-hotfix.php" ), 'runtime must load the front club registration scope hotfix' );
$assert( false !== strpos( $handler, "'ufsc_save_club'" ), 'club creation must remain on the existing canonical save handler' );
$assert( false !== strpos( $handler, 'UFSC_CL_Utils::validate_club_data' ), 'club creation must keep canonical club validation' );
$assert( false !== strpos( $utils, "Région non valide" ), 'selected region must remain validated against the canonical UFSC region list' );
$assert( false !== strpos( $hotfix, "'ufsc_save_club' !== \$action" ), 'hotfix must be restricted to the club save action' );
$assert( false !== strpos( $hotfix, "if ( \$club_id > 0 )" ), 'hotfix must apply only to first club creation, never edits' );
$assert( false !== strpos( $hotfix, 'UFSC_Permissions::CAP_ALL_REGIONS_ACCESS' ), 'hotfix may relax only the regional scope capability for this request' );
$assert( false !== strpos( $hotfix, "'ufsc_region_viewer'" ), 'read-only regional staff must remain excluded from the bypass' );
$assert( false !== strpos( $hotfix, "'ufsc_region_manager'" ), 'regional managers must remain subject to their assigned scope' );
$assert( false !== strpos( $hotfix, 'UFSC_Permissions::CAP_GESTION_READ' ), 'existing UFSC back-office accounts must remain excluded' );
$assert( false === strpos( $hotfix, 'update_user_meta' ), 'hotfix must not persist user permissions' );
$assert( false === strpos( $hotfix, 'add_cap(' ), 'hotfix must not persist capabilities' );
$assert( false === strpos( $hotfix, '$wpdb->insert' ), 'hotfix must not insert business data' );
$assert( false === strpos( $hotfix, '$wpdb->update' ), 'hotfix must not update business data' );
$assert( false === strpos( $hotfix, 'WC()->cart' ), 'hotfix must not touch WooCommerce cart logic' );
$assert( false === strpos( $hotfix, 'UFSC_Unified_Handlers::' ), 'hotfix must not touch licence business handlers' );

echo "Front club registration region scope safeguards OK\n";
