<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce cart integration for UFSC Gestion
 * Handles secure add-to-cart and meta transfer to orders
 *
 * FAIL-CLOSED / ZERO REGRESSION PRINCIPLES
 * - Keeps existing public metas/keys as-is (ufsc_club_id, ufsc_license_ids, ufsc_licence_ids, ufsc_licence_id, etc.)
 * - Defensive guards (Woo not loaded, WC()->cart null, session missing, etc.)
 * - Add-to-cart is nonce-protected and ownership-checked
 * - Revert licence status on cart item removal ONLY when no reliable Woo order linkage exists
 * - Adds filter ufsc_cart_max_licence_ids (default 50, bounded 1..500)
 */

/**
 * Initialize cart integration hooks
 */
function ufsc_init_cart_integration() {
	// Handle secure add-to-cart requests even when WooCommerce is unavailable so
	// renewal forms fail closed with a redirect instead of an unhandled action.
	add_action( 'admin_post_ufsc_add_to_cart', 'ufsc_handle_add_to_cart_secure' );
	add_action( 'admin_post_nopriv_ufsc_add_to_cart', 'ufsc_handle_add_to_cart_secure' );
	add_action( 'admin_post_ufsc_bulk_renew_licences', 'ufsc_handle_bulk_renew_licences' );
	add_action( 'admin_post_nopriv_ufsc_bulk_renew_licences', 'ufsc_handle_bulk_renew_licences' );

	if ( ! function_exists( 'ufsc_is_woocommerce_active' ) || ! ufsc_is_woocommerce_active() ) {
		return;
	}

	// Transfer meta data from cart to order
	add_action( 'woocommerce_checkout_create_order_line_item', 'ufsc_transfer_cart_meta_to_order', 10, 4 );
	add_filter( 'woocommerce_cart_item_name', 'ufsc_renewal_cart_item_name', 10, 3 );
	add_filter( 'woocommerce_add_cart_item_data', 'ufsc_capture_affiliation_product_context', 10, 2 );
	add_filter( 'woocommerce_add_to_cart_quantity', 'ufsc_force_affiliation_product_quantity_one', 10, 2 );
	add_filter( 'woocommerce_add_to_cart_validation', 'ufsc_validate_licence_affiliation_add_to_cart', 10, 5 );
	add_filter( 'woocommerce_get_cart_item_from_session', 'ufsc_validate_licence_affiliation_cart_session', 20, 3 );
	add_action( 'woocommerce_check_cart_items', 'ufsc_validate_licence_affiliation_checkout' );
	add_action( 'woocommerce_checkout_process', 'ufsc_validate_licence_affiliation_checkout' );

	// Revert pending licence status when cart items are removed without real order linkage.
	add_action( 'woocommerce_remove_cart_item', 'ufsc_handle_remove_cart_item_licence_revert', 10, 2 );
	add_action( 'woocommerce_cart_item_removed', 'ufsc_handle_cart_item_removed_licence_revert', 10, 2 );
	add_action( 'woocommerce_before_cart_emptied', 'ufsc_snapshot_cart_before_empty', 10, 1 );
	add_action( 'woocommerce_cart_emptied', 'ufsc_handle_cart_emptied_licence_revert', 10 );
}

function ufsc_renewal_cart_item_name( $name, $cart_item, $cart_item_key = '' ) {
	if ( 'renew_licence' !== sanitize_key( (string) ( $cart_item['ufsc_action'] ?? '' ) ) ) { return $name; }
	$person = trim( sanitize_text_field( (string) ( $cart_item['ufsc_prenom'] ?? '' ) ) . ' ' . sanitize_text_field( (string) ( $cart_item['ufsc_nom'] ?? '' ) ) );
	$season = sanitize_text_field( (string) ( $cart_item['ufsc_target_season'] ?? '' ) );
	return esc_html( sprintf( __( 'Renouvellement licence — %1$s — Saison %2$s', 'ufsc-clubs' ), $person, $season ) );
}

/** Validate and add archive renewals as distinct quantity-one cart lines. */
function ufsc_add_renewal_sources_to_cart( $product_id, $club_id, $source_ids, $season, $profiles = array() ) {
	global $wpdb;
	$result = array( 'added' => array(), 'skipped' => array() );
	$source_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $source_ids ) ) ) );
	if ( ! $source_ids ) { return $result; }
	if ( ! absint( $product_id ) || ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
		foreach ( $source_ids as $id ) { $result['skipped'][ $id ] = __( 'Le panier WooCommerce est indisponible.', 'ufsc-clubs' ); }
		return $result;
	}
	$gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id, $season ) : array( 'allowed' => false, 'message' => __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ) );
	if ( empty( $gate['allowed'] ) ) {
		if ( function_exists( 'ufsc_log_licence_affiliation_refusal' ) ) { ufsc_log_licence_affiliation_refusal( $gate, 'bulk_renewal' ); }
		foreach ( (array) $source_ids as $id ) { $result['skipped'][ absint( $id ) ] = $gate['message'] ?? __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ); }
		return $result;
	}
	$table = ufsc_get_licences_table();
	foreach ( $source_ids as $source_id ) {
		$source = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $source_id ) );
		if ( ! $source || absint( $source->club_id ?? 0 ) !== absint( $club_id ) ) { $result['skipped'][ $source_id ] = __( 'Licence inaccessible.', 'ufsc-clubs' ); continue; }
		if ( empty( $source->nom ) || empty( $source->prenom ) || empty( $source->date_naissance ) ) { $result['skipped'][ $source_id ] = __( 'Identité incomplète.', 'ufsc-clubs' ); continue; }
		$target_start = (int) substr( $season, 0, 4 );
		$expected_source_season = $target_start ? ( $target_start - 1 ) . '-' . $target_start : '';
		$source_season = function_exists( 'ufsc_get_licence_season_label' ) ? ufsc_get_licence_season_label( $source ) : ( function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $source ) : ( $source->season ?? $source->saison ?? '' ) );
		if ( $expected_source_season && $source_season && $source_season !== $expected_source_season ) { $result['skipped'][$source_id] = __( 'La licence source ne correspond pas à la saison précédente.', 'ufsc-clubs' ); continue; }
		$source_status = sanitize_key( (string) ( $source->statut ?? $source->status ?? '' ) );
		if ( in_array( $source_status, array( 'suspended', 'suspendu', 'rejected', 'refused', 'refuse' ), true ) ) { $result['skipped'][ $source_id ] = __( 'Le statut de cette licence interdit son renouvellement.', 'ufsc-clubs' ); continue; }
		if ( is_callable( array( 'UFSC_Renewal_Service', 'can_renew' ) ) ) {
			$renewable = UFSC_Renewal_Service::can_renew( $source, $club_id, $season );
			if ( is_wp_error( $renewable ) ) { $result['skipped'][ $source_id ] = $renewable->get_error_message(); continue; }
		}
		$profile = UFSC_Renewal_Service::sanitize_renewal_updates( $source, $profiles[$source_id] ?? array() );
		if ( ! empty( $profile['errors'] ) ) { $result['skipped'][$source_id] = implode( ' ', array_values( $profile['errors'] ) ); continue; }
		$level = $profile['data']['fighter_level'];
		$weight = $profile['data']['poids'];
		$category = class_exists( 'UFSC_Category_Repository' ) ? UFSC_Category_Repository::detect_for_athlete( (object) array_merge( (array) $source, $profile['data'] ), UFSC_Category_Repository::DEFAULT_DISCIPLINE, $season ) : array();
		if ( function_exists( 'ufsc_get_renewed_licence_marker' ) && ufsc_get_renewed_licence_marker( $source_id, $season ) ) { $result['skipped'][ $source_id ] = __( 'Licence déjà renouvelée.', 'ufsc-clubs' ); continue; }
		if ( ufsc_wc_has_pending_renewal_order( 'renew_licence', $club_id, $season, $source_id ) || ufsc_cart_has_renewal_item( 'renew_licence', $club_id, $season, $source_id ) ) { $result['skipped'][ $source_id ] = __( 'Renouvellement déjà au panier ou en attente.', 'ufsc-clubs' ); continue; }
		$data = array( 'ufsc_action' => 'renew_licence', 'ufsc_club_id' => absint( $club_id ), 'ufsc_target_season' => $season, 'ufsc_renew_from_licence_id' => $source_id, 'ufsc_previous_licence_id' => $source_id, 'ufsc_person_identifier' => UFSC_Renewal_Service::person_key( $source, $club_id ), 'ufsc_numero_licence_ufsc' => UFSC_Identifier_Resolver::read( $source, 'licence_ufsc' ), 'ufsc_request_type' => 'renewal', 'ufsc_item_type' => 'licence_renewal', 'ufsc_user_id' => get_current_user_id(), 'ufsc_nom' => (string) ( $profile['data']['nom'] ?? $source->nom ), 'ufsc_prenom' => (string) ( $profile['data']['prenom'] ?? $source->prenom ), 'ufsc_fighter_level' => $level, 'ufsc_weight' => $weight, 'ufsc_category' => trim( (string) ( $category['age_category_label'] ?? '' ) . ( ! empty( $category['weight_category_label'] ) ? ' — ' . $category['weight_category_label'] : '' ) ), 'ufsc_renewal_updates' => $profile['data'], 'ufsc_renewal_changes' => $profile['changes'], 'ufsc_sensitive_identity_change' => ! empty( $profile['sensitive_identity_change'] ), 'ufsc_cart_identity' => wp_generate_uuid4(), 'quantity' => 1 );
		$key = WC()->cart->add_to_cart( absint( $product_id ), 1, 0, array(), $data );
		if ( $key ) { $result['added'][] = $source_id; } else { $result['skipped'][ $source_id ] = __( 'Ajout au panier impossible.', 'ufsc-clubs' ); }
	}
	return $result;
}

