<?php
/**
 * Static regression guard for the production new-club onboarding hardening.
 * Run with: php tests/test-new-club-onboarding-hardening-static.php
 */
$root = dirname( __DIR__ );
$module_path = $root . '/inc/common/new-club-onboarding-hardening.php';
$feature_path = $root . '/inc/common/feature-flags.php';
$compliance_path = $root . '/inc/common/compliance.php';
$bridge_path = $root . '/inc/common/affiliation-order-state-bridge.php';

$files = array( $module_path, $feature_path, $compliance_path, $bridge_path );
foreach ( $files as $file ) {
    if ( ! is_file( $file ) ) {
        fwrite( STDERR, "Missing required file: {$file}\n" );
        exit( 1 );
    }
}

$module = file_get_contents( $module_path );
$feature = file_get_contents( $feature_path );
$compliance = file_get_contents( $compliance_path );
$bridge = file_get_contents( $bridge_path );

$checks = array(
    'runtime module is loaded' => strpos( $feature, "new-club-onboarding-hardening.php" ) !== false,
    'legacy checkout loop is intercepted' => strpos( $module, "prevent_legacy_checkout_loop" ) !== false,
    'duplicate checkout is blocked at validation boundary' => strpos( $module, "woocommerce_after_checkout_validation" ) !== false,
    'annual statuses are reused' => strpos( $module, "pending_validation" ) !== false && strpos( $module, "pending_payment" ) !== false,
    'registration dead slug is not reintroduced' => strpos( $module, "/creation-du-club/" ) === false,
    'registration password email is explained' => strpos( $module, "wp_new_user_notification_email" ) !== false,
    'checkout wording is localized' => strpos( $module, "Finaliser mon affiliation" ) !== false && strpos( $module, "Informations du responsable" ) !== false,
    'office addresses are validated' => strpos( $module, "president_adresse" ) !== false && strpos( $module, "secretaire_adresse" ) !== false && strpos( $module, "tresorier_adresse" ) !== false,
    'canonical honorability remains authoritative' => strpos( $compliance, "function ufsc_role_requires_honorability" ) !== false && strpos( $compliance, "'president', 'secretaire', 'tresorier'" ) !== false,
    'no second honorability storage model is defined' => strpos( $module, "function ufsc_save_honorability_document" ) === false,
    'canonical affiliation order bridge remains authoritative' => strpos( $bridge, "one affiliation pack maximum per club and season" ) !== false,
    'no duplicate bridge class is defined' => substr_count( $module, "UFSC_Affiliation_Order_State_Bridge" ) === 0,
);

$failed = array();
foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) { $failed[] = $label; }
}

if ( $failed ) {
    fwrite( STDERR, "New-club onboarding regression check failed:\n - " . implode( "\n - ", $failed ) . "\n" );
    exit( 1 );
}

echo "New-club onboarding hardening static checks: OK\n";
