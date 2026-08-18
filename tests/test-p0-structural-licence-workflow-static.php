<?php
$root = dirname(__DIR__);
$workflow = file_get_contents($root . '/inc/common/licence-workflow-structural.php');
$flags = file_get_contents($root . '/inc/common/feature-flags.php');
$js = file_get_contents($root . '/assets/js/ufsc-structural-portal.js');
$css = file_get_contents($root . '/assets/css/ufsc-structural-portal.css');

$assert = static function ($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};

$assert(strpos($flags, 'licence-workflow-structural.php') !== false, 'structural state service is loaded');
$assert(strpos($flags, "'submit_for_validation'") !== false, 'included finalization remains non-blocking for honorability');
$assert(strpos($workflow, 'ufsc_structural_finalize_saved_licence') !== false, 'saved licence finalization hook exists');
$assert(strpos($workflow, 'ufsc_allocate_pack_credit') !== false, 'server reserves quota atomically');
$assert(strpos($workflow, "'is_included' => 1") !== false, 'included reservation is persisted even for legacy NULL values');
$assert(strpos($workflow, "'en_attente'") !== false, 'included finalization becomes pending validation');
$assert(strpos($workflow, "'payment_status' => 'included'") !== false, 'included finalization is explicitly non-payable');
$assert(strpos($workflow, "'submitted_at'") !== false, 'submission date is persisted');
$assert(strpos($workflow, "$_GET['ufsc_section'] = 'licences-archives'") !== false, 'archive route is restored server-side');
$assert(strpos($workflow, 'ufsc_structural_admin_pending_notice') !== false, 'admin pending notice uses structural query');
$assert(strpos($js, 'completeStatusFilter') !== false && strpos($js, 'en_attente') !== false, 'current list exposes pending status');
$assert(strpos($js, 'preserveArchiveForms') !== false, 'archive forms preserve route client-side too');
$assert(strpos($js, 'routeArchiveRenewalsThroughAssistant') !== false, 'historical renewals enter assistant instead of direct cart');
$assert(strpos($js, 'dedupeClubNavigation') !== false, 'duplicate account navigation is removed');
$assert(strpos($js, 'Modifier le logo') !== false, 'logo exposes one primary edit action');
$assert(strpos($css, 'justify-content: center') !== false, 'buttons are centered');
$assert(strpos($css, '170px') !== false, 'desktop logo has useful size');
$assert(strpos($css, '.ufsc-club-hero') !== false, 'club overview layout has an explicit stable grid');

echo "P0 structural server and portal contract OK\n";
