<?php
$root = dirname( __DIR__ );
$handler = file_get_contents( $root . '/includes/frontend/class-club-form-handler.php' );
$ux = file_get_contents( $root . '/assets/js/ufsc-production-licence-ux.js' );

$failures = 0;
$assert = static function( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failures++;
    }
};

$assert(
    false !== strpos( $handler, "remove_query_arg( array( 'ufsc_success', 'ufsc_message' ), \$redirect_url )" ),
    'Un redirect erreur supprime les anciens messages de succès.'
);

$assert(
    false !== strpos( $handler, "remove_query_arg( array( 'ufsc_error', 'ufsc_message' ), \$redirect_url )" ),
    'Un redirect succès supprime les anciens messages d’erreur.'
);

$assert(
    false !== strpos( $ux, "document.querySelector('.ufsc-notice-error,.ufsc-alert.error,.ufsc-message.ufsc-error')" ),
    'Le runtime JS n’ajoute pas un second message si le serveur en a déjà rendu un.'
);

if ( $failures ) {
    exit( 1 );
}

echo "OK: notices de sauvegarde club dédupliquées.\n";
