<?php
$root      = dirname( __DIR__ );
$hardening = file_get_contents( $root . '/inc/common/readonly-multiregion-admin-hardening.php' );
$base      = file_get_contents( $root . '/inc/common/readonly-multiregion-admin.php' );
$flags     = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, 'readonly-multiregion-admin-hardening.php' ), 'runtime must load the read-only hardening layer' );
$assert( false !== strpos( $flags, 'affiliation-order-state-bridge.php' ), 'current production affiliation bridge must remain loaded' );
$assert( false !== strpos( $flags, 'affiliation-status-labels-fr.php' ), 'current production French affiliation labels must remain loaded' );
$assert( false !== strpos( $hardening, 'Responsables actuellement configurés' ), 'administrator assignment overview must be present' );
$assert( false !== strpos( $hardening, 'INNER JOIN `{$clubs_table}` c ON c.id = l.club_id' ), 'licence KPIs must scope through the clubs table join' );
$assert( false !== strpos( $hardening, "UFSC_Scope::build_scope_condition( 'region', \$alias )" ), 'dashboard must reuse canonical multi-region scope' );
$assert( false !== strpos( $hardening, 'UFSC_Scope::assert_club_in_scope' ), 'direct club/licence detail URLs must enforce regional scope' );
$assert( false !== strpos( $hardening, 'Les commandes, règlements, montants et données comptables ne sont jamais affichés.' ), 'dashboard must keep the non-accounting boundary explicit' );
$assert( false !== strpos( $hardening, '[name="payment_status"]' ), 'payment filters must be removed from the read-only presentation' );
$assert( false !== strpos( $base, "'POST' === \$method" ), 'base server-side write guard must remain present' );
$assert( false === strpos( $hardening, 'UFSC_Unified_Handlers::' ), 'hardening must never call licence mutation handlers' );
$assert( false === strpos( $hardening, 'WC()->cart' ), 'hardening must never touch WooCommerce cart logic' );
$assert( false === strpos( $hardening, '$wpdb->insert' ), 'hardening must not insert business data' );
$assert( false === strpos( $hardening, '$wpdb->update' ), 'hardening must not update business data' );
$assert( false === strpos( $hardening, '$wpdb->delete' ), 'hardening must not delete business data' );

echo "Read-only multi-region admin hardening safeguards OK\n";
