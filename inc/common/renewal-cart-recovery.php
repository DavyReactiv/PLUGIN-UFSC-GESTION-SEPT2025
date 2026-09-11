<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * P0 renewal cart recovery.
 *
 * The production renewal controller historically duplicated the WooCommerce
 * hand-off while the rest of the plugin already owns an idempotent native cart
 * helper. This layer replaces only FINAL renewal submissions and keeps
 * verify/save/cancel on the existing assistant.
 *
 * Goals:
 * - reuse a current-season renewal draft after a failed first attempt;
 * - append the renewal to an existing cart instead of replacing it;
 * - never create a second annual row for the same person;
 * - never create a second payment when a Woo order already owns the renewal;
 * - catch runtime failures and keep the dossier editable instead of exposing a
 *   WordPress critical-error page;
 * - keep included renewals outside WooCommerce.
 */

/** @return bool */
function ufsc_renewal_recovery_is_final_request() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return false;
    }

    $intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) )
        : 'verify';

    return in_array( $intent, array( 'add_to_cart', 'submit_for_validation', 'finalize' ), true );
}

/**
 * Replace the duplicated final mutation controller after production-readiness
 * has registered it. Non-final requests intentionally fall through to the
 * established legacy assistant handler.
 */
function ufsc_renewal_recovery_bind_final_handler() {
    if ( class_exists( 'UFSC_Production_Readiness_Hotfix' ) ) {
        remove_action(
            'admin_post_ufsc_bulk_renew_licences',
            array( 'UFSC_Production_Readiness_Hotfix', 'handle_final_renewal_request' ),
            1
        );
    }

    add_action( 'admin_post_ufsc_bulk_renew_licences', 'ufsc_renewal_recovery_handle_final_request', 1 );
}
add_action( 'wp_loaded', 'ufsc_renewal_recovery_bind_final_handler', 1005 );

/** Resolve the canonical current season. */
function ufsc_renewal_recovery_current_season() {
    $season = class_exists( 'UFSC_Season_Service' )
        ? (string) UFSC_Season_Service::get_current_season()
        : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );

    return str_replace( '/', '-', sanitize_text_field( $season ) );
}

/** @return int[] */
function ufsc_renewal_recovery_posted_source_ids() {
    foreach ( array( 'ufsc_renew_ids', 'source_ids', 'renew_licence_ids' ) as $key ) {
        if ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) {
            return array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST[ $key ] ) ) ) ) );
        }
    }

    return array();
}

/** Return the current-season target row, if the first attempt already created it. */
function ufsc_renewal_recovery_existing_target( $source, $club_id, $season ) {
    if ( ! $source || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return null;
    }

    $target_id = function_exists( 'ufsc_wc_find_equivalent_renewed_licence_id' )
        ? absint( ufsc_wc_find_equivalent_renewed_licence_id( $source, $club_id, $season ) )
        : 0;
    if ( $target_id < 1 ) {
        return null;
    }

    global $wpdb;
    $table = ufsc_get_licences_table();
    $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $target_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! $row || absint( $row->club_id ?? 0 ) !== absint( $club_id ) ) {
        return null;
    }

    $row_season = function_exists( 'ufsc_get_licence_season_label' )
        ? str_replace( '/', '-', (string) ufsc_get_licence_season_label( $row ) )
        : str_replace( '/', '-', (string) ( $row->season ?? ( $row->saison ?? '' ) ) );
    if ( $row_season && $row_season !== $season ) {
        return null;
    }

    return $row;
}

/**
 * Explain exactly which values prevent the current-season renewal target from
 * reaching WooCommerce. Nothing is auto-filled or guessed.
 *
 * @return string[]
 */
