<?php
$root = dirname( __DIR__ );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };

foreach ( array( 'ufsc_club_dashboard', 'ufsc_club_profile' ) as $shortcode ) {
    $assert( strpos( $front, "add_shortcode( '{$shortcode}'" ) !== false, "Shortcode registered: {$shortcode}" );
}
$assert( strpos( $front, 'ufsc-club-portal ufsc-club-account ufsc-club-dashboard' ) !== false, 'Dashboard uses canonical portal scope.' );
$assert( strpos( $front, 'ufsc-club-portal ufsc-club-account ufsc-club-profile' ) !== false, 'Account uses canonical portal scope.' );
foreach ( array( '#ufsc-overview', '#ufsc-club-information', '#ufsc-club-officers', '#ufsc-club-documents', '#ufsc-licences-archives' ) as $anchor ) {
    $assert( strpos( $front, 'href="' . $anchor . '"' ) !== false || '#ufsc-overview' === $anchor, "Anchor href present: {$anchor}" );
    $assert( strpos( $front, 'id="' . substr( $anchor, 1 ) . '"' ) !== false, "Anchor target present: {$anchor}" );
}
$assert( substr_count( $front, 'id="ufsc-club-documents"' ) === 1, 'Documents ID is unique.' );
$assert( substr_count( $front, 'id="ufsc-club-officers"' ) === 1, 'Officers ID is unique.' );
$assert( strpos( $front, 'width="96" height="96"' ) !== false && strpos( $front, 'width="280" height="220"' ) !== false, 'Logos/photos reserve first-paint dimensions.' );
$assert( strpos( $front, 'in_array( $affiliation_state[\'status\'], array( \'active\', \'validated\' ), true )' ) !== false, 'Active/validated annual statuses suppress renewal CTA.' );
$assert( strpos( $front, 'Affiliation %s active' ) !== false, 'Active affiliation message is explicit.' );
$assert( strpos( $front, 'Finaliser mon paiement' ) !== false && strpos( $front, 'En attente de validation' ) !== false && strpos( $front, 'Renouveler mon affiliation %s' ) !== false, 'Annual affiliation states render expected CTAs/messages.' );
$assert( strpos( $front, 'render_archived_licences_section( $archive_licences, $archive_seasons, $archive_filter, $atts, true )' ) !== false, 'Dashboard always renders read-only licence archives section.' );
$assert( strpos( $front, 'unset( $all_licence_args[\'season\'] );' ) !== false, 'Archive data is fetched across seasons then split by club-owned rows.' );
$assert( substr_count( $front, 'ufsc-club-account__savebar' ) === 1, 'Only one savebar is rendered.' );
$assert( strpos( $css, '.ufsc-club-portal' ) !== false && strpos( $css, 'width: min(100% - 32px, 1280px)' ) !== false, 'Canonical portal width exists.' );
$assert( strpos( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' ) !== false, 'KPI/document grids use safe minmax columns.' );
$assert( strpos( $css, 'grid-template-columns: repeat(12, minmax(0, 1fr))' ) !== false, 'Account form grid uses 12 columns.' );
$assert( strpos( $css, 'scroll-margin-top: 120px' ) !== false, 'Anchors account for sticky headers.' );
$assert( strpos( $css, 'transform: scale(' ) === false && strpos( $css, 'zoom:' ) === false, 'No forbidden zoom or scale rules.' );
$assert( ! preg_match( '/max-width:\s*(280|320|400)px/', $css ), 'No tiny desktop max-width remains in ufsc-front.css.' );
echo "Final club portal static safeguards OK\n";
