<?php
$root = dirname( __DIR__ );
$hotfix = file_get_contents( $root . '/inc/common/prod-club-save-dashboard-hotfix.php' );
$loader = file_get_contents( $root . '/inc/common/front-club-registration-scope-hotfix.php' );
$sql = file_get_contents( $root . '/includes/core/class-sql.php' );
$utils = file_get_contents( $root . '/includes/core/class-utils.php' );
$admin = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$front_form = file_get_contents( $root . '/includes/frontend/class-club-form.php' );

$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { $failures[] = $message; }
};

$assert( false !== strpos( $loader, 'prod-club-save-dashboard-hotfix.php' ), 'Le hotfix production doit être chargé.' );
$assert( false !== strpos( $hotfix, "'ufsc_sql_save_club'" ), 'Le normaliseur doit rester limité au save club admin.' );
$assert( false !== strpos( $hotfix, "'number' === \$type && '' === \$value" ), 'Les nombres vides doivent être retirés avant MySQL strict.' );
$assert( false !== strpos( $hotfix, "'date' === \$type" ), 'Les dates vides/zéro doivent être protégées.' );
$assert( false !== strpos( $hotfix, "'num_affiliation'" ), 'Le numéro d’affiliation vide doit être protégé sur une édition existante.' );
$assert( false !== strpos( $hotfix, "'rna_number'" ), 'Le RNA vide doit être protégé sur une édition existante.' );
$assert( false !== strpos( $hotfix, "'siren'" ), 'Le SIREN vide doit être protégé sur une édition existante.' );
$assert( false !== strpos( $sql, "'siren'=>array('SIREN','text')" ), 'Le SIREN doit rester un champ texte dans le modèle canonique.' );
$assert( false !== strpos( $hotfix, 'ufsc_prod_hotfix_is_scientific_numeric_identifier' ), 'La notation scientifique du SIREN doit être détectée avant sauvegarde admin.' );
$assert( false !== strpos( $hotfix, 'ufsc_prod_hotfix_normalize_plain_numeric_identifier' ), 'La normalisation SIREN doit rester une opération sur chaîne.' );
$assert( false !== strpos( $hotfix, "unset( \$_POST[ \$key ], \$_REQUEST[ \$key ] )" ), 'Un SIREN scientifique ne doit jamais écraser la valeur stockée.' );
$assert( false !== strpos( $utils, 'Le SIREN doit être saisi en chiffres, sans notation scientifique.' ), 'Les nouvelles saisies scientifiques doivent être refusées.' );
$assert( false !== strpos( $admin, "'siren' === \$k" ) && false !== strpos( $admin, 'inputmode="numeric"' ), 'Le champ SIREN admin doit rester textuel avec clavier numérique.' );
$assert( false !== strpos( $front_form, 'id="siren" name="siren" inputmode="numeric"' ), 'Le champ SIREN front doit rester textuel avec clavier numérique.' );
$assert( false === strpos( $front_form, 'type="number" id="siren"' ), 'Le SIREN front ne doit jamais devenir un input number.' );
$assert( false !== strpos( $hotfix, "SELECT statut FROM" ), 'Le statut existant doit être conservé lorsque non soumis.' );
$assert( false !== strpos( $hotfix, '$wpdb->last_error' ), 'La vraie erreur SQL doit pouvoir être journalisée sans exposition utilisateur.' );
$assert( false !== strpos( $hotfix, 'DONOTCACHEPAGE' ), 'Le portail dynamique doit désactiver le cache de page.' );
$assert( false !== strpos( $hotfix, "litespeed_control_set_nocache" ), 'Le portail doit signaler le bypass à LiteSpeed si disponible.' );

if ( $failures ) {
    foreach ( $failures as $failure ) { fwrite( STDERR, "FAIL: {$failure}\n" ); }
    exit( 1 );
}

echo "OK: production club save/dashboard hotfix static contract\n";
