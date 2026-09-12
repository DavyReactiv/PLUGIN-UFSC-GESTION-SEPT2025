<?php
$root  = dirname( __DIR__ );
$admin = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$utils = file_get_contents( $root . '/includes/core/class-utils.php' );

$failures = 0;
$assert = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failures++;
    }
};

$assert( false !== strpos( $utils, '$allow_incomplete = false' ), 'club validation exposes one progressive-save flag' );
$assert( false !== strpos( $utils, '! $allow_incomplete && empty( $data[$field] )' ), 'club required values remain strict by default' );
$assert( false !== strpos( $utils, '! $allow_incomplete && empty( $data[$key] )' ), 'officer required values remain strict by default' );
$assert( false !== strpos( $admin, 'validate_club_data( $data, false, $id > 0 )' ), 'only existing admin club records use progressive save' );
$assert( false !== strpos( $admin, "array( '0000-00-00', '0000-00-00 00:00:00' )" ), 'legacy zero dates are hidden by the existing renderer' );
$assert( false !== strpos( $admin, '<input type="date"' ), 'admin date fields use native date inputs' );
$assert( false !== strpos( $admin, 'unset( $data[ $key ] )' ), 'blank admin date inputs preserve stored values' );

exit( $failures > 0 ? 1 : 0 );
