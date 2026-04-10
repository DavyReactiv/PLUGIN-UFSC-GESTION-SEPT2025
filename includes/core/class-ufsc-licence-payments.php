<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Conservative payment traceability layer for licences.
 */
class UFSC_Licence_Payments {
	const TABLE_SUFFIX = 'ufsc_licence_payments';
	const OPTION_DB_VERSION = 'ufsc_licence_payments_db_version';
	const DB_VERSION = '1.0.0';

	/**
	 * Install hooks.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_migrate' ) );
	}

	/**
	 * Ensure schema exists.
	 */
	public static function maybe_migrate() {
		$current = (string) get_option( self::OPTION_DB_VERSION, '0.0.0' );
		if ( version_compare( $current, self::DB_VERSION, '>=' ) ) {
			return;
		}

		self::create_payments_table();
		self::ensure_licence_summary_columns();
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	/**
	 * Create append-only payment trace table.
	 */
	private static function create_payments_table() {
		global $wpdb;
		$table = self::get_table_name();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			licence_id bigint(20) unsigned NOT NULL,
			action varchar(50) NOT NULL DEFAULT 'linked',
			payment_status varchar(50) NOT NULL DEFAULT 'non_rattache',
			payment_source varchar(50) NOT NULL DEFAULT '',
			payment_method varchar(50) NOT NULL DEFAULT '',
			payment_gateway varchar(100) NOT NULL DEFAULT '',
			payment_reference varchar(191) NOT NULL DEFAULT '',
			payment_date datetime NULL,
			payment_amount decimal(12,2) NULL,
			currency varchar(10) NOT NULL DEFAULT '',
			wc_order_id bigint(20) unsigned NULL,
			wc_order_item_id bigint(20) unsigned NULL,
			wc_transaction_id varchar(191) NOT NULL DEFAULT '',
			admin_note text NULL,
			reconciliation_status varchar(50) NOT NULL DEFAULT '',
			reconciliation_note text NULL,
			exception_reason varchar(191) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			fingerprint varchar(64) NOT NULL,
			is_deleted tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY idx_licence_id (licence_id),
			KEY idx_payment_status (payment_status),
			KEY idx_payment_source (payment_source),
			KEY idx_wc_order_id (wc_order_id),
			UNIQUE KEY uniq_licence_fingerprint (licence_id,fingerprint)
		) {$wpdb->get_charset_collate()};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add conservative summary columns on licences table when available.
	 */
	private static function ensure_licence_summary_columns() {
		if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
			return;
		}

		global $wpdb;
		$table = ufsc_get_licences_table();
		if ( empty( $table ) ) {
			return;
		}

		$columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $table ) : $wpdb->get_col( "DESCRIBE `{$table}`" );
		$definitions = array(
			'payment_link_status'          => "ALTER TABLE `{$table}` ADD COLUMN payment_link_status varchar(50) NOT NULL DEFAULT 'non_rattache'",
			'payment_source'               => "ALTER TABLE `{$table}` ADD COLUMN payment_source varchar(50) NOT NULL DEFAULT ''",
			'payment_method'               => "ALTER TABLE `{$table}` ADD COLUMN payment_method varchar(50) NOT NULL DEFAULT ''",
			'payment_gateway'              => "ALTER TABLE `{$table}` ADD COLUMN payment_gateway varchar(100) NOT NULL DEFAULT ''",
			'payment_reference'            => "ALTER TABLE `{$table}` ADD COLUMN payment_reference varchar(191) NOT NULL DEFAULT ''",
			'payment_date'                 => "ALTER TABLE `{$table}` ADD COLUMN payment_date datetime NULL",
			'wc_order_id'                  => "ALTER TABLE `{$table}` ADD COLUMN wc_order_id bigint(20) unsigned NULL",
			'wc_order_item_id'             => "ALTER TABLE `{$table}` ADD COLUMN wc_order_item_id bigint(20) unsigned NULL",
			'wc_transaction_id'            => "ALTER TABLE `{$table}` ADD COLUMN wc_transaction_id varchar(191) NOT NULL DEFAULT ''",
			'payment_linked_by'            => "ALTER TABLE `{$table}` ADD COLUMN payment_linked_by bigint(20) unsigned NOT NULL DEFAULT 0",
			'payment_linked_at'            => "ALTER TABLE `{$table}` ADD COLUMN payment_linked_at datetime NULL",
			'payment_reconciliation_status'=> "ALTER TABLE `{$table}` ADD COLUMN payment_reconciliation_status varchar(50) NOT NULL DEFAULT ''",
			'payment_reconciliation_note'  => "ALTER TABLE `{$table}` ADD COLUMN payment_reconciliation_note text NULL",
			'payment_exception_reason'     => "ALTER TABLE `{$table}` ADD COLUMN payment_exception_reason varchar(191) NOT NULL DEFAULT ''",
		);

