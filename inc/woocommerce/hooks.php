<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce hooks for UFSC Gestion
 * Handles order processing and integrations
 */

/**
 * Helper to log WooCommerce events with audit trail fallback.
 *
 * @param string $action  Action performed.
 * @param array  $context Context information.
 * @param string $level   Log level (info|error).
 */
function ufsc_wc_log( $action, $context = array(), $level = 'info' ) {
	if ( function_exists( 'ufsc_admin_debug_log' ) ) {
		ufsc_admin_debug_log( $action, $context );
	}
}

/**
 * Initialize WooCommerce hooks
 */
function ufsc_init_woocommerce_hooks() {
	if ( ! ufsc_is_woocommerce_active() ) {
		return;
	}

	add_action(
		'woocommerce_checkout_create_order_line_item',
		function ( $item, $cart_key, $values ) {
			foreach ( $values as $k => $v ) {
				if ( strpos( $k, 'ufsc_' ) === 0 ) {
					$item->add_meta_data( $k, $v, true );
				}
			}
		},
		10,
		3
	);

	// Hook into order processing with idempotent handler
	add_action( 'woocommerce_order_status_processing', 'ufsc_handle_order_processing' );
	add_action( 'woocommerce_order_status_completed', 'ufsc_handle_order_completed' );

	// UFSC PATCH: Retry payment handling on failed/cancelled orders.
	add_action( 'woocommerce_order_status_failed', 'ufsc_handle_order_failed_or_cancelled' );
	add_action( 'woocommerce_order_status_cancelled', 'ufsc_handle_order_failed_or_cancelled' );
}

/**
 * Handle order when it reaches processing status
 *
 * @param int $order_id Order ID
 */
function ufsc_handle_order_processing( $order_id ) {
	ufsc_wc_process_order_once( $order_id );
}

/**
 * Handle order when it reaches completed status
 *
 * @param int $order_id Order ID
 */
function ufsc_handle_order_completed( $order_id ) {
	ufsc_wc_process_order_once( $order_id );
}

/**
 * UFSC PATCH: Process order once per season (idempotent).
 *
 * @param int $order_id Order ID.
 * @return void
 */
