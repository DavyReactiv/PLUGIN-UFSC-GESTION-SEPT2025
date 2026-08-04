<?php
/** Executable storage test for annual affiliation and document history. */
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error { private $message; public function __construct( $code, $message ) { $this->message = $message; } public function get_error_message() { return $this->message; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $s ) { return $s; } function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( (string) $v ); } function sanitize_textarea_field( $v ) { return trim( (string) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function current_time() { return '2026-08-04 12:00:00'; } function wp_json_encode( $v ) { return json_encode( $v ); }
function get_current_user_id() { return 99; }
$GLOBALS['options'] = array(); $GLOBALS['post_meta'] = array();
function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['options'][ $k ] = $v; return true; }
function get_post_meta( $id, $key ) { return $GLOBALS['post_meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['post_meta'][ $id ][ $key ] = $value; return true; }
function wp_get_attachment_url( $id ) { return $id ? 'https://test/' . $id : ''; }
function get_the_title( $id ) { return 'document-' . $id; } function get_attached_file() { return ''; } function get_the_date() { return ''; }
class Runtime_WPDB {
    public $prefix = 'wp_'; public $rows = array(); public $insert_id = 0;
    public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
    public function get_row( $prepared ) { $args = $prepared[1]; return $this->rows[ $args[0] . '|' . $args[1] ] ?? null; }
    public function insert( $table, $data ) { $this->insert_id++; $data['id'] = $this->insert_id; $this->rows[ $data['club_id'] . '|' . $data['season'] ] = (object) $data; return 1; }
    public function update( $table, $data, $where ) { foreach ( $this->rows as $key => $row ) { if ( (int) $row->id === (int) $where['id'] ) { $this->rows[$key] = (object) array_merge( (array) $row, $data ); return 1; } } return false; }
}
$GLOBALS['wpdb'] = new Runtime_WPDB();
require dirname( __DIR__ ) . '/includes/core/class-ufsc-season-archive-manager.php';
$result = UFSC_Season_Archive_Manager::save_admin_affiliation( 56, '2026-2027', array( 'status' => 'active', 'payment_status' => 'paid', 'request_type' => 'offline', 'num_affiliation' => 'UFSC-26-56' ), 99 );
if ( true !== $result ) { fwrite( STDERR, "Annual creation failed\n" ); exit( 1 ); }
$result = UFSC_Season_Archive_Manager::save_admin_affiliation( 56, '2026-2027', array( 'status' => 'suspended', 'decision_reason' => 'Pièce expirée' ), 99 );
$row = UFSC_Season_Archive_Manager::get_affiliation( 56, '2026-2027' );
if ( true !== $result || 'suspended' !== $row->status || 2 !== count( json_decode( $row->review_history, true ) ) ) { fwrite( STDERR, "Annual status/history failed\n" ); exit( 1 ); }
$invalid = UFSC_Season_Archive_Manager::save_admin_affiliation( 56, '2026-2027', array( 'status' => 'rejected' ), 99 );
if ( ! $invalid instanceof WP_Error ) { fwrite( STDERR, "Annual reason validation failed\n" ); exit( 1 ); }

require dirname( __DIR__ ) . '/includes/admin/class-sql-admin.php';
$decision = UFSC_SQL_Admin::save_club_document_decision( 56, 'doc_statuts', 'rejected', 'Document illisible', 99 );
$events = get_option( 'ufsc_club_doc_statuts_review_history_56', array() );
if ( true !== $decision || 'Document illisible' !== get_option( 'ufsc_club_doc_statuts_reason_56' ) || 1 !== count( $events ) ) { fwrite( STDERR, "Document decision failed\n" ); exit( 1 ); }
$missing_reason = UFSC_SQL_Admin::save_club_document_decision( 56, 'doc_statuts', 'correction_required', '', 99 );
if ( ! $missing_reason instanceof WP_Error ) { fwrite( STDERR, "Document reason validation failed\n" ); exit( 1 ); }
$GLOBALS['post_meta'][56]['doc_statuts'] = 10;
$method = new ReflectionMethod( 'UFSC_SQL_Admin', 'ufsc_docs_set_file' ); $method->setAccessible( true );
$method->invoke( null, 56, 'doc_statuts', 11 );
$history = get_option( 'ufsc_club_doc_statuts_history_56', array() );
if ( 1 !== count( $history ) || 10 !== $history[0]['attachment_id'] || 11 !== (int) get_post_meta( 56, 'doc_statuts', true ) ) { fwrite( STDERR, "Document replacement history failed\n" ); exit( 1 ); }
echo "Annual affiliation and document runtime safeguards passed.\n";
