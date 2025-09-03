<?php
use PHPUnit\Framework\TestCase;

// Include plugin files
require_once __DIR__ . '/../includes/core/class-import-export.php';
require_once __DIR__ . '/../includes/api/class-rest-api.php';
require_once __DIR__ . '/../inc/woocommerce/settings-woocommerce.php';


// --- WordPress and utility stubs ---
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = [];
        public function __construct($code = '', $message = '', $data = '') {
            if ($code) {
                $this->errors[$code][] = $message;
            }
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {}
}

if (!function_exists('__')) { function __($t, $d = 'default') { return $t; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s){ return $s; } }
if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($s){ return $s; } }
if (!function_exists('sanitize_email')) { function sanitize_email($s){ return $s; } }
if (!function_exists('is_email')) { function is_email($e){ return strpos($e,'@') !== false; } }
if (!function_exists('current_time')) { function current_time($t){ return date('Y-m-d H:i:s'); } }
if (!function_exists('ufsc_audit_log')) { function ufsc_audit_log(...$args) {} }
if (!function_exists('wp_upload_dir')) { function wp_upload_dir(){ return ['path'=>sys_get_temp_dir(),'url'=>'http://example.com']; } }
if (!function_exists('sanitize_file_name')) { function sanitize_file_name($n){ return $n; } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in(){ return $GLOBALS['ufsc_is_logged_in'] ?? false; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return $GLOBALS['ufsc_current_user_id'] ?? 0; } }
if (!function_exists('ufsc_get_user_club_id')) { function ufsc_get_user_club_id($u){ return $GLOBALS['ufsc_user_club_id'] ?? null; } }
if (!function_exists('wc_get_product')) {
    function wc_get_product($id) {
        $exists = $GLOBALS['ufsc_product_exists'] ?? true;
        return new class($exists) {
            private $exists;
            public function __construct($exists){ $this->exists = $exists; }
            public function exists(){ return $this->exists; }
        };
    }
}

// --- Testable subclasses for Import/Export ---
class UFSC_Import_Export_Success extends UFSC_Import_Export {
    protected static function get_club_quota_info( $club_id ) {
        return ['total'=>10,'used'=>0,'remaining'=>10];
    }
    protected static function create_licence_record( $club_id, $data ) {
        return 1;
    }
    protected static function get_club_licences_for_export( $club_id, $filters ) {
        return [ ['id'=>1,'nom'=>'Doe','prenom'=>'John','email'=>'john@example.com'] ];
    }
    protected static function get_club_name( $club_id ) {
        return 'TestClub';
    }
    protected static function create_payment_order( $club_id, $licence_ids ) {
        return 0;
    }
}

class UFSC_Import_Export_Failure extends UFSC_Import_Export {
    protected static function get_club_quota_info( $club_id ) {
        return ['total'=>10,'used'=>0,'remaining'=>10];
    }
    protected static function create_licence_record( $club_id, $data ) {
        return 0; // simulate failure
    }
    protected static function get_club_licences_for_export( $club_id, $filters ) {
        return []; // nothing to export
    }
    protected static function get_club_name( $club_id ) {
        return 'TestClub';
    }
    protected static function create_payment_order( $club_id, $licence_ids ) {
        return 0;
    }
}

class ImportExportTest extends TestCase {
    public function test_import_csv_data_success() {
        $data = [ ['nom'=>'Doe','prenom'=>'John','email'=>'john@example.com','line_number'=>2,'status'=>'valid'] ];
        $result = UFSC_Import_Export_Success::import_csv_data($data, 1);
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']);
        $this->assertEmpty($result['errors']);
    }

    public function test_import_csv_data_failure() {
        $data = [ ['nom'=>'Doe','prenom'=>'John','email'=>'john@example.com','line_number'=>2,'status'=>'valid'] ];
        $result = UFSC_Import_Export_Failure::import_csv_data($data, 1);
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_export_csv_success_and_failure() {
        $success = UFSC_Import_Export_Success::export_licences_csv(1);
        $this->assertTrue($success['success']);
        $this->assertEquals(1, $success['record_count']);
        $this->assertFileExists($success['file_path']);
        unlink($success['file_path']);

        $failure = UFSC_Import_Export_Failure::export_licences_csv(1);
        $this->assertFalse($failure['success']);
    }
}

class ApiPermissionsTest extends TestCase {
    public function test_check_club_permissions_failure() {
        $GLOBALS['ufsc_is_logged_in'] = false;
        $result = UFSC_REST_API::check_club_permissions(new WP_REST_Request());
        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_check_club_permissions_success() {
        $GLOBALS['ufsc_is_logged_in'] = true;
        $GLOBALS['ufsc_current_user_id'] = 1;
        $GLOBALS['ufsc_user_club_id'] = 2;
        $result = UFSC_REST_API::check_club_permissions(new WP_REST_Request());
        $this->assertTrue($result);
    }

    public function test_check_club_permissions_no_club() {
        $GLOBALS['ufsc_is_logged_in'] = true;
        $GLOBALS['ufsc_current_user_id'] = 1;
        unset($GLOBALS['ufsc_user_club_id']);
        $result = UFSC_REST_API::check_club_permissions(new WP_REST_Request());
        $this->assertInstanceOf(WP_Error::class, $result);
    }
}

class WooCommerceFunctionsTest extends TestCase {
    public function test_woocommerce_active_and_validation() {
        // Failure: WooCommerce not active
        $this->assertFalse(ufsc_is_woocommerce_active());
        $GLOBALS['ufsc_product_exists'] = false;
        $this->assertFalse(ufsc_validate_woocommerce_product(123));

        // Success scenario with valid product
        eval('class WooCommerce {}');
        $GLOBALS['ufsc_product_exists'] = true;
        $this->assertTrue(ufsc_is_woocommerce_active());
        $this->assertTrue(ufsc_validate_woocommerce_product(123));
    }

    public function test_woocommerce_product_not_found() {
        if (!class_exists('WooCommerce')) {
            eval('class WooCommerce {}');
        }
        $GLOBALS['ufsc_product_exists'] = false;
        $this->assertTrue(ufsc_is_woocommerce_active());
        $this->assertFalse(ufsc_validate_woocommerce_product(999));
    }
}
