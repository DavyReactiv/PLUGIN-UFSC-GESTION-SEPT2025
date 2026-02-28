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
			foreach ( (array) $values as $k => $v ) {
				if ( strpos( (string) ( $k ?? '' ), 'ufsc_' ) === 0 ) {
					$item->add_meta_data( $k, $v, true );
				}
			}
		},
		10,
		3
	);

	// Hook into order processing with idempotent handler (no Monetico dependency).
	add_action( 'woocommerce_payment_complete', 'ufsc_handle_woocommerce_payment_confirmed' );
	add_action( 'woocommerce_order_status_processing', 'ufsc_handle_woocommerce_payment_confirmed' );
	add_action( 'woocommerce_order_status_completed', 'ufsc_handle_woocommerce_payment_confirmed' );

	add_action( 'woocommerce_order_status_processing', 'ufsc_handle_order_processing' );
	add_action( 'woocommerce_order_status_completed', 'ufsc_handle_order_completed' );

	// UFSC PATCH: Retry payment handling on failed/cancelled orders.
	add_action( 'woocommerce_order_status_failed', 'ufsc_handle_order_failed_or_cancelled' );
	add_action( 'woocommerce_order_status_cancelled', 'ufsc_handle_order_failed_or_cancelled' );

	add_filter( 'woocommerce_order_item_display_meta_value', 'ufsc_wc_format_order_item_meta_display', 10, 3 );
	add_action( 'woocommerce_after_order_itemmeta', 'ufsc_wc_render_missing_ids_hint', 10, 3 );
	add_action( 'woocommerce_admin_order_data_after_order_details', 'ufsc_wc_render_generate_missing_admin_action' );
	add_action( 'admin_post_ufsc_generate_missing_licences', 'ufsc_wc_handle_generate_missing_licences' );
	add_action( 'admin_notices', 'ufsc_wc_render_generate_missing_notice' );
}

/**
 * Standard WooCommerce payment handler (idempotent).
 * - Uses WooCommerce order states only (no Monetico patch).
 * - If paid and status is 'brouillon' => promotes to 'en_attente'.
 * - Does not touch final statuses (valide/refuse/desactive).
 *
 * @param int $order_id Order ID.
 * @return void
 */
