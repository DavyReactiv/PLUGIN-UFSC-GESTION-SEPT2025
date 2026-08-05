<?php
/**
 * Runtime guard: include the frontend shortcode class so PHP parse errors fail fast.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once __DIR__ . '/../includes/frontend/class-frontend-shortcodes.php';

if ( ! class_exists( 'UFSC_Frontend_Shortcodes', false ) ) {
    fwrite( STDERR, "UFSC_Frontend_Shortcodes class was not loaded.\n" );
    exit( 1 );
}

echo "Frontend shortcodes load OK\n";
