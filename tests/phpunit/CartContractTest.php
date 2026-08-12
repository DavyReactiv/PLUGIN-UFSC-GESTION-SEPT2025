<?php
use PHPUnit\Framework\TestCase;

final class CartContractTest extends TestCase {
    private string $root;

    protected function setUp(): void {
        $this->root = dirname( __DIR__, 2 );
    }

    public function test_real_forms_send_semantic_intent_without_client_product_authority(): void {
        $front = file_get_contents( $this->root . '/includes/frontend/class-frontend-shortcodes.php' );
        $admin = file_get_contents( $this->root . '/includes/admin/class-sql-admin.php' );
        self::assertStringContainsString( 'name="ufsc_submit_action" value="add_to_cart"', $front );
        self::assertStringContainsString( 'name="ufsc_renew_intent" value="add_to_cart"', $front );
        self::assertStringNotContainsString( 'name="product_id"', $front );
        self::assertStringNotContainsString( 'name="product_id"', $admin );
    }

    public function test_one_registered_handler_uses_native_session_persistence(): void {
        $cart = file_get_contents( $this->root . '/inc/woocommerce/cart-integration.php' );
        self::assertSame( 1, substr_count( $cart, "add_action( 'admin_post_ufsc_add_to_cart', 'ufsc_handle_add_to_cart_secure' )" ) );
        self::assertStringContainsString( 'ufsc_ensure_woocommerce_cart()', $cart );
        self::assertStringContainsString( 'set_customer_session_cookie', $cart );
        self::assertStringContainsString( 'save_data', $cart );
        self::assertStringContainsString( "hash( 'sha256', \$club_id . '|' . \$licence_id", $cart );
    }

    public function test_javascript_does_not_disable_the_successful_submitter(): void {
        $js = file_get_contents( $this->root . '/assets/js/frontend-dashboard.js' );
        self::assertStringNotContainsString( "renewalForm.data('ufsc-cart-submitting', true); \$(submitter).prop('disabled', true)", $js );
        self::assertStringContainsString( "submitter.value !== 'add_to_cart'", $js );
    }
}