function ufsc_handle_bulk_renew_licences() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( __( 'Méthode non autorisée.', 'ufsc-clubs' ), 405 ); }
	$club_id = absint( $_POST['ufsc_club_id'] ?? 0 ); check_admin_referer( 'ufsc_bulk_renew_licences_' . $club_id );
	$user_club = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
	if ( ! is_user_logged_in() || $club_id !== $user_club ) { wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) ); }
	$season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ufsc_get_current_season();
	$posted_season = isset( $_POST['ufsc_target_season'] ) && ! is_array( $_POST['ufsc_target_season'] ) ? sanitize_text_field( wp_unslash( $_POST['ufsc_target_season'] ) ) : '';
	if ( $posted_season && $posted_season !== $season ) { wp_die( __( 'Saison cible invalide.', 'ufsc-clubs' ) ); }
	$ids = isset( $_POST['ufsc_renew_ids'] ) && is_array( $_POST['ufsc_renew_ids'] ) ? $_POST['ufsc_renew_ids'] : ( isset( $_POST['source_ids'] ) && is_array( $_POST['source_ids'] ) ? $_POST['source_ids'] : ( isset( $_POST['renew_licence_ids'] ) && is_array( $_POST['renew_licence_ids'] ) ? $_POST['renew_licence_ids'] : array() ) );
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	$profiles = isset( $_POST['renewal_profiles'] ) && is_array( $_POST['renewal_profiles'] ) ? wp_unslash( $_POST['renewal_profiles'] ) : array();
	$intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) ) : 'verify';
	$state_key = 'ufsc_renewal_front_' . get_current_user_id() . '_' . $club_id;
	if ( 'cancel' === $intent ) {
		delete_transient( $state_key );
		$return_url = remove_query_arg( array( 'ufsc_renew_step', 'renew_source_id', 'target_season', 'ufsc_error' ), wp_get_referer() );
		wp_safe_redirect( $return_url ); exit;
	}
	if ( in_array( $intent, array( 'save_draft', 'verify' ), true ) ) {
		set_transient( $state_key, array( 'ids' => $ids, 'profiles' => $profiles, 'state' => $intent, 'saved_at' => time() ), 30 * 60 );
		$return_url = wp_get_referer();
		if ( 'verify' === $intent && $ids ) { $return_url = add_query_arg( 'ufsc_renew_step', 2, $return_url ); }
		if ( function_exists( 'wc_add_notice' ) ) { wc_add_notice( 'save_draft' === $intent ? __( 'Brouillon enregistré. Le panier n’a pas été modifié.', 'ufsc-clubs' ) : __( 'Sélection enregistrée. Vérifiez et complétez les informations.', 'ufsc-clubs' ), 'success' ); }
		wp_safe_redirect( $return_url ); exit;
	}
	$result = ufsc_add_renewal_sources_to_cart( ufsc_get_licence_product_id(), $club_id, $ids, $season, $profiles );
	if ( $result['skipped'] && function_exists( 'set_transient' ) ) { set_transient( 'ufsc_renewal_front_' . get_current_user_id() . '_' . $club_id, array( 'ids' => array_map( 'absint', $ids ), 'profiles' => $profiles ), 30 * 60 ); }
	elseif ( function_exists( 'delete_transient' ) ) { delete_transient( 'ufsc_renewal_front_' . get_current_user_id() . '_' . $club_id ); }
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'ufsc_wc_log' ) ) { ufsc_wc_log( 'ufsc_front_renewal_handler', array( 'user_id' => get_current_user_id(), 'club_id' => $club_id, 'selection' => array_map( 'absint', $ids ), 'target_season' => $season, 'added' => count( $result['added'] ), 'skipped' => array_keys( $result['skipped'] ) ) ); }
	foreach ( $result['skipped'] as $id => $reason ) { wc_add_notice( sprintf( __( 'Licence #%1$d ignorée : %2$s', 'ufsc-clubs' ), $id, $reason ), 'notice' ); }
	if ( $result['added'] ) { wc_add_notice( sprintf( _n( '%d licence ajoutée au panier.', '%d licences ajoutées au panier.', count( $result['added'] ), 'ufsc-clubs' ), count( $result['added'] ) ), 'success' ); }
	wp_safe_redirect( function_exists( 'wc_get_cart_url' ) && $result['added'] ? wc_get_cart_url() : wp_get_referer() ); exit;
}

/** Preserve a validated product-page renewal context in the cart. */
function ufsc_force_affiliation_product_quantity_one( $quantity, $product_id ) {
	return absint( $product_id ) === absint( ufsc_get_affiliation_product_id() ) ? 1 : $quantity;
}

function ufsc_capture_affiliation_product_context( $cart_item_data, $product_id ) {
	$action = isset( $_REQUEST['ufsc_action'] ) && ! is_array( $_REQUEST['ufsc_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['ufsc_action'] ) ) : '';
	if ( ! in_array( $action, array( 'renewal', 'renew_affiliation' ), true ) || absint( $product_id ) !== absint( ufsc_get_affiliation_product_id() ) ) {
		return $cart_item_data;
	}

	$club_id = isset( $_REQUEST['ufsc_club_id'] ) ? absint( $_REQUEST['ufsc_club_id'] ) : 0;
	$season  = isset( $_REQUEST['ufsc_target_season'] ) && ! is_array( $_REQUEST['ufsc_target_season'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ufsc_target_season'] ) ) : '';
	$user_club_id = is_user_logged_in() && function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
	$current_season = class_exists( 'UFSC_Season_Service' )
		? UFSC_Season_Service::get_current_season()
		: ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );

	if ( $club_id <= 0 || $club_id !== $user_club_id || $season !== $current_season || ufsc_is_club_affiliated_for_season( $club_id, $season ) || ufsc_wc_has_pending_renewal_order( 'renew_affiliation', $club_id, $season ) || ufsc_cart_has_renewal_item( 'renew_affiliation', $club_id, $season ) ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( sprintf( __( 'Une demande d’affiliation %s est déjà présente dans votre panier ou en attente de traitement.', 'ufsc-clubs' ), $season ), 'notice' );
		}
		return $cart_item_data;
	}

	$cart_item_data['ufsc_action'] = 'renew_affiliation';
	$cart_item_data['ufsc_request_type'] = 'renewal';
	$cart_item_data['ufsc_item_type'] = 'affiliation_renewal';
	$cart_item_data['ufsc_club_id'] = $club_id;
	$cart_item_data['ufsc_target_season'] = $season;
	$cart_item_data['ufsc_previous_affiliation_id'] = isset( $_REQUEST['ufsc_previous_affiliation_id'] ) ? absint( $_REQUEST['ufsc_previous_affiliation_id'] ) : 0;
	$cart_item_data['ufsc_request_date'] = current_time( 'mysql' );
	$cart_item_data['ufsc_product_id'] = absint( $product_id );
	$cart_item_data['ufsc_user_id'] = get_current_user_id();
	$cart_item_data['ufsc_return_url'] = wp_get_referer() ? esc_url_raw( wp_get_referer() ) : home_url( '/' );
	$cart_item_data['quantity'] = 1;

	return $cart_item_data;
}

/**
 * Return an existing pending affiliation renewal payment URL when WooCommerce can still pay it.
 *
 * @param int    $club_id Club ID.
 * @param string $season  Target season.
 * @return string
 */
function ufsc_get_pending_affiliation_payment_url( $club_id, $season ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return '';
	}

	$club_id = absint( $club_id );
	$season  = sanitize_text_field( (string) $season );
	if ( $club_id <= 0 || '' === $season ) {
		return '';
	}

	$orders = wc_get_orders(
		array(
			'status'  => array( 'pending', 'on-hold' ),
			'limit'   => 50,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		)
	);

	foreach ( (array) $orders as $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			continue;
		}
		if ( is_callable( array( $order, 'needs_payment' ) ) && ! $order->needs_payment() ) {
			continue;
		}

		foreach ( $order->get_items() as $item ) {
			$item_action = sanitize_key( (string) $item->get_meta( 'ufsc_action', true ) );
			if ( '' === $item_action ) {
				$item_action = sanitize_key( (string) $item->get_meta( '_ufsc_action', true ) );
			}
			$item_type = sanitize_key( (string) $item->get_meta( 'ufsc_item_type', true ) );
			if ( '' === $item_type ) {
				$item_type = sanitize_key( (string) $item->get_meta( '_ufsc_item_type', true ) );
			}
			$item_club_id = absint( $item->get_meta( '_ufsc_club_id', true ) );
			if ( ! $item_club_id ) {
				$item_club_id = absint( $item->get_meta( 'ufsc_club_id', true ) );
			}
			$item_season = sanitize_text_field( (string) $item->get_meta( '_ufsc_target_season', true ) );
			if ( '' === $item_season ) {
				$item_season = sanitize_text_field( (string) $item->get_meta( 'ufsc_target_season', true ) );
			}

			if ( $item_club_id === $club_id && $item_season === $season && ( 'renew_affiliation' === $item_action || 'affiliation_renewal' === $item_type ) ) {
				$url = is_callable( array( $order, 'get_checkout_payment_url' ) ) ? $order->get_checkout_payment_url() : '';
				return is_string( $url ) ? $url : '';
			}
		}
	}

	return '';
}

/**
 * Check whether a renewal item is already present in the WooCommerce cart.
 *
 * @param string $action Renewal action (renew_affiliation|renew_licence).
 * @param int    $club_id Club ID.
 * @param string $season Target season.
 * @param int    $source_licence_id Source licence for licence renewals.
 * @return bool
 */
function ufsc_cart_has_renewal_item( $action, $club_id, $season, $source_licence_id = 0 ) {
    if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
        return false;
    }

    $action            = sanitize_key( (string) $action );
    $club_id           = absint( $club_id );
    $season            = sanitize_text_field( (string) $season );
    $source_licence_id = absint( $source_licence_id );

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $item_action = isset( $cart_item['ufsc_action'] ) ? sanitize_key( (string) $cart_item['ufsc_action'] ) : '';
        if ( $item_action !== $action ) {
            continue;
        }

        $item_club_id = isset( $cart_item['ufsc_club_id'] ) ? absint( $cart_item['ufsc_club_id'] ) : 0;
        $item_season  = isset( $cart_item['ufsc_target_season'] ) ? sanitize_text_field( (string) $cart_item['ufsc_target_season'] ) : '';
        if ( $item_club_id !== $club_id || $item_season !== $season ) {
            continue;
        }

        if ( 'renew_licence' === $action ) {
            $item_source = isset( $cart_item['ufsc_renew_from_licence_id'] ) ? absint( $cart_item['ufsc_renew_from_licence_id'] ) : 0;
            if ( $item_source !== $source_licence_id ) {
                continue;
            }
        }

        return true;
    }

    return false;
}


/**
 * Check whether a pending WooCommerce order already contains the same renewal.
 *
 * @param string $action Renewal action (renew_affiliation|renew_licence).
 * @param int    $club_id Club ID.
 * @param string $season Target season.
 * @param int    $source_licence_id Source licence for licence renewals.
 * @return bool
 */
function ufsc_wc_find_pending_renewal_order( $action, $club_id, $season, $source_licence_id = 0 ) {
    if ( ! function_exists( 'wc_get_orders' ) ) {
        return false;
    }

    $action            = sanitize_key( (string) $action );
    $club_id           = absint( $club_id );
    $season            = sanitize_text_field( (string) $season );
    $source_licence_id = absint( $source_licence_id );
    if ( ! in_array( $action, array( 'renew_affiliation', 'renew_licence' ), true ) || $club_id <= 0 || '' === $season ) {
        return false;
    }

    $statuses = (array) apply_filters(
        'ufsc_pending_renewal_order_statuses',
        array( 'pending', 'on-hold', 'processing' ),
        $action,
        $club_id,
        $season,
        $source_licence_id
    );
    $statuses = array_values( array_unique( array_filter( array_map( 'sanitize_key', $statuses ) ) ) );
    if ( empty( $statuses ) ) {
        return false;
    }

    $limit = absint( apply_filters( 'ufsc_pending_renewal_order_lookup_limit', 200, $action, $club_id, $season ) );
    $limit = max( 10, min( $limit, 1000 ) );

    $orders = wc_get_orders(
        array(
            'status'  => $statuses,
            'limit'   => $limit,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'objects',
        )
    );

    $expected_item_type = ( 'renew_licence' === $action ) ? 'licence_renewal' : 'affiliation_renewal';

    foreach ( (array) $orders as $order ) {
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            continue;
        }

        foreach ( $order->get_items() as $item ) {
            $item_action = sanitize_key( (string) $item->get_meta( 'ufsc_action', true ) );
            if ( '' === $item_action ) {
                $item_action = sanitize_key( (string) $item->get_meta( '_ufsc_action', true ) );
            }

            $item_type = sanitize_key( (string) $item->get_meta( 'ufsc_item_type', true ) );
            if ( '' === $item_type ) {
                $item_type = sanitize_key( (string) $item->get_meta( '_ufsc_item_type', true ) );
            }

            if ( $item_action !== $action && $item_type !== $expected_item_type ) {
                continue;
            }

            $item_club_id = absint( $item->get_meta( 'ufsc_club_id', true ) );
            if ( ! $item_club_id ) {
                $item_club_id = absint( $item->get_meta( '_ufsc_club_id', true ) );
            }
            if ( $item_club_id !== $club_id ) {
                continue;
            }

            $item_season = sanitize_text_field( (string) $item->get_meta( 'ufsc_target_season', true ) );
            if ( '' === $item_season ) {
                $item_season = sanitize_text_field( (string) $item->get_meta( '_ufsc_target_season', true ) );
            }
            if ( '' === $item_season ) {
                $item_season = sanitize_text_field( (string) $item->get_meta( 'ufsc_season', true ) );
            }
            if ( '' === $item_season ) {
                $item_season = sanitize_text_field( (string) $item->get_meta( '_ufsc_season', true ) );
            }
            if ( $item_season !== $season ) {
                continue;
            }

            if ( 'renew_licence' === $action ) {
                $item_source = absint( $item->get_meta( 'ufsc_source_licence_id', true ) );
                if ( ! $item_source ) {
                    $item_source = absint( $item->get_meta( '_ufsc_source_licence_id', true ) );
                }
                if ( ! $item_source ) {
                    $item_source = absint( $item->get_meta( 'ufsc_renew_from_licence_id', true ) );
                }
                if ( ! $item_source ) {
                    $item_source = absint( $item->get_meta( '_ufsc_renew_from_licence_id', true ) );
                }
                if ( $item_source !== $source_licence_id ) {
                    continue;
                }
            }

            return $order;
        }
    }

    return false;
}

