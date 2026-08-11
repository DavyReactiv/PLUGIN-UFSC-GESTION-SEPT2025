<?php
$root = dirname( __DIR__ );
$css = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$css = str_replace( array( "\r\n", "\r" ), "\n", $css );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };

$assert( strpos( $css, 'body.ufsc-club-portal-page :is(' ) === false, 'No broad :is(...):has(.ufsc-club-portal) ancestor widening remains.' );
$assert( strpos( $css, 'body.ufsc-club-portal-page .site-content > .ast-container' ) !== false, 'Astra content wrapper is targeted explicitly.' );
$assert( strpos( $css, '.elementor-widget-shortcode:has(.ufsc-club-portal)' ) !== false, 'Elementor shortcode widget is targeted explicitly.' );
$assert( strpos( $css, 'max-width: 1280px;\n    flex-basis' ) === false, 'Intermediate wrappers do not receive a boxed max-width before flex sizing.' );
$assert( strpos( $css, '.ufsc-club-portal .ufsc-dashboard-hero-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px' ) !== false || strpos( $css, 'grid-template-columns: minmax(0, 1fr) 300px;' ) !== false, 'Dashboard hero uses bounded 300px KPI column.' );
$assert( strpos( $css, 'grid-template-columns: minmax(360px, .86fr)' ) === false && strpos( $css, 'minmax(520px, 1.14fr)' ) === false, 'Old overflowing dashboard hero columns removed.' );
$assert( strpos( $css, "grid-template-columns: repeat(2, minmax(0, 1fr));\n    gap: 18px" ) !== false, 'Compte Club form cards use the compact robust two-column grid.' );
$assert( strpos( $css, 'grid-template-columns: repeat(12' ) === false, 'Compte Club no longer depends on a 12-column grid.' );
$assert( strpos( $css, 'grid-template-columns: repeat(3, minmax(0, 1fr));' ) !== false, 'Dirigeants grid uses three fluid columns.' );
$assert( strpos( $front, 'ufsc-club-portal__section--full" id="ufsc-club-officers"' ) !== false, 'Dirigeants section is explicitly full width.' );
$assert( strpos( $front, 'ufsc-club-portal__section--full" id="ufsc-club-documents"' ) !== false, 'Documents section is explicitly full width.' );
$assert( strpos( $front, 'ufsc-profile-insight__value' ) !== false && strpos( $front, 'Documents manquants' ) !== false && strpos( $front, 'Rôles manquants' ) !== false, 'Compte Club KPI values and labels are separated.' );
$assert( strpos( $css, 'width: 100vw' ) === false && strpos( $css, 'margin-left: -' ) === false && strpos( $css, 'transform: scale(' ) === false && strpos( $css, 'zoom:' ) === false, 'No forbidden viewport/scale hack is used.' );

echo "Front club portal layout regression safeguards OK\n";
