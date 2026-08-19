<?php
$tests = array(
    __DIR__ . '/pack-boundary-repro.php',
    __DIR__ . '/pack-null-reservation-repro.php',
    __DIR__ . '/status-dual-column-repro.php',
    __DIR__ . '/paid-renewal-cart-repro.php',
);

$failures = array();
foreach ( $tests as $test ) {
    $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $test );
    passthru( $command, $exit_code );
    if ( 0 !== $exit_code ) {
        $failures[] = array( 'file' => basename( $test ), 'exit' => $exit_code );
    }
}

if ( $failures ) {
    fwrite( STDERR, "P0 reproductions not all green:\n" );
    foreach ( $failures as $failure ) {
        fwrite( STDERR, sprintf( "- %s (exit %d)\n", $failure['file'], $failure['exit'] ) );
    }
    exit( 1 );
}

echo "P0 reproduction suite passed.\n";
