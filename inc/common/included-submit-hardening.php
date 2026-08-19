<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Harden the included-licence submit transition.
 *
 * The legacy status mirror may use a different vocabulary from the canonical
 * `statut` column. Never let a legacy mirror failure cancel the canonical
 * transition from brouillon to en_attente.
 */

function ufsc_included_submit_redirect_url( $type, $message = '' ) {
    $base = class_exists( 'UFSC_Frontend_Shortcodes' )
        ? UFSC_Frontend_Shortcodes::get_club_portal_url( 'club-licences' )
        : home_url( '/tableau-de-bord-club/' );

    $args = array( 'ufsc_submit_notice' => sanitize_key( $type ) );
    if ( '' !== trim( (string) $message ) ) {
        $args['ufsc_submit_message'] = sanitize_text_field( $message );
    }
    return add_query_arg( $args, $base );
}

function ufsc_included_submit_fail( $message ) {
    wp_safe_redirect( ufsc_included_submit_redirect_url( 'error', $message ) );
    exit;
}

/**
 * Persist the canonical pending-validation status, then mirror to the legacy
 * status column on a best-effort basis. The canonical write is verified by a
 * fresh read before the request is considered successful.
 */
function ufsc_included_submit_write_pending_status( $licence_id, $club_id ) {
    global $wpdb;
    $table = function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : '';
    if ( ! $table ) {
        return new WP_Error( 'ufsc_submit_table_missing', __( 'La table des licences est indisponible.', 'ufsc-clubs' ) );
    }

    $columns = function_exists( 'ufsc_table_columns' )
        ? (array) ufsc_table_columns( $table )
        : (array) $wpdb->get_col( "DESCRIBE `{$table}`", 0 );

    if ( in_array( 'statut', $columns, true ) ) {
        $written = $wpdb->update(
            $table,
            array( 'statut' => 'en_attente' ),
            array( 'id' => absint( $licence_id ), 'club_id' => absint( $club_id ) ),
            array( '%s' ),
            array( '%d', '%d' )
        );
        if ( false === $written ) {
            return new WP_Error( 'ufsc_submit_status_write_failed', __( 'Le statut de la licence n’a pas pu être enregistré.', 'ufsc-clubs' ) );
        }

        // Legacy mirror only. A constraint on this column must never roll back
        // the canonical `statut` write.
        if ( in_array( 'status', $columns, true ) ) {
            $wpdb->update(
                $table,
                array( 'status' => 'pending' ),
                array( 'id' => absint( $licence_id ), 'club_id' => absint( $club_id ) ),
                array( '%s' ),
                array( '%d', '%d' )
            );
        }
    } elseif ( in_array( 'status', $columns, true ) ) {
        $written = $wpdb->update(
            $table,
            array( 'status' => 'pending' ),
            array( 'id' => absint( $licence_id ), 'club_id' => absint( $club_id ) ),
            array( '%s' ),
            array( '%d', '%d' )
        );
        if ( false === $written ) {
            return new WP_Error( 'ufsc_submit_status_write_failed', __( 'Le statut de la licence n’a pas pu être enregistré.', 'ufsc-clubs' ) );
        }
    } else {
        return new WP_Error( 'ufsc_submit_status_column_missing', __( 'Aucune colonne de statut compatible n’est disponible.', 'ufsc-clubs' ) );
    }

    $fresh = function_exists( 'ufsc_journey_get_licence' ) ? ufsc_journey_get_licence( $licence_id ) : null;
    $normalized = $fresh && function_exists( 'ufsc_get_licence_status_from_record' )
        ? ufsc_get_licence_status_from_record( $fresh )
        : '';

    return 'en_attente' === $normalized
        ? true
        : new WP_Error( 'ufsc_submit_status_verification_failed', __( 'La licence n’a pas confirmé son passage en attente de validation.', 'ufsc-clubs' ) );
}

