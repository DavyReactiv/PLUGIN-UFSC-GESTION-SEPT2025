<?php
/** Execute isolated runtime/static scripts without loading PHPUnit fixtures directly. */
$files = glob( __DIR__ . '/test-*.php' );
sort( $files );
$passed = 0;
$failed = array();
foreach ( $files as $file ) {
    $source = file_get_contents( $file );
    if ( false !== strpos( $source, 'PHPUnit\\Framework\\TestCase' ) ) { continue; }
    $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $file );
    passthru( $command, $exit_code );
    if ( 0 === $exit_code ) { $passed++; } else { $failed[] = basename( $file ); }
}
if ( $failed ) {
    fwrite( STDERR, sprintf( "Standalone failures (%d): %s\n", count( $failed ), implode( ', ', $failed ) ) );
    exit( 1 );
}
echo sprintf( "Standalone suite: %d scripts passed.\n", $passed );
