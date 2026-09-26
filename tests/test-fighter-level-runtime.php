<?php
define( 'ABSPATH', __DIR__ );
function __( $text ) { return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function apply_filters( $hook, $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error { private $message; public function __construct($code,$message){$this->message=$message;} public function get_error_message(){return $this->message;} }
require dirname( __DIR__ ) . '/inc/common/fighter-level.php';

$birth_year = static function ( $year ) { return sprintf( '%04d-06-15', $year ); };
$season = '2026-2027';
$assert = static function ( $condition, $message ) { if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);} echo "PASS: {$message}\n"; };

$levels = ufsc_get_sport_level_options();
$assert( array_keys( $levels ) === array( 'pro','classe_a','classe_b','combat','assaut','veteran' ), 'liste canonique PRO A B COMBAT ASSAUT VETERAN sans Classe C' );
$assert( ! isset( $levels['classe_c'] ), 'Classe C n est plus selectable' );
$assert( 'classe_b' === ufsc_normalize_fighter_level( 'classe_c' ), 'ancienne valeur classe_c est normalisee vers Classe B' );
$assert( 'classe_b' === ufsc_normalize_fighter_level( 'senior combat classe c' ), 'ancien libelle Classe C est normalise vers Classe B' );

$assert( 14 === ufsc_competition_age_from_birth_date( $birth_year(2012), $season ), 'age sportif calcule avec annee 1 de saison' );
$assert( 'assaut' === ufsc_get_default_fighter_level( $birth_year(2012), $season ), 'cadet 1re annee propose Assaut' );
$assert( 'assaut' === ufsc_get_default_fighter_level( $birth_year(2011), $season ), 'cadet 2e annee conserve Assaut par defaut' );
$assert( 'assaut' === ufsc_get_default_fighter_level( $birth_year(2009), $season ), 'junior conserve Assaut par defaut' );
$assert( 'classe_b' === ufsc_get_default_fighter_level( $birth_year(2008), $season ), 'senior 18 ans propose Classe B par defaut' );

$assert( true === ufsc_validate_fighter_level( 'assaut', $birth_year(2012), false, $season ), 'cadet 1re annee Assaut accepte' );
$assert( is_wp_error( ufsc_validate_fighter_level( 'combat', $birth_year(2012), false, $season ) ), 'Combat refuse avant Cadet 2e annee' );
$assert( true === ufsc_validate_fighter_level( 'combat', $birth_year(2011), false, $season ), 'Cadet 2e annee Combat accepte' );
$assert( true === ufsc_validate_fighter_level( 'combat', $birth_year(2009), false, $season ), 'Junior Combat accepte' );
$assert( is_wp_error( ufsc_validate_fighter_level( 'combat', $birth_year(2008), false, $season ) ), 'Combat generique refuse en Senior' );

foreach(array('assaut','classe_b','classe_a','pro') as $level){
    $assert(true===ufsc_validate_fighter_level($level,$birth_year(2000),false,$season),"Senior 26 ans accepte en {$level}");
}
$assert( is_wp_error( ufsc_validate_fighter_level( 'classe_a', $birth_year(2009), false, $season ) ), 'Junior refuse avec Classe A' );
$assert( is_wp_error( ufsc_validate_fighter_level( 'classe_b', $birth_year(1985), false, $season ) ), '41 ans refuse en Classe B Senior' );
$assert( true === ufsc_validate_fighter_level( 'veteran', $birth_year(1985), false, $season ), 'Veteran accepte a 41 ans' );
$assert( true === ufsc_validate_fighter_level( '', $birth_year(1966), true, $season ), 'ancienne licence vide acceptee en lecture historique' );
$assert( true === ufsc_validate_fighter_level( 'debutant', $birth_year(1996), true, $season ), 'ancienne valeur Debutant reste compatible historiquement' );
$assert( 'Débutant' === ufsc_fighter_level_label( 'debutant' ), 'ancienne valeur Debutant reste lisible' );
$assert( 'Non renseigné' === ufsc_fighter_level_label( null ), 'ancienne licence sans niveau reste Non renseigne' );

echo "Fighter-level 2026-2027 safeguards OK\n";