/** Boolean compatibility wrapper around the contextual pending-order lookup. */
function ufsc_wc_has_pending_renewal_order( $action, $club_id, $season, $source_licence_id = 0 ) {
    return false !== ufsc_wc_find_pending_renewal_order( $action, $club_id, $season, $source_licence_id );
}

/**
 * Find an equivalent renewed licence for cart/renewal guards.
 *
 * @param object $source Source licence row.
 * @param int    $club_id Club ID.
 * @param string $target_season Target season.
 * @return int Existing licence ID or 0.
 */
function ufsc_wc_find_equivalent_renewed_licence_id( $source, $club_id, $target_season ) {
    global $wpdb;

    if ( ! $source || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return 0;
    }

    $table   = ufsc_get_licences_table();
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : (array) $wpdb->get_col( "DESCRIBE `{$table}`" );
    if ( empty( $columns ) || ! in_array( 'id', $columns, true ) || ! in_array( 'club_id', $columns, true ) ) {
        return 0;
    }

    $clauses = array( 'club_id = %d' );
    $values  = array( absint( $club_id ) );
    foreach ( array( 'nom', 'prenom', 'date_naissance' ) as $field ) {
        $value = isset( $source->{$field} ) ? trim( (string) $source->{$field} ) : '';
        if ( '' !== $value && in_array( $field, $columns, true ) ) {
            $clauses[] = "{$field} = %s";
            $values[]  = $value;
        }
    }
    if ( count( $clauses ) < 4 ) {
        return 0;
    }

    $season_column = '';
    foreach ( array( 'paid_season', 'season', 'saison', 'season_end_year' ) as $candidate ) {
        if ( in_array( $candidate, $columns, true ) ) {
            $season_column = $candidate;
            break;
        }
    }
    if ( '' === $season_column ) {
        return 0;
    }

    if ( 'season_end_year' === $season_column ) {
        $target_end_year = function_exists( 'ufsc_get_season_end_year_from_label' ) ? absint( ufsc_get_season_end_year_from_label( $target_season ) ) : 0;
        if ( $target_end_year <= 0 ) {
            return 0;
        }
        $clauses[] = 'season_end_year = %d';
        $values[]  = $target_end_year;
    } else {
        $clauses[] = "{$season_column} = %s";
        $values[]  = sanitize_text_field( (string) $target_season );
    }

    $source_id = absint( $source->id ?? 0 );
    if ( $source_id > 0 && in_array( 'previous_licence_id', $columns, true ) ) {
        $trace_clauses = $clauses;
        $trace_values  = $values;
        $trace_clauses[] = 'previous_licence_id = %d';
        $trace_values[]  = $source_id;
        $trace_sql = "SELECT id FROM `{$table}` WHERE " . implode( ' AND ', $trace_clauses ) . ' LIMIT 1';
        $trace_id  = absint( $wpdb->get_var( $wpdb->prepare( $trace_sql, ...$trace_values ) ) );
        if ( $trace_id > 0 ) {
            return $trace_id;
        }
    }
    if ( $source_id > 0 ) {
        $clauses[] = 'id <> %d';
        $values[]  = $source_id;
    }

    $sql = "SELECT id FROM `{$table}` WHERE " . implode( ' AND ', $clauses ) . ' LIMIT 1';
    return absint( $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) ) );
}

/**
 * Snapshot cart items before emptying.
 *
 * @param WC_Cart $cart Cart object.
 */
function ufsc_snapshot_cart_before_empty( $cart ) {
	if ( ! $cart || ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
		return;
	}

	if ( function_exists( 'WC' ) && WC() && WC()->session ) {
		WC()->session->set( 'ufsc_cart_empty_snapshot', $cart->get_cart() );
	}
}

/**
 * Handle licence status revert on cart emptied.
 */
function ufsc_handle_cart_emptied_licence_revert() {
	if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
		if ( function_exists( 'ufsc_wc_log' ) ) {
			ufsc_wc_log( 'ufsc_cart_empty_woo_missing', array(), 'warning' );
		}
		return;
	}

	$snapshot = WC()->session->get( 'ufsc_cart_empty_snapshot', array() );
	WC()->session->set( 'ufsc_cart_empty_snapshot', null );

	if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
		return;
	}

	foreach ( $snapshot as $cart_item ) {
		ufsc_maybe_revert_licences_from_cart_item( $cart_item );
	}
}

/**
 * Handle remove_cart_item hook.
 *
 * @param string  $cart_item_key Cart item key.
 * @param WC_Cart $cart          Cart object.
 */
function ufsc_handle_remove_cart_item_licence_revert( $cart_item_key, $cart ) {
	if ( ! $cart || ! is_object( $cart ) || ! isset( $cart->removed_cart_contents[ $cart_item_key ] ) ) {
		return;
	}

	ufsc_maybe_revert_licences_from_cart_item( $cart->removed_cart_contents[ $cart_item_key ] );
}

/**
 * Handle cart_item_removed hook.
 *
 * @param string  $cart_item_key Cart item key.
 * @param WC_Cart $cart          Cart object.
 */
function ufsc_handle_cart_item_removed_licence_revert( $cart_item_key, $cart ) {
	if ( ! $cart || ! is_object( $cart ) || ! isset( $cart->removed_cart_contents[ $cart_item_key ] ) ) {
		return;
	}

	ufsc_maybe_revert_licences_from_cart_item( $cart->removed_cart_contents[ $cart_item_key ] );
}

/**
 * Extract licence IDs from cart item payload.
 *
 * Supports: ufsc_licence_id (single), ufsc_license_ids (lot), ufsc_licence_ids (lot)
 *
 * @param array $cart_item Cart item data.
 * @return int[]
 */
function ufsc_extract_licence_ids_from_cart_item( $cart_item ) {
	$ids = array();

	if ( isset( $cart_item['ufsc_licence_id'] ) ) {
		$ids[] = absint( $cart_item['ufsc_licence_id'] );
	}

	foreach ( array( 'ufsc_license_ids', 'ufsc_licence_ids' ) as $key ) {
		if ( isset( $cart_item[ $key ] ) && is_array( $cart_item[ $key ] ) ) {
			$ids = array_merge( $ids, array_map( 'absint', $cart_item[ $key ] ) );
		}
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Check whether a licence is already linked to a Woo order.
 *
 * @param int $licence_id Licence ID.
 * @return bool|null True if linked, false if not linked, null on uncertainty (fail-closed).
 */
function ufsc_is_licence_linked_to_order( $licence_id ) {
	global $wpdb;

	$licence_id = absint( $licence_id );
	if ( $licence_id <= 0 ) {
		return null;
	}

	if ( ! function_exists( 'wc_get_order' ) ) {
		return null;
	}

	$order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
	$order_items    = $wpdb->prefix . 'woocommerce_order_items';
	$posts          = $wpdb->posts;

	$single_item_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT order_item_id
			 FROM {$order_itemmeta}
			 WHERE meta_key IN ('_ufsc_licence_id','ufsc_licence_id')
			   AND meta_value = %s",
			(string) $licence_id
		)
	);

	$serialized_like = '%"' . $wpdb->esc_like( (string) $licence_id ) . '"%';
	$lot_item_ids    = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT order_item_id
			 FROM {$order_itemmeta}
			 WHERE meta_key IN ('_ufsc_licence_ids','ufsc_licence_ids')
			   AND meta_value LIKE %s",
			$serialized_like
		)
	);

	$item_ids = array_values(
		array_unique(
			array_map(
				'absint',
				array_merge( (array) $single_item_ids, (array) $lot_item_ids )
			)
		)
	);

	if ( empty( $item_ids ) ) {
		return false;
	}

	$in       = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
	$order_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT p.ID
			 FROM {$order_items} oi
			 INNER JOIN {$posts} p ON p.ID = oi.order_id
			 WHERE oi.order_item_id IN ({$in})
			   AND p.post_type IN ('shop_order','shop_order_refund')",
			$item_ids
		)
	);

	if ( ! is_array( $order_ids ) ) {
		return null;
	}

	foreach ( $order_ids as $order_id ) {
		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order ) {
			continue;
		}

		$status = (string) $order->get_status();
		if ( ! in_array( $status, array( 'cancelled', 'failed', 'refunded', 'trash' ), true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Revert pending licences from removed cart item when safe.
 *
 * - Normalizes licence IDs from cart item
 * - Checks club_id + ownership (unless admin)
 * - Reverts only en_attente/pending => brouillon when no linked Woo order exists
 * - Fail-closed when unsure
 *
 * @param array $cart_item Cart item data.
 */
function ufsc_maybe_revert_licences_from_cart_item( $cart_item ) {
	global $wpdb;

	if ( ! is_array( $cart_item ) ) {
		return;
	}

	$licence_ids = ufsc_extract_licence_ids_from_cart_item( $cart_item );
	if ( empty( $licence_ids ) ) {
		if ( function_exists( 'ufsc_wc_log' ) ) {
			ufsc_wc_log( 'ufsc_cart_remove_missing_ids', array(), 'warning' );
		}
		return;
	}

	$club_id = isset( $cart_item['ufsc_club_id'] ) ? absint( $cart_item['ufsc_club_id'] ) : 0;
	if ( $club_id <= 0 ) {
		if ( function_exists( 'ufsc_wc_log' ) ) {
			ufsc_wc_log( 'ufsc_cart_remove_missing_club', array( 'licence_ids' => $licence_ids ), 'warning' );
		}
		return;
	}

	if ( ! class_exists( 'UFSC_SQL' ) ) {
		return;
	}

	$settings = UFSC_SQL::get_settings();
	$table    = isset( $settings['table_licences'] ) ? $settings['table_licences'] : '';
	if ( '' === $table ) {
		return;
	}

	$can_manage_all = false;
	if ( class_exists( 'UFSC_Capabilities' ) && method_exists( 'UFSC_Capabilities', 'user_can' ) ) {
		$can_manage_all = UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ );
	}
	if ( ! $can_manage_all ) {
		$can_manage_all = current_user_can( 'manage_options' );
	}

	$current_user_club_id = function_exists( 'ufsc_get_user_club_id' )
		? absint( ufsc_get_user_club_id( get_current_user_id() ) )
		: 0;

	if ( ! $can_manage_all && $current_user_club_id > 0 && $current_user_club_id !== $club_id ) {
		if ( function_exists( 'ufsc_wc_log' ) ) {
			ufsc_wc_log(
				'ufsc_cart_remove_club_mismatch',
				array( 'club_id' => $club_id, 'user_club_id' => $current_user_club_id ),
				'warning'
			);
		}
		return;
	}

	foreach ( $licence_ids as $licence_id ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $licence_id ) );
		if ( ! $row ) {
			if ( function_exists( 'ufsc_wc_log' ) ) {
				ufsc_wc_log( 'ufsc_cart_remove_licence_not_found', array( 'licence_id' => $licence_id ), 'warning' );
			}
			continue;
		}

		$row_club_id = absint( $row->club_id ?? 0 );
		if ( $row_club_id <= 0 || $row_club_id !== $club_id ) {
			if ( function_exists( 'ufsc_wc_log' ) ) {
				ufsc_wc_log(
					'ufsc_cart_remove_licence_club_mismatch',
					array( 'licence_id' => $licence_id, 'row_club_id' => $row_club_id, 'club_id' => $club_id ),
					'warning'
				);
			}
			continue;
		}

		$status_raw = (string) ( $row->statut ?? '' );
		$status     = class_exists( 'UFSC_Licence_Status' )
			? UFSC_Licence_Status::normalize( $status_raw )
			: strtolower( trim( $status_raw ) );

		if ( ! in_array( $status, array( 'en_attente', 'pending' ), true ) ) {
			continue;
		}

		$order_linked = ufsc_is_licence_linked_to_order( $licence_id );
		if ( null === $order_linked ) {
			if ( function_exists( 'ufsc_wc_log' ) ) {
				ufsc_wc_log( 'ufsc_cart_remove_order_link_unknown', array( 'licence_id' => $licence_id ), 'warning' );
			}
			continue; // fail-closed
		}

		if ( true === $order_linked ) {
			if ( function_exists( 'ufsc_wc_log' ) ) {
				ufsc_wc_log( 'ufsc_cart_remove_order_linked_detected', array( 'licence_id' => $licence_id ), 'warning' );
			}
			continue;
		}

		if ( class_exists( 'UFSC_Licence_Status' ) ) {
			UFSC_Licence_Status::update_status_columns( $table, array( 'id' => $licence_id ), 'brouillon', array( '%d' ) );
		} else {
			$wpdb->update( $table, array( 'statut' => 'brouillon' ), array( 'id' => $licence_id ), array( '%s' ), array( '%d' ) );
		}

		do_action( 'ufsc_licence_updated', (int) $club_id );

		if ( function_exists( 'ufsc_wc_log' ) ) {
			ufsc_wc_log(
				'ufsc_cart_remove_revert_performed',
				array( 'licence_id' => $licence_id, 'from_status' => $status, 'to_status' => 'brouillon' ),
				'warning'
			);
		}
	}
}

