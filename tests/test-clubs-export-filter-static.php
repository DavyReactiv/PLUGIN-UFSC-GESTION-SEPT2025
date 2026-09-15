<?php
$root = dirname( __DIR__ );
$filter = file_get_contents( $root . '/includes/admin/class-clubs-export-filter.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$assert = static function( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $loader, 'class-clubs-export-filter.php' ), 'Le module filtre export clubs est chargé.' );
$assert( false !== strpos( $loader, 'UFSC_Clubs_Export_Filter::init()' ), 'Le module filtre export clubs est initialisé.' );
$assert( false !== strpos( $filter, 'get_annual_affiliations_table' ), 'Le filtre utilise la source canonique des affiliations annuelles.' );
$assert( false !== strpos( $filter, "'active','validated','valide'" ), 'Les statuts actifs canoniques sont reconnus côté interface.' );
$assert( false !== strpos( $filter, 'Aucun club ne correspond aux filtres sélectionnés.' ), 'Un filtre sans résultat bloque un export global accidentel.' );
$assert( false !== strpos( $filter, 'visible.forEach' ), 'Sans sélection explicite, seuls les résultats visibles sont sélectionnés pour export.' );
$assert( ! preg_match( '/\$wpdb->(?:insert|update|delete|replace)\s*\(/i', $filter ), 'Le module de filtrage reste en lecture seule.' );

echo "OK: filtre export clubs couvert\n";
