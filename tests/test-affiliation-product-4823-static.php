<?php
/** Static safeguards for canonical affiliation product 4823 renewal flow. */
$root = dirname( __DIR__ );
$settings = file_get_contents( $root . '/inc/woocommerce/settings-woocommerce.php' );
$dashboard = file_get_contents( $root . '/templates/frontend/club-dashboard.php' );
$cart = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$hooks = file_get_contents( $root . '/inc/woocommerce/hooks.php' );
$archive = file_get_contents( $root . '/includes/core/class-ufsc-season-archive-manager.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );

$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };

$assert( strpos( $settings, "'product_affiliation_id'  => 4823" ) !== false, 'Default affiliation product is 4823.' );
$assert( strpos( $settings, 'function ufsc_get_affiliation_product_id()' ) !== false && strpos( $settings, 'return $configured_id > 0 ? $configured_id : 4823;' ) !== false, 'Canonical helper respects an explicit product and otherwise returns 4823.' );
$assert( strpos( $settings, 'function ufsc_get_affiliation_product()' ) !== false && strpos( $settings, 'wc_get_product( $product_id )' ) !== false, 'Product helper uses wc_get_product.' );
$assert( strpos( $settings, 'function ufsc_get_affiliation_product_url()' ) !== false && strpos( $settings, '$product->get_permalink()' ) !== false, 'Product URL helper uses product permalink.' );
foreach ( array( 'woocommerce_inactive', 'missing_product_id', 'product_not_found', 'product_not_published', 'product_without_price', 'product_not_purchasable', 'permalink_unavailable' ) as $reason ) {
    $assert( strpos( $settings, $reason ) !== false, 'Diagnostic reason exists: ' . $reason );
}
$assert( strpos( $settings, "'hidden' ===" ) === false, 'Hidden catalog visibility must not block purchasable direct URL products.' );
$assert( strpos( $dashboard, 'UFSC_Season_Service::get_current_season()' ) !== false, 'Dashboard season label comes from current season service.' );
$assert( strpos( $dashboard, 'ufsc_get_affiliation_renewal_state' ) !== false && strpos( $dashboard, 'UFSC_Season_Archive_Manager::get_affiliation' ) === false, 'Dashboard delegates annual state to centralized helper.' );
$assert( strpos( $dashboard, 'Renouveler mon affiliation %s' ) !== false && strpos( $dashboard, 'href="<?php echo esc_url( $renewal_url ); ?>"' ) !== false, 'Renewal button uses generated product permalink URL.' );
$assert( strpos( $dashboard, 'Affiliation %s active' ) !== false, 'Active annual affiliation hides renewal path.' );
$assert( strpos( $dashboard, 'Finaliser mon paiement' ) !== false, 'Pending payment shows payment completion action.' );
$assert( strpos( $front, 'ufsc_get_affiliation_renewal_state' ) !== false && strpos( $front, 'ufsc_get_pending_affiliation_payment_url' ) !== false, 'Compte Club shortcode uses centralized renewal state and pending payment URL.' );
$assert( strpos( $front, '$can_renew_affiliation' ) !== false && strpos( $front, 'Renouveler mon affiliation %s' ) !== false, 'Compte Club shortcode exposes renewal CTA when annual state allows it.' );
$assert( strpos( $front, 'Le renouvellement en ligne est temporairement indisponible' ) !== false && strpos( $front, 'ufsc_get_affiliation_product_unavailable_message' ) !== false, 'Compte Club only shows unavailable fallback through product diagnostics.' );
$assert( strpos( $cart, 'ufsc_force_affiliation_product_quantity_one' ) !== false, 'Affiliation quantity is forced to one.' );
$assert( strpos( $cart, "add_to_cart( 4823" ) === false, 'No affiliation submission bypasses the configured product helper.' );
$assert( strpos( $cart, '$club_id !== $user_club_id' ) !== false, 'Legacy affiliation submission enforces club ownership.' );
$assert( strpos( $cart, "ufsc_cart_has_renewal_item( 'renew_affiliation'" ) !== false && strpos( $cart, "ufsc_wc_has_pending_renewal_order( 'renew_affiliation'" ) !== false, 'Cart and pending order duplicates are blocked.' );
foreach ( array( 'ufsc_item_type', 'ufsc_action', 'ufsc_club_id', 'ufsc_target_season', 'ufsc_previous_affiliation_id', 'ufsc_user_id', 'ufsc_return_url' ) as $meta ) {
    $assert( strpos( $cart, $meta ) !== false, 'Cart metadata exists: ' . $meta );
}
foreach ( array( '_ufsc_club_id', '_ufsc_affiliation_request_type', '_ufsc_target_season', '_ufsc_previous_affiliation_id', '_ufsc_affiliation_product_id', '_ufsc_request_user_id' ) as $meta ) {
    $assert( strpos( $cart, $meta ) !== false, 'Order metadata exists: ' . $meta );
}
$assert( strpos( $archive, 'ON DUPLICATE KEY UPDATE' ) !== false && strpos( $hooks, 'record_paid_renewal' ) !== false, 'Paid affiliation processing is idempotent.' );
$record = substr( $archive, strpos( $archive, 'public static function record_paid_renewal' ), 1800 );
$assert( strpos( $record, 'num_affiliation' ) === false, 'Paid affiliation renewal does not copy historical ASPTT affiliation number.' );
$assert( strpos( $front, "ufsc_club_can_manage_licences_for_season" ) !== false && strpos( $front, "\$affiliation_gate['message']" ) !== false, 'Licence renewals use the central annual affiliation gate and its contextual refusal.' );
$assert( strpos( $cart, 'ufsc_add_renewal_sources_to_cart' ) !== false && strpos( $cart, "'ufsc_request_type' => 'renewal'" ) !== false, 'Nominative individual/group licence renewal flow remains distinct.' );
$assert( strpos( $cart, 'add_to_cart( $product_id, 1' ) !== false, 'Renewed licences are added as quantity-one lines.' );
$assert( strpos( $hooks, "'previous_licence_id'" ) !== false, 'previous_licence_id lineage is preserved.' );

echo "Affiliation product 4823 static safeguards OK\n";
