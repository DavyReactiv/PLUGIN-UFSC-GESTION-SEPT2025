<?php
$root = dirname( __DIR__ );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };

foreach ( array( 'ufsc_club_dashboard', 'ufsc_club_profile' ) as $shortcode ) {
    $assert( strpos( $front, "add_shortcode( '{$shortcode}'" ) !== false, "Shortcode preserved: {$shortcode}" );
}
foreach ( array( 'ufsc_save_club', 'ufsc_club_nonce', 'ufsc_upload_profile_photo', 'ufsc_remove_profile_photo', 'profile_photo', 'doc_statuts', 'doc_recepisse', 'doc_jo', 'doc_pv_ag', 'doc_cer', 'doc_attestation_cer' ) as $needle ) {
    $assert( strpos( $front, $needle ) !== false, 'Form/upload/document contract preserved: ' . $needle );
}
foreach ( array( 'ufsc_get_club_profile_value( $club, \'region\' )', 'ufsc_get_club_profile_value( $club, \'address\' )', 'ufsc_get_club_profile_value( $club, \'phone\' )', 'ufsc_get_club_profile_value( $club, \'email\' )', 'ufsc_get_club_profile_value( $club, \'website\' )', 'ufsc_get_club_profile_value( $club, \'logo\' )' ) as $needle ) {
    $assert( strpos( $front, $needle ) !== false, 'Read-only profile alias is used: ' . $needle );
}
foreach ( array( '--ufsc-front-primary', '--ufsc-front-success', '--ufsc-front-warning', '--ufsc-front-danger', '--ufsc-front-focus', '--ufsc-front-shadow-lg' ) as $var ) {
    $assert( strpos( $css, $var ) !== false, 'Premium front design-system token exists: ' . $var );
}
foreach ( array( '.ufsc-hero-kpi-grid', '.ufsc-profile-insight-band', '.ufsc-board-role-card', '.ufsc-document-card', '.ufsc-club-account__savebar', '@media (max-width: 1180px)', '@media (max-width: 780px)' ) as $selector ) {
    $assert( strpos( $css, $selector ) !== false, 'Premium responsive selector exists: ' . $selector );
}
$assert( strpos( $css, 'color: #ffffff' ) !== false && strpos( $css, 'color: var(--ufsc-front-text)' ) !== false, 'High-contrast light/dark text rules are present.' );
$assert( strpos( $front, 'ufsc_get_affiliation_renewal_state' ) !== false && strpos( $front, 'ufsc_get_affiliation_attestation_data' ) !== false, 'Affiliation and attestation business renderers remain in place.' );

echo "Premium front club UI static safeguards OK\n";