/**
 * Handle secure add to cart requests posted via admin-post.php
 */
function ufsc_ensure_woocommerce_cart() {
	if ( ! function_exists( 'WC' ) || ! WC() ) {
		return new WP_Error( 'ufsc_woocommerce_unavailable', __( 'WooCommerce n’est pas initialisé. Rechargez la page puis réessayez.', 'ufsc-clubs' ) );
	}

	if ( WC()->cart ) {
		return true;
	}

	// admin-post.php does not execute the normal shop-page bootstrap. Use the
	// public WooCommerce initializers so session, customer and cart stay native.
	if ( method_exists( WC(), 'initialize_session' ) && ! WC()->session ) {
		WC()->initialize_session();
	}
	if ( method_exists( WC(), 'initialize_cart' ) && ! WC()->cart ) {
		WC()->initialize_cart();
	} elseif ( function_exists( 'wc_load_cart' ) && ! WC()->cart ) {
		wc_load_cart();
	}

	return WC()->cart
		? true
		: new WP_Error( 'ufsc_cart_initialization_failed', __( 'La session WooCommerce n’a pas pu initialiser le panier. Autorisez les cookies puis rechargez la page.', 'ufsc-clubs' ) );
}

/** Persist the native WooCommerce cart before leaving admin-post.php. */
function ufsc_persist_woocommerce_cart() {
	if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart || ! WC()->session ) {
		return new WP_Error( 'ufsc_cart_session_unavailable', __( 'La session WooCommerce est indisponible. Autorisez les cookies puis réessayez.', 'ufsc-clubs' ) );
	}

	WC()->cart->calculate_totals();
	WC()->cart->set_session();
	if ( method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
		WC()->session->set_customer_session_cookie( true );
	}
	if ( method_exists( WC()->session, 'save_data' ) ) {
		WC()->session->save_data();
	}

	return true;
}

