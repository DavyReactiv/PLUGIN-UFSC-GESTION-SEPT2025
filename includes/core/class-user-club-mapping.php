<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC User Club Mapping
 * Gère les relations entre les utilisateurs WordPress et les clubs
 */
class UFSC_User_Club_Mapping {

    /** Request-local caches: one portal page asks for the same mapping many times. */
    private static $resolution_cache = array();
    private static $club_cache = array();

    /** Reset request-local values after a relation write. */
    private static function clear_user_cache( $user_id ) {
        $user_id = absint( $user_id );
        if ( $user_id < 1 ) { return; }
        unset( self::$resolution_cache[ $user_id ], self::$club_cache[ $user_id ] );
    }

    /**
     * Return the canonical clubs table used by admin, front-end and mapping.
     *
     * @return string
     */
    private static function get_clubs_table() {
        if ( class_exists( 'UFSC_Storage_Resolver' ) ) {
            return UFSC_Storage_Resolver::get_clubs_table();
        }
        if ( function_exists( 'ufsc_get_clubs_table' ) ) {
            return ufsc_get_clubs_table();
        }

        if ( class_exists( 'UFSC_SQL' ) && is_callable( array( 'UFSC_SQL', 'get_settings' ) ) ) {
            $settings = UFSC_SQL::get_settings();
            return isset( $settings['table_clubs'] ) ? $settings['table_clubs'] : 'clubs';
        }

        global $wpdb;
        return $wpdb->prefix . 'ufsc_clubs';
    }

    /**
     * Récupère l'ID du club pour un utilisateur
     *
     * @param int $user_id
     * @return int|false
     */
    public static function get_user_club_id( $user_id ) {
        $result = self::resolve_user_club( $user_id );
        return ! empty( $result['found'] ) && 'diagnostic_only' !== ( $result['confidence'] ?? '' ) ? (int) $result['club_id'] : false;
    }

