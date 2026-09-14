<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Compatibility for optional unique licence identifiers.
 *
 * Historical schemas may carry a UNIQUE index on numero_licence_delegataire.
 * A missing delegated number must never be written as an empty string because
 * several empty strings collide on that historical unique key.
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
 * Detect the legacy SQL-admin edit form used by UFSC_SQL_Admin::handle_save_licence().
 *
 * That form posts back to the licences admin page (not admin-post.php) and uses
 * `id` for the existing licence primary key.
 *
 * @return bool
 */
function ufsc_production_is_existing_admin_licence_update_request() {
    if ( ! is_admin() || ! is_user_logged_in() ) {
        return false;
    }

    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
    if ( 'POST' !== $method ) {
        return false;
    }

    $page = isset( $_REQUEST['page'] ) && ! is_array( $_REQUEST['page'] )
        ? sanitize_key( wp_unslash( $_REQUEST['page'] ) )
        : '';
    if ( 'ufsc_lc_licences' !== $page ) {
        return false;
    }

    $licence_id = isset( $_POST['id'] ) && ! is_array( $_POST['id'] )
        ? absint( wp_unslash( $_POST['id'] ) )
        : 0;

    return $licence_id > 0;
}

/**
 * Omit a missing delegated licence number from NEW inserts and safe legacy
 * admin updates.
 *
 * For existing licences we intentionally preserve the stored value instead of
 * rewriting an empty string. This is the least destructive production hotfix:
 * it avoids the UNIQUE-key collision without deleting or rewriting historical
 * identifiers. A populated submitted number keeps the canonical behaviour.
 *
 * @param array $fields Canonical licence field map.
 * @return array
 */
function ufsc_production_optional_identifier_fields( $fields ) {
    if ( ! is_array( $fields ) ) {
        return $fields;
    }

    $is_new_request    = ufsc_production_is_new_licence_write_request();
    $is_admin_update   = ufsc_production_is_existing_admin_licence_update_request();
    if ( ! $is_new_request && ! $is_admin_update ) {
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
add_filter( 'ufsc_licence_fields', 'ufsc_production_optional_identifier_fields', 999 );

/** Backward-compatible callback name kept for any external/internal references. */
function ufsc_production_new_licence_optional_identifier_fields( $fields ) {
    return ufsc_production_optional_identifier_fields( $fields );
}

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
