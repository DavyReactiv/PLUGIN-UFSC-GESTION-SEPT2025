<?php
define( 'ABSPATH', __DIR__ );
$GLOBALS['opts'] = array();
$GLOBALS['products'] = array();
function get_option( $key, $default = false ) { return $GLOBALS['opts'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['opts'][ $key ] = $value; return true; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function current_time() { return time(); }
function apply_filters( $tag, $value ) { return $value; }
function __( $message ) { return $message; }
class WooCommerce {}
class Licence_Product {
    private $status; private $price; private $purchasable;
    public function __construct( $status, $price, $purchasable ) { $this->status=$status; $this->price=$price; $this->purchasable=$purchasable; }
    public function exists(){ return true; } public function get_name(){ return 'Licence UFSC'; }
    public function get_status(){ return $this->status; } public function get_price(){ return $this->price; }
    public function is_purchasable(){ return $this->purchasable; } public function get_catalog_visibility(){ return 'visible'; }
    public function get_type(){ return 'simple'; } public function get_permalink(){ return 'https://example.test/licence'; }
}
function wc_get_product( $id ) { return $GLOBALS['products'][$id] ?? false; }
require __DIR__ . '/../inc/woocommerce/settings-woocommerce.php';
$assert = static function($ok,$message){ if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);} };
$assert( 'Aucun produit Licence UFSC n’est configuré.' === ufsc_get_licence_product_message(), 'empty configuration diagnostic' );
$GLOBALS['opts']['product_license_id'] = 2934;
$settings = ufsc_get_woocommerce_settings();
$assert( 2934 === $settings['product_license_id'] && 2934 === $GLOBALS['opts']['ufsc_woocommerce_settings']['product_license_id'], 'legacy numeric option migrates once into canonical array' );
$assert( 'Le produit #2934 est introuvable.' === ufsc_get_licence_product_message(), 'missing product diagnostic' );
$GLOBALS['products'][2934] = new Licence_Product('draft','25',false);
$assert( 'Le produit #2934 est configuré mais n’est pas publié.' === ufsc_get_licence_product_message(), 'draft diagnostic' );
$GLOBALS['products'][2934] = new Licence_Product('publish','',false);
$assert( 'Le produit #2934 est configuré mais ne possède pas de prix.' === ufsc_get_licence_product_message(), 'price diagnostic' );
$GLOBALS['products'][2934] = new Licence_Product('publish','25',true);
$assert( 'Produit Licence UFSC configuré, publié et achetable.' === ufsc_get_licence_product_message(), 'valid runtime resolution' );
unset($GLOBALS['opts']['product_license_id']);
$assert( 2934 === ufsc_get_licence_product_id(), 'canonical saved value is idempotent' );
ufsc_save_woocommerce_settings( array( 'included_licenses' => 12 ) );
$assert( 2934 === ufsc_get_licence_product_id(), 'partial settings save cannot erase canonical product' );
echo "Canonical licence product migration/runtime safeguards OK\n";
