<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * P0 renewal -> canonical WooCommerce handoff.
 *
 * Renewal keeps its dedicated server-side validation/finalization flow, but paid
 * targets use the same idempotent licence cart helper as working new licences.
 * This avoids a second direct WC()->cart->add_to_cart implementation while
 * preserving renewal metadata, existing cart lines and historical source rows.
 */

/** Resolve final renewal intent including the browser fallback field. */
function ufsc_renewal_native_handoff_is_final_request() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return false;
    }

    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';
    if ( 'ufsc_bulk_renew_licences' !== $action ) {
        return false;
    }

    $intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) )
        : '';
    if ( '' === $intent && isset( $_POST['ufsc_renew_intent_fallback'] ) && ! is_array( $_POST['ufsc_renew_intent_fallback'] ) ) {
        $intent = sanitize_key( wp_unslash( $_POST['ufsc_renew_intent_fallback'] ) );
    }
    if ( '' === $intent && isset( $_POST['ufsc_final_intent'] ) && ! is_array( $_POST['ufsc_final_intent'] ) ) {
        $intent = sanitize_key( wp_unslash( $_POST['ufsc_final_intent'] ) );
    }

    return in_array( $intent, array( 'add_to_cart', 'submit_for_validation', 'finalize' ), true );
}

/** Replace only the final renewal controller after the previous P0 bind ran. */
function ufsc_renewal_native_handoff_bind() {
    remove_action( 'admin_post_ufsc_bulk_renew_licences', 'ufsc_renewal_recovery_handle_final_request', 1 );
    if ( class_exists( 'UFSC_Production_Readiness_Hotfix' ) ) {
        remove_action(
            'admin_post_ufsc_bulk_renew_licences',
            array( 'UFSC_Production_Readiness_Hotfix', 'handle_final_renewal_request' ),
            1
        );
    }
    add_action( 'admin_post_ufsc_bulk_renew_licences', 'ufsc_renewal_native_handoff_handle_final_request', 1 );
}
add_action( 'wp_loaded', 'ufsc_renewal_native_handoff_bind', 1010 );

