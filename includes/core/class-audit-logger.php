<?php
class UFSC_Audit_Logger {
    public static function log($message, array $context = []) {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info(is_string($message) ? $message : wp_json_encode($message), ['source' => 'ufsc'] + $context);
        } else {
            error_log('[UFSC] ' . (is_string($message) ? $message : wp_json_encode($message)));
        }
    }
}