function ufsc_handle_add_to_cart_secure() {
	$log_warning = static function( $event, $context = array() ) {
		if ( function_exists( 'ufsc_wc_log' ) ) {
			ufsc_wc_log( $event, $context, 'warning' );
		}
	};
	$log_info = static function( $event, $context = array() ) {
		if ( function_exists( 'ufsc_wc_log' ) ) {
			ufsc_wc_log( $event, $context, 'info' );
		}
	};

	$can_manage_all = false;
	if ( class_exists( 'UFSC_Capabilities' ) && method_exists( 'UFSC_Capabilities', 'user_can' ) ) {
		$can_manage_all = UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ );
	}
	if ( ! $can_manage_all ) {
		$can_manage_all = current_user_can( 'manage_options' );
	}

	// Woo required. In admin-post contexts Woo front helpers may not always be loaded yet.
	if ( class_exists( 'WooCommerce' ) && defined( 'WC_ABSPATH' ) ) {
		if ( ! function_exists( 'wc_add_notice' ) && file_exists( WC_ABSPATH . 'includes/wc-notice-functions.php' ) ) {
			include_once WC_ABSPATH . 'includes/wc-notice-functions.php';
		}
		if ( ! function_exists( 'wc_get_product' ) && file_exists( WC_ABSPATH . 'includes/wc-product-functions.php' ) ) {
			include_once WC_ABSPATH . 'includes/wc-product-functions.php';
		}
	}

	if ( ! function_exists( 'wc_add_notice' ) || ! function_exists( 'wc_get_product' ) ) {
		$log_warning( 'ufsc_add_to_cart_woo_missing', array(
			'woocommerce_class'        => class_exists( 'WooCommerce' ) ? 1 : 0,
			'wc_abspath'               => defined( 'WC_ABSPATH' ) ? WC_ABSPATH : '',
			'wc_add_notice_available'  => function_exists( 'wc_add_notice' ) ? 1 : 0,
			'wc_get_product_available' => function_exists( 'wc_get_product' ) ? 1 : 0,
			'posted_product_id'        => isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0,
			'posted_ufsc_action'       => isset( $_POST['ufsc_action'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_action'] ) ) : '',
			'posted_target_season'     => isset( $_POST['ufsc_target_season'] ) ? sanitize_text_field( wp_unslash( $_POST['ufsc_target_season'] ) ) : '',
		) );
		$redirect = wp_get_referer() ? wp_get_referer() : home_url();
		wp_safe_redirect( add_query_arg( 'ufsc_error', __( 'Le renouvellement en ligne est temporairement indisponible. Merci de contacter l’UFSC.', 'ufsc-clubs' ), $redirect ) );
		exit;
	}

	// Verify nonce
	$nonce = isset( $_POST['_ufsc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ufsc_nonce'] ) ) : '';
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ufsc_add_to_cart_action' ) ) {
		$log_warning( 'ufsc_add_to_cart_nonce_failed', array( 'user_id' => get_current_user_id() ) );
		wc_add_notice( __( 'Action non autorisée', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
		$log_warning( 'ufsc_add_to_cart_auth_failed', array(
			'logged_in' => is_user_logged_in() ? 1 : 0,
			'user_id'   => get_current_user_id(),
		) );

		wc_add_notice( __( 'Vous devez être connecté pour effectuer cette action', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	if ( ! $product_id ) {
		$log_warning( 'ufsc_add_to_cart_product_missing', array( 'user_id' => get_current_user_id() ) );
		wc_add_notice( __( 'Produit non trouvé', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	$product = wc_get_product( $product_id );
	if ( ! $product || ! $product->exists() ) {
		$posted_ufsc_action = isset( $_POST['ufsc_action'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_action'] ) ) : '';
		$expected_product_id = 0;
		if ( 'renew_licence' === $posted_ufsc_action ) {
			$expected_product_id = function_exists( 'ufsc_get_licence_product_id' ) ? ufsc_get_licence_product_id() : 0;
		} elseif ( 'renew_affiliation' === $posted_ufsc_action ) {
			$expected_product_id = function_exists( 'ufsc_get_affiliation_product_id' ) ? ufsc_get_affiliation_product_id() : 0;
		}
		$product_diagnostic = function_exists( 'ufsc_get_woocommerce_product_diagnostic' ) ? ufsc_get_woocommerce_product_diagnostic( $expected_product_id ) : array();
		$log_warning( 'ufsc_add_to_cart_product_invalid', array(
			'product_id'          => $product_id,
			'ufsc_action'         => $posted_ufsc_action,
			'expected_product_id' => absint( $expected_product_id ),
			'woocommerce_active'  => ! empty( $product_diagnostic['woocommerce_active'] ) ? 1 : 0,
			'product_found'       => ! empty( $product_diagnostic['product_found'] ) ? 1 : 0,
			'product_purchasable' => ! empty( $product_diagnostic['product_purchasable'] ) ? 1 : 0,
		) );
		if ( in_array( $posted_ufsc_action, array( 'renew_licence', 'renew_affiliation' ), true ) ) {
			wc_add_notice( current_user_can( 'manage_options' ) ? __( 'Produit WooCommerce d’affiliation non configuré ou indisponible. Merci de renseigner le produit dans les paramètres UFSC WooCommerce.', 'ufsc-clubs' ) : __( 'Le renouvellement en ligne est temporairement indisponible. Merci de contacter l’UFSC.', 'ufsc-clubs' ), 'error' );
		} else {
			wc_add_notice( __( 'Produit non trouvé', 'ufsc-clubs' ), 'error' );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	$quantity       = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
	$cart_item_data = array();
	$license_ids    = array();
	$ufsc_action    = isset( $_POST['ufsc_action'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_action'] ) ) : '';
	$target_season  = isset( $_POST['ufsc_target_season'] ) ? sanitize_text_field( wp_unslash( $_POST['ufsc_target_season'] ) ) : '';
	$renew_from_id  = isset( $_POST['ufsc_renew_from_licence_id'] ) ? absint( $_POST['ufsc_renew_from_licence_id'] ) : 0;

	$log_info( 'ufsc_add_to_cart_request_received', array(
		'user_id'             => get_current_user_id(),
		'ufsc_action'         => $ufsc_action,
		'product_id'          => $product_id,
		'affiliation_product' => function_exists( 'ufsc_get_affiliation_product_id' ) ? ufsc_get_affiliation_product_id() : 0,
		'licence_product'     => function_exists( 'ufsc_get_licence_product_id' ) ? ufsc_get_licence_product_id() : 0,
		'club_id'             => isset( $_POST['ufsc_club_id'] ) ? absint( wp_unslash( $_POST['ufsc_club_id'] ) ) : 0,
		'target_season'       => $target_season,
		'nonce_ok'            => 1,
		'woocommerce_active'  => class_exists( 'WooCommerce' ) ? 1 : 0,
		'cart_available'      => ( function_exists( 'WC' ) && WC() && WC()->cart ) ? 1 : 0,
		'renewal_open'        => function_exists( 'ufsc_is_renewal_window_open' ) && ufsc_is_renewal_window_open() ? 1 : 0,
	) );

	// Parse licence ids once (avoid double parsing / regression)
	if ( isset( $_POST['ufsc_license_ids'] ) ) {
		$license_ids_string = sanitize_text_field( wp_unslash( $_POST['ufsc_license_ids'] ) );
		$license_ids        = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $license_ids_string ) ) ) ) );

		$max_ids = (int) apply_filters( 'ufsc_cart_max_licence_ids', 50 );
		$max_ids = max( 1, min( $max_ids, 500 ) );

		if ( count( $license_ids ) > $max_ids ) {
			$log_warning( 'ufsc_add_to_cart_ids_too_many', array( 'count' => count( $license_ids ), 'max' => $max_ids ) );
			wc_add_notice( __( 'Une ou plusieurs licences ne peuvent pas être ajoutées au panier.', 'ufsc-clubs' ), 'error' );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
			exit;
		}
	}

	$current_user_id = get_current_user_id();
	$user_club_id    = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( $current_user_id ) ) : 0;

	// Resolve club id:
	// - If posted ufsc_club_id exists: take it (admin or club mode)
	// - Admin fallback: if missing but license_ids provided, resolve club_id from first licence (fail-closed)
	// - Fallback to user_club_id
	$club_id = isset( $_POST['ufsc_club_id'] ) ? absint( $_POST['ufsc_club_id'] ) : 0;

	if ( $can_manage_all && $club_id <= 0 && ! empty( $license_ids ) && function_exists( 'ufsc_get_licences_table' ) ) {
		global $wpdb;
		$table            = ufsc_get_licences_table();
		$first_id         = absint( $license_ids[0] );
		$resolved_club_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT club_id FROM `{$table}` WHERE id = %d", $first_id )
		);

		if ( $resolved_club_id > 0 ) {
			$club_id = $resolved_club_id;
		}
	}

	if ( $club_id <= 0 ) {
		$club_id = $user_club_id;
	}

	if ( $club_id <= 0 ) {
		$log_warning( 'ufsc_add_to_cart_club_missing', array(
			'user_id'      => $current_user_id,
			'user_club_id' => $user_club_id,
			'product_id'   => $product_id,
			'ids_count'    => count( $license_ids ),
		) );

		wc_add_notice( __( 'Club invalide pour cet utilisateur.', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	// Non-admins can only use their own club.
	if ( ! $can_manage_all && ( $user_club_id <= 0 || $club_id !== $user_club_id ) ) {
		$log_warning( 'ufsc_add_to_cart_club_mismatch', array(
			'user_id'      => $current_user_id,
			'club_id'      => $club_id,
			'user_club_id' => $user_club_id,
			'product_id'   => $product_id,
		) );

		wc_add_notice( __( 'Club invalide pour cet utilisateur.', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}


	if ( in_array( $ufsc_action, array( 'renew_licence', 'renew_affiliation' ), true ) ) {
		if ( '' === $target_season ) {
			$target_season = class_exists( 'UFSC_Season_Service' )
				? UFSC_Season_Service::get_current_season()
				: ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
		}

		$current_season_for_renewal = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
		if ( $target_season && $current_season_for_renewal && $target_season !== $current_season_for_renewal && ( ! function_exists( 'ufsc_is_renewal_window_open' ) || ! ufsc_is_renewal_window_open() ) ) {
			$renew_start_ts = function_exists( 'ufsc_get_renewal_window_start_ts' ) ? (int) ufsc_get_renewal_window_start_ts() : 0;
			$renew_open_label = $renew_start_ts > 0 ? wp_date( 'd/m/Y', $renew_start_ts ) : __( '30/07', 'ufsc-clubs' );
			$message = sprintf( __( 'Le renouvellement pour la saison %1$s sera disponible à partir du %2$s.', 'ufsc-clubs' ), $target_season, $renew_open_label );
			$log_warning( 'ufsc_add_to_cart_renewal_window_closed', array(
				'ufsc_action'       => $ufsc_action,
				'club_id'           => isset( $_POST['ufsc_club_id'] ) ? absint( wp_unslash( $_POST['ufsc_club_id'] ) ) : 0,
				'product_id'        => $product_id,
				'target_season'    => $target_season,
				'current_season'   => $current_season_for_renewal,
				'renewal_open_ts'  => $renew_start_ts,
				'renewal_open_label' => $renew_open_label,
			) );
			wc_add_notice( $message, 'error' );
			$redirect = wp_get_referer() ? wp_get_referer() : home_url();
			wp_safe_redirect( add_query_arg( 'ufsc_error', rawurlencode( $message ), $redirect ) );
			exit;
		}

		$expected_product_id = ( 'renew_licence' === $ufsc_action )
			? ( function_exists( 'ufsc_get_licence_product_id' ) ? ufsc_get_licence_product_id() : 0 )
			: ( function_exists( 'ufsc_get_affiliation_product_id' ) ? ufsc_get_affiliation_product_id() : 0 );
		$product_diagnostic = function_exists( 'ufsc_get_woocommerce_product_diagnostic' ) ? ufsc_get_woocommerce_product_diagnostic( $expected_product_id ) : array();
		if ( $expected_product_id <= 0 || absint( $product_id ) !== absint( $expected_product_id ) || ( function_exists( 'ufsc_is_woocommerce_product_available' ) && ! ufsc_is_woocommerce_product_available( $expected_product_id ) ) ) {
			$log_warning( 'ufsc_add_to_cart_renewal_product_unavailable', array(
				'ufsc_action'            => $ufsc_action,
				'club_id'                => $club_id,
				'target_season'          => $target_season,
				'received_product_id'    => absint( $product_id ),
				'expected_product_id'    => absint( $expected_product_id ),
				'woocommerce_active'     => ! empty( $product_diagnostic['woocommerce_active'] ) ? 1 : 0,
				'product_found'          => ! empty( $product_diagnostic['product_found'] ) ? 1 : 0,
				'product_status'         => isset( $product_diagnostic['product_status'] ) ? (string) $product_diagnostic['product_status'] : '',
				'product_purchasable'    => ! empty( $product_diagnostic['product_purchasable'] ) ? 1 : 0,
			) );
			if ( current_user_can( 'manage_options' ) ) {
				wc_add_notice( __( 'Produit WooCommerce d’affiliation non configuré ou indisponible. Merci de renseigner le produit dans les paramètres UFSC WooCommerce.', 'ufsc-clubs' ), 'error' );
			} else {
				wc_add_notice( __( 'Le renouvellement en ligne est temporairement indisponible. Merci de contacter l’UFSC.', 'ufsc-clubs' ), 'error' );
			}
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
			exit;
		}

		$item_type = ( 'renew_licence' === $ufsc_action ) ? 'licence_renewal' : 'affiliation_renewal';
		$cart_item_data['ufsc_item_type'] = $item_type;
		$cart_item_data['ufsc_user_id']   = $current_user_id;
		$cart_item_data['ufsc_source']    = 'ufsc_gestion';
		$cart_item_data['ufsc_season']    = $target_season;

		if ( 'renew_licence' === $ufsc_action ) {
			if ( $renew_from_id <= 0 || ! function_exists( 'ufsc_get_licences_table' ) ) {
				wc_add_notice( __( 'Licence source invalide.', 'ufsc-clubs' ), 'error' );
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}
			global $wpdb;
			$table = ufsc_get_licences_table();
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $renew_from_id ) );
			if ( ! $row || absint( $row->club_id ?? 0 ) !== absint( $club_id ) ) {
				wc_add_notice( __( 'Licence source invalide.', 'ufsc-clubs' ), 'error' );
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}
			$gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id, $target_season ) : array( 'allowed' => false, 'message' => __( 'Vous devez renouveler votre affiliation avant de renouveler vos licences.', 'ufsc-clubs' ) );
			if ( empty( $gate['allowed'] ) ) {
				if ( function_exists( 'ufsc_log_licence_affiliation_refusal' ) ) { ufsc_log_licence_affiliation_refusal( $gate, 'secure_renew_licence', $renew_from_id ); }
				wc_add_notice( $gate['message'], 'error' );
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}
			if ( function_exists( 'ufsc_get_renewed_licence_marker' ) && ufsc_get_renewed_licence_marker( $renew_from_id, $target_season ) ) {
				wc_add_notice( __( 'Cette licence a déjà été renouvelée pour cette saison.', 'ufsc-clubs' ), 'error' );
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}
			if ( function_exists( 'ufsc_wc_find_equivalent_renewed_licence_id' ) && ufsc_wc_find_equivalent_renewed_licence_id( $row, $club_id, $target_season ) > 0 ) {
				wc_add_notice( __( 'Une licence équivalente existe déjà pour cette saison.', 'ufsc-clubs' ), 'error' );
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}
			if ( function_exists( 'ufsc_wc_has_pending_renewal_order' ) && ufsc_wc_has_pending_renewal_order( 'renew_licence', $club_id, $target_season, $renew_from_id ) ) {
				wc_add_notice( __( 'Une commande de renouvellement est déjà en attente pour cet élément. Merci de finaliser ou vérifier votre commande.', 'ufsc-clubs' ), 'error' );
				wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : ( wp_get_referer() ? wp_get_referer() : home_url() ) );
				exit;
			}
			if ( function_exists( 'ufsc_cart_has_renewal_item' ) && ufsc_cart_has_renewal_item( 'renew_licence', $club_id, $target_season, $renew_from_id ) ) {
				wc_add_notice( __( 'Cette licence est déjà dans le panier de renouvellement.', 'ufsc-clubs' ), 'notice' );
				wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : ( wp_get_referer() ? wp_get_referer() : home_url() ) );
				exit;
			}
			$cart_item_data['ufsc_action'] = 'renew_licence';
			$cart_item_data['ufsc_renew_from_licence_id'] = $renew_from_id;
			$cart_item_data['ufsc_source_licence_id'] = $renew_from_id;
			$cart_item_data['ufsc_target_season'] = $target_season;
			$cart_item_data['ufsc_nom'] = isset( $row->nom ) ? (string) $row->nom : '';
			$cart_item_data['ufsc_prenom'] = isset( $row->prenom ) ? (string) $row->prenom : '';
			$cart_item_data['ufsc_date_naissance'] = isset( $row->date_naissance ) ? (string) $row->date_naissance : '';
			$cart_item_data['ufsc_sexe'] = isset( $row->sexe ) ? (string) $row->sexe : '';
			$cart_item_data['ufsc_fighter_level'] = isset( $row->fighter_level ) ? sanitize_key( (string) $row->fighter_level ) : '';
			$quantity = 1;
		} else {
			if ( function_exists( 'ufsc_is_club_affiliated_for_season' ) && ufsc_is_club_affiliated_for_season( $club_id, $target_season ) ) {
				wc_add_notice( __( 'Affiliation déjà renouvelée pour cette saison.', 'ufsc-clubs' ), 'error' );
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}
			if ( function_exists( 'ufsc_wc_has_pending_renewal_order' ) && ufsc_wc_has_pending_renewal_order( 'renew_affiliation', $club_id, $target_season ) ) {
				wc_add_notice( __( 'Une commande de renouvellement est déjà en attente pour cet élément. Merci de finaliser ou vérifier votre commande.', 'ufsc-clubs' ), 'error' );
				wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : ( wp_get_referer() ? wp_get_referer() : home_url() ) );
				exit;
			}
			if ( function_exists( 'ufsc_cart_has_renewal_item' ) && ufsc_cart_has_renewal_item( 'renew_affiliation', $club_id, $target_season ) ) {
				wc_add_notice( __( 'Votre affiliation est déjà dans le panier de renouvellement.', 'ufsc-clubs' ), 'notice' );
				wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : ( wp_get_referer() ? wp_get_referer() : home_url() ) );
				exit;
			}
			$cart_item_data['ufsc_action'] = 'renew_affiliation';
			$cart_item_data['ufsc_target_season'] = $target_season;
			$quantity = 1;
		}
	}

	$cart_item_data['ufsc_club_id'] = $club_id;

	$cart_ready = ufsc_ensure_woocommerce_cart();
	if ( is_wp_error( $cart_ready ) ) {
		$log_warning( 'ufsc_add_to_cart_cart_unavailable', array(
			'user_id'    => $current_user_id,
			'club_id'    => $club_id,
			'product_id' => $product_id,
		) );

		wc_add_notice( $cart_ready->get_error_message(), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	$cart_item_key = false;

	// Licence flow: add one cart line per licence (idempotent per licence ID).
	if ( ! empty( $license_ids ) ) {
		if ( ! ufsc_validate_licence_ids_for_cart( $license_ids, $club_id ) ) {
			$log_warning( 'ufsc_add_to_cart_ids_invalid', array(
				'user_id'    => $current_user_id,
				'club_id'    => $club_id,
				'product_id' => $product_id,
				'ids_count'  => count( $license_ids ),
			) );

			wc_add_notice( __( 'Une ou plusieurs licences ne peuvent pas être ajoutées au panier.', 'ufsc-clubs' ), 'error' );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
			exit;
		}

		$add_result = ufsc_add_licence_ids_to_cart_idempotent(
			$product_id,
			$club_id,
			$license_ids,
			array()
		);

		if ( is_wp_error( $add_result ) ) {
			$log_warning(
				'ufsc_add_to_cart_ids_add_failed',
				array(
					'user_id'    => $current_user_id,
					'club_id'    => $club_id,
					'product_id' => $product_id,
					'ids'        => $license_ids,
					'error'      => $add_result->get_error_code(),
				)
			);
			wc_add_notice( $add_result->get_error_message(), 'error' );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
			exit;
		}

		$cart_item_key = ! empty( $add_result['added'] ) || ! empty( $add_result['existing'] );

		if ( ! empty( $add_result['added'] ) ) {
			wc_add_notice(
				sprintf( __( '%s ajouté au panier', 'ufsc-clubs' ), $product->get_name() ),
				'success'
			);
		} else {
			wc_add_notice( __( 'Cette ou ces licences sont déjà présentes dans le panier.', 'ufsc-clubs' ), 'notice' );
		}
	} else {
		$cart_item_key = WC()->cart->add_to_cart( $product_id, max( 1, $quantity ), 0, array(), $cart_item_data );

		if ( $cart_item_key ) {
			wc_add_notice(
				sprintf( __( '%s ajouté au panier', 'ufsc-clubs' ), $product->get_name() ),
				'success'
			);
		} else {
			$log_warning( 'ufsc_add_to_cart_add_failed', array(
				'user_id'    => $current_user_id,
				'product_id' => $product_id,
				'club_id'    => $club_id,
				'qty'        => max( 1, $quantity ),
				'ids_count'  => count( $license_ids ),
			) );

			wc_add_notice( __( 'Erreur lors de l\'ajout au panier', 'ufsc-clubs' ), 'error' );
		}
	}

	if ( $cart_item_key && empty( $license_ids ) ) {
		$persisted = ufsc_persist_woocommerce_cart();
		if ( is_wp_error( $persisted ) ) {
			wc_add_notice( $persisted->get_error_message(), 'error' );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
			exit;
		}
	}

	$redirect_url = in_array( $ufsc_action, array( 'renew_licence', 'renew_affiliation' ), true ) && function_exists( 'wc_get_cart_url' )
		? wc_get_cart_url()
		: ( wp_get_referer() ? wp_get_referer() : ( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url() ) );
	$log_info( 'ufsc_add_to_cart_redirect', array(
		'ufsc_action'    => $ufsc_action,
		'product_id'     => $product_id,
		'club_id'        => $club_id,
		'target_season'  => $target_season,
		'cart_item_key'  => $cart_item_key ? 1 : 0,
		'redirect_url'   => $redirect_url,
	) );
	wp_safe_redirect( $redirect_url );
	exit;
}

/**
 * Add licence IDs to cart in an idempotent way.
 *
 * - One cart line per licence ID (prevents incorrect quantity merging).
 * - Reuses existing cart lines when same licence is already present.
 *
 * @param int   $product_id Product ID.
 * @param int   $club_id    Club ID.
 * @param array $licence_ids Licence IDs.
 * @param array $extra_cart_item_data Additional cart item data.
 * @return array|WP_Error
 */

function ufsc_is_licence_cart_item( $item ) {
	$type = sanitize_key( (string) ( $item['ufsc_item_type'] ?? '' ) );
	$action = sanitize_key( (string) ( $item['ufsc_action'] ?? '' ) );
	return 'licence_renewal' === $type || 'renew_licence' === $action || ! empty( $item['ufsc_licence_id'] ) || ! empty( $item['ufsc_license_ids'] );
}

function ufsc_validate_licence_affiliation_cart_item( $item, $entrypoint ) {
	if ( ! ufsc_is_licence_cart_item( (array) $item ) ) { return true; }
	if ( isset( $item['quantity'] ) && 1 !== (int) $item['quantity'] ) { if ( function_exists( 'wc_add_notice' ) ) { wc_add_notice( __( 'Chaque licence doit constituer une ligne de quantité 1.', 'ufsc-clubs' ), 'error' ); } return false; }
	if ( 'renew_licence' === sanitize_key( (string) ( $item['ufsc_action'] ?? '' ) ) ) {
		if ( empty( $item['ufsc_person_identifier'] ) || empty( $item['ufsc_renew_from_licence_id'] ) || empty( $item['ufsc_fighter_level'] ) ) { if ( function_exists( 'wc_add_notice' ) ) { wc_add_notice( ufsc_get_sport_level_required_message(), 'error' ); } return false; }
		$weight = class_exists( 'UFSC_Category_Repository' ) ? UFSC_Category_Repository::normalize_weight( $item['ufsc_weight'] ?? '' ) : null;
		if ( null === $weight || $weight < 20 || $weight > 300 ) { if ( function_exists( 'wc_add_notice' ) ) { wc_add_notice( __( 'Le poids déclaré est invalide.', 'ufsc-clubs' ), 'error' ); } return false; }
	}
	$club_id = absint( $item['ufsc_club_id'] ?? 0 );
	$season = sanitize_text_field( (string) ( $item['ufsc_target_season'] ?? ( $item['ufsc_season'] ?? ( $item['season'] ?? '' ) ) ) );
	$gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id, $season ?: null ) : array( 'allowed' => false, 'message' => __( 'Votre club doit renouveler et faire activer son affiliation avant de souscrire ou renouveler des licences.', 'ufsc-clubs' ) );
	if ( ! empty( $gate['allowed'] ) ) { return true; }
	if ( function_exists( 'ufsc_log_licence_affiliation_refusal' ) ) { ufsc_log_licence_affiliation_refusal( $gate, $entrypoint, absint( $item['ufsc_licence_id'] ?? ( $item['ufsc_renew_from_licence_id'] ?? 0 ) ) ); }
	if ( function_exists( 'wc_add_notice' ) ) { wc_add_notice( $gate['message'], 'error' ); }
	return false;
}

function ufsc_validate_licence_affiliation_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
	if ( false === $passed ) { return false; }
	$item = array(
		'ufsc_club_id' => isset( $_REQUEST['ufsc_club_id'] ) ? absint( $_REQUEST['ufsc_club_id'] ) : 0,
		'ufsc_action' => isset( $_REQUEST['ufsc_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['ufsc_action'] ) ) : '',
		'ufsc_target_season' => isset( $_REQUEST['ufsc_target_season'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ufsc_target_season'] ) ) : '',
		'ufsc_licence_id' => isset( $_REQUEST['ufsc_licence_id'] ) ? absint( $_REQUEST['ufsc_licence_id'] ) : 0,
		'ufsc_license_ids' => isset( $_REQUEST['ufsc_license_ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['ufsc_license_ids'] ) ) ) ) ) : array(),
	);
	if ( empty( $item['ufsc_club_id'] ) && function_exists( 'ufsc_get_user_club_id' ) ) { $item['ufsc_club_id'] = absint( ufsc_get_user_club_id( get_current_user_id() ) ); }
	return ufsc_validate_licence_affiliation_cart_item( $item, 'woocommerce_add_to_cart_validation' );
}

function ufsc_validate_licence_affiliation_cart_session( $cart_item, $values, $key ) {
	return ufsc_validate_licence_affiliation_cart_item( $cart_item, 'cart_session_restore' ) ? $cart_item : false;
}

function ufsc_validate_licence_affiliation_checkout() {
	if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) { return; }
	foreach ( WC()->cart->get_cart() as $item ) { ufsc_validate_licence_affiliation_cart_item( $item, 'checkout' ); }
}

function ufsc_add_licence_ids_to_cart_idempotent( $product_id, $club_id, $licence_ids, $extra_cart_item_data = array() ) {
	$product_id = absint( $product_id );
	$club_id    = absint( $club_id );
	$licence_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $licence_ids ) ) ) );

	if ( $product_id <= 0 || $club_id <= 0 || empty( $licence_ids ) ) {
		return new WP_Error( 'ufsc_invalid_add_to_cart_payload', __( 'Paramètres d\'ajout au panier invalides.', 'ufsc-clubs' ) );
	}

	$cart_ready = ufsc_ensure_woocommerce_cart();
	if ( is_wp_error( $cart_ready ) ) {
		return $cart_ready;
	}

	$season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
	$gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id, $season ) : array( 'allowed' => false, 'message' => __( 'Votre club doit renouveler et faire activer son affiliation avant de souscrire ou renouveler des licences.', 'ufsc-clubs' ) );
	if ( empty( $gate['allowed'] ) ) {
		if ( function_exists( 'ufsc_log_licence_affiliation_refusal' ) ) { ufsc_log_licence_affiliation_refusal( $gate, 'cart_helper' ); }
		return new WP_Error( 'ufsc_affiliation_required', $gate['message'] );
	}

	// Defense-in-depth: enforce ownership/status guard regardless of caller.
	if ( ! ufsc_validate_licence_ids_for_cart( $licence_ids, $club_id ) ) {
		if ( function_exists( 'ufsc_wc_log' ) ) {
			ufsc_wc_log(
				'ufsc_add_to_cart_helper_validation_failed',
				array(
					'club_id'     => $club_id,
					'product_id'  => $product_id,
					'licence_ids' => $licence_ids,
					'user_id'     => get_current_user_id(),
				),
				'warning'
			);
		}
		return new WP_Error( 'ufsc_licence_validation_failed', __( 'Une ou plusieurs licences ne peuvent pas être ajoutées au panier.', 'ufsc-clubs' ) );
	}

	$added    = array();
	$existing = array();
	$failed   = array();

	foreach ( $licence_ids as $licence_id ) {
		$already_in_cart = false;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( absint( $item['product_id'] ?? 0 ) !== $product_id ) {
				continue;
			}

			$item_ids = ufsc_extract_licence_ids_from_cart_item( $item );
			if ( in_array( $licence_id, $item_ids, true ) ) {
				$already_in_cart = true;
				break;
			}
		}

		if ( $already_in_cart ) {
			$existing[] = $licence_id;
			continue;
		}

		// Identity is always resolved from the authorized server-side record.
		$licence_identity = array();
		if ( function_exists( 'ufsc_get_licences_table' ) ) {
			global $wpdb;
			$table = ufsc_get_licences_table();
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT nom, prenom, date_naissance FROM `{$table}` WHERE id = %d AND club_id = %d", $licence_id, $club_id ) );
			if ( $row ) {
				$licence_identity = array(
					'ufsc_nom'            => sanitize_text_field( (string) $row->nom ),
					'ufsc_prenom'         => sanitize_text_field( (string) $row->prenom ),
					'ufsc_date_naissance' => sanitize_text_field( (string) $row->date_naissance ),
				);
			}
		}

		$item_data = array_merge(
			(array) $extra_cart_item_data,
			$licence_identity,
			array(
				'ufsc_club_id'     => $club_id,
				'ufsc_licence_id'  => $licence_id,
				'ufsc_license_ids' => array( $licence_id ),
			)
		);

		$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $item_data );
		if ( ! $cart_item_key ) {
			$failed[] = $licence_id;
			continue;
		}

		$added[] = $licence_id;
	}

	if ( ! empty( $failed ) ) {
		return new WP_Error( 'ufsc_add_to_cart_failed', __( 'Erreur lors de l\'ajout au panier', 'ufsc-clubs' ) );
	}

	$persisted = ufsc_persist_woocommerce_cart();
	if ( is_wp_error( $persisted ) ) {
		return $persisted;
	}

	return array(
		'added'    => $added,
		'existing' => $existing,
	);
}

/**
 * Validate club ownership and lock/payment state before cart add.
 *
 * FAIL-CLOSED: any doubt => false.
 *
 * @param array $licence_ids Licence IDs.
 * @param int   $club_id     Current user club ID.
 * @return bool
 */
function ufsc_validate_licence_ids_for_cart( $licence_ids, $club_id ) {
	global $wpdb;

	$can_manage_all = false;
	if ( class_exists( 'UFSC_Capabilities' ) && method_exists( 'UFSC_Capabilities', 'user_can' ) ) {
		$can_manage_all = UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ );
	}
	if ( ! $can_manage_all ) {
		$can_manage_all = current_user_can( 'manage_options' );
	}

	if ( ! is_array( $licence_ids ) ) {
		return false;
	}

	$licence_ids = array_values( array_unique( array_filter( array_map( 'absint', $licence_ids ) ) ) );
	if ( empty( $licence_ids ) ) {
		return false;
	}

	$max_ids = (int) apply_filters( 'ufsc_cart_max_licence_ids', 50 );
	$max_ids = max( 1, min( $max_ids, 500 ) );

	if ( count( $licence_ids ) > $max_ids ) {
		return false;
	}

	if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
		return false;
	}

	$season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
	$gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id, $season ) : array( 'allowed' => false );
	if ( empty( $gate['allowed'] ) ) {
		if ( function_exists( 'ufsc_log_licence_affiliation_refusal' ) ) { ufsc_log_licence_affiliation_refusal( $gate, 'validate_cart_ids' ); }
		return false;
	}

	// Non-admins must have a club.
	if ( ! $can_manage_all && $club_id <= 0 ) {
		return false;
	}

	$table = ufsc_get_licences_table();
	$clubs = array();

	foreach ( $licence_ids as $licence_id ) {
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", absint( $licence_id ) )
		);

		if ( ! $row ) {
			return false;
		}

		$row_club_id = absint( $row->club_id ?? 0 );
		if ( $row_club_id <= 0 ) {
			return false;
		}

		$clubs[ $row_club_id ] = true;

		// Ownership check (non-admin).
		if ( ! $can_manage_all && $row_club_id !== absint( $club_id ) ) {
			return false;
		}

		// Lock check: paid/locked/en_attente(with proof)/valide/etc.
		if ( function_exists( 'ufsc_is_licence_locked_for_club' ) && ufsc_is_licence_locked_for_club( $row ) ) {
			return false;
		}
	}

	// All licences must belong to one club (prevents mixing).
	if ( count( $clubs ) !== 1 ) {
		return false;
	}

	$club_ids = array_map( 'absint', array_keys( $clubs ) );
	if ( empty( $club_ids ) ) {
		return false;
	}

	if ( $club_id > 0 && (int) $club_ids[0] !== (int) absint( $club_id ) ) {
		return false;
	}

	return true;
}

