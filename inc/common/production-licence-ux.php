<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$ufsc_production_dbdelta_compat = __DIR__ . '/production-dbdelta-compat.php';
if ( file_exists( $ufsc_production_dbdelta_compat ) ) {
    require_once $ufsc_production_dbdelta_compat;
}
unset( $ufsc_production_dbdelta_compat );

/** Return one request-scoped, pagination-independent renewal state summary. */
function ufsc_production_renewal_state_counts() {
    static $cached = null;
    if ( null !== $cached ) { return $cached; }
    $cached = array( 'renewable' => 0, 'renewed' => 0, 'pending' => 0, 'payable' => 0, 'blocked' => 0, 'total' => 0 );
    if ( ! is_user_logged_in() || ! function_exists( 'ufsc_get_user_club_id' ) || ! function_exists( 'ufsc_get_licences_table' ) ) { return $cached; }
    $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
    if ( $club_id < 1 ) { return $cached; }
    $target = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $target, $matches ) ) { return $cached; }
    $source = sprintf( '%d-%d', (int) $matches[1] - 1, (int) $matches[1] );
    global $wpdb;
    $table = (string) ufsc_get_licences_table();
    if ( '' === $table ) { return $cached; }
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE club_id = %d", $club_id ) );
    foreach ( (array) $rows as $row ) {
        if ( ! is_object( $row ) ) { continue; }
        if ( function_exists( 'ufsc_get_licence_season_label' ) ) { $row_season = (string) ufsc_get_licence_season_label( $row ); }
        elseif ( function_exists( 'ufsc_get_licence_season' ) ) { $row_season = (string) ufsc_get_licence_season( $row ); }
        else { $row_season = (string) ( $row->season ?? ( $row->saison ?? '' ) ); }
        $row_season = trim( str_replace( '/', '-', $row_season ) );
        if ( $source !== $row_season ) { continue; }
        $cached['total']++;
        if ( function_exists( 'ufsc_get_licence_season_context_status' ) ) {
            $context = (array) ufsc_get_licence_season_context_status( $row, $target );
            $state = sanitize_key( (string) ( $context['renewal_state'] ?? '' ) );
            if ( ! isset( $cached[ $state ] ) ) { $state = ! empty( $context['renewal_allowed'] ) ? 'renewable' : 'blocked'; }
        } elseif ( is_callable( array( 'UFSC_Renewal_Service', 'can_renew' ) ) && true === UFSC_Renewal_Service::can_renew( $row, $club_id, $target ) ) { $state = 'renewable'; }
        else { $state = 'blocked'; }
        $cached[ $state ]++;
    }
    return $cached;
}

/** Safe read-only metadata for the visible current-season list. */
function ufsc_production_current_licence_meta() {
    static $cached = null;
    if ( null !== $cached ) { return $cached; }
    $cached = array();
    if ( ! is_user_logged_in() || ! function_exists( 'ufsc_get_user_club_id' ) || ! function_exists( 'ufsc_get_licences_table' ) ) { return $cached; }
    $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
    $season = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    if ( $club_id < 1 || '' === $season ) { return $cached; }
    global $wpdb;
    $table = (string) ufsc_get_licences_table();
    if ( '' === $table ) { return $cached; }
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE club_id = %d", $club_id ) );
    foreach ( (array) $rows as $row ) {
        if ( ! is_object( $row ) ) { continue; }
        $row_season = function_exists( 'ufsc_get_licence_season_label' ) ? (string) ufsc_get_licence_season_label( $row ) : (string) ( $row->season ?? ( $row->saison ?? '' ) );
        if ( $season !== trim( str_replace( '/', '-', $row_season ) ) ) { continue; }
        $birth = trim( (string) ( $row->date_naissance ?? '' ) );
        $age_label = '';
        if ( '' !== $birth ) {
            try {
                $age = ( new DateTimeImmutable( $birth ) )->diff( new DateTimeImmutable( current_time( 'Y-m-d' ) ) )->y;
                $age_label = $age < 18 ? __( 'Mineur', 'ufsc-clubs' ) : __( 'Majeur', 'ufsc-clubs' );
            } catch ( Exception $e ) { $age_label = ''; }
        }
        $cached[ absint( $row->id ) ] = array(
            'birthDate' => $birth,
            'ageCategory' => $age_label,
            'gender' => sanitize_text_field( (string) ( $row->sexe ?? '' ) ),
            'practice' => ! empty( $row->competition ) ? __( 'Compétition', 'ufsc-clubs' ) : __( 'Loisir', 'ufsc-clubs' ),
            'level' => sanitize_text_field( (string) ( $row->fighter_level ?? '' ) ),
        );
    }
    return $cached;
}

function ufsc_production_licence_ux_urls() {
    $base = home_url( '/tableau-de-bord-club/' );
    $season = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    $previous = '';
    if ( preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) { $previous = sprintf( '%d-%d', (int) $matches[1] - 1, (int) $matches[1] ); }
    return array(
        'dashboard' => $base,
        'current' => add_query_arg( array( 'ufsc_section' => 'club-licences', 'ufsc_season' => $season ), $base ) . '#ufsc-club-licences',
        'renewal' => add_query_arg( 'ufsc_section', 'licences-renouvellement', $base ) . '#ufsc-renouvellement',
        'add' => add_query_arg( array( 'ufsc_section' => 'club-licences', 'ufsc_tab' => 'add_licence' ), $base ) . '#ufsc-section-add_licence',
        'previous' => $previous ? add_query_arg( array( 'ufsc_section' => 'club-licences', 'ufsc_season' => $previous ), $base ) . '#ufsc-club-licences' : $base,
        'season' => $season,
        'previousSeason' => $previous,
        'renewalCounts'  => ufsc_production_renewal_state_counts(),
        'licenceMeta' => ufsc_production_current_licence_meta(),
    );
}

