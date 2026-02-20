<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC PATCH: Licence status normalization helpers.
 */

/**
 * Get raw status value from a licence record.
 *
 * @param object|array $licence Licence record.
 * @return string
 */
function ufsc_get_licence_status_raw( $licence ) {
	if ( is_array( $licence ) ) {
		return (string) ( $licence['statut'] ?? '' );
	}

	if ( is_object( $licence ) ) {
		return (string) ( $licence->statut ?? '' );
	}

	return '';
}

/**
 * Normalize a licence status to a consistent internal value.
 *
 * @param string $status Raw status.
 * @return string Normalized status.
 */
function ufsc_normalize_licence_status( $status ) {
	$status = strtolower( trim( (string) $status ) );
	if ( '' === $status ) {
		return 'brouillon';
	}

	$map = array(
		// Canonical
		'brouillon'   => 'brouillon',
		'non_payee'   => 'non_payee',
		'non_payée'   => 'non_payee',
		'en_attente'  => 'en_attente',
		'valide'      => 'valide',
		'refuse'      => 'refuse',
		'a_regler'    => 'a_regler',
		'desactive'   => 'desactive',
		'expire'      => 'expire',
		'expiré'      => 'expire',
		'expirée'     => 'expire',

		// Legacy mappings
		'draft'       => 'brouillon',
		'pending'     => 'en_attente',
		'pending_payment' => 'en_attente',
		'attente'     => 'en_attente',
		'paid'        => 'en_attente',
		'payee'       => 'en_attente',
		'payée'       => 'en_attente',

		'valid'       => 'valide',
		'validé'      => 'valide',
		'validé'     => 'valide',
		'validee'     => 'valide',
		'validée'     => 'valide',
		'validated'   => 'valide',
		'approved'    => 'valide',
		'active'      => 'valide',
		'actif'       => 'valide',
		'applied'     => 'valide',

		'refusé'      => 'refuse',
		'refusee'     => 'refuse',
		'refusée'     => 'refuse',
		'rejected'    => 'refuse',
		'denied'      => 'refuse',
		'expired'     => 'expire',
	);

	return $map[ $status ] ?? $status;
}

/**
 * Canonical license statuses (value => label).
 *
 * @return array
 */
function ufsc_get_license_statuses() {
	$statuses = array(
		'brouillon'  => __( 'Brouillon', 'ufsc-clubs' ),
		'en_attente' => __( 'En attente de validation', 'ufsc-clubs' ),
		'valide'     => __( 'Validée', 'ufsc-clubs' ),
		'refuse'     => __( 'Refusée', 'ufsc-clubs' ),
		'desactive'  => __( 'Désactivée', 'ufsc-clubs' ),
		'a_regler'   => __( 'À régler', 'ufsc-clubs' ),
		'expire'     => __( 'Expirée', 'ufsc-clubs' ),
		'non_payee'  => __( 'Non payée', 'ufsc-clubs' ),
	);

	if ( class_exists( 'UFSC_SQL' ) && method_exists( 'UFSC_SQL', 'statuses' ) ) {
		$statuses = wp_parse_args( UFSC_SQL::statuses(), $statuses );
	}

	return $statuses;
}

/**
 * Normalize any license status to canonical format.
 *
 * @param string $raw Raw status.
 * @return string
 */
function ufsc_normalize_license_status( $raw ) {
	$normalized = ufsc_normalize_licence_status( $raw );

	return array_key_exists( $normalized, ufsc_get_license_statuses() )
		? $normalized
		: 'brouillon';
}

/**
 * Get status label.
 *
 * @param string $status Raw/normalized status.
 * @return string
 */
function ufsc_license_status_label( $status ) {
	$normalized = ufsc_normalize_license_status( $status );
	$statuses   = ufsc_get_license_statuses();

	return isset( $statuses[ $normalized ] ) ? $statuses[ $normalized ] : $statuses['brouillon'];
}

/**
 * Get badge CSS class for a status.
 *
 * @param string $status Raw/normalized status.
 * @return string
 */
function ufsc_license_status_badge_class( $status ) {
	$normalized = ufsc_normalize_license_status( $status );
	$map        = array(
		'brouillon'  => 'badge-draft',
		'en_attente' => 'badge-warning',
		'valide'     => 'badge-success',
		'refuse'     => 'badge-danger',
		'desactive'  => 'badge-dark',
		'a_regler'   => 'badge-warning',
		'expire'     => 'badge-secondary',
		'non_payee'  => 'badge-danger',
	);

	return isset( $map[ $normalized ] ) ? $map[ $normalized ] : 'badge-secondary';
}