function ufsc_handle_woocommerce_payment_confirmed( $order_id ) {
	if ( ! ufsc_is_woocommerce_active() || ! function_exists( 'ufsc_get_licences_table' ) ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$already_processed = 'yes' === (string) $order->get_meta( '_ufsc_licences_processed', true );
	if ( $already_processed && ! ufsc_wc_order_has_missing_licence_ids( $order ) ) {
		return;
	}

	// Guard: only run on real paid transitions (except payment_complete which is paid by definition).
	$current_hook = current_filter();
	$order_status = (string) $order->get_status();

	if ( 'woocommerce_payment_complete' !== $current_hook && ! in_array( $order_status, array( 'processing', 'completed' ), true ) ) {
		return;
	}

	ufsc_wc_process_renewal_items( $order );
	ufsc_wc_maybe_generate_order_licences( $order );

	// Collect licence ids from order meta.
	$licence_ids = array();
	foreach ( array( 'ufsc_licence_id', 'license_id', 'licence_id', '_ufsc_licence_id' ) as $meta_key ) {
		$licence_ids = array_merge(
			$licence_ids,
			ufsc_parse_licence_ids_from_meta( $order->get_meta( $meta_key, true ) )
		);
	}

	// Collect licence ids from items meta.
	foreach ( $order->get_items() as $item ) {
		foreach ( array( '_ufsc_licence_id', 'ufsc_licence_id', 'license_id', 'licence_id', '_ufsc_licence_ids', 'ufsc_licence_ids' ) as $meta_key ) {
			$licence_ids = array_merge(
				$licence_ids,
				ufsc_parse_licence_ids_from_meta( $item->get_meta( $meta_key, true ) )
			);
		}
	}

	/**
	 * Allow extensions to provide/override UFSC licence IDs extracted from an order.
	 *
	 * @param array|int|string $licence_ids Collected licence IDs.
	 * @param WC_Order         $order       Current WooCommerce order.
	 */
	$licence_ids = apply_filters( 'ufsc_wc_order_license_ids', $licence_ids, $order );

	// Normalize possible scalar returns.
	$licence_ids = is_array( $licence_ids ) ? $licence_ids : array( $licence_ids );
	$licence_ids = array_values( array_unique( array_filter( array_map( 'absint', $licence_ids ) ) ) );

	if ( empty( $licence_ids ) ) {
		return;
	}

	global $wpdb;
	$table   = ufsc_get_licences_table();
	$columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $table ) : $wpdb->get_col( "DESCRIBE `{$table}`" );

	$updated_count = 0;

	foreach ( $licence_ids as $licence_id ) {
		$current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) );
		if ( ! $current ) {
			continue;
		}

		// Normalize current status.
		$current_status = function_exists( 'ufsc_normalize_license_status' )
			? ufsc_normalize_license_status( $current->statut ?? '' )
			: (string) ( $current->statut ?? '' );

		// Do not touch final statuses.
		if ( in_array( $current_status, array( 'valide', 'refuse', 'desactive' ), true ) ) {
			continue;
		}

		// Idempotence guard: if already paid and not draft, nothing to do.
		$is_paid_already = ufsc_wc_is_licence_paid_row( $current );
		if ( $is_paid_already && 'brouillon' !== $current_status ) {
			continue;
		}

		$data    = array();
		$formats = array();

		// Mark payment as paid where supported, only if change is needed.
		if ( in_array( 'payment_status', $columns, true ) && (string) ( $current->payment_status ?? '' ) !== 'paid' ) {
			$data['payment_status'] = 'paid';
			$formats[]              = '%s';
		}

		foreach ( array( 'paid', 'payee', 'is_paid' ) as $paid_col ) {
			if ( in_array( $paid_col, $columns, true ) && (int) ( $current->{$paid_col} ?? 0 ) !== 1 ) {
				$data[ $paid_col ] = 1;
				$formats[]         = '%d';
			}
		}

		// Promote editable/unpaid statuses to "en_attente" after payment.
		if ( in_array( $current_status, array( 'brouillon', 'non_payee', 'a_regler' ), true ) ) {
			if ( in_array( 'statut', $columns, true ) && (string) ( $current->statut ?? '' ) !== 'en_attente' ) {
				$data['statut'] = 'en_attente';
				$formats[]      = '%s';
			}
			if ( in_array( 'status', $columns, true ) && (string) ( $current->status ?? '' ) !== 'en_attente' ) {
				$data['status'] = 'en_attente';
				$formats[]      = '%s';
			}
		}

		if ( empty( $data ) ) {
			continue;
		}

		$updated = $wpdb->update( $table, $data, array( 'id' => $licence_id ), $formats, array( '%d' ) );
		if ( false !== $updated && $updated > 0 ) {
			$updated_count++;
		}
	}

	// Single debug log per order, only in debug.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		ufsc_wc_log(
			'ufsc_payment_sync',
			array(
				'order_id' => (int) $order_id,
				'updated'  => (int) $updated_count,
				'count'    => (int) count( $licence_ids ),
				'hook'     => (string) $current_hook,
				'status'   => (string) $order_status,
			)
		);
	}

	$order->update_meta_data( '_ufsc_licences_processed', 'yes' );
	$order->save();
}

/**
 * Process renewal items (licence + affiliation) once order is paid.
 * Idempotence is enforced via UFSC renewal markers.
 */