function ufsc_production_licence_ux_enqueue() {
    if ( is_admin() || ! defined( 'UFSC_CL_URL' ) ) { return; }
    $base_css = 'assets/css/ufsc-front.css'; $css = 'assets/css/ufsc-production-licence-ux.css'; $js = 'assets/js/ufsc-production-licence-ux.js';
    $base_css_version = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $base_css ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    $css_version = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $css ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    $js_version = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $js ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    if ( ! wp_style_is( 'ufsc-front', 'registered' ) ) { wp_register_style( 'ufsc-front', UFSC_CL_URL . $base_css, array(), $base_css_version ); }
    wp_enqueue_style( 'ufsc-production-licence-ux', UFSC_CL_URL . $css, array( 'ufsc-front' ), $css_version );
    wp_enqueue_script( 'ufsc-production-licence-ux', UFSC_CL_URL . $js, array(), $js_version, true );
    wp_localize_script( 'ufsc-production-licence-ux', 'ufscLicenceUx', ufsc_production_licence_ux_urls() );
}
add_action( 'wp_enqueue_scripts', 'ufsc_production_licence_ux_enqueue', 1300 );

function ufsc_production_register_renewal_query_filter() {
    if ( is_admin() || ! is_user_logged_in() ) { return; }
    $section = isset( $_GET['ufsc_section'] ) && ! is_array( $_GET['ufsc_section'] ) ? sanitize_key( wp_unslash( $_GET['ufsc_section'] ) ) : '';
    if ( 'licences-renouvellement' !== $section || ! function_exists( 'ufsc_get_licences_table' ) ) { return; }
    $table = (string) ufsc_get_licences_table(); if ( '' === $table ) { return; }
    $GLOBALS['ufsc_production_renewal_licences_table'] = $table;
    add_filter( 'query', 'ufsc_production_expand_renewal_source_query', 999 );
}
add_action( 'wp_loaded', 'ufsc_production_register_renewal_query_filter', 20 );

function ufsc_production_expand_renewal_source_query( $query ) {
    if ( ! is_string( $query ) ) { return $query; }
    $table = isset( $GLOBALS['ufsc_production_renewal_licences_table'] ) ? (string) $GLOBALS['ufsc_production_renewal_licences_table'] : '';
    if ( '' === $table || false === strpos( $query, $table ) || false === stripos( $query, 'SELECT' ) ) { return $query; }
    static $running = false; if ( $running ) { return $query; } $running = true;
    $pattern = '/\s+AND\s+(?:COALESCE\(NULLIF\(TRIM\(`statut`\),\s*\'\'\),\s*`status`\)|`statut`|`status`)\s+IN\s*\((?=[^)]*\'valide\')(?=[^)]*\'validated\')[^)]*\)/i';
    $expanded = preg_replace( $pattern, '', $query ); $running = false;
    return is_string( $expanded ) ? $expanded : $query;
}

function ufsc_production_redirect_included_direct_renewal( $location, $status ) {
    unset( $status );
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
    $intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) ) : '';
    if ( 'POST' !== $method || 'ufsc_bulk_renew_licences' !== $action || 'add_to_cart' !== $intent ) { return $location; }
    $ids = array();
    foreach ( array( 'ufsc_renew_ids', 'source_ids', 'renew_licence_ids' ) as $key ) {
        if ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) { $ids = array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST[ $key ] ) ) ) ) ); break; }
    }
    if ( 1 !== count( $ids ) || ! function_exists( 'ufsc_get_renewed_licence_marker' ) || ! function_exists( 'ufsc_get_licences_table' ) ) { return $location; }
    $season = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    $target_id = absint( ufsc_get_renewed_licence_marker( $ids[0], $season ) );
    $club_id = isset( $_POST['ufsc_club_id'] ) ? absint( wp_unslash( $_POST['ufsc_club_id'] ) ) : 0;
    if ( $target_id < 1 || $club_id < 1 ) { return $location; }
    global $wpdb; $table = ufsc_get_licences_table();
    $target = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND club_id = %d LIMIT 1", $target_id, $club_id ) );
    if ( ! $target ) { return $location; }
    $payment_status = sanitize_key( (string) ( $target->payment_status ?? '' ) );
    $is_included = ! empty( $target->is_included ) || in_array( $payment_status, array( 'included', 'incluse', 'pack', 'included_pack' ), true );
    if ( ! $is_included ) { return $location; }
    $urls = ufsc_production_licence_ux_urls();
    $target_url = add_query_arg( array( 'ufsc_section' => 'club-licences', 'ufsc_season' => $season, 'ufsc_message' => 'renewal_included' ), $urls['dashboard'] );
    return $target_url . '#ufsc-current-licences';
}
add_filter( 'wp_redirect', 'ufsc_production_redirect_included_direct_renewal', 999, 2 );

function ufsc_production_licence_ux_admin_enqueue() {
    if ( ! defined( 'UFSC_CL_URL' ) ) { return; }
    $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( '' === $page || false === strpos( $page, 'ufsc' ) ) { return; }
    $css = 'assets/css/ufsc-production-licence-ux.css';
    $version = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $css ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    wp_enqueue_style( 'ufsc-production-licence-ux-admin', UFSC_CL_URL . $css, array(), $version );
}
add_action( 'admin_enqueue_scripts', 'ufsc_production_licence_ux_admin_enqueue', 1300 );