/**
 * Normalize a raw status value.
 *
 * @param string $raw Raw status.
 * @return string
 */
function ufsc_get_licence_status_norm( $raw ) {
	return ufsc_normalize_license_status( $raw );
}

/**
 * Get raw status values that map to a normalized status.
 *
 * @param string $normalized Normalized status.
 * @return array
 */
function ufsc_get_licence_status_raw_values_for_norm( $normalized ) {
	$normalized = ufsc_get_licence_status_norm( $normalized );

	$map = array(
		'brouillon'  => array( 'brouillon', 'draft' ),
		'non_payee'  => array( 'non_payee', 'non_payée' ),
		'en_attente' => array( 'en_attente', 'attente', 'pending', 'pending_payment', 'a_regler', 'paid', 'payee', 'payée' ),
		'valide'     => array( 'valide', 'valid', 'validé', 'validé', 'validee', 'validée', 'validated', 'approved', 'active', 'actif', 'applied' ),
		'refuse'     => array( 'refuse', 'refusé', 'refusee', 'refusée', 'rejected', 'denied' ),
		'a_regler'   => array( 'a_regler' ),
		'desactive'  => array( 'desactive' ),
		'expire'     => array( 'expire', 'expired', 'expiré', 'expirée' ),
	);

	return $map[ $normalized ] ?? array( $normalized );
}

/**
 * Get the French label for a licence status.
 *
 * @param string $status Raw or normalized status.
 * @return string
 */
function ufsc_get_licence_status_label_fr( $status ) {
	$normalized = ufsc_get_licence_status_norm( $status );
	$labels     = array();

	if ( class_exists( 'UFSC_SQL' ) && method_exists( 'UFSC_SQL', 'statuses' ) ) {
		$labels = UFSC_SQL::statuses();
	}

	if ( empty( $labels ) ) {
		$labels = ufsc_get_license_statuses();
	}

	if ( ! array_key_exists( $normalized, $labels ) ) {
		$normalized = 'brouillon';
	}

	return $labels[ $normalized ]
		?? $labels['brouillon']
		?? ucfirst( str_replace( '_', ' ', $normalized ) );
}

/**
 * Get a normalized status for a licence record.
 *
 * @param object|array $licence Licence record.
 * @return string
 */
function ufsc_get_licence_status_from_record( $licence ) {
	$raw = ufsc_get_licence_status_raw( $licence );
	return ufsc_get_licence_status_norm( $raw );
}

/**
 * Licence status helpers for normalization, labels, and syncing.
 */
final class UFSC_Licence_Status {

	/**
	 * Normalize raw input to a canonical status value.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function normalize( $raw ) {
		return ufsc_normalize_licence_status( $raw );
	}

	/**
	 * Get allowed status values.
	 *
	 * @return array
	 */
	public static function allowed() {
		if ( class_exists( 'UFSC_SQL' ) && method_exists( 'UFSC_SQL', 'statuses' ) ) {
			return array_keys( UFSC_SQL::statuses() );
		}

		return array( 'en_attente', 'valide', 'a_regler', 'desactive', 'refuse', 'expire', 'brouillon', 'non_payee' );
	}

	/**
	 * Check if a status is allowed.
	 *
	 * @param string $status Status value.
	 * @return bool
	 */
	public static function is_valid( $status ) {
		$status = self::normalize( $status );
		return in_array( $status, self::allowed(), true );
	}

	/**
	 * Get a display-safe status value.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function display_status( $raw ) {
		$normalized = self::normalize( $raw );
		if ( '' === $normalized || ! self::is_valid( $normalized ) ) {
			return 'brouillon';
		}
		return $normalized;
	}

	/**
	 * Get the French label for a status.
	 *
	 * @param string $status Raw or normalized status.
	 * @return string
	 */
	public static function label( $status ) {
		$status = self::display_status( $status );
		return ufsc_get_licence_status_label_fr( $status );
	}

