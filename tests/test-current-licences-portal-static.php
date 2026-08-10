<?php
$source = file_get_contents(__DIR__ . '/../includes/frontend/class-frontend-shortcodes.php');
$css = file_get_contents(__DIR__ . '/../assets/css/ufsc-front.css');
$checks = array(
    'dedicated current-season renderer' => 'render_current_licences_section',
    'stable current list anchor' => 'id="ufsc-current-licences"',
    'season filter' => 'name="ufsc_season"',
    'empty-search recovery' => 'Réinitialiser les filtres',
    'edit/completion action' => 'Modifier / Compléter',
    'renewal route separation' => "'licences-renouvellement' === \$requested_section",
    'archive route separation' => "'licences-archives' === \$requested_section",
    'current list pagination state' => 'aria-current="page"',
    'disabled pagination state' => 'aria-disabled="true"',
);
foreach ($checks as $label => $needle) {
    if (strpos($source, $needle) === false) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
}
foreach (array('.ufsc-club-portal .ufsc-page-link', '.ufsc-club-portal .ufsc-licence-table--current', 'min-height: 44px') as $needle) {
    if (strpos($css, $needle) === false) { fwrite(STDERR, "FAIL CSS: $needle\n"); exit(1); }
}
if (preg_match('/(^|\})\s*(button|a|\.button)\s*\{/m', $css)) { fwrite(STDERR, "FAIL: unscoped global control selector\n"); exit(1); }
echo "Current licence list routing, filters, actions and responsive contract OK\n";