function ufsc_wc_process_order_once( $order_id ) {
	if ( ! ufsc_is_woocommerce_active() ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$settings = ufsc_get_woocommerce_settings();
	$season   = $settings['season'];

	if ( ufsc_wc_order_already_processed( $order, $season ) ) {
		return;
	}

	ufsc_process_order_items( $order_id );
	ufsc_wc_validate_paid_items( $order_id );
	ufsc_wc_increment_included_quota( $order_id );

	ufsc_wc_mark_order_processed( $order, $season );
}

/**
 * UFSC PATCH: Get order meta key for processed flag.
 *
 * @param string $season Season label.
 * @return string
 */
function ufsc_wc_get_processed_meta_key( $season ) {
	$season_key = preg_replace( '/[^a-z0-9_]/', '_', strtolower( (string) $season ) );
	// Use leading underscore for "private" meta keys in Woo.
	return '_ufsc_processed_' . $season_key;
}

/**
 * UFSC PATCH: Check if order already processed for season (legacy compatible).
 *
 * @param WC_Order $order  Order object.
 * @param string   $season Season label.
 * @return bool
 */
function ufsc_wc_order_already_processed( $order, $season ) {
	$season_key = preg_replace( '/[^a-z0-9_]/', '_', strtolower( (string) $season ) );

	$meta_key   = ufsc_wc_get_processed_meta_key( $season );
	$legacy_key = 'ufsc_processed_' . $season_key;

	// New key (preferred)
	if ( $order->get_meta( $meta_key, true ) ) {
		return true;
	}

	// Legacy season key (older builds)
	if ( $order->get_meta( $legacy_key, true ) ) {
		return true;
	}

	// Very old global marker
	return (bool) $order->get_meta( '_ufsc_processed', true );
}

/**
 * UFSC PATCH: Mark order as processed for season (writes legacy keys too).
 *
 * @param WC_Order $order  Order object.
 * @param string   $season Season label.
 * @return void
 */
function ufsc_wc_mark_order_processed( $order, $season ) {
	$season_key = preg_replace( '/[^a-z0-9_]/', '_', strtolower( (string) $season ) );

	$meta_key   = ufsc_wc_get_processed_meta_key( $season );
	$legacy_key = 'ufsc_processed_' . $season_key;

	// Store a timestamp for audit/debug
	$order->update_meta_data( $meta_key, current_time( 'mysql' ) );

	// Keep legacy markers to avoid regressions with older reads
	$order->update_meta_data( $legacy_key, 1 );
	$order->update_meta_data( '_ufsc_processed', 1 );

	$order->save();
}

/**
 * Validate paid items for an order once payment is confirmed.
 *
 * @param int $order_id Order ID.
 */
function ufsc_wc_validate_paid_items( $order_id ) {
	if ( ! ufsc_is_woocommerce_active() ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$settings               = ufsc_get_woocommerce_settings();
	$season                 = $settings['season'];
	$affiliation_product_id = $settings['product_affiliation_id'];
	$license_product_id     = $settings['product_license_id'];

	if ( ufsc_wc_order_already_processed( $order, $season ) ) {
		return;
	}

	foreach ( $order->get_items() as $item ) {
		$product_id = $item->get_product_id();

		if ( $product_id == $license_product_id ) {
			$license_ids = ufsc_get_item_licence_ids( $item );
			foreach ( $license_ids as $license_id ) {
				if ( class_exists( 'UFSC_SQL' ) ) {
					UFSC_SQL::mark_licence_as_paid_and_validated( $license_id, $season );
				}
			}
		}

		if ( $product_id == $affiliation_product_id ) {
			$club_id = $item->get_meta( '_ufsc_club_id' );
			if ( empty( $club_id ) ) {
				$club_id = $item->get_meta( 'ufsc_club_id' );
			}
			if ( ! $club_id ) {
				$club_id = ufsc_get_user_club_id( $order->get_user_id() );
			}
			if ( $club_id && class_exists( 'UFSC_SQL' ) ) {
				UFSC_SQL::mark_club_affiliation_active( $club_id, $season );
			}
		}
	}
}

/**
 * Increment included quota usage for licences marked as consuming it.
 *
 * @param int $order_id Order identifier.
 */
function ufsc_wc_increment_included_quota( $order_id ) {
	if ( ! ufsc_is_woocommerce_active() ) {
		return;
	}

	if ( function_exists( 'ufsc_quotas_enabled' ) && ! ufsc_quotas_enabled() ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$settings = ufsc_get_woocommerce_settings();
	$season   = $settings['season'];

	if ( ufsc_wc_order_already_processed( $order, $season ) ) {
		return;
	}

	foreach ( $order->get_items() as $item ) {
		if ( ! $item->get_meta( 'ufsc_consumes_included' ) ) {
			continue;
		}

		$club_id = $item->get_meta( '_ufsc_club_id' );
		if ( ! $club_id ) {
			$club_id = ufsc_get_user_club_id( $order->get_user_id() );
		}

		if ( ! $club_id ) {
			continue;
		}

		$qty = max( 1, $item->get_quantity() );

		if ( function_exists( 'ufsc_get_clubs_table' ) ) {
			global $wpdb;
			$clubs_table = ufsc_get_clubs_table();
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$clubs_table} SET included_quota_used = COALESCE(included_quota_used,0) + %d WHERE id = %d",
					$qty,
					$club_id
				)
			);
		}
	}
}

/**
 * UFSC PATCH: Handle failed/cancelled orders by unlocking licence payment.
 *
 * @param int $order_id Order ID.
 */
