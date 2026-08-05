<?php
/** Static regression contract for exclusive admin-club season views. */
$clubs = file_get_contents( dirname( __DIR__ ) . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$admin = file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-sql-admin.php' );
$assert = static function ( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
    echo "PASS: {$message}\n";
};
$assert( false !== strpos( $clubs, "empty( \$filters['kpi_filter'] )" ), 'KPI views do not inherit implicit season evidence.' );
$assert( false !== strpos( $clubs, "array_merge( \$active_statuses, \$pending_statuses )" ), 'Renewals exclude validated and already-open requests.' );
$assert( false !== strpos( $clubs, "'affiliations_active'" ) && false !== strpos( $clubs, "'annual_numbers_missing'" ), 'Active and missing-number views use shared KPI conditions.' );
$assert( false !== strpos( $clubs, 'Nom du club, email ou numéro d’affiliation' ), 'Club search has the required business label.' );
$assert( false !== strpos( $clubs, "array( 'nom', 'email', 'num_affiliation', 'ville' )" ), 'Search covers permanent club identifiers.' );
$assert( false !== strpos( $clubs, 'aq.num_affiliation LIKE %s' ), 'Search covers annual affiliation number.' );
$assert( false !== strpos( $clubs, 'Effacer tous les filtres' ) && false !== strpos( $clubs, '× ' ), 'Business filter chips are removable.' );
$assert( false === strpos( $clubs, "'<code>' . esc_html( implode" ), 'Raw technical GET summary is absent.' );
$assert( false !== strpos( $clubs, 'Aucun club ne correspond aux filtres actuels.' ), 'Empty state explains filters and recovery.' );
$assert( false !== strpos( $clubs, "'paged'" ) && false !== strpos( $clubs, "remove_query_arg( array( \$key, 'paged' )" ), 'Changing/removing filters resets pagination.' );
$assert( false !== strpos( $clubs, "'return_to' => \$return_to" ) && false !== strpos( $admin, 'get_admin_return_url' ), 'Club detail return URL is validated and preserved.' );
$assert( false !== strpos( $clubs, 'Renouveler pour %s' ) && false !== strpos( $clubs, 'Attribuer le numéro' ), 'Renewal and annual-number actions are contextual.' );
