<?php
define( 'ABSPATH', __DIR__ );
function __( $text ) { return $text; }
function remove_accents( $text ) {
    return strtr( (string) $text, array(
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'E',
        'à'=>'a','â'=>'a','ä'=>'a','À'=>'A',
        'ç'=>'c','Ç'=>'C','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u'
    ) );
}
require dirname( __DIR__ ) . '/includes/admin/class-ffst-club-profile-fields.php';

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
};

$assert( false === UFSC_FFST_Club_Profile_Fields::is_foreign_birth_country( 'France' ), 'France is not foreign' );
$assert( false === UFSC_FFST_Club_Profile_Fields::is_foreign_birth_country( 'française' ), 'French nationality alias stays local' );
$assert( true === UFSC_FFST_Club_Profile_Fields::is_foreign_birth_country( 'Algérie' ), 'Algeria is detected as foreign' );

$foreign = array(
    'president_pays_naissance' => 'Algérie',
    'president_pere_nom' => '',
    'president_pere_prenom' => '',
    'president_mere_nom' => '',
    'president_mere_prenom' => '',
);
$errors = UFSC_FFST_Club_Profile_Fields::validate_foreign_parent_identity( $foreign, 0 );
$assert( 4 === count( $errors ), 'foreign-born leader requires four split parent identity values' );

$complete = array(
    'president_pays_naissance' => 'Algérie',
    'president_pere_nom' => 'BENALI',
    'president_pere_prenom' => 'Ahmed',
    'president_mere_nom' => 'KHELIFI',
    'president_mere_prenom' => 'Nadia',
);
$assert( array() === UFSC_FFST_Club_Profile_Fields::validate_foreign_parent_identity( $complete, 0 ), 'complete foreign parent identity is accepted' );

$partial = $complete;
$partial['president_pere_prenom'] = '';
$assert( 1 === count( UFSC_FFST_Club_Profile_Fields::validate_foreign_parent_identity( $partial, 0 ) ), 'partial split father identity is rejected' );

$legacy = array(
    'president_pays_naissance' => 'Algérie',
    'president_pere_nom_prenom' => 'BENALI Ahmed',
    'president_mere_nom_prenom' => 'KHELIFI Nadia',
);
$assert( array() === UFSC_FFST_Club_Profile_Fields::validate_foreign_parent_identity( $legacy, 0 ), 'legacy combined identities remain non-blocking without destructive migration' );

$french = array(
    'president_pays_naissance' => 'France',
    'president_pere_nom' => '',
    'president_pere_prenom' => '',
    'president_mere_nom' => '',
    'president_mere_prenom' => '',
);
$assert( array() === UFSC_FFST_Club_Profile_Fields::validate_foreign_parent_identity( $french, 0 ), 'parent identity is not required for French birth' );

echo "Foreign parent identity runtime safeguards OK\n";
