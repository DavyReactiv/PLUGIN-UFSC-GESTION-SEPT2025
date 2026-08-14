<?php
/** Static regression checks which run without WordPress. */
$root = dirname( __DIR__ );
$checks = array();
$assert = static function ( $condition, $message ) use ( &$checks ) {
	$checks[] = array( (bool) $condition, $message );
};
$admin = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$clubs = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$level = file_get_contents( $root . '/inc/common/fighter-level.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$woo   = file_get_contents( $root . '/inc/woocommerce/hooks.php' );

$assert( substr_count( $admin, 'static $rendered = false' ) === 1, 'Le renderer canonique bloque tout second tableau Licences.' );
$guard_position = strpos( $admin, 'static $rendered = false' );
$form_position  = strpos( $admin, 'self::render_licence_form', $guard_position );
$assert( false !== $guard_position && false !== $form_position && $guard_position < $form_position, 'Le garde de rendu sort avant tout formulaire ou tableau parasite.' );
$assert( false !== strpos( $admin, 'Archives uniquement' ), 'Les archives restent accessibles depuis le tableau principal.' );
$assert( false !== strpos( $clubs, 'get_season_options' ) && false !== strpos( $clubs, 'Archives uniquement' ), 'La page Clubs propose le filtre saison centralisé.' );
$assert( false !== strpos( $clubs, 'Club permanent :' ) && false !== strpos( $clubs, 'Affiliation %s :' ), 'Club permanent et affiliation annuelle sont séparés.' );
$assert( false !== strpos( $clubs, 'Affiliations en attente %s' ) && false !== strpos( $clubs, 'Licences %s' ), 'Les KPI Clubs portent la saison sélectionnée.' );
$assert(
	false !== strpos( $level, "'pro'       =>" )
	&& false !== strpos( $level, "'classe_a'  =>" )
	&& false !== strpos( $level, "'classe_b'  =>" )
	&& false !== strpos( $level, "'classe_c'  =>" )
	&& false !== strpos( $level, "'assaut'    =>" )
	&& false !== strpos( $level, "'veteran'   =>" )
	&& false !== strpos( $level, "return $age < 18 ? 'assaut' : 'classe_c';" ),
	'Les niveaux PRO/A/B/C/ASSAUT/VÉTÉRAN et les défauts mineur/majeur sont validés côté serveur.'
);
$assert( false !== strpos( $level, '$allow_empty' ) && false !== strpos( $level, "'debutant' === $key" ), 'Une ancienne licence vide ou Débutant reste compatible sans migration destructive.' );
$assert( false !== strpos( $front, 'data-ufsc-fighter-level' ), 'Le formulaire front expose le niveau sportif.' );
$assert( false !== strpos( $admin, 'filter_level' ) && false !== strpos( $admin, 'ufsc_fighter_level_label' ), 'Le tableau admin filtre et affiche le niveau.' );
$assert( false !== strpos( $woo, "'fighter_level'" ) && false !== strpos( $woo, "'previous_licence_id'" ), 'Le renouvellement conserve niveau et filiation sans modifier la source.' );
$assert( false !== strpos( $admin, "END AS fighter_level" ) && false !== strpos( $admin, "'Niveau sportif'" ), 'Le véritable export admin produit le libellé du niveau.' );

$failed = 0;
foreach ( $checks as $check ) {
	list( $ok, $message ) = $check;
	echo ( $ok ? "PASS" : "FAIL" ) . ': ' . $message . PHP_EOL;
	$failed += $ok ? 0 : 1;
}
exit( $failed ? 1 : 0 );
