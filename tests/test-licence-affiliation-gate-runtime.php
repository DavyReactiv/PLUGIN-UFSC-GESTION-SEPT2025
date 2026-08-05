<?php
/** Runtime tests for the central annual-affiliation licence gate. */
define( 'ABSPATH', __DIR__ );
function __( $text ) { return $text; }
function sanitize_text_field( $v ) { return is_scalar( $v ) ? trim( (string) $v ) : ''; }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $v ) ); }
function absint( $v ) { return abs( (int) $v ); }
function current_time() { return '2026-08-05 00:00:00'; }
function get_current_user_id() { return 42; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function add_action() {}
function add_filter() {}
function ufsc_wc_log( $event, $payload = array(), $level = 'info' ) { Test_Log::$events[] = compact( 'event', 'payload', 'level' ); }
class Test_Log { public static $events = array(); }
class UFSC_Season_Service { public static function get_current_season() { return '2026-2027'; } }
class UFSC_Season_Archive_Manager {
    public static $rows = array();
    public static function get_affiliation( $club_id, $season ) { return self::$rows[$club_id . ':' . $season] ?? null; }
}
function ufsc_get_current_season() { return UFSC_Season_Service::get_current_season(); }
require __DIR__ . '/../inc/common/season.php';
$assert = function ( $cond, $msg ) { if ( ! $cond ) { fwrite( STDERR, "FAIL: $msg\n" ); exit( 1 ); } };
$row = function ( $status, $id = 10, $payment = '' ) { return (object) array( 'id' => $id, 'status' => $status, 'payment_status' => $payment ); };
$denied = array( '', 'renewal_required', 'pending', 'pending_payment', 'pending_validation', 'correction_required', 'rejected', 'refused', 'suspended', 'expired', 'cancelled', 'archived', 'mystery' );
foreach ( $denied as $status ) {
    UFSC_Season_Archive_Manager::$rows = $status === '' ? array() : array( '7:2026-2027' => $row( $status ) );
    $gate = ufsc_club_can_manage_licences_for_season( 7 );
    $assert( false === $gate['allowed'], "status $status must fail closed" );
    $assert( '2026-2027' === $gate['season'] && 7 === $gate['club_id'], 'structured context retained' );
}
UFSC_Season_Archive_Manager::$rows = array( '7:2025-2026' => $row( 'active' ) );
$assert( ! ufsc_club_can_manage_licences_for_season( 7, '2026-2027' )['allowed'], 'previous-season affiliation must not unlock current season' );
foreach ( array( 'active', 'validated' ) as $status ) {
    UFSC_Season_Archive_Manager::$rows = array( '7:2026-2027' => $row( $status, 99 ) );
    $gate = ufsc_club_can_manage_licences_for_season( 7, '2026-2027' );
    $assert( true === $gate['allowed'] && 'affiliation_active' === $gate['code'] && 99 === $gate['affiliation_id'], "$status must allow" );
}
UFSC_Season_Archive_Manager::$rows = array( '8:2026-2027' => $row( 'active' ) );
$assert( ! ufsc_club_can_manage_licences_for_season( 7, '2026-2027' )['allowed'], 'forged other club_id must fail' );
ufsc_log_licence_affiliation_refusal( ufsc_club_can_manage_licences_for_season( 7, '2026-2027' ), 'test_entry', 123 );
ufsc_log_licence_affiliation_refusal( ufsc_club_can_manage_licences_for_season( 7, '2026-2027' ), 'test_entry', 123 );
$assert( 1 === count( Test_Log::$events ), 'duplicate refusal logs are throttled per request' );
echo "OK: central licence affiliation gate runtime\n";
