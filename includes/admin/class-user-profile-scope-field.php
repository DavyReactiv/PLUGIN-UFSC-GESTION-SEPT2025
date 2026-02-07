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
        if ( ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $current = get_user_meta( $user->ID, UFSC_Scope::USER_META_KEY, true );
        $regions = UFSC_Scope::get_regions_map();
        $can_all = UFSC_Scope::user_has_all_regions( $user->ID );

        echo '<h2>' . esc_html__( 'UFSC', 'ufsc-clubs' ) . '</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th><label for="ufsc_scope_region">' . esc_html__( 'Scope région', 'ufsc-clubs' ) . '</label></th>';
        echo '<td>';
        wp_nonce_field( 'ufsc_scope_region_save', 'ufsc_scope_region_nonce' );
        echo '<select name="ufsc_scope_region" id="ufsc_scope_region">';
        if ( $can_all ) {
            echo '<option value="">' . esc_html__( 'Toutes régions', 'ufsc-clubs' ) . '</option>';
        } else {
            echo '<option value="">' . esc_html__( '— Sélectionner —', 'ufsc-clubs' ) . '</option>';
        }
        foreach ( $regions as $slug => $label ) {
            echo '<option value="' . esc_attr( $slug ) . '"' . selected( $current, $slug, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        if ( ! $can_all ) {
            echo '<p class="description">' . esc_html__( 'L’option "Toutes régions" nécessite la capacité ufsc_scope_all_regions.', 'ufsc-clubs' ) . '</p>';
        }
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    public static function save_field( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        if ( ! isset( $_POST['ufsc_scope_region_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ufsc_scope_region_nonce'] ) ), 'ufsc_scope_region_save' ) ) {
            return;
        }

        $value = isset( $_POST['ufsc_scope_region'] ) ? sanitize_text_field( wp_unslash( $_POST['ufsc_scope_region'] ) ) : '';

        if ( $value === '' ) {
            if ( UFSC_Scope::user_has_all_regions( $user_id ) ) {
                delete_user_meta( $user_id, UFSC_Scope::USER_META_KEY );
                return;
            }
            delete_user_meta( $user_id, UFSC_Scope::USER_META_KEY );
            return;
        }

        $regions = UFSC_Scope::get_regions_map();
        if ( ! isset( $regions[ $value ] ) ) {
            delete_user_meta( $user_id, UFSC_Scope::USER_META_KEY );
            return;
        }

        update_user_meta( $user_id, UFSC_Scope::USER_META_KEY, $value );
    }
}