	/**
	 * Update status columns for a licence row.
	 *
	 * @param string $table Table name.
	 * @param array  $where Where clause.
	 * @param string $status Status to set.
	 * @param array  $where_format Where format.
	 * @return int|false
	 */
	public static function update_status_columns( $table, $where, $status, $where_format = array( '%d' ) ) {
		global $wpdb;

		$columns = function_exists( 'ufsc_table_columns' )
			? ufsc_table_columns( $table )
			: $wpdb->get_col( "DESCRIBE `{$table}`" );

		$data   = array();
		$format = array();

		if ( in_array( 'statut', $columns, true ) ) {
			$data['statut'] = $status;
			$format[]       = '%s';
		}

		if ( in_array( 'status', $columns, true ) ) {
			$data['status'] = $status;
			$format[]       = '%s';
		}

		if ( empty( $data ) ) {
			return false;
		}

		return $wpdb->update( $table, $data, $where, $format, $where_format );
	}

	/**
	 * Sync legacy status column with canonical statut.
	 *
	 * @return int Number of rows updated.
	 */
	public static function sync_legacy_status_column() {
		global $wpdb;

		if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
			return 0;
		}

		$table = ufsc_get_licences_table();

		$columns = function_exists( 'ufsc_table_columns' )
			? ufsc_table_columns( $table )
			: $wpdb->get_col( "DESCRIBE `{$table}`" );

		if ( ! in_array( 'statut', $columns, true ) || ! in_array( 'status', $columns, true ) ) {
			return 0;
		}

		$updated = $wpdb->query(
			"UPDATE `{$table}` SET `status` = `statut` WHERE `statut` IS NOT NULL AND `statut` <> '' AND (`status` IS NULL OR `status` = '' OR `status` <> `statut`)"
		);

		if ( class_exists( 'UFSC_Audit_Logger' ) ) {
			UFSC_Audit_Logger::log( sprintf( 'UFSC: Synced licence status column (updated %d rows).', (int) $updated ) );
		}

		return (int) $updated;
	}
}

/**
 * Determine if a licence can be edited.
 *
 * NOTE: en_attente is editable unless ufsc_is_licence_locked_for_club() returns true
 * (i.e. reliable payment/order linkage). This prevents the "stuck pending" issue.
 *
 * @param string $status Raw status.
 * @return bool
 */
function ufsc_is_editable_licence_status( $status ) {
	$normalized = ufsc_get_licence_status_norm( $status );
	return in_array( $normalized, array( 'brouillon', 'en_attente', 'non_payee', 'a_regler' ), true );
}

/**
 * Check if a licence is paid using schema-compatible markers.
 *
 * @param object|array $licence Licence record.
 * @return bool
 */
function ufsc_is_licence_paid( $licence ) {
	$payment_status = '';

	if ( is_array( $licence ) ) {
		$payment_status = strtolower( (string) ( $licence['payment_status'] ?? '' ) );
	} elseif ( is_object( $licence ) ) {
		$payment_status = strtolower( (string) ( $licence->payment_status ?? '' ) );
	}

	if ( in_array( $payment_status, array( 'paid', 'completed', 'processing' ), true ) ) {
		return true;
	}

	foreach ( array( 'paid', 'payee', 'is_paid' ) as $key ) {
		$value = 0;

		if ( is_array( $licence ) ) {
			$value = (int) ( $licence[ $key ] ?? 0 );
		} elseif ( is_object( $licence ) ) {
			$value = (int) ( $licence->{$key} ?? 0 );
		}

		if ( 1 === $value ) {
			return true;
		}
	}

	$order_id = 0;
	if ( is_array( $licence ) ) {
		$order_id = absint( $licence['order_id'] ?? 0 );
	} elseif ( is_object( $licence ) ) {
		$order_id = absint( $licence->order_id ?? 0 );
	}

	return $order_id > 0;
}

/**
 * Check if licence is locked for club-side edit/delete.
 *
 * Fail-closed: invalid payload, unknown status => locked.
 * Special case: order_id referenced but Woo order missing => locked ONLY if paid proof exists in payload.
 *
 * @param object|array $licence Licence record.
 * @return bool
 */
