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
    $assert( strpos( $front, "'" . substr( $anchor, 6 ) . "'" ) !== false, "Canonical URL helper maps anchor: {$anchor}" );
    $assert( strpos( $front, 'id="' . substr( $anchor, 1 ) . '"' ) !== false, "Anchor target present: {$anchor}" );
}
$assert( strpos( $front, 'id="ufsc-club-licences"' ) !== false, 'Licence-list anchor target is present.' );
$assert( substr_count( $front, 'id="ufsc-club-documents"' ) === 1, 'Documents ID is unique.' );
$assert( substr_count( $front, 'id="ufsc-club-officers"' ) === 1, 'Officers ID is unique.' );
$assert( strpos( $front, 'ufsc-club-logo' ) !== false && preg_match( '/aspect-ratio:\s*1\s*\/\s*1/', $css ) && preg_match( '/object-fit:\s*contain/', $css ), 'Reusable logos are square and non-cropping.' );
$assert( strpos( $front, "\$renewal_affiliation_done = ! empty( \$licence_affiliation_gate['allowed'] )" ) !== false, 'The canonical annual affiliation gate suppresses the renewal CTA.' );
$assert( strpos( $front, 'Affiliation %s active' ) !== false, 'Active affiliation message is explicit.' );
$assert( strpos( $front, 'Finaliser mon paiement' ) !== false && strpos( $front, 'En attente de validation' ) !== false && strpos( $front, 'Renouveler mon affiliation %s' ) !== false, 'Annual affiliation states render expected CTAs/messages.' );
$assert( strpos( $front, 'render_archived_licences_section( $archive_licences, $archive_seasons, $archive_filter, $atts, true, $archive_total, $archive_page, $archive_per_page )' ) !== false, 'Dashboard always renders read-only licence archives section.' );
$assert( strpos( $front, 'get_club_archive_seasons' ) !== false && strpos( $front, "'per_page' => " . '$archive_per_page' ) !== false && strpos( $front, 'array_slice( $archive_licences' ) === false, 'Archives use season-scoped SQL pagination instead of loading every row.' );
$assert( substr_count( $front, 'ufsc-club-account__savebar' ) === 1, 'Only one savebar is rendered.' );
$assert( strpos( $css, '.ufsc-club-portal' ) !== false && strpos( $css, 'width: min(100% - 32px, 1280px)' ) !== false, 'Canonical portal width exists.' );
$assert( strpos( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' ) !== false, 'KPI/document grids use safe minmax columns.' );
$assert( strpos( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' ) !== false, 'Account form grid uses the robust two-column desktop layout.' );
$assert( strpos( $css, 'scroll-margin-top: 120px' ) !== false, 'Anchors account for sticky headers.' );
$assert( strpos( $css, 'transform: scale(' ) === false && strpos( $css, 'zoom:' ) === false, 'No forbidden zoom or scale rules.' );
$assert( ! preg_match( '/max-width:\s*(280|320|400)px/', $css ), 'No tiny desktop max-width remains in ufsc-front.css.' );
echo "Final club portal static safeguards OK\n";