function ufsc_wc_process_renewal_items( $order ) {
	global $wpdb;

	if ( ! $order || ! is_a( $order, 'WC_Order' ) || ! function_exists( 'ufsc_get_licences_table' ) ) {
		return;
	}

	$table   = ufsc_get_licences_table();
	$columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();

	foreach ( $order->get_items() as $item ) {

		$action = (string) $item->get_meta( 'ufsc_action', true );
		if ( '' === $action ) {
			$action = (string) $item->get_meta( '_ufsc_action', true );
		}

		$club_id = absint( $item->get_meta( 'ufsc_club_id', true ) );
		if ( ! $club_id ) {
			$club_id = absint( $item->get_meta( '_ufsc_club_id', true ) );
		}

		$target_season = (string) $item->get_meta( 'ufsc_target_season', true );
		if ( '' === $target_season ) {
			$target_season = (string) $item->get_meta( '_ufsc_target_season', true );
		}
		if ( '' === $target_season && function_exists( 'ufsc_get_next_season' ) ) {
			$target_season = ufsc_get_next_season();
		}

		/**
		 * RENEW LICENCE
		 */
		if ( 'renew_licence' === $action ) {

			$source_id = absint( $item->get_meta( 'ufsc_renew_from_licence_id', true ) );
			if ( ! $source_id ) {
				$source_id = absint( $item->get_meta( '_ufsc_renew_from_licence_id', true ) );
			}

			if ( $source_id <= 0 || $club_id <= 0 || '' === $target_season ) {
				continue;
			}

			// Idempotence (skip if already renewed)
			if ( function_exists( 'ufsc_get_renewed_licence_marker' ) ) {
				$existing_new_id = absint( ufsc_get_renewed_licence_marker( $source_id, $target_season ) );
				if ( $existing_new_id > 0 ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						ufsc_wc_log(
							'ufsc_renew_licence_idempotent_skip',
							array(
								'order_id'  => (int) $order->get_id(),
								'source_id' => (int) $source_id,
								'season'    => (string) $target_season,
								'existing'  => (int) $existing_new_id,
							)
						);
					}
					continue;
				}
			}

			$source = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $source_id ) );
			if ( ! $source || absint( $source->club_id ?? 0 ) !== $club_id ) {
				continue;
			}

			$data = array();

			// Whitelist: central helper if available
			$allowed = function_exists( 'ufsc_get_renewal_copy_fields' )
				? (array) ufsc_get_renewal_copy_fields()
				: array(
					'nom','nom_licence','prenom','email','adresse','code_postal','ville',
					'tel_fixe','tel_mobile','date_naissance','sexe','nationalite',
					'competition','surclassement','piece_identite','photo_identite'
				);

			foreach ( $allowed as $field ) {
				if ( in_array( $field, $columns, true ) && isset( $source->{$field} ) ) {
					$data[ $field ] = $source->{$field};
				}
			}

			// Forced fields
			if ( in_array( 'club_id', $columns, true ) ) { $data['club_id'] = $club_id; }
			if ( in_array( 'statut', $columns, true ) )  { $data['statut'] = 'en_attente'; }
			if ( in_array( 'status', $columns, true ) )  { $data['status'] = 'en_attente'; }

			// Dates
			if ( in_array( 'date_creation', $columns, true ) )      { $data['date_creation'] = current_time( 'mysql' ); }
			if ( in_array( 'date_modification', $columns, true ) )  { $data['date_modification'] = current_time( 'mysql' ); }
			if ( in_array( 'date_inscription', $columns, true ) )   { $data['date_inscription'] = current_time( 'mysql' ); }

			if ( empty( $data ) || ! isset( $data['club_id'] ) ) {
				continue;
			}

			$ok = $wpdb->insert( $table, $data );
			if ( false === $ok ) {
				ufsc_wc_log(
					'ufsc_renew_licence_insert_failed',
					array(
						'order_id' => (int) $order->get_id(),
						'source_id'=> (int) $source_id,
						'club_id'  => (int) $club_id,
						'season'   => (string) $target_season,
						'error'    => (string) $wpdb->last_error,
					),
					'error'
				);
				continue;
			}

			$new_id = (int) $wpdb->insert_id;

			// Meta debug on order item
			$item->update_meta_data( '_ufsc_renew_new_licence_id', $new_id );
			$item->save();

			// Persist season + marker
			if ( function_exists( 'ufsc_set_licence_season' ) ) {
				ufsc_set_licence_season( $new_id, $target_season );
			}
			if ( function_exists( 'ufsc_mark_renewed_licence_marker' ) ) {
				ufsc_mark_renewed_licence_marker( $source_id, $target_season, $new_id );
			}

			// Hooks (keep existing behavior)
			do_action( 'ufsc_licence_created', $new_id, $club_id );
			do_action( 'ufsc_licence_updated', $club_id );
		}

		/**
		 * RENEW AFFILIATION
		 */
		if ( 'renew_affiliation' === $action ) {

			if ( $club_id <= 0 || '' === $target_season ) {
				continue;
			}

			// Idempotence (skip if already renewed)
			if ( function_exists( 'ufsc_is_affiliation_renewed' ) && ufsc_is_affiliation_renewed( $club_id, $target_season ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					ufsc_wc_log(
						'ufsc_renew_affiliation_idempotent_skip',
						array(
							'order_id' => (int) $order->get_id(),
							'club_id'  => (int) $club_id,
							'season'   => (string) $target_season,
						)
					);
				}
				continue;
			}

			if ( class_exists( 'UFSC_SQL' ) ) {
				UFSC_SQL::mark_club_affiliation_active( $club_id, $target_season );
			}
			if ( function_exists( 'ufsc_set_affiliation_season' ) ) {
				ufsc_set_affiliation_season( $club_id, $target_season );
			}
			if ( function_exists( 'ufsc_mark_affiliation_renewed' ) ) {
				ufsc_mark_affiliation_renewed( $club_id, $target_season );
			}

			do_action( 'ufsc_licence_updated', $club_id );
		}
	}
}