function ufsc_handle_order_failed_or_cancelled( $order_id ) {
	if ( ! ufsc_is_woocommerce_active() ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$licence_ids  = ufsc_get_order_licence_ids( $order );
	$order_status = $order->get_status();
	if ( empty( $licence_ids ) ) {
		return;
	}

	foreach ( $licence_ids as $licence_id ) {
		if ( function_exists( 'ufsc_is_validated_licence' ) && ufsc_is_validated_licence( $licence_id ) ) {
			continue;
		}
		ufsc_update_licence_payment_status( $licence_id, 'non_payee', $order_status );
	}
}

/**
 * UFSC PATCH: Parse licence IDs from stored meta.
 *
 * @param mixed $value Meta value.
 * @return int[]
 */
function ufsc_parse_licence_ids_from_meta( $value ) {
	if ( empty( $value ) ) {
		return array();
	}

	if ( is_numeric( $value ) ) {
		return array( absint( $value ) );
	}

	if ( is_string( $value ) ) {
		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			$value = $decoded;
		} else {
			$parts = array_map( 'trim', explode( ',', $value ) );
			return array_values( array_unique( array_filter( array_map( 'absint', $parts ) ) ) );
		}
	}

	if ( is_array( $value ) ) {
		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	return array();
}

/**
 * UFSC PATCH: Extract licence IDs from a line item.
 *
 * @param WC_Order_Item_Product $item Line item.
 * @return int[]
 */
function ufsc_get_item_licence_ids( $item ) {
	$ids = $item->get_meta( '_ufsc_licence_ids' );
	if ( empty( $ids ) ) {
		$ids = $item->get_meta( 'ufsc_licence_ids' );
	}

	$parsed = ufsc_parse_licence_ids_from_meta( $ids );
	if ( empty( $parsed ) ) {
		$single_id = $item->get_meta( '_ufsc_licence_id' );
		$parsed    = ufsc_parse_licence_ids_from_meta( $single_id );
	}

	return $parsed;
}

/**
 * UFSC PATCH: Extract licence IDs from an order.
 *
 * @param WC_Order $order Order object.
 * @return int[]
 */
function ufsc_get_order_licence_ids( $order ) {
	$licence_ids = array();

	foreach ( $order->get_items() as $item ) {
		$licence_ids = array_merge( $licence_ids, ufsc_get_item_licence_ids( $item ) );
	}

	return array_values( array_unique( array_filter( $licence_ids ) ) );
}

/**
 * UFSC PATCH: Update licence status/payment status with column existence checks.
 *
 * @param int    $licence_id     Licence ID.
 * @param string $status         Licence status.
 * @param string $payment_status Payment status.
 * @return void
 */
function ufsc_update_licence_payment_status( $licence_id, $status, $payment_status = '' ) {
	if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
		return;
	}

	global $wpdb;
	$table   = ufsc_get_licences_table();
	$columns = function_exists( 'ufsc_table_columns' )
		? ufsc_table_columns( $table )
		: $wpdb->get_col( "DESCRIBE `{$table}`" );

	$data  = array();
	$types = array();

	if ( in_array( 'statut', $columns, true ) ) {
		$data['statut'] = $status;
		$types[]        = '%s';
	}

	if ( $payment_status && in_array( 'payment_status', $columns, true ) ) {
		$data['payment_status'] = $payment_status;
		$types[]                = '%s';
	}

	if ( empty( $data ) ) {
		return;
	}

	$wpdb->update( $table, $data, array( 'id' => $licence_id ), $types, array( '%d' ) );

	$club_id = $wpdb->get_var( $wpdb->prepare( "SELECT club_id FROM {$table} WHERE id = %d", $licence_id ) );
	if ( $club_id ) {
		do_action( 'ufsc_licence_updated', (int) $club_id );
	}
}

/**
 * UFSC PATCH: Get latest order status for a licence ID.
 *
 * @param int $licence_id Licence ID.
 * @return string
 */
function ufsc_get_latest_licence_order_status( $licence_id ) {
	if ( ! ufsc_is_woocommerce_active() ) {
		return '';
	}

	global $wpdb;
	$licence_id = absint( $licence_id );
	if ( ! $licence_id ) {
		return '';
	}

	$order_items    = $wpdb->prefix . 'woocommerce_order_items';
	$order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

	$like_value = '%\"' . $wpdb->esc_like( (string) $licence_id ) . '\"%';

	$order_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT oi.order_id
			 FROM {$order_items} oi
			 INNER JOIN {$order_itemmeta} oim ON oim.order_item_id = oi.order_item_id
			 WHERE oi.order_item_type = 'line_item'
			 AND (
				 (oim.meta_key = '_ufsc_licence_id' AND oim.meta_value = %d)
				 OR (oim.meta_key IN ('_ufsc_licence_ids','ufsc_licence_ids') AND oim.meta_value LIKE %s)
			 )
			 ORDER BY oi.order_id DESC
			 LIMIT 1",
			$licence_id,
			$like_value
		)
	);

	if ( ! $order_id ) {
		return '';
	}

	$order = wc_get_order( $order_id );
	return $order ? $order->get_status() : '';
}

