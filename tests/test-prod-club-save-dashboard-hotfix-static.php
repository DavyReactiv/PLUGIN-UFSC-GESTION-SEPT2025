<?php
$root = dirname( __DIR__ );
$hotfix = file_get_contents( $root . '/inc/common/prod-club-save-dashboard-hotfix.php' );
$loader = file_get_contents( $root . '/inc/common/front-club-registration-scope-hotfix.php' );

$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { $failures[] = $message; }
};

$assert( false !== strpos( $loader, 'prod-club-save-dashboard-hotfix.php' ), 'Le hotfix production doit être chargé.' );
$assert( false !== strpos( $hotfix, "'ufsc_sql_save_club'" ), 'Le normaliseur doit rester limité au save club admin.' );
$assert( false !== strpos( $hotfix, "'number' === \$type && '' === \$value" ), 'Les nombres vides doivent être retirés avant MySQL strict.' );
$assert( false !== strpos( $hotfix, "'date' === \$type" ), 'Les dates vides/zéro doivent être protégées.' );
$assert( false !== strpos( $hotfix, "'num_affiliation'" ), 'Le numéro d’affiliation vide doit être protégé sur une édition existante.' );
$assert( false !== strpos( $hotfix, "'rna_number'" ), 'Le RNA vide doit être protégé sur une édition existante.' );
$assert( false !== strpos( $hotfix, "'siren'" ), 'Le SIREN vide doit être protégé sur une édition existante.' );
$assert( false !== strpos( $hotfix, "SELECT statut FROM" ), 'Le statut existant doit être conservé lorsque non soumis.' );
$assert( false !== strpos( $hotfix, '$wpdb->last_error' ), 'La vraie erreur SQL doit pouvoir être journalisée sans exposition utilisateur.' );
$assert( false !== strpos( $hotfix, 'DONOTCACHEPAGE' ), 'Le portail dynamique doit désactiver le cache de page.' );
$assert( false !== strpos( $hotfix, "litespeed_control_set_nocache" ), 'Le portail doit signaler le bypass à LiteSpeed si disponible.' );

if ( $failures ) {
    foreach ( $failures as $failure ) { fwrite( STDERR, "FAIL: {$failure}\n" ); }
    exit( 1 );
}

echo "OK: production club save/dashboard hotfix static contract\n";
