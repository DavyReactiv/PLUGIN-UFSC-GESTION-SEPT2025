<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Utilities Class
 * Common utility functions for UFSC Gestion
 */
class UFSC_Utils {

    /**
     * Derive season from a date
     * 
     * @param string|DateTime $date Date to process
     * @param int $pivot_month Pivot month (default: 9 for September)
     * @return string Season in YYYY-YYYY+1 format
     */
    public static function derive_season( $date = null, $pivot_month = 9 ) {
        if ( is_null( $date ) ) {
            $date = current_time( 'mysql' );
        }

        if ( is_string( $date ) ) {
            $date = new DateTime( $date );
        }

        if ( ! $date instanceof DateTime ) {
            return '';
        }

        $year = (int) $date->format( 'Y' );
        $month = (int) $date->format( 'n' );

        // If month is before pivot (September), we're in the previous season
        if ( $month < $pivot_month ) {
            $start_year = $year - 1;
        } else {
            $start_year = $year;
        }

        $end_year = $start_year + 1;

        return sprintf( '%d-%d', $start_year, $end_year );
    }

    /**
     * Get current season
     * 
     * @param int $pivot_month Pivot month (default: 9 for September)
     * @return string Current season in YYYY-YYYY+1 format
     */
    public static function get_current_season( $pivot_month = 9 ) {
        return self::derive_season( null, $pivot_month );
    }

    /**
     * Validate season format
     * 
     * @param string $season Season string to validate
     * @return bool True if valid season format
     */
    public static function is_valid_season( $season ) {
        return preg_match( '/^\d{4}-\d{4}$/', $season ) === 1;
    }

    /**
     * Get seasons between two dates
     * 
     * @param string|DateTime $start_date Start date
     * @param string|DateTime $end_date End date
     * @param int $pivot_month Pivot month
     * @return array Array of seasons
     */
    public static function get_seasons_between( $start_date, $end_date, $pivot_month = 9 ) {
        $start_season = self::derive_season( $start_date, $pivot_month );
        $end_season = self::derive_season( $end_date, $pivot_month );

        if ( ! self::is_valid_season( $start_season ) || ! self::is_valid_season( $end_season ) ) {
            return array();
        }

        $seasons = array();
        $start_year = (int) substr( $start_season, 0, 4 );
        $end_year = (int) substr( $end_season, 0, 4 );

        for ( $year = $start_year; $year <= $end_year; $year++ ) {
            $seasons[] = sprintf( '%d-%d', $year, $year + 1 );
        }

        return $seasons;
    }

    /**
     * Format season for display
     * 
     * @param string $season Season in YYYY-YYYY format
     * @return string Formatted season for display
     */
    public static function format_season( $season ) {
        if ( ! self::is_valid_season( $season ) ) {
            return $season;
        }

        return sprintf( __( 'Saison %s', 'ufsc-clubs' ), $season );
    }
}