/**
 * Create missing licences for paid WooCommerce order items (qty-based, idempotent).
 *
 * @param WC_Order $order Order object.
 * @return void
 */
function ufsc_wc_maybe_generate_order_licences( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return;
	}

	$wc_settings = ufsc_get_woocommerce_settings();
	$product_id  = isset( $wc_settings['product_license_id'] ) ? absint( $wc_settings['product_license_id'] ) : 0;
	$season      = isset( $wc_settings['season'] ) ? (string) $wc_settings['season'] : '';

	if ( ! $product_id ) {
		return;
	}

	if ( 'yes' === (string) $order->get_meta( '_ufsc_licences_generated', true ) && ! ufsc_wc_order_has_missing_licence_ids( $order ) ) {
		return;
	}

	$all_complete = true;

	foreach ( $order->get_items() as $item_id => $item ) {
		if ( absint( $item->get_product_id() ) !== $product_id ) {
			continue;
		}

		$quantity = max( 1, absint( $item->get_quantity() ) );
		$ids      = ufsc_get_item_licence_ids( $item );
		$missing  = max( 0, $quantity - count( $ids ) );

		if ( $missing <= 0 ) {
			ufsc_wc_link_licence_ids_to_order_item( $order, $item, $ids );
			continue;
		}

		$club_id = absint( $item->get_meta( '_ufsc_club_id', true ) );
		if ( ! $club_id ) {
			$club_id = absint( $item->get_meta( 'ufsc_club_id', true ) );
		}
		if ( ! $club_id ) {
			$club_id = absint( ufsc_get_user_club_id( $order->get_user_id() ) );
		}

		if ( ! $club_id ) {
			$all_complete = false;
			ufsc_wc_log(
				'ufsc_missing_club_id_for_licence_generation',
				array(
					'order_id' => $order->get_id(),
					'item_id'  => $item_id,
				)
			);
			continue;
		}

		$new_ids = ufsc_wc_generate_missing_licence_rows( $order, $item, $club_id, $missing, $season );
		if ( count( $new_ids ) !== $missing ) {
			$all_complete = false;
		}

		$merged_ids = array_values( array_unique( array_filter( array_map( 'absint', array_merge( $ids, $new_ids ) ) ) ) );

		$item->update_meta_data( '_ufsc_licence_ids', $merged_ids );
		$item->update_meta_data( 'ufsc_licence_ids', $merged_ids );
		if ( ! empty( $merged_ids ) ) {
			$item->update_meta_data( '_ufsc_licence_id', (int) $merged_ids[0] );
			$item->update_meta_data( 'ufsc_licence_id', (int) $merged_ids[0] );
		}
		if ( ! $item->get_meta( '_ufsc_club_id', true ) ) {
			$item->update_meta_data( '_ufsc_club_id', (int) $club_id );
		}

		ufsc_wc_link_licence_ids_to_order_item( $order, $item, $merged_ids );
		$item->save();

		if ( count( $merged_ids ) < $quantity ) {
			$all_complete = false;
		}
	}

	$order->update_meta_data( '_ufsc_licences_generated', $all_complete ? 'yes' : 'partial' );
	$order->save();
}

