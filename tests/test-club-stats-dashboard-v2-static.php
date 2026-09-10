<?php
/** Static safeguards for the current-season club statistics presentation. */
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
$assert( false !== strpos( $module, 'Dossiers saisis' ), 'Dashboard must distinguish entered dossiers.' );
$assert( false !== strpos( $module, 'Inclus dans le pack' ), 'Dashboard must expose included quota consumption.' );
$assert( false !== strpos( $module, 'En attente UFSC' ), 'Dashboard must expose dossiers awaiting UFSC.' );
$assert( false !== strpos( $module, 'Paiements licence reçus' ), 'Paid KPI wording must explain individual payments.' );
$assert( false !== strpos( $module, 'Répartition des dossiers' ), 'Dashboard must use a distribution label instead of a fake evolution label.' );
$assert( false !== strpos( $module, 'birth_years' ) && false !== strpos( $module, 'Détail par année de naissance' ), 'Birth-year data must remain useful when dossiers are not validated yet.' );
$assert( false === strpos( $module, 'UPDATE ' ) && false === strpos( $module, 'DELETE FROM' ) && false === strpos( $module, 'ALTER TABLE' ), 'Statistics v2 must stay read-only.' );
$assert( false !== strpos( $core, 'if ( ! $official ) { continue; }' ), 'Canonical official-only demographic contract must stay untouched.' );

if ( $failures ) {
    fwrite( STDERR, "Club statistics v2 safeguards failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}

echo "Club statistics v2 safeguards: OK\n";
