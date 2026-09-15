<?php
$root = dirname( __DIR__ );
$export = file_get_contents( $root . '/includes/admin/class-clubs-flexible-export.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$assert = static function( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $loader, 'class-clubs-flexible-export.php' ), 'Le module export clubs intégré est chargé.' );
$assert( false !== strpos( $loader, 'UFSC_Clubs_Flexible_Export::init()' ), 'Le module export clubs intégré est initialisé.' );
$assert( false !== strpos( $export, 'Filtres Clubs' ), 'Le formulaire affiche un bloc de filtres Clubs dédié.' );
$assert( false !== strpos( $export, 'club_filter_season' ), 'Le filtre saison est présent.' );
$assert( false !== strpos( $export, 'club_filter_affiliation' ), 'Le filtre affiliation annuelle est présent.' );
$assert( false !== strpos( $export, 'club_filter_region' ), 'Le filtre région est présent.' );
$assert( false !== strpos( $export, 'club_filter_number' ), 'Le filtre numéro affiliation est présent.' );
$assert( false !== strpos( $export, 'club_filter_licences' ), 'Le filtre présence/nombre de licences est présent.' );
$assert( false !== strpos( $export, 'club_filter_search' ), 'La recherche club est présente.' );
$assert( false !== strpos( $export, 'get_annual_affiliations_table' ), 'Le filtre utilise la source canonique des affiliations annuelles.' );
$assert( false !== strpos( $export, "'active', 'validated', 'valide'" ), 'Les statuts actifs canoniques sont reconnus.' );
$assert( false !== strpos( $export, "'pending_payment', 'awaiting_payment', 'payment_pending'" ), 'Les statuts de paiement en attente sont reconnus.' );
$assert( false !== strpos( $export, 'ufsc_get_pack_season_storage_context' ), 'Le filtre licences respecte le stockage canonique de saison.' );
$assert( false !== strpos( $export, 'Aucun club ne correspond aux filtres sélectionnés.' ), 'Un filtre sans résultat bloque un export global accidentel.' );
$assert( ! preg_match( '/\$wpdb->(?:insert|update|delete|replace)\s*\(/i', $export ), 'Le moteur d’export reste strictement en lecture seule.' );
$assert( false === strpos( $loader, 'class-clubs-export-filter.php' ), 'Aucun module de filtre parallèle inutile n’est chargé.' );

echo "OK: filtres export clubs intégrés couverts\n";
