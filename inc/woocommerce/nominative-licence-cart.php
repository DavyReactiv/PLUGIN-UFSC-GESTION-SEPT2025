<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enforce one nominative WooCommerce line per UFSC licence.
 *
 * New licence lines are linked to an existing licence draft ID. Renewal lines
 * are linked to the source licence ID. Quantity-only anonymous licence sales
 * are rejected at cart/checkout level.
 */
function ufsc_init_nominative_licence_cart() {
    if ( ! function_exists( 'ufsc_is_woocommerce_active' ) || ! ufsc_is_woocommerce_active() ) {
        return;
    }

    add_action( 'woocommerce_check_cart_items', 'ufsc_validate_nominative_licence_cart' );
    add_filter( 'woocommerce_get_item_data', 'ufsc_render_nominative_licence_cart_data', 20, 2 );
    add_action( 'woocommerce_checkout_create_order_line_item', 'ufsc_persist_nominative_licence_order_snapshot', 20, 4 );
    add_action( 'admin_post_ufsc_add_bulk_licence_renewals', 'ufsc_handle_bulk_licence_renewals' );
}

function ufsc_get_cart_item_nominative_licence_id( $cart_item ) {
    foreach ( array( 'ufsc_licence_id', 'ufsc_source_licence_id', 'ufsc_renew_from_licence_id' ) as $key ) {
        if ( ! empty( $cart_item[ $key ] ) ) {
            return absint( $cart_item[ $key ] );
        }
    }

    foreach ( array( 'ufsc_licence_ids', 'ufsc_license_ids' ) as $key ) {
        if ( ! empty( $cart_item[ $key ] ) && is_array( $cart_item[ $key ] ) ) {
            $ids = array_values( array_unique( array_filter( array_map( 'absint', $cart_item[ $key ] ) ) ) );
            if ( 1 === count( $ids ) ) {
                return $ids[0];
            }
        }
    }

    return 0;
}

function ufsc_is_nominative_licence_cart_item( $cart_item ) {
    $action    = isset( $cart_item['ufsc_action'] ) ? sanitize_key( (string) $cart_item['ufsc_action'] ) : '';
    $item_type = isset( $cart_item['ufsc_item_type'] ) ? sanitize_key( (string) $cart_item['ufsc_item_type'] ) : '';

    if ( 'renew_licence' === $action || in_array( $item_type, array( 'licence', 'new_licence', 'licence_renewal' ), true ) ) {
        return true;
    }

    $product_id         = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
    $licence_product_id = function_exists( 'ufsc_get_licence_product_id' ) ? absint( ufsc_get_licence_product_id() ) : 0;

    return $licence_product_id > 0 && $product_id === $licence_product_id;
}

function ufsc_validate_nominative_licence_cart() {
    if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
        return;
    }

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( ! ufsc_is_nominative_licence_cart_item( $cart_item ) ) {
            continue;
        }

        $quantity   = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 0;
        $licence_id = ufsc_get_cart_item_nominative_licence_id( $cart_item );

        if ( 1 !== $quantity || $licence_id <= 0 ) {
            wc_add_notice(
                __( 'Chaque licence doit correspondre à une personne identifiée et apparaître sur une ligne distincte du panier.', 'ufsc-clubs' ),
                'error'
            );
            return;
        }
    }
}

function ufsc_get_nominative_licence_snapshot( $licence_id ) {
    global $wpdb;

    $licence_id = absint( $licence_id );
    if ( $licence_id <= 0 || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return array();
    }

    $table = ufsc_get_licences_table();
    $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) );
    if ( ! $row ) {
        return array();
    }

    $last_name = '';
    foreach ( array( 'nom_licence', 'nom' ) as $field ) {
        if ( isset( $row->{$field} ) && '' !== trim( (string) $row->{$field} ) ) {
            $last_name = trim( (string) $row->{$field} );
            break;
        }
    }

    return array(
        'licence_id'     => (string) $licence_id,
        'prenom'         => isset( $row->prenom ) ? trim( (string) $row->prenom ) : '',
        'nom'            => $last_name,
        'date_naissance' => isset( $row->date_naissance ) ? trim( (string) $row->date_naissance ) : '',
        'club_id'        => isset( $row->club_id ) ? (string) absint( $row->club_id ) : '',
    );
}

