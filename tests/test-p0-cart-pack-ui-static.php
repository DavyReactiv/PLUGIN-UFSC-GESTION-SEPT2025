<?php
$root=dirname(__DIR__);$handler=file_get_contents($root.'/includes/core/class-unified-handlers.php');$cart=file_get_contents($root.'/inc/woocommerce/cart-integration.php');$form=file_get_contents($root.'/includes/frontend/class-frontend-shortcodes.php');$js=file_get_contents($root.'/assets/js/ufsc-license-form.js');$css=file_get_contents($root.'/assets/css/ufsc-front.css');
$assert=static function($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}};
foreach(array('ufsc_allocate_pack_credit','Cette licence est comprise dans votre pack','ajoutée au panier au tarif en vigueur','is_purchasable') as $s)$assert(strpos($handler,$s)!==false,$s);
foreach(array('ufsc_ensure_woocommerce_cart','ufsc_persist_woocommerce_cart','is_purchasable') as $s)$assert(strpos($cart,$s)!==false,"renewal $s");
$assert(substr_count($form,'id="ufsc-health-confirm-adult"')===1&&substr_count($form,'id="ufsc-health-confirm-minor"')===1&&substr_count($form,'id="ufsc-honorability-confirmed"')===1,'unique compliance checkbox ids');
$assert(strpos($form,'id="role" name="role" required')!==false,'required canonical role');
$assert(strpos($js,"honorabilityRoles.indexOf(role.val())")!==false,'client role truth table');
foreach(array('min-height: 44px','height: 22px','-webkit-text-fill-color: #fff !important') as $s)$assert(strpos($css,$s)!==false,"accessible style $s");
echo "P0 cart, pack and accessible UI static safeguards OK\n";