function ufsc_renewal_recovery_missing_fields( $row ) {
    $row = is_object( $row ) ? $row : (object) $row;
    $labels = array(
        'nom'            => 'nom',
        'prenom'         => 'prénom',
        'sexe'           => 'sexe',
        'date_naissance' => 'date de naissance',
        'adresse'        => 'adresse',
        'code_postal'    => 'code postal',
        'ville'          => 'ville',
        'pays'           => 'pays',
        'email'          => 'e-mail',
    );
    $missing = array();

    foreach ( $labels as $field => $label ) {
        if ( '' === trim( (string) ( $row->{$field} ?? '' ) ) ) {
            $missing[] = $label;
        }
    }

    $phone = trim( (string) ( $row->telephone ?? ( $row->tel_mobile ?? '' ) ) );
    if ( '' === $phone ) {
        $missing[] = 'téléphone';
    }

    $level = trim( (string) ( $row->fighter_level ?? ( $row->niveau_combattant ?? '' ) ) );
    if ( '' === $level ) {
        $missing[] = 'niveau sportif';
    }

    $weight = class_exists( 'UFSC_Category_Repository' )
        ? UFSC_Category_Repository::normalize_weight( $row->poids ?? '' )
        : ( is_numeric( $row->poids ?? null ) ? (float) $row->poids : null );
    if ( null === $weight || $weight < 20 || $weight > 300 ) {
        $missing[] = 'poids';
    }

    return array_values( array_unique( $missing ) );
}

/** Does the native Woo cart currently contain this target licence? */
function ufsc_renewal_recovery_cart_contains_target( $licence_id ) {
    $licence_id = absint( $licence_id );
    if ( $licence_id < 1 || ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
        return false;
    }

    foreach ( (array) WC()->cart->get_cart() as $item ) {
        $ids = function_exists( 'ufsc_extract_licence_ids_from_cart_item' )
            ? ufsc_extract_licence_ids_from_cart_item( (array) $item )
            : array( absint( $item['ufsc_licence_id'] ?? 0 ) );
        if ( in_array( $licence_id, $ids, true ) ) {
            return true;
        }
    }

    return false;
}

/** Put a failed, unpaid renewal back into an editable state without deleting it. */
function ufsc_renewal_recovery_keep_editable( $target_id, $club_id ) {
    $target_id = absint( $target_id );
    $club_id   = absint( $club_id );
    if ( $target_id < 1 || $club_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return;
    }
    if ( ufsc_renewal_recovery_cart_contains_target( $target_id ) ) {
        return;
    }
    if ( function_exists( 'ufsc_is_licence_linked_to_order' ) ) {
        $linked = ufsc_is_licence_linked_to_order( $target_id );
        if ( false !== $linked ) {
            return; // true or uncertainty: fail closed.
        }
    } else {
        return;
    }

    global $wpdb;
    $table = ufsc_get_licences_table();
    if ( class_exists( 'UFSC_Licence_Status' ) ) {
        UFSC_Licence_Status::update_status_columns(
            $table,
            array( 'id' => $target_id, 'club_id' => $club_id ),
            'brouillon',
            array( '%d', '%d' )
        );
    }
}

/** Build renewal metadata while keeping identity sourced from server-side rows. */
function ufsc_renewal_recovery_cart_metadata( $source, $target, $club_id, $season ) {
    $source = (object) $source;
    $target = (object) $target;

    return array(
        'ufsc_action'                    => 'renew_licence',
        'ufsc_request_type'              => 'renewal',
        'ufsc_item_type'                 => 'licence_renewal',
        'ufsc_operation_type'            => 'renewal',
        'ufsc_target_season'             => $season,
        'ufsc_season'                    => $season,
        'ufsc_renew_from_licence_id'     => absint( $source->id ?? 0 ),
        'ufsc_previous_licence_id'       => absint( $source->id ?? 0 ),
        'ufsc_person_identifier'         => is_callable( array( 'UFSC_Renewal_Service', 'person_key' ) )
            ? UFSC_Renewal_Service::person_key( $source, $club_id )
            : '',
        'ufsc_fighter_level'             => sanitize_key( (string) ( $target->fighter_level ?? ( $target->niveau_combattant ?? '' ) ) ),
        'ufsc_weight'                    => (string) ( $target->poids ?? '' ),
        'ufsc_sensitive_identity_change' => false,
        'quantity'                       => 1,
    );
}

