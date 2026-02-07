<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Scope helper for region-based access control.
 */
class UFSC_Scope {
    const USER_META_KEY = 'ufsc_scope_region';

    /**
     * Get mapping of region slugs to labels.
     *
     * @return array
     */
    public static function get_regions_map() {
        $regions = array(
            'auvergne-rhone-alpes'      => 'Auvergne-Rhône-Alpes UFSC',
            'bourgogne-franche-comte'  => 'Bourgogne-Franche-Comté UFSC',
            'bretagne'                  => 'Bretagne UFSC',
            'centre-val-de-loire'       => 'Centre-Val de Loire UFSC',
            'corse'                     => 'Corse UFSC',
            'grand-est'                 => 'Grand Est UFSC',
            'hauts-de-france'           => 'Hauts-de-France UFSC',
            'ile-de-france'             => 'Île-de-France UFSC',
            'normandie'                 => 'Normandie UFSC',
            'nouvelle-aquitaine'        => 'Nouvelle-Aquitaine UFSC',
            'occitanie'                 => 'Occitanie UFSC',
            'pays-de-la-loire'          => 'Pays de la Loire UFSC',
            'provence-alpes-cote-dazur' => "Provence-Alpes-Côte d'Azur UFSC",
            'drom-com'                  => 'DROM-COM UFSC',
            'guadeloupe'                => 'Guadeloupe UFSC',
            'martinique'                => 'Martinique UFSC',
            'guyane'                    => 'Guyane UFSC',
            'reunion'                   => 'Réunion UFSC',
            'mayotte'                   => 'Mayotte UFSC',
        );

        /**
         * Filter the UFSC regions map (slug => label).
         *
         * @param array $regions
         */
        return apply_filters( 'ufsc_regions_map', $regions );
    }

    /**
     * Get scope region slug for user (or current user).
     *
     * @param int $user_id
     * @return string|null
     */
    public static function get_user_scope_region( $user_id = 0 ) {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if ( ! $user_id ) {
            return null;
        }

        if ( self::user_has_all_regions( $user_id ) ) {
            return null;
        }

        $value = get_user_meta( $user_id, self::USER_META_KEY, true );
        $value = sanitize_text_field( (string) $value );
        if ( $value === '' ) {
            return null;
        }

        $regions = self::get_regions_map();
        if ( ! isset( $regions[ $value ] ) ) {
            return null;
        }

        return $value;
    }

    /**
     * Check if user has access to all regions.
     *
     * @param int $user_id
     * @return bool
     */
    public static function user_has_all_regions( $user_id = 0 ) {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }

        if ( user_can( $user_id, UFSC_Capabilities::CAP_SCOPE_ALL_REGIONS ) ) {
            return true;
        }

        return user_can( $user_id, 'manage_options' );
    }

    /**
     * Get region label for slug.
     *
     * @param string $slug
     * @return string|null
     */
    public static function get_region_label( $slug ) {
        $slug = sanitize_text_field( (string) $slug );
        $regions = self::get_regions_map();
        return $regions[ $slug ] ?? null;
    }

    /**
     * Build scope SQL condition for region column.
     *
     * @param string $column
     * @param string $table_alias
     * @return string
     */
    public static function build_scope_condition( $column, $table_alias = '' ) {
        global $wpdb;

        $values = self::get_scope_region_values();
        if ( empty( $values ) ) {
            return '';
        }

        $column_ref = $table_alias ? "{$table_alias}.{$column}" : $column;
        $placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );

        return $wpdb->prepare( "{$column_ref} IN ({$placeholders})", $values );
    }

    /**
     * Get allowed region values for query.
     *
     * @return array
     */
    public static function get_scope_region_values() {
        $slug = self::get_user_scope_region();
        if ( ! $slug ) {
            return array();
        }

        $label = self::get_region_label( $slug );
        $values = array_filter( array( $slug, $label ) );
        $values = array_values( array_unique( $values ) );

        return $values;
    }

    /**
     * Enforce scope check against a region string.
     *
     * @param string|null $object_region
     * @return void
     */
    public static function assert_in_scope( $object_region ) {
        $scope_values = self::get_scope_region_values();
        if ( empty( $scope_values ) ) {
            return;
        }

        $object_region = sanitize_text_field( (string) $object_region );
        if ( $object_region === '' ) {
            ufsc_admin_debug_log( 'Scope check failed: missing object region.', array( 'scope' => $scope_values ) );
            wp_die( __( 'Accès refusé (hors région).', 'ufsc-clubs' ) );
        }

        if ( ! in_array( $object_region, $scope_values, true ) ) {
            ufsc_admin_debug_log( 'Scope check failed: region mismatch.', array( 'scope' => $scope_values, 'object' => $object_region ) );
            wp_die( __( 'Accès refusé (hors région).', 'ufsc-clubs' ) );
        }
    }

    /**
     * Check if a region is within the current scope.
     *
     * @param string|null $object_region
     * @return bool
     */
    public static function is_in_scope( $object_region ) {
        $scope_values = self::get_scope_region_values();
        if ( empty( $scope_values ) ) {
            return true;
        }

        $object_region = sanitize_text_field( (string) $object_region );
        if ( $object_region === '' ) {
            return false;
        }

        return in_array( $object_region, $scope_values, true );
    }

    /**
     * Assert scope for a club ID.
     *
     * @param int $club_id
     * @return void
     */
    public static function assert_club_in_scope( $club_id ) {
        $club_id = (int) $club_id;
        if ( ! $club_id ) {
            return;
        }

        $region = self::get_club_region( $club_id );
        self::assert_in_scope( $region );
    }

    /**
     * Get club region from database.
     *
     * @param int $club_id
     * @return string|null
     */
    public static function get_club_region( $club_id ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $pk = ufsc_club_col( 'id' );

        $region = $wpdb->get_var( $wpdb->prepare(
            "SELECT region FROM `{$table}` WHERE `{$pk}` = %d",
            $club_id
        ) );

        return $region ? (string) $region : null;
    }
}

if ( ! function_exists( 'ufsc_get_user_scope_region' ) ) {
    function ufsc_get_user_scope_region() {
        return UFSC_Scope::get_user_scope_region();
    }
}

if ( ! function_exists( 'ufsc_user_has_all_regions' ) ) {
    function ufsc_user_has_all_regions() {
        return UFSC_Scope::user_has_all_regions();
    }
}

if ( ! function_exists( 'ufsc_assert_in_scope' ) ) {
    function ufsc_assert_in_scope( $object_region ) {
        UFSC_Scope::assert_in_scope( $object_region );
    }
}
