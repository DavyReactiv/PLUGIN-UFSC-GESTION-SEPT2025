<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ufsc_get_club_profile_value' ) ) {
    /** Read club profile values with historical aliases, without database writes. */
    function ufsc_get_club_profile_value( $club, $field ) {
        $aliases = array(
            'name' => array( 'nom', 'name', 'club_name' ),
            'region' => array( 'region', 'region_club' ),
            'address' => array( 'adresse', 'adresse_siege', 'adresse_postale', 'address' ),
            'postal_code' => array( 'code_postal', 'cp', 'postal_code' ),
            'city' => array( 'ville', 'city' ),
            'phone' => array( 'telephone', 'tel', 'phone', 'tel_mobile', 'telephone_contact' ),
            'email' => array( 'email', 'email_contact', 'contact_email' ),
            'website' => array( 'url_site', 'site_web', 'website', 'site_internet' ),
            'affiliation_number' => array( 'num_affiliation', 'numero_affiliation', 'num_asptt' ),
            'logo' => array( 'profile_photo_url', 'logo_url', 'logo' ),
        );
        foreach ( $aliases[ $field ] ?? array( $field ) as $key ) {
            if ( is_object( $club ) && isset( $club->$key ) && '' !== trim( (string) $club->$key ) ) { return $club->$key; }
            if ( is_array( $club ) && isset( $club[ $key ] ) && '' !== trim( (string) $club[ $key ] ) ) { return $club[ $key ]; }
        }
        return '';
    }
}
