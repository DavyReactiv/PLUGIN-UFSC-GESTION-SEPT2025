<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC User Club Mapping
 * Gère les relations entre les utilisateurs WordPress et les clubs
 */
class UFSC_User_Club_Mapping {

    /**
     * Récupère l'ID du club pour un utilisateur
     *
     * @param int $user_id
     * @return int|false
     */
    public static function get_user_club_id( $user_id ) {
        global $wpdb;

        $settings        = UFSC_SQL::get_settings();
        $clubs_table     = $settings['table_clubs'];
        $pk_col          = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id';
        $responsable_col = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'responsable_id' ) : 'responsable_id';

        $club_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `{$pk_col}` FROM `{$clubs_table}` WHERE `{$responsable_col}` = %d LIMIT 1",
                $user_id
            )
        );

        return $club_id ? (int) $club_id : false;
    }

    /**
     * Récupère les données du club d'un utilisateur
     *
     * @param int $user_id
     * @return object|false
     */
    public static function get_user_club( $user_id ) {
        global $wpdb;

        $settings        = UFSC_SQL::get_settings();
        $clubs_table     = $settings['table_clubs'];
        $responsable_col = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'responsable_id' ) : 'responsable_id';

        $club = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$clubs_table}` WHERE `{$responsable_col}` = %d LIMIT 1",
                $user_id
            )
        );

        return $club ?: false;
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

        $user = get_user_by( 'id', (int) $user_id );
        if ( ! $user ) { return false; }

        $settings        = UFSC_SQL::get_settings();
        $clubs_table     = $settings['table_clubs'];
        $pk_col          = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id';
        $responsable_col = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'responsable_id' ) : 'responsable_id';

        // Le club existe-t-il ?
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `{$pk_col}` FROM `{$clubs_table}` WHERE `{$pk_col}` = %d LIMIT 1",
                $club_id
            )
        );
        if ( ! $exists ) { return false; }

        // L'utilisateur est-il déjà responsable d'un autre club ?
        $existing = self::get_user_club_id( $user_id );
        if ( $existing && (int) $existing !== (int) $club_id ) {
            return false;
        }

        $res = $wpdb->update(
            $clubs_table,
            array( $responsable_col => (int) $user_id ),
            array( $pk_col => (int) $club_id ),
            array( '%d' ),
            array( '%d' )
        );

        return $res !== false;
    }

    /**
     * Supprime l'association utilisateur-club
     *
     * @param int $user_id
     * @return bool
     */
    public static function remove_user_club_association( $user_id ) {
        global $wpdb;

        $club_id = self::get_user_club_id( $user_id );
        if ( ! $club_id ) {
            return true; // déjà sans association
        }

        $settings        = UFSC_SQL::get_settings();
        $clubs_table     = $settings['table_clubs'];
        $pk_col          = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id';
        $responsable_col = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'responsable_id' ) : 'responsable_id';

        // Mettre explicitement la colonne à NULL pour éviter les problèmes de format.
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$clubs_table}` SET `{$responsable_col}` = NULL WHERE `{$pk_col}` = %d",
                $club_id
            )
        );

        if ( $result !== false ) {
            if ( function_exists( 'ufsc_audit_log' ) ) {
                ufsc_audit_log( 'user_club_dissociated', array(
                    'user_id'       => (int) $user_id,
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

        $settings    = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];

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

        $settings    = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];

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
        // Les administrateurs peuvent tout gérer.
        if ( current_user_can( 'ufsc_manage' ) ) {
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

        $settings    = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];

        // Validation de la région via la liste déclarée par le plugin.
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

if ( ! function_exists( 'ufsc_get_user_club' ) ) {
    function ufsc_get_user_club( $user_id ) {
        return UFSC_User_Club_Mapping::get_user_club( $user_id );
    }
}