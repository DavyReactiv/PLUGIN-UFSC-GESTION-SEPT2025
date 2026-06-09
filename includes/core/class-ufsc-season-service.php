<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Centralized UFSC sport season service (01/08 -> 31/07).
 *
 * This service is read-only by default: it computes season labels and dates
 * without changing existing affiliation/licence data.
 */
class UFSC_Season_Service {
    const SEASON_START_MONTH = 8;
    const SEASON_START_DAY   = 1;
    const SEASON_END_MONTH   = 7;
    const SEASON_END_DAY     = 31;

    /**
     * Get the current season label, preferring a valid configured option.
     *
     * @return string Season label, e.g. 2025-2026.
     */
    public static function get_current_season() {
        $stored = get_option( 'ufsc_current_season', '' );
        $stored = is_string( $stored ) ? sanitize_text_field( $stored ) : '';
        if ( self::is_valid_season( $stored ) ) {
            return $stored;
        }

        return self::get_season_from_date( current_time( 'Y-m-d' ) );
    }

    /**
     * @return string
     */
    public static function get_next_season() {
        return self::shift_season( self::get_current_season(), 1 );
    }

    /**
     * @return string
     */
    public static function get_previous_season() {
        return self::shift_season( self::get_current_season(), -1 );
    }

    /**
     * @param string $season Season label.
     * @return string Date as Y-m-d.
     */
    public static function get_season_start_date( $season ) {
        $parts = self::parse_season( $season );
        if ( ! $parts ) {
            return '';
        }

        return sprintf( '%04d-%02d-%02d', $parts[0], self::SEASON_START_MONTH, self::SEASON_START_DAY );
    }

    /**
     * @param string $season Season label.
     * @return string Date as Y-m-d.
     */
    public static function get_season_end_date( $season ) {
        $parts = self::parse_season( $season );
        if ( ! $parts ) {
            return '';
        }

        return sprintf( '%04d-%02d-%02d', $parts[1], self::SEASON_END_MONTH, self::SEASON_END_DAY );
    }

    /**
     * @param string $season Season label.
     * @return bool
     */
    public static function is_current_season( $season ) {
        return self::normalize_season( $season ) === self::get_current_season();
    }

    /**
     * @param string $season Season label.
     * @return bool
     */
    public static function is_season_expired( $season ) {
        $end = self::get_season_end_date( $season );
        if ( '' === $end ) {
            return false;
        }

        return strtotime( $end . ' 23:59:59' ) < current_time( 'timestamp' );
    }

    /**
     * Resolve the UFSC sport season for a date. Season changes on August 1st.
     *
     * @param string|int $date Date string or timestamp.
     * @return string Season label.
     */
    public static function get_season_from_date( $date ) {
        if ( is_numeric( $date ) ) {
            $timestamp = absint( $date );
        } else {
            $timestamp = strtotime( (string) $date );
        }

        if ( ! $timestamp ) {
            $timestamp = current_time( 'timestamp' );
        }

        $month      = (int) wp_date( 'n', $timestamp );
        $year       = (int) wp_date( 'Y', $timestamp );
        $start_year = ( $month >= self::SEASON_START_MONTH ) ? $year : ( $year - 1 );

        return sprintf( '%d-%d', $start_year, $start_year + 1 );
    }

    /**
     * @return string[]
     */
    public static function get_available_seasons() {
        $current  = self::get_current_season();
        $seasons  = array(
            self::shift_season( $current, -2 ),
            self::shift_season( $current, -1 ),
            $current,
            self::shift_season( $current, 1 ),
        );
        $filtered = array();
        foreach ( $seasons as $season ) {
            if ( self::is_valid_season( $season ) ) {
                $filtered[] = $season;
            }
        }

        return array_values( array_unique( $filtered ) );
    }

    /**
     * Compare two season labels chronologically.
     *
     * @param string $a First season label.
     * @param string $b Second season label.
     * @return int|null -1 if $a is before $b, 0 if equal, 1 if after, null if invalid.
     */
    public static function compare_seasons( $a, $b ) {
        $a_parts = self::parse_season( $a );
        $b_parts = self::parse_season( $b );
        if ( ! $a_parts || ! $b_parts ) {
            return null;
        }

        if ( $a_parts[0] === $b_parts[0] ) {
            return 0;
        }

        return ( $a_parts[0] < $b_parts[0] ) ? -1 : 1;
    }

    /**
     * @param string $season Season label.
     * @return string
     */
    public static function normalize_season( $season ) {
        $season = trim( str_replace( '/', '-', (string) $season ) );
        return self::is_valid_season( $season ) ? $season : '';
    }

    /**
     * @param string $season Season label.
     * @return bool
     */
    public static function is_valid_season( $season ) {
        return (bool) self::parse_season( $season );
    }

    /**
     * @param string $season Season label.
     * @param int    $offset Number of seasons to move.
     * @return string
     */
    private static function shift_season( $season, $offset ) {
        $parts = self::parse_season( $season );
        if ( ! $parts ) {
            return '';
        }

        $offset = (int) $offset;
        return sprintf( '%d-%d', $parts[0] + $offset, $parts[1] + $offset );
    }

    /**
     * @param string $season Season label.
     * @return array{0:int,1:int}|null
     */
    private static function parse_season( $season ) {
        $season = trim( str_replace( '/', '-', (string) $season ) );
        if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
            return null;
        }

        $start = (int) $matches[1];
        $end   = (int) $matches[2];
        if ( $end !== $start + 1 ) {
            return null;
        }

        return array( $start, $end );
    }
}
