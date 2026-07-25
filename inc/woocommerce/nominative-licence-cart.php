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
}

/**
 * Resolve the single licence/person identifier carried by a cart item.
 *
 * @param array $cart_item Cart item data.
 * @return int
 */
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

/**
 * Determine whether a cart line is an UFSC licence line.
 *
 * @param array $cart_item Cart item data.
 * @return bool
 */
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

/**
 * Reject anonymous or grouped licence lines before checkout.
 *
 * @return void
 */
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

/**
 * Read the nominative identity from the licence database row.
 *
 * @param int $licence_id Licence ID.
 * @return array<string,string>
 */
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
        'licence_id'    => (string) $licence_id,
        'prenom'        => isset( $row->prenom ) ? trim( (string) $row->prenom ) : '',
        'nom'           => $last_name,
        'date_naissance'=> isset( $row->date_naissance ) ? trim( (string) $row->date_naissance ) : '',
        'club_id'       => isset( $row->club_id ) ? (string) absint( $row->club_id ) : '',
    );
}

/**
 * Show the person's identity in cart and checkout review.
 *
 * @param array $item_data Existing display data.
 * @param array $cart_item Cart item data.
 * @return array
 */
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

/**
 * Persist an immutable nominative snapshot on the WooCommerce order line.
 *
 * Existing public UFSC meta keys are preserved. These additional private keys
 * make the payment auditable even if the licence record is later corrected.
 *
 * @param WC_Order_Item_Product $item Order item.
 * @param string                $cart_item_key Cart item key.
 * @param array                 $values Cart item values.
 * @param WC_Order              $order Order.
 * @return void
 */
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
