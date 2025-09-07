<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Status utilities
 * Provides mapping between status keys and their labels, icons and colors.
 */
class UFSC_Status {
    /**
     * Canonical status configuration.
     *
     * @var array
     */
    private static $statuses = array(
        'draft'    => array(
            'label' => 'Brouillon',
            'icon'  => '📝',
            'color' => 'info',
        ),
        'pending'  => array(
            'label' => 'En attente',
            'icon'  => '⏳',
            'color' => 'warning',
        ),
        'active'   => array(
            'label' => 'Active',
            'icon'  => '✅',
            'color' => 'success',
        ),
        'expired'  => array(
            'label' => 'Expirée',
            'icon'  => '⌛',
            'color' => 'danger',
        ),
        'rejected' => array(
            'label' => 'Refusée',
            'icon'  => '❌',
            'color' => 'danger',
        ),
        'inactive' => array(
            'label' => 'Désactivée',
            'icon'  => '⛔',
            'color' => 'danger',
        ),
    );

    /**
     * Map various raw statuses to canonical keys.
     *
     * @var array
     */
    private static $aliases = array(
        // Draft
        'brouillon' => 'draft',
        // Pending
        'en_attente' => 'pending',
        'attente'    => 'pending',
        'a_regler'   => 'pending',
        'pending'    => 'pending',
        'non_payee'  => 'pending',
        // Active / Valid
        'valide'   => 'active',
        'validee'  => 'active',
        'active'   => 'active',
        'applied'  => 'active',
        // Rejected
        'refuse'   => 'rejected',
        'rejected' => 'rejected',
        // Inactive
        'desactive' => 'inactive',
        'inactive'  => 'inactive',
        'off'       => 'inactive',
    );

    /**
     * Normalize a status value to its canonical key.
     *
     * @param string $status Raw status.
     * @return string Canonical status key.
     */
    public static function normalize( $status ) {
        $status = strtolower( trim( $status ) );
        if ( isset( self::$aliases[ $status ] ) ) {
            return self::$aliases[ $status ];
        }
        return $status;
    }

    /**
     * Retrieve status configuration.
     *
     * @param string $status Status key.
     * @return array Configuration array with label, icon and color.
     */
    public static function get( $status ) {
        $status = self::normalize( $status );
        return self::$statuses[ $status ] ?? self::$statuses['draft'];
    }

    /**
     * Get status label.
     */
    public static function label( $status ) {
        $info = self::get( $status );
        return $info['label'];
    }

    /**
     * Get status icon.
     */
    public static function icon( $status ) {
        $info = self::get( $status );
        return $info['icon'];
    }

    /**
     * Get status color.
     */
    public static function color( $status ) {
        $info = self::get( $status );
        return $info['color'];
    }

    /**
     * Get database status variants for a given status.
     *
     * @param string $status Canonical or raw status.
     * @return array Array of database status values.
     */
    public static function db_statuses( $status ) {
        $canonical = self::normalize( $status );
        $map = array(
            'draft'    => array( 'brouillon' ),
            'pending'  => array( 'en_attente', 'attente', 'pending', 'a_regler', 'non_payee' ),
            'active'   => array( 'valide', 'validee', 'active', 'applied' ),
            'expired'  => array( 'expired' ),
            'rejected' => array( 'refuse', 'rejected' ),
            'inactive' => array( 'desactive', 'inactive', 'off' ),
        );
        return $map[ $canonical ] ?? array( $canonical );
    }

    /**
     * Render a badge for the provided status.
     *
     * @param string $status Status key.
     * @return string HTML badge.
     */
    public static function badge( $status ) {
        $info  = self::get( $status );
        $class = 'ufsc-badge ufsc-badge-' . $info['color'];
        return '<span class="' . esc_attr( $class ) . '" aria-label="' . esc_attr( $info['label'] ) . '">' . esc_html( $info['icon'] . ' ' . $info['label'] ) . '</span>';
    }
}

/**
 * Count licences for a club optionally filtered by status.
 *
 * @param int         $club_id Club identifier.
 * @param string|null $status  Optional status to filter.
 * @return int Number of licences.
 */
function ufsc_count_licences( $club_id, $status = null ) {
    global $wpdb;
    $settings       = UFSC_SQL::get_settings();
    $table          = $settings['table_licences'];
    $sql            = "SELECT COUNT(*) FROM `{$table}` WHERE club_id = %d AND deleted_at IS NULL";
    $params         = array( (int) $club_id );

    if ( $status ) {
        $statuses    = UFSC_Status::db_statuses( $status );
        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql         .= " AND statut IN ({$placeholders})";
        $params      = array_merge( $params, $statuses );
    }

    return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
}

/**
 * Get total licence quota for a club.
 *
 * @param int $club_id Club identifier.
 * @return int Quota value.
 */
function ufsc_get_quota_total( $club_id ) {
    global $wpdb;
    $settings    = UFSC_SQL::get_settings();
    $clubs_table = $settings['table_clubs'];
    $quota       = $wpdb->get_var( $wpdb->prepare( "SELECT quota_licences FROM `{$clubs_table}` WHERE id = %d", (int) $club_id ) );
    return (int) $quota;
}

/**
 * Get number of licences already used for a club.
 *
 * @param int $club_id Club identifier.
 * @return int Used licences count.
 */
function ufsc_get_quota_used( $club_id ) {
    return ufsc_count_licences( $club_id );
}

/**
 * Get remaining licence quota for a club.
 *
 * @param int $club_id Club identifier.
 * @return int Remaining quota.
 */
function ufsc_get_quota_remaining( $club_id ) {
    $total = ufsc_get_quota_total( $club_id );
    $used  = ufsc_get_quota_used( $club_id );
    return max( 0, $total - $used );
}

/**
 * Convenience helper returning quota information.
 *
 * @param int $club_id Club identifier.
 * @return array { total, used, remaining }
 */
function ufsc_get_quota_info( $club_id ) {
    $total = ufsc_get_quota_total( $club_id );
    $used  = ufsc_get_quota_used( $club_id );
    return array(
        'total'     => $total,
        'used'      => $used,
        'remaining' => max( 0, $total - $used ),
    );
}