/**
 * Transfer custom meta from cart to order line items
 *
 * @param WC_Order_Item_Product $item          Order line item
 * @param string               $cart_item_key  Cart item key
 * @param array                $values         Cart item values
 * @param WC_Order             $order          Order object
 */
function ufsc_transfer_cart_meta_to_order( $item, $cart_item_key, $values, $order ) {
	// Transfer club ID (store both underscored + public for compatibility)
	if ( isset( $values['ufsc_club_id'] ) ) {
		$club_id = absint( $values['ufsc_club_id'] );
		$item->add_meta_data( '_ufsc_club_id', $club_id );
		$item->add_meta_data( 'ufsc_club_id', $club_id );
		if ( function_exists( 'ufsc_get_club_name' ) ) {
			$club_name = ufsc_get_club_name( $club_id );
			if ( '' !== (string) $club_name ) {
				$item->add_meta_data( '_ufsc_club_name', sanitize_text_field( (string) $club_name ) );
				$item->add_meta_data( 'ufsc_club_name', sanitize_text_field( (string) $club_name ) );
			}
		}
	}

	// Transfer single licence ID (legacy compatibility)
	if ( isset( $values['ufsc_licence_id'] ) ) {
		$item->add_meta_data( '_ufsc_licence_id', absint( $values['ufsc_licence_id'] ) );
		$item->add_meta_data( 'ufsc_licence_id', absint( $values['ufsc_licence_id'] ) );
	}

	/**
	 * Transfer licence IDs (lot)
	 * NOTE: cart uses 'ufsc_license_ids' (EN spelling).
	 * Order metas stored as both _ufsc_licence_ids and ufsc_licence_ids for compatibility.
	 */
	if ( isset( $values['ufsc_license_ids'] ) && is_array( $values['ufsc_license_ids'] ) ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $values['ufsc_license_ids'] ) ) ) );
		if ( ! empty( $ids ) ) {
			$item->add_meta_data( '_ufsc_licence_id', (int) $ids[0], true );
			$item->add_meta_data( 'ufsc_licence_id', (int) $ids[0], true );
			$item->add_meta_data( '_ufsc_licence_ids', $ids );
			$item->add_meta_data( 'ufsc_licence_ids', $ids );
		}
	}


	if ( isset( $values['ufsc_item_type'] ) ) {
		$item_type = sanitize_key( $values['ufsc_item_type'] );
		$item->add_meta_data( '_ufsc_item_type', $item_type );
		$item->add_meta_data( 'ufsc_item_type', $item_type );
	}
	if ( 'renew_affiliation' === ( $values['ufsc_action'] ?? '' ) ) {
		$item->add_meta_data( '_ufsc_affiliation_request_type', sanitize_key( $values['ufsc_request_type'] ?? 'renewal' ) );
		$item->add_meta_data( '_ufsc_affiliation_product_id', absint( $values['ufsc_product_id'] ?? ufsc_get_affiliation_product_id() ) );
		$item->add_meta_data( '_ufsc_request_user_id', absint( $values['ufsc_user_id'] ?? get_current_user_id() ) );
		$item->add_meta_data( '_ufsc_previous_affiliation_id', absint( $values['ufsc_previous_affiliation_id'] ?? 0 ) );
	}
	if ( isset( $values['ufsc_user_id'] ) ) {
		$item->add_meta_data( '_ufsc_user_id', absint( $values['ufsc_user_id'] ) );
		$item->add_meta_data( 'ufsc_user_id', absint( $values['ufsc_user_id'] ) );
	}
	if ( isset( $values['ufsc_source'] ) ) {
		$item->add_meta_data( '_ufsc_source', sanitize_key( $values['ufsc_source'] ) );
	}
	if ( isset( $values['ufsc_action'] ) ) {
		$item->add_meta_data( '_ufsc_action', sanitize_key( $values['ufsc_action'] ) );
		$item->add_meta_data( 'ufsc_action', sanitize_key( $values['ufsc_action'] ) );
	}
	if ( isset( $values['ufsc_target_season'] ) ) {
		$item->add_meta_data( '_ufsc_target_season', sanitize_text_field( (string) $values['ufsc_target_season'] ) );
		$item->add_meta_data( 'ufsc_target_season', sanitize_text_field( (string) $values['ufsc_target_season'] ) );
	}
	if ( isset( $values['ufsc_season'] ) ) {
		$item->add_meta_data( '_ufsc_season', sanitize_text_field( (string) $values['ufsc_season'] ) );
		$item->add_meta_data( 'ufsc_season', sanitize_text_field( (string) $values['ufsc_season'] ) );
	}
	if ( isset( $values['ufsc_renew_from_licence_id'] ) ) {
		$item->add_meta_data( '_ufsc_renew_from_licence_id', absint( $values['ufsc_renew_from_licence_id'] ) );
		$item->add_meta_data( 'ufsc_renew_from_licence_id', absint( $values['ufsc_renew_from_licence_id'] ) );
	}
	if ( isset( $values['ufsc_source_licence_id'] ) ) {
		$item->add_meta_data( '_ufsc_source_licence_id', absint( $values['ufsc_source_licence_id'] ) );
		$item->add_meta_data( 'ufsc_source_licence_id', absint( $values['ufsc_source_licence_id'] ) );
	}

	// Transfer personal data
	if ( isset( $values['ufsc_nom'] ) ) {
		$item->add_meta_data( '_ufsc_nom', sanitize_text_field( (string) $values['ufsc_nom'] ) );
	}
	if ( isset( $values['ufsc_prenom'] ) ) {
		$item->add_meta_data( '_ufsc_prenom', sanitize_text_field( (string) $values['ufsc_prenom'] ) );
	}
	if ( isset( $values['ufsc_date_naissance'] ) ) {
		$item->add_meta_data( '_ufsc_date_naissance', sanitize_text_field( (string) $values['ufsc_date_naissance'] ) );
	}
	if ( isset( $values['ufsc_fighter_level'] ) ) {
		$item->add_meta_data( '_ufsc_fighter_level', sanitize_key( (string) $values['ufsc_fighter_level'] ) );
	}
	if ( isset( $values['ufsc_weight'] ) ) { $item->add_meta_data( '_ufsc_weight', (string) $values['ufsc_weight'] ); }
	if ( isset( $values['ufsc_person_identifier'] ) ) { $item->add_meta_data( '_ufsc_person_identifier', sanitize_text_field( (string) $values['ufsc_person_identifier'] ) ); }
	if ( isset( $values['ufsc_previous_licence_id'] ) ) { $item->add_meta_data( '_ufsc_previous_licence_id', absint( $values['ufsc_previous_licence_id'] ) ); }
	if ( isset( $values['ufsc_numero_licence_ufsc'] ) ) { $item->add_meta_data( '_ufsc_numero_licence_ufsc', sanitize_text_field( (string) $values['ufsc_numero_licence_ufsc'] ) ); }
	if ( isset( $values['ufsc_category'] ) ) { $item->add_meta_data( '_ufsc_category', sanitize_text_field( (string) $values['ufsc_category'] ) ); }
	if ( isset( $values['ufsc_renewal_updates'] ) && is_array( $values['ufsc_renewal_updates'] ) ) { $item->add_meta_data( '_ufsc_renewal_updates', $values['ufsc_renewal_updates'] ); }
	if ( isset( $values['ufsc_renewal_changes'] ) && is_array( $values['ufsc_renewal_changes'] ) ) { $item->add_meta_data( '_ufsc_renewal_changes', $values['ufsc_renewal_changes'] ); }
	if ( ! empty( $values['ufsc_sensitive_identity_change'] ) ) { $item->add_meta_data( '_ufsc_sensitive_identity_change', 1 ); }
}

