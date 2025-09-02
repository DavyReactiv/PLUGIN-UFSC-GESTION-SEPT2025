<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC User Club Mapping
 * Handles relationships between WordPress users and clubs
 */
class UFSC_User_Club_Mapping {

    /**
     * Get club ID for a user
     * 
     * @param int $user_id User ID
     * @return int|false Club ID or false if none found
     */
    public static function get_user_club_id( $user_id ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) { return false; }
        $clubs_table = ufsc_get_clubs_table();
        $club_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$clubs_table}` WHERE responsable_id = %d LIMIT 1",

        
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        $pk_col = ufsc_club_col( 'id' );
        $responsable_col = ufsc_club_col( 'responsable_id' );
        
        $club_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT `{$pk_col}` FROM `{$clubs_table}` WHERE `{$responsable_col}` = %d LIMIT 1",

            $user_id
        ) );
        return $club_id ? (int) $club_id : false;
    }

    /**
     * Get club data for a user
     * 
     * @param int $user_id User ID
     * @return object|false Club object or false if none found
     */
    public static function get_user_club( $user_id ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) { return false; }
        $clubs_table = ufsc_get_clubs_table();
        $club = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$clubs_table}` WHERE responsable_id = %d LIMIT 1",

        
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        $responsable_col = ufsc_club_col( 'responsable_id' );
        
        $club = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$clubs_table}` WHERE `{$responsable_col}` = %d LIMIT 1",

            $user_id
        ) );
        return $club ?: false;
    }

    /**
     * Associate a user with a club
     * 
     * @param int $user_id User ID
     * @param int $club_id Club ID
     * @return bool Success status
     */
    public static function associate_user_with_club( $user_id, $club_id ) {
        global $wpdb;
        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) { return false; }
        $clubs_table = ufsc_get_clubs_table();
        $user = get_user_by( 'id', (int) $user_id );
        if ( ! $user ) { return false; }
        $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM `{$clubs_table}` WHERE id = %d", $club_id) );
        if ( ! $exists ) { return false; }
        $existing = self::get_user_club_id( $user_id );
        if ( $existing && (int) $existing !== (int) $club_id ) { return false; }
        $res = $wpdb->update( $clubs_table, array( 'responsable_id' => (int) $user_id ), array( 'id' => (int) $club_id ), array( '%d' ), array( '%d' ) );
        return $res !== false;
    }

    /**
     * Remove user-club association
     * 
     * @param int $user_id User ID
     * @return bool Success status
     */
    public static function remove_user_club_association( $user_id ) {
        global $wpdb;
        
        $club_id = self::get_user_club_id( $user_id );
        if ( ! $club_id ) {
            return true; // Already no association
        }
        
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        
        $result = $wpdb->update(
            $clubs_table,
            array( 'responsable_id' => null ),
            array( 'id' => $club_id ),
            array( '%d' ),
            array( '%d' )
        );
        
        if ( $result !== false ) {
            // Log the removal
            ufsc_audit_log( 'user_club_dissociated', array(
                'user_id' => $user_id,
                'club_id' => $club_id,
                'admin_user_id' => get_current_user_id()
            ) );
            
            return true;
        }
        
        return false;
    }

    /**
     * Get all users who manage clubs
     * 
     * @return array Array of user data with club info
     */
    public static function get_club_managers() {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        
        $results = $wpdb->get_results( "
            SELECT c.id as club_id, c.nom as club_name, c.region, c.responsable_id as user_id
            FROM {$clubs_table} c 
            WHERE c.responsable_id IS NOT NULL AND c.responsable_id > 0
            ORDER BY c.nom
        " );
        
        $managers = array();
        foreach ( $results as $result ) {
            $user = get_user_by( 'id', $result->user_id );
            if ( $user ) {
                $managers[] = array(
                    'club_id' => $result->club_id,
                    'club_name' => $result->club_name,
                    'region' => $result->region,
                    'user_id' => $result->user_id,
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'display_name' => $user->display_name
                );
            }
        }
        
        return $managers;
    }

    /**
     * Get clubs without a manager
     * 
     * @return array Array of clubs without managers
     */
    public static function get_clubs_without_managers() {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        
        return $wpdb->get_results( "
            SELECT id, nom, region, email 
            FROM {$clubs_table} 
            WHERE (responsable_id IS NULL OR responsable_id = 0)
            ORDER BY nom
        " );
    }

    /**
     * Check if user can manage a specific club
     * 
     * @param int $user_id User ID
     * @param int $club_id Club ID
     * @return bool
     */
    public static function user_can_manage_club( $user_id, $club_id ) {
        // Admins can manage any club
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        
        $user_club_id = self::get_user_club_id( $user_id );
        return $user_club_id && $user_club_id === $club_id;
    }

    /**
     * Update club region
     * 
     * @param int $club_id Club ID
     * @param string $region Region name
     * @return bool Success status
     */
    public static function update_club_region( $club_id, $region ) {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        
        // Validate region
        $valid_regions = ufsc_get_regions_list();
        if ( ! in_array( $region, $valid_regions ) ) {
            return false;
        }
        
        $result = $wpdb->update(
            $clubs_table,
            array( 'region' => $region ),
            array( 'id' => $club_id ),
            array( '%s' ),
            array( '%d' )
        );
        
        if ( $result !== false ) {
            ufsc_audit_log( 'club_region_updated', array(
                'club_id' => $club_id,
                'region' => $region,
                'user_id' => get_current_user_id()
            ) );
            
            return true;
        }
        
        return false;
    }
}

/**
 * Helper function for backward compatibility
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