<?php
/** Static safeguards for the club authentication routing. */
$root = dirname( __DIR__ );
$auth = file_get_contents( $root . '/includes/frontend/class-auth-shortcodes.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $auth, "home_url( '/tableau-de-bord-club/' )" ), 'Canonical production dashboard slug must be used.' );
$assert( false === strpos( $auth, "home_url( '/club-dashboard/' )" ), 'Legacy /club-dashboard/ redirect must not remain in auth flow.' );
$assert( false !== strpos( $auth, "apply_filters( 'ufsc_club_dashboard_url'" ), 'Dashboard URL must be centralized and filterable.' );
$assert( substr_count( $auth, 'self::get_club_dashboard_url()' ) >= 4, 'Login form, logged-in summary and redirects must share the canonical helper.' );
$assert( false !== strpos( $auth, "'new_affiliation'" ) && false !== strpos( $auth, 'Nouvelle affiliation en cours' ), 'First affiliation wording must remain distinct from renewal.' );
$assert( false === strpos( $auth, 'UFSC_Stats::get_club_stats' ), 'Login summary must not run the expensive dashboard statistics aggregation.' );

echo "Club auth routing safeguards OK\n";
