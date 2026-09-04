<?php
$root = dirname( __DIR__ );
$file = $root . '/includes/admin/class-ffst-compliance-admin.php';
$bootstrap = $root . '/includes/admin/class-user-profile-scope-field.php';
$assert = static function( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
};
$assert( file_exists( $file ), 'FFST compliance admin class must exist' );
$code = file_get_contents( $file );
$assert( false !== strpos( $code, 'CAP_GESTION_MANAGE' ), 'admin-only capability required' );
$assert( false !== strpos( $code, 'ufsc_ffst_compliance_' ), 'club/season tracking option required' );
$assert( false !== strpos( $code, 'wp_handle_upload' ), 'signed document upload must use WordPress upload API' );
$assert( false !== strpos( $code, 'check_admin_referer' ), 'admin actions must be nonce protected' );
$assert( false !== strpos( file_get_contents( $bootstrap ), 'class-ffst-compliance-admin.php' ), 'compliance class must be loaded' );
echo "OK\n";
