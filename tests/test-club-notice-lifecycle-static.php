<?php
$root = dirname( __DIR__ );
$handler = file_get_contents( $root . '/includes/frontend/class-club-form-handler.php' );
$form    = file_get_contents( $root . '/includes/frontend/class-club-form.php' );
$ux      = file_get_contents( $root . '/assets/js/ufsc-production-licence-ux.js' );
$core    = file_get_contents( $root . '/includes/core/class-sql.php' );
$admin   = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );

$failures = 0;
$assert = static function( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failures++;
    }
};

$assert(
    false !== strpos( $handler, "remove_query_arg( array( 'ufsc_success', 'ufsc_message' ), \$redirect_url )" ),
    'Un redirect erreur supprime les anciens succès.'
);
$assert(
    false !== strpos( $handler, "remove_query_arg( array( 'ufsc_error', 'ufsc_message' ), \$redirect_url )" ),
    'Un redirect succès supprime les anciennes erreurs.'
);
$assert(
    false !== strpos( $handler, "} elseif ( \$has_success ) {" ),
    'Le rendu club n’affiche jamais succès et erreur simultanément.'
);
$assert(
    false !== strpos( $handler, 'searchParams.delete("ufsc_error")' )
    && false !== strpos( $handler, 'searchParams.delete("ufsc_success")' ),
    'Les paramètres flash sont retirés de l’URL après le premier affichage.'
);
$assert(
    false !== strpos( $form, "} elseif ( \$has_success ) {" )
    && false !== strpos( $form, 'searchParams.delete("ufsc_error")' ),
    'Le formulaire club historique applique la même règle de message unique et temporaire.'
);
$assert(
    false !== strpos( $ux, ".ufsc-notice-error,.ufsc-notice-success" ),
    'Le runtime JS ne duplique pas une notice déjà rendue côté serveur.'
);

// Front/admin synchronization contract: both sides use the same canonical SQL field.
$assert(
    false !== strpos( $core, "'tresorier_ville'=>array('Trésorier – Ville','text')" ),
    'tresorier_ville reste une colonne canonique du club.'
);
$assert(
    false !== strpos( $admin, "'tresorier_ville'" ),
    'L’admin lit/rend le même champ canonique tresorier_ville.'
);
$assert(
    false !== strpos( $handler, "'ville'," ) && false !== strpos( $handler, "array( 'president', 'secretaire', 'tresorier', 'entraineur' )" ),
    'Le front sauvegarde la ville des dirigeants dans la même colonne canonique.'
);

if ( $failures ) {
    exit( 1 );
}

echo "OK: messages club temporaires, uniques et contrat front/admin synchronisé.\n";
