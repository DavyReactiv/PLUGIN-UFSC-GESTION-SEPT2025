<?php
// Debug configuration
// Default to production environment unless WP_ENVIRONMENT_TYPE is set.
if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
    define( 'WP_ENVIRONMENT_TYPE', getenv( 'WP_ENVIRONMENT_TYPE' ) ?: 'production' );
}

// Enable debugging only on test environment.
if ( WP_ENVIRONMENT_TYPE === 'test' ) {
    define( 'WP_DEBUG', true );
} else {
    define( 'WP_DEBUG', false );
}

// Never display debug messages on screen.
define( 'WP_DEBUG_DISPLAY', false );

