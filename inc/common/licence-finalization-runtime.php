<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Runtime wiring for the canonical licence finalization service.
 *
 * The service decides included vs payable. Existing controllers keep ownership
 * of form validation and WooCommerce cart persistence.
 */
final class UFSC_Licence_Finalization_Runtime {
	/** @var array<int,array|WP_Error> */
	private static $results = array();

	/** @var bool */
	private static $skip_unified_quota_recheck = false;

	/** @var bool */
	private static $notice_rendered = false;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'normalize_final_intent_post' ), 0 );
		add_action( 'init', array( __CLASS__, 'replace_legacy_finalizers' ), 99 );
		add_action( 'ufsc_licence_created', array( __CLASS__, 'finalize_created_licence' ), 0, 2 );
		add_action( 'ufsc_licence_updated', array( __CLASS__, 'finalize_updated_licence' ), 0, 1 );
		add_filter( 'ufsc_renewal_target_draft_result', array( __CLASS__, 'finalize_renewal_target' ), 5, 5 );
		add_filter( 'ufsc_quotas_enabled', array( __CLASS__, 'maybe_skip_unified_quota_recheck' ), 999 );
		add_filter( 'wp_redirect', array( __CLASS__, 'rewrite_unified_redirect' ), 10, 2 );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'prepend_portal_notice' ), 5, 4 );
	}

	/**
	 * Treat submit_for_validation as the canonical finalisation intent before the
	 * historical Unified Handler validates its checkout-specific fields.
	 */
	public static function normalize_final_intent_post() {
		if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			return;
		}

		$action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
			? sanitize_key( wp_unslash( $_POST['action'] ) )
			: '';
		if ( ! in_array( $action, array( 'ufsc_add_licence', 'ufsc_save_licence', 'ufsc_update_licence' ), true ) ) {
			return;
		}

		$intent = isset( $_POST['ufsc_submit_action'] ) && ! is_array( $_POST['ufsc_submit_action'] )
			? sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) )
			: '';
		if ( 'submit_for_validation' !== $intent ) {
			return;
		}

		$_POST['ufsc_final_intent']  = 'submit_for_validation';
		$_POST['ufsc_submit_action'] = 'add_to_cart';
	}

	/**
	 * Remove the two after-the-fact finalizers and replace the Journey controller.
	 * Routing/UI helpers from those modules remain active.
	 */
	public static function replace_legacy_finalizers() {
		remove_action( 'ufsc_licence_created', 'ufsc_structural_finalize_created_licence', 1 );
		remove_action( 'ufsc_licence_updated', 'ufsc_structural_finalize_updated_request', 1 );

		remove_action( 'admin_post_ufsc_journey_finalize_licence', 'ufsc_journey_finalize_licence' );
		add_action( 'admin_post_ufsc_journey_finalize_licence', array( __CLASS__, 'handle_journey_finalize_licence' ) );
	}

	public static function finalize_created_licence( $licence_id, $club_id = 0 ) {
		if ( ! self::is_unified_final_request() ) {
			return;
		}
		self::finalize_for_unified_request( absint( $licence_id ), absint( $club_id ), 'unified_new' );
	}

	public static function finalize_updated_licence( $club_id ) {
		if ( ! self::is_unified_final_request() ) {
			return;
		}
		$licence_id = isset( $_POST['licence_id'] ) && ! is_array( $_POST['licence_id'] )
			? absint( wp_unslash( $_POST['licence_id'] ) )
			: 0;
		if ( $licence_id < 1 || isset( self::$results[ $licence_id ] ) ) {
			return;
		}
		self::finalize_for_unified_request( $licence_id, absint( $club_id ), 'unified_update' );
	}

	/**
	 * Finalise the current-season target row of a renewal before the cart helper
	 * decides whether to create a paid line. Returning WP_Error makes the existing
	 * renewal controller fail closed without touching the historical source row.
	 */
	public static function finalize_renewal_target( $target, $source, $club_id, $season, $updates ) {
		unset( $source, $updates );
		if ( is_wp_error( $target ) || ! self::is_renewal_final_request() ) {
			return $target;
		}
		$licence_id = absint( is_array( $target ) ? ( $target['licence_id'] ?? 0 ) : 0 );
		if ( $licence_id < 1 || ! class_exists( 'UFSC_Licence_Finalization_Service' ) ) {
			return $target;
		}

		$result = UFSC_Licence_Finalization_Service::finalize( $licence_id, absint( $club_id ), (string) $season, 'renewal' );
		self::$results[ $licence_id ] = $result;
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$target['ufsc_finalization'] = $result;
		return $target;
	}

	private static function is_renewal_final_request() {
		if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			return false;
		}
		$action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
			? sanitize_key( wp_unslash( $_POST['action'] ) )
			: '';
		if ( ! in_array( $action, array( 'ufsc_bulk_renew_licences', 'ufsc_renew_licence' ), true ) ) {
			return false;
		}
		$intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] )
			? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) )
			: '';
		if ( '' === $intent && isset( $_POST['ufsc_submit_action'] ) && ! is_array( $_POST['ufsc_submit_action'] ) ) {
			$intent = sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) );
		}
		return in_array( $intent, array( 'add_to_cart', 'submit_for_validation', 'finalize' ), true );
	}

	/**
	 * The service has already made the payable decision; prevent the historical
	 * Unified Handler from asking the quota allocator a second time in this request.
	 */
	public static function maybe_skip_unified_quota_recheck( $enabled ) {
		return self::$skip_unified_quota_recheck ? false : $enabled;
	}

	private static function finalize_for_unified_request( $licence_id, $club_id, $context ) {
		if ( $licence_id < 1 || isset( self::$results[ $licence_id ] ) || ! class_exists( 'UFSC_Licence_Finalization_Service' ) ) {
			return;
		}

		$result = UFSC_Licence_Finalization_Service::finalize( $licence_id, $club_id, '', $context );
		self::$results[ $licence_id ] = $result;

		if ( is_wp_error( $result ) || ! empty( $result['included'] ) ) {
			// The canonical service either completed the included transition or failed
			// closed. In both cases the old handler must not create a cart line.
			$_POST['ufsc_submit_action'] = 'continue';
			return;
		}

		if ( ! empty( $result['payable'] ) ) {
			self::$skip_unified_quota_recheck = true;
		}
	}

	private static function is_unified_final_request() {
		if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			return false;
		}
		$action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
			? sanitize_key( wp_unslash( $_POST['action'] ) )
			: '';
		if ( ! in_array( $action, array( 'ufsc_add_licence', 'ufsc_save_licence', 'ufsc_update_licence' ), true ) ) {
			return false;
		}
		$intent = isset( $_POST['ufsc_submit_action'] ) && ! is_array( $_POST['ufsc_submit_action'] )
			? sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) )
			: '';
		$final_intent = isset( $_POST['ufsc_final_intent'] ) && ! is_array( $_POST['ufsc_final_intent'] )
			? sanitize_key( wp_unslash( $_POST['ufsc_final_intent'] ) )
			: '';

		return 'add_to_cart' === $intent || 'submit_for_validation' === $final_intent;
	}

	/**
	 * Rewrite only the success/error redirect of the historical Unified Handler.
	 */
	public static function rewrite_unified_redirect( $location, $status = 302 ) {
		unset( $status );
		if ( empty( self::$results ) || ! self::is_unified_request_action() ) {
			return $location;
		}

		$licence_id = self::resolved_result_licence_id();
		if ( $licence_id < 1 || ! isset( self::$results[ $licence_id ] ) ) {
			return $location;
		}
		$result = self::$results[ $licence_id ];
		if ( ! is_wp_error( $result ) && empty( $result['included'] ) ) {
			return $location; // Paid branch owns its WooCommerce redirect.
		}

		$location = remove_query_arg( array( 'licence_saved', 'created', 'updated', 'licence_included', 'ufsc_message', 'ufsc_error', 'pack_bucket' ), $location );
		if ( is_wp_error( $result ) ) {
			return add_query_arg(
				array(
					'ufsc_error' => $result->get_error_message(),
					'licence_id' => $licence_id,
				),
				$location
			);
		}

		return add_query_arg(
			array(
				'ufsc_message'      => 'licence_included',
				'licence_included' => 1,
				'licence_id'       => $licence_id,
				'pack_bucket'      => sanitize_key( (string) ( $result['bucket'] ?? 'libre' ) ),
			),
			$location
		);
	}

	private static function is_unified_request_action() {
		$action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
			? sanitize_key( wp_unslash( $_POST['action'] ) )
			: '';
		return in_array( $action, array( 'ufsc_add_licence', 'ufsc_save_licence', 'ufsc_update_licence' ), true );
	}

	private static function resolved_result_licence_id() {
		$posted = isset( $_POST['licence_id'] ) && ! is_array( $_POST['licence_id'] ) ? absint( wp_unslash( $_POST['licence_id'] ) ) : 0;
		if ( $posted > 0 && isset( self::$results[ $posted ] ) ) {
			return $posted;
		}
		$ids = array_keys( self::$results );
		return $ids ? absint( end( $ids ) ) : 0;
	}

	/**
	 * Canonical replacement for the Journey finalisation endpoint.
	 */
	public static function handle_journey_finalize_licence() {
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

		$club_id = absint( $licence->club_id ?? 0 );
		if ( ! class_exists( 'UFSC_Licence_Finalization_Service' ) ) {
			self::journey_redirect_error( __( 'Le service de finalisation des licences est indisponible.', 'ufsc-clubs' ), $licence_id );
		}

		$result = UFSC_Licence_Finalization_Service::finalize( $licence_id, $club_id, '', 'journey' );
		if ( is_wp_error( $result ) ) {
			self::journey_redirect_error( $result->get_error_message(), $licence_id );
		}

		if ( ! empty( $result['included'] ) ) {
			do_action( 'ufsc_licence_updated', $club_id );
			$url = add_query_arg(
				array(
					'ufsc_message'      => 'licence_included',
					'licence_included' => 1,
					'licence_id'       => $licence_id,
					'pack_bucket'      => sanitize_key( (string) ( $result['bucket'] ?? 'libre' ) ),
				),
				wp_get_referer() ?: home_url()
			);
			wp_safe_redirect( $url );
			exit;
		}

		if ( empty( $result['payable'] ) ) {
			self::journey_redirect_error( __( 'La décision de paiement de cette licence est indéterminée.', 'ufsc-clubs' ), $licence_id );
		}

		if ( ! function_exists( 'ufsc_handle_add_to_cart_secure' ) ) {
			self::journey_redirect_error( __( 'Le panier WooCommerce est indisponible.', 'ufsc-clubs' ), $licence_id );
		}

		$product_id = function_exists( 'ufsc_get_licence_product_id' ) ? absint( ufsc_get_licence_product_id() ) : 0;
		if ( $product_id < 1 || ( function_exists( 'ufsc_is_woocommerce_product_available' ) && ! ufsc_is_woocommerce_product_available( $product_id ) ) ) {
			self::journey_redirect_error( __( 'Le produit Licence UFSC est indisponible. Le dossier a été conservé.', 'ufsc-clubs' ), $licence_id );
		}

		$_POST['_ufsc_nonce']      = wp_create_nonce( 'ufsc_add_to_cart_action' );
		$_POST['product_id']       = $product_id;
		$_POST['ufsc_action']      = 'new_licence';
		$_POST['ufsc_club_id']     = $club_id;
		$_POST['ufsc_license_ids'] = (string) $licence_id;
		$_POST['ufsc_licence_id']  = $licence_id;
		ufsc_handle_add_to_cart_secure();

		self::journey_redirect_error( __( 'Le panier n’a pas confirmé l’ajout de la licence.', 'ufsc-clubs' ), $licence_id );
	}

	private static function journey_redirect_error( $message, $licence_id ) {
		$url = add_query_arg(
			array(
				'ufsc_error' => sanitize_text_field( (string) $message ),
				'licence_id' => absint( $licence_id ),
			),
			wp_get_referer() ?: home_url()
		);
		wp_safe_redirect( $url );
		exit;
	}

	/** Render one clear success/error message after a finalisation redirect. */
	public static function prepend_portal_notice( $output, $tag, $attr, $m ) {
		unset( $attr, $m );
		if ( self::$notice_rendered || ! in_array( $tag, array( 'ufsc_club_licences', 'ufsc_add_licence' ), true ) ) {
			return $output;
		}

		$error = isset( $_GET['ufsc_error'] ) && ! is_array( $_GET['ufsc_error'] )
			? sanitize_text_field( wp_unslash( $_GET['ufsc_error'] ) )
			: '';
		$message_code = isset( $_GET['ufsc_message'] ) && ! is_array( $_GET['ufsc_message'] )
			? sanitize_key( wp_unslash( $_GET['ufsc_message'] ) )
			: '';

		if ( '' === $error && 'licence_included' !== $message_code ) {
			return $output;
		}

		self::$notice_rendered = true;
		if ( '' !== $error ) {
			$notice = '<div class="ufsc-message ufsc-error" role="alert"><strong>'
				. esc_html__( 'Finalisation impossible :', 'ufsc-clubs' )
				. '</strong> ' . esc_html( $error ) . '</div>';
			return $notice . $output;
		}

		$notice = '<div class="ufsc-message ufsc-success" role="status"><strong>'
			. esc_html__( 'Licence envoyée pour validation.', 'ufsc-clubs' )
			. '</strong> ' . esc_html__( 'Elle est incluse dans l’affiliation du club : aucun paiement supplémentaire n’est nécessaire.', 'ufsc-clubs' )
			. '</div>';
		return $notice . $output;
	}
}

UFSC_Licence_Finalization_Runtime::init();
