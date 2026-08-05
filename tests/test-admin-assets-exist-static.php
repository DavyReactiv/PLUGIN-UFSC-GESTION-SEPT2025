<?php
$root = dirname( __DIR__ );
$assert = function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$admin = file_get_contents( $root . '/includes/admin/class-user-club-admin.php' );
$assert( strpos( $admin, "UFSC_CL_URL" ) !== false, 'User/club admin CSS uses the plugin root URL constant.' );
$assert( file_exists( $root . '/assets/admin/css/user-club-admin.css' ), 'user-club-admin.css exists at the enqueued plugin-root asset path.' );
$assert( strpos( $admin, "plugins_url('assets/admin/css/user-club-admin.css', __FILE__)" ) === false, 'Broken includes/admin-relative asset URL is not used.' );
echo "Admin asset existence safeguards OK\n";