/** Add a payable renewal target through the canonical idempotent licence cart helper. */
function ufsc_renewal_native_handoff_add_target( $source, $target, $club_id, $season ) {
    $source    = (object) $source;
    $target    = (object) $target;
    $club_id   = absint( $club_id );
    $target_id = absint( $target->id ?? 0 );
    $source_id = absint( $source->id ?? 0 );

    if ( $club_id < 1 || $target_id < 1 || $source_id < 1 ) {
        return new WP_Error( 'ufsc_renewal_native_invalid_target', __( 'Le dossier renouvelé est introuvable.', 'ufsc-clubs' ) );
    }

    if ( function_exists( 'ufsc_is_licence_linked_to_order' ) ) {
        $linked = ufsc_is_licence_linked_to_order( $target_id );
        if ( true === $linked ) {
            return new WP_Error( 'ufsc_renewal_native_order_exists', __( 'Une commande existe déjà pour ce renouvellement. Vérifiez vos commandes avant de relancer un paiement.', 'ufsc-clubs' ) );
        }
        if ( null === $linked ) {
            return new WP_Error( 'ufsc_renewal_native_order_unknown', __( 'Impossible de vérifier de façon sûre si ce renouvellement possède déjà une commande.', 'ufsc-clubs' ) );
        }
    }

    if ( function_exists( 'ufsc_renewal_recovery_cart_contains_target' ) && ufsc_renewal_recovery_cart_contains_target( $target_id ) ) {
        return array( 'existing' => true, 'target_id' => $target_id );
    }

    $missing = function_exists( 'ufsc_renewal_recovery_missing_fields' )
        ? ufsc_renewal_recovery_missing_fields( $target )
        : array();
    if ( $missing ) {
        return new WP_Error(
            'ufsc_renewal_native_incomplete',
            sprintf(
                __( 'Le renouvellement est conservé en brouillon. Complétez avant le panier : %s.', 'ufsc-clubs' ),
                implode( ', ', $missing )
            )
        );
    }

    $resolution = function_exists( 'ufsc_get_licence_product_resolution' )
        ? ufsc_get_licence_product_resolution()
        : array();
    $product_id = absint( $resolution['configured_id'] ?? ( function_exists( 'ufsc_get_licence_product_id' ) ? ufsc_get_licence_product_id() : 0 ) );
    if ( $product_id < 1 || ( isset( $resolution['valid'] ) && empty( $resolution['valid'] ) ) ) {
        $message = function_exists( 'ufsc_get_licence_product_message' )
            ? ufsc_get_licence_product_message( $resolution )
            : __( 'Le produit Licence UFSC est indisponible.', 'ufsc-clubs' );
        return new WP_Error( 'ufsc_renewal_native_product_unavailable', $message );
    }

    if ( ! function_exists( 'ufsc_add_licence_ids_to_cart_idempotent' ) ) {
        return new WP_Error( 'ufsc_renewal_native_cart_helper_missing', __( 'Le service panier des licences est indisponible.', 'ufsc-clubs' ) );
    }

    $item_data = function_exists( 'ufsc_renewal_recovery_cart_metadata' )
        ? ufsc_renewal_recovery_cart_metadata( $source, $target, $club_id, $season )
        : array();
    $item_data['ufsc_action']                  = 'renew_licence';
    $item_data['ufsc_operation_type']          = 'renewal';
    $item_data['ufsc_request_type']            = 'renewal';
    $item_data['ufsc_item_type']               = 'licence_renewal';
    $item_data['ufsc_club_id']                 = $club_id;
    $item_data['ufsc_licence_id']              = $target_id;
    $item_data['ufsc_license_ids']             = array( $target_id );
    $item_data['ufsc_target_season']           = $season;
    $item_data['ufsc_season']                  = $season;
    $item_data['ufsc_renew_from_licence_id']   = $source_id;
    $item_data['ufsc_previous_licence_id']     = $source_id;
    $item_data['ufsc_cart_identity']           = hash( 'sha256', $club_id . '|' . $source_id . '|' . $target_id . '|' . $season );
    $item_data['quantity']                     = 1;

    try {
        $added = ufsc_add_licence_ids_to_cart_idempotent(
            $product_id,
            $club_id,
            array( $target_id ),
            $item_data
        );
    } catch ( Throwable $error ) {
        if ( function_exists( 'ufsc_renewal_recovery_log' ) ) {
            ufsc_renewal_recovery_log(
                'ufsc_renewal_native_add_throwable',
                array(
                    'club_id'       => $club_id,
                    'source_id'     => $source_id,
                    'target_id'     => $target_id,
                    'error_class'   => get_class( $error ),
                    'error_message' => sanitize_text_field( $error->getMessage() ),
                ),
                'error'
            );
        }
        return new WP_Error( 'ufsc_renewal_native_add_exception', __( 'Le renouvellement a été conservé mais son ajout au panier a rencontré une erreur technique.', 'ufsc-clubs' ) );
    }

    if ( is_wp_error( $added ) ) {
        return $added;
    }

    if ( function_exists( 'ufsc_renewal_recovery_cart_contains_target' ) && ! ufsc_renewal_recovery_cart_contains_target( $target_id ) ) {
        return new WP_Error( 'ufsc_renewal_native_unconfirmed', __( 'Le renouvellement a été conservé, mais sa présence dans le panier n’a pas pu être confirmée.', 'ufsc-clubs' ) );
    }

    if ( function_exists( 'ufsc_renewal_recovery_log' ) ) {
        ufsc_renewal_recovery_log(
            'ufsc_renewal_native_cart_added',
            array(
                'club_id'   => $club_id,
                'source_id' => $source_id,
                'target_id' => $target_id,
                'cart_path' => 'canonical_idempotent_helper',
            ),
            'info'
        );
    }

    return array( 'added' => true, 'target_id' => $target_id );
}

