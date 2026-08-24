<?php
/**
 * Static regression guard for the premium club wizard.
 * Run with: php tests/test-premium-club-wizard-static.php
 */
$root = dirname( __DIR__ );
$js_path  = $root . '/assets/frontend/js/ufsc-club-form.js';
$css_path = $root . '/assets/frontend/css/ufsc-club-form.css';

foreach ( array( $js_path, $css_path ) as $file ) {
    if ( ! is_file( $file ) ) {
        fwrite( STDERR, "Missing required file: {$file}\n" );
        exit( 1 );
    }
}

$js  = file_get_contents( $js_path );
$css = file_get_contents( $css_path );

$checks = array(
    'wizard is initialized once per form' => strpos( $js, "ufsc-wizard-ready" ) !== false,
    'wizard reuses the existing form' => strpos( $js, "fieldset.ufsc-form-section" ) !== false && strpos( $js, "detach()" ) !== false,
    'input names and server action are not rewritten' => strpos( $js, "attr('name'" ) === false && strpos( $js, "attr('action'" ) !== false,
    'drafts use sessionStorage' => strpos( $js, 'sessionStorage') !== false,
    'drafts exclude file and password fields' => strpos( $js, "['file', 'password', 'submit', 'button', 'reset']" ) !== false,
    'drafts expire' => strpos( $js, 'DRAFT_TTL_MS' ) !== false,
    'server errors can restore scalar values' => strpos( $js, "has('ufsc_error')" ) !== false && strpos( $js, 'restoreDraft') !== false,
    'file recovery limitation is explained' => strpos( $js, 'fichiers joints doivent être sélectionnés à nouveau') !== false,
    'required fields are validated before changing step' => strpos( $js, 'validatePane') !== false,
    'premium CSS remains scoped to the club form container' => strpos( $css, '.ufsc-club-form-container .ufsc-premium-wizard') !== false,
    'office cards use responsive two-column layout' => strpos( $css, '.ufsc-club-form-container .ufsc-dirigeants') !== false && strpos( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr))') !== false,
    'no generic unscoped premium button rule' => strpos( $css, "\n.ufsc-btn {") === false,
);

$failed = array();
foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) { $failed[] = $label; }
}

if ( $failed ) {
    fwrite( STDERR, "Premium club wizard regression check failed:\n - " . implode( "\n - ", $failed ) . "\n" );
    exit( 1 );
}

echo "Premium club wizard static checks: OK\n";
