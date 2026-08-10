<?php
/** Runtime proof for the exact form handler, including nonce, capability and read-after-write. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array();
function get_option($key,$default=false){ return $GLOBALS['options'][$key] ?? $default; }
function update_option($key,$value){ $changed=!array_key_exists($key,$GLOBALS['options']) || $GLOBALS['options'][$key]!==$value; $GLOBALS['options'][$key]=$value; return $changed; }
function wp_parse_args($args,$defaults=array()){ return array_merge($defaults,(array)$args); }
function absint($value){ return abs((int)$value); }
function sanitize_text_field($value){ return trim((string)$value); }
function wp_unslash($value){ return $value; }
function current_time(){ return time(); }
function apply_filters($tag,$value){ return $value; }
function __($message){ return $message; }
function wp_verify_nonce($nonce,$action){ return $nonce==='valid-runtime-nonce' && $action==='ufsc_woocommerce_settings'; }
function ufsc_user_can($capability){ return $capability==='manage_ufsc_settings'; }
class UFSC_Permissions { const CAP_SETTINGS_MANAGE='manage_ufsc_settings'; }
class WooCommerce {}
class Runtime_Licence_Product {
 public function exists(){return true;} public function get_name(){return 'Licences UFSC / FASPTT – Rejoignez le mouvement';}
 public function get_status(){return 'publish';} public function is_purchasable(){return true;}
 public function get_catalog_visibility(){return 'visible';} public function get_type(){return 'simple';}
 public function get_price(){return '32.00';} public function get_permalink(){return 'https://example.test/produit/licence-ufsc';}
}
function wc_get_product($id){ return (int)$id===2934 ? new Runtime_Licence_Product() : false; }
require dirname(__DIR__).'/inc/woocommerce/settings-woocommerce.php';
$assert=function($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}};
$post=array('_wpnonce'=>'valid-runtime-nonce','ufsc_save_woocommerce_settings'=>'1','ufsc_woocommerce_settings'=>array('product_license_id'=>'2934','product_affiliation_id'=>'4823','included_licenses'=>'10'));
$result=ufsc_process_woocommerce_settings_submission($post);
$assert($result['success'] && $result['product_id']===2934,'real handler saves and immediately rereads 2934');
$assert(($GLOBALS['options']['ufsc_woocommerce_settings']['product_license_id']??0)===2934,'canonical nested option contains 2934');
// Simulate a new request by resolving only from persistent option state.
$assert(ufsc_get_licence_product_id()===2934,'new request rereads 2934');
$partial=ufsc_process_woocommerce_settings_submission(array('_wpnonce'=>'valid-runtime-nonce','ufsc_save_woocommerce_settings'=>'1','ufsc_woocommerce_settings'=>array('included_licenses'=>'12')));
$assert($partial['success'] && ufsc_get_licence_product_id()===2934,'partial handler save preserves 2934');
$resolution=ufsc_get_licence_product_resolution();
$assert($resolution['configured_id']===2934 && $resolution['product_name']==='Licences UFSC / FASPTT – Rejoignez le mouvement' && $resolution['product_type']==='simple' && $resolution['product_status']==='publish' && $resolution['product_price']==='32.00' && $resolution['product_purchasable'] && $resolution['valid'],'runtime diagnostic resolves the real product contract');
$assert(ufsc_get_licence_product_message($resolution)==='Produit Licence UFSC configuré, publié et achetable.','valid product message');
$invalid=ufsc_process_woocommerce_settings_submission(array('_wpnonce'=>'invalid','ufsc_woocommerce_settings'=>array('product_license_id'=>'999')));
$assert(!$invalid['success'] && ufsc_get_licence_product_id()===2934,'invalid nonce cannot mutate product');
echo "WooCommerce settings real-handler runtime safeguards OK\n";
