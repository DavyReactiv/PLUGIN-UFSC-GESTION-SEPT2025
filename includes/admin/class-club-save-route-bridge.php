<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Ensure the front-office club creation flow has a single canonical handler.
 *
 * Both UFSC_Unified_Handlers and UFSC_CL_Club_Form_Handler historically register
 * the same admin-post action. The unified handler can redirect back to the form
 * before the front handler adds the affiliation product to WooCommerce.
 *
 * This bridge is intentionally non-destructive: it only removes the duplicate
 * callbacks for the club-save endpoint. Licence handlers and AJAX routes remain
 * untouched.
 */
final class UFSC_Club_Save_Route_Bridge {
    public static function init() {
        add_action( 'init', array( __CLASS__, 'route_to_canonical_handler' ), PHP_INT_MAX );
    }

    public static function route_to_canonical_handler() {
        if ( class_exists( 'UFSC_Unified_Handlers' ) ) {
            remove_action( 'admin_post_ufsc_save_club', array( 'UFSC_Unified_Handlers', 'handle_save_club' ) );
            remove_action( 'admin_post_nopriv_ufsc_save_club', array( 'UFSC_Unified_Handlers', 'handle_save_club' ) );
        }

        // Keep the canonical front-office handler registered exactly once.
        if ( class_exists( 'UFSC_CL_Club_Form_Handler' ) ) {
            remove_action( 'admin_post_ufsc_save_club', array( 'UFSC_CL_Club_Form_Handler', 'handle_save_club' ) );
            remove_action( 'admin_post_nopriv_ufsc_save_club', array( 'UFSC_CL_Club_Form_Handler', 'handle_save_club' ) );
            add_action( 'admin_post_ufsc_save_club', array( 'UFSC_CL_Club_Form_Handler', 'handle_save_club' ), 10 );
            add_action( 'admin_post_nopriv_ufsc_save_club', array( 'UFSC_CL_Club_Form_Handler', 'handle_save_club' ), 10 );
        }
    }
}
