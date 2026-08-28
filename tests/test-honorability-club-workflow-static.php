<?php
/**
 * Static regression checks for the club-facing honorability workflow.
 * Run: php tests/test-honorability-club-workflow-static.php
 */

$root = dirname( __DIR__ );
$attestations = file_get_contents( $root . '/inc/common/attestations.php' );
$handlers     = file_get_contents( $root . '/includes/core/class-unified-handlers.php' );
$compliance   = file_get_contents( $root . '/inc/common/compliance.php' );

$failures = array();
$assert = static function( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) {
        $failures[] = $message;
    }
};

$assert( false !== strpos( $attestations, "add_shortcode( 'ufsc_honorability_documents'" ), 'Dedicated honorability shortcode missing.' );
$assert( false !== strpos( $attestations, "'ufsc_club_profile' !== $tag" ), 'Club profile integration missing.' );
$assert( false !== strpos( $attestations, 'ufsc_get_club_honorability_licences' ), 'Season-scoped licence resolver missing.' );
$assert( false !== strpos( $attestations, 'ufsc_role_requires_honorability' ), 'Canonical role rule must be reused.' );
$assert( false !== strpos( $attestations, 'ufsc_get_honorability_document' ), 'Canonical document storage must be reused.' );
$assert( false !== strpos( $attestations, 'ufsc_honorability_template_url' ), 'Configurable official template URL missing.' );
$assert( false !== strpos( $attestations, 'Déposer l’attestation signée' ), 'Club upload wording missing.' );
$assert( false !== strpos( $attestations, 'Déposée — vérification UFSC' ), 'Pending status wording missing.' );
$assert( false !== strpos( $attestations, 'À corriger' ), 'Correction-required status wording missing.' );
$assert( false !== strpos( $attestations, 'Document conforme pour cette saison' ), 'Validated season wording missing.' );
$assert( false !== strpos( $attestations, 'accept="application/pdf,image/jpeg,image/png"' ), 'Upload accept filter missing.' );
$assert( false !== strpos( $attestations, "wp_nonce_field( 'ufsc_honorability_attestation_'" ), 'Upload nonce missing.' );
$assert( false !== strpos( $attestations, 'action="<?php echo esc_url( admin_url( \'admin-post.php\' ) ); ?>"' ), 'Upload must use the existing admin-post handler.' );
$assert( false !== strpos( $handlers, "admin_post_ufsc_upload_honorability_attestation" ), 'Canonical upload handler missing.' );
$assert( false !== strpos( $handlers, "media_handle_upload( 'honorability_attestation'" ), 'Canonical media upload missing.' );
$assert( false !== strpos( $handlers, '5 * MB_IN_BYTES' ), 'Honorability upload size limit missing.' );
$assert( false !== strpos( $compliance, 'ufsc_honorability_document_option_key' ), 'Season-scoped canonical storage missing.' );
$assert( false !== strpos( $compliance, "'status' => 'pending'" ), 'Canonical pending state missing.' );
$assert( false === strpos( $attestations, 'update_option( ufsc_honorability_document_option_key' ), 'Presentation layer must not duplicate honorability persistence.' );

if ( $failures ) {
    fwrite( STDERR, "Honorability club workflow static tests failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}

echo "Honorability club workflow static tests: OK\n";
