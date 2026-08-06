<?php
$root = dirname( __DIR__ );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$cart  = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$js    = file_get_contents( $root . '/assets/js/frontend-dashboard.js' );
$admin = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );

$checks = array(
	'fallback target is verified' => false !== strpos( $front, '$requested_target === $target' ) && false !== strpos( $front, '$requested_context[\'renewal_allowed\']' ),
	'renewal JS has an initialization guard' => false !== strpos( $js, 'ufsc-renewal-initialized' ),
	'weight is checked client-side' => false !== strpos( $js, 'weight < 20 || weight > 300' ),
	'cart fails closed without WooCommerce' => false !== strpos( $cart, "! function_exists( 'WC' )" ) && false !== strpos( $cart, '! WC()->cart' ),
	'forged blocked statuses are refused' => false !== strpos( $cart, "'suspended', 'suspendu', 'rejected', 'refused', 'refuse'" ),
	'canonical eligibility is checked at cart boundary' => false !== strpos( $cart, "array( 'UFSC_Renewal_Service', 'can_renew' )" ),
	'admin defaults to 25 and caps at 50' => false !== strpos( $admin, ': 25;' ) && false !== strpos( $admin, 'min( 50, max( 1, $requested_per_page ) )' ),
);

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); }
	echo "PASS: {$label}\n";
}
echo "Definitive renewal hardening safeguards passed.\n";
