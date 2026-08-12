<?php
/**
 * Static color and visibility safeguards for Club portal controls.
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

$contract_marker = '/* Canonical Club portal color contract.';
$contract_start  = strpos( $css, $contract_marker );
$assert( false !== $contract_start, 'The canonical Club portal color contract must exist.' );
$contract = substr( $css, $contract_start );

foreach ( array( '.ufsc-club-portal', '.ufsc-licences-section', '.ufsc-licence-detail', '.ufsc-add-licence-section' ) as $scope ) {
    $assert( false !== strpos( $contract, $scope ), "Missing scoped portal surface: {$scope}" );
}

foreach ( array(
    '--ufsc-control-primary: #0b4f86',
    '--ufsc-control-primary-hover: #073b66',
    '--ufsc-control-surface: #f8fafc',
    '--ufsc-control-text: #0f172a',
    '--ufsc-control-muted: #475569',
    '--ufsc-control-danger: #b91c1c',
    '--ufsc-control-disabled-bg: #e5e7eb',
    '--ufsc-control-disabled-text: #374151',
) as $token ) {
    $assert( false !== strpos( $contract, $token ), "Missing documented color token: {$token}" );
}

foreach ( array(
    ':is(.ufsc-btn, .ufsc-page-link, .ufsc-action)',
    ':is(:hover, :active)',
    ':focus-visible',
    ':is(:disabled, [disabled], [aria-disabled="true"], .ufsc-btn-disabled, .disabled)',
    '.ufsc-renewal-pagination [aria-current="page"]',
    '.ufsc-page-link.current',
    '.ufsc-club-account__nav a',
    '.ufsc-attestation-card',
    '.ufsc-honorability-document-card a:not(.ufsc-btn)',
    'min-height: 44px',
    'opacity: 1',
    'visibility: visible',
    '-webkit-text-fill-color:',
) as $rule ) {
    $assert( false !== strpos( $contract, $rule ), "Missing control-state safeguard: {$rule}" );
}

$assert( 0 === preg_match( '/(?:color|-webkit-text-fill-color)\s*:\s*transparent\b/i', $contract ), 'Control text must never be transparent.' );
$assert( 0 === preg_match( '/^\s*(?:button|a|input|select|body|\.button)(?=\s|[.:#\[])/m', $contract ), 'Canonical control rules must not use global theme selectors.' );
$assert( substr_count( $css, '!important' ) <= 37, 'The color correction must not add !important declarations.' );

foreach ( array(
    'Précédent',
    'Continuer',
    'Enregistrer en brouillon',
    'Ajouter au panier',
    'Vérifier les informations',
    'Rechercher',
    'Réinitialiser les filtres',
    'Première',
    'Précédente',
    'Suivante',
    'Dernière',
    'Consulter',
    'Modifier / Compléter',
    'Renouveler',
    'Supprimer',
    'questionnaire majeur',
    'questionnaire mineur',
    'contrôle de l’honorabilité',
    'Attestation UFSC',
) as $label ) {
    $assert( false !== strpos( $front, $label ), "Expected covered portal control is missing: {$label}" );
}

$relative_luminance = static function ( $hex ) {
    $hex = ltrim( $hex, '#' );
    $channels = array(
        hexdec( substr( $hex, 0, 2 ) ) / 255,
        hexdec( substr( $hex, 2, 2 ) ) / 255,
        hexdec( substr( $hex, 4, 2 ) ) / 255,
    );
    foreach ( $channels as &$channel ) {
        $channel = $channel <= 0.04045 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
    }
    unset( $channel );
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
};

$contrast = static function ( $foreground, $background ) use ( $relative_luminance ) {
    $first  = $relative_luminance( $foreground );
    $second = $relative_luminance( $background );
    return ( max( $first, $second ) + 0.05 ) / ( min( $first, $second ) + 0.05 );
};

$pairs = array(
    'primary'   => array( '#ffffff', '#0b4f86', 4.5 ),
    'hover'     => array( '#ffffff', '#073b66', 4.5 ),
    'secondary' => array( '#0b4f86', '#f8fafc', 4.5 ),
    'disabled'  => array( '#374151', '#e5e7eb', 4.5 ),
    'danger'    => array( '#b91c1c', '#ffffff', 4.5 ),
    'body'      => array( '#0f172a', '#ffffff', 4.5 ),
    'muted'     => array( '#475569', '#ffffff', 4.5 ),
    'focus'     => array( '#073b66', '#ffffff', 3.0 ),
);

$results = array();
foreach ( $pairs as $state => $pair ) {
    list( $foreground, $background, $minimum ) = $pair;
    $ratio = $contrast( $foreground, $background );
    $assert( strtolower( $foreground ) !== strtolower( $background ), "{$state} foreground and background must differ." );
    $assert( $ratio >= $minimum, sprintf( '%s contrast %.2f:1 is below %.1f:1.', $state, $ratio, $minimum ) );
    $results[] = sprintf( '%s=%.2f:1', $state, $ratio );
}

echo 'Club portal color contrast safeguards OK — ' . implode( ', ', $results ) . "\n";
