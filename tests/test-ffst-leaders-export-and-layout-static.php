<?php
$root = dirname( __DIR__ );
$export = file_get_contents( $root . '/includes/admin/class-ffst-leaders-export-fix.php' );
$layout = file_get_contents( $root . '/includes/admin/class-ffst-documents-layout-fix.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$failures = 0;
$assert = static function( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failures++;
    }
};

$assert( false !== strpos( $loader, 'class-ffst-leaders-export-fix.php' ), 'Le correctif bordereau dirigeants est chargé.' );
$assert( false !== strpos( $loader, 'class-ffst-documents-layout-fix.php' ), 'Le correctif de centrage Dossiers FFST est chargé.' );
$assert( false !== strpos( $export, "'president'" ) && false !== strpos( $export, "'secretaire'" ) && false !== strpos( $export, "'tresorier'" ), 'Les trois dirigeants obligatoires viennent aussi du compte club.' );
$assert( false !== strpos( $export, 'build_officers' ), 'Le bordereau consolide profil club et licences de saison.' );
$assert( false !== strpos( $export, "array( 'season', 'saison', 'paid_season', 'season_end_year' )" ), 'La sélection des licences reste isolée par saison.' );
$assert( false !== strpos( $export, 'Contrôle UFSC' ), 'Une feuille de contrôle accompagne le bordereau.' );
$assert( false === stripos( $export, 'add_to_cart' ), 'Le correctif bordereau ne touche pas au panier.' );
$assert( false === stripos( $export, 'woocommerce_' ), 'Le correctif bordereau ne touche pas à WooCommerce.' );
$assert( false === stripos( $export, 'DELETE FROM' ), 'Le correctif bordereau ne supprime aucune donnée.' );
$assert( false !== strpos( $layout, 'margin:18px auto 0!important' ), 'Les blocs 4 et 5 sont centrés.' );
$assert( false !== strpos( $layout, 'max-width:1180px!important' ), 'Les blocs 4 et 5 partagent une largeur maîtrisée.' );

if ( $failures ) { exit( 1 ); }
echo "OK: bordereau dirigeants prérempli et blocs FFST 4/5 centrés sans impact métier.\n";
