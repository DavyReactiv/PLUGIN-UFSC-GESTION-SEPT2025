<?php
/** Runtime contract: every UFSC REST handler is callable and club scope is server-owned. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['ufsc_routes'] = array();
$GLOBALS['user_id'] = 10;
$GLOBALS['user_clubs'] = array( 10 => 101 );
$GLOBALS['caps'] = array();
$GLOBALS['regions'] = array( 101 => 'region-a', 102 => 'region-a', 201 => 'region-b' );

function add_action() {}
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['ufsc_routes'][ $namespace . $route ][] = $args; }
function is_user_logged_in() { return $GLOBALS['user_id'] > 0; }
function get_current_user_id() { return $GLOBALS['user_id']; }
function ufsc_get_user_club_id( $id ) { return $GLOBALS['user_clubs'][ $id ] ?? 0; }
function user_can( $id, $cap ) { return ! empty( $GLOBALS['caps'][ $id ][ $cap ] ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }

class UFSC_Permissions {
	const CAP_GESTION_READ = 'ufsc_gestion_read';
	const CAP_GESTION_MANAGE = 'ufsc_gestion_manage';
	const CAP_LICENCES_READ = 'ufsc_licences_read';
	const CAP_LICENCES_MANAGE = 'ufsc_licences_manage';
}
class UFSC_Scope {
	public static function get_club_region( $id ) { return $GLOBALS['regions'][ $id ] ?? null; }
	public static function is_in_scope( $region ) {
		if ( user_can( get_current_user_id(), 'manage_options' ) ) { return true; }
		return in_array( $region, $GLOBALS['caps'][ get_current_user_id() ]['regions'] ?? array(), true );
	}
}
class WP_Error {
	public $code;
	public function __construct( $code ) { $this->code = $code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_REST_Request {
	private $params;
	public function __construct( $params = array() ) { $this->params = $params; }
	public function get_param( $key ) { return $this->params[ $key ] ?? null; }
}

require dirname( __DIR__ ) . '/includes/api/class-rest-api.php';
UFSC_REST_API::register_routes();

foreach ( $GLOBALS['ufsc_routes'] as $route => $definitions ) {
	foreach ( $definitions as $definition ) {
		foreach ( array( 'callback', 'permission_callback' ) as $key ) {
			if ( empty( $definition[ $key ] ) || ! is_callable( $definition[ $key ] ) ) {
				fwrite( STDERR, "FAIL: {$route} {$key} is not callable\n" );
				exit( 1 );
			}
		}
	}
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
};
$request = static function ( $club_id ) { return new WP_REST_Request( array( 'club_id' => $club_id ) ); };

// Club A is pinned to Club A; neither a browser region nor a forged ID is trusted.
$assert( 101 === UFSC_REST_API::resolve_authorized_club_id( $request( 101 ) ), 'Club A -> Club A' );
$assert( is_wp_error( UFSC_REST_API::resolve_authorized_club_id( $request( 102 ) ) ), 'Club A -> Club B denied' );

// Regional operator has no linked club and is checked against the target club DB region.
$GLOBALS['user_id'] = 20;
$GLOBALS['caps'][20] = array( 'ufsc_gestion_read' => true, 'regions' => array( 'region-a' ) );
$assert( 102 === UFSC_REST_API::resolve_authorized_club_id( $request( 102 ) ), 'Region A -> Region A club' );
$assert( is_wp_error( UFSC_REST_API::resolve_authorized_club_id( $request( 201 ) ) ), 'Region A -> Region B denied' );

// UFSC administrator has global scope but still supplies a real, server-resolved club.
$GLOBALS['user_id'] = 30;
$GLOBALS['caps'][30] = array( 'manage_options' => true );
$assert( 201 === UFSC_REST_API::resolve_authorized_club_id( $request( 201 ) ), 'UFSC administrator global access' );

echo "All UFSC REST callbacks and Club/Region/Admin scope runtime safeguards OK\n";
