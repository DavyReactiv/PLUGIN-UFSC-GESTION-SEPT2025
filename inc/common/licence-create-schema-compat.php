<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creation-only compatibility for optional unique licence identifiers.
 *
 * Historical schemas may carry a UNIQUE index on numero_licence_delegataire.
 * A new licence without a delegated number must therefore omit that field so
 * MySQL stores the nullable default instead of an empty string. Existing licence
 * updates keep their current behaviour so disabling the option can still clear a
 * previously stored delegated number.
 */

/** Return true only for an authenticated new-licence admin-post request. */
function ufsc_production_is_new_licence_write_request() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
    if ( 'POST' !== $method ) {
        return false;
    }

    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';
    if ( ! in_array( $action, array( 'ufsc_add_licence', 'ufsc_save_licence' ), true ) ) {
        return false;
    }

    $licence_id = isset( $_POST['licence_id'] ) && ! is_array( $_POST['licence_id'] )
        ? absint( wp_unslash( $_POST['licence_id'] ) )
        : 0;

    return 0 === $licence_id;
}

/**
 * Omit a missing delegated licence number from NEW inserts only.
 *
 * The unified handler builds an empty string for the unchecked optional field.
 * Keeping that empty value in a UNIQUE column recreates the duplicate-key issue
 * that the schema preflight was designed to eliminate.
 *
 * @param array $fields Canonical licence field map.
 * @return array
 */
function ufsc_production_new_licence_optional_identifier_fields( $fields ) {
    if ( ! is_array( $fields ) || ! ufsc_production_is_new_licence_write_request() ) {
        return $fields;
    }

    $delegated_enabled = ! empty( $_POST['licence_delegataire'] );
    $delegated_number  = isset( $_POST['numero_licence_delegataire'] ) && ! is_array( $_POST['numero_licence_delegataire'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['numero_licence_delegataire'] ) ) )
        : '';

    if ( ! $delegated_enabled && '' === $delegated_number ) {
        unset( $fields['numero_licence_delegataire'] );
    }

    return $fields;
}
add_filter( 'ufsc_licence_fields', 'ufsc_production_new_licence_optional_identifier_fields', 999 );

/**
 * Guarantee the nullable schema immediately before a new licence mutation.
 *
 * The normal repair stays off public page rendering for performance. This hook
 * runs only on the authenticated admin-post creation request and only forces the
 * existing canonical repair when the live column is still NOT NULL.
 */
function ufsc_production_preflight_new_licence_identifier_schema() {
    if ( ! ufsc_production_is_new_licence_write_request() || ! class_exists( 'UFSC_SQL' ) ) {
        return;
    }
    if ( ! function_exists( 'ufsc_production_prepare_optional_unique_identifiers' ) ) {
        return;
    }

    global $wpdb;
    $settings = (array) UFSC_SQL::get_settings();
    $table = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['table_licences'] ?? '' ) );
    if ( '' === $table ) {
        return;
    }

    $column_info = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'numero_licence_delegataire' ) );
    if ( ! $column_info || 'YES' === strtoupper( (string) ( $column_info->Null ?? '' ) ) ) {
        return;
    }

    // Force the existing, idempotent repair once for this stale schema. The
    // function itself restores the version marker only after every repair passes.
    delete_option( 'ufsc_optional_identifier_repair_version' );
    ufsc_production_prepare_optional_unique_identifiers();

    if ( function_exists( 'ufsc_flush_table_columns_cache' ) ) {
        ufsc_flush_table_columns_cache( $table );
    }
}
add_action( 'admin_post_ufsc_add_licence', 'ufsc_production_preflight_new_licence_identifier_schema', -100 );
add_action( 'admin_post_ufsc_save_licence', 'ufsc_production_preflight_new_licence_identifier_schema', -100 );