/** Process one source renewal and reuse the annual target when it already exists. */
function ufsc_renewal_native_handoff_process_source( $source_id, $club_id, $season, array $profile_updates = array() ) {
    global $wpdb;

    if ( ! function_exists( 'ufsc_get_licences_table' ) || ! class_exists( 'UFSC_Renewal_Service' ) || ! class_exists( 'UFSC_Licence_Finalization_Service' ) ) {
        return new WP_Error( 'ufsc_renewal_native_service_unavailable', __( 'Le service de renouvellement est indisponible.', 'ufsc-clubs' ) );
    }

    $table  = ufsc_get_licences_table();
    $source = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", absint( $source_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! $source || absint( $source->club_id ?? 0 ) !== absint( $club_id ) ) {
        return new WP_Error( 'ufsc_renewal_native_source_inaccessible', __( 'Licence source inaccessible.', 'ufsc-clubs' ) );
    }

    $target   = function_exists( 'ufsc_renewal_recovery_existing_target' ) ? ufsc_renewal_recovery_existing_target( $source, $club_id, $season ) : null;
    $decision = null;

    if ( ! $target ) {
        $renewable = UFSC_Renewal_Service::can_renew( $source, $club_id, $season );
        if ( is_wp_error( $renewable ) ) {
            return $renewable;
        }

        $profile = UFSC_Renewal_Service::sanitize_renewal_updates( $source, $profile_updates );
        if ( ! empty( $profile['errors'] ) ) {
            return new WP_Error( 'ufsc_renewal_native_profile_invalid', implode( ' ', array_values( $profile['errors'] ) ) );
        }

        $created = UFSC_Renewal_Service::create_target_draft( $source, $club_id, $season, $profile['data'] );
        if ( is_wp_error( $created ) ) {
            return $created;
        }

        $target_id = absint( $created['licence_id'] ?? 0 );
        $target    = $target_id > 0
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $target_id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            : null;
        if ( ! $target ) {
            return new WP_Error( 'ufsc_renewal_native_target_unconfirmed', __( 'Le dossier renouvelé n’a pas pu être relu après sa création.', 'ufsc-clubs' ) );
        }

        if ( isset( $created['ufsc_finalization'] ) ) {
            $decision = $created['ufsc_finalization'];
        }
    }

    $target_id = absint( $target->id ?? 0 );
    if ( function_exists( 'ufsc_is_licence_linked_to_order' ) && true === ufsc_is_licence_linked_to_order( $target_id ) ) {
        return new WP_Error( 'ufsc_renewal_native_order_exists', __( 'Une commande existe déjà pour ce renouvellement.', 'ufsc-clubs' ) );
    }

    if ( function_exists( 'ufsc_renewal_recovery_cart_contains_target' ) && ufsc_renewal_recovery_cart_contains_target( $target_id ) ) {
        return array( 'paid' => true, 'existing' => true, 'target_id' => $target_id );
    }

    if ( null === $decision ) {
        $decision = UFSC_Licence_Finalization_Service::finalize( $target_id, $club_id, $season, 'renewal_native_handoff' );
    }
    if ( is_wp_error( $decision ) ) {
        return $decision;
    }

    if ( ! empty( $decision['included'] ) ) {
        if ( function_exists( 'ufsc_mark_renewed_licence_marker' ) ) {
            ufsc_mark_renewed_licence_marker( absint( $source->id ?? 0 ), $season, $target_id );
        }
        return array( 'included' => true, 'target_id' => $target_id );
    }

    if ( empty( $decision['payable'] ) ) {
        return new WP_Error( 'ufsc_renewal_native_payment_undetermined', __( 'Le mode de règlement du renouvellement est indéterminé.', 'ufsc-clubs' ) );
    }

    $result = ufsc_renewal_native_handoff_add_target( $source, $target, $club_id, $season );
    if ( is_wp_error( $result ) && function_exists( 'ufsc_renewal_recovery_keep_editable' ) ) {
        ufsc_renewal_recovery_keep_editable( $target_id, $club_id );
    }

    return $result;
}

