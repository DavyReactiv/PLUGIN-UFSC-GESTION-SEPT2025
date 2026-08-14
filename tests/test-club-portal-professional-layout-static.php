<?php
/** Regression safeguards for the professional Club portal layout. */

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

$assert( false !== $portal_css && false !== $frontend_css && false !== $front, 'Portal sources must be readable.' );

$loaded_css = $frontend_css . "\n" . $portal_css;
$assert( 0 === preg_match( '/word-break\s*:\s*break-all/i', $loaded_css ), 'Portal CSS must never split words letter by letter.' );
$assert( 0 === preg_match( '/overflow-x\s*:\s*hidden/i', $portal_css ), 'Portal overflow must be fixed structurally, not hidden.' );
$large_container_violation = false;
preg_match_all( '/([^{}]+)\{([^{}]+)\}/s', $portal_css, $portal_rules, PREG_SET_ORDER );
foreach ( $portal_rules as $portal_rule ) {
    $selector = trim( preg_replace( '/\/\*.*?\*\//s', '', $portal_rule[1] ) );
    if ( false !== strpos( $selector, '::' ) || 0 === preg_match( '/\.(?:ufsc-dashboard-header|ufsc-dashboard-hero-layout|ufsc-dashboard-content|ufsc-club-hero|ufsc-profile-cards)(?:[\s,.#:\[]|$)/', $selector ) ) {
        continue;
    }
    if ( preg_match( '/\b(?:height|min-height)\s*:\s*(?:100v(?:h|w|min|max)|[2-9][0-9]{2,}px)/i', $portal_rule[2] ) ) {
        $large_container_violation = true;
        break;
    }
}
$assert( ! $large_container_violation, 'Large portal containers must remain content-driven.' );

foreach ( array(
    'grid-template-columns: repeat(3, minmax(220px, 1fr))',
    '@media (max-width: 1199px) { .ufsc-club-portal .ufsc-pack-summary { grid-template-columns: repeat(2, minmax(220px, 1fr)); } }',
    '@media (max-width: 767px) { .ufsc-club-portal .ufsc-pack-summary { grid-template-columns: minmax(0, 1fr); }',
    'grid-template-columns: repeat(2, minmax(150px, 1fr))',
    '@media (min-width: 768px) and (max-width: 1199px)',
    '@media (min-width: 1200px)',
    'grid-template-columns: repeat(6, minmax(0, 1fr))',
    'grid-template-columns: repeat(3, minmax(0, 1fr))',
    'grid-template-columns: repeat(2, minmax(0, 1fr))',
    'grid-template-columns: minmax(0, 1fr)',
) as $layout_contract ) {
    $assert( false !== strpos( $portal_css, $layout_contract ), 'Missing responsive layout contract: ' . $layout_contract );
}

foreach ( array(
    'class="ufsc-pack-card"',
    'class="ufsc-pack-card__label"',
    'class="ufsc-pack-card__value"',
    'Licences incluses dans votre affiliation',
    'Quota inclus',
    'Licences supplémentaires payantes',
    'class="ufsc-logo-editor__title"',
) as $markup_contract ) {
    $assert( false !== strpos( $front, $markup_contract ), 'Missing professional portal markup: ' . $markup_contract );
}
$assert( false === strpos( $front, "'%d/7'" ), 'The portal must not present the obsolete seven-free quota.' );

$assert( false !== strpos( $portal_css, '.ufsc-club-portal .ufsc-pack-summary a' ) && false !== strpos( $portal_css, 'min-width: 220px' ), 'Affiliation cards need a readable minimum width.' );
$assert( false !== strpos( $portal_css, '.ufsc-club-portal .ufsc-club-account__nav a' ) && false !== strpos( $portal_css, 'min-height: 44px' ), 'Account tabs must preserve 44px targets.' );
$assert( false !== strpos( $portal_css, '.ufsc-club-portal .ufsc-logo-editor { align-items: center; display: grid;' ), 'Logo management must use the compact grid.' );
$assert( false !== strpos( $portal_css, 'object-fit: contain' ), 'Club logos must remain uncropped and undistorted.' );
$assert( false === strpos( $frontend_css, "\ninput:focus," ) && false === strpos( $frontend_css, "\nselect:focus," ) && false === strpos( $frontend_css, "\ntextarea:focus" ), 'Legacy focus rules must remain scoped to UFSC containers.' );

echo "Professional Club portal layout safeguards OK -- content height, readable cards, aligned tabs and responsive grids\n";
