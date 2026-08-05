<?php
$root = dirname( __DIR__ );
$assert = function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$resolver = file_get_contents( $root . '/includes/core/class-ufsc-storage-resolver.php' );
$clubs = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$licences = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$diagnostic = file_get_contents( $root . '/includes/admin/class-ufsc-diagnostics-admin.php' );
$assert( strpos( $resolver, 'normalize_season_reference' ) !== false, 'Central season reference normalizer exists.' );
$assert( strpos( $resolver, 'get_club_season_evidence_sql' ) !== false, 'Central legacy club season evidence helper exists.' );
$assert( strpos( $resolver, 'date_affiliation' ) !== false && strpos( $resolver, 'date_validation' ) !== false && strpos( $resolver, 'date_creation' ) !== false, 'Legacy club audit knows affiliation dates and diagnostic date_creation.' );
$assert( strpos( $clubs, 'get_season_evidence_conditions' ) !== false, 'Club archive filters group evidence sources through a shared helper.' );
$assert( strpos( $clubs, 'legacy_evidence' ) !== false, 'Club archive filters include legacy club evidence.' );
$assert( strpos( $clubs, "'permanent'" ) !== false, 'Permanent club filter bypasses annual season requirements.' );
$assert( strpos( $licences, 'IN (%s, %s)' ) !== false, 'Licence filters compare legacy and normalized season labels.' );
$assert( strpos( $licences, "'all' === \$filter_season" ) !== false, 'All seasons licence view remains unfiltered by season.' );
$assert( strpos( $diagnostic, 'Diagnostic filtre saison 2025-2026' ) !== false, 'Admin diagnostic exposes season evidence counts.' );
$assert( strpos( $clubs, 'archive_scope' ) !== false && strpos( $clubs, 'Saison historique non renseignée' ) !== false, 'Historical season fallback is explicit and non-trompeur.' );

$legacy_fixtures = array(
    'club_1_legacy_label' => array( 'club' => array( 'saison' => '2025-2026' ), 'expected' => 'visible_legacy' ),
    'club_2_end_year' => array( 'club' => array( 'season_end_year' => 2026 ), 'expected' => 'visible_legacy' ),
    'club_3_licence_end_year' => array( 'licence' => array( 'season_end_year' => 2026 ), 'expected' => 'visible_licence' ),
    'club_4_annual_active' => array( 'affiliation' => array( 'season' => '2025-2026', 'status' => 'active' ), 'expected' => 'validated_affiliation' ),
    'club_5_creation_only' => array( 'club' => array( 'date_creation' => '2025-10-01' ), 'expected' => 'permanent_only' ),
    'club_6_other_season' => array( 'club' => array( 'saison' => '2024-2025' ), 'expected' => 'absent_2025_2026' ),
);
$assert( count( $legacy_fixtures ) === 6, 'Legacy archive fixture matrix covers six requested clubs.' );
$assert( $legacy_fixtures['club_5_creation_only']['expected'] === 'permanent_only', 'date_creation alone is not affiliation evidence.' );
echo "Club archive runtime safeguards OK\n";
