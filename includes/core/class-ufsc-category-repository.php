<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Centralized UFSC sport category repository and detector.
 */
class UFSC_Category_Repository {
    const DEFAULT_SEASON     = '2025/2026';
    const DEFAULT_DISCIPLINE = 'kickboxing_tatami_assaut';

    /**
     * Get age categories for a season and discipline.
     *
     * @param string $season Season label/key.
     * @param string $discipline Discipline label/key.
     * @return array<string,array<string,mixed>>
     */
    public static function get_age_categories( $season = self::DEFAULT_SEASON, $discipline = self::DEFAULT_DISCIPLINE ) {
        $referential = self::get_referential( $season, $discipline );
        return $referential ? $referential['age_categories'] : array();
    }

    /**
     * Get weight categories for age category and gender.
     *
     * @param string $season Season label/key.
     * @param string $discipline Discipline label/key.
     * @param string $age_category_key Age category key.
     * @param string $gender Raw gender.
     * @return array<int,array<string,mixed>>
     */
    public static function get_weight_categories( $season, $discipline, $age_category_key, $gender ) {
        $referential = self::get_referential( $season, $discipline );
        if ( ! $referential ) {
            return array();
        }

        $gender = self::normalize_gender( $gender );
        if ( '' === $gender ) {
            return array();
        }

        $age_category_key = sanitize_key( (string) $age_category_key );
        if ( empty( $referential['weight_categories'][ $age_category_key ][ $gender ] ) ) {
            return array();
        }

        return $referential['weight_categories'][ $age_category_key ][ $gender ];
    }

    /**
     * Detect official age category from birth year and gender.
     *
     * @param string $birthdate Birthdate.
     * @param string $gender Raw gender.
     * @param string $season Season label/key.
     * @return array<string,mixed>|null
     */
    public static function detect_age_category( $birthdate, $gender, $season = self::DEFAULT_SEASON ) {
        $birth_year = self::extract_birth_year( $birthdate );
        $gender     = self::normalize_gender( $gender );

        if ( ! $birth_year || '' === $gender ) {
            return null;
        }

        foreach ( self::get_age_categories( $season, self::DEFAULT_DISCIPLINE ) as $key => $category ) {
            $genders = isset( $category['genders'] ) ? (array) $category['genders'] : array();
            if ( ! in_array( $gender, $genders, true ) ) {
                continue;
            }

            $years = isset( $category['birth_years'] ) ? (array) $category['birth_years'] : array();
            $min   = isset( $years[0] ) ? (int) $years[0] : 0;
            $max   = isset( $years[1] ) ? (int) $years[1] : 0;
            if ( $min && $max && $birth_year >= $min && $birth_year <= $max ) {
                $category['key'] = $key;
                return $category;
            }
        }

        return null;
    }