/**
 * Generate missing licence rows for one line item.
 */
function ufsc_wc_generate_missing_licence_rows( $order, $item, $club_id, $missing, $season ) {
	global $wpdb;

	$created = array();
	if ( $missing <= 0 || ! function_exists( 'ufsc_get_licences_table' ) ) {
		return $created;
	}

	$table   = ufsc_get_licences_table();
	$columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : $wpdb->get_col( "DESCRIBE `{$table}`" );

	for ( $i = 0; $i < $missing; $i++ ) {
		$data = array();

		if ( in_array( 'club_id', $columns, true ) ) {
			$data['club_id'] = (int) $club_id;
		}
		if ( in_array( 'nom', $columns, true ) ) {
			$data['nom'] = '';
		}
		if ( in_array( 'prenom', $columns, true ) ) {
			$data['prenom'] = '';
		}
		if ( in_array( 'email', $columns, true ) ) {
			$data['email'] = '';
		}
		if ( in_array( 'statut', $columns, true ) ) {
			$data['statut'] = 'en_attente';
		}
		if ( in_array( 'status', $columns, true ) ) {
			$data['status'] = 'en_attente';
		}
		if ( in_array( 'payment_status', $columns, true ) ) {
			$data['payment_status'] = 'paid';
		}
		foreach ( array( 'paid', 'payee', 'is_paid' ) as $paid_col ) {
			if ( in_array( $paid_col, $columns, true ) ) {
				$data[ $paid_col ] = 1;
			}
		}
		if ( in_array( 'is_included', $columns, true ) ) {
			$data['is_included'] = 0;
		}
		if ( in_array( 'paid_date', $columns, true ) ) {
			$data['paid_date'] = current_time( 'mysql' );
		}
		if ( in_array( 'paid_season', $columns, true ) && '' !== $season ) {
			$data['paid_season'] = $season;
		}
		if ( in_array( 'season_end_year', $columns, true ) && function_exists( 'ufsc_get_season_end_year_from_label' ) && '' !== $season ) {
			$data['season_end_year'] = (int) ufsc_get_season_end_year_from_label( $season );
		}
		if ( in_array( 'date_creation', $columns, true ) ) {
			$data['date_creation'] = current_time( 'mysql' );
		}
		if ( in_array( 'date_modification', $columns, true ) ) {
			$data['date_modification'] = current_time( 'mysql' );
		}
		if ( in_array( 'date_inscription', $columns, true ) ) {
			$data['date_inscription'] = current_time( 'mysql' );
		}
		if ( in_array( 'note', $columns, true ) ) {
			$data['note'] = sprintf( 'Commande WooCommerce #%d - Item #%d', $order->get_id(), $item->get_id() );
		}

		if ( empty( $data ) || ! isset( $data['club_id'] ) ) {
			ufsc_wc_log( 'ufsc_licence_generation_missing_required_columns', array( 'order_id' => $order->get_id(), 'item_id' => $item->get_id() ), 'error' );
			break;
		}

		$inserted = $wpdb->insert( $table, $data );
		if ( false === $inserted ) {
			ufsc_wc_log(
				'ufsc_licence_generation_insert_failed',
				array(
					'order_id' => $order->get_id(),
					'item_id'  => $item->get_id(),
					'error'    => (string) $wpdb->last_error,
				),
				'error'
			);
			continue;
		}

		$new_id     = (int) $wpdb->insert_id;
		if ( function_exists( 'ufsc_get_licence_season' ) && function_exists( 'ufsc_set_licence_season' ) ) {
			$stored_season = ufsc_get_licence_season( $new_id );
			if ( ! is_string( $stored_season ) || '' === trim( $stored_season ) ) {
				ufsc_set_licence_season( $new_id, ufsc_get_current_season() );
			}
		}
		$created[]  = $new_id;
		do_action( 'ufsc_licence_created', $new_id, (int) $club_id );
		do_action( 'ufsc_licence_updated', (int) $club_id );
	}

	return $created;
}

/**
 * Link licence rows to order/item for audit when columns exist.
 */
