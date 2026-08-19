<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( defined( 'UFSC_CL_DIR' ) ) {
    require_once UFSC_CL_DIR . 'includes/core/class-ufsc-debug-trace.php';
}

/**
 * Admin-only compatibility logger.
 *
 * @param string $message Log message.
 * @param array  $context Context data (non-sensitive).
 * @return void
 */
function ufsc_admin_debug_log( $message, $context = array() ) {
    if ( class_exists( 'UFSC_Debug_Trace' ) && UFSC_Debug_Trace::enabled() ) {
        UFSC_Debug_Trace::record(
            'admin_debug',
            array(
                'message' => sanitize_text_field( (string) $message ),
                'context' => is_array( $context ) ? $context : array(),
            )
        );
        return;
    }

    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $payload = array(
        'message' => sanitize_text_field( (string) $message ),
        'context' => is_array( $context ) ? $context : array(),
    );

    error_log( '[UFSC] ' . wp_json_encode( $payload ) );
}
