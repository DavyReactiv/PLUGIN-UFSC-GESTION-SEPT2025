<?php
/**
 * UFSC address-entry assistance.
 *
 * Read-only helper layer: no address migration, no automatic rewrite of stored
 * values, no WooCommerce cart/order hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'ufsc_address_assist_get_countries' ) ) {
    function ufsc_address_assist_get_countries() {
        $countries = array();

        if ( class_exists( 'WC_Countries' ) ) {
            $wc_countries = new WC_Countries();
            $countries = (array) $wc_countries->get_countries();
        }

        if ( empty( $countries ) ) {
            $countries = array(
                'FR' => 'France',
                'BE' => 'Belgique',
                'CH' => 'Suisse',
                'LU' => 'Luxembourg',
                'DE' => 'Allemagne',
                'ES' => 'Espagne',
                'IT' => 'Italie',
                'PT' => 'Portugal',
                'GB' => 'Royaume-Uni',
                'NL' => 'Pays-Bas',
            );
        }

        return apply_filters( 'ufsc_address_assist_countries', $countries );
    }
}

if ( ! function_exists( 'ufsc_address_assist_enqueue' ) ) {
    /**
     * Enqueue the address assistant once.
     *
     * The script performs no network request on page load. Postal-code lookup is
     * triggered only after an eligible French 5-digit code is entered.
     */
    function ufsc_address_assist_enqueue() {
        static $done = false;
        if ( $done ) {
            return;
        }
        $done = true;

        $relative = 'assets/js/ufsc-address-assist.js';
        $path     = defined( 'UFSC_CL_DIR' ) ? UFSC_CL_DIR . $relative : '';
        $url      = defined( 'UFSC_CL_URL' ) ? UFSC_CL_URL . $relative : '';
        $version  = $path && file_exists( $path ) ? (string) filemtime( $path ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : '1.0' );

        if ( ! $url ) {
            return;
        }

        wp_enqueue_script( 'ufsc-address-assist', $url, array(), $version, true );
        wp_localize_script(
            'ufsc-address-assist',
            'UFSCAddressAssist',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'ufsc_address_assist' ),
                'countries'        => ufsc_address_assist_get_countries(),
                'defaultCountry'   => 'France',
                'lookupDelay'      => 350,
                'strings'          => array(
                    'chooseCity'      => __( 'Choisir une ville', 'ufsc-clubs' ),
                    'citySuggestion'  => __( 'Ville proposée à partir du code postal', 'ufsc-clubs' ),
                    'manualFallback'  => __( 'Vous pouvez toujours saisir la ville manuellement.', 'ufsc-clubs' ),
                    'lookupFailed'    => __( 'Suggestion indisponible. La saisie manuelle reste possible.', 'ufsc-clubs' ),
                    'countryLabel'    => __( 'Pays', 'ufsc-clubs' ),
                ),
            )
        );
    }
}

if ( ! function_exists( 'ufsc_address_assist_enqueue_admin' ) ) {
    function ufsc_address_assist_enqueue_admin() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( '' === $page || 0 !== strpos( $page, 'ufsc' ) ) {
            return;
        }

        ufsc_address_assist_enqueue();
    }
}
add_action( 'admin_enqueue_scripts', 'ufsc_address_assist_enqueue_admin', 120 );

if ( ! function_exists( 'ufsc_address_assist_lookup_postal_code' ) ) {
    /**
     * Return French communes for a postal code.
     *
     * Results are cached locally for 30 days. Failures are cached briefly so a
     * remote outage never slows repeated form entry.
     */
    function ufsc_address_assist_lookup_postal_code() {
        check_ajax_referer( 'ufsc_address_assist', 'nonce' );

        $postal_code = isset( $_POST['postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['postal_code'] ) ) : '';
        if ( ! preg_match( '/^\d{5}$/', $postal_code ) ) {
            wp_send_json_error( array( 'message' => __( 'Code postal invalide.', 'ufsc-clubs' ) ), 400 );
        }

        $cache_key = 'ufsc_addr_cp_' . $postal_code;
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            wp_send_json_success( array( 'cities' => $cached, 'cached' => true ) );
        }

        $failure_key = 'ufsc_addr_cp_fail_' . $postal_code;
        if ( get_transient( $failure_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Suggestion temporairement indisponible.', 'ufsc-clubs' ) ), 503 );
        }

        $url = add_query_arg(
            array(
                'codePostal' => $postal_code,
                'fields'     => 'nom,codesPostaux',
                'format'     => 'json',
            ),
            'https://geo.api.gouv.fr/communes'
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 2.5,
                'redirection' => 2,
                'headers'     => array( 'Accept' => 'application/json' ),
            )
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            set_transient( $failure_key, '1', 15 * MINUTE_IN_SECONDS );
            wp_send_json_error( array( 'message' => __( 'Suggestion temporairement indisponible.', 'ufsc-clubs' ) ), 503 );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) ) {
            set_transient( $failure_key, '1', 15 * MINUTE_IN_SECONDS );
            wp_send_json_error( array( 'message' => __( 'Réponse de suggestion invalide.', 'ufsc-clubs' ) ), 502 );
        }

        $cities = array();
        foreach ( $body as $row ) {
            $name = isset( $row['nom'] ) ? sanitize_text_field( (string) $row['nom'] ) : '';
            if ( '' !== $name ) {
                $cities[] = $name;
            }
        }

        $cities = array_values( array_unique( $cities ) );
        sort( $cities, SORT_NATURAL | SORT_FLAG_CASE );

        set_transient( $cache_key, $cities, 30 * DAY_IN_SECONDS );
        delete_transient( $failure_key );

        wp_send_json_success( array( 'cities' => $cities, 'cached' => false ) );
    }
}
add_action( 'wp_ajax_ufsc_address_lookup_postal_code', 'ufsc_address_assist_lookup_postal_code' );
add_action( 'wp_ajax_nopriv_ufsc_address_lookup_postal_code', 'ufsc_address_assist_lookup_postal_code' );
