<?php
/** Static safeguards for the contextual navigation of a front licence detail. */
$front = file_get_contents( __DIR__ . '/../includes/frontend/class-frontend-shortcodes.php' );
$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
};

$assert( false !== strpos( $front, "isset( \$_GET['view_licence'] )" ), 'The detail renderer is selected with view_licence.' );
$assert( false !== strpos( $front, 'public static function render_single_licence' ), 'The licence detail renderer remains explicit.' );
$assert( false !== strpos( $front, '← Retour à mes licences' ), 'The detail provides a prominent list return action.' );
$assert( false !== strpos( $front, 'wp_validate_redirect' ), 'Return candidates pass through WordPress redirect validation.' );
$assert( false !== strpos( $front, '$candidate_host !== $site_host' ), 'Return candidates are restricted to this site.' );
$assert( false !== strpos( $front, "\$_GET['ufsc_return']" ), 'An explicit filter-preserving return URL is supported.' );
$assert( false !== strpos( $front, 'wp_get_referer()' ), 'A validated portal referer is the secondary return source.' );
$assert( false !== strpos( $front, "self::get_club_portal_url( 'club-licences' )" ), 'The canonical licence-list anchor is the fallback.' );
$assert( false !== strpos( $front, "'overview'          => 'ufsc-overview'" ), 'Overview resolves to its real anchor.' );
$assert( false !== strpos( $front, "'club-information'  => 'ufsc-club-information'" ), 'Club information resolves to the account page anchor.' );
$assert( false !== strpos( $front, "'club-officers'     => 'ufsc-club-officers'" ), 'Officers resolve to the account page anchor.' );
$assert( false !== strpos( $front, "'club-documents'    => 'ufsc-club-documents'" ), 'Documents resolve to the account page anchor.' );
$assert( false !== strpos( $front, "'licences-archives' => 'ufsc-licences-archives'" ), 'Archives resolve to the dashboard anchor.' );
$assert( false !== strpos( $front, "id=\"ufsc-club-licences\"" ), 'The canonical licence-list target exists.' );
$assert( false !== strpos( $front, "https://ufsc-france.fr/ufsc-reglements-sportifs-techniques-interieur/" ), 'The official sports rules URL is present.' );
$assert( false === strpos( $front, 'href="#ufsc-overview"' ), 'Portal navigation no longer uses a page-relative overview link.' );
$assert( false === strpos( $front, 'href="#"' ), 'No inactive hash-only link is introduced.' );
$assert( false !== strpos( $front, 'self::get_licence( $club_id, $licence_id )' ), 'Detail lookup remains scoped to the connected club.' );
$assert( false !== strpos( $front, 'WHERE {$where}", $licence_id, $club_id' ), 'The licence query still binds both licence and club IDs.' );

echo "Front licence detail navigation checks passed.\n";
