<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' ); }

class WP_Error {
    private $code;
    private $message;
    public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text ) { return $text; }
function apply_filters( $hook, $value ) { return $value; }
function remove_accents( $value ) { return $value; }
function sanitize_title( $value ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( (string) $value ) ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function ufsc_get_licences_table() { return 'wp_ufsc_licences'; }

final class Repro_WPDB {
    public $row_is_included = null;

    public function get_col( $sql, $column = 0 ) {
        if ( false !== strpos( $sql, 'DESC' ) ) {
            return array( 'id', 'club_id', 'season', 'role', 'is_included' );
        }
        if ( false !== strpos( $sql, 'SELECT role' ) ) {
            return array();
        }
        return array();
    }

    public function get_var( $sql ) {
        if ( false !== strpos( $sql, 'GET_LOCK' ) || false !== strpos( $sql, 'RELEASE_LOCK' ) ) {
            return 1;
        }
        if ( false !== strpos( $sql, 'SELECT is_included' ) ) {
            return $this->row_is_included;
        }
        return null;
    }

    public function prepare( $query, ...$args ) {
        foreach ( $args as $arg ) {
            $replacement = is_int( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'";
            $query = preg_replace( '/%[ds]/', $replacement, $query, 1 );
        }
        return $query;
    }

    public function query( $sql ) {
        if ( false !== strpos( $sql, 'UPDATE `wp_ufsc_licences` SET is_included = 1' ) ) {
            if ( null === $this->row_is_included && false === strpos( $sql, 'is_included IS NULL' ) ) {
                return 0;
            }
            $this->row_is_included = 1;
            return 1;
        }
        return 0;
    }
}

$wpdb = new Repro_WPDB();
require_once dirname( __DIR__, 2 ) . '/inc/common/compliance.php';

$result = ufsc_allocate_pack_credit( 501, 7, '2026-2027', 'adherent' );
if ( is_wp_error( $result ) ) {
    fwrite( STDERR, 'FAIL: unexpected WP_Error: ' . $result->get_error_message() . "\n" );
    exit( 1 );
}
if ( empty( $result['included'] ) ) {
    fwrite( STDERR, "FAIL: the legacy NULL row should be eligible for an included credit\n" );
    exit( 1 );
}
if ( empty( $result['reserved'] ) || 1 !== $wpdb->row_is_included ) {
    fwrite( STDERR, "FAIL: eligible legacy NULL row was not confirmed as reserved\n" );
    exit( 1 );
}

echo "OK: legacy NULL row reserved and confirmed as included\n";