/**
 * Process order items for UFSC products
 *
 * @param int $order_id Order ID
 */
function ufsc_process_order_items( $order_id ) {
	if ( ! ufsc_is_woocommerce_active() ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$wc_settings            = ufsc_get_woocommerce_settings();
	$affiliation_product_id = $wc_settings['product_affiliation_id'];
	$license_product_id     = $wc_settings['product_license_id'];

	foreach ( $order->get_items() as $item_id => $item ) {
		$product_id = $item->get_product_id();
		$quantity   = $item->get_quantity();

		if ( $product_id == $affiliation_product_id ) {
			// Handle affiliation pack
			ufsc_handle_affiliation_pack_payment( $order, $item, $quantity );
		} elseif ( $product_id == $license_product_id ) {
			// Handle additional license
			ufsc_handle_additional_license_payment( $order, $item, $quantity );
		}
	}
}

/**
 * Handle affiliation pack payment
 *
 * @param WC_Order              $order    Order object
 * @param WC_Order_Item_Product $item     Item object
 * @param int                   $quantity Quantity purchased
 */
function ufsc_handle_affiliation_pack_payment( $order, $item, $quantity ) {
	$user_id = $order->get_user_id();
	$season  = ufsc_get_woocommerce_settings()['season'];

	// Get club ID for this user
	$club_id = ufsc_get_user_club_id( $user_id );

	if ( $club_id ) {
		// Mark affiliation as paid for the season
		ufsc_mark_affiliation_paid( $club_id, $season );

		// Credit included licenses quota
		$included_licenses = ufsc_get_woocommerce_settings()['included_licenses'];
		$total_licenses    = $included_licenses * $quantity;
		ufsc_quota_add_included( $club_id, $total_licenses, $season );

		// Log the action
		ufsc_wc_log(
			'Affiliation pack processed',
			array(
				'order_id'          => $order->get_id(),
				'club_id'           => $club_id,
				'season'            => $season,
				'licenses_credited' => $total_licenses,
			)
		);
	}
}

/**
 * Handle additional license payment
 *
 * @param WC_Order              $order    Order object
 * @param WC_Order_Item_Product $item     Item object
 * @param int                   $quantity Quantity purchased
 */
function ufsc_handle_additional_license_payment( $order, $item, $quantity ) {
	$user_id = $order->get_user_id();
	$season  = ufsc_get_woocommerce_settings()['season'];
	$club_id = ufsc_get_user_club_id( $user_id );

	// Check if specific license IDs are attached to this line item
	$license_ids = ufsc_get_item_licence_ids( $item );

	if ( ! empty( $license_ids ) ) {
		// Mark specific licenses as paid
		foreach ( $license_ids as $license_id ) {
			ufsc_mark_licence_paid( $license_id, $season );
		}
		ufsc_wc_log(
			'Specific licenses marked as paid',
			array(
				'order_id'    => $order->get_id(),
				'club_id'     => $club_id,
				'license_ids' => implode( ', ', $license_ids ),
				'season'      => $season,
			)
		);
	} else {
		// Credit prepaid licenses for future use
		if ( $club_id ) {
			ufsc_quota_add_paid( $club_id, $quantity, $season );

			ufsc_wc_log(
				'Prepaid licenses credited',
				array(
					'order_id' => $order->get_id(),
					'club_id'  => $club_id,
					'quantity' => $quantity,
					'season'   => $season,
				)
			);
		}
	}
}

// Database helper functions

/**
 * Get club ID for a user.
 *
 * Tries to resolve the club via the optional UFSC_User_Club_Mapping class
 * if available. If not, the function falls back to a direct lookup on the
 * configured clubs table using the `responsable_id` column.
 *
 * @param int $user_id User ID
 * @return int|false Club ID or false if not found
 */
if ( ! function_exists( 'ufsc_get_user_club_id' ) ) {
	function ufsc_get_user_club_id( $user_id ) {
		// Delegate to the mapping class when present
		if ( class_exists( 'UFSC_User_Club_Mapping' ) ) {
			return UFSC_User_Club_Mapping::get_user_club_id( $user_id );
		}

		global $wpdb;
		$clubs_table = ufsc_get_clubs_table();

		$club_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$clubs_table} WHERE responsable_id = %d",
				$user_id
			)
		);

		return $club_id ? (int) $club_id : false;
	}
}

