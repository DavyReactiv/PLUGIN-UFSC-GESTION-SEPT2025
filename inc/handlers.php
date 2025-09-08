<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dedicated save handlers for UFSC Gestion.
 */

/**
 * Register form save handlers.
 */
function ufsc_register_save_handlers() {
    add_action( 'admin_post_ufsc_save_club', 'ufsc_handle_save_club' );
    add_action( 'admin_post_nopriv_ufsc_save_club', 'ufsc_handle_save_club' );

    add_action( 'admin_post_ufsc_save_licence', 'ufsc_handle_save_licence' );
    add_action( 'admin_post_nopriv_ufsc_save_licence', 'ufsc_handle_save_licence' );
}
add_action( 'init', 'ufsc_register_save_handlers', 20 );

/**
 * Handle club save request.
 */
function ufsc_handle_save_club() {
    if ( ! isset( $_POST['ufsc_club_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ufsc_club_nonce'] ) ), 'ufsc_save_club' ) ) {
        wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
    }

    if ( ! is_user_logged_in() ) {
        wp_die( __( 'Vous devez être connecté', 'ufsc-clubs' ) );
    }

    $club_id      = isset( $_POST['club_id'] ) ? intval( $_POST['club_id'] ) : 0;
    $user_id      = get_current_user_id();
    $user_club_id = ufsc_get_user_club_id( $user_id );

    if ( ! current_user_can( 'ufsc_manage_clubs' ) && $user_club_id !== $club_id ) {
        wp_die( __( 'Permissions insuffisantes', 'ufsc-clubs' ) );
    }

    $fields = array(
        'nom'   => 'sanitize_text_field',
        'email' => 'sanitize_email',
    );

    $data   = array();
    $format = array();

    foreach ( $fields as $key => $sanitize_callback ) {
        if ( isset( $_POST[ $key ] ) ) {
            $value        = call_user_func( $sanitize_callback, wp_unslash( $_POST[ $key ] ) );
            $data[ $key ] = $value;
            $format[]     = '%s';
        }
    }

    if ( empty( $data ) ) {
        wp_die( __( 'Aucune donnée à enregistrer', 'ufsc-clubs' ) );
    }

    global $wpdb;
    $table = ufsc_get_clubs_table();

    if ( $club_id > 0 ) {
        $result = $wpdb->update( $table, $data, array( 'id' => $club_id ), $format, array( '%d' ) );
    } else {
        $result  = $wpdb->insert( $table, $data, $format );
        $club_id = $result ? $wpdb->insert_id : 0;
    }

    $success = ( false !== $result && '' === $wpdb->last_error );

    if ( ! $success ) {
        error_log( sprintf( '[UFSC][update_fail] %s | %s', $wpdb->last_error, $wpdb->last_query ) );
    }

    $redirect = add_query_arg( $success ? 'saved' : 'error', 1, wp_get_referer() );
    wp_safe_redirect( $redirect );
    exit;
}

/**
 * Handle licence save request.
 */
function ufsc_handle_save_licence() {
    if ( ! isset( $_POST['ufsc_licence_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ufsc_licence_nonce'] ) ), 'ufsc_save_licence' ) ) {
        wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
    }

    if ( ! is_user_logged_in() ) {
        wp_die( __( 'Vous devez être connecté', 'ufsc-clubs' ) );
    }

    $licence_id   = isset( $_POST['licence_id'] ) ? intval( $_POST['licence_id'] ) : 0;
    $club_id      = isset( $_POST['club_id'] ) ? intval( $_POST['club_id'] ) : ufsc_get_user_club_id( get_current_user_id() );
    $user_id      = get_current_user_id();
    $user_club_id = ufsc_get_user_club_id( $user_id );

    if ( ! current_user_can( 'ufsc_manage_clubs' ) && $user_club_id !== $club_id ) {
        wp_die( __( 'Permissions insuffisantes', 'ufsc-clubs' ) );
    }

    $fields = array(
        'club_id'   => 'intval',
        'nom'       => 'sanitize_text_field',
        'prenom'    => 'sanitize_text_field',
        'email'     => 'sanitize_email',
        'categorie' => 'sanitize_text_field',
    );

    $data   = array();
    $format = array();

    foreach ( $fields as $key => $sanitize_callback ) {
        if ( isset( $_POST[ $key ] ) ) {
            $value        = call_user_func( $sanitize_callback, wp_unslash( $_POST[ $key ] ) );
            $data[ $key ] = $value;
            $format[]     = ( 'intval' === $sanitize_callback ) ? '%d' : '%s';
        }
    }

    if ( empty( $data ) ) {
        wp_die( __( 'Aucune donnée à enregistrer', 'ufsc-clubs' ) );
    }

    global $wpdb;
    $table = ufsc_get_licences_table();

    if ( $licence_id > 0 ) {
        $result = $wpdb->update( $table, $data, array( 'id' => $licence_id ), $format, array( '%d' ) );
    } else {
        $result     = $wpdb->insert( $table, $data, $format );
        $licence_id = $result ? $wpdb->insert_id : 0;
    }

    $success = ( false !== $result && '' === $wpdb->last_error );

    if ( ! $success ) {
        error_log( sprintf( '[UFSC][update_fail] %s | %s', $wpdb->last_error, $wpdb->last_query ) );
    }

    $redirect = add_query_arg( $success ? 'saved' : 'error', 1, wp_get_referer() );
    wp_safe_redirect( $redirect );
    exit;
}
