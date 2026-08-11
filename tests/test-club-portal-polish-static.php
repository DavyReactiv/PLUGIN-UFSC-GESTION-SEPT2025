<?php
/** Final polish safeguards for the rendered Club portal surfaces. */

$root         = dirname( __DIR__ );
$portal_css   = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$frontend_css = file_get_contents( $root . '/assets/frontend/css/frontend.css' );
$front        = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== $portal_css && false !== $frontend_css && false !== $front, 'Polish sources must be readable.' );

$loaded_css = $portal_css . "\n" . $frontend_css;
$assert( 0 === preg_match( '/border-(?:left|right)(?:-width)?\s*:\s*(?:[2-9]|[1-9][0-9]+)px/i', $loaded_css ), 'Loaded portal CSS must not recreate a thick side-tab border.' );
$assert( false === stripos( $loaded_css, 'writing-mode' ), 'Portal CSS must not rotate interface copy into a vertical tab.' );

$production_sources = $front;
foreach ( array( 'assets', 'includes', 'inc', 'templates' ) as $directory ) {
    $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $directory ) );
    foreach ( $iterator as $file ) {
        if ( ! $file->isFile() || ! in_array( strtolower( $file->getExtension() ), array( 'php', 'js' ), true ) ) {
            continue;
        }
        $production_sources .= "\n" . file_get_contents( $file->getPathname() );
    }
}
$assert( 0 === preg_match( '/raconte\s*[- ]?\s*nous/i', $production_sources ), 'The plugin must not render the reported parasitic "raconte nous" link.' );

foreach ( array(
    '.ufsc-club-portal .ufsc-renewal-summary{margin:16px 0;padding:12px 14px;background:#eef6ff;border:1px solid #173b67;border-radius:8px}',
    '.ufsc-club-portal .ufsc-renewal-change-summary{margin:18px 0;padding:12px 14px;background:#ecfdf5;border:1px solid #047857;border-radius:8px}',
    '.ufsc-club-portal .ufsc-pack-summary a { background: #fff; border: 1px solid #cbdff3; border-radius: 18px;',
    'block-size: clamp(76px, 8vw, 96px)',
    'inline-size: clamp(76px, 8vw, 96px)',
    '.ufsc-club-portal .ufsc-message { border: 1px solid #94a3b8; border-radius: 8px;',
    'border-radius: var(--ufsc-front-radius-md);',
) as $contract ) {
    $assert( false !== strpos( $portal_css, $contract ), 'Missing canonical polish contract: ' . $contract );
}

$assert( false === strpos( $portal_css, '.ufsc-cockpit-priority .ufsc-priority-card' ), 'Unused cockpit priority side-tab rule must stay removed.' );
$assert( false === strpos( $portal_css, '.ufsc-premium-v3 .ufsc-hero-kpi-card.-success' ), 'Unrendered premium KPI variants must stay removed.' );
$assert( 35 === substr_count( $portal_css, '!important' ), 'Final polish must not add !important declarations.' );

echo "Club portal polish safeguards OK -- no side-tabs, no parasitic vertical copy, compact canonical cards, pack and logo\n";