function ufsc_hardened_journey_finalize_licence() {
    if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    $licence_id = isset( $_POST['licence_id'] ) && ! is_array( $_POST['licence_id'] )
        ? absint( wp_unslash( $_POST['licence_id'] ) )
        : 0;
    check_admin_referer( 'ufsc_journey_finalize_' . $licence_id );

    $licence = function_exists( 'ufsc_journey_get_licence' ) ? ufsc_journey_get_licence( $licence_id ) : null;
    if ( ! $licence || ! function_exists( 'ufsc_journey_can_manage_licence' ) || ! ufsc_journey_can_manage_licence( $licence ) ) {
        wp_die( esc_html__( 'Licence inaccessible.', 'ufsc-clubs' ) );
    }

    $decision = function_exists( 'ufsc_journey_licence_decision' ) ? ufsc_journey_licence_decision( $licence ) : array();
    if ( empty( $decision['included'] ) ) {
        // Preserve the already-tested paid WooCommerce route unchanged.
        ufsc_journey_finalize_licence();
        return;
    }

    $club_id = absint( $licence->club_id ?? 0 );
    $season  = function_exists( 'ufsc_journey_current_season' ) ? ufsc_journey_current_season() : '';
    $gate = function_exists( 'ufsc_club_can_manage_licences_for_season' )
        ? ufsc_club_can_manage_licences_for_season( $club_id, $season )
        : array( 'allowed' => false, 'message' => __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ) );

    if ( empty( $gate['allowed'] ) ) {
        ufsc_included_submit_fail( (string) ( $gate['message'] ?? __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ) ) );
    }

    $role = sanitize_key( (string) ( $licence->role ?? '' ) );
    $allocation = function_exists( 'ufsc_allocate_pack_credit' )
        ? ufsc_allocate_pack_credit( $licence_id, $club_id, $season, $role )
        : new WP_Error( 'quota_unavailable', __( 'Le quota d’affiliation est indisponible.', 'ufsc-clubs' ) );

    if ( is_wp_error( $allocation ) ) {
        ufsc_included_submit_fail( $allocation->get_error_message() );
    }

    // A concurrent request may have consumed the final included place after the
    // form was displayed. In that case, hand back to the canonical paid route.
    if ( empty( $allocation['included'] ) ) {
        ufsc_journey_finalize_licence();
        return;
    }

    global $wpdb;
    $table = ufsc_get_licences_table();
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
    $was_included = ! empty( $licence->is_included );

    // The historical schema may store NULL instead of 0. The canonical allocator
    // previously used `is_included = 0`, which leaves NULL rows untouched.
    if ( in_array( 'is_included', $columns, true ) ) {
        $reserved = $wpdb->update(
            $table,
            array( 'is_included' => 1 ),
            array( 'id' => $licence_id, 'club_id' => $club_id ),
            array( '%d' ),
            array( '%d', '%d' )
        );
        if ( false === $reserved ) {
            ufsc_included_submit_fail( __( 'La licence incluse n’a pas pu être réservée dans votre quota.', 'ufsc-clubs' ) );
        }
    }

    $status_result = ufsc_included_submit_write_pending_status( $licence_id, $club_id );
    if ( is_wp_error( $status_result ) ) {
        if ( ! $was_included && in_array( 'is_included', $columns, true ) ) {
            $wpdb->update( $table, array( 'is_included' => 0 ), array( 'id' => $licence_id, 'club_id' => $club_id ), array( '%d' ), array( '%d', '%d' ) );
        }
        ufsc_included_submit_fail( $status_result->get_error_message() );
    }

    if ( in_array( 'payment_status', $columns, true ) ) {
        $wpdb->update(
            $table,
            array( 'payment_status' => 'included' ),
            array( 'id' => $licence_id, 'club_id' => $club_id ),
            array( '%s' ),
            array( '%d', '%d' )
        );
    }

    if ( function_exists( 'ufsc_journey_record_submission' ) ) {
        ufsc_journey_record_submission( $licence_id, $club_id, $season, 'club_included_verified' );
    }
    do_action( 'ufsc_licence_updated', $club_id );

    wp_safe_redirect( ufsc_included_submit_redirect_url( 'success' ) );
    exit;
}

/** Replace only the included/detail finalizer. The paid route stays delegated. */
function ufsc_install_hardened_included_submit_handler() {
    remove_action( 'admin_post_ufsc_journey_finalize_licence', 'ufsc_journey_finalize_licence' );
    add_action( 'admin_post_ufsc_journey_finalize_licence', 'ufsc_hardened_journey_finalize_licence' );
}
add_action( 'init', 'ufsc_install_hardened_included_submit_handler', 999 );

/** Visible confirmation/error after the redirect back to the licence workspace. */
function ufsc_render_included_submit_notice( $content ) {
    if ( is_admin() || empty( $_GET['ufsc_submit_notice'] ) ) { return $content; }
    global $post;
    if ( ! $post || ! ( has_shortcode( $post->post_content, 'ufsc_club_dashboard' ) || has_shortcode( $post->post_content, 'ufsc_club_licences' ) ) ) {
        return $content;
    }

    $type = sanitize_key( wp_unslash( $_GET['ufsc_submit_notice'] ) );
    if ( 'success' === $type ) {
        $notice = '<div class="ufsc-message ufsc-success" role="status"><strong>' . esc_html__( 'Licence envoyée à l’UFSC pour validation.', 'ufsc-clubs' ) . '</strong> ' . esc_html__( 'Son statut est maintenant « En attente de validation » et elle est disponible côté administration.', 'ufsc-clubs' ) . '</div>';
        return $notice . $content;
    }

    if ( 'error' === $type ) {
        $message = isset( $_GET['ufsc_submit_message'] ) && ! is_array( $_GET['ufsc_submit_message'] )
            ? sanitize_text_field( wp_unslash( $_GET['ufsc_submit_message'] ) )
            : __( 'La licence n’a pas pu être envoyée. Aucune donnée n’a été perdue.', 'ufsc-clubs' );
        $notice = '<div class="ufsc-message ufsc-error" role="alert"><strong>' . esc_html__( 'Envoi non effectué.', 'ufsc-clubs' ) . '</strong> ' . esc_html( $message ) . '</div>';
        return $notice . $content;
    }

    return $content;
}
add_filter( 'the_content', 'ufsc_render_included_submit_notice', 8 );
