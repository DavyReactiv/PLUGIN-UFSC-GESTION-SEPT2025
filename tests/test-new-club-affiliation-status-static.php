<?php
/** Static safeguards for first-affiliation versus renewal wording on the login summary. */
$root = dirname( __DIR__ );
$auth = file_get_contents( $root . '/includes/frontend/class-auth-shortcodes.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( strpos( $auth, 'club_has_affiliation_history' ) !== false, 'Login summary must distinguish first affiliation from historical renewal.' );
$assert( strpos( $auth, "'new_affiliation'" ) !== false, 'A dedicated new-affiliation state must exist.' );
$assert( strpos( $auth, 'Nouvelle affiliation en cours' ) !== false, 'New clubs must not be labelled as renewals.' );
$assert( strpos( $auth, 'Affiliation %s à finaliser' ) !== false, 'New clubs must receive a first-affiliation completion message.' );
$assert( strpos( $auth, "UFSC_Stats::get_club_stats" ) === false, 'The login summary must not run the full club statistics aggregation.' );
$assert( strpos( $auth, 'Affiliation %s à renouveler' ) !== false, 'Historical clubs must retain renewal wording.' );

echo "New club affiliation status static safeguards OK\n";
