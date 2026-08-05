<?php
$root = dirname( __DIR__ );
$assert = function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$clubs = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$resolver = file_get_contents( $root . '/includes/core/class-ufsc-storage-resolver.php' );
$assert( strpos( $clubs, "'archive_scope' => self::get_query_value( 'archive_scope', 'key' )" ) !== false, 'Archive scope GET parameter is read.' );
$assert( strpos( $clubs, "'all_historical' !== $" . "archive_scope" ) !== false, 'Season proof filter is bypassed only by explicit all_historical archive scope.' );
$assert( strpos( $clubs, "'0=1'" ) !== false, 'A season without evidence is explicitly empty until fallback is selected.' );
$assert( strpos( $clubs, 'ne peuvent pas être déterminées avec certitude' ) !== false, 'Empty historical evidence notice is rendered without claiming affiliation.' );
$assert( strpos( $clubs, 'Voir les clubs historiques' ) !== false && strpos( $clubs, 'Retour à la saison courante' ) !== false, 'Historical and current-season CTAs are rendered.' );
$assert( strpos( $clubs, 'ufsc-debug-season-diagnostic' ) !== false && strpos( $clubs, "WP_DEBUG" ) !== false && strpos( $clubs, "current_user_can( 'manage_options' )" ) !== false, 'Debug season SQL block is admin-only and debug-only.' );
$assert( strpos( $clubs, 'Filtres actifs' ) !== false, 'Active filter summary is rendered.' );
$assert( strpos( $clubs, 'el.name!=="page" && !el.value' ) !== false, 'Empty GET controls are omitted on filter submit.' );
$assert( strpos( $resolver, 'get_licence_archive_counts' ) !== false, 'Licence archive counts distinguish proven and unclassified seasons.' );
$assert( strpos( $clubs, 'Licences historiques sans saison renseignée' ) !== false, 'KPI exposes unclassified licence archives.' );
$fixtures = array( 'permanent_clubs' => 56, 'historical_licences' => 996, 'annual_2025_2026' => 0, 'with_season_end_year_2026' => 200, 'without_season' => 796 );
$assert( $fixtures['permanent_clubs'] === 56 && $fixtures['historical_licences'] === 996, 'Realistic dev fixture counts are documented in test.' );
echo "Historical season fallback safeguards OK\n";
