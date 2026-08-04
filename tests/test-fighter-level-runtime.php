<?php
define( 'ABSPATH', __DIR__ );
function __( $text ) { return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function apply_filters( $hook, $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
require dirname( __DIR__ ) . '/inc/common/fighter-level.php';

$birth = static function ( $age ) { return gmdate( 'Y-m-d', strtotime( '-' . $age . ' years' ) ); };
$assert = static function ( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
	echo "PASS: {$message}\n";
};
$assert( true === ufsc_validate_fighter_level( 'assaut', $birth( 17 ), false ), 'mineur avec Assaut' );
$assert( is_wp_error( ufsc_validate_fighter_level( 'classe_a', $birth( 17 ), false ) ), 'mineur refusé avec Classe A' );
foreach ( array( 'classe_c', 'classe_b', 'classe_a' ) as $level ) {
	$assert( true === ufsc_validate_fighter_level( $level, $birth( 18 ), false ), "majeur accepté en {$level}" );
}
$assert( is_wp_error( ufsc_validate_fighter_level( 'veteran', $birth( 40 ), false ) ), 'Vétéran refusé avant 41 ans' );
$assert( true === ufsc_validate_fighter_level( 'veteran', $birth( 41 ), false ), 'Vétéran accepté à 41 ans' );
$assert( true === ufsc_validate_fighter_level( '', $birth( 60 ), true ), 'ancienne licence NULL/non renseignée acceptée' );
$assert( 'Non renseigné' === ufsc_fighter_level_label( null ), 'export historique libellé Non renseigné' );
$assert( 'Classe A' === ufsc_fighter_level_label( 'classe_a' ), 'export libellé Classe A' );
echo "Fighter-level runtime safeguards OK\n";
