<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Structural licence workflow safety layer.
 *
 * Business invariants enforced here:
 * - drafts never consume quota;
 * - a real club finalisation reserves one of the 10 included licences first;
 * - an included licence becomes en_attente immediately, never enters WooCommerce;
 * - only licences beyond the included quota remain eligible for the paid flow;
 * - historical source rows are never rewritten by a renewal display/action.
 */

function ufsc_structural_final_intent() {
    $intent = '';
    if ( isset( $_POST['ufsc_submit_action'] ) && ! is_array( $_POST['ufsc_submit_action'] ) ) {
        $intent = sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) );
    }
    if ( ! in_array( $intent, array( 'add_to_cart', 'submit_for_validation' ), true ) && isset( $_POST['ufsc_final_intent'] ) && ! is_array( $_POST['ufsc_final_intent'] ) ) {
        $intent = sanitize_key( wp_unslash( $_POST['ufsc_final_intent'] ) );
    }
    return in_array( $intent, array( 'add_to_cart', 'submit_for_validation' ), true ) ? $intent : '';
}

function ufsc_structural_get_licence( $licence_id ) {
    global $wpdb;
    $licence_id = absint( $licence_id );
    if ( $licence_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) { return null; }
    $table = ufsc_get_licences_table();
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) );
}

function ufsc_structural_season_sql( $table, $season ) {
    global $wpdb;
    $season = str_replace( '/', '-', sanitize_text_field( (string) $season ) );
    $column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $table ) : '';
    if ( ! $column && function_exists( 'ufsc_table_columns' ) ) {
        $columns = (array) ufsc_table_columns( $table );
        $column = in_array( 'season', $columns, true ) ? 'season' : ( in_array( 'saison', $columns, true ) ? 'saison' : '' );
    }
    if ( ! $column || ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) { return ''; }
    if ( 'season_end_year' === $column && preg_match( '/^(\d{4})-(\d{4})$/', $season, $m ) ) {
        return $wpdb->prepare( '`season_end_year` = %d', (int) $m[2] );
    }
    return $wpdb->prepare( "REPLACE(TRIM(`{$column}`), '/', '-') = %s", $season );
}

/**
 * Guarantee the included transition after the canonical save has persisted data.
 * This function receives a real licence ID and is intentionally idempotent.
 */
function ufsc_structural_finalize_saved_licence( $licence_id ) {
    static $running = array();
    $licence_id = absint( $licence_id );
    if ( $licence_id < 1 || isset( $running[ $licence_id ] ) || '' === ufsc_structural_final_intent() ) { return; }

    $row = ufsc_structural_get_licence( $licence_id );
    if ( ! $row ) { return; }

    $season = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    $row_season = function_exists( 'ufsc_get_licence_season_label' ) ? (string) ufsc_get_licence_season_label( $row ) : (string) ( $row->season ?? $row->saison ?? '' );
    $row_season = str_replace( '/', '-', trim( $row_season ) );
    if ( $row_season && $season && $row_season !== str_replace( '/', '-', $season ) ) {
        return; // Historical rows remain immutable.
    }

    $club_id = absint( $row->club_id ?? 0 );
    if ( $club_id < 1 || ! function_exists( 'ufsc_allocate_pack_credit' ) ) { return; }

    $running[ $licence_id ] = true;
    $role = sanitize_key( (string) ( $row->role ?? '' ) );
    $allocation = ufsc_allocate_pack_credit( $licence_id, $club_id, $season, $role );
    if ( is_wp_error( $allocation ) || empty( $allocation['included'] ) ) {
        unset( $running[ $licence_id ] );
        return; // Paid branch remains owned by the canonical WooCommerce handler.
    }

    global $wpdb;
    $table = ufsc_get_licences_table();
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();

    // Some legacy rows have NULL is_included; force the authoritative reservation.
    if ( in_array( 'is_included', $columns, true ) ) {
        $wpdb->update( $table, array( 'is_included' => 1 ), array( 'id' => $licence_id, 'club_id' => $club_id ), array( '%d' ), array( '%d', '%d' ) );
    }
    if ( class_exists( 'UFSC_Licence_Status' ) ) {
        UFSC_Licence_Status::update_status_columns( $table, array( 'id' => $licence_id, 'club_id' => $club_id ), 'en_attente', array( '%d', '%d' ) );
    } elseif ( in_array( 'statut', $columns, true ) ) {
        $wpdb->update( $table, array( 'statut' => 'en_attente' ), array( 'id' => $licence_id, 'club_id' => $club_id ), array( '%s' ), array( '%d', '%d' ) );
    }
    if ( in_array( 'payment_status', $columns, true ) ) {
        $wpdb->update( $table, array( 'payment_status' => 'included' ), array( 'id' => $licence_id, 'club_id' => $club_id ), array( '%s' ), array( '%d', '%d' ) );
    }
    if ( in_array( 'submitted_at', $columns, true ) ) {
        $current = (string) $wpdb->get_var( $wpdb->prepare( "SELECT submitted_at FROM `{$table}` WHERE id=%d", $licence_id ) );
        if ( '' === trim( $current ) || '0000-00-00 00:00:00' === $current ) {
            $wpdb->update( $table, array( 'submitted_at' => current_time( 'mysql' ) ), array( 'id' => $licence_id ), array( '%s' ), array( '%d' ) );
        }
    }
    if ( function_exists( 'ufsc_journey_record_submission' ) ) {
        ufsc_journey_record_submission( $licence_id, $club_id, $season, 'club_included_structural' );
    }
    unset( $running[ $licence_id ] );
}

