<?php
/** Static safeguards for the licence portal production hotfix. */
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$hotfix = file_get_contents( $root . '/inc/common/licence-portal-production-hotfix.php' );
$failures = array();
$assert = static function( $condition, $message ) use ( &$failures ) { if ( ! $condition ) { $failures[] = $message; } };

$assert( false !== strpos( $flags, 'licence-portal-production-hotfix.php' ), 'Portal hotfix must be loaded by feature flags.' );
$assert( false !== strpos( $hotfix, "'infos_fsasptt', 'infos_asptt', 'infos_cr'" ), 'The three obsolete partner opt-ins must be targeted together.' );
$assert( false !== strpos( $hotfix, "'ufsc_club_dashboard', 'ufsc_club_licences', 'ufsc_add_licence', 'ufsc_licences'" ), 'Legacy partner opt-ins must be filtered from every club licence renderer, including the dashboard wrapper used by edit screens.' );
$assert( false !== strpos( $hotfix, 'ufsc_portal_hotfix_preserve_existing_legacy_optins' ), 'Existing legacy opt-in values must be preserved on edit.' );
$assert( false !== strpos( $hotfix, "\$_POST['ufsc_submit_action'] = 'add_to_cart'" ), 'A confirmed included reservation must restore the final intent.' );
$assert( false !== strpos( $hotfix, 'SELECT is_included' ), 'Included intent restoration must verify the persisted quota reservation.' );
$assert( false !== strpos( $hotfix, 'Quota actuel :' ) && false !== strpos( $hotfix, 'ne consomme aucune place' ), 'The final step must explain quota and draft behavior.' );
$assert( false !== strpos( $hotfix, 'Vérification avant envoi' ), 'Included flow must not be labelled as a cart step.' );
$assert( false !== strpos( $hotfix, "admin_post_ufsc_portal_logout" ), 'Secure logout endpoint must be registered.' );
$assert( false !== strpos( $hotfix, "check_admin_referer( 'ufsc_portal_logout' )" ), 'Logout endpoint must verify a nonce.' );
$assert( false !== strpos( $hotfix, 'wp_logout();' ) && false !== strpos( $hotfix, 'wp_safe_redirect' ), 'Logout endpoint must terminate the WordPress session and redirect safely.' );
$assert( false === strpos( $hotfix, 'UPDATE ' ) && false === strpos( $hotfix, 'DELETE FROM' ) && false === strpos( $hotfix, 'ALTER TABLE' ), 'Hotfix must not directly rewrite production rows or schema.' );

if ( $failures ) {
    fwrite( STDERR, "Licence portal production hotfix safeguards failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}
echo "Licence portal production hotfix safeguards: OK\n";
