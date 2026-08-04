<?php
/** Executable BACS and seasonal archive schema regression checks. */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['options'] = array( 'admin_email' => 'admin@example.test' );
function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function sanitize_email( $email ) { return filter_var( $email, FILTER_SANITIZE_EMAIL ); }
function is_email( $email ) { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function ufsc_get_woocommerce_settings() { return array(); }
require dirname( __DIR__ ) . '/inc/woocommerce/hooks.php';
$html = ufsc_wc_bacs_instructions_html();
foreach ( array( 'numéro de commande', 'nom complet du club', 'admin@example.test' ) as $expected ) {
    if ( false === strpos( $html, $expected ) ) { fwrite( STDERR, "FAIL: missing BACS instruction {$expected}\n" ); exit( 1 ); }
}
$migration = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-ufsc-db-migrations.php' );
if ( false === strpos( $migration, '`validated_by`' ) || false === strpos( $migration, 'UNIQUE KEY `uniq_club_season`' ) ) {
    fwrite( STDERR, "FAIL: annual affiliation audit/idempotence schema incomplete\n" ); exit( 1 );
}
$cart = file_get_contents( dirname( __DIR__ ) . '/inc/woocommerce/cart-integration.php' );
foreach ( array( 'ufsc_request_type', 'ufsc_user_id', 'ID interne du club', 'Renouvellement d’affiliation' ) as $expected ) {
    if ( false === strpos( $cart, $expected ) ) { fwrite( STDERR, "FAIL: missing cart/order context {$expected}\n" ); exit( 1 ); }
}
echo "Production readiness runtime safeguards OK\n";
