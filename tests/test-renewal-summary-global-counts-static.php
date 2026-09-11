<?php
$root = dirname(__DIR__);
$front = file_get_contents($root . '/includes/frontend/class-frontend-shortcodes.php');
$uxPhp = file_get_contents($root . '/inc/common/production-licence-ux.php');
$uxJs = file_get_contents($root . '/assets/js/ufsc-production-licence-ux.js');
if ($front === false || $uxPhp === false || $uxJs === false) {
    fwrite(STDERR, "FAIL: unable to read renewal counter sources\n");
    exit(1);
}

$failures = array();
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(
    false !== strpos($uxPhp, 'function ufsc_production_renewal_state_counts()'),
    'renewal UX must expose one canonical global state counter'
);
$assert(
    false !== strpos($uxPhp, "'renewalCounts'  => ufsc_production_renewal_state_counts()"),
    'canonical renewal counters must be localized once for the front UX'
);
$assert(
    false !== strpos($uxPhp, 'ufsc_get_licence_season_context_status'),
    'global renewal counters must use the canonical season-context resolver'
);
$assert(
    false !== strpos($uxJs, 'function applyCanonicalRenewalSummary()'),
    'renewal assistant must replace page-local summary with canonical global counters'
);
$assert(
    false !== strpos($uxJs, 'data-ufsc-global-renewal-counts'),
    'renewal summary must expose a dedicated global counter region'
);
$assert(
    false !== strpos($uxJs, 'Sélection courante — '),
    'selection completeness must be labelled as current selection, not a global KPI'
);

$assistantPos = strpos($front, 'private static function render_renewal_assistant');
$assistant = $assistantPos === false ? '' : substr($front, $assistantPos, 22000);
$assert(
    false !== strpos($assistant, 'data-ufsc-selection-count'),
    'renewal renderer must retain the live selection target used by the production controller'
);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo "OK renewal summary canonical global counts contract\n";
