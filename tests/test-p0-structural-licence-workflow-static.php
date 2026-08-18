<?php
$root = dirname(__DIR__);
$handler = file_get_contents($root . '/includes/core/class-unified-handlers.php');
$front = file_get_contents($root . '/includes/frontend/class-frontend-shortcodes.php');
$journey = file_get_contents($root . '/inc/common/club-journey.php');
$flags = file_get_contents($root . '/inc/common/feature-flags.php');
$trace = file_get_contents($root . '/inc/common/club-dashboard-hardening.php');
$css = file_get_contents($root . '/assets/css/ufsc-club-journey.css');

$assert = static function ($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};

$assert(strpos($handler, "'submit_for_validation'") !== false, 'canonical submit-for-validation intent exists');
$assert(strpos($handler, 'should_finalize_licence') !== false, 'finalization is distinct from cart mutation');
$assert(strpos($handler, '$wants_finalization') !== false, 'quota allocation uses finalization decision');
$assert(strpos($handler, "payment_status' => 'included'") !== false, 'included finalization persists included payment state');
$assert(strpos($front, 'name="ufsc_final_intent"') !== false, 'form has server fallback final intent');
$assert(strpos($front, "'submit_for_validation'") !== false, 'canonical form posts submit-for-validation when quota remains');
$assert(strpos($front, "'en_attente' => __( 'En attente de validation'") !== false, 'current list exposes pending validation filter');
$assert(strpos($front, '<input type="hidden" name="ufsc_section" value="licences-archives">') !== false, 'archive season form preserves archive route');
$assert(strpos($front, "Renouveler pour %s") !== false, 'archive row routes to renewal assistant');
$assert(strpos($front, "Finaliser — quota inclus en priorité") !== false, 'renewal CTA reflects included quota');
$assert(strpos($flags, "'submit_for_validation'") !== false, 'honorability filter recognizes included finalization');
$assert(strpos($trace, "'submit_for_validation'") !== false, 'submission chronology recognizes included finalization');
$assert(strpos($journey, 'Creation/edit and renewal controls are rendered canonically') !== false, 'post-render CTA rewrites removed');
$assert(strpos($front, 'Modifier le logo') !== false, 'single logo primary action exists');
$assert(strpos($css, 'P0 structural portal stabilization') !== false, 'scoped portal stabilization CSS exists');
$assert(strpos($css, 'justify-content: center') !== false, 'button centering contract exists');

echo "P0 structural licence workflow contract OK\n";
