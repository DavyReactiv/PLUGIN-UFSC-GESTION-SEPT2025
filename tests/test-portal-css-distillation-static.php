<?php
/**
 * Guards the proven CSS deduplication performed by the distill phase.
 */

$root         = dirname( __DIR__ );
$frontend_css = file_get_contents( $root . '/assets/frontend/css/frontend.css' );
$portal_css   = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$layout_test  = file_get_contents( __DIR__ . '/test-front-club-portal-layout-regression-static.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== $frontend_css && false !== $portal_css && false !== $layout_test, 'Distillation sources must be readable.' );

$normalize = static function ( $value ) {
    return trim( preg_replace( '/\s+/', ' ', $value ), " ;\t\n\r\0\x0B" );
};

$rules = static function ( $css ) use ( $normalize ) {
    $parsed = array();
    preg_match_all( '/([^{}@]+)\{([^{}]+)\}/s', $css, $matches, PREG_SET_ORDER );
    foreach ( $matches as $match ) {
        $selector = preg_replace( '/\/\*.*?\*\//s', '', $match[1] );
        $key      = $normalize( $selector ) . '|' . $normalize( $match[2] );
        $parsed[ $key ] = isset( $parsed[ $key ] ) ? $parsed[ $key ] + 1 : 1;
    }
    return $parsed;
};

$frontend_rules = $rules( $frontend_css );
$portal_rules   = $rules( $portal_css );

$expected_once = array(
    '.ufsc-dashboard-nav|background: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; flex-wrap: wrap; gap: 0',
    '.ufsc-nav-btn|background: transparent; border: none; padding: 1rem 1.5rem; cursor: pointer; font-size: 0.95rem; font-weight: 500; color: #495057; border-bottom: 3px solid transparent; transition: all 0.3s ease',
    '.ufsc-nav-btn:hover|background: #e9ecef; color: #2c3e50',
    '.ufsc-nav-btn.active|color: #2c3e50; border-bottom-color: #3498db; background: white',
    '.ufsc-dashboard-content|background: white; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px',
    '.ufsc-section-header|display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f8f9fa',
    '.ufsc-kpi-value|font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin-bottom: 0.5rem',
    '.ufsc-document-actions|display: flex; gap: 0.5rem',
    '.ufsc-upload-label:hover|background: #2980b9',
    '.ufsc-help-text|margin: 0.5rem 0 0 0; font-size: 0.8rem; color: #6c757d; font-style: italic',
    '.ufsc-dashboard-section|padding: 1rem',
    '.ufsc-table|font-size: var(--ufsc-font-size-base)',
    '.ufsc-table th, .ufsc-table td|padding: 0.5rem',
    '.ufsc-table--spacious th, .ufsc-table--spacious td|padding-top: 0.75rem; padding-bottom: 0.75rem',
    '.ufsc-section-header--tight|margin-bottom: 0.5rem; padding-bottom: 0.25rem',
);

foreach ( $expected_once as $key ) {
    $assert( 1 === ( $frontend_rules[ $key ] ?? 0 ), "Expected one canonical frontend rule, found a missing or duplicated rule: {$key}" );
}

$profile_key = '.ufsc-club-portal .ufsc-profile-cards|grid-template-columns: 1fr';
$assert( 1 === ( $portal_rules[ $profile_key ] ?? 0 ), 'The mobile profile-card collapse must have one effective declaration.' );

$assert( false !== strpos( $layout_test, 'str_replace( array( "\\r\\n", "\\r" ), "\\n", $css )' ), 'The layout regression test must normalize LF/CRLF without changing its CSS assertion.' );
$assert( false !== strpos( $layout_test, 'grid-template-columns: repeat(2, minmax(0, 1fr));\n    gap: 18px' ), 'The compact two-column layout assertion must remain exact after newline normalization.' );
$assert( 35 >= substr_count( $portal_css, '!important' ), 'Polish may remove obsolete overrides but must never increase the !important baseline.' );

echo "Portal CSS distillation safeguards OK — 16 redundant rule blocks consolidated with contracts preserved\n";