function ufsc_wc_link_licence_ids_to_order_item( $order, $item, $licence_ids ) {
	global $wpdb;

	if ( empty( $licence_ids ) || ! function_exists( 'ufsc_get_licences_table' ) ) {
		return;
	}

	$table   = ufsc_get_licences_table();
	$columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : $wpdb->get_col( "DESCRIBE `{$table}`" );
	if ( empty( $columns ) ) {
		return;
	}

	$club_id = absint( $item->get_meta( '_ufsc_club_id', true ) );
	if ( ! $club_id ) {
		$club_id = absint( $item->get_meta( 'ufsc_club_id', true ) );
	}

	$data = array();
	$type = array();
	if ( in_array( 'order_id', $columns, true ) ) {
		$data['order_id'] = (int) $order->get_id();
		$type[]           = '%d';
	}
	if ( in_array( 'order_item_id', $columns, true ) ) {
		$data['order_item_id'] = (int) $item->get_id();
		$type[]                = '%d';
	}
	if ( $club_id && in_array( 'club_id', $columns, true ) ) {
		$data['club_id'] = (int) $club_id;
		$type[]          = '%d';
	}

	if ( empty( $data ) ) {
		return;
	}

	foreach ( $licence_ids as $licence_id ) {
		$wpdb->update( $table, $data, array( 'id' => absint( $licence_id ) ), $type, array( '%d' ) );
	}
}

/**
 * Human readable display for stored licence ID arrays in Woo admin.
 */
function ufsc_wc_format_order_item_meta_display( $display_value, $meta, $item ) {
	$meta_key = isset( $meta->key ) ? (string) $meta->key : '';
	if ( ! in_array( $meta_key, array( '_ufsc_licence_ids', 'ufsc_licence_ids' ), true ) ) {
		return $display_value;
	}

	$ids = ufsc_parse_licence_ids_from_meta( $meta->value );
	if ( empty( $ids ) ) {
		return $display_value;
	}

	return implode( ', ', $ids );
}

/**
 * Render per-item warning in order admin when IDs are missing vs qty.
 */
function ufsc_wc_render_missing_ids_hint( $item_id, $item, $product ) {
	if ( ! is_admin() || ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
		return;
	}

	$qty     = max( 1, absint( $item->get_quantity() ) );
	$ids     = ufsc_get_item_licence_ids( $item );
	$missing = max( 0, $qty - count( $ids ) );

	if ( $missing <= 0 ) {
		return;
	}

	echo '<p style="margin:4px 0;color:#b32d2e;font-weight:600;">' . esc_html__( 'IDs manquants — génération incomplète/forçage.', 'ufsc-clubs' ) . '</p>';
}

/**
 * Render order-level secure action link for missing IDs generation.
 */
function ufsc_wc_render_generate_missing_admin_action( $order ) {
	if ( ! $order || ! is_admin() || ! ufsc_wc_user_can_manage_licences() ) {
		return;
	}

	$order_id = absint( $order->get_id() );
	if ( ! $order_id || ! ufsc_wc_order_has_missing_licence_ids( $order ) ) {
		return;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=ufsc_generate_missing_licences&order_id=' . $order_id ),
		'ufsc_generate_missing_licences_' . $order_id
	);

	echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Générer les licences manquantes', 'ufsc-clubs' ) . '</a></p>';
}

/**
 * Handle secure admin action that generates only missing IDs.
 */
function ufsc_wc_handle_generate_missing_licences() {
	if ( ! is_admin() || ! ufsc_wc_user_can_manage_licences() ) {
		wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
	}

	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
	if ( ! $order_id ) {
		wp_die( esc_html__( 'Commande invalide.', 'ufsc-clubs' ) );
	}

	check_admin_referer( 'ufsc_generate_missing_licences_' . $order_id );

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_die( esc_html__( 'Commande introuvable.', 'ufsc-clubs' ) );
	}

	$before_missing = ufsc_wc_count_order_missing_licence_ids( $order );
	ufsc_wc_maybe_generate_order_licences( $order );
	$after_missing  = ufsc_wc_count_order_missing_licence_ids( $order );
	$generated      = max( 0, $before_missing - $after_missing );

	ufsc_wc_log(
		'ufsc_manual_generate_missing_licences',
		array(
			'order_id'       => $order_id,
			'before_missing' => $before_missing,
			'after_missing'  => $after_missing,
			'generated'      => $generated,
		)
	);

	$redirect = add_query_arg(
		array(
			'post'                   => $order_id,
			'action'                 => 'edit',
			'ufsc_missing_generated' => $generated,
			'ufsc_missing_left'      => $after_missing,
		),
		admin_url( 'post.php' )
	);

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Show action feedback in admin order screen.
 */
