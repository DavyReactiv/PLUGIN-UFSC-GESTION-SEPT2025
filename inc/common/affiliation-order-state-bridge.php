<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Production bridge between the canonical affiliation product and the annual
 * affiliation table.
 *
 * Goals:
 * - one affiliation pack maximum per club and season;
 * - an order created by bank transfer immediately becomes an annual request;
 * - paid orders move to pending validation, never directly to active;
 * - cancelled/failed/refunded orders reopen renewal unless an admin already
 *   validated the annual affiliation;
 * - existing orders created during deployment can be reconciled idempotently.
 *
 * This class does not touch licence/quota flows.
 */
final class UFSC_Affiliation_Order_State_Bridge {
	const BACKFILL_OPTION  = 'ufsc_affiliation_order_bridge_backfill_20260821';
	const BACKFILL_VERSION = '1';

	public static function init() {
		if ( ! function_exists( 'add_action' ) ) { return; }

		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'sync_order' ), 30 );
		foreach ( array( 'pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed', 'refunded' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'sync_order_id' ), 30 );
		}

		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_affiliation_purchase' ), 999, 5 );
		add_filter( 'gettext', array( __CLASS__, 'frontend_wording' ), 30, 3 );

		// One safe admin-only reconciliation pass for orders created while older
		// plugin versions were deployed. It is idempotent and records no new order.
		add_action( 'admin_init', array( __CLASS__, 'maybe_reconcile_existing_orders' ), 90 );
	}

	public static function sync_order_id( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) { return; }
		$order = wc_get_order( absint( $order_id ) );
		if ( $order ) { self::sync_order( $order ); }
	}

	/** Synchronize every affiliation line of one order. */
	public static function sync_order( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) { return; }

		foreach ( $order->get_items() as $item ) {
			if ( ! self::is_affiliation_item( $item ) ) { continue; }
			$context = self::resolve_order_context( $order, $item );
			if ( empty( $context['club_id'] ) || empty( $context['season'] ) ) {
				self::log( 'affiliation_order_context_unresolved', array( 'order_id' => absint( $order->get_id() ) ) );
				continue;
			}
			self::upsert_request_from_order( $order, $item, $context );
		}
	}

	/** Affiliation product or explicit UFSC affiliation item. */
	private static function is_affiliation_item( $item ) {
		if ( ! $item || ! is_object( $item ) ) { return false; }
		$action = sanitize_key( (string) $item->get_meta( 'ufsc_action', true ) );
		if ( '' === $action ) { $action = sanitize_key( (string) $item->get_meta( '_ufsc_action', true ) ); }
		$type = sanitize_key( (string) $item->get_meta( 'ufsc_item_type', true ) );
		if ( '' === $type ) { $type = sanitize_key( (string) $item->get_meta( '_ufsc_item_type', true ) ); }
		if ( 'renew_affiliation' === $action || 'affiliation_renewal' === $type ) { return true; }

		$product_id = is_callable( array( $item, 'get_product_id' ) ) ? absint( $item->get_product_id() ) : 0;
		$expected   = function_exists( 'ufsc_get_affiliation_product_id' ) ? absint( ufsc_get_affiliation_product_id() ) : 0;
		if ( $product_id && $expected && $product_id === $expected ) { return true; }

		if ( function_exists( 'wc_get_product' ) && $product_id && $expected ) {
			$product = wc_get_product( $product_id );
			if ( $product && is_callable( array( $product, 'get_parent_id' ) ) && absint( $product->get_parent_id() ) === $expected ) { return true; }
		}
		return false;
	}

	/** Resolve club + season, including legacy orders whose item metadata was incomplete. */
	private static function resolve_order_context( $order, $item ) {
		$club_id = absint( $item->get_meta( 'ufsc_club_id', true ) );
		if ( ! $club_id ) { $club_id = absint( $item->get_meta( '_ufsc_club_id', true ) ); }
		if ( ! $club_id && is_callable( array( $order, 'get_user_id' ) ) && function_exists( 'ufsc_get_user_club_id' ) ) {
			$user_id = absint( $order->get_user_id() );
			if ( $user_id ) { $club_id = absint( ufsc_get_user_club_id( $user_id ) ); }
		}

		$season = sanitize_text_field( (string) $item->get_meta( 'ufsc_target_season', true ) );
		if ( '' === $season ) { $season = sanitize_text_field( (string) $item->get_meta( '_ufsc_target_season', true ) ); }
		if ( '' === $season ) { $season = sanitize_text_field( (string) $item->get_meta( 'ufsc_season', true ) ); }
		if ( '' === $season ) { $season = sanitize_text_field( (string) $item->get_meta( '_ufsc_season', true ) ); }
		if ( ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
			$created = is_callable( array( $order, 'get_date_created' ) ) ? $order->get_date_created() : null;
			$timestamp = $created && is_callable( array( $created, 'getTimestamp' ) ) ? (int) $created->getTimestamp() : 0;
			$season = $timestamp && function_exists( 'ufsc_get_season_for_date' ) ? (string) ufsc_get_season_for_date( $timestamp ) : '';
		}
		if ( ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
			$season = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
		}

		return array( 'club_id' => $club_id, 'season' => sanitize_text_field( $season ) );
	}

	/** Map Woo state to the annual request state without auto-validating a club. */
	private static function state_for_order( $order ) {
		$status = sanitize_key( (string) $order->get_status() );
		if ( in_array( $status, array( 'processing', 'completed' ), true ) || ( is_callable( array( $order, 'is_paid' ) ) && $order->is_paid() ) ) {
			return array( 'status' => 'pending_validation', 'payment_status' => 'paid' );
		}
		if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
			return array( 'status' => 'pending_payment', 'payment_status' => 'pending' );
		}
		if ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
			return array( 'status' => 'a_renouveler', 'payment_status' => $status );
		}
		return array( 'status' => 'pending_payment', 'payment_status' => 'pending' );
	}

	private static function upsert_request_from_order( $order, $item, $context ) {
		global $wpdb;
		if ( ! class_exists( 'UFSC_Season_Archive_Manager' ) ) { return false; }

		$club_id = absint( $context['club_id'] );
		$season  = sanitize_text_field( (string) $context['season'] );
		if ( $club_id < 1 || ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) { return false; }

		$existing = UFSC_Season_Archive_Manager::get_affiliation( $club_id, $season );
		$existing_status = $existing ? sanitize_key( (string) ( $existing->status ?? '' ) ) : '';
		// A human-validated affiliation is authoritative; Woo cannot demote it.
		if ( in_array( $existing_status, array( 'active', 'validated' ), true ) ) { return true; }

		$state   = self::state_for_order( $order );
		$table   = UFSC_Season_Archive_Manager::get_affiliations_table();
		$columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
		if ( ! $columns ) { return false; }

		$created = is_callable( array( $order, 'get_date_created' ) ) ? $order->get_date_created() : null;
		$paid    = is_callable( array( $order, 'get_date_paid' ) ) ? $order->get_date_paid() : null;
		$requested_at = $created && is_callable( array( $created, 'date' ) ) ? $created->date( 'Y-m-d H:i:s' ) : current_time( 'mysql' );
		$paid_at      = $paid && is_callable( array( $paid, 'date' ) ? array( $paid, 'date' ) : array() ) ? $paid->date( 'Y-m-d H:i:s' ) : ( 'paid' === $state['payment_status'] ? current_time( 'mysql' ) : null );
		$product_id   = is_callable( array( $item, 'get_product_id' ) ) ? absint( $item->get_product_id() ) : 0;
		$request_type = sanitize_key( (string) $item->get_meta( 'ufsc_request_type', true ) );
		if ( '' === $request_type ) { $request_type = sanitize_key( (string) $item->get_meta( '_ufsc_request_type', true ) ); }
		if ( '' === $request_type ) { $request_type = 'renewal'; }

		$data = array(
			'club_id'                 => $club_id,
			'season'                  => $season,
			'status'                  => $state['status'],
			'payment_status'          => $state['payment_status'],
			'wc_order_id'             => absint( $order->get_id() ),
			'product_id'              => $product_id,
			'previous_affiliation_id' => absint( $item->get_meta( 'ufsc_previous_affiliation_id', true ) ) ?: null,
			'request_type'            => $request_type,
			'requested_at'            => $requested_at,
			'updated_at'              => current_time( 'mysql' ),
		);
		if ( 'paid' === $state['payment_status'] ) { $data['paid_at'] = $paid_at; }

		$data = array_intersect_key( $data, array_flip( $columns ) );
		if ( $existing ) {
			// Never erase existing identifiers or validation history.
			$result = $wpdb->update( $table, $data, array( 'id' => absint( $existing->id ) ) );
		} else {
			if ( in_array( 'created_at', $columns, true ) ) { $data['created_at'] = current_time( 'mysql' ); }
			$result = $wpdb->insert( $table, $data );
		}
		if ( false === $result ) {
			self::log( 'affiliation_order_state_write_failed', array( 'order_id' => absint( $order->get_id() ), 'club_id' => $club_id, 'season' => $season, 'error' => (string) $wpdb->last_error ) );
			return false;
		}

		if ( is_callable( array( $order, 'update_meta_data' ) ) ) {
			$order->update_meta_data( '_ufsc_affiliation_state_synced', 'yes' );
			$order->update_meta_data( '_ufsc_affiliation_club_id', $club_id );
			$order->update_meta_data( '_ufsc_affiliation_season', $season );
			$order->save();
		}
		do_action( 'ufsc_licence_updated', $club_id );
		return true;
	}

	/**
	 * Server-side one-pack-per-club-per-season guard. Also catches direct visits
	 * to the Woo product page that bypass the UFSC renewal button.
	 */
	public static function validate_affiliation_purchase( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		unset( $quantity, $variations );
		if ( ! $passed ) { return false; }
		$expected = function_exists( 'ufsc_get_affiliation_product_id' ) ? absint( ufsc_get_affiliation_product_id() ) : 0;
		$candidate = absint( $variation_id ) ?: absint( $product_id );
		if ( ! $expected || ( $candidate !== $expected && absint( $product_id ) !== $expected ) ) { return $passed; }

		if ( ! is_user_logged_in() || ! function_exists( 'ufsc_get_user_club_id' ) ) {
			self::notice( __( 'Connectez-vous à votre espace club pour souscrire l’affiliation UFSC.', 'ufsc-clubs' ) );
			return false;
		}
		$club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
		$season  = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
		if ( $club_id < 1 || ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
			self::notice( __( 'Aucun club ou saison UFSC valide n’est associé à ce compte.', 'ufsc-clubs' ) );
			return false;
		}

		$affiliation = class_exists( 'UFSC_Season_Archive_Manager' ) ? UFSC_Season_Archive_Manager::get_affiliation( $club_id, $season ) : null;
		$status = $affiliation ? sanitize_key( (string) ( $affiliation->status ?? '' ) ) : '';
		if ( in_array( $status, array( 'active', 'validated', 'pending_payment', 'pending_validation', 'pending', 'en_attente' ), true ) ) {
			self::notice( __( 'Une affiliation ou une demande d’affiliation existe déjà pour cette saison.', 'ufsc-clubs' ) );
			return false;
		}

		if ( self::find_existing_order( $club_id, $season ) ) {
			self::notice( __( 'Le pack d’affiliation a déjà été commandé pour cette saison. Consultez le suivi de votre affiliation.', 'ufsc-clubs' ) );
			return false;
		}
		return $passed;
	}

	/** Find an active affiliation order, including legacy orders missing item metadata. */
	private static function find_existing_order( $club_id, $season ) {
		if ( ! function_exists( 'wc_get_orders' ) ) { return false; }
		$orders = wc_get_orders( array( 'status' => array( 'pending', 'on-hold', 'processing', 'completed' ), 'limit' => 500, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ) );
		foreach ( (array) $orders as $order ) {
			if ( ! $order || ! is_a( $order, 'WC_Order' ) ) { continue; }
			foreach ( $order->get_items() as $item ) {
				if ( ! self::is_affiliation_item( $item ) ) { continue; }
				$context = self::resolve_order_context( $order, $item );
				if ( absint( $context['club_id'] ) === absint( $club_id ) && (string) $context['season'] === (string) $season ) { return $order; }
			}
		}
		return false;
	}

	/** Rebuild annual rows for orders created while older deployments were live. */
	public static function maybe_reconcile_existing_orders() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || ! function_exists( 'wc_get_orders' ) ) { return; }
		if ( (string) get_option( self::BACKFILL_OPTION, '' ) === self::BACKFILL_VERSION ) { return; }

		$orders = wc_get_orders( array( 'status' => array( 'pending', 'on-hold', 'processing', 'completed' ), 'limit' => 1000, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ) );
		foreach ( (array) $orders as $order ) { self::sync_order( $order ); }
		update_option( self::BACKFILL_OPTION, self::BACKFILL_VERSION, false );
	}

	/** Hide internal WooCommerce vocabulary from club-facing wording only. */
	public static function frontend_wording( $translation, $text, $domain ) {
		if ( is_admin() || 'ufsc-clubs' !== $domain ) { return $translation; }
		if ( false !== stripos( (string) $text, 'produit woocommerce' ) || false !== stripos( (string) $translation, 'produit woocommerce' ) ) {
			return str_ireplace( 'Produit WooCommerce :', 'Pack d’affiliation :', (string) $translation );
		}
		return $translation;
	}

	private static function notice( $message ) {
		if ( function_exists( 'wc_add_notice' ) ) { wc_add_notice( sanitize_text_field( (string) $message ), 'error' ); }
	}

	private static function log( $action, $context ) {
		if ( function_exists( 'ufsc_admin_debug_log' ) ) { ufsc_admin_debug_log( $action, (array) $context ); }
	}
}

UFSC_Affiliation_Order_State_Bridge::init();
