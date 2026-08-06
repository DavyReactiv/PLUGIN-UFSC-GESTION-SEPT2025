<?php
$root = dirname(__DIR__);
$front = file_get_contents($root . '/includes/frontend/class-frontend-shortcodes.php');
$cart = file_get_contents($root . '/inc/woocommerce/cart-integration.php');
$hooks = file_get_contents($root . '/inc/woocommerce/hooks.php');
$level = file_get_contents($root . '/inc/common/fighter-level.php');
$checks = array(
 'stable archives route' => strpos($front, "add_query_arg( 'ufsc_section', 'licences-archives'") !== false,
 'three step assistant' => strpos($front, 'ufsc-renewal-steps') !== false && strpos($front, 'Ajouter les licences éligibles au panier') !== false,
 'central level options' => strpos($level, 'function ufsc_get_sport_level_options') !== false && strpos($level, 'ufsc_sport_level_options') !== false,
 'quantity one validation' => strpos($cart, 'Chaque licence doit constituer une ligne de quantité 1') !== false,
 'person metadata' => strpos($cart, 'ufsc_person_identifier') !== false,
 'weight metadata' => strpos($cart, 'ufsc_weight') !== false,
 'paid pending validation' => substr_count($hooks, "['statut'] = 'pending_validation'") > 0,
 'permanent UFSC copied' => strpos($hooks, "['numero_licence_ufsc']") !== false,
);
foreach ($checks as $label => $ok) { if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); } }
echo "Final renewal journey static checks passed.\n";
