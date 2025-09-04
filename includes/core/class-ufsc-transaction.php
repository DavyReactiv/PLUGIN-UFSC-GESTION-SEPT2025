<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Transaction Management Class
 * Provides database transaction and locking capabilities with retry logic
 */
class UFSC_Transaction {

    /**
     * Maximum retry attempts for deadlock scenarios
     */
    const MAX_RETRIES = 3;

    /**
     * Lock timeout in seconds
     */
    const LOCK_TIMEOUT = 10;

    /**
     * Execute a callback within a database transaction with locking
     * 
     * @param string $lock_key Unique lock key (club_id or other identifier)
     * @param callable $callback Function to execute within transaction
     * @return mixed Result of callback or false on failure
     */
    public static function with_lock( $lock_key, $callback ) {
        global $wpdb;

        if ( ! is_callable( $callback ) ) {
            UFSC_Audit_Logger::log( 'UFSC_Transaction: Invalid callback provided' );
            return false;
        }

        $lock_name = 'ufsc_lock_' . md5( (string) $lock_key );
        $retry_count = 0;

        while ( $retry_count <= self::MAX_RETRIES ) {
            try {
                // Acquire lock
                if ( ! self::acquire_lock( $lock_name ) ) {
                    throw new Exception( 'Failed to acquire lock: ' . $lock_name );
                }

                // Start transaction
                $wpdb->query( 'START TRANSACTION' );

                // Execute callback
                $result = call_user_func( $callback );

                // Commit transaction
                $wpdb->query( 'COMMIT' );

                // Release lock
                self::release_lock( $lock_name );

                return $result;

            } catch ( Exception $e ) {
                // Rollback transaction
                $wpdb->query( 'ROLLBACK' );

                // Release lock
                self::release_lock( $lock_name );

                // Check if this is a retryable error
                if ( self::is_retryable_error( $e, $wpdb ) && $retry_count < self::MAX_RETRIES ) {
                    $retry_count++;
                    UFSC_Audit_Logger::log( sprintf(
                        'UFSC_Transaction: Retryable error (attempt %d/%d): %s',
                        $retry_count,
                        self::MAX_RETRIES,
                        $e->getMessage()
                    ) );

                    // Wait before retry (exponential backoff)
                    usleep( pow( 2, $retry_count ) * 100000 ); // 0.2s, 0.4s, 0.8s
                    continue;
                } else {
                    UFSC_Audit_Logger::log( 'UFSC_Transaction: Non-retryable error or max retries exceeded: ' . $e->getMessage() );
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Acquire MySQL GET_LOCK
     * 
     * @param string $lock_name Lock name
     * @return bool True if lock acquired
     */
    private static function acquire_lock( $lock_name ) {
        global $wpdb;

        $result = $wpdb->get_var( $wpdb->prepare( 
            "SELECT GET_LOCK( %s, %d )", 
            $lock_name, 
            self::LOCK_TIMEOUT 
        ) );

        return $result == 1;
    }

    /**
     * Release MySQL lock
     * 
     * @param string $lock_name Lock name
     * @return bool True if lock released
     */
    private static function release_lock( $lock_name ) {
        global $wpdb;

        $result = $wpdb->get_var( $wpdb->prepare( 
            "SELECT RELEASE_LOCK( %s )", 
            $lock_name 
        ) );

        return $result == 1;
    }

    /**
     * Check if error is retryable (deadlock, lock timeout, etc.)
     * 
     * @param Exception $exception Exception to check
     * @param wpdb $wpdb WordPress database object
     * @return bool True if error is retryable
     */
    private static function is_retryable_error( $exception, $wpdb ) {
        $message = $exception->getMessage();
        $last_error = $wpdb->last_error;

        // MySQL error codes for retryable conditions
        $retryable_patterns = array(
            '/1213/',  // Deadlock found when trying to get lock
            '/1205/',  // Lock wait timeout exceeded
            '/40001/', // SQLSTATE 40001 (serialization failure)
            '/deadlock/i',
            '/lock.*timeout/i',
            '/try restarting transaction/i'
        );

        foreach ( $retryable_patterns as $pattern ) {
            if ( preg_match( $pattern, $message ) || preg_match( $pattern, $last_error ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Simple transaction wrapper without locking
     * 
     * @param callable $callback Function to execute within transaction
     * @return mixed Result of callback or false on failure
     */
    public static function transaction( $callback ) {
        global $wpdb;

        if ( ! is_callable( $callback ) ) {
            UFSC_Audit_Logger::log( 'UFSC_Transaction: Invalid callback provided' );
            return false;
        }

        try {
            $wpdb->query( 'START TRANSACTION' );
            $result = call_user_func( $callback );
            $wpdb->query( 'COMMIT' );
            return $result;
        } catch ( Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            UFSC_Audit_Logger::log( 'UFSC_Transaction: Transaction failed: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Check if a lock is currently held
     * 
     * @param string $lock_key Lock key to check
     * @return bool True if lock is held
     */
    public static function is_locked( $lock_key ) {
        global $wpdb;

        $lock_name = 'ufsc_lock_' . md5( (string) $lock_key );
        
        $result = $wpdb->get_var( $wpdb->prepare( 
            "SELECT IS_USED_LOCK( %s )", 
            $lock_name 
        ) );

        return ! is_null( $result );
    }

    /**
     * Force release all locks (emergency function)
     * 
     * @return bool True on success
     */
    public static function release_all_locks() {
        global $wpdb;

        try {
            $wpdb->query( "SELECT RELEASE_ALL_LOCKS()" );
            return true;
        } catch ( Exception $e ) {
            UFSC_Audit_Logger::log( 'UFSC_Transaction: Failed to release all locks: ' . $e->getMessage() );
            return false;
        }
    }
}