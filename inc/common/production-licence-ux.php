<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Production UX consolidation for licence pages.
 *
 * Keeps the existing renderers and data flows, but provides one stable navigation
 * contract, visible notices and a responsive table presentation across current,
 * historical and renewal views.
 */
function ufsc_production_licence_ux_urls() {
    $base = home_url( '/tableau-de-bord-club/' );
    $season = class_exists( 'UFSC_Season_Service' )
        ? (string) UFSC_Season_Service::get_current_season()
        : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );

    $previous = '';
    if ( preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
        $previous = sprintf( '%d-%d', (int) $matches[1] - 1, (int) $matches[1] );
    }

    return array(
        'dashboard' => $base,
        'current'   => add_query_arg(
            array(
                'ufsc_section' => 'club-licences',
                'ufsc_season'  => $season,
            ),
            $base
        ) . '#ufsc-club-licences',
        'renewal'   => add_query_arg( 'ufsc_section', 'licences-renouvellement', $base ) . '#ufsc-renouvellement',
        'add'       => add_query_arg(
            array(
                'ufsc_section' => 'club-licences',
                'ufsc_tab'     => 'add_licence',
            ),
            $base
        ) . '#ufsc-section-add_licence',
        'previous'  => $previous ? add_query_arg(
            array(
                'ufsc_section' => 'club-licences',
                'ufsc_season'  => $previous,
            ),
            $base
        ) . '#ufsc-club-licences' : $base,
        'season'    => $season,
        'previousSeason' => $previous,
    );
}

function ufsc_production_licence_ux_enqueue() {
    if ( is_admin() || ! defined( 'UFSC_CL_URL' ) ) { return; }

    $css = 'assets/css/ufsc-production-licence-ux.css';
    $js  = 'assets/js/ufsc-production-licence-ux.js';
    $css_version = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $css ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    $js_version  = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $js ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );

    wp_enqueue_style( 'ufsc-production-licence-ux', UFSC_CL_URL . $css, array( 'ufsc-front' ), $css_version );
    wp_enqueue_script( 'ufsc-production-licence-ux', UFSC_CL_URL . $js, array(), $js_version, true );
    wp_localize_script( 'ufsc-production-licence-ux', 'ufscLicenceUx', ufsc_production_licence_ux_urls() );
}
add_action( 'wp_enqueue_scripts', 'ufsc_production_licence_ux_enqueue', 1300 );

/**
 * Renewal source compatibility bridge.
 *
 * The legacy renderer still injects a validated-only SQL clause before calling
 * UFSC_Renewal_Service::can_renew(). That hides legitimate historical sources
 * in Brouillon / En attente / Non payée before the canonical service can decide.
 * On the renewal screen only, remove that obsolete validated-only predicate.
 * The canonical can_renew() service remains the final authority for club,
 * previous-season, affiliation, duplicate and allowed-status checks.
 */
function ufsc_production_expand_renewal_source_query( $query ) {
    // The global `query` filter can fire during the WordPress bootstrap, before
    // pluggable.php has defined is_user_logged_in() (for example while ACF reads
    // options or when wp-cron.php boots). Never run front-end renewal logic then.
    if ( ! function_exists( 'is_user_logged_in' ) ) {
        return $query;
    }

    if ( is_admin() || ! is_user_logged_in() || ! is_string( $query ) ) {
        return $query;
    }

    $section = isset( $_GET['ufsc_section'] ) && ! is_array( $_GET['ufsc_section'] )
        ? sanitize_key( wp_unslash( $_GET['ufsc_section'] ) )
        : '';
    if ( 'licences-renouvellement' !== $section || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return $query;
    }

    $table = (string) ufsc_get_licences_table();
    if ( '' === $table || false === strpos( $query, $table ) || false === stripos( $query, 'SELECT' ) ) {
        return $query;
    }

    // Only remove IN() predicates that clearly represent the legacy "validated" alias set.
    // Explicit filters such as Brouillon / En attente remain untouched.
    $pattern = '/\s+AND\s+(?:COALESCE\(NULLIF\(TRIM\(`statut`\),\s*\'\'\),\s*`status`\)|`statut`|`status`)\s+IN\s*\((?=[^)]*\'valide\')(?=[^)]*\'validated\')[^)]*\)/i';
    $expanded = preg_replace( $pattern, '', $query );

    return is_string( $expanded ) ? $expanded : $query;
}
add_filter( 'query', 'ufsc_production_expand_renewal_source_query', 999 );

/** Use the same visual language for WordPress admin notices on UFSC screens. */
function ufsc_production_licence_ux_admin_enqueue() {
    if ( ! defined( 'UFSC_CL_URL' ) ) { return; }
    $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( '' === $page || false === strpos( $page, 'ufsc' ) ) { return; }

    $css = 'assets/css/ufsc-production-licence-ux.css';
    $version = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $css ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    wp_enqueue_style( 'ufsc-production-licence-ux-admin', UFSC_CL_URL . $css, array(), $version );
}
add_action( 'admin_enqueue_scripts', 'ufsc_production_licence_ux_admin_enqueue', 1300 );