function ufsc_is_licence_locked_for_club( $licence ) {
	// Fail-closed on invalid payload.
	if ( ! is_array( $licence ) && ! is_object( $licence ) ) {
		if ( function_exists( 'ufsc_wc_log' ) ) {
			ufsc_wc_log( 'ufsc_licence_lock_invalid_payload', array( 'type' => gettype( $licence ) ), 'error' );
		}
		return true;
	}

	$status_raw = is_array( $licence )
		? ( $licence['statut'] ?? ( $licence['status'] ?? '' ) )
		: ( $licence->statut ?? ( $licence->status ?? '' ) );
	$status     = ufsc_get_licence_status_norm( $status_raw );

	// Explicit admin lock.
	$locked_by_admin = is_array( $licence )
		? (int) ( $licence['locked_by_admin'] ?? 0 )
		: (int) ( $licence->locked_by_admin ?? 0 );
	if ( 1 === $locked_by_admin ) {
		return true;
	}

	// Locked statuses.
	if ( in_array( $status, array( 'valide', 'refuse', 'desactive', 'expire' ), true ) ) {
		return true;
	}

	// Fail-closed on unknown raw status value (when a raw is present but not recognized).
	$raw_trim = strtolower( trim( (string) $status_raw ) );
	if ( '' !== $raw_trim && function_exists( 'ufsc_get_licence_status_raw_values_for_norm' ) ) {
		$known_raw_values = ufsc_get_licence_status_raw_values_for_norm( $status );
		$known_raw_values = array_map(
			static function( $v ) {
				return strtolower( trim( (string) $v ) );
			},
			is_array( $known_raw_values ) ? $known_raw_values : array()
		);

		if ( ! in_array( $raw_trim, $known_raw_values, true ) ) {
			if ( function_exists( 'ufsc_wc_log' ) ) {
				ufsc_wc_log( 'ufsc_licence_lock_unknown_status', array( 'status_raw' => (string) $status_raw ), 'error' );
			}
			return true;
		}
	}

	/**
	 * en_attente is locked ONLY when a reliable payment/order linkage exists.
	 * Otherwise it must remain editable to avoid the "stuck pending" scenario.
	 */
	if ( 'en_attente' === $status ) {
		// Reliable proof from schema-compatible markers.
		if ( ufsc_is_licence_paid( $licence ) ) {
			return true;
		}

		$order_id = is_array( $licence ) ? absint( $licence['order_id'] ?? 0 ) : absint( $licence->order_id ?? 0 );

		// If Woo is available, verify order status.
		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order && ! in_array( (string) $order->get_status(), array( 'cancelled', 'failed', 'refunded', 'trash' ), true ) ) {
				return true;
			}
		} elseif ( $order_id > 0 && ! function_exists( 'wc_get_order' ) ) {
			// No Woo runtime => no reliable proof; keep editable but log.
			if ( function_exists( 'ufsc_wc_log' ) ) {
				ufsc_wc_log( 'ufsc_licence_lock_order_check_unavailable', array( 'order_id' => $order_id ), 'warning' );
			}
		}

		return false;
	}

	// If an order is referenced, try to validate payment proof via Woo.
	$order_id = is_array( $licence ) ? absint( $licence['order_id'] ?? 0 ) : absint( $licence->order_id ?? 0 );
	if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( $order_id );

		// Special case: order missing => lock ONLY if paid proof exists in payload, else allow edits (and warn).
		if ( ! $order ) {
			$payment_status = is_array( $licence )
				? strtolower( (string) ( $licence['payment_status'] ?? '' ) )
				: strtolower( (string) ( $licence->payment_status ?? '' ) );

			$has_paid_proof = in_array( $payment_status, array( 'paid', 'completed', 'processing' ), true );

			if ( ! $has_paid_proof ) {
				foreach ( array( 'paid', 'payee', 'is_paid' ) as $paid_key ) {
					$paid_value = is_array( $licence )
						? (int) ( $licence[ $paid_key ] ?? 0 )
						: (int) ( $licence->{$paid_key} ?? 0 );
					if ( 1 === $paid_value ) {
						$has_paid_proof = true;
						break;
					}
				}
			}

			if ( function_exists( 'ufsc_wc_log' ) ) {
				ufsc_wc_log(
					$has_paid_proof ? 'ufsc_licence_lock_order_missing_but_paid' : 'ufsc_licence_lock_order_missing_not_paid',
					array( 'order_id' => $order_id, 'has_paid_proof' => $has_paid_proof ),
					$has_paid_proof ? 'error' : 'warning'
				);
			}

			return $has_paid_proof;
		}

		// Consider paid states as locked.
		if ( in_array( (string) $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			return true;
		}
	}

	// Fallback on schema-compatible "paid" markers.
	return ufsc_is_licence_paid( $licence );
}

/**
 * Check if a licence payment can be retried based on latest Woo order status.
 *
 * @param int $licence_id Licence ID.
 * @return bool
 */
function ufsc_can_retry_licence_payment( $licence_id ) {
	if ( ! function_exists( 'ufsc_get_latest_licence_order_status' ) ) {
		return false;
	}

	$status = ufsc_get_latest_licence_order_status( $licence_id );
	return in_array( $status, array( 'failed', 'cancelled' ), true );
}