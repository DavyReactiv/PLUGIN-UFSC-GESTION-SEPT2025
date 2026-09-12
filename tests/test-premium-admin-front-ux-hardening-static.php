<?php
$root = dirname(__DIR__);
$portal = file_get_contents($root . '/assets/js/ufsc-portal-clean.js');
$ux = file_get_contents($root . '/assets/js/ufsc-production-licence-ux.js');
$css = file_get_contents($root . '/assets/css/ufsc-production-licence-ux.css');
$admin = file_get_contents($root . '/assets/admin/js/admin.js');
$attest = file_get_contents($root . '/inc/common/attestations.php');
$handlers = file_get_contents($root . '/includes/core/class-unified-handlers.php');
if (false === $portal || false === $ux || false === $css || false === $admin || false === $attest || false === $handlers) {
    fwrite(STDERR, "FAIL unable to load premium UX sources\n"); exit(1);
}
$fail = array();
$assert = static function($ok, $message) use (&$fail) { if (!$ok) $fail[] = $message; };

// Compte Club anchors: one existing routing function, re-aligned after late theme layout.
$assert(false !== strpos($portal, 'function alignAccountAnchor()'), 'Compte Club anchor routing must be explicit');
$assert(false !== strpos($portal, "'ufsc-club-information'" ) && false !== strpos($portal, "'ufsc-club-documents'"), 'Compte Club canonical targets must be supported');
$assert(false !== strpos($portal, '[0, 80, 240, 600]'), 'late layout shifts must re-align the same canonical anchor');

// Detail/list premium UX must remain presentation-only and keep canonical actions.
$assert(false !== strpos($ux, "target.classList.contains('ufsc-licence-detail')"), 'detail must not receive the duplicated shortcut navigation');
$assert(false !== strpos($ux, 'function enhanceLicenceDetail()'), 'licence detail must receive premium grouped presentation');
$assert(false !== strpos($ux, 'function enhanceCurrentLicenceFilters()'), 'current licence list must expose useful category filters');
$assert(false !== strpos($ux, "age.name = 'ufsc_age'"), 'age category must use existing server-side filter');
$assert(false !== strpos($ux, "practice.name = 'ufsc_practice'"), 'practice category must use existing server-side filter');
$assert(false !== strpos($css, '.ufsc-licence-person-name') && false !== strpos($css, '.ufsc-licence-detail-grid'), 'premium list/detail CSS contract missing');

// Admin is action-oriented but business totals remain server-owned.
$assert(false !== strpos($admin, 'buildAdminPriorityCenter'), 'admin priority centre missing');
$assert(false !== strpos($admin, "$('.ufsc-dashboard-card')"), 'admin priority centre must reuse server-rendered KPIs');
$assert(false === strpos($admin, 'DELETE FROM') && false === strpos($admin, 'empty_cart'), 'UX hardening must not mutate data/cart');

// Honorability: minors are excluded at person level and from the club KPI/list.
$assert(false !== strpos($attest, 'function ufsc_is_minor_birth_date'), 'minor age helper missing');
$assert(false !== strpos($attest, 'function ufsc_licence_requires_honorability'), 'person-level honorability helper missing');
$assert(false !== strpos($attest, 'date_naissance FROM'), 'honorability list must read DOB');
$assert(false !== strpos($attest, 'ufsc_licence_requires_honorability( $row )'), 'minor-aware rule must filter club honorability list');

// Duplicate identity contract: only name + first name + DOB within club/season.
$assert(false !== strpos($handlers, 'AND date_naissance = %s'), 'duplicate candidate query must include date of birth');
$assert(false !== strpos($handlers, '$candidate->nom') && false !== strpos($handlers, '$candidate->prenom'), 'duplicate check must compare name and first name');
$duplicatePos = strpos($handlers, 'duplicate_licence');
$duplicateScope = false === $duplicatePos ? '' : substr($handlers, max(0, $duplicatePos - 1800), 2600);
$assert(false === stripos($duplicateScope, 'candidate->email') && false === stripos($duplicateScope, 'candidate->adresse'), 'email/address must never participate in duplicate blocking');

if ($fail) { foreach ($fail as $message) fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL); exit(1); }
echo "OK premium admin/front UX hardening contract\n";