/**
 * Mark affiliation as paid for a season
 *
 * @param int    $club_id Club ID
 * @param string $season  Season identifier
 * @return bool
 */
if ( ! function_exists( 'ufsc_mark_affiliation_paid' ) ) {
	function ufsc_mark_affiliation_paid( $club_id, $season ) {
		global $wpdb;

		if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
			return false;
		}

		$clubs_table = ufsc_get_clubs_table();

		// Update affiliation date to mark payment
		$updated = $wpdb->update(
			$clubs_table,
			array( 'date_affiliation' => current_time( 'mysql' ) ),
			array( 'id' => $club_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}
}

/**
 * Mark a specific license as paid
 *
 * @param int    $license_id License ID
 * @param string $season     Season identifier
 * @return bool
 */
if ( ! function_exists( 'ufsc_mark_licence_paid' ) ) {
	function ufsc_mark_licence_paid( $license_id, $season ) {
		global $wpdb;

		if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
			return false;
		}

		$licences_table = ufsc_get_licences_table();
		$data           = array(
			'statut'      => 'en_attente',
			'is_included' => 0,
			'paid_season' => $season,
			'paid_date'   => current_time( 'mysql' ),
		);
		$types          = array( '%s', '%d', '%s', '%s' );

		// Optional season_end_year persistence when column exists.
		if ( function_exists( 'ufsc_table_has_column' ) && function_exists( 'ufsc_get_season_end_year_from_label' ) ) {
			if ( ufsc_table_has_column( $licences_table, 'season_end_year' ) ) {
				$data['season_end_year'] = ufsc_get_season_end_year_from_label( $season );
				$types[]                 = '%d';
			}
		}

		$updated = $wpdb->update(
			$licences_table,
			$data,
			array( 'id' => $license_id ),
			$types,
			array( '%d' )
		);

		return false !== $updated;
	}
}

/**
 * Add included licenses to club quota
 *
 * @param int    $club_id   Club ID
 * @param int    $quantity  Number of licenses to add
 * @param string $season    Season identifier
 * @return bool
 */
function ufsc_quota_add_included( $club_id, $quantity, $season ) {
	global $wpdb;

	if ( function_exists( 'ufsc_quotas_enabled' ) && ! ufsc_quotas_enabled() ) {
		return false;
	}

	if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
		return false;
	}

	$clubs_table = ufsc_get_clubs_table();
	$updated     = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$clubs_table} SET quota_licences = COALESCE(quota_licences,0) + %d WHERE id = %d",
			$quantity,
			$club_id
		)
	);

	return false !== $updated;
}

/**
 * Add paid licenses to club quota
 *
 * @param int    $club_id   Club ID
 * @param int    $quantity  Number of licenses to add
 * @param string $season    Season identifier
 * @return bool
 */
function ufsc_quota_add_paid( $club_id, $quantity, $season ) {
	global $wpdb;

	if ( function_exists( 'ufsc_quotas_enabled' ) && ! ufsc_quotas_enabled() ) {
		return false;
	}

	if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
		return false;
	}

	$clubs_table = ufsc_get_clubs_table();
	$updated     = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$clubs_table} SET quota_licences = COALESCE(quota_licences,0) + %d WHERE id = %d",
			$quantity,
			$club_id
		)
	);

	return false !== $updated;
}
