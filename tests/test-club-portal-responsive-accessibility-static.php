<?php
/**
 * Responsive and touch-accessibility safeguards for the Club portal.
 */

$root  = dirname( __DIR__ );
$css   = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== $css && false !== $front, 'Portal CSS and markup sources must be readable.' );

$marker = '/* Canonical responsive adaptation contract.';
$start  = strpos( $css, $marker );
$assert( false !== $start, 'The canonical responsive adaptation contract must exist.' );
$contract = substr( $css, $start );

foreach ( array( '.ufsc-club-portal', '.ufsc-licences-section', '.ufsc-licence-detail', '.ufsc-add-licence-section' ) as $scope ) {
    $assert( false !== strpos( $contract, $scope ), "Missing responsive scope: {$scope}" );
}

foreach ( array(
    '@media (max-width: 479px)',
    '@media (max-width: 767px)',
    '@media (min-width: 768px) and (max-width: 1199px)',
    '@media (min-width: 1200px)',
    '@media (min-width: 1440px)',
    '@media (pointer: coarse)',
) as $query ) {
    $assert( false !== strpos( $contract, $query ), "Missing responsive/input query: {$query}" );
}

foreach ( array(
    'inline-size: min(1180px, calc(100% - 32px))',
    'max-inline-size: 1180px',
    '.ufsc-club-portal :is(.ufsc-licences-section, .ufsc-licence-detail, .ufsc-add-licence-section)',
    'margin-inline: 0',
    'min-block-size: 44px',
    'min-inline-size: 44px',
    'overflow-wrap: normal',
    'word-break: normal',
    'hyphens: none',
) as $safeguard ) {
    $assert( false !== strpos( $contract, $safeguard ), "Missing narrow-screen safeguard: {$safeguard}" );
}

foreach ( array(
    'label:has(> input:is([type="checkbox"], [type="radio"]))',
    '.ufsc-checkbox-label, .ufsc-renewal-selection-control',
    'block-size: 24px',
    'flex: 0 0 24px',
    'td > input:is([type="checkbox"], [type="radio"])',
    ':focus-within',
    'accent-color: var(--ufsc-control-primary)',
) as $selection_rule ) {
    $assert( false !== strpos( $contract, $selection_rule ), "Missing checkbox/radio safeguard: {$selection_rule}" );
}

foreach ( array(
    '.ufsc-renewal-actions',
    '.ufsc-club-portal__actions',
    '.ufsc-licence-wizard-navigation',
    '.ufsc-form-actions',
    '.ufsc-final-buttons',
    '.ufsc-filter-actions',
    '.ufsc-dashboard-nav',
    '.ufsc-nav-btn',
    '.ufsc-club-account__nav',
    'grid-template-columns: repeat(4, minmax(0, 1fr))',
    'grid-template-columns: repeat(6, minmax(0, 1fr))',
    'grid-template-columns: repeat(3, minmax(0, 1fr))',
    'grid-template-columns: repeat(2, minmax(0, 1fr))',
    'grid-template-columns: minmax(0, 1fr)',
) as $layout_rule ) {
    $assert( false !== strpos( $contract, $layout_rule ), "Missing action/navigation layout rule: {$layout_rule}" );
}

foreach ( array(
    '.ufsc-renewal-filters, .ufsc-archive-filter-form',
    'repeat(auto-fit, minmax(min(100%, 180px), 1fr))',
    '.ufsc-current-licence-filters .ufsc-filter-actions',
    '.ufsc-front-table-scroll',
    'overflow-x: auto',
    'overscroll-behavior-inline: contain',
    'scrollbar-gutter: stable',
    '.ufsc-licence-detail .ufsc-table',
    'table-layout: fixed',
    '.ufsc-renewal-pagination, .ufsc-pagination-wrapper',
    'flex-wrap: wrap',
) as $workflow_rule ) {
    $assert( false !== strpos( $contract, $workflow_rule ), "Missing form/table/pagination rule: {$workflow_rule}" );
}

foreach ( array(
    'block-size: auto',
    'max-block-size: none',
    'min-block-size: 0',
    'a.ufsc-kpi-tile, .ufsc-pack-summary a, summary',
    'block-size: clamp(76px, 8vw, 96px)',
    'inline-size: clamp(76px, 8vw, 96px)',
    'object-fit: contain',
) as $fluid_rule ) {
    $assert( false !== strpos( $contract, $fluid_rule ), "Missing fluid height/logo rule: {$fluid_rule}" );
}

$assert( 0 === preg_match( '/overflow-x\s*:\s*hidden/i', $contract ), 'The responsive contract must fix overflow causes, not hide page overflow.' );
$assert( 0 === preg_match( '/display\s*:\s*none/i', $contract ), 'The responsive contract must not hide portal content.' );
$assert( 0 === preg_match( '/^\s*(?:body|button|a|input|select|table|\.button)[^{,\n]*\{/m', $contract ), 'Responsive rules must remain scoped to UFSC roots.' );
$assert( substr_count( $css, '!important' ) <= 37, 'Responsive adaptation must not add !important declarations.' );

foreach ( array(
    '<table class="ufsc-licence-table ufsc-licence-table--current">',
    '<table class="ufsc-licence-table ufsc-renewal-table">',
    'data-label="<?php esc_attr_e(',
    'role="region"',
    'tabindex="0"',
) as $semantic_markup ) {
    $assert( false !== strpos( $front, $semantic_markup ), "Responsive tables must retain semantic markup: {$semantic_markup}" );
}

foreach ( array(
    '--ufsc-control-primary: #0b4f86',
    '--ufsc-control-primary-hover: #073b66',
    '--ufsc-control-surface: #f8fafc',
    '--ufsc-control-disabled-bg: #e5e7eb',
    '--ufsc-control-disabled-text: #374151',
) as $color_contract ) {
    $assert( false !== strpos( $css, $color_contract ), "Colorize contract changed unexpectedly: {$color_contract}" );
}

echo "Club portal responsive contract OK — 360/768/1024/1440/1920, 44px targets, labelled selections, fluid actions, filters, tables, pagination and logos\n";
