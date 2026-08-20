<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Last production boundary for WooCommerce.
 *
 * - A paid renewal pays the already-created current-season target licence.
 *   It must not trigger the historical post-payment copier a second time.
 * - A newly-created club is sent directly to its annual affiliation cart item.
 */
final class UFSC_Production_Payment_Boundary {

	public static function init() {
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'normalize_paid_renewal_item' ), 100, 2 );
		add_filter( 'wp_redirect', array( __CLASS__, 'chain_new_club_to_affiliation' ), 5, 2 );
	}

	/**
	 * The renewal service already created the 2026-2027 target row before cart.
	 * Rename only the Woo action so ufsc_wc_process_renewal_items() does not copy
	 * the historical source again after payment. Generic paid-order sync still
	 * receives ufsc_licence_id and promotes that existing target row.
	 */
	public static function normalize_paid_renewal_item( $cart_item_data, $product_id ) {
		unset( $product_id );
		$action    = sanitize_key( (string) ( $cart_item_data['ufsc_action'] ?? '' ) );
		$target_id = absint( $cart_item_data['ufsc_licence_id'] ?? 0 );
		$source_id = absint( $cart_item_data['ufsc_renew_from_licence_id'] ?? $cart_item_data['ufsc_previous_licence_id'] ?? 0 );
		if ( 'renew_licence' !== $action || $target_id < 1 || $source_id < 1 ) {
			return $cart_item_data;
		}

		$cart_item_data['ufsc_original_action'] = 'renew_licence';
		$cart_item_data['ufsc_action']          = 'licence_payment';
		$cart_item_data['ufsc_operation_type']  = 'renewal';
		$cart_item_data['ufsc_request_type']    = 'renewal';
		return $cart_item_data;
	}

	/**
	 * After UFSC_Affiliation_Form has really created the club, its success redirect
	 * contains ufsc_notice=club_created. At that precise boundary, create the
	 * annual affiliation cart line and redirect to the cart. Validation errors or
	 * unsuccessful club creation keep their original redirect untouched.
	 */
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Runs only on the success redirect emitted after UFSC_Affiliation_Form validated its create-club nonce.
	public static function chain_new_club_to_affiliation( $location, $status = 302 ) {
		unset( $status );
		if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			return $location;
		}
		$action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( 'ufsc_create_club' !== $action || false === strpos( (string) $location, 'ufsc_notice=club_created' ) ) {
			return $location;
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			return $location;
		}

		$club_id = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
		if ( $club_id < 1 ) {
			return $location;
		}
		$season = class_exists( 'UFSC_Season_Service' )
			? (string) UFSC_Season_Service::get_current_season()
			: ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
		if ( '' === $season ) {
			return $location;
		}

		if ( function_exists( 'ufsc_is_club_affiliated_for_season' ) && ufsc_is_club_affiliated_for_season( $club_id, $season ) ) {
			return $location;
		}
		if ( function_exists( 'ufsc_cart_has_renewal_item' ) && ufsc_cart_has_renewal_item( 'renew_affiliation', $club_id, $season ) ) {
			return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $location;
		}

		$product_id = function_exists( 'ufsc_get_affiliation_product_id' ) ? absint( ufsc_get_affiliation_product_id() ) : 0;
		if ( $product_id < 1 || ( function_exists( 'ufsc_is_woocommerce_product_available' ) && ! ufsc_is_woocommerce_product_available( $product_id ) ) ) {
			self::notice( __( 'Le club a été créé, mais le produit d’affiliation UFSC est indisponible. Contactez l’UFSC.', 'ufsc-clubs' ), 'error' );
			return $location;
		}

		$ready = function_exists( 'ufsc_ensure_woocommerce_cart' ) ? ufsc_ensure_woocommerce_cart() : new WP_Error( 'woo_unavailable', __( 'WooCommerce est indisponible.', 'ufsc-clubs' ) );
		if ( is_wp_error( $ready ) ) {
			self::notice( $ready->get_error_message(), 'error' );
			return $location;
		}
		$args = function_exists( 'ufsc_get_cart_product_arguments' )
			? ufsc_get_cart_product_arguments( $product_id )
			: array( 'product_id' => $product_id, 'variation_id' => 0, 'variation' => array() );
		if ( is_wp_error( $args ) ) {
			self::notice( $args->get_error_message(), 'error' );
			return $location;
		}

		$data = array(
			'ufsc_action'                  => 'renew_affiliation',
			'ufsc_request_type'            => 'new_affiliation',
			'ufsc_item_type'               => 'affiliation_renewal',
			'ufsc_operation_type'          => 'new_affiliation',
			'ufsc_club_id'                 => $club_id,
			'ufsc_target_season'           => $season,
			'ufsc_previous_affiliation_id' => 0,
			'ufsc_user_id'                 => get_current_user_id(),
			'ufsc_product_id'              => $product_id,
			'ufsc_cart_identity'           => hash( 'sha256', $club_id . '|affiliation|' . $season ),
		);
		$key = WC()->cart->add_to_cart( absint( $args['product_id'] ), 1, absint( $args['variation_id'] ), (array) $args['variation'], $data );
		if ( ! $key ) {
			self::notice( __( 'Le club a été créé, mais son affiliation n’a pas pu être ajoutée au panier.', 'ufsc-clubs' ), 'error' );
			return $location;
		}
		if ( method_exists( WC()->cart, 'set_quantity' ) ) {
			WC()->cart->set_quantity( $key, 1, false );
		}
		$persisted = function_exists( 'ufsc_persist_woocommerce_cart' ) ? ufsc_persist_woocommerce_cart() : true;
		if ( is_wp_error( $persisted ) ) {
			self::notice( $persisted->get_error_message(), 'error' );
			return $location;
		}

		self::notice( __( 'Votre club est créé. Finalisez maintenant son affiliation pour la saison en cours.', 'ufsc-clubs' ), 'success' );
		return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : ( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : $location );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	private static function notice( $message, $type ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( sanitize_text_field( (string) $message ), sanitize_key( (string) $type ) );
		}
	}
}

UFSC_Production_Payment_Boundary::init();
