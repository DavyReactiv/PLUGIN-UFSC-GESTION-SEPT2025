<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Production hotfix — club admin save + dynamic club dashboard freshness.
 *
 * Scope volontairement étroit :
 * - normaliser les valeurs optionnelles qui peuvent faire échouer un UPDATE
 *   sous MySQL strict ;
 * - préserver le statut permanent existant lorsqu'il n'est pas soumis ;
 * - empêcher le cache de page de servir un tableau de bord club obsolète.
 *
 * Aucune migration, aucun changement de quota, paiement, panier ou historique.
 */

/**
 * Normaliser uniquement le POST d'édition admin d'un club avant le handler canonique.
 *
 * Le handler historique transmet les champs postés directement à wpdb::update().
 * Sous MySQL strict, une chaîne vide envoyée vers une colonne numérique ou date
 * peut faire retourner false à wpdb::update(). Sur une édition existante, une
 * valeur vide signifie ici "ne pas écraser la valeur existante".
 */
function ufsc_prod_hotfix_prepare_admin_club_update_payload() {
    if ( ! is_admin() || 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return;
    }

    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';
    if ( 'ufsc_sql_save_club' !== $action ) {
        return;
    }

    $club_id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
    if ( $club_id < 1 || ! class_exists( 'UFSC_SQL' ) ) {
        return;
    }

    $fields = (array) UFSC_SQL::get_club_fields();
    foreach ( $fields as $key => $conf ) {
        if ( ! array_key_exists( $key, $_POST ) || is_array( $_POST[ $key ] ) ) {
            continue;
        }

        $type  = isset( $conf[1] ) ? sanitize_key( (string) $conf[1] ) : 'text';
        $value = trim( (string) wp_unslash( $_POST[ $key ] ) );

        // Legacy zero-dates and empty date inputs must not be written back.
        if ( 'date' === $type && in_array( $value, array( '', '0000-00-00', '0000-00-00 00:00:00' ), true ) ) {
            unset( $_POST[ $key ], $_REQUEST[ $key ] );
            continue;
        }

        // Optional numeric fields can be empty in the admin form. Preserve the
        // stored value instead of forcing an invalid empty string into INT/BIGINT.
        if ( 'number' === $type && '' === $value ) {
            unset( $_POST[ $key ], $_REQUEST[ $key ] );
            continue;
        }

        // Boolean fields may legitimately be cleared: normalise blank to zero.
        if ( 'bool' === $type && '' === $value ) {
            $_POST[ $key ]    = '0';
            $_REQUEST[ $key ] = '0';
        }
    }

    // The canonical handler historically defaults an absent status to
    // en_attente, which can downgrade an existing club during an unrelated edit.
    // Re-inject the current stored status only when the form did not provide one.
    if ( ! isset( $_POST['statut'] ) || '' === trim( (string) wp_unslash( $_POST['statut'] ) ) ) {
        global $wpdb;
        $settings = (array) UFSC_SQL::get_settings();
        $table    = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['table_clubs'] ?? '' ) );
        $pk       = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['pk_club'] ?? 'id' ) );
        if ( $table && $pk ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are allow-listed above.
            $current_status = $wpdb->get_var( $wpdb->prepare( "SELECT statut FROM `{$table}` WHERE `{$pk}` = %d LIMIT 1", $club_id ) );
            if ( null !== $current_status && '' !== trim( (string) $current_status ) ) {
                $_POST['statut']    = (string) $current_status;
                $_REQUEST['statut'] = (string) $current_status;
            }
        }
    }
}
add_action( 'admin_init', 'ufsc_prod_hotfix_prepare_admin_club_update_payload', 1 );

/**
 * Le tableau de bord club contient des compteurs et tableaux SQL en temps réel.
 * Il ne doit jamais être servi depuis un cache HTML pour un utilisateur connecté.
 */
function ufsc_prod_hotfix_nocache_club_portal() {
    if ( is_admin() || ! is_user_logged_in() ) {
        return;
    }
    if ( ! function_exists( 'ufsc_is_club_portal_request' ) || ! ufsc_is_club_portal_request() ) {
        return;
    }

    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
    if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
        define( 'DONOTCACHEOBJECT', true );
    }
    if ( ! headers_sent() ) {
        nocache_headers();
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'X-UFSC-Portal-Cache: bypass' );
    }

    // LiteSpeed Cache respecte ce contrôle lorsqu'il est disponible.
    if ( has_action( 'litespeed_control_set_nocache' ) ) {
        do_action( 'litespeed_control_set_nocache', 'UFSC club portal dynamic data' );
    }
}
add_action( 'template_redirect', 'ufsc_prod_hotfix_nocache_club_portal', 0 );
