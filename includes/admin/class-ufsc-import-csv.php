<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_Import_CSV {
    /**
     * Process a CSV file import.
     *
     * @param string $file Path to CSV file.
     * @param string $mode Import mode: dry-run|upsert|insert_only|update_only
     * @return array Report
     */
    public static function process( $file, $mode = 'dry-run' ) {
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $table = $s['table_licences'];
        $report = array(
            'inserted' => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'errors'   => array(),
        );
        if ( ! file_exists( $file ) ) {
            $report['errors'][] = 'File not found';
            return $report;
        }
        if ( ( $handle = fopen( $file, 'r' ) ) === false ) {
            $report['errors'][] = 'Cannot open file';
            return $report;
        }
        $headers = fgetcsv( $handle );
        if ( ! $headers ) {
            fclose( $handle );
            $report['errors'][] = 'Empty file';
            return $report;
        }
        while ( ( $data = fgetcsv( $handle ) ) !== false ) {
            $row = array();
            foreach ( $headers as $i => $h ) {
                $row[ $h ] = $data[ $i ] ?? '';
            }
            if ( isset( $row['statut'] ) ) {
                $row['statut'] = self::normalize_status( $row['statut'] );
            }
            if ( isset( $row['paid'] ) ) {
                $row['paid'] = ! empty( $row['paid'] ) && $row['paid'] != '0' ? 1 : 0;
            }
            $existing_id = null;
            if ( ! empty( $row['id'] ) ) {
                $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id=%d", $row['id'] ) );
            } elseif ( isset( $row['club_id'], $row['holder_name'], $row['birthdate'] ) ) {
                $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE club_id=%d AND holder_name=%s AND birthdate=%s", $row['club_id'], $row['holder_name'], $row['birthdate'] ) );
            }
            if ( $existing_id ) {
                if ( 'insert_only' === $mode ) {
                    $report['skipped']++;
                    continue;
                }
                if ( 'dry-run' !== $mode ) {
                    $wpdb->update( $table, $row, array( 'id' => $existing_id ) );
                }
                $report['updated']++;
            } else {
                if ( 'update_only' === $mode ) {
                    $report['skipped']++;
                    continue;
                }
                if ( 'dry-run' !== $mode ) {
                    $wpdb->insert( $table, $row );
                }
                $report['inserted']++;
            }
        }
        fclose( $handle );
        return $report;
    }

    private static function normalize_status( $status ) {
        $status = strtolower( trim( $status ) );
        $map = array(
            'valide'    => 'valide',
            'validée'   => 'valide',
            'active'    => 'valide',
            'en_attente'=> 'en_attente',
            'attente'   => 'en_attente',
            'pending'   => 'en_attente',
            'a_regler'  => 'a_regler',
            'refuse'    => 'refuse',
            'rejected'  => 'refuse',
            'desactive' => 'desactive',
            'inactive'  => 'desactive',
            'off'       => 'desactive',
        );
        return $map[ $status ] ?? $status;
    }
}
