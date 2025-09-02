<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_SQL_Shortcodes {

    public static function register()
    {
        self::register_shortcodes();
    }

    public static function register_shortcodes() {
        add_shortcode( 'ufsc_sql_licence_form', array( __CLASS__, 'licence_form' ) );
        add_shortcode( 'ufsc_sql_my_club', array( __CLASS__, 'my_club' ) );
    }

    public static function licence_form( $atts = array(), $content = '' ) {
        ob_start();
        if ( class_exists( 'UFSC_SQL_Public' ) ) {
            UFSC_SQL_Public::render_licence_form();
        } else {
            echo '<div>Formulaire indisponible.</div>';
        }
        return ob_get_clean();
    }

    public static function my_club( $atts = array(), $content = '' ) {
        ob_start();
        if ( class_exists( 'UFSC_SQL_Public' ) ) {
            UFSC_SQL_Public::render_my_club();
        } else {
            echo '<div>Profil club indisponible.</div>';
        }
        return ob_get_clean();
    }
}
