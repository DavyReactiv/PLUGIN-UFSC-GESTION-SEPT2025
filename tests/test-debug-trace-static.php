<?php
$root = dirname( __DIR__ );
$trace = file_get_contents( $root . '/includes/core/class-ufsc-debug-trace.php' );
$logging = file_get_contents( $root . '/inc/common/logging.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $trace, "defined( 'UFSC_DEBUG_TRACE' )" ), 'trace is opt-in and disabled by default' );
$assert( false !== strpos( $trace, "'trace_id'" ), 'trace id is attached to every diagnostic event' );
$assert( false !== strpos( $trace, "'source' => 'ufsc-trace'" ), 'WooCommerce logger uses dedicated UFSC trace source' );
$assert( false !== strpos( $trace, "do_action( 'qm/debug'" ), 'Query Monitor receives trace payload when available' );
$assert( false !== strpos( $trace, "'[redacted]'" ), 'sensitive context is redacted' );
$assert( false !== strpos( $logging, "class-ufsc-debug-trace.php" ), 'trace utility is loaded through existing logging bootstrap' );

echo "OK: safe diagnostic tracing contract\n";
