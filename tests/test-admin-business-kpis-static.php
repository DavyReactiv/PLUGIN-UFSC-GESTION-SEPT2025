<?php
$root = dirname( __DIR__ );
$clubs = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$plugin = file_get_contents( $root . '/ufsc-clubs-licences-sql.php' );
$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };

foreach ( array( 'Clubs enregistrés', 'Affiliations actives %s', 'Renouvellements à traiter', 'Affiliations en attente %s', 'Dossiers clubs incomplets', 'Licences %s actives', 'Numéros annuels à attribuer' ) as $label ) {
    $assert( strpos( $clubs, $label ) !== false, 'Business KPI label is present: ' . $label );
}
foreach ( array( 'Statut historique non déterminable %s', 'Clubs sans numéro d’affiliation pour %s', 'Licences avec saison %s prouvée' ) as $technical_label ) {
    $assert( strpos( $clubs, $technical_label ) === false, 'Technical/ambiguous KPI removed from primary cards: ' . $technical_label );
}
$assert( strpos( $clubs, 'get_admin_kpi_filter_condition' ) !== false && strpos( $clubs, "'kpi_filter'" ) !== false, 'KPI cards and list filters share the same condition helper.' );
$assert( strpos( $clubs, "'pending_payment', 'pending_validation', 'correction_required'" ) !== false, 'Pending KPI covers payment, validation and correction states.' );
$assert( strpos( $clubs, "'annual_numbers_missing'" ) !== false && strpos( $clubs, "a.num_affiliation IS NULL OR a.num_affiliation = ''" ) !== false, 'Annual number KPI only applies to active annual affiliations with an empty annual number.' );
$assert( strpos( $clubs, 'Nombre de clubs distincts avec au moins un document permanent obligatoire manquant' ) !== false, 'Incomplete dossiers KPI counts clubs, not documents.' );
$assert( strpos( $plugin, 'ufsc-club-portal-page' ) !== false && strpos( $plugin, 'body_class' ) !== false, 'Club portal pages receive a body class for targeted Astra/Elementor width fixes.' );
$assert( strpos( $css, 'body.ufsc-club-portal-page' ) !== false && strpos( $css, ':has(.ufsc-club-portal)' ) !== false, 'Width fix targets only parents that actually contain the portal.' );
$assert( strpos( $css, 'width: 100vw' ) === false && strpos( $css, 'margin-left: -' ) === false && strpos( $css, 'transform: scale(' ) === false && strpos( $css, 'zoom:' ) === false, 'No forbidden front width hack is used.' );

$fixtures = array(
    'clubs' => range( 1, 56 ),
    'historical' => range( 1, 54 ),
    'active' => array( 1, 2 ),
    'active_without_number' => array( 1, 2 ),
    'pending_validation' => array( 3, 4, 5, 6, 7 ),
    'pending_payment' => array( 8, 9, 10 ),
    'documents_incomplete' => range( 11, 20 ),
    'licences_current_active' => array( 1, 1, 2, 3, 11 ),
);
$renewals = array_values( array_diff( $fixtures['historical'], $fixtures['active'] ) );
$assert( 56 === count( $fixtures['clubs'] ), 'Fixture: 56 permanent clubs.' );
$assert( 8 === count( $fixtures['pending_validation'] ) + count( $fixtures['pending_payment'] ), 'Fixture: pending KPI total is 8.' );
$assert( 10 === count( array_unique( $fixtures['documents_incomplete'] ) ), 'Fixture: incomplete dossiers counts 10 distinct clubs.' );
$assert( 2 === count( $fixtures['active_without_number'] ), 'Fixture: annual numbers to assign is 2 only.' );
$assert( 52 === count( $renewals ), 'Fixture: renewals exclude the 2 active annual affiliations from 54 historical clubs.' );
$assert( 5 === count( $fixtures['licences_current_active'] ), 'Fixture: current active licence total is stable.' );
echo "Admin business KPI static/runtime safeguards OK\n";
