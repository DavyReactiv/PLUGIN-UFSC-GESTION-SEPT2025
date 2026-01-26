<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests the affiliation flow redirects to cart and adds product.
 */
class UFSC_Affiliation_Redirect_Test extends TestCase {

    public function setUp(): void {
        // Load required files
        require_once __DIR__ . '/../includes/common/functions.php';
        require_once __DIR__ . '/../includes/frontend/class-club-form-handler.php';

        if ( ! function_exists( 'ufsc_add_affiliation_to_cart' ) ) {
            function ufsc_add_affiliation_to_cart( $club_id ) {
                $GLOBALS['ufsc_cart'][] = $club_id;
            }
        }

        if ( ! function_exists( 'wc_get_cart_url' ) ) {
            function wc_get_cart_url() {
                return 'https://example.com/cart';
            }
        }

        if ( ! function_exists( 'wp_safe_redirect' ) ) {
            function wp_safe_redirect( $url ) {
                $GLOBALS['redirect_url'] = $url;
                throw new Exception( 'redirect' );
            }
        }

        if ( ! function_exists( 'apply_filters' ) ) {
            function apply_filters( $tag, $value ) {
                return $value;
            }
        }

        if ( ! function_exists( 'add_query_arg' ) ) {
            function add_query_arg( $key, $value, $url ) {
                return $url . '?' . urlencode( $key ) . '=' . urlencode( $value );
            }
        }

        if ( ! function_exists( 'sanitize_key' ) ) {
            function sanitize_key( $key ) {
                return preg_replace( '/[^a-z0-9_]/', '', strtolower( $key ) );
            }
        }
    }

    public function test_redirects_to_cart_with_product() {
        $GLOBALS['ufsc_cart']    = array();
        $GLOBALS['redirect_url'] = '';

        $reflection = new ReflectionClass( 'UFSC_CL_Club_Form_Handler' );
        $method = $reflection->getMethod( 'handle_affiliation_redirect' );
        $method->setAccessible( true );

        try {
            $method->invoke( null, 42, true );
        } catch ( Exception $e ) {
            // Expected redirect.
        }

        $this->assertSame( array( 42 ), $GLOBALS['ufsc_cart'] );
        $this->assertSame( 'https://example.com/cart?ufsc_notice=affiliation_added', $GLOBALS['redirect_url'] );
    }
}
