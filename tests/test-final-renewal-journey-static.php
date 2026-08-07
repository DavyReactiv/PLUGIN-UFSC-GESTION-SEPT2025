<?php
$root = dirname(__DIR__);
$front = file_get_contents($root . '/includes/frontend/class-frontend-shortcodes.php');
$cart = file_get_contents($root . '/inc/woocommerce/cart-integration.php');
$hooks = file_get_contents($root . '/inc/woocommerce/hooks.php');
$level = file_get_contents($root . '/inc/common/fighter-level.php');
$service = file_get_contents($root . '/includes/core/class-ufsc-renewal-service.php');
$checks = array(
 'stable archives route' => strpos($front, "add_query_arg( 'ufsc_section', 'licences-archives'") !== false,
 'three step assistant' => strpos($front, 'ufsc-renewal-steps') !== false && strpos($front, "value=" . chr(34) . "add_to_cart" . chr(34)) !== false,
 'central level options' => strpos($level, 'function ufsc_get_sport_level_options') !== false && strpos($level, 'ufsc_sport_level_options') !== false,
 'quantity one validation' => strpos($cart, 'Chaque licence doit constituer une ligne de quantité 1') !== false,
 'person metadata' => strpos($cart, 'ufsc_person_identifier') !== false,
 'weight metadata' => strpos($cart, 'ufsc_weight') !== false,
 'paid pending validation' => substr_count($hooks, "['statut'] = 'pending_validation'") > 0,
 'permanent UFSC copied' => strpos($hooks, "['numero_licence_ufsc']") !== false,
 'renewal update allowlist' => strpos($service, 'editable_renewal_fields') !== false && strpos($service, 'sanitize_renewal_updates') !== false,
 'archive never updated' => strpos($service, 'UPDATE') === false,
 'order carries updates' => strpos($cart, '_ufsc_renewal_updates') !== false && strpos($hooks, '_ufsc_renewal_updates') !== false,
 'target category recalculated' => strpos($hooks, 'detect_for_athlete') !== false,
);
foreach ($checks as $label => $ok) { if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); } }
echo "Final renewal journey static checks passed.\n";
