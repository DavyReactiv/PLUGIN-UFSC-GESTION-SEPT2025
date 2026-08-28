<?php
/** Static regression checks for the honorability admin review queue. */
$root = dirname( __DIR__ );
$admin = file_get_contents( $root . '/inc/common/honorability-admin.php' );
$functions = file_get_contents( $root . '/inc/common/functions.php' );
$handlers = file_get_contents( $root . '/includes/core/class-unified-handlers.php' );
$failures = array();
$assert = static function( $condition, $message ) use ( &$failures ) { if ( ! $condition ) { $failures[] = $message; } };

$assert( false !== strpos( $functions, "require_once UFSC_CL_DIR . 'inc/common/honorability-admin.php'" ), 'Admin module is not loaded.' );
$assert( false !== strpos( $admin, "add_submenu_page(" ) && false !== strpos( $admin, "'ufsc-honorability'" ), 'Honorability admin submenu missing.' );
$assert( false !== strpos( $admin, "'pending' => 'À vérifier'" ), 'Pending review queue must be the default admin state.' );
$assert( false !== strpos( $admin, 'Voir le document' ), 'Admin document preview link missing.' );
$assert( false !== strpos( $admin, 'value="validated"' ), 'Validate action missing.' );
$assert( false !== strpos( $admin, 'value="correction_required"' ), 'Correction action missing.' );
$assert( false !== strpos( $admin, 'value="rejected"' ), 'Reject action missing.' );
$assert( false !== strpos( $admin, 'data-require-reason="1"' ), 'Correction/rejection reason guard missing.' );
$assert( false !== strpos( $admin, "wp_nonce_field( 'ufsc_decide_honorability_'" ), 'Decision nonce missing.' );
$assert( false !== strpos( $admin, 'ufsc_decide_honorability_attestation' ), 'Canonical decision endpoint must be reused.' );
$assert( false !== strpos( $handlers, 'handle_decide_honorability_attestation' ), 'Canonical decision handler missing.' );
$assert( false === strpos( $admin, 'update_option(' ), 'Admin presentation must not write a parallel honorability model.' );

if ( $failures ) { fwrite( STDERR, "Honorability admin static tests failed:\n- " . implode( "\n- ", $failures ) . "\n" ); exit( 1 ); }
echo "Honorability admin static tests: OK\n";