/** Add/re-add a current-season payable renewal target without touching the other cart lines. */
function ufsc_renewal_recovery_add_target_to_cart( $source, $target, $club_id, $season ) {
    $source    = (object) $source;
    $target    = (object) $target;
    $target_id = absint( $target->id ?? 0 );

    if ( $target_id < 1 ) {
        return new WP_Error( 'ufsc_renewal_target_missing', __( 'Le dossier renouvelé est introuvable.', 'ufsc-clubs' ) );
    }
    if ( ! empty( $target->is_included ) ) {
        return array( 'included' => true, 'target_id' => $target_id );
    }
    if ( function_exists( 'ufsc_is_licence_linked_to_order' ) ) {
        $linked = ufsc_is_licence_linked_to_order( $target_id );
        if ( true === $linked ) {
            return new WP_Error( 'ufsc_renewal_order_exists', __( 'Une commande existe déjà pour ce renouvellement. Vérifiez vos commandes avant de relancer un paiement.', 'ufsc-clubs' ) );
        }
    }

    if ( ufsc_renewal_recovery_cart_contains_target( $target_id ) || ( function_exists( 'ufsc_cart_has_renewal_item' ) && ufsc_cart_has_renewal_item( 'renew_licence', $club_id, $season, absint( $source->id ?? 0 ) ) ) ) {
        return array( 'existing' => true, 'target_id' => $target_id );
    }

    $missing = ufsc_renewal_recovery_missing_fields( $target );
    if ( $missing ) {
        return new WP_Error(
            'ufsc_renewal_incomplete',
            sprintf(
                __( 'Le renouvellement est conservé en brouillon. Complétez avant le panier : %s.', 'ufsc-clubs' ),
                implode( ', ', $missing )
            )
        );
    }

    $product_id = function_exists( 'ufsc_get_licence_product_id' ) ? absint( ufsc_get_licence_product_id() ) : 0;
    if ( $product_id < 1 || ! function_exists( 'ufsc_add_licence_ids_to_cart_idempotent' ) ) {
        return new WP_Error( 'ufsc_renewal_product_unavailable', __( 'Le produit Licence UFSC est indisponible.', 'ufsc-clubs' ) );
    }

    $ready = function_exists( 'ufsc_ensure_woocommerce_cart' )
        ? ufsc_ensure_woocommerce_cart()
        : new WP_Error( 'ufsc_renewal_cart_unavailable', __( 'Le panier WooCommerce est indisponible.', 'ufsc-clubs' ) );
    if ( is_wp_error( $ready ) ) {
        return $ready;
    }

    $result = ufsc_add_licence_ids_to_cart_idempotent(
        $product_id,
        $club_id,
        array( $target_id ),
        ufsc_renewal_recovery_cart_metadata( $source, $target, $club_id, $season )
    );
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    if ( ! ufsc_renewal_recovery_cart_contains_target( $target_id ) ) {
        return new WP_Error( 'ufsc_renewal_cart_unconfirmed', __( 'Le renouvellement a été conservé, mais sa présence dans le panier n’a pas pu être confirmée.', 'ufsc-clubs' ) );
    }

    $persisted = function_exists( 'ufsc_persist_woocommerce_cart' ) ? ufsc_persist_woocommerce_cart() : true;
    if ( is_wp_error( $persisted ) ) {
        return $persisted;
    }

    return array(
        'added'     => ! empty( $result['added'] ),
        'existing'  => ! empty( $result['existing'] ),
        'target_id' => $target_id,
    );
}