/** Creation hook provides the real new licence ID. */
function ufsc_structural_finalize_created_licence( $licence_id, $club_id = 0 ) {
    unset( $club_id );
    ufsc_structural_finalize_saved_licence( $licence_id );
}
add_action( 'ufsc_licence_created', 'ufsc_structural_finalize_created_licence', 1, 2 );

/** Update hook historically provides club_id, so resolve licence_id from the authenticated form request. */
function ufsc_structural_finalize_updated_request( $club_id ) {
    unset( $club_id );
    $licence_id = isset( $_POST['licence_id'] ) && ! is_array( $_POST['licence_id'] ) ? absint( wp_unslash( $_POST['licence_id'] ) ) : 0;
    if ( $licence_id > 0 ) { ufsc_structural_finalize_saved_licence( $licence_id ); }
}
add_action( 'ufsc_licence_updated', 'ufsc_structural_finalize_updated_request', 1, 1 );

/** Preserve the archives route even when a legacy GET form omitted ufsc_section. */
function ufsc_structural_preserve_portal_route() {
    if ( isset( $_GET['ufsc_archive_season'] ) && ! isset( $_GET['ufsc_section'] ) ) {
        $_GET['ufsc_section'] = 'licences-archives';
        $_REQUEST['ufsc_section'] = 'licences-archives';
    }
}
add_action( 'wp', 'ufsc_structural_preserve_portal_route', 1 );

/** Replace the old broad pending notice with a season-scoped, database-consistent count. */
function ufsc_structural_replace_admin_pending_notice() {
    remove_action( 'admin_notices', 'ufsc_journey_admin_pending_notice' );
}
add_action( 'admin_init', 'ufsc_structural_replace_admin_pending_notice', 50 );

function ufsc_structural_admin_pending_notice() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) || ! function_exists( 'ufsc_get_licences_table' ) ) { return; }
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( ! in_array( $page, array( 'ufsc_lc_licences', 'ufsc-gestion-licences', 'ufsc-licences', 'ufsc-sql-licences', 'ufsc-sql-licenses' ), true ) ) { return; }

    global $wpdb;
    $table = ufsc_get_licences_table();
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
    $status_col = in_array( 'statut', $columns, true ) ? 'statut' : ( in_array( 'status', $columns, true ) ? 'status' : '' );
    if ( ! $status_col ) { return; }
    $season = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : '';
    $season_sql = ufsc_structural_season_sql( $table, $season );
    if ( '' === $season_sql ) { return; }
    $deleted_sql = in_array( 'deleted_at', $columns, true ) ? " AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')" : '';
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE `{$status_col}`='en_attente' AND {$season_sql}{$deleted_sql}" );
    if ( $count < 1 ) { return; }

    echo '<div class="notice notice-warning"><p><strong>' . esc_html( sprintf( _n( '%d licence est en attente de validation UFSC.', '%d licences sont en attente de validation UFSC.', $count, 'ufsc-clubs' ), $count ) ) . '</strong> ' . esc_html( sprintf( __( 'Saison %s — ces dossiers ne sont plus des brouillons.', 'ufsc-clubs' ), $season ) ) . '</p></div>';
}
add_action( 'admin_notices', 'ufsc_structural_admin_pending_notice', 21 );

/** Front assets are scoped to UFSC components only. */
function ufsc_structural_enqueue_front_assets() {
    if ( is_admin() || ! defined( 'UFSC_CL_URL' ) ) { return; }
    $version = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/css/ufsc-structural-portal.css' ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    wp_enqueue_style( 'ufsc-structural-portal', UFSC_CL_URL . 'assets/css/ufsc-structural-portal.css', array( 'ufsc-front' ), $version );
    wp_enqueue_script( 'ufsc-structural-portal', UFSC_CL_URL . 'assets/js/ufsc-structural-portal.js', array(), $version, true );
    wp_localize_script( 'ufsc-structural-portal', 'ufscStructuralPortal', array(
        'licencesUrl' => add_query_arg( 'ufsc_section', 'club-licences', home_url( '/tableau-de-bord-club/' ) ) . '#ufsc-current-licences',
        'renewalUrl'  => add_query_arg( 'ufsc_section', 'licences-renouvellement', home_url( '/tableau-de-bord-club/' ) ) . '#ufsc-renouvellement',
    ) );
}
add_action( 'wp_enqueue_scripts', 'ufsc_structural_enqueue_front_assets', 120 );
