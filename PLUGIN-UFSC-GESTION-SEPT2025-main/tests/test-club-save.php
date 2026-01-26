<?php
define('ABSPATH', __DIR__);
// Minimal stubs for WordPress functions used in handle_save_club
function wp_verify_nonce( $nonce, $action ) { return $nonce === 'valid'; }
function wp_die( $message ) { throw new Exception( $message ); }
function is_user_logged_in() { return true; }
function get_current_user_id() { return 1; }
function ufsc_get_user_club_id( $user_id ) { return 1; }
function current_user_can( $capability ) { return true; }
function set_transient( $key, $value, $expiration ) {}
function wp_safe_redirect( $url ) { $GLOBALS['redirect_url'] = $url; throw new Exception('redirect'); }
function wp_get_referer() { return 'http://example.com/form'; }
function wp_redirect( $url ) { $GLOBALS['redirect_url'] = $url; throw new Exception('redirect'); }
function add_query_arg( ...$args ) {
    if ( count( $args ) === 3 ) {
        list( $key, $value, $url ) = $args;
        return $url . '?' . urlencode( $key ) . '=' . urlencode( $value );
    }
    if ( count( $args ) === 2 ) {
        list( $arr, $url ) = $args;
        return $url . '?' . http_build_query( $arr );
    }
    return '';
}
function sanitize_text_field( $str ) { return $str; }
function sanitize_textarea_field( $str ) { return $str; }
function sanitize_email( $str ) { return $str; }
function is_email( $email ) { return strpos( $email, '@' ) !== false; }
function __($text, $domain = 'default') { return $text; }
function wp_handle_upload( $file, $args ) { return array( 'url' => 'http://example.com/file.pdf' ); }
function esc_url_raw( $url ) { return $url; }
function delete_transient( $key ) {}
class WP_Error {
    private $message;
    public function __construct( $code = '', $message = '', $data = '' ) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
class WPDB_Stub { public function update( $table, $data, $where ) { $GLOBALS['db_update'] = compact('table','data','where'); return 1; } }
$wpdb = new WPDB_Stub();
class UFSC_SQL { public static function get_settings() { return array( 'table_clubs' => 'wp_clubs' ); } }

require_once __DIR__ . '/../includes/core/class-uploads.php';

// Simulate POST and FILES data for a valid club form
$_POST = array(
    'ufsc_club_nonce' => 'valid',
    'club_id' => 1,
    'nom' => 'Club Test',
    'email' => 'club@example.com'
);
$_FILES = array();

require_once __DIR__ . '/../includes/core/class-unified-handlers.php';

try {
    UFSC_Unified_Handlers::handle_save_club();
} catch ( Exception $e ) {
    // Ignore redirect exception
}

if ( isset( $GLOBALS['db_update'] ) ) {
    echo "Club saved successfully\n";
} else {
    echo "Club save failed\n";
}
?>
