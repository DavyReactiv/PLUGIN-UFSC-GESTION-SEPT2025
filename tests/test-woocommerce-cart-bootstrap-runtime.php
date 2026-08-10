<?php
/** Runtime proof that admin-post initializes the native Woo session/cart. */
define('ABSPATH',__DIR__.'/');
function __($v){return $v;} function add_action(){} function add_filter(){} function is_wp_error($v){return $v instanceof WP_Error;} class WP_Error{private $c,$m;function __construct($c,$m){$this->c=$c;$this->m=$m;}function get_error_message(){return $this->m;}}
class RuntimeCart{public $items=array(),$totals=0,$sessions=0;function get_cart(){return $this->items;}function add_to_cart($p,$q,$v,$a,$d){$this->items[]=array('product_id'=>$p,'quantity'=>$q)+$d;return 'line';}function calculate_totals(){$this->totals++;}function set_session(){$this->sessions++;}}
class RuntimeSession{public $cookies=0,$saves=0;function set_customer_session_cookie($set){if($set)$this->cookies++;}function save_data(){$this->saves++;}}
class RuntimeWoo{public $session=null,$cart=null,$session_calls=0,$cart_calls=0;function initialize_session(){$this->session=new RuntimeSession();$this->session_calls++;}function initialize_cart(){$this->cart=new RuntimeCart();$this->cart_calls++;}}
$GLOBALS['runtime_wc']=new RuntimeWoo();function WC(){return $GLOBALS['runtime_wc'];}
require dirname(__DIR__).'/inc/woocommerce/cart-integration.php';
$result=ufsc_ensure_woocommerce_cart();if($result!==true||WC()->session_calls!==1||WC()->cart_calls!==1||!WC()->cart)exit("FAIL native cart bootstrap\n");
$result=ufsc_ensure_woocommerce_cart();if($result!==true||WC()->session_calls!==1||WC()->cart_calls!==1)exit("FAIL idempotent cart bootstrap\n");
$key=WC()->cart->add_to_cart(2934,1,0,array(),array('ufsc_licence_id'=>42,'ufsc_club_id'=>9));if(!$key||count(WC()->cart->items)!==1||WC()->cart->items[0]['quantity']!==1)exit("FAIL nominative quantity-one cart line\n");
$persisted=ufsc_persist_woocommerce_cart();if($persisted!==true||WC()->cart->totals!==1||WC()->cart->sessions!==1||WC()->session->cookies!==1||WC()->session->saves!==1)exit("FAIL persistent native cart session\n");
echo "Native WooCommerce session/cart bootstrap runtime safeguards OK\n";
