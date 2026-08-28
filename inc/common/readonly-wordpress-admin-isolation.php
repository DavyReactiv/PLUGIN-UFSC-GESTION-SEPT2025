<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Keep managed federation read-only accounts inside the UFSC back office only.
 *
 * These users are not WordPress administrators. They may enter /wp-admin/ with
 * their UFSC read capabilities, but native WordPress content/settings/user/plugin
 * capabilities are explicitly denied, even if an older non-admin role still
 * grants one of them.
 *
 * This layer never touches clubs, licences, affiliations, quota, seasons,
 * WooCommerce carts/orders or historical business data.
 */
function ufsc_readonly_access_strip_wordpress_capabilities( $allcaps, $caps, $args, $user ) {
    unset( $caps, $args );

    if ( ! $user instanceof WP_User ) {
        return $allcaps;
    }

    if ( ! function_exists( 'ufsc_readonly_access_is_user' ) || ! ufsc_readonly_access_is_user( $user->ID ) ) {
        return $allcaps;
    }

    $blocked = array(
        // WordPress content/media.
        'edit_posts',
        'edit_others_posts',
        'edit_published_posts',
        'publish_posts',
        'delete_posts',
        'delete_others_posts',
        'delete_published_posts',
        'edit_pages',
        'edit_others_pages',
        'edit_published_pages',
        'publish_pages',
        'delete_pages',
        'delete_others_pages',
        'delete_published_pages',
        'upload_files',
        'manage_categories',
        'moderate_comments',
        'manage_links',
        'unfiltered_html',

        // WordPress administration.
        'manage_options',
        'edit_theme_options',
        'switch_themes',
        'edit_themes',
        'install_themes',
        'update_themes',
        'delete_themes',
        'activate_plugins',
        'edit_plugins',
        'install_plugins',
        'update_plugins',
        'delete_plugins',
        'update_core',
        'list_users',
        'create_users',
        'edit_users',
        'delete_users',
        'promote_users',
        'remove_users',
        'import',
        'export',

        // WooCommerce/accounting surfaces.
        'manage_woocommerce',
        'view_woocommerce_reports',
        'edit_shop_orders',
        'edit_others_shop_orders',
        'publish_shop_orders',
        'delete_shop_orders',
    );

    foreach ( $blocked as $capability ) {
        $allcaps[ $capability ] = false;
    }

    // Deliberately preserve native `read` plus the UFSC read capabilities.
    return $allcaps;
}
add_filter( 'user_has_cap', 'ufsc_readonly_access_strip_wordpress_capabilities', 1000001, 4 );

/**
 * Administrator guidance on the access-assignment page.
 */
function ufsc_readonly_access_wordpress_role_notice() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( 'ufsc-readonly-access' !== $page ) {
        return;
    }

    echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Important :', 'ufsc-clubs' ) . '</strong> ';
    echo esc_html__( 'ne donnez pas le rôle WordPress « Administrateur » à un responsable UFSC. Créez un compte WordPress standard, puis attribuez ici « Responsable de ligue – Consultation » ou « Responsable national – Consultation ». Le compte pourra entrer dans /wp-admin/ mais restera limité aux écrans UFSC autorisés.', 'ufsc-clubs' );
    echo '</p></div>';
}
add_action( 'admin_notices', 'ufsc_readonly_access_wordpress_role_notice', 20 );