function ufsc_render_nominative_licence_cart_data( $item_data, $cart_item ) {
    if ( ! ufsc_is_nominative_licence_cart_item( $cart_item ) ) {
        return $item_data;
    }

    $licence_id = ufsc_get_cart_item_nominative_licence_id( $cart_item );
    $snapshot   = ufsc_get_nominative_licence_snapshot( $licence_id );
    if ( empty( $snapshot ) ) {
        return $item_data;
    }

    $name = trim( $snapshot['prenom'] . ' ' . $snapshot['nom'] );
    $item_data[] = array(
        'key'   => __( 'Licencié(e)', 'ufsc-clubs' ),
        'value' => $name ? $name : sprintf( __( 'Dossier licence #%d', 'ufsc-clubs' ), $licence_id ),
    );

    if ( ! empty( $snapshot['date_naissance'] ) ) {
        $item_data[] = array(
            'key'   => __( 'Date de naissance', 'ufsc-clubs' ),
            'value' => $snapshot['date_naissance'],
        );
    }

    $action = isset( $cart_item['ufsc_action'] ) ? sanitize_key( (string) $cart_item['ufsc_action'] ) : '';
    $item_data[] = array(
        'key'   => __( 'Type de demande', 'ufsc-clubs' ),
        'value' => 'renew_licence' === $action ? __( 'Renouvellement', 'ufsc-clubs' ) : __( 'Nouvelle licence', 'ufsc-clubs' ),
    );

    $season = isset( $cart_item['ufsc_target_season'] ) ? sanitize_text_field( (string) $cart_item['ufsc_target_season'] ) : ( isset( $cart_item['ufsc_season'] ) ? sanitize_text_field( (string) $cart_item['ufsc_season'] ) : '' );
    if ( $season ) {
        $item_data[] = array(
            'key'   => __( 'Saison', 'ufsc-clubs' ),
            'value' => $season,
        );
    }

    return $item_data;
}

function ufsc_persist_nominative_licence_order_snapshot( $item, $cart_item_key, $values, $order ) {
    if ( ! ufsc_is_nominative_licence_cart_item( $values ) ) {
        return;
    }

    $licence_id = ufsc_get_cart_item_nominative_licence_id( $values );
    $snapshot   = ufsc_get_nominative_licence_snapshot( $licence_id );
    if ( empty( $snapshot ) ) {
        return;
    }

    $action = isset( $values['ufsc_action'] ) ? sanitize_key( (string) $values['ufsc_action'] ) : '';
    $season = isset( $values['ufsc_target_season'] ) ? sanitize_text_field( (string) $values['ufsc_target_season'] ) : ( isset( $values['ufsc_season'] ) ? sanitize_text_field( (string) $values['ufsc_season'] ) : '' );

    $item->add_meta_data( '_ufsc_nominative_licence_id', $licence_id, true );
    $item->add_meta_data( '_ufsc_nominative_first_name', $snapshot['prenom'], true );
    $item->add_meta_data( '_ufsc_nominative_last_name', $snapshot['nom'], true );
    $item->add_meta_data( '_ufsc_nominative_birthdate', $snapshot['date_naissance'], true );
    $item->add_meta_data( '_ufsc_nominative_club_id', absint( $snapshot['club_id'] ), true );
    $item->add_meta_data( '_ufsc_nominative_request_type', 'renew_licence' === $action ? 'renewal' : 'new', true );
    if ( $season ) {
        $item->add_meta_data( '_ufsc_nominative_season', $season, true );
    }
}

/**
 * Add several renewals in one action while preserving one person per cart line.
 */