function ufsc_wc_render_generate_missing_notice() {
	if ( ! is_admin() || ! isset( $_GET['ufsc_missing_generated'] ) || ! isset( $_GET['post'] ) ) {
		return;
	}

	if ( 'shop_order' !== get_post_type( absint( $_GET['post'] ) ) ) {
		return;
	}

	$generated = absint( $_GET['ufsc_missing_generated'] );
	$left      = isset( $_GET['ufsc_missing_left'] ) ? absint( $_GET['ufsc_missing_left'] ) : 0;
	$class     = $left > 0 ? 'notice notice-warning is-dismissible' : 'notice notice-success is-dismissible';
	$message   = $left > 0
		? sprintf( __( 'Licences générées partiellement (%1$d). IDs manquants restants : %2$d.', 'ufsc-clubs' ), $generated, $left )
		: sprintf( __( 'Licences manquantes générées : %d.', 'ufsc-clubs' ), $generated );

	echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
}

/**
 * Capability helper for admin licence maintenance.
 */
function ufsc_wc_user_can_manage_licences() {
	if ( function_exists( 'current_user_can' ) && current_user_can( 'ufsc_licence_edit' ) ) {
		return true;
	}

	return function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' );
}

/**
 * Check if an order still has missing licence IDs.
 */
function ufsc_wc_order_has_missing_licence_ids( $order ) {
	return ufsc_wc_count_order_missing_licence_ids( $order ) > 0;
}

/**
 * Count missing licence IDs across relevant line items.
 */
function ufsc_wc_count_order_missing_licence_ids( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return 0;
	}

	$wc_settings = ufsc_get_woocommerce_settings();
	$product_id  = isset( $wc_settings['product_license_id'] ) ? absint( $wc_settings['product_license_id'] ) : 0;
	if ( ! $product_id ) {
		return 0;
	}

	$missing_total = 0;
	foreach ( $order->get_items() as $item ) {
		if ( absint( $item->get_product_id() ) !== $product_id ) {
			continue;
		}
		$qty           = max( 1, absint( $item->get_quantity() ) );
		$ids           = ufsc_get_item_licence_ids( $item );
		$missing_total += max( 0, $qty - count( $ids ) );
	}

	return (int) $missing_total;
}

/**
 * Check if licence row is already paid using existing UFSC schema variants.
 *
 * @param object $row Licence row.
 * @return bool
 */
function ufsc_wc_is_licence_paid_row( $row ) {
	$payment_status = strtolower( (string) ( $row->payment_status ?? '' ) );
	if ( in_array( $payment_status, array( 'paid', 'completed', 'processing' ), true ) ) {
		return true;
	}

	foreach ( array( 'paid', 'payee', 'is_paid' ) as $paid_key ) {
		if ( (int) ( $row->{$paid_key} ?? 0 ) === 1 ) {
			return true;
		}
	}

	return false;
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
	$order_status = (string) $order->get_status();

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

	if ( in_array( 'status', $columns, true ) ) {
		$data['status'] = $status;
		$types[]        = '%s';
	}

	if ( $payment_status && in_array( 'payment_status', $columns, true ) ) {
		$data['payment_status'] = $payment_status;
		$types[]                = '%s';
	}

	if ( empty( $data ) ) {
		return;
	}

	$wpdb->update( $table, $data, array( 'id' => absint( $licence_id ) ), $types, array( '%d' ) );

	$club_id = $wpdb->get_var( $wpdb->prepare( "SELECT club_id FROM {$table} WHERE id = %d", absint( $licence_id ) ) );
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
	return $order ? (string) $order->get_status() : '';
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
			array( 'id' => absint( $license_id ) ),
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