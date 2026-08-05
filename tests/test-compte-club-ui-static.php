<?php
/** Static safeguards for /compte-club/ premium UI without changing business handlers. */
$root = dirname( __DIR__ );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$bootstrap = file_get_contents( $root . '/ufsc-clubs-licences-sql.php' );
$handler = file_get_contents( $root . '/includes/frontend/class-club-form-handler.php' );
$uploads = file_get_contents( $root . '/includes/core/class-uploads.php' );
$settings = file_get_contents( $root . '/inc/woocommerce/settings-woocommerce.php' );

$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };

$assert( strpos( $front, "add_shortcode( 'ufsc_club_dashboard'" ) !== false, 'Canonical dashboard shortcode must remain registered.' );
$assert( strpos( $front, 'ufsc-club-account ufsc-club-dashboard ufsc-premium-v3' ) !== false, 'Compte Club dashboard must expose the canonical container.' );
$assert( strpos( $front, 'ufsc-club-account__header' ) !== false && strpos( $front, 'ufsc-club-account__identity' ) !== false, 'Hero summary and identity block are present.' );
foreach ( array( 'Région', 'Adresse', 'Téléphone', 'Email', 'Site' ) as $label ) {
    $assert( strpos( $front, $label ) !== false, 'Hero must include non-empty summary label: ' . $label );
}
$assert( strpos( $front, 'ufsc-club-account__nav' ) !== false && strpos( $front, 'Vue d’ensemble' ) !== false && strpos( $front, 'Documents' ) !== false, 'Accessible anchor navigation is present.' );
$assert( strpos( $front, 'Renouveler mon affiliation %s' ) !== false && strpos( $front, 'Le renouvellement en ligne est temporairement indisponible' ) !== false, 'Affiliation CTA and fallback remain rendered by existing logic.' );
$assert( strpos( $front, 'dev.ufsc-france.fr/produit' ) === false, 'No hard-coded affiliation product URL is used in UI.' );
$assert( strpos( $settings, 'ufsc_get_affiliation_product_id()' ) !== false, 'Existing affiliation product helper remains available.' );
$assert( strpos( $front, 'name="action" value="ufsc_save_club"' ) !== false && strpos( $front, "wp_nonce_field( 'ufsc_save_club', 'ufsc_club_nonce' )" ) !== false, 'Save action and nonce are preserved.' );
foreach ( array( 'profile_photo', 'ufsc_upload_profile_photo', 'ufsc_remove_profile_photo', 'doc_statuts', 'statuts_upload', 'recepisse_upload', 'jo_upload', 'pv_ag_upload', 'cer_upload', 'attestation_cer_upload' ) as $field ) {
    $assert( strpos( $front, $field ) !== false || strpos( $handler, $field ) !== false || strpos( $uploads, $field ) !== false, 'Expected field/handler preserved: ' . $field );
}
$assert( strpos( $front, 'ufsc-document-summary' ) !== false && strpos( $front, 'ufsc-documents-grid' ) !== false && strpos( $front, 'ufsc-document-card' ) !== false, 'Documents are rendered as summarized cards.' );
foreach ( array( '--ufsc-primary', '--ufsc-primary-dark', '--ufsc-surface', '--ufsc-border', '--ufsc-text', '--ufsc-muted', '--ufsc-success', '--ufsc-warning', '--ufsc-danger', '--ufsc-radius', '--ufsc-shadow' ) as $var ) {
    $assert( strpos( $css, $var ) !== false, 'Design-system variable exists: ' . $var );
}
$assert( strpos( $css, 'max-width: 1280px !important' ) !== false && strpos( $css, 'width: min(100% - clamp(20px, 4vw, 56px), 1280px)' ) !== false, 'Desktop width target is enforced.' );
$assert( strpos( $css, '@media (max-width: 1024px)' ) !== false && strpos( $css, '@media (max-width: 768px)' ) !== false && strpos( $css, '@media (max-width: 420px)' ) !== false, 'Responsive breakpoints are present.' );
$assert( strpos( $css, '.ufsc-club-account__savebar' ) !== false && strpos( $front, 'Mettre à jour le club' ) !== false, 'Compact save bar and save button are preserved.' );
$assert( strpos( $bootstrap, "has_shortcode( \$post->post_content, 'ufsc_club_dashboard' )" ) !== false, 'Frontend assets remain scoped to shortcode pages.' );

echo "Compte Club UI static safeguards OK\n";
