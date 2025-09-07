<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Permissions helper for club management
 */
class UFSC_CL_Permissions {
    
    /**
     * Check if current user can edit a specific club
     * 
     * @param int $club_id Club ID to check
     * @return bool True if user can edit, false otherwise
     */
    public static function ufsc_user_can_edit_club( $club_id ) {
        // Admin users can edit any club
        if ( current_user_can( 'ufsc_manage' ) ) {
            return true;
        }
        
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            return false;
        }
        
        // Get club data to check responsable_id
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $pk = ufsc_club_col( 'id' );
        $responsable_col = ufsc_club_col( 'responsable_id' );
        
        $club = $wpdb->get_row( $wpdb->prepare(
            "SELECT `{$responsable_col}` FROM `{$table}` WHERE `{$pk}` = %d",
            $club_id
        ) );
        
        if ( ! $club ) {
            return false;
        }
        
        // User can edit if they are the responsable of the club
        $responsable_id_field = ufsc_club_col( 'responsable_id' );
        return get_current_user_id() === (int) $club->{$responsable_id_field};
    }
    
    /**
     * Check if current user can create clubs
     *
     * Users may create only one club. If the current user already
     * manages a club, additional creation is blocked.
     *
     * @return bool True if user can create clubs
     */
    public static function ufsc_user_can_create_club() {
        // Must be logged in to create clubs
        if ( ! is_user_logged_in() ) {
            return false;
        }

        // Check if user already manages a club
        $club_id = UFSC_User_Club_Mapping::get_user_club_id( get_current_user_id() );

        $can_create = ! $club_id;

        /**
         * Filter the ability for a user to create a new club.
         *
         * @param bool      $can_create Whether the user can create a club.
         * @param int|false $club_id    Existing club ID if found.
         */
        return apply_filters( 'ufsc_user_can_create_club', $can_create, $club_id );
    }
}