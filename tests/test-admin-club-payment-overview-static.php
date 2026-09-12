<?php
$root  = dirname( __DIR__ );
$table = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$css   = file_get_contents( $root . '/assets/admin/css/ufsc-clubs-admin.css' );

$failures = 0;
$assert = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); $failures++; }
};

$assert( false !== strpos( $table, "esc_html__( 'Règlement', 'ufsc-clubs' )" ), 'clubs table exposes the payment column' );
$assert( false !== strpos( $table, 'get_club_payment_context( $clubs )' ), 'payment context is loaded once for the current page' );
$assert( false !== strpos( $table, "wc_get_orders( array( 'include' => $order_ids" ), 'orders are batch loaded with the HPOS-compatible API' );
$assert( false !== strpos( $table, "array( 'monetico', 'stripe', 'mypos', 'woocommerce_payments', 'card', 'cb' )" ), 'known card gateways are grouped explicitly' );
$assert( false !== strpos( $table, "array( 'bacs', 'bank_transfer', 'virement', 'virement_bancaire' )" ), 'bank transfers are grouped explicitly' );
$assert( false !== strpos( $table, 'get_transaction_id' ), 'transaction trace is read from the order' );
$assert( false !== strpos( $table, 'get_edit_order_url' ), 'authorized admins can open the WooCommerce trace' );
$assert( false === strpos( $table, 'update_status' ), 'payment overview does not mutate order status' );
$assert( false !== strpos( $css, '.ufsc-payment-badge--card' ), 'payment badges have scoped admin styles' );

exit( $failures > 0 ? 1 : 0 );
