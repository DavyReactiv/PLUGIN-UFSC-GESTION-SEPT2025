<?php
/** End-to-end contract from the actual rendered form source to the registered handler. */
$root = dirname(__DIR__);
$form = file_get_contents($root.'/includes/frontend/class-frontend-shortcodes.php');
$handler = file_get_contents($root.'/includes/core/class-unified-handlers.php');
$cart = file_get_contents($root.'/inc/woocommerce/cart-integration.php');
$assert = static function($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}};

$assert(strpos($form,'name="action" value="ufsc_save_licence"')!==false,'real admin-post action');
$assert(strpos($form,"wp_nonce_field( 'ufsc_save_licence' )")!==false,'real nonce');
$assert(strpos($form,'name="licence_id"')!==false,'real licence identifier');
$assert(strpos($form,'name="ufsc_submit_action" value="add_to_cart"')!==false,'semantic cart submitter');
$assert(strpos($form,"onclick=\"document.getElementById('ufsc_submit_action')")===false,'no JavaScript-only intent');
$assert(strpos($handler,"admin_post_ufsc_save_licence")!==false,'registered real handler');
$assert(strpos($handler,"should_add_licence_to_cart( \$submit_action )")!==false,'handler consumes submitted intent');
foreach(array('add_to_cart( $product_id, 1','ufsc_licence_id','ufsc_nom','ufsc_prenom','ufsc_persist_woocommerce_cart','set_customer_session_cookie','save_data') as $token){$assert(strpos($cart,$token)!==false,"cart contract $token");}
echo "Real licence form-to-native-cart contract safeguards OK\n";
