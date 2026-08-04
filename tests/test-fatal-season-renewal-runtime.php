<?php
/** Executable regression test for the PR #465 fatal renewal URL. */
$mode = isset( $argv[1] ) ? $argv[1] : 'runner';
if ( 'runner' === $mode ) {
    foreach ( array( 'available', 'disabled', 'missing' ) as $scenario ) {
        $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $scenario );
        exec( $command, $output, $status );
        if ( 0 !== $status ) {
            fwrite( STDERR, "FAIL: runtime scenario {$scenario}\n" . implode( "\n", $output ) . "\n" );
            exit( 1 );
        }
        $output = array();
    }
    echo "Fatal season renewal runtime safeguards OK\n";
    exit( 0 );
}

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['ufsc_options'] = array(
    'ufsc_current_season' => '2026-2027',
    'ufsc_woocommerce_settings' => array( 'product_affiliation_id' => 77 ),
);
function get_option( $key, $default = false ) { return $GLOBALS['ufsc_options'][ $key ] ?? $default; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $type ) { return 'Y-m-d' === $type ? '2026-08-04' : strtotime( '2026-08-04 12:00:00 UTC' ); }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ?: time() ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function get_permalink( $id ) { return 77 === (int) $id ? 'https://example.test/product/affiliation' : ''; }
if ( 'disabled' !== $mode ) {
    class WooCommerce {}
    class UFSC_Test_Product {
        public function exists() { return true; }
        public function get_status() { return 'publish'; }
        public function is_purchasable() { return true; }
		public function get_catalog_visibility() { return 'visible'; }
		public function get_type() { return 'simple'; }
		public function get_price() { return '50'; }
    }
    function wc_get_product( $id ) {
        return ( 'missing' === $GLOBALS['runtime_mode'] || 77 !== (int) $id ) ? false : new UFSC_Test_Product();
    }
}
$GLOBALS['runtime_mode'] = $mode;
require dirname( __DIR__ ) . '/includes/core/class-ufsc-season-service.php';
require dirname( __DIR__ ) . '/inc/woocommerce/settings-woocommerce.php';
$url = ufsc_get_affiliation_renewal_url( 56, '2030-2031' );
if ( 'available' === $mode ) {
    if ( false === strpos( $url, 'ufsc_target_season=2026-2027' ) || false === strpos( $url, 'ufsc_club_id=56' ) || false === strpos( $url, 'ufsc_action=renewal' ) ) {
        fwrite( STDERR, "FAIL: URL must execute with configured current season context: {$url}\n" );
        exit( 1 );
    }
} elseif ( '' !== $url ) {
    fwrite( STDERR, "FAIL: unavailable WooCommerce/product must return an empty URL\n" );
    exit( 1 );
}
$method = new ReflectionMethod( 'UFSC_Season_Service', 'shift_season' );
if ( ! $method->isPrivate() ) {
    fwrite( STDERR, "FAIL: shift_season must remain private\n" );
    exit( 1 );
}
