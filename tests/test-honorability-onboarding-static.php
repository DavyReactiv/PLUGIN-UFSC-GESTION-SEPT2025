<?php
/**
 * Static checks for the honorability onboarding compatibility layer.
 */

$root = dirname( __DIR__ );
$file = $root . '/inc/common/functions.php';
$code = file_get_contents( $file );

$checks = array(
    'official fillable template URL is present' => strpos( $code, 'Attestation_Honorabilite_UFSC_2026_remplissable.pdf' ) !== false,
    'official honorability note URL is present' => strpos( $code, '2021-06-02-2-ANNEXE-1-NOTE-SUR-LE-CONTROLE-DE-LHONORABILITE.pdf' ) !== false,
    'template stays configurable through existing filter' => strpos( $code, "add_filter( 'ufsc_honorability_template_url'" ) !== false,
    'licence flow reuses existing honorability_confirmed input' => strpos( $code, 'input[name="honorability_confirmed"]' ) !== false,
    'club form is detected through the existing save action' => strpos( $code, 'input[name="action"][value="ufsc_save_club"]' ) !== false,
    'no new upload endpoint is introduced here' => strpos( $code, 'admin_post_ufsc_upload_honorability' ) === false,
    'club creation is explicitly non blocking' => strpos( $code, 'Le dépôt ne bloque pas la création du club.' ) !== false,
    'guidance explains future profile upload' => strpos( $code, 'Compte Club' ) !== false,
    'checkbox wording no longer claims a document was already transmitted' => strpos( $code, 'Je reconnais avoir pris connaissance de l’obligation d’honorabilité' ) !== false,
);

$failed = array();
foreach ( $checks as $label => $ok ) {
    if ( ! $ok ) {
        $failed[] = $label;
    }
}

if ( $failed ) {
    fwrite( STDERR, "Honorability onboarding static checks failed:\n- " . implode( "\n- ", $failed ) . "\n" );
    exit( 1 );
}

echo "Honorability onboarding static checks passed.\n";
