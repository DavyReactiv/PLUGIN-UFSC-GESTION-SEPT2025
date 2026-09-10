<?php
/** Runtime reproduction for the included-quota handoff. */
define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['ufsc_test_actions'] = array();
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['ufsc_test_actions'][] = array( $hook, $callback, $priority, $accepted_args ); }
function add_filter() {}
function sanitize_text_field( $value ) { return is_scalar( $value ) ? (string) $value : ''; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function ufsc_get_licences_table() { return 'wp_ufsc_licences'; }
function ufsc_table_columns() { return array( 'id', 'club_id', 'is_included', 'infos_fsasptt', 'infos_asptt', 'infos_cr' ); }
function is_user_logged_in() { return true; }
function get_current_user_id() { return 7; }
function ufsc_get_user_club_id() { return 12; }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function wp_validate_redirect( $url, $fallback ) { return $url ?: $fallback; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function esc_url( $value ) { return $value; }
function esc_html( $value ) { return $value; }
function __( $value ) { return $value; }
function wp_nonce_field() { return '<input type="hidden" name="_wpnonce" value="nonce">'; }

class UFSC_Test_WPDB {
    public $reserved = 1;
    public function prepare( $sql ) { return $sql; }
    public function get_var() { return $this->reserved; }
    public function get_row() { return null; }
}
$GLOBALS['wpdb'] = new UFSC_Test_WPDB();

require_once dirname( __DIR__ ) . '/inc/common/licence-portal-production-hotfix.php';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'action' => 'ufsc_save_licence',
    'licence_id' => 1380,
    'ufsc_submit_action' => 'add_to_cart',
);
ufsc_portal_hotfix_capture_final_request();
$_POST['ufsc_submit_action'] = 'continue';
ufsc_portal_hotfix_restore_included_intent( 1380, 12 );
if ( 'add_to_cart' !== $_POST['ufsc_submit_action'] ) {
    fwrite( STDERR, "FAIL: confirmed included reservation did not restore final intent.\n" );
    exit( 1 );
}

$GLOBALS['wpdb']->reserved = 0;
$_POST['ufsc_submit_action'] = 'add_to_cart';
ufsc_portal_hotfix_capture_final_request();
$_POST['ufsc_submit_action'] = 'continue';
ufsc_portal_hotfix_restore_included_intent( 1380, 12 );
if ( 'continue' !== $_POST['ufsc_submit_action'] ) {
    fwrite( STDERR, "FAIL: unreserved licence must stay fail-closed.\n" );
    exit( 1 );
}

echo "Included quota handoff runtime safeguards: OK\n";
