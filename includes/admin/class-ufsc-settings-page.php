<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_Settings_Page {
    const OPTION_KEY = 'ufsc_woocommerce_settings';

    public static function get_default_settings() {
        return array(
            'product_license_id'     => 2934,
            'product_affiliation_id' => 4823,
            'included_licenses'      => 10,
            'max_profile_photo_size' => 2,
            'auto_consume_included'  => 1,
        );
    }

    public static function get_settings() {
        $defaults = self::get_default_settings();
        $saved    = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( $saved, $defaults );
    }

    public static function save_settings( $input ) {
        $sanitized = array();
        if ( isset( $input['product_license_id'] ) ) {
            $sanitized['product_license_id'] = absint( $input['product_license_id'] );
        }
        if ( isset( $input['product_affiliation_id'] ) ) {
            $sanitized['product_affiliation_id'] = absint( $input['product_affiliation_id'] );
        }
        if ( isset( $input['included_licenses'] ) ) {
            $sanitized['included_licenses'] = absint( $input['included_licenses'] );
        }
        if ( isset( $input['max_profile_photo_size'] ) ) {
            $sanitized['max_profile_photo_size'] = absint( $input['max_profile_photo_size'] );
        }
        $sanitized['auto_consume_included'] = ! empty( $input['auto_consume_included'] ) ? 1 : 0;

        $existing = get_option( self::OPTION_KEY, array() );
        $sanitized = array_merge( $existing, $sanitized );
        update_option( self::OPTION_KEY, $sanitized );
    }

    public static function render() {
        if ( isset( $_POST['ufsc_settings_save'] ) && check_admin_referer( 'ufsc_settings' ) ) {
            self::save_settings( wp_unslash( $_POST ) );
            echo '<div class="updated"><p>' . esc_html__( 'Paramètres enregistrés.', 'ufsc-clubs' ) . '</p></div>';
        }
        $s = self::get_settings();
        echo '<div class="wrap"><h1>' . esc_html__( 'Paramètres UFSC', 'ufsc-clubs' ) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field( 'ufsc_settings' );
        echo '<table class="form-table">';

        echo '<tr><th scope="row"><label for="product_affiliation_id">' . esc_html__( 'ID du produit pack affiliation', 'ufsc-clubs' ) . '</label></th>';
        echo '<td><input type="number" id="product_affiliation_id" name="product_affiliation_id" value="' . esc_attr( $s['product_affiliation_id'] ) . '" class="regular-text" /></td></tr>';

        echo '<tr><th scope="row"><label for="product_license_id">' . esc_html__( 'ID du produit licence additionnelle', 'ufsc-clubs' ) . '</label></th>';
        echo '<td><input type="number" id="product_license_id" name="product_license_id" value="' . esc_attr( $s['product_license_id'] ) . '" class="regular-text" /></td></tr>';

        echo '<tr><th scope="row"><label for="included_licenses">' . esc_html__( 'Licences incluses par pack', 'ufsc-clubs' ) . '</label></th>';
        echo '<td><input type="number" id="included_licenses" name="included_licenses" value="' . esc_attr( $s['included_licenses'] ) . '" class="regular-text" min="0" /></td></tr>';

        echo '<tr><th scope="row"><label for="max_profile_photo_size">' . esc_html__( 'Taille max photo profil (Mo)', 'ufsc-clubs' ) . '</label></th>';
        echo '<td><input type="number" id="max_profile_photo_size" name="max_profile_photo_size" value="' . esc_attr( $s['max_profile_photo_size'] ) . '" class="regular-text" min="1" /></td></tr>';

        echo '<tr><th scope="row">' . esc_html__( 'Auto-consommer licences incluses', 'ufsc-clubs' ) . '</th>';
        echo '<td><label><input type="checkbox" id="auto_consume_included" name="auto_consume_included" value="1" ' . checked( 1, $s['auto_consume_included'], false ) . ' /> ' . esc_html__( 'Activer', 'ufsc-clubs' ) . '</label></td></tr>';

        echo '</table>';
        submit_button( __( 'Enregistrer', 'ufsc-clubs' ), 'primary', 'ufsc_settings_save' );
        echo '</form></div>';
    }
}