function ufsc_handle_bulk_licence_renewals() {
    if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    check_admin_referer( 'ufsc_add_bulk_licence_renewals', '_ufsc_bulk_nonce' );

    if ( function_exists( 'wc_load_cart' ) ) {
        wc_load_cart();
    }
    if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart || ! function_exists( 'wc_get_product' ) ) {
        wp_safe_redirect( add_query_arg( 'ufsc_error', rawurlencode( __( 'Panier indisponible.', 'ufsc-clubs' ) ), wp_get_referer() ?: home_url() ) );
        exit;
    }

    $product_id          = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
    $expected_product_id = function_exists( 'ufsc_get_licence_product_id' ) ? absint( ufsc_get_licence_product_id() ) : 0;
    $target_season       = isset( $_POST['ufsc_target_season'] ) ? sanitize_text_field( wp_unslash( $_POST['ufsc_target_season'] ) ) : '';
    $ids_raw             = isset( $_POST['ufsc_renew_licence_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ufsc_renew_licence_ids'] ) ) : '';
    $licence_ids         = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $ids_raw ) ) ) ) );

    if ( $product_id <= 0 || $product_id !== $expected_product_id || ! wc_get_product( $product_id ) ) {
        wc_add_notice( __( 'Produit de licence invalide.', 'ufsc-clubs' ), 'error' );
        wp_safe_redirect( wp_get_referer() ?: home_url() );
        exit;
    }

    if ( ! preg_match( '/^\d{4}-\d{4}$/', $target_season ) || empty( $licence_ids ) || count( $licence_ids ) > 50 ) {
        wc_add_notice( __( 'Sélection de renouvellement invalide.', 'ufsc-clubs' ), 'error' );
        wp_safe_redirect( wp_get_referer() ?: home_url() );
        exit;
    }

    if ( function_exists( 'ufsc_is_renewal_window_open' ) && ! ufsc_is_renewal_window_open() ) {
        wc_add_notice( __( 'La période de renouvellement n’est pas ouverte.', 'ufsc-clubs' ), 'error' );
        wp_safe_redirect( wp_get_referer() ?: home_url() );
        exit;
    }

    $user_club_id = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
    $can_manage   = current_user_can( 'manage_options' ) || ( class_exists( 'UFSC_Permissions' ) && current_user_can( UFSC_Permissions::CAP_LICENCES_MANAGE ) );
    if ( $user_club_id <= 0 && ! $can_manage ) {
        wc_add_notice( __( 'Club introuvable pour cet utilisateur.', 'ufsc-clubs' ), 'error' );
        wp_safe_redirect( wp_get_referer() ?: home_url() );
        exit;
    }

    global $wpdb;
    $table          = function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : '';
    $current_season = function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '';
    $added          = 0;
    $skipped        = 0;

    foreach ( $licence_ids as $licence_id ) {
        $row = $table ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) ) : null;
        $club_id = $row ? absint( $row->club_id ?? 0 ) : 0;
        if ( ! $row || $club_id <= 0 || ( ! $can_manage && $club_id !== $user_club_id ) ) {
            $skipped++;
            continue;
        }

        $row_season = function_exists( 'ufsc_get_licence_season_label' ) ? ufsc_get_licence_season_label( $row ) : ( function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $row ) : '' );
        if ( ! $current_season || $row_season !== $current_season ) {
            $skipped++;
            continue;
        }

        if ( function_exists( 'ufsc_is_club_affiliated_for_season' ) && ! ufsc_is_club_affiliated_for_season( $club_id, $target_season ) ) {
            $skipped++;
            continue;
        }
        if ( function_exists( 'ufsc_get_renewed_licence_marker' ) && ufsc_get_renewed_licence_marker( $licence_id, $target_season ) ) {
            $skipped++;
            continue;
        }
        if ( function_exists( 'ufsc_wc_find_equivalent_renewed_licence_id' ) && ufsc_wc_find_equivalent_renewed_licence_id( $row, $club_id, $target_season ) > 0 ) {
            $skipped++;
            continue;
        }
        if ( function_exists( 'ufsc_wc_has_pending_renewal_order' ) && ufsc_wc_has_pending_renewal_order( 'renew_licence', $club_id, $target_season, $licence_id ) ) {
            $skipped++;
            continue;
        }
        if ( function_exists( 'ufsc_cart_has_renewal_item' ) && ufsc_cart_has_renewal_item( 'renew_licence', $club_id, $target_season, $licence_id ) ) {
            $skipped++;
            continue;
        }

        $last_name = isset( $row->nom_licence ) && '' !== trim( (string) $row->nom_licence ) ? (string) $row->nom_licence : (string) ( $row->nom ?? '' );
        $cart_item_data = array(
            'ufsc_action'                   => 'renew_licence',
            'ufsc_item_type'                => 'licence_renewal',
            'ufsc_user_id'                  => get_current_user_id(),
            'ufsc_source'                   => 'ufsc_gestion_bulk',
            'ufsc_season'                   => $target_season,
            'ufsc_target_season'            => $target_season,
            'ufsc_club_id'                  => $club_id,
            'ufsc_renew_from_licence_id'    => $licence_id,
            'ufsc_source_licence_id'        => $licence_id,
            'ufsc_nom'                      => $last_name,
            'ufsc_prenom'                   => (string) ( $row->prenom ?? '' ),
            'ufsc_date_naissance'           => (string) ( $row->date_naissance ?? '' ),
            'ufsc_sexe'                     => (string) ( $row->sexe ?? '' ),
            'ufsc_nominative_unique_key'    => 'renewal-' . $licence_id . '-' . $target_season,
        );

        if ( WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data ) ) {
            $added++;
        } else {
            $skipped++;
        }
    }

    if ( $added > 0 ) {
        wc_add_notice(
            sprintf(
                _n( '%d licence nominative ajoutée au panier.', '%d licences nominatives ajoutées au panier.', $added, 'ufsc-clubs' ),
                $added
            ),
            'success'
        );
    }
    if ( $skipped > 0 ) {
        wc_add_notice(
            sprintf(
                _n( '%d licence n’a pas été ajoutée car elle est invalide, déjà renouvelée ou déjà en cours de règlement.', '%d licences n’ont pas été ajoutées car elles sont invalides, déjà renouvelées ou déjà en cours de règlement.', $skipped, 'ufsc-clubs' ),
                $skipped
            ),
            'notice'
        );
    }

    wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url() );
    exit;
}
