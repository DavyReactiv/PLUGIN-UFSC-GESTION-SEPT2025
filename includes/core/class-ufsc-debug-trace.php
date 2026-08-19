<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Opt-in diagnostic tracing for DEV/preproduction.
 *
 * Enable with: define( 'UFSC_DEBUG_TRACE', true );
 * The tracer redacts common personal/sensitive fields and never changes business state.
 */
final class UFSC_Debug_Trace {
    private static $trace_id = '';

    public static function enabled() {
        $enabled = defined( 'UFSC_DEBUG_TRACE' ) && UFSC_DEBUG_TRACE;
        if ( function_exists( 'apply_filters' ) ) {
            $enabled = (bool) apply_filters( 'ufsc_debug_trace_enabled', $enabled );
        }
        return (bool) $enabled;
    }

    public static function id() {
        if ( '' !== self::$trace_id ) {
            return self::$trace_id;
        }

        self::$trace_id = function_exists( 'wp_generate_uuid4' )
            ? substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 16 )
            : substr( md5( uniqid( 'ufsc', true ) ), 0, 16 );

        return self::$trace_id;
    }

    public static function record( $event, array $context = array() ) {
        if ( ! self::enabled() ) {
            return;
        }

        $payload = array(
            'trace_id' => self::id(),
            'event'    => sanitize_key( (string) $event ),
            'user_id'  => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
            'context'  => self::sanitize_context( $context ),
        );

        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            if ( is_callable( array( $logger, 'debug' ) ) ) {
                $logger->debug( wp_json_encode( $payload ), array( 'source' => 'ufsc-trace' ) );
            }
        } else {
            error_log( '[UFSC TRACE] ' . wp_json_encode( $payload ) );
        }

        if ( function_exists( 'do_action' ) ) {
            do_action( 'qm/debug', $payload );
        }
    }

    private static function sanitize_context( array $context ) {
        $safe = array();

        foreach ( $context as $key => $value ) {
            $key_string = strtolower( (string) $key );
            if ( preg_match( '/pass|password|secret|token|nonce|authorization|cookie|email|mail|phone|telephone|address|adresse|birth|naissance|medical|certificat|honorabil|document|file|content|note/i', $key_string ) ) {
                $safe[ $key ] = '[redacted]';
                continue;
            }

            if ( is_array( $value ) ) {
                $safe[ $key ] = self::sanitize_context( $value );
            } elseif ( is_object( $value ) ) {
                $safe[ $key ] = '[object ' . get_class( $value ) . ']';
            } elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
                $safe[ $key ] = $value;
            } else {
                $safe[ $key ] = substr( sanitize_text_field( (string) $value ), 0, 500 );
            }
        }

        return $safe;
    }
}