/** Process one source licence, reusing a pre-existing annual target when present. */
function ufsc_renewal_recovery_process_source( $source_id, $club_id, $season, array $profile_updates = array() ) {
    global $wpdb;

    if ( ! function_exists( 'ufsc_get_licences_table' ) || ! class_exists( 'UFSC_Renewal_Service' ) || ! class_exists( 'UFSC_Licence_Finalization_Service' ) ) {
        return new WP_Error( 'ufsc_renewal_service_unavailable', __( 'Le service de renouvellement est indisponible.', 'ufsc-clubs' ) );
    }

    $table  = ufsc_get_licences_table();
    $source = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", absint( $source_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! $source || absint( $source->club_id ?? 0 ) !== absint( $club_id ) ) {
        return new WP_Error( 'ufsc_renewal_source_inaccessible', __( 'Licence source inaccessible.', 'ufsc-clubs' ) );
    }

    $target = ufsc_renewal_recovery_existing_target( $source, $club_id, $season );
    if ( ! $target ) {
        $renewable = UFSC_Renewal_Service::can_renew( $source, $club_id, $season );
        if ( is_wp_error( $renewable ) ) {
            return $renewable;
        }

        $profile = UFSC_Renewal_Service::sanitize_renewal_updates( $source, $profile_updates );
        if ( ! empty( $profile['errors'] ) ) {
            return new WP_Error( 'ufsc_renewal_profile_invalid', implode( ' ', array_values( $profile['errors'] ) ) );
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
            return new WP_Error( 'ufsc_renewal_target_creation_unconfirmed', __( 'Le dossier de renouvellement n’a pas pu être relu après sa création.', 'ufsc-clubs' ) );
        }
    }

    $target_id = absint( $target->id ?? 0 );
    if ( function_exists( 'ufsc_is_licence_linked_to_order' ) && true === ufsc_is_licence_linked_to_order( $target_id ) ) {
        return new WP_Error( 'ufsc_renewal_order_exists', __( 'Une commande existe déjà pour ce renouvellement.', 'ufsc-clubs' ) );
    }

    if ( ufsc_renewal_recovery_cart_contains_target( $target_id ) ) {
        return array( 'paid' => true, 'existing' => true, 'target_id' => $target_id );
    }

    $decision = UFSC_Licence_Finalization_Service::finalize( $target_id, $club_id, $season, 'renewal_recovery' );
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
        return new WP_Error( 'ufsc_renewal_payment_undetermined', __( 'Le mode de règlement du renouvellement est indéterminé.', 'ufsc-clubs' ) );
    }

    return ufsc_renewal_recovery_add_target_to_cart( $source, $target, $club_id, $season );
}

/** Log technical context without licence personal data. */
function ufsc_renewal_recovery_log( $event, array $context = array(), $level = 'warning' ) {
    if ( function_exists( 'ufsc_wc_log' ) ) {
        ufsc_wc_log( $event, $context, $level );
    }
}

/** Redirect back with a club-readable error and keep the annual target editable. */
function ufsc_renewal_recovery_redirect_error( $message ) {
    $return = wp_get_referer() ?: home_url( '/' );
    wp_safe_redirect( add_query_arg( 'ufsc_error', rawurlencode( sanitize_text_field( (string) $message ) ), $return ) );
    exit;
}