/** Final renewal endpoint with one cart persistence pass for all paid targets. */
function ufsc_renewal_native_handoff_handle_final_request() {
    if ( ! ufsc_renewal_native_handoff_is_final_request() ) {
        return;
    }

    $club_id = isset( $_POST['ufsc_club_id'] ) && ! is_array( $_POST['ufsc_club_id'] )
        ? absint( wp_unslash( $_POST['ufsc_club_id'] ) )
        : 0;
    check_admin_referer( 'ufsc_bulk_renew_licences_' . $club_id );

    $user_club = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
    if ( ! is_user_logged_in() || ! current_user_can( 'read' ) || $club_id < 1 || $club_id !== $user_club ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    $season = function_exists( 'ufsc_renewal_recovery_current_season' )
        ? ufsc_renewal_recovery_current_season()
        : ( class_exists( 'UFSC_Season_Service' ) ? str_replace( '/', '-', (string) UFSC_Season_Service::get_current_season() ) : '' );
    $posted_season = isset( $_POST['ufsc_target_season'] ) && ! is_array( $_POST['ufsc_target_season'] )
        ? str_replace( '/', '-', sanitize_text_field( wp_unslash( $_POST['ufsc_target_season'] ) ) )
        : '';
    if ( '' !== $posted_season && $posted_season !== $season ) {
        ufsc_renewal_recovery_redirect_error( __( 'Saison cible invalide.', 'ufsc-clubs' ) );
    }

    $source_ids = function_exists( 'ufsc_renewal_recovery_posted_source_ids' ) ? ufsc_renewal_recovery_posted_source_ids() : array();
    if ( ! $source_ids ) {
        ufsc_renewal_recovery_redirect_error( __( 'Aucune licence à renouveler n’a été sélectionnée.', 'ufsc-clubs' ) );
    }

    $profiles = isset( $_POST['renewal_profiles'] ) && is_array( $_POST['renewal_profiles'] )
        ? map_deep( wp_unslash( $_POST['renewal_profiles'] ), 'sanitize_text_field' )
        : array();

    $included     = 0;
    $paid_targets = array();
    $errors       = array();

    foreach ( $source_ids as $source_id ) {
        try {
            $result = ufsc_renewal_native_handoff_process_source(
                $source_id,
                $club_id,
                $season,
                isset( $profiles[ $source_id ] ) && is_array( $profiles[ $source_id ] ) ? $profiles[ $source_id ] : array()
            );
        } catch ( Throwable $error ) {
            if ( function_exists( 'ufsc_renewal_recovery_log' ) ) {
                ufsc_renewal_recovery_log(
                    'ufsc_renewal_native_process_throwable',
                    array(
                        'club_id'       => $club_id,
                        'source_id'     => absint( $source_id ),
                        'error_class'   => get_class( $error ),
                        'error_message' => sanitize_text_field( $error->getMessage() ),
                    ),
                    'error'
                );
            }
            $errors[] = __( 'Le renouvellement a été conservé sans perte de données, mais son ajout au panier a rencontré une erreur technique.', 'ufsc-clubs' );
            continue;
        }

        if ( is_wp_error( $result ) ) {
            $errors[] = $result->get_error_message();
            continue;
        }

        $target_id = absint( $result['target_id'] ?? 0 );
        if ( ! empty( $result['included'] ) ) {
            $included++;
        } elseif ( $target_id > 0 && ( ! empty( $result['added'] ) || ! empty( $result['existing'] ) || ! empty( $result['paid'] ) ) ) {
            $paid_targets[ $target_id ] = $target_id;
        }
    }

    if ( $paid_targets ) {
        try {
            $persisted = function_exists( 'ufsc_persist_woocommerce_cart' )
                ? ufsc_persist_woocommerce_cart()
                : new WP_Error( 'ufsc_renewal_native_persist_missing', __( 'La session WooCommerce est indisponible.', 'ufsc-clubs' ) );
        } catch ( Throwable $error ) {
            if ( function_exists( 'ufsc_renewal_recovery_log' ) ) {
                ufsc_renewal_recovery_log(
                    'ufsc_renewal_native_persist_throwable',
                    array(
                        'club_id'       => $club_id,
                        'target_ids'    => array_values( $paid_targets ),
                        'error_class'   => get_class( $error ),
                        'error_message' => sanitize_text_field( $error->getMessage() ),
                    ),
                    'error'
                );
            }
            $persisted = new WP_Error( 'ufsc_renewal_native_persist_exception', __( 'Le panier n’a pas pu être enregistré. Les dossiers restent récupérables.', 'ufsc-clubs' ) );
        }

        if ( is_wp_error( $persisted ) ) {
            $errors[] = $persisted->get_error_message();
        } else {
            foreach ( $paid_targets as $target_id ) {
                if ( function_exists( 'ufsc_renewal_recovery_cart_contains_target' ) && ! ufsc_renewal_recovery_cart_contains_target( $target_id ) ) {
                    $errors[] = __( 'Une licence renouvelée n’a pas été conservée dans le panier. Le dossier reste récupérable.', 'ufsc-clubs' );
                    if ( function_exists( 'ufsc_renewal_recovery_keep_editable' ) ) {
                        ufsc_renewal_recovery_keep_editable( $target_id, $club_id );
                    }
                }
            }
        }
    }

    if ( $errors ) {
        ufsc_renewal_recovery_redirect_error( implode( ' ', array_values( array_unique( $errors ) ) ) );
    }

    if ( $paid_targets ) {
        wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) );
        exit;
    }

    $return = wp_get_referer() ?: home_url( '/' );
    $return = add_query_arg(
        array(
            'ufsc_message' => 'renewal_included',
            'ufsc_renewed' => $included,
        ),
        $return
    );
    wp_safe_redirect( $return );
    exit;
}