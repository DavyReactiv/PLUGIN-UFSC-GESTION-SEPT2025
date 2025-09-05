<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Base helper for CSV exports.
 */
abstract class UFSC_Export_Base {
    /**
     * Output a CSV file from rows.
     *
     * @param string $filename CSV filename.
     * @param array  $headers  Header row.
     * @param array  $rows     Data rows.
     */
    protected static function output_csv( $filename, $headers, $rows ) {
        if ( ob_get_level() ) {
            ob_end_clean();
        }

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        echo "\xEF\xBB\xBF";

        $out = fopen( 'php://output', 'w' );

        if ( ! empty( $headers ) ) {
            fputcsv( $out, $headers );
        }

        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                fputcsv( $out, $row );
            }
        }

        fclose( $out );
        exit;
    }
}
