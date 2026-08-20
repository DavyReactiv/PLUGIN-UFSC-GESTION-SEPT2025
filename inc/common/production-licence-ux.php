<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$ufsc_production_dbdelta_compat = __DIR__ . '/production-dbdelta-compat.php';
if ( file_exists( $ufsc_production_dbdelta_compat ) ) {
    require_once $ufsc_production_dbdelta_compat;
}
unset( $ufsc_production_dbdelta_compat );

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

    $base_css = 'assets/css/ufsc-front.css';
    $css = 'assets/css/ufsc-production-licence-ux.css';
    $js  = 'assets/js/ufsc-production-licence-ux.js';
    $base_css_version = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $base_css ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    $css_version = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $css ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    $js_version  = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $js ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );

    // The dashboard renderer may enqueue ufsc-front after wp_enqueue_scripts has
    // already run. Register the dependency here so WordPress can resolve and
    // print the production UX stylesheet reliably on the first page load.
    if ( ! wp_style_is( 'ufsc-front', 'registered' ) ) {
        wp_register_style( 'ufsc-front', UFSC_CL_URL . $base_css, array(), $base_css_version );
    }

    wp_enqueue_style( 'ufsc-production-licence-ux', UFSC_CL_URL . $css, array( 'ufsc-front' ), $css_version );
    wp_enqueue_script( 'ufsc-production-licence-ux', UFSC_CL_URL . $js, array(), $js_version, true );
    wp_localize_script( 'ufsc-production-licence-ux', 'ufscLicenceUx', ufsc_production_licence_ux_urls() );
}
add_action( 'wp_enqueue_scripts', 'ufsc_production_licence_ux_enqueue', 1300 );

/**
 * Register the renewal SQL compatibility bridge only after WordPress is fully
 * loaded and only for the authenticated front-end renewal request.
 *
 * This is deliberately separate from the `query` filter callback. Calling
 * is_user_logged_in(), get_option(), UFSC_SQL::get_settings() or any helper that
 * can touch the database from inside the global `query` filter can recursively
 * invoke wpdb and exhaust PHP memory.
 */
function ufsc_production_register_renewal_query_filter() {
    if ( is_admin() || ! is_user_logged_in() ) {
        return;
    }

    $section = isset( $_GET['ufsc_section'] ) && ! is_array( $_GET['ufsc_section'] )
        ? sanitize_key( wp_unslash( $_GET['ufsc_section'] ) )
        : '';

    if ( 'licences-renouvellement' !== $section || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return;
    }

    // Resolve once, outside the global SQL filter. The helper may read options.
    $table = (string) ufsc_get_licences_table();
    if ( '' === $table ) {
        return;
    }

    $GLOBALS['ufsc_production_renewal_licences_table'] = $table;
    add_filter( 'query', 'ufsc_production_expand_renewal_source_query', 999 );
}
add_action( 'wp_loaded', 'ufsc_production_register_renewal_query_filter', 20 );

/**
 * Renewal source compatibility bridge.
 *
 * The legacy renderer still injects a validated-only SQL clause before calling
 * UFSC_Renewal_Service::can_renew(). That hides legitimate historical sources
 * in Brouillon / En attente / Non payée before the canonical service can decide.
 * On the renewal screen only, remove that obsolete validated-only predicate.
 * The canonical can_renew() service remains the final authority for club,
 * previous-season, affiliation, duplicate and allowed-status checks.
 *
 * IMPORTANT: this callback must never call WordPress helpers that can query the
 * database. It runs inside wpdb::query().
 */
function ufsc_production_expand_renewal_source_query( $query ) {
    if ( ! is_string( $query ) ) {
        return $query;
    }

    $table = isset( $GLOBALS['ufsc_production_renewal_licences_table'] )
        ? (string) $GLOBALS['ufsc_production_renewal_licences_table']
        : '';

    if ( '' === $table || false === strpos( $query, $table ) || false === stripos( $query, 'SELECT' ) ) {
        return $query;
    }

    // Extra recursion guard: the callback itself currently performs no SQL, but
    // this prevents future edits from turning the global filter into a loop.
    static $running = false;
    if ( $running ) {
        return $query;
    }

    $running = true;

    // Only remove IN() predicates that clearly represent the legacy "validated" alias set.
    // Explicit filters such as Brouillon / En attente remain untouched.
    $pattern = '/\s+AND\s+(?:COALESCE\(NULLIF\(TRIM\(`statut`\),\s*\'\'\),\s*`status`\)|`statut`|`status`)\s+IN\s*\((?=[^)]*\'valide\')(?=[^)]*\'validated\')[^)]*\)/i';
    $expanded = preg_replace( $pattern, '', $query );

    $running = false;

    return is_string( $expanded ) ? $expanded : $query;
}

/**
 * After a successful direct renewal covered by the affiliation quota, leave the
 * verification assistant and return to the current-season licence list.
 *
 * The canonical handler already completed ownership, nonce, season, duplicate,
 * profile and quota checks before WordPress reaches wp_redirect. We additionally
 * require a real renewal marker and an included target row, so a skipped or paid
 * renewal can never be turned into a false success redirect here.
 */
function ufsc_production_redirect_included_direct_renewal( $location, $status ) {
    unset( $status );

    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
    $intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) ) : '';

    if ( 'POST' !== $method || 'ufsc_bulk_renew_licences' !== $action || 'add_to_cart' !== $intent ) {
        return $location;
    }

    $ids = array();
    foreach ( array( 'ufsc_renew_ids', 'source_ids', 'renew_licence_ids' ) as $key ) {
        if ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) {
            $ids = array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST[ $key ] ) ) ) ) );
            break;
        }
    }
    if ( 1 !== count( $ids ) || ! function_exists( 'ufsc_get_renewed_licence_marker' ) || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return $location;
    }

    $season = class_exists( 'UFSC_Season_Service' )
        ? (string) UFSC_Season_Service::get_current_season()
        : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    $target_id = absint( ufsc_get_renewed_licence_marker( $ids[0], $season ) );
    $club_id = isset( $_POST['ufsc_club_id'] ) ? absint( wp_unslash( $_POST['ufsc_club_id'] ) ) : 0;
    if ( $target_id < 1 || $club_id < 1 ) {
        return $location;
    }

    global $wpdb;
    $table = ufsc_get_licences_table();
    $target = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND club_id = %d LIMIT 1", $target_id, $club_id ) );
    if ( ! $target ) {
        return $location;
    }

    $payment_status = sanitize_key( (string) ( $target->payment_status ?? '' ) );
    $is_included = ! empty( $target->is_included ) || in_array( $payment_status, array( 'included', 'incluse', 'pack', 'included_pack' ), true );
    if ( ! $is_included ) {
        return $location;
    }

    $urls = ufsc_production_licence_ux_urls();
    $target_url = add_query_arg(
        array(
            'ufsc_section' => 'club-licences',
            'ufsc_season'  => $season,
            'ufsc_message' => 'renewal_included',
        ),
        $urls['dashboard']
    );

    return $target_url . '#ufsc-current-licences';
}
add_filter( 'wp_redirect', 'ufsc_production_redirect_included_direct_renewal', 999, 2 );

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
