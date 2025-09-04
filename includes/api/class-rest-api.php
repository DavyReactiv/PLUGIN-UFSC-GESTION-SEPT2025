<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_REST_API {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route( 'ufsc/v1', '/stats', array(
            'methods'  => 'GET',
            'callback' => array( __CLASS__, 'get_stats' ),
        ) );

        register_rest_route( 'ufsc/v1', '/licences', array(
            'methods'  => 'GET',
            'callback' => array( __CLASS__, 'get_licences' ),
        ) );

        register_rest_route( 'ufsc/v1', '/clubs/(?P<id>\\d+)', array(
            'methods'  => 'GET',
            'callback' => array( __CLASS__, 'get_club' ),
        ) );
    }

    public static function get_stats( $request ) {
        return rest_ensure_response( array( 'stats' => array() ) );
    }

    public static function get_licences( $request ) {
        return rest_ensure_response( array( 'licences' => array() ) );
    }

    public static function get_club( $request ) {
        $id = (int) $request['id'];
        return rest_ensure_response( array( 'club_id' => $id ) );
    }
}

UFSC_REST_API::init();