    /**
     * Resolve a user-to-club link across modern and historical sources.
     * Email matches are returned as diagnostic-only and never become an automatic link.
     */
    public static function resolve_user_club( $user_id ) {
        $user_id = absint( $user_id );
        if ( $user_id < 1 ) {
            return array( 'found' => false, 'club_id' => 0, 'source' => 'none', 'confidence' => 'none', 'diagnostic_code' => 'invalid_user' );
        }

        if ( array_key_exists( $user_id, self::$resolution_cache ) ) {
            return self::$resolution_cache[ $user_id ];
        }

        if ( class_exists( 'UFSC_Storage_Resolver' ) ) {
            $result = UFSC_Storage_Resolver::resolve_club_for_user( $user_id );
            self::$resolution_cache[ $user_id ] = is_array( $result ) ? $result : array( 'found' => false, 'club_id' => 0, 'source' => 'none', 'confidence' => 'none', 'diagnostic_code' => 'resolver_invalid_result' );
            return self::$resolution_cache[ $user_id ];
        }

        global $wpdb;
        $clubs_table     = self::get_clubs_table();
        $pk_col          = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id';
        $responsable_col = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'responsable_id' ) : 'responsable_id';
        $club_id = $wpdb->get_var( $wpdb->prepare( "SELECT `{$pk_col}` FROM `{$clubs_table}` WHERE `{$responsable_col}` = %d LIMIT 1", $user_id ) );
        self::$resolution_cache[ $user_id ] = $club_id
            ? array( 'found' => true, 'club_id' => (int) $club_id, 'source' => 'club_column:responsable_id', 'confidence' => 'high', 'diagnostic_code' => 'explicit_column_match' )
            : array( 'found' => false, 'club_id' => 0, 'source' => 'none', 'confidence' => 'none', 'diagnostic_code' => 'no_relation' );
        return self::$resolution_cache[ $user_id ];
    }

    /**
     * Récupère les données du club d'un utilisateur
     *
     * @param int $user_id
     * @return object|false
     */
    public static function get_user_club( $user_id ) {
        global $wpdb;

        $user_id = absint( $user_id );
        if ( $user_id < 1 ) { return false; }
        if ( array_key_exists( $user_id, self::$club_cache ) ) {
            return self::$club_cache[ $user_id ];
        }

        $club_id = self::get_user_club_id( $user_id );
        if ( ! $club_id ) {
            self::$club_cache[ $user_id ] = false;
            return false;
        }
        $clubs_table = self::get_clubs_table();
        $pk_col = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::first_existing_column( $clubs_table, array( 'id', 'club_id', 'ID' ) ) : ( function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id' );
        $club = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$clubs_table}` WHERE `{$pk_col}` = %d LIMIT 1", $club_id ) );
        self::$club_cache[ $user_id ] = $club ?: false;
        return self::$club_cache[ $user_id ];
    }

    /**
     * Associe un utilisateur à un club
     *
     * @param int $user_id
     * @param int $club_id
     * @return bool
     */
    public static function associate_user_with_club( $user_id, $club_id ) {
        global $wpdb;

        $user_id = absint( $user_id );
        $club_id = absint( $club_id );
        $user = get_user_by( 'id', $user_id );
        if ( ! $user || $club_id < 1 ) { return false; }

        $clubs_table     = self::get_clubs_table();
        $pk_col          = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id';
        $responsable_col = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'responsable_id' ) : 'responsable_id';

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `{$pk_col}` FROM `{$clubs_table}` WHERE `{$pk_col}` = %d LIMIT 1",
                $club_id
            )
        );
        if ( ! $exists ) { return false; }

        $existing = self::get_user_club_id( $user_id );
        if ( $existing && (int) $existing !== (int) $club_id ) {
            return false;
        }

        $res = $wpdb->update(
            $clubs_table,
            array( $responsable_col => $user_id ),
            array( $pk_col => $club_id ),
            array( '%d' ),
            array( '%d' )
        );

        if ( false !== $res ) {
            self::clear_user_cache( $user_id );
            return true;
        }
        return false;
    }

    /**
     * Supprime l'association utilisateur-club
     *
     * @param int $user_id
     * @return bool
     */
    public static function remove_user_club_association( $user_id ) {
        global $wpdb;

        $user_id = absint( $user_id );
        $club_id = self::get_user_club_id( $user_id );
        if ( ! $club_id ) {
            return true;
        }

        $clubs_table     = self::get_clubs_table();
        $pk_col          = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id';
        $responsable_col = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'responsable_id' ) : 'responsable_id';

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$clubs_table}` SET `{$responsable_col}` = NULL WHERE `{$pk_col}` = %d",
                $club_id
            )
        );

        if ( $result !== false ) {
            self::clear_user_cache( $user_id );
            if ( function_exists( 'ufsc_audit_log' ) ) {
                ufsc_audit_log( 'user_club_dissociated', array(
                    'user_id'       => $user_id,
                    'club_id'       => (int) $club_id,
                    'admin_user_id' => get_current_user_id(),
                ) );
            }
            return true;
        }

        return false;
    }

    /**
     * Récupère les responsables de clubs
     *
     * @return array
     */
    public static function get_club_managers() {
        global $wpdb;

        $clubs_table = self::get_clubs_table();

        $results = $wpdb->get_results("
            SELECT c.id as club_id, c.nom as club_name, c.region, c.responsable_id as user_id
            FROM `{$clubs_table}` c
            WHERE c.responsable_id IS NOT NULL AND c.responsable_id > 0
            ORDER BY c.nom
        ");

        $managers = array();
        foreach ( $results as $result ) {
            $user = get_user_by( 'id', $result->user_id );
            if ( $user ) {
                $managers[] = array(
                    'club_id'      => (int) $result->club_id,
                    'club_name'    => $result->club_name,
                    'region'       => $result->region,
                    'user_id'      => (int) $result->user_id,
                    'user_login'   => $user->user_login,
                    'user_email'   => $user->user_email,
                    'display_name' => $user->display_name,
                );
            }
        }

        return $managers;
    }

    /**
     * Récupère les clubs sans responsable
     *
     * @return array
     */
    public static function get_clubs_without_managers() {
        global $wpdb;

        $clubs_table = self::get_clubs_table();

        return $wpdb->get_results("
            SELECT id, nom, region, email
            FROM `{$clubs_table}`
            WHERE (responsable_id IS NULL OR responsable_id = 0)
            ORDER BY nom
        ");
    }

    /**
     * Vérifie si un utilisateur peut gérer un club donné
     *
     * @param int $user_id
     * @param int $club_id
     * @return bool
     */
    public static function user_can_manage_club( $user_id, $club_id ) {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $user_club_id = self::get_user_club_id( $user_id );
        return $user_club_id && (int) $user_club_id === (int) $club_id;
    }

    /**
     * Met à jour la région d'un club
     *
     * @param int $club_id
     * @param string $region
     * @return bool
     */
    public static function update_club_region( $club_id, $region ) {
        global $wpdb;

        $clubs_table = self::get_clubs_table();

        $valid_regions = function_exists( 'ufsc_get_regions_list' ) ? ufsc_get_regions_list() : array();
        if ( ! in_array( $region, $valid_regions, true ) ) {
            return false;
        }

        $result = $wpdb->update(
            $clubs_table,
            array( 'region' => $region ),
            array( 'id' => (int) $club_id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            if ( function_exists( 'ufsc_audit_log' ) ) {
                ufsc_audit_log( 'club_region_updated', array(
                    'club_id' => (int) $club_id,
                    'region'  => $region,
                    'user_id' => get_current_user_id(),
                ) );
            }
            return true;
        }

        return false;
    }
}

/**
 * Fonctions helper (compat)
 */
if ( ! function_exists( 'ufsc_get_user_club_id' ) ) {
    function ufsc_get_user_club_id( $user_id ) {
        return UFSC_User_Club_Mapping::get_user_club_id( $user_id );
    }
}
if ( ! function_exists( 'ufsc_get_club_id_for_user' ) ) {
    function ufsc_get_club_id_for_user( $user_id ) {
        return UFSC_User_Club_Mapping::resolve_user_club( $user_id );
    }
}

if ( ! function_exists( 'ufsc_get_user_club' ) ) {
    function ufsc_get_user_club( $user_id ) {
        return UFSC_User_Club_Mapping::get_user_club( $user_id );
    }
}
