<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * User profile field for UFSC scope region.
 */
class UFSC_User_Profile_Scope_Field {

    public static function init() {
        add_action( 'show_user_profile', array( __CLASS__, 'render_field' ) );
        add_action( 'edit_user_profile', array( __CLASS__, 'render_field' ) );
        add_action( 'personal_options_update', array( __CLASS__, 'save_field' ) );
        add_action( 'edit_user_profile_update', array( __CLASS__, 'save_field' ) );
    }

    public static function render_field( $user ) {
        if ( ! current_user_can( 'edit_user', $user->ID ) ) { return; }
        $current = get_user_meta( $user->ID, UFSC_Scope::USER_META_KEY, true );
        $regions = UFSC_Scope::get_regions_map();
        $can_all = UFSC_Scope::user_has_all_regions( $user->ID );
        echo '<h2>' . esc_html__( 'UFSC', 'ufsc-clubs' ) . '</h2>';
        echo '<table class="form-table" role="presentation"><tr><th><label for="ufsc_scope_region">' . esc_html__( 'Scope région', 'ufsc-clubs' ) . '</label></th><td>';
        wp_nonce_field( 'ufsc_scope_region_save', 'ufsc_scope_region_nonce' );
        echo '<select name="ufsc_scope_region" id="ufsc_scope_region">';
        echo '<option value="">' . esc_html( $can_all ? __( 'Toutes régions', 'ufsc-clubs' ) : __( '— Sélectionner —', 'ufsc-clubs' ) ) . '</option>';
        foreach ( $regions as $slug => $label ) { echo '<option value="' . esc_attr( $slug ) . '"' . selected( $current, $slug, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select>';
        if ( ! $can_all ) { echo '<p class="description">' . esc_html__( 'L’option "Toutes régions" nécessite la capacité ufsc_scope_all_regions.', 'ufsc-clubs' ) . '</p>'; }
        echo '</td></tr></table>';
    }

    public static function save_field( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) { return; }
        if ( ! isset( $_POST['ufsc_scope_region_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ufsc_scope_region_nonce'] ) ), 'ufsc_scope_region_save' ) ) { return; }
        $value = isset( $_POST['ufsc_scope_region'] ) ? sanitize_text_field( wp_unslash( $_POST['ufsc_scope_region'] ) ) : '';
        if ( '' === $value ) { delete_user_meta( $user_id, UFSC_Scope::USER_META_KEY ); return; }
        $regions = UFSC_Scope::get_regions_map();
        if ( ! isset( $regions[ $value ] ) ) { delete_user_meta( $user_id, UFSC_Scope::USER_META_KEY ); return; }
        update_user_meta( $user_id, UFSC_Scope::USER_META_KEY, $value );
    }
}

// Modules d'administration chargés depuis un fichier déjà requis côté admin.
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-export-admin.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-official-documents-admin.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-official-template-admin.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-affiliation-action-bridge.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-birthplace-fields.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-licence-role-ui.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-club-form-fields-bridge.php';
require_once UFSC_CL_DIR . 'includes/admin/class-clubs-selected-export.php';
require_once UFSC_CL_DIR . 'includes/admin/class-clubs-flexible-export.php';
require_once UFSC_CL_DIR . 'includes/admin/class-clubs-export-selection.php';
require_once UFSC_CL_DIR . 'includes/admin/class-club-manual-renewal-admin.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-compliance-admin.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-insurance-admin.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-documents-admin.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ffst-documents-admin-v2.php';
require_once UFSC_CL_DIR . 'includes/admin/class-ufsc-bank-transfer-admin.php';
UFSC_FFST_Birthplace_Fields::init();
UFSC_FFST_Licence_Role_UI::init();
UFSC_FFST_Club_Form_Fields_Bridge::init();
UFSC_Clubs_Selected_Export::init();
UFSC_Clubs_Flexible_Export::init();
UFSC_Clubs_Export_Selection::init();
UFSC_FFST_Official_Template_Admin::init();
add_action( 'admin_menu', static function() {
    add_submenu_page(
        'ufsc-dashboard',
        __( 'Dossiers FFST', 'ufsc-clubs' ),
        __( 'Dossiers FFST', 'ufsc-clubs' ),
        UFSC_Permissions::CAP_GESTION_MANAGE,
        'ufsc-ffst-documents',
        array( 'UFSC_FFST_Documents_Admin_V2', 'render' )
    );
}, 25 );
