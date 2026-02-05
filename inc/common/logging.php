<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC PATCH: Admin-only debug logger without sensitive data.
 *
 * @param string $message Log message.
 * @param array  $context Context data (non-sensitive).
 * @return void
 */
function ufsc_admin_debug_log( $message, $context = array() ) {
    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $payload = array(
        'message' => sanitize_text_field( (string) $message ),
        'context' => is_array( $context ) ? $context : array(),
    );

    error_log( '[UFSC] ' . wp_json_encode( $payload ) );
}
