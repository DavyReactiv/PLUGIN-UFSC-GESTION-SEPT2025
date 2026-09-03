<?php
/** Static safeguards for request-local user/club mapping cache. */
$root = dirname( __DIR__ );
$file = file_get_contents( $root . '/includes/core/class-user-club-mapping.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( strpos( $file, 'private static $resolution_cache' ) !== false, 'Mapping resolution must use a request-local cache.' );
$assert( strpos( $file, 'private static $club_cache' ) !== false, 'Club object lookup must use a request-local cache.' );
$assert( strpos( $file, 'array_key_exists( $user_id, self::$resolution_cache )' ) !== false, 'Repeated mapping lookups must short-circuit.' );
$assert( strpos( $file, 'array_key_exists( $user_id, self::$club_cache )' ) !== false, 'Repeated club object lookups must short-circuit.' );
$assert( substr_count( $file, 'self::clear_user_cache( $user_id )' ) >= 2, 'Association writes must invalidate request-local caches.' );
$assert( strpos( $file, "'diagnostic_code' => 'invalid_user'" ) !== false, 'Invalid user IDs fail closed.' );

echo "User/club request cache safeguards OK\n";
