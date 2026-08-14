<?php
define( 'ABSPATH', __DIR__ );
function __( $text ) { return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function apply_filters( $hook, $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error { private $message; public function __construct($code,$message){$this->message=$message;} public function get_error_message(){return $this->message;} }
require dirname( __DIR__ ) . '/inc/common/fighter-level.php';
$birth = static function ( $age ) { return gmdate( 'Y-m-d', strtotime( '-' . $age . ' years' ) ); };
$assert = static function ( $condition, $message ) { if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);} echo "PASS: {$message}\n"; };
$assert( array_keys( ufsc_get_sport_level_options() ) === array( 'pro','classe_a','classe_b','classe_c','assaut','veteran' ), 'liste officielle PRO A B C ASSAUT VETERAN' );
$assert( 'assaut' === ufsc_get_default_fighter_level( $birth(17) ), 'mineur propose Assaut par défaut' );
$assert( 'classe_c' === ufsc_get_default_fighter_level( $birth(18) ), 'majeur propose Classe C par défaut' );
$assert( true === ufsc_validate_fighter_level( 'assaut', $birth(17), false ), 'mineur avec Assaut' );
$assert( is_wp_error( ufsc_validate_fighter_level( 'classe_a', $birth(17), false ) ), 'mineur refusé avec Classe A' );
foreach(array('assaut','classe_c','classe_b','classe_a','pro') as $level){$assert(true===ufsc_validate_fighter_level($level,$birth(25),false),"majeur accepté en {$level}");}
$assert( is_wp_error( ufsc_validate_fighter_level( 'veteran', $birth(40), false ) ), 'Vétéran refusé avant 41 ans' );
$assert( true === ufsc_validate_fighter_level( 'veteran', $birth(41), false ), 'Vétéran accepté à 41 ans' );
$assert( true === ufsc_validate_fighter_level( '', $birth(60), true ), 'ancienne licence vide acceptée en lecture historique' );
$assert( true === ufsc_validate_fighter_level( 'debutant', $birth(30), true ), 'ancienne valeur Débutant reste compatible historiquement' );
$assert( 'Débutant' === ufsc_fighter_level_label( 'debutant' ), 'ancienne valeur Débutant reste lisible' );
$assert( 'Non renseigné' === ufsc_fighter_level_label( null ), 'ancienne licence sans niveau reste Non renseigné' );
echo "Fighter-level defaults and history safeguards OK\n";