/** Final renewal endpoint. */
function ufsc_renewal_recovery_handle_final_request() {
    if ( ! ufsc_renewal_recovery_is_final_request() ) {
        return; // verify/save/cancel continue to the established handler.
    }

    $club_id = isset( $_POST['ufsc_club_id'] ) && ! is_array( $_POST['ufsc_club_id'] ) ? absint( wp_unslash( $_POST['ufsc_club_id'] ) ) : 0;
    check_admin_referer( 'ufsc_bulk_renew_licences_' . $club_id );

    $user_club = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
    if ( ! is_user_logged_in() || ! current_user_can( 'read' ) || $club_id < 1 || $club_id !== $user_club ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    $season        = ufsc_renewal_recovery_current_season();
    $posted_season = isset( $_POST['ufsc_target_season'] ) && ! is_array( $_POST['ufsc_target_season'] )
        ? str_replace( '/', '-', sanitize_text_field( wp_unslash( $_POST['ufsc_target_season'] ) ) )
        : '';
    if ( '' !== $posted_season && $posted_season !== $season ) {
        ufsc_renewal_recovery_redirect_error( __( 'Saison cible invalide.', 'ufsc-clubs' ) );
    }

    $source_ids = ufsc_renewal_recovery_posted_source_ids();
    if ( ! $source_ids ) {
        ufsc_renewal_recovery_redirect_error( __( 'Aucune licence à renouveler n’a été sélectionnée.', 'ufsc-clubs' ) );
    }

    $profiles = isset( $_POST['renewal_profiles'] ) && is_array( $_POST['renewal_profiles'] )
        ? map_deep( wp_unslash( $_POST['renewal_profiles'] ), 'sanitize_text_field' )
        : array();

    $included = 0;
    $paid     = 0;
    $errors   = array();

    foreach ( $source_ids as $source_id ) {
        $target_id = 0;
        try {
            $result = ufsc_renewal_recovery_process_source(
                $source_id,
                $club_id,
                $season,
                isset( $profiles[ $source_id ] ) && is_array( $profiles[ $source_id ] ) ? $profiles[ $source_id ] : array()
            );

            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
                continue;
            }

            $target_id = absint( $result['target_id'] ?? 0 );
            if ( ! empty( $result['included'] ) ) {
                $included++;
            } elseif ( ! empty( $result['added'] ) || ! empty( $result['existing'] ) || ! empty( $result['paid'] ) ) {
                $paid++;
            }
        } catch ( Throwable $error ) {
            if ( $target_id > 0 ) {
                ufsc_renewal_recovery_keep_editable( $target_id, $club_id );
            }
            ufsc_renewal_recovery_log(
                'ufsc_renewal_recovery_throwable',
                array(
                    'club_id'       => $club_id,
                    'source_id'     => absint( $source_id ),
                    'target_id'     => $target_id,
                    'error_class'   => get_class( $error ),
                    'error_message' => sanitize_text_field( $error->getMessage() ),
                ),
                'error'
            );
            $errors[] = __( 'Le renouvellement a été conservé sans perte de données, mais son ajout au panier a rencontré une erreur technique. Vous pouvez reprendre le dossier.', 'ufsc-clubs' );
        }
    }

    if ( $errors ) {
        ufsc_renewal_recovery_redirect_error( implode( ' ', array_values( array_unique( $errors ) ) ) );
    }

    if ( $paid > 0 ) {
        $persisted = function_exists( 'ufsc_persist_woocommerce_cart' ) ? ufsc_persist_woocommerce_cart() : true;
        if ( is_wp_error( $persisted ) ) {
            ufsc_renewal_recovery_redirect_error( $persisted->get_error_message() );
        }
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

/**
 * Cart-label postcondition. Any item carrying a licence ID is a licence request,
 * never an affiliation request. This also repairs legacy items already in the
 * session whose request_type alone was misleading.
 */
function ufsc_renewal_recovery_fix_cart_request_label( $item_data, $cart_item ) {
    if ( ! is_array( $item_data ) || ! is_array( $cart_item ) ) {
        return $item_data;
    }

    $has_licence = ! empty( $cart_item['ufsc_licence_id'] ) || ! empty( $cart_item['ufsc_license_ids'] ) || ! empty( $cart_item['ufsc_licence_ids'] );
    if ( ! $has_licence ) {
        return $item_data;
    }

    $action    = sanitize_key( (string) ( $cart_item['ufsc_action'] ?? '' ) );
    $item_type = sanitize_key( (string) ( $cart_item['ufsc_item_type'] ?? '' ) );
    $operation = sanitize_key( (string) ( $cart_item['ufsc_operation_type'] ?? '' ) );
    $is_renewal = 'renew_licence' === $action
        || 'licence_renewal' === $item_type
        || 'renewal' === $operation
        || ! empty( $cart_item['ufsc_renew_from_licence_id'] )
        || ! empty( $cart_item['ufsc_previous_licence_id'] );

    $filtered = array();
    foreach ( $item_data as $row ) {
        $key = is_array( $row ) && isset( $row['key'] ) ? wp_strip_all_tags( (string) $row['key'] ) : '';
        if ( 'Demande' !== $key ) {
            $filtered[] = $row;
        }
    }
    $filtered[] = array(
        'key'   => __( 'Demande', 'ufsc-clubs' ),
        'value' => $is_renewal ? __( 'Renouvellement de licence', 'ufsc-clubs' ) : __( 'Nouvelle licence', 'ufsc-clubs' ),
    );

    return $filtered;
}
add_filter( 'woocommerce_get_item_data', 'ufsc_renewal_recovery_fix_cart_request_label', 1005, 2 );
