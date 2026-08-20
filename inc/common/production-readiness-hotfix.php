<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Production-readiness bridge for the 2026-2027 opening.
 *
 * This file deliberately owns only the final mutation boundaries that are still
 * duplicated in legacy controllers. Selection, draft, verification and display
 * remain owned by their existing components.
 */
final class UFSC_Production_Readiness_Hotfix {

	public static function init() {
		add_action( 'wp_loaded', array( __CLASS__, 'bind_final_handlers' ), 999 );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'repair_affiliation_form_markup' ), 1, 4 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'persist_affiliation_cart' ), 100, 6 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'remove_legacy_portal_layout_css' ), 1002 );
	}

	/**
	 * Make final mutation endpoints deterministic after every legacy module has
	 * registered its hooks. Non-final renewal intents continue to the old handler.
	 */
	public static function bind_final_handlers() {
		if ( class_exists( 'UFSC_Licence_Finalization_Runtime' ) ) {
			remove_action( 'admin_post_ufsc_journey_finalize_licence', 'ufsc_journey_finalize_licence' );
			remove_action( 'admin_post_ufsc_journey_finalize_licence', array( 'UFSC_Licence_Finalization_Runtime', 'handle_journey_finalize_licence' ) );
			add_action( 'admin_post_ufsc_journey_finalize_licence', array( 'UFSC_Licence_Finalization_Runtime', 'handle_journey_finalize_licence' ), 1 );
		}

		add_action( 'admin_post_ufsc_bulk_renew_licences', array( __CLASS__, 'handle_final_renewal_request' ), 1 );

		if ( class_exists( 'UFSC_Affiliation_Form' ) ) {
			remove_action( 'admin_post_ufsc_affiliation_pay', array( 'UFSC_Affiliation_Form', 'handle_affiliation_pay' ) );
			add_action( 'admin_post_ufsc_affiliation_pay', array( __CLASS__, 'handle_affiliation_pay' ), 1 );
		}
	}

	/** Remove the accidental outer form emitted before the real affiliation form. */
	public static function repair_affiliation_form_markup( $output, $tag, $attr, $m ) {
		unset( $attr, $m );
		if ( 'ufsc_affiliation_form' !== $tag || ! is_string( $output ) ) {
			return $output;
		}

		$pattern = '#<form\s+method="post"\s+action="[^"]*admin-post\.php"\s+class="ufsc-form">\s*(?=<form\s+method="post"[^>]*class="ufsc-form\s+ufsc-grid")#i';
		$repaired = preg_replace( $pattern, '', $output, 1 );
		return is_string( $repaired ) ? $repaired : $output;
	}

	/**
	 * Legacy mobile hardening overlaps the canonical portal stylesheet and can
	 * collapse the desktop profile grid. The clean stylesheet already owns the
	 * responsive contract.
	 */
	public static function remove_legacy_portal_layout_css() {
		if ( wp_style_is( 'ufsc-portal-clean', 'enqueued' ) ) {
			wp_dequeue_style( 'ufsc-club-mobile-v2' );
			wp_deregister_style( 'ufsc-club-mobile-v2' );
		}
	}

	/** Persist every affiliation cart mutation in admin-post contexts. */
	public static function persist_affiliation_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		unset( $cart_item_key, $quantity, $variation_id, $variation, $cart_item_data );
		$affiliation_product = function_exists( 'ufsc_get_affiliation_product_id' ) ? absint( ufsc_get_affiliation_product_id() ) : 0;
		if ( $affiliation_product > 0 && absint( $product_id ) === $affiliation_product && function_exists( 'ufsc_persist_woocommerce_cart' ) ) {
			ufsc_persist_woocommerce_cart();
		}
	}

	/** Robust non-AJAX affiliation payment entry point. */
	public static function handle_affiliation_pay() {
		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
		}
		check_admin_referer( 'ufsc_affiliation_pay', 'ufsc_affiliation_nonce' );

		$club_id = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
		if ( $club_id < 1 ) {
			self::redirect_with_error( __( 'Créez d’abord votre club avant de régler son affiliation.', 'ufsc-clubs' ) );
		}

		$season = class_exists( 'UFSC_Season_Service' )
			? (string) UFSC_Season_Service::get_current_season()
			: ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
		if ( function_exists( 'ufsc_is_club_affiliated_for_season' ) && ufsc_is_club_affiliated_for_season( $club_id, $season ) ) {
			self::redirect_with_error( __( 'Votre affiliation est déjà active pour cette saison.', 'ufsc-clubs' ) );
		}

		$product_id = function_exists( 'ufsc_get_affiliation_product_id' ) ? absint( ufsc_get_affiliation_product_id() ) : 0;
		if ( $product_id < 1 || ( function_exists( 'ufsc_is_woocommerce_product_available' ) && ! ufsc_is_woocommerce_product_available( $product_id ) ) ) {
			self::redirect_with_error( __( 'Le produit d’affiliation UFSC est indisponible.', 'ufsc-clubs' ) );
		}

		$ready = function_exists( 'ufsc_ensure_woocommerce_cart' ) ? ufsc_ensure_woocommerce_cart() : new WP_Error( 'woo_unavailable', __( 'WooCommerce est indisponible.', 'ufsc-clubs' ) );
		if ( is_wp_error( $ready ) ) {
			self::redirect_with_error( $ready->get_error_message() );
		}

		if ( function_exists( 'ufsc_cart_has_renewal_item' ) && ufsc_cart_has_renewal_item( 'renew_affiliation', $club_id, $season ) ) {
			wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url() );
			exit;
		}

		$args = function_exists( 'ufsc_get_cart_product_arguments' ) ? ufsc_get_cart_product_arguments( $product_id ) : array( 'product_id' => $product_id, 'variation_id' => 0, 'variation' => array() );
		if ( is_wp_error( $args ) ) {
			self::redirect_with_error( $args->get_error_message() );
		}

		$data = array(
			'ufsc_action'        => 'renew_affiliation',
			'ufsc_request_type'  => 'renewal',
			'ufsc_item_type'     => 'affiliation_renewal',
			'ufsc_club_id'       => $club_id,
			'ufsc_target_season' => $season,
			'ufsc_user_id'       => get_current_user_id(),
			'ufsc_product_id'    => $product_id,
		);
		$key = WC()->cart->add_to_cart( absint( $args['product_id'] ), 1, absint( $args['variation_id'] ), (array) $args['variation'], $data );
		if ( ! $key ) {
			self::redirect_with_error( __( 'Impossible d’ajouter l’affiliation au panier.', 'ufsc-clubs' ) );
		}
		if ( method_exists( WC()->cart, 'set_quantity' ) ) {
			WC()->cart->set_quantity( $key, 1, false );
		}
		$persisted = function_exists( 'ufsc_persist_woocommerce_cart' ) ? ufsc_persist_woocommerce_cart() : true;
		if ( is_wp_error( $persisted ) ) {
			self::redirect_with_error( $persisted->get_error_message() );
		}

		wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : ( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url() ) );
		exit;
	}

	/**
	 * Intercept final renewal only. Verify/save/cancel continue to the established
	 * assistant handler so the UI workflow is unchanged.
	 */
	public static function handle_final_renewal_request() {
		if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			return;
		}
		$intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] )
			? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) )
			: 'verify';
		if ( ! in_array( $intent, array( 'add_to_cart', 'submit_for_validation', 'finalize' ), true ) ) {
			return;
		}

		$club_id = isset( $_POST['ufsc_club_id'] ) && ! is_array( $_POST['ufsc_club_id'] ) ? absint( wp_unslash( $_POST['ufsc_club_id'] ) ) : 0;
		check_admin_referer( 'ufsc_bulk_renew_licences_' . $club_id );
		$user_club = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
		if ( ! is_user_logged_in() || $club_id < 1 || $club_id !== $user_club ) {
			wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
		}

		$season = class_exists( 'UFSC_Season_Service' )
			? (string) UFSC_Season_Service::get_current_season()
			: ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
		$posted_season = isset( $_POST['ufsc_target_season'] ) && ! is_array( $_POST['ufsc_target_season'] ) ? sanitize_text_field( wp_unslash( $_POST['ufsc_target_season'] ) ) : '';
		if ( '' !== $posted_season && $posted_season !== $season ) {
			wp_die( esc_html__( 'Saison cible invalide.', 'ufsc-clubs' ) );
		}

		$ids = array();
		foreach ( array( 'ufsc_renew_ids', 'source_ids', 'renew_licence_ids' ) as $ids_key ) {
			if ( isset( $_POST[ $ids_key ] ) && is_array( $_POST[ $ids_key ] ) ) {
				$ids = array_map( 'absint', wp_unslash( $_POST[ $ids_key ] ) );
				break;
			}
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( ! $ids ) {
			self::redirect_with_error( __( 'Aucune licence à renouveler n’a été sélectionnée.', 'ufsc-clubs' ) );
		}

		$profiles = isset( $_POST['renewal_profiles'] ) && is_array( $_POST['renewal_profiles'] )
			? map_deep( wp_unslash( $_POST['renewal_profiles'] ), 'sanitize_text_field' )
			: array();
		$result = self::finalize_renewals( $club_id, $season, $ids, $profiles );
		if ( ! empty( $result['errors'] ) ) {
			self::redirect_with_error( implode( ' ', array_values( $result['errors'] ) ) );
		}

		if ( ! empty( $result['paid'] ) ) {
			wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url() );
			exit;
		}

		$return = wp_get_referer() ?: home_url();
		$return = add_query_arg( array( 'ufsc_message' => 'renewal_included', 'ufsc_renewed' => count( $result['included'] ) ), $return );
		wp_safe_redirect( $return );
		exit;
	}

	private static function finalize_renewals( $club_id, $season, array $source_ids, array $profiles ) {
		global $wpdb;
		$result = array( 'included' => array(), 'paid' => array(), 'errors' => array() );
		$gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id, $season ) : array( 'allowed' => false, 'message' => __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ) );
		if ( empty( $gate['allowed'] ) ) {
			$result['errors']['affiliation'] = (string) ( $gate['message'] ?? __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ) );
			return $result;
		}
		if ( ! class_exists( 'UFSC_Renewal_Service' ) || ! class_exists( 'UFSC_Licence_Finalization_Service' ) || ! function_exists( 'ufsc_get_licences_table' ) ) {
			$result['errors']['service'] = __( 'Le service de renouvellement est indisponible.', 'ufsc-clubs' );
			return $result;
		}

		$table = ufsc_get_licences_table();
		$product_id = function_exists( 'ufsc_get_licence_product_id' ) ? absint( ufsc_get_licence_product_id() ) : 0;
		$product_args = null;

		foreach ( $source_ids as $source_id ) {
			$source = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $source_id ) );
			if ( ! $source || absint( $source->club_id ?? 0 ) !== $club_id ) {
				$result['errors'][ $source_id ] = __( 'Licence inaccessible.', 'ufsc-clubs' );
				continue;
			}
			$renewable = UFSC_Renewal_Service::can_renew( $source, $club_id, $season );
			if ( is_wp_error( $renewable ) ) {
				$result['errors'][ $source_id ] = $renewable->get_error_message();
				continue;
			}
			$profile = UFSC_Renewal_Service::sanitize_renewal_updates( $source, $profiles[ $source_id ] ?? array() );
			if ( ! empty( $profile['errors'] ) ) {
				$result['errors'][ $source_id ] = implode( ' ', array_values( $profile['errors'] ) );
				continue;
			}

			$target = UFSC_Renewal_Service::create_target_draft( $source, $club_id, $season, $profile['data'] );
			if ( is_wp_error( $target ) ) {
				$result['errors'][ $source_id ] = $target->get_error_message();
				continue;
			}
			$target_id = absint( $target['licence_id'] ?? 0 );
			$decision = isset( $target['ufsc_finalization'] ) ? $target['ufsc_finalization'] : UFSC_Licence_Finalization_Service::finalize( $target_id, $club_id, $season, 'renewal_production' );
			if ( is_wp_error( $decision ) ) {
				$result['errors'][ $source_id ] = $decision->get_error_message();
				continue;
			}

			if ( ! empty( $decision['included'] ) ) {
				if ( function_exists( 'ufsc_mark_renewed_licence_marker' ) ) {
					ufsc_mark_renewed_licence_marker( $source_id, $season, $target_id );
				}
				$result['included'][] = $source_id;
				continue;
			}

			if ( empty( $decision['payable'] ) ) {
				$result['errors'][ $source_id ] = __( 'Le mode de règlement de la licence est indéterminé.', 'ufsc-clubs' );
				continue;
			}

			if ( function_exists( 'ufsc_wc_has_pending_renewal_order' ) && ufsc_wc_has_pending_renewal_order( 'renew_licence', $club_id, $season, $source_id ) ) {
				$result['errors'][ $source_id ] = __( 'Une commande de renouvellement existe déjà.', 'ufsc-clubs' );
				continue;
			}
			if ( function_exists( 'ufsc_cart_has_renewal_item' ) && ufsc_cart_has_renewal_item( 'renew_licence', $club_id, $season, $source_id ) ) {
				$result['paid'][] = $source_id;
				continue;
			}

			if ( null === $product_args ) {
				if ( $product_id < 1 || ( function_exists( 'ufsc_is_woocommerce_product_available' ) && ! ufsc_is_woocommerce_product_available( $product_id ) ) ) {
					$result['errors'][ $source_id ] = __( 'Le produit Licence UFSC est indisponible.', 'ufsc-clubs' );
					continue;
				}
				$ready = function_exists( 'ufsc_ensure_woocommerce_cart' ) ? ufsc_ensure_woocommerce_cart() : new WP_Error( 'woo_unavailable', __( 'WooCommerce est indisponible.', 'ufsc-clubs' ) );
				if ( is_wp_error( $ready ) ) {
					$result['errors'][ $source_id ] = $ready->get_error_message();
					continue;
				}
				$product_args = function_exists( 'ufsc_get_cart_product_arguments' ) ? ufsc_get_cart_product_arguments( $product_id ) : array( 'product_id' => $product_id, 'variation_id' => 0, 'variation' => array() );
				if ( is_wp_error( $product_args ) ) {
					$result['errors'][ $source_id ] = $product_args->get_error_message();
					$product_args = null;
					continue;
				}
			}

			$target_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $target_id ) );
			$level = sanitize_text_field( (string) ( $profile['data']['fighter_level'] ?? ( $target_row->fighter_level ?? '' ) ) );
			$weight = sanitize_text_field( (string) ( $profile['data']['poids'] ?? ( $target_row->poids ?? '' ) ) );
			$data = array(
				'ufsc_action'                  => 'renew_licence',
				'ufsc_request_type'            => 'renewal',
				'ufsc_item_type'               => 'licence_renewal',
				'ufsc_club_id'                 => $club_id,
				'ufsc_target_season'           => $season,
				'ufsc_renew_from_licence_id'   => $source_id,
				'ufsc_previous_licence_id'     => $source_id,
				'ufsc_licence_id'              => $target_id,
				'ufsc_license_ids'             => array( $target_id ),
				'ufsc_person_identifier'       => UFSC_Renewal_Service::person_key( $source, $club_id ),
				'ufsc_nom'                     => (string) ( $profile['data']['nom'] ?? $source->nom ?? '' ),
				'ufsc_prenom'                  => (string) ( $profile['data']['prenom'] ?? $source->prenom ?? '' ),
				'ufsc_fighter_level'           => $level,
				'ufsc_weight'                  => $weight,
				'ufsc_renewal_updates'         => $profile['data'],
				'ufsc_renewal_changes'         => $profile['changes'],
				'ufsc_sensitive_identity_change' => ! empty( $profile['sensitive_identity_change'] ),
				'ufsc_cart_identity'           => hash( 'sha256', $club_id . '|' . $source_id . '|' . $season ),
				'quantity'                     => 1,
			);
			$key = WC()->cart->add_to_cart( absint( $product_args['product_id'] ), 1, absint( $product_args['variation_id'] ), (array) $product_args['variation'], $data );
			if ( ! $key ) {
				$result['errors'][ $source_id ] = __( 'Ajout de la licence au panier impossible.', 'ufsc-clubs' );
				continue;
			}
			if ( method_exists( WC()->cart, 'set_quantity' ) ) {
				WC()->cart->set_quantity( $key, 1, false );
			}
			$result['paid'][] = $source_id;
		}

		if ( ! empty( $result['paid'] ) && function_exists( 'ufsc_persist_woocommerce_cart' ) ) {
			$persisted = ufsc_persist_woocommerce_cart();
			if ( is_wp_error( $persisted ) ) {
				$result['errors']['cart'] = $persisted->get_error_message();
				$result['paid'] = array();
			}
		}
		return $result;
	}

	private static function redirect_with_error( $message ) {
		$url = add_query_arg( 'ufsc_error', rawurlencode( sanitize_text_field( (string) $message ) ), wp_get_referer() ?: home_url() );
		wp_safe_redirect( $url );
		exit;
	}
}

UFSC_Production_Readiness_Hotfix::init();
