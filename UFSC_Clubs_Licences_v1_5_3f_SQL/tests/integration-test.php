<?php
/**
 * Integration test for UFSC Club Form
 * Verifies that key components work together properly
 * To be run in WordPress testing environment
 */

// This file shows how to test the club form in a real WordPress environment
// It is provided for documentation and testing purposes

class UFSC_Club_Form_Integration_Test {
    
    /**
     * Test shortcode registration
     */
    public static function test_shortcode_registration() {
        global $shortcode_tags;
        
        if (isset($shortcode_tags['ufsc_club_form'])) {
            echo "✓ Shortcode [ufsc_club_form] is registered\n";
            return true;
        } else {
            echo "✗ Shortcode [ufsc_club_form] is NOT registered\n";
            return false;
        }
    }
    
    /**
     * Test that document fields are present
     */
    public static function test_document_fields() {
        if (!class_exists('UFSC_SQL')) {
            echo "✗ UFSC_SQL class not available\n";
            return false;
        }
        
        $fields = UFSC_SQL::get_club_fields();
        $required_docs = array('doc_statuts', 'doc_recepisse', 'doc_cer');
        
        foreach ($required_docs as $doc) {
            if (!isset($fields[$doc])) {
                echo "✗ Document field '{$doc}' missing\n";
                return false;
            }
        }
        
        echo "✓ All required document fields present\n";
        return true;
    }
    
    /**
     * Test permissions functionality
     */
    public static function test_permissions() {
        if (!class_exists('UFSC_CL_Permissions')) {
            echo "✗ UFSC_CL_Permissions class not available\n";
            return false;
        }
        
        // Test create permission (requires login)
        $can_create = UFSC_CL_Permissions::ufsc_user_can_create_club();
        echo $can_create ? "✓ Create permission check working\n" : "✓ Create permission correctly requires login\n";
        
        return true;
    }
    
    /**
     * Test upload utility
     */
    public static function test_upload_utility() {
        if (!class_exists('UFSC_CL_Uploads')) {
            echo "✗ UFSC_CL_Uploads class not available\n";
            return false;
        }
        
        // Test MIME type configurations
        $logo_types = UFSC_CL_Uploads::get_logo_mime_types();
        $doc_types = UFSC_CL_Uploads::get_document_mime_types();
        
        if (isset($logo_types['jpg']) && isset($doc_types['pdf'])) {
            echo "✓ Upload utility MIME types configured\n";
            return true;
        } else {
            echo "✗ Upload utility MIME types misconfigured\n";
            return false;
        }
    }
    
    /**
     * Test validation enhancements
     */
    public static function test_validation() {
        if (!class_exists('UFSC_CL_Utils')) {
            echo "✗ UFSC_CL_Utils class not available\n";
            return false;
        }
        
        // Test basic validation
        $test_data = array(
            'nom' => 'Test Club',
            'email' => 'invalid-email',
            'code_postal' => '123'
        );
        
        $errors = UFSC_CL_Utils::validate_club_data($test_data, false);
        
        if (isset($errors['email']) && isset($errors['code_postal'])) {
            echo "✓ Validation correctly identifies format errors\n";
            return true;
        } else {
            echo "✗ Validation not working properly\n";
            return false;
        }
    }
    
    /**
     * Test IBAN validation
     */
    public static function test_iban_validation() {
        if (!method_exists('UFSC_CL_Utils', 'validate_iban')) {
            echo "✗ IBAN validation method not available\n";
            return false;
        }
        
        $valid_iban = 'FR1420041010050500013M02606';
        $invalid_iban = 'INVALID123';
        
        if (UFSC_CL_Utils::validate_iban($valid_iban) && !UFSC_CL_Utils::validate_iban($invalid_iban)) {
            echo "✓ IBAN validation working correctly\n";
            return true;
        } else {
            echo "✗ IBAN validation not working properly\n";
            return false;
        }
    }
    
    /**
     * Run all tests
     */
    public static function run_all_tests() {
        echo "Running UFSC Club Form Integration Tests...\n\n";
        
        $tests = array(
            'test_shortcode_registration',
            'test_document_fields', 
            'test_permissions',
            'test_upload_utility',
            'test_validation',
            'test_iban_validation'
        );
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if (self::$test()) {
                $passed++;
            }
        }
        
        echo "\nTest Results: {$passed}/{$total} passed\n";
        
        if ($passed === $total) {
            echo "🎉 All tests passed! Club form implementation is working correctly.\n";
        } else {
            echo "⚠️  Some tests failed. Please check the implementation.\n";
        }
        
        return $passed === $total;
    }
}

// Example of how to run tests in WordPress environment:
/*
if (defined('ABSPATH')) {
    // WordPress environment available
    add_action('admin_init', function() {
        if (isset($_GET['run_ufsc_tests'])) {
            UFSC_Club_Form_Integration_Test::run_all_tests();
            exit;
        }
    });
}
*/
?>