/**
 * Display custom cart item data
 *
 * @param array $item_data Item data
 * @param array $cart_item Cart item
 * @return array Modified item data
 */
function ufsc_display_cart_item_data( $item_data, $cart_item ) {
	// Display club info
	if ( isset( $cart_item['ufsc_club_id'] ) ) {
		$club_id   = absint( $cart_item['ufsc_club_id'] );
		$club_name = ufsc_get_club_name( $club_id );

		if ( $club_name ) {
			$item_data[] = array(
				'key'   => __( 'Club', 'ufsc-clubs' ),
				'value' => $club_name,
			);
		}
		$item_data[] = array(
			'key'   => __( 'ID interne du club', 'ufsc-clubs' ),
			'value' => (string) $club_id,
		);
	}

	$season = isset( $cart_item['ufsc_target_season'] ) ? sanitize_text_field( (string) $cart_item['ufsc_target_season'] ) : ( isset( $cart_item['ufsc_season'] ) ? sanitize_text_field( (string) $cart_item['ufsc_season'] ) : '' );
	if ( $season ) {
		$item_data[] = array( 'key' => __( 'Saison', 'ufsc-clubs' ), 'value' => $season );
	}
	if ( ! empty( $cart_item['ufsc_request_type'] ) || 'renew_affiliation' === ( $cart_item['ufsc_action'] ?? '' ) ) {
		$item_data[] = array( 'key' => __( 'Demande', 'ufsc-clubs' ), 'value' => __( 'Renouvellement d’affiliation', 'ufsc-clubs' ) );
	}

	// Display license IDs (lot)
	if ( isset( $cart_item['ufsc_license_ids'] ) && is_array( $cart_item['ufsc_license_ids'] ) ) {
		$license_count = count( $cart_item['ufsc_license_ids'] );
		$item_data[]   = array(
			'key'   => __( 'Licences', 'ufsc-clubs' ),
			'value' => sprintf( __( '%d licence(s) spécifique(s)', 'ufsc-clubs' ), $license_count ),
		);
	}

	// Display single licence ID
	if ( isset( $cart_item['ufsc_licence_id'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Licence', 'ufsc-clubs' ),
			'value' => '#' . intval( $cart_item['ufsc_licence_id'] ),
		);
	}

	// Display personal data
	if ( isset( $cart_item['ufsc_nom'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Nom', 'ufsc-clubs' ),
			'value' => sanitize_text_field( $cart_item['ufsc_nom'] ),
		);
	}
	if ( isset( $cart_item['ufsc_prenom'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Prénom', 'ufsc-clubs' ),
			'value' => sanitize_text_field( $cart_item['ufsc_prenom'] ),
		);
	}
	if ( isset( $cart_item['ufsc_date_naissance'] ) && $cart_item['ufsc_date_naissance'] ) {
		$item_data[] = array(
			'key'   => __( 'Date de naissance', 'ufsc-clubs' ),
			'value' => sanitize_text_field( $cart_item['ufsc_date_naissance'] ),
		);
	}
	if ( ! empty( $cart_item['ufsc_fighter_level'] ) ) { $item_data[] = array( 'key' => __( 'Niveau sportif', 'ufsc-clubs' ), 'value' => ufsc_fighter_level_label( $cart_item['ufsc_fighter_level'] ) ); }
	if ( isset( $cart_item['ufsc_weight'] ) ) { $item_data[] = array( 'key' => __( 'Poids', 'ufsc-clubs' ), 'value' => str_replace( '.', ',', (string) $cart_item['ufsc_weight'] ) . ' kg' ); }
	if ( ! empty( $cart_item['ufsc_category'] ) ) { $item_data[] = array( 'key' => __( 'Catégorie', 'ufsc-clubs' ), 'value' => sanitize_text_field( $cart_item['ufsc_category'] ) ); }

	return $item_data;
}

/**
 * Get club name by ID.
 *
 * Looks up the `nom` column in the configured clubs table. Returns the name
 * or `false` when no matching club exists.
 *
 * @param int $club_id Club ID.
 * @return string|false Club name or false if not found.
 */
function ufsc_get_club_name( $club_id ) {
	global $wpdb;

	$club_id = absint( $club_id );
	if ( $club_id <= 0 ) {
		return false;
	}

	if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
		return false;
	}

	$clubs_table = ufsc_get_clubs_table();

	$club_name = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT nom FROM {$clubs_table} WHERE id = %d",
			$club_id
		)
	);

	return ! empty( $club_name ) ? $club_name : false;
}

/**
 * Add affiliation pack to cart for a club.
 * This function can be called from other parts of the plugin.
 *
 * @param int $club_id Club ID
 * @return bool True on success
 */
function ufsc_add_affiliation_to_cart( $club_id ) {
	if ( ! function_exists( 'ufsc_is_woocommerce_active' ) || ! ufsc_is_woocommerce_active() ) {
		return false;
	}

	if ( ! function_exists( 'WC' ) ) {
		return false;
	}

	if ( is_null( WC()->cart ) ) {
		function_exists( 'wc_load_cart' ) && wc_load_cart();
	}

	$wc_settings = function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings() : array();
	$product_id  = isset( $wc_settings['product_affiliation_id'] ) ? absint( $wc_settings['product_affiliation_id'] ) : 0;

	// Fallback: preserve previous behavior if settings missing.
	if ( $product_id <= 0 ) {
		return false;
	}

	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
	if ( ! $product || ! $product->exists() ) {
		return false;
	}

	$cart_item_data = array(
		'ufsc_club_id' => absint( $club_id ),
	);

	$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

	return $cart_item_key !== false;
}

/**
 * Apply included licence quota to cart items.
 *
 * Sets licence product price to zero when the club still has included
 * licences available. Flagged items receive the ufsc_consumes_included
 * marker so the quota can be updated on successful order.
 *
 * @param WC_Cart $cart Cart object.
 */
function ufsc_apply_included_quota_to_cart( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	if ( ! function_exists( 'ufsc_is_woocommerce_active' ) || ! ufsc_is_woocommerce_active() ) {
		return;
	}

	if ( function_exists( 'ufsc_quotas_enabled' ) && ! ufsc_quotas_enabled() ) {
		return;
	}

	$settings           = function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings() : array();
	$licence_product_id = isset( $settings['product_license_id'] ) ? (int) $settings['product_license_id'] : 0;
	if ( $licence_product_id <= 0 ) {
		return;
	}

	$club_remaining = array();

	foreach ( $cart->get_cart() as $key => $item ) {
		if ( (int) ( $item['product_id'] ?? 0 ) !== $licence_product_id ) {
			continue;
		}

		$club_id = isset( $item['ufsc_club_id'] )
			? absint( $item['ufsc_club_id'] )
			: ( function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0 );

		if ( ! $club_id ) {
			continue;
		}

		if ( ! isset( $club_remaining[ $club_id ] ) ) {
			$club_remaining[ $club_id ] = 0;

			if ( function_exists( 'ufsc_get_clubs_table' ) ) {
				global $wpdb;

				$clubs_table = ufsc_get_clubs_table();

				$quota = (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT included_quota FROM {$clubs_table} WHERE id = %d", $club_id )
				);

				$used = (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT included_quota_used FROM {$clubs_table} WHERE id = %d", $club_id )
				);

				$club_remaining[ $club_id ] = max( 0, $quota - $used );
			}
		}

		if ( $club_remaining[ $club_id ] > 0 && isset( $item['data'] ) && is_object( $item['data'] ) ) {
			$item['data']->set_price( 0 );
			$cart->cart_contents[ $key ]['ufsc_consumes_included'] = 1;
			$club_remaining[ $club_id ]--;
		}
	}
}

add_action( 'woocommerce_before_calculate_totals', 'ufsc_apply_included_quota_to_cart', 10, 1 );

// Initialize cart integration if WooCommerce is active
add_action( 'plugins_loaded', 'ufsc_init_cart_integration' );

// Hook to display cart item data
add_filter( 'woocommerce_get_item_data', 'ufsc_display_cart_item_data', 10, 2 );

if ( ! function_exists( 'ufsc_redirect_with_notice' ) ) {
	/**
	 * Redirect helper that appends notice query args.
	 *
	 * @param string $message      Message to show.
	 * @param string $type         Notice type: success|error.
	 * @param string $redirect_url Optional redirect URL.
	 */
	function ufsc_redirect_with_notice( $message, $type = 'success', $redirect_url = '' ) {
		$redirect_url = $redirect_url ?: ( wp_get_referer() ?: home_url() );
		$key          = ( 'error' === $type ) ? 'ufsc_error' : 'ufsc_message';
		$redirect_url = add_query_arg( $key, rawurlencode( $message ), $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit;
	}
}

/**
 * Redirect to checkout if club is "en_attente" and cart empty; add affiliation product if needed.
 *
 * @param int $existing_club_id Club ID.
 */
function ufsc_club_redirect_cart( $existing_club_id ) {
	global $wpdb;

	if ( ! class_exists( 'UFSC_SQL' ) || ! method_exists( 'UFSC_SQL', 'get_settings' ) ) {
		return;
	}

	$existing_club_id = absint( $existing_club_id );
	if ( $existing_club_id <= 0 ) {
		return;
	}

	$settings = UFSC_SQL::get_settings();
	$table    = $settings['table_clubs'] ?? '';
	$pk       = $settings['pk_club'] ?? 'id';

	if ( ! $table ) {
		return;
	}

	$club_data = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT statut FROM `{$table}` WHERE `{$pk}` = %d",
			$existing_club_id
		),
		ARRAY_A
	);

	if ( $club_data && strtolower( (string) ( $club_data['statut'] ?? '' ) ) === 'en_attente' ) {
		if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
			$cart = WC()->cart;
			if ( empty( $cart->cart_contents ) ) {
				ufsc_add_affiliation_to_cart( $existing_club_id );
			}
		}

		wp_safe_redirect( site_url( '/checkout' ) );
		exit;
	}
}

/**
 * Handle club affiliation form submission.
 *
 * Processes required documents and adds the affiliation product to cart.
 */
function ufsc_club_affiliation_submit() {
	check_admin_referer( 'ufsc_club_affiliation_submit' );

	if ( ! current_user_can( 'read' ) ) {
		ufsc_redirect_with_notice( __( 'Vous devez être connecté', 'ufsc-clubs' ), 'error' );
	}

	$club_id = isset( $_POST['club_id'] ) ? absint( $_POST['club_id'] ) : 0;
	if ( $club_id <= 0 ) {
		ufsc_redirect_with_notice( __( 'Club invalide.', 'ufsc-clubs' ), 'error' );
	}
	$user_club_id = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
	if ( ! current_user_can( 'manage_options' ) && $club_id !== $user_club_id ) {
		ufsc_redirect_with_notice( __( 'Vous ne pouvez pas gérer ce club.', 'ufsc-clubs' ), 'error' );
	}

	if ( ! class_exists( 'UFSC_Uploads' ) || ! method_exists( 'UFSC_Uploads', 'handle_required_docs' ) ) {
		ufsc_redirect_with_notice( __( 'Système de documents indisponible.', 'ufsc-clubs' ), 'error' );
	}

	$uploads = UFSC_Uploads::handle_required_docs( $_FILES );
	if ( is_wp_error( $uploads ) ) {
		ufsc_redirect_with_notice( $uploads->get_error_message(), 'error' );
	}

	$added = false;
	if ( function_exists( 'WC' ) ) {
		function_exists( 'wc_load_cart' ) && wc_load_cart();

		$product_id = function_exists( 'ufsc_get_affiliation_product_id' ) ? ufsc_get_affiliation_product_id() : 4823;
		$added = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'ufsc_club_id' => $club_id ) );
	}

	if ( ! $added ) {
		ufsc_redirect_with_notice( __( 'Impossible d\'ajouter le produit au panier.', 'ufsc-clubs' ), 'error' );
	}

	$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url();
	ufsc_redirect_with_notice( __( 'Produit d\'affiliation ajouté au panier.', 'ufsc-clubs' ), 'success', $cart_url );
}

add_action( 'admin_post_ufsc_club_affiliation_submit', 'ufsc_club_affiliation_submit' );
add_action( 'admin_post_nopriv_ufsc_club_affiliation_submit', 'ufsc_club_affiliation_submit' );
