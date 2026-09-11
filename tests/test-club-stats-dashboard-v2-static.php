<?php
/** Static safeguards for validated-only club statistics. */
$root   = dirname( __DIR__ );
$flags  = file_get_contents( $root . '/inc/common/feature-flags.php' );
$module = file_get_contents( $root . '/inc/common/club-stats-dashboard-v2.php' );
$core   = file_get_contents( $root . '/includes/front/class-ufsc-stats.php' );
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { $failures[] = $message; }
};

$assert( false !== strpos( $flags, 'club-stats-dashboard-v2.php' ), 'Statistics v2 module must be loaded.' );
$assert( false !== strpos( $module, "'ufsc_club_dashboard' !== \$tag" ), 'Statistics v2 must be scoped to the club dashboard.' );
$assert( false !== strpos( $module, 'ufsc_resolve_licence_business_state' ), 'Statistics must use the canonical licence business state.' );
$assert( false !== strpos( $module, 'if ( ! $official )' ) && false !== strpos( $module, 'continue;' ), 'Draft and pending dossiers must be excluded before aggregation.' );
$assert( false !== strpos( $module, 'Licences validées' ), 'Dashboard must label the official validated population explicitly.' );
$assert( false !== strpos( $module, 'Brouillons et dossiers en attente exclus' ), 'Dashboard must explain that non-validated dossiers are excluded.' );
$assert( false !== strpos( $module, 'Aucune licence validée pour cette saison.' ), 'Zero-state must explain why profile statistics can be zero.' );
$assert( false !== strpos( $module, 'Répartition des licences validées' ), 'Distribution title must reflect validated-only scope.' );
$assert( false !== strpos( $module, 'birth_years' ) && false !== strpos( $module, 'Détail par année de naissance' ), 'Birth-year distribution must remain available for validated licences.' );
$assert( false === strpos( $module, 'tous les dossiers saisis' ), 'Statistics must not claim to include drafts or pending dossiers.' );
$assert( false === strpos( $module, 'UPDATE ' ) && false === strpos( $module, 'DELETE FROM' ) && false === strpos( $module, 'ALTER TABLE' ), 'Statistics v2 must stay read-only.' );
$assert( false !== strpos( $core, 'if ( ! $official ) { continue; }' ), 'Canonical official-only demographic contract must stay untouched.' );

if ( $failures ) {
    fwrite( STDERR, "Club statistics v2 safeguards failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}

echo "Club statistics v2 safeguards: OK\n";
