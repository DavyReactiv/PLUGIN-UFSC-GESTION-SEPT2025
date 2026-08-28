<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Prevent the Accès responsables page from rendering twice.
 *
 * The base module registers the original callback, then the hardening layer
 * re-registers the same submenu slug with an enriched callback. WordPress keeps
 * both page actions unless the first one is removed explicitly. This small
 * presentation-only compatibility layer removes only the original renderer.
 */
function ufsc_readonly_access_deduplicate_admin_page_callback() {
    if ( ! is_admin() || ! function_exists( 'get_plugin_page_hookname' ) ) {
        return;
    }

    $hook = get_plugin_page_hookname( 'ufsc-readonly-access', 'ufsc-dashboard' );
    if ( ! $hook ) {
        return;
    }

    remove_action( $hook, 'ufsc_readonly_access_render_admin_page' );
}
add_action( 'admin_menu', 'ufsc_readonly_access_deduplicate_admin_page_callback', 33 );