    /**
     * Detect official weight category.
     *
     * @param string $birthdate Birthdate.
     * @param string $gender Raw gender.
     * @param mixed  $weight Raw weight.
     * @param string $discipline Discipline label/key.
     * @param string $season Season label/key.
     * @return array<string,mixed>|null
     */
    public static function detect_weight_category( $birthdate, $gender, $weight, $discipline = self::DEFAULT_DISCIPLINE, $season = self::DEFAULT_SEASON ) {
        $normalized_weight = self::normalize_weight( $weight );
        if ( null === $normalized_weight ) {
            return null;
        }

        $age_category = self::detect_age_category( $birthdate, $gender, $season );
        if ( ! $age_category || empty( $age_category['key'] ) ) {
            return null;
        }

        $weight_categories = self::get_weight_categories( $season, $discipline, $age_category['key'], $gender );
        foreach ( $weight_categories as $category ) {
            $limit = array_key_exists( 'max', $category ) ? $category['max'] : null;
            if ( null === $limit ) {
                return $category;
            }
            if ( $normalized_weight <= (float) $limit ) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Normalize a weight into kilograms.
     *
     * @param mixed $weight Raw weight.
     * @return float|null
     */
    public static function normalize_weight( $weight ) {
        if ( null === $weight || is_array( $weight ) || is_object( $weight ) ) {
            return null;
        }

        $value = trim( strtolower( (string) $weight ) );
        if ( '' === $value ) {
            return null;
        }

        $value = str_replace( array( ',', 'kg', 'kgs', 'kilogrammes', 'kilogramme' ), array( '.', '', '', '', '' ), $value );
        $value = trim( preg_replace( '/[^0-9.]/', '', $value ) );
        if ( '' === $value || ! is_numeric( $value ) ) {
            return null;
        }

        $weight = (float) $value;
        if ( $weight < 10 || $weight > 250 ) {
            return null;
        }

        return round( $weight, 2 );
    }

    /**
     * Normalize gender to M/F.
     *
     * @param mixed $gender Raw gender.
     * @return string
     */
    public static function normalize_gender( $gender ) {
        $gender = strtoupper( trim( function_exists( 'remove_accents' ) ? remove_accents( (string) $gender ) : (string) $gender ) );
        $male   = array( 'M', 'H', 'HOMME', 'MASCULIN', 'MALE', 'GARCON', 'GARCONS' );
        $female = array( 'F', 'FEMME', 'FEMININ', 'FEMALE', 'FILLE', 'FILLES' );

        if ( in_array( $gender, $male, true ) ) {
            return 'M';
        }
        if ( in_array( $gender, $female, true ) ) {
            return 'F';
        }

        return '';
    }

    /**
     * Build a full detection summary for displays/diagnostics.
     *
     * @param object|array $athlete Licence/athlete row.
     * @param string       $discipline Discipline label/key.
     * @param string       $season Season label/key.
     * @return array<string,mixed>
     */
    public static function detect_for_athlete( $athlete, $discipline = self::DEFAULT_DISCIPLINE, $season = self::DEFAULT_SEASON ) {
        $birthdate = self::get_value( $athlete, 'date_naissance' );
        $gender    = self::get_value( $athlete, 'sexe' );
        $weight    = self::get_first_value( $athlete, array( 'poids', 'weight' ) );

        $age_category    = self::detect_age_category( $birthdate, $gender, $season );
        $weight_category = self::detect_weight_category( $birthdate, $gender, $weight, $discipline, $season );
        $normalized      = self::normalize_weight( $weight );

        return array(
            'birthdate'             => $birthdate,
            'birth_year'            => self::extract_birth_year( $birthdate ),
            'gender'                => $gender,
            'normalized_gender'     => self::normalize_gender( $gender ),
            'weight'                => $weight,
            'normalized_weight'     => $normalized,
            'age_category'          => $age_category,
            'age_category_label'    => $age_category ? $age_category['label'] : '',
            'weight_category'       => $weight_category,
            'weight_category_label' => $weight_category ? $weight_category['label'] : '',
            'season'                => self::normalize_season( $season ),
            'discipline'            => self::normalize_discipline( $discipline ),
            'status'                => self::get_detection_status( $birthdate, $gender, $weight, $age_category, $weight_category ),
        );
    }

    /**
     * Get diagnostic data. Caller must restrict display to administrators.
     *
     * @param object|array $athlete Licence/athlete row.
     * @param string       $discipline Discipline label/key.
     * @param string       $season Season label/key.
     * @return array<string,mixed>
     */
    public static function get_detection_diagnostic( $athlete, $discipline = self::DEFAULT_DISCIPLINE, $season = self::DEFAULT_SEASON ) {
        return self::detect_for_athlete( $athlete, $discipline, $season );
    }

    /**
     * Normalize season labels.
     *
     * @param string $season Season label/key.
     * @return string
     */
    public static function normalize_season( $season ) {
        $season = trim( (string) $season );
        if ( '' === $season ) {
            return self::DEFAULT_SEASON;
        }
        $season = str_replace( '-', '/', $season );
        return '2025/2026' === $season ? self::DEFAULT_SEASON : $season;
    }

    /**
     * Normalize supported discipline labels.
     *
     * @param string $discipline Discipline label/key.
     * @return string
     */
    public static function normalize_discipline( $discipline ) {
        $discipline = strtolower( function_exists( 'remove_accents' ) ? remove_accents( trim( (string) $discipline ) ) : trim( (string) $discipline ) );
        $discipline = str_replace( array( '-', '/', ' ' ), '_', $discipline );

        $aliases = array(
            'kickboxing_tatami_assaut' => self::DEFAULT_DISCIPLINE,
            'kickboxing'               => self::DEFAULT_DISCIPLINE,
            'tatami'                   => self::DEFAULT_DISCIPLINE,
            'assaut'                   => self::DEFAULT_DISCIPLINE,
            'light_contact'            => self::DEFAULT_DISCIPLINE,
            'kick_light'               => self::DEFAULT_DISCIPLINE,
            'point_fighting'           => self::DEFAULT_DISCIPLINE,
            'k1_style_light'           => self::DEFAULT_DISCIPLINE,
        );

        return $aliases[ $discipline ] ?? $discipline;
    }

    private static function get_referential( $season, $discipline ) {
        $season     = self::normalize_season( $season );
        $discipline = self::normalize_discipline( $discipline );
        $data       = self::get_referentials();

        return $data[ $season ][ $discipline ] ?? null;
    }

    private static function get_referentials() {
        $age_categories = array(
            'pre_poussins'       => array( 'label' => 'Pré-poussins M/F', 'birth_years' => array( 2018, 2019 ), 'genders' => array( 'M', 'F' ) ),
            'poussins'           => array( 'label' => 'Poussins M/F', 'birth_years' => array( 2016, 2017 ), 'genders' => array( 'M', 'F' ) ),
            'benjamins'          => array( 'label' => 'Benjamins M/F', 'birth_years' => array( 2014, 2015 ), 'genders' => array( 'M', 'F' ) ),
            'minimes_filles'     => array( 'label' => 'Minimes filles', 'birth_years' => array( 2012, 2013 ), 'genders' => array( 'F' ) ),
            'minimes_garcons'    => array( 'label' => 'Minimes garçons', 'birth_years' => array( 2012, 2013 ), 'genders' => array( 'M' ) ),
            'cadettes'           => array( 'label' => 'Cadettes', 'birth_years' => array( 2010, 2011 ), 'genders' => array( 'F' ) ),
            'cadets'             => array( 'label' => 'Cadets', 'birth_years' => array( 2010, 2011 ), 'genders' => array( 'M' ) ),
            'juniors_filles'     => array( 'label' => 'Juniors filles', 'birth_years' => array( 2008, 2009 ), 'genders' => array( 'F' ) ),
            'juniors_garcons'    => array( 'label' => 'Juniors garçons', 'birth_years' => array( 2008, 2009 ), 'genders' => array( 'M' ) ),
            'seniors_feminines'  => array( 'label' => 'Seniors féminines', 'birth_years' => array( 1985, 2007 ), 'genders' => array( 'F' ) ),
            'veterans_feminines' => array( 'label' => 'Vétérans féminines', 'birth_years' => array( 1975, 1984 ), 'genders' => array( 'F' ) ),
            'seniors_masculins'  => array( 'label' => 'Seniors masculins', 'birth_years' => array( 1985, 2007 ), 'genders' => array( 'M' ) ),
            'veterans_masculins' => array( 'label' => 'Vétérans masculins', 'birth_years' => array( 1975, 1984 ), 'genders' => array( 'M' ) ),
        );

        $weights = array(
            'pre_poussins'       => self::same_weights_for_all( array( 18, 23, 28, 32, 37, 42, 47 ), 47 ),
            'poussins'           => self::same_weights_for_all( array( 18, 23, 28, 32, 37, 42, 47 ), 47 ),
            'benjamins'          => self::same_weights_for_all( array( 23, 28, 32, 37, 42, 47, 52 ), 52 ),
            'minimes_filles'     => array( 'F' => self::build_weight_categories( array( 28, 32, 37, 42, 46, 50, 55, 60 ), 60 ) ),
            'minimes_garcons'    => array( 'M' => self::build_weight_categories( array( 28, 32, 37, 42, 47, 52, 57, 63, 69 ), 69 ) ),
            'cadettes'           => array( 'F' => self::build_weight_categories( array( 37, 42, 46, 50, 55, 60, 65 ), 65 ) ),
            'cadets'             => array( 'M' => self::build_weight_categories( array( 37, 42, 47, 52, 57, 63, 69, 74 ), 74 ) ),
            'juniors_filles'     => array( 'F' => self::build_weight_categories( array( 42, 46, 50, 55, 60, 65, 70 ), 70 ) ),
            'juniors_garcons'    => array( 'M' => self::build_weight_categories( array( 47, 52, 57, 63, 69, 74, 79, 84, 89, 94 ), 94 ) ),
            'seniors_feminines'  => array( 'F' => self::build_weight_categories( array( 50, 55, 60, 65, 70 ), 70 ) ),
            'veterans_feminines' => array( 'F' => self::build_weight_categories( array( 50, 55, 60, 65, 70 ), 70 ) ),
            'seniors_masculins'  => array( 'M' => self::build_weight_categories( array( 57, 63, 69, 74, 79, 84, 89, 94 ), 94 ) ),
            'veterans_masculins' => array( 'M' => self::build_weight_categories( array( 57, 63, 69, 74, 79, 84, 89, 94 ), 94 ) ),
        );

        return array(
            self::DEFAULT_SEASON => array(
                self::DEFAULT_DISCIPLINE => array(
                    'season'             => self::DEFAULT_SEASON,
                    'discipline'         => self::DEFAULT_DISCIPLINE,
                    'discipline_label'   => 'Kickboxing / Tatami / Assaut',
                    'sub_disciplines'    => array( 'Light Contact', 'Kick Light', 'Point Fighting', 'K1 Style Light' ),
                    'age_categories'     => $age_categories,
                    'weight_categories'  => $weights,
                ),
            ),
        );
    }

    private static function same_weights_for_all( $limits, $plus_limit ) {
        $categories = self::build_weight_categories( $limits, $plus_limit );
        return array( 'M' => $categories, 'F' => $categories );
    }

    private static function build_weight_categories( $limits, $plus_limit ) {
        $categories = array();
        foreach ( $limits as $limit ) {
            $categories[] = array(
                'label' => '-' . $limit . ' kg',
                'max'   => (float) $limit,
            );
        }
        $categories[] = array(
            'label' => '+' . $plus_limit . ' kg',
            'max'   => null,
        );

        return $categories;
    }

    private static function extract_birth_year( $birthdate ) {
        $birthdate = trim( (string) $birthdate );
        if ( '' === $birthdate || '0000-00-00' === $birthdate || '0000-00-00 00:00:00' === $birthdate ) {
            return 0;
        }

        if ( preg_match( '/^(\d{4})[-\/]\d{2}[-\/]\d{2}/', $birthdate, $matches ) ) {
            return (int) $matches[1];
        }
        if ( preg_match( '/^\d{2}\/\d{2}\/(\d{4})$/', $birthdate, $matches ) ) {
            return (int) $matches[1];
        }

        return 0;
    }

    private static function get_detection_status( $birthdate, $gender, $weight, $age_category, $weight_category ) {
        if ( ! self::extract_birth_year( $birthdate ) ) {
            return 'invalid_birthdate';
        }
        if ( '' === self::normalize_gender( $gender ) ) {
            return 'invalid_gender';
        }
        if ( ! $age_category ) {
            return 'age_not_found';
        }
        if ( null === self::normalize_weight( $weight ) ) {
            return 'missing_weight';
        }
        if ( ! $weight_category ) {
            return 'weight_not_found';
        }

        return 'ok';
    }

    private static function get_first_value( $row, $fields ) {
        foreach ( $fields as $field ) {
            $value = self::get_value( $row, $field );
            if ( '' !== trim( (string) $value ) ) {
                return $value;
            }
        }

        return '';
    }

    private static function get_value( $row, $field ) {
        if ( is_array( $row ) ) {
            return $row[ $field ] ?? '';
        }
        if ( is_object( $row ) ) {
            return $row->{$field} ?? '';
        }

        return '';
    }
}