		foreach ( $definitions as $column => $sql ) {
			if ( ! in_array( $column, $columns, true ) ) {
				$wpdb->query( $sql );
			}
		}
	}

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function get_payment_snapshot( $licence_id ) {
		if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
			return null;
		}
		global $wpdb;
		$table = ufsc_get_licences_table();
		$licence_id = absint( $licence_id );
		if ( $licence_id <= 0 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) );
	}

	public static function can_validate_licence( $licence_row, $exception_reason = '' ) {
		$payment_status = strtolower( (string) ( $licence_row->payment_status ?? '' ) );
		$link_status    = strtolower( (string) ( $licence_row->payment_link_status ?? '' ) );
		if ( in_array( $payment_status, array( 'paid', 'completed', 'processing', 'paye', 'payee' ), true ) ) {
			return true;
		}
		if ( in_array( $link_status, array( 'paye', 'paid', 'paye_manuellement', 'exonere' ), true ) ) {
			return true;
		}
		return '' !== trim( (string) $exception_reason );
	}

	public static function upsert_manual_payment( $licence_id, $data ) {
		$licence_id = absint( $licence_id );
		if ( $licence_id <= 0 ) {
			return false;
		}

		$normalized = array(
			'action'                => 'manual_linked',
			'payment_status'        => ! empty( $data['payment_status'] ) ? sanitize_key( $data['payment_status'] ) : 'paye_manuellement',
			'payment_source'        => 'manuel',
			'payment_method'        => ! empty( $data['payment_method'] ) ? sanitize_key( $data['payment_method'] ) : 'autre',
			'payment_gateway'       => 'manuel',
			'payment_reference'     => sanitize_text_field( (string) ( $data['payment_reference'] ?? '' ) ),
			'payment_date'          => ! empty( $data['payment_date'] ) ? sanitize_text_field( $data['payment_date'] ) : current_time( 'mysql' ),
			'payment_amount'        => isset( $data['payment_amount'] ) && '' !== $data['payment_amount'] ? (float) $data['payment_amount'] : null,
			'currency'              => ! empty( $data['currency'] ) ? strtoupper( sanitize_text_field( $data['currency'] ) ) : 'EUR',
			'admin_note'            => sanitize_textarea_field( (string) ( $data['admin_note'] ?? '' ) ),
			'reconciliation_status' => sanitize_key( (string) ( $data['reconciliation_status'] ?? '' ) ),
			'reconciliation_note'   => sanitize_textarea_field( (string) ( $data['reconciliation_note'] ?? '' ) ),
			'exception_reason'      => sanitize_text_field( (string) ( $data['exception_reason'] ?? '' ) ),
			'created_by'            => get_current_user_id(),
		);

		self::save_trace_and_summary( $licence_id, $normalized );
		return true;
	}

	public static function sync_order_payments( $order, $licence_ids ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		$licence_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $licence_ids ) ) ) );
		if ( empty( $licence_ids ) ) {
			return;
		}

		$transaction_id = method_exists( $order, 'get_transaction_id' ) ? (string) $order->get_transaction_id() : '';
		$gateway = method_exists( $order, 'get_payment_method' ) ? (string) $order->get_payment_method() : '';
		$method  = 'cb';
		if ( in_array( $gateway, array( 'bacs', 'cheque', 'cod' ), true ) ) {
			$method = $gateway;
		}

		foreach ( $order->get_items() as $item ) {
			$item_licence_ids = function_exists( 'ufsc_get_item_licence_ids' ) ? ufsc_get_item_licence_ids( $item ) : array();
			$item_licence_ids = array_values( array_intersect( $licence_ids, array_map( 'absint', $item_licence_ids ) ) );
			if ( empty( $item_licence_ids ) ) {
				continue;
			}

			foreach ( $item_licence_ids as $licence_id ) {
				self::save_trace_and_summary(
					$licence_id,
					array(
						'action'            => 'woo_paid',
						'payment_status'    => 'paid',
						'payment_source'    => 'woocommerce',
						'payment_method'    => $method,
						'payment_gateway'   => $gateway,
						'payment_reference' => (string) $order->get_order_number(),
						'payment_date'      => current_time( 'mysql' ),
						'payment_amount'    => (float) $item->get_total(),
						'currency'          => (string) $order->get_currency(),
						'wc_order_id'       => (int) $order->get_id(),
						'wc_order_item_id'  => (int) $item->get_id(),
						'wc_transaction_id' => $transaction_id,
						'created_by'        => 0,
					)
				);
			}
		}
	}

	public static function mark_order_as_unpaid( $order, $licence_ids, $reason = '' ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		foreach ( (array) $licence_ids as $licence_id ) {
			$licence_id = absint( $licence_id );
			if ( $licence_id <= 0 ) {
				continue;
			}
			self::save_trace_and_summary(
				$licence_id,
				array(
					'action'            => 'woo_unpaid',
					'payment_status'    => 'non_rattache',
					'payment_source'    => 'woocommerce',
					'payment_method'    => 'cb',
					'payment_gateway'   => (string) $order->get_payment_method(),
					'payment_reference' => (string) $order->get_order_number(),
					'payment_date'      => current_time( 'mysql' ),
					'wc_order_id'       => (int) $order->get_id(),
					'wc_transaction_id' => (string) $order->get_transaction_id(),
					'admin_note'        => $reason,
					'created_by'        => get_current_user_id(),
				)
			);
		}
	}

	private static function save_trace_and_summary( $licence_id, $payload ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = array(
			'action'                => 'linked',
			'payment_status'        => 'non_rattache',
			'payment_source'        => '',
			'payment_method'        => '',
			'payment_gateway'       => '',
			'payment_reference'     => '',
			'payment_date'          => current_time( 'mysql' ),
			'payment_amount'        => null,
			'currency'              => '',
			'wc_order_id'           => null,
			'wc_order_item_id'      => null,
			'wc_transaction_id'     => '',
			'admin_note'            => '',
			'reconciliation_status' => '',
			'reconciliation_note'   => '',
			'exception_reason'      => '',
			'created_by'            => get_current_user_id(),
		);
		$data = wp_parse_args( $payload, $defaults );
		$data['created_at'] = current_time( 'mysql' );
		$data['licence_id'] = $licence_id;
		$data['fingerprint'] = hash( 'sha256', wp_json_encode( array(
			$licence_id,
			$data['action'],
			$data['payment_status'],
			$data['payment_source'],
			$data['payment_method'],
			$data['payment_gateway'],
			$data['payment_reference'],
			$data['wc_order_id'],
			$data['wc_order_item_id'],
			$data['wc_transaction_id'],
		) ) );

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO `{$table}`
			(licence_id, action, payment_status, payment_source, payment_method, payment_gateway, payment_reference, payment_date, payment_amount, currency, wc_order_id, wc_order_item_id, wc_transaction_id, admin_note, reconciliation_status, reconciliation_note, exception_reason, created_by, created_at, fingerprint, is_deleted)
			VALUES (%d,%s,%s,%s,%s,%s,%s,%s,%f,%s,%d,%d,%s,%s,%s,%s,%s,%d,%s,%s,0)
			ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)",
			$licence_id,
			$data['action'],
			$data['payment_status'],
			$data['payment_source'],
			$data['payment_method'],
			$data['payment_gateway'],
			$data['payment_reference'],
			$data['payment_date'],
			(float) $data['payment_amount'],
			$data['currency'],
			(int) $data['wc_order_id'],
			(int) $data['wc_order_item_id'],
			$data['wc_transaction_id'],
			$data['admin_note'],
			$data['reconciliation_status'],
			$data['reconciliation_note'],
			$data['exception_reason'],
			(int) $data['created_by'],
			$data['created_at'],
			$data['fingerprint']
		) );

		self::update_licence_summary( $licence_id, $data );
	}

	private static function update_licence_summary( $licence_id, $data ) {
		if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
			return;
		}
		global $wpdb;
		$table = ufsc_get_licences_table();
		$columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $table ) : $wpdb->get_col( "DESCRIBE `{$table}`" );
		$summary = array(
			'payment_link_status'           => $data['payment_status'],
			'payment_status'                => in_array( $data['payment_status'], array( 'paid', 'paye', 'paye_manuellement' ), true ) ? 'paid' : $data['payment_status'],
			'payment_source'                => $data['payment_source'],
			'payment_method'                => $data['payment_method'],
			'payment_gateway'               => $data['payment_gateway'],
			'payment_reference'             => $data['payment_reference'],
			'payment_date'                  => $data['payment_date'],
			'wc_order_id'                   => (int) $data['wc_order_id'],
			'wc_order_item_id'              => (int) $data['wc_order_item_id'],
			'wc_transaction_id'             => $data['wc_transaction_id'],
			'payment_linked_by'             => (int) $data['created_by'],
			'payment_linked_at'             => current_time( 'mysql' ),
			'payment_reconciliation_status' => $data['reconciliation_status'],
			'payment_reconciliation_note'   => $data['reconciliation_note'],
			'payment_exception_reason'      => $data['exception_reason'],
		);
		$update = array();
		foreach ( $summary as $k => $v ) {
			if ( in_array( $k, $columns, true ) ) {
				$update[ $k ] = $v;
			}
		}
		if ( ! empty( $update ) ) {
			$wpdb->update( $table, $update, array( 'id' => $licence_id ) );
		}
	}
}

UFSC_Licence_Payments::init();
