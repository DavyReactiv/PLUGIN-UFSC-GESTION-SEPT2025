<?php
$root      = dirname( __DIR__ );
$loader    = file_get_contents( $root . '/inc/common/readonly-access-denied-messages.php' );
$isolation = file_get_contents( $root . '/inc/common/readonly-wordpress-admin-isolation.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $loader, 'readonly-wordpress-admin-isolation.php' ), 'read-only runtime must load the WordPress admin isolation layer' );
$assert( false !== strpos( $isolation, "add_filter( 'user_has_cap', 'ufsc_readonly_access_strip_wordpress_capabilities'" ), 'managed read-only accounts must enforce capability isolation server-side' );
$assert( false !== strpos( $isolation, 'ufsc_readonly_access_is_user( $user->ID )' ), 'isolation must target only managed federation read-only users' );
$assert( false !== strpos( $isolation, "'manage_options'" ), 'WordPress settings capability must be denied' );
$assert( false !== strpos( $isolation, "'edit_posts'" ), 'WordPress article editing capability must be denied' );
$assert( false !== strpos( $isolation, "'upload_files'" ), 'WordPress media upload capability must be denied' );
$assert( false !== strpos( $isolation, "'activate_plugins'" ), 'WordPress plugin administration capability must be denied' );
$assert( false !== strpos( $isolation, "'list_users'" ), 'WordPress user administration capability must be denied' );
$assert( false !== strpos( $isolation, "'manage_woocommerce'" ), 'WooCommerce administration capability must be denied' );
$assert( false !== strpos( $isolation, 'Deliberately preserve native `read` plus the UFSC read capabilities.' ), 'native read access must remain available for /wp-admin/' );
$assert( false !== strpos( $isolation, 'ne donnez pas le rôle WordPress « Administrateur »' ), 'assignment screen must explain that UFSC responsible accounts are not WordPress administrators' );
$assert( false === strpos( $isolation, 'UFSC_Unified_Handlers::' ), 'isolation must not call licence mutation handlers' );
$assert( false === strpos( $isolation, 'WC()->cart' ), 'isolation must not touch cart logic' );
$assert( false === strpos( $isolation, '$wpdb->' ), 'isolation must not mutate business data' );

echo "Read-only WordPress admin isolation safeguards OK\n";
