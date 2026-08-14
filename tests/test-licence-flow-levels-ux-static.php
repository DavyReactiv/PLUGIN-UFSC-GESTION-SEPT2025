<?php
/** Static contract for the unified new/renewal licence journey. */
$root = dirname(__DIR__);
$compliance = file_get_contents($root.'/inc/common/compliance.php');
$cart = file_get_contents($root.'/inc/woocommerce/cart-integration.php');
$handlers = file_get_contents($root.'/includes/core/class-unified-handlers.php');
$renewal = file_get_contents($root.'/includes/core/class-ufsc-renewal-service.php');
$front = file_get_contents($root.'/includes/frontend/class-frontend-shortcodes.php');
$css = file_get_contents($root.'/assets/css/ufsc-front.css');
$assert=static function($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";};
$assert(strpos($compliance,'count( $included_roles ) >= $limit')!==false,'pack allocation uses total included count');
$assert(strpos($cart,'ufsc_allocate_pack_credit( $target_id, $club_id, $season, $target_role )')!==false,'renewal uses canonical pack allocation');
$assert(strpos($handlers,'ufsc_allocate_pack_credit')!==false,'new licence uses canonical pack allocation');
$assert(strpos($cart,'WC()->cart->add_to_cart')!==false,'paid renewal has WooCommerce add_to_cart path');
$assert(strpos($handlers,'ufsc_add_licence_ids_to_cart_idempotent')!==false,'paid new licence has idempotent cart path');
$assert(strpos($renewal,'never applied to the source row')!==false,'historical renewal source remains immutable');
$assert(strpos($front,'Niveau du boxeur')!==false,'front displays boxer level');
$assert(strpos($front,'ufsc_get_sport_level_help()')!==false,'front displays explicit level guidance');
$assert(strpos($css,'Club account navigation final contract')!==false,'club navigation final alignment contract exists');
$assert(substr_count($css,'!important')<=37,'navigation correction adds no important declarations');
echo "Unified licence journey safeguards OK\n";
