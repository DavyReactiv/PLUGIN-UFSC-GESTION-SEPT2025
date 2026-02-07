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
        return '';
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
 * Normalize a raw status value.
 *
 * @param string $raw Raw status.
 * @return string
 */
function ufsc_get_licence_status_norm( $raw ) {
    return ufsc_normalize_licence_status( $raw );
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
        $labels = array(
            'brouillon'  => __( 'Brouillon', 'ufsc-clubs' ),
            'non_payee'  => __( 'Non payée', 'ufsc-clubs' ),
            'valide'     => __( 'Validé', 'ufsc-clubs' ),
            'en_attente' => __( 'En attente', 'ufsc-clubs' ),
            'refuse'     => __( 'Refusé', 'ufsc-clubs' ),
            'a_regler'   => __( 'À régler', 'ufsc-clubs' ),
            'desactive'  => __( 'Désactivée', 'ufsc-clubs' ),
            'expire'     => __( 'Expiré', 'ufsc-clubs' ),
        );
    }

    if ( ! array_key_exists( $normalized, $labels ) ) {
        $normalized = 'en_attente';
    }

    return $labels[ $normalized ];
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
            return 'en_attente';
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
 * @param string $status Raw status.
 * @return bool
 */
function ufsc_is_editable_licence_status( $status ) {
    $normalized = ufsc_get_licence_status_norm( $status );
    return in_array( $normalized, array( 'brouillon', 'non_payee', 'en_attente' ), true );
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
