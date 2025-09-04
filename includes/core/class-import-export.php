<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Import/Export utilities for UFSC
 * Handles CSV and Excel file operations
 */
class UFSC_Import_Export {

    /**
     * Export licences to CSV
     *
     * @param int   $club_id Club ID
     * @param array $filters Optional filters (status, season, ids)
     * @return array Result with success status and file info
     */
    public static function export_licences_csv( $club_id, $filters = array() ) {
        $licences = static::get_club_licences_for_export( $club_id, $filters );

        if ( empty( $licences ) ) {
            return array(
                'success' => false,
                'message' => __( 'Aucune licence à exporter.', 'ufsc-clubs' ),
            );
        }

        // Create CSV content
        $csv_data = array();

        // Header row
        $headers = array(
            __( 'ID', 'ufsc-clubs' ),
            __( 'Nom', 'ufsc-clubs' ),
            __( 'Prénom', 'ufsc-clubs' ),
            __( 'Email', 'ufsc-clubs' ),
            __( 'Téléphone', 'ufsc-clubs' ),
            __( 'Date de naissance', 'ufsc-clubs' ),
            __( 'Sexe', 'ufsc-clubs' ),
            __( 'Adresse', 'ufsc-clubs' ),
            __( 'Ville', 'ufsc-clubs' ),
            __( 'Code postal', 'ufsc-clubs' ),
            __( 'Statut', 'ufsc-clubs' ),
            __( 'Date création', 'ufsc-clubs' ),
            __( 'Date validation', 'ufsc-clubs' ),
        );
        $csv_data[] = $headers;

        // Data rows
        foreach ( $licences as $licence ) {
            $csv_data[] = array(
                $licence['id'] ?? '',
                $licence['nom'] ?? '',
                $licence['prenom'] ?? '',
                $licence['email'] ?? '',
                $licence['telephone'] ?? '',
                $licence['date_naissance'] ?? '',
                $licence['sexe'] ?? '',
                $licence['adresse'] ?? '',
                $licence['ville'] ?? '',
                $licence['code_postal'] ?? '',
                $licence['statut'] ?? '',
                $licence['date_creation'] ?? '',
                $licence['date_validation'] ?? '',
            );
        }

        // Generate filename
        $club_name = static::get_club_name( $club_id );
        $date      = date( 'Y-m-d_H-i-s' );
        $filename  = sanitize_file_name( "licences_{$club_name}_{$date}.csv" );

        // Create file
        $upload_dir = wp_upload_dir();
        $file_path  = $upload_dir['path'] . '/' . $filename;

        $file_handle = fopen( $file_path, 'w' );
        if ( ! $file_handle ) {
            return array(
                'success' => false,
                'message' => __( 'Impossible de créer le fichier CSV.', 'ufsc-clubs' ),
            );
        }

        // Write BOM for UTF-8
        fwrite( $file_handle, "\xEF\xBB\xBF" );

        // Write CSV data
        foreach ( $csv_data as $row ) {
            fputcsv( $file_handle, $row, ',', '"' );
        }

        fclose( $file_handle );

        // Log export
        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log( 'csv_export', array(
                'club_id'      => $club_id,
                'filename'     => $filename,
                'record_count' => count( $licences ),
            ) );
        }

        return array(
            'success'      => true,
            'filename'     => $filename,
            'file_path'    => $file_path,
            'file_url'     => $upload_dir['url'] . '/' . $filename,
            'record_count' => count( $licences ),
        );
    }

    /**
     * Export licences to Excel (XLSX)
     *
     * @param int   $club_id Club ID
     * @param array $filters Optional filters (status, season, ids)
     * @return array Result with success status and file info
     */
    public static function export_licences_xlsx( $club_id, $filters = array() ) {
        // Check if PhpSpreadsheet is available
        if ( ! class_exists( 'PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            // Fall back to CSV
            $csv_result = static::export_licences_csv( $club_id, $filters );
            if ( $csv_result['success'] ) {
                $csv_result['message'] = __( 'PhpSpreadsheet non disponible. Export CSV généré à la place.', 'ufsc-clubs' );
            }
            return $csv_result;
        }

        $licences = static::get_club_licences_for_export( $club_id, $filters );
        if ( empty( $licences ) ) {
            return array(
                'success' => false,
                'message' => __( 'Aucune licence à exporter.', 'ufsc-clubs' ),
            );
        }

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle( __( 'Licences', 'ufsc-clubs' ) );

            // Headers
            $headers = array(
                'A1' => __( 'ID', 'ufsc-clubs' ),
                'B1' => __( 'Nom', 'ufsc-clubs' ),
                'C1' => __( 'Prénom', 'ufsc-clubs' ),
                'D1' => __( 'Email', 'ufsc-clubs' ),
                'E1' => __( 'Téléphone', 'ufsc-clubs' ),
                'F1' => __( 'Date de naissance', 'ufsc-clubs' ),
                'G1' => __( 'Sexe', 'ufsc-clubs' ),
                'H1' => __( 'Adresse', 'ufsc-clubs' ),
                'I1' => __( 'Ville', 'ufsc-clubs' ),
                'J1' => __( 'Code postal', 'ufsc-clubs' ),
                'K1' => __( 'Statut', 'ufsc-clubs' ),
                'L1' => __( 'Date création', 'ufsc-clubs' ),
                'M1' => __( 'Date validation', 'ufsc-clubs' ),
            );
            foreach ( $headers as $cell => $value ) {
                $sheet->setCellValue( $cell, $value );
            }

            // Style headers
            $headerStyle = array(
                'font' => array( 'bold' => true ),
                'fill' => array(
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => array( 'rgb' => 'E9ECEF' ),
                ),
            );
            $sheet->getStyle( 'A1:M1' )->applyFromArray( $headerStyle );

            // Data
            $row = 2;
            foreach ( $licences as $licence ) {
                $sheet->setCellValue( "A{$row}", $licence['id'] ?? '' );
                $sheet->setCellValue( "B{$row}", $licence['nom'] ?? '' );
                $sheet->setCellValue( "C{$row}", $licence['prenom'] ?? '' );
                $sheet->setCellValue( "D{$row}", $licence['email'] ?? '' );
                $sheet->setCellValue( "E{$row}", $licence['telephone'] ?? '' );
                $sheet->setCellValue( "F{$row}", $licence['date_naissance'] ?? '' );
                $sheet->setCellValue( "G{$row}", $licence['sexe'] ?? '' );
                $sheet->setCellValue( "H{$row}", $licence['adresse'] ?? '' );
                $sheet->setCellValue( "I{$row}", $licence['ville'] ?? '' );
                $sheet->setCellValue( "J{$row}", $licence['code_postal'] ?? '' );
                $sheet->setCellValue( "K{$row}", $licence['statut'] ?? '' );
                $sheet->setCellValue( "L{$row}", $licence['date_creation'] ?? '' );
                $sheet->setCellValue( "M{$row}", $licence['date_validation'] ?? '' );
                $row++;
            }

            // Auto-size columns
            foreach ( range( 'A', 'M' ) as $column ) {
                $sheet->getColumnDimension( $column )->setAutoSize( true );
            }

            // Generate filename
            $club_name = static::get_club_name( $club_id );
            $date      = date( 'Y-m-d_H-i-s' );
            $filename  = sanitize_file_name( "licences_{$club_name}_{$date}.xlsx" );

            // Save file
            $upload_dir = wp_upload_dir();
            $file_path  = $upload_dir['path'] . '/' . $filename;

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
            $writer->save( $file_path );

            // Log export
            if ( function_exists( 'ufsc_audit_log' ) ) {
                ufsc_audit_log( 'xlsx_export', array(
                    'club_id'      => $club_id,
                    'filename'     => $filename,
                    'record_count' => count( $licences ),
                ) );
            }

            return array(
                'success'      => true,
                'filename'     => $filename,
                'file_path'    => $file_path,
                'file_url'     => $upload_dir['url'] . '/' . $filename,
                'record_count' => count( $licences ),
            );
        } catch ( \Exception $e ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Erreur lors de la création du fichier Excel: %s', 'ufsc-clubs' ), $e->getMessage() ),
            );
        }
    }

    /**
     * Parse CSV file for import preview
     *
     * @param array $file Uploaded file array
     * @return array Result with parsed data and validation errors
     */
    public static function parse_csv_for_import( $file ) {
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Fichier invalide.', 'ufsc-clubs' ),
            );
        }

        // Validate file type
        $file_info = pathinfo( $file['name'] );
        if ( strtolower( $file_info['extension'] ?? '' ) !== 'csv' ) {
            return array(
                'success' => false,
                'message' => __( 'Seuls les fichiers CSV sont acceptés.', 'ufsc-clubs' ),
            );
        }

        // Read CSV file
        $handle = fopen( $file['tmp_name'], 'r' );
        if ( ! $handle ) {
            return array(
                'success' => false,
                'message' => __( 'Impossible de lire le fichier.', 'ufsc-clubs' ),
            );
        }

        // Skip BOM if present
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        $data         = array();
        $errors       = array();
        $line_number  = 0;
        $headers      = null;

        while ( ( $row = fgetcsv( $handle, 0, ',', '"' ) ) !== false ) {
            $line_number++;

            if ( $line_number === 1 ) {
                // First line should be headers
                $headers = array_map( 'strtolower', array_map( 'trim', $row ) );

                // Validate required headers
                $required_headers = array( 'nom', 'prenom', 'email' );
                $missing_headers  = array_diff( $required_headers, $headers );

                if ( ! empty( $missing_headers ) ) {
                    $errors[] = sprintf(
                        __( 'En-têtes manquants: %s', 'ufsc-clubs' ),
                        implode( ', ', $missing_headers )
                    );
                    break;
                }

                continue;
            }

            // Map row by headers
            $row_data  = array();
            $row_errors = array();

            foreach ( $headers as $index => $header ) {
                $value = isset( $row[ $index ] ) ? trim( $row[ $index ] ) : '';
                $row_data[ $header ] = $value;
            }

            // Validate required fields
            if ( empty( $row_data['nom'] ) ) {
                $row_errors[] = __( 'Nom requis', 'ufsc-clubs' );
            }
            if ( empty( $row_data['prenom'] ) ) {
                $row_errors[] = __( 'Prénom requis', 'ufsc-clubs' );
            }
            if ( empty( $row_data['email'] ) ) {
                $row_errors[] = __( 'Email requis', 'ufsc-clubs' );
            } elseif ( ! is_email( $row_data['email'] ) ) {
                $row_errors[] = __( 'Email invalide', 'ufsc-clubs' );
            }

            // Validate date format if provided
            if ( ! empty( $row_data['date_naissance'] ) ) {
                $date = \DateTime::createFromFormat( 'Y-m-d', $row_data['date_naissance'] );
                if ( ! $date || $date->format( 'Y-m-d' ) !== $row_data['date_naissance'] ) {
                    $row_errors[] = __( 'Format de date invalide (YYYY-MM-DD attendu)', 'ufsc-clubs' );
                }
            }

            // Validate sex if provided
            if ( ! empty( $row_data['sexe'] ) && ! in_array( strtoupper( $row_data['sexe'] ), array( 'M', 'F' ), true ) ) {
                $row_errors[] = __( 'Sexe invalide (M ou F attendu)', 'ufsc-clubs' );
            }

            if ( ! empty( $row_errors ) ) {
                $errors[] = sprintf(
                    __( 'Ligne %d: %s', 'ufsc-clubs' ),
                    $line_number,
                    implode( ', ', $row_errors )
                );
            }

            $row_data['line_number'] = $line_number;
            $row_data['status']      = empty( $row_errors ) ? 'valid' : 'error';
            $data[] = $row_data;

            // Limit preview to first 50 rows
            if ( count( $data ) >= 50 ) {
                break;
            }
        }

        fclose( $handle );

        return array(
            'success'     => true,
            'data'        => $data,
            'errors'      => $errors,
            'total_rows'  => $line_number - 1, // Excluding header
            'valid_rows'  => count( array_filter( $data, function( $r ) { return $r['status'] === 'valid'; } ) ),
        );
    }

    /**
     * Import CSV data after validation
     *
     * @param array $data     Validated CSV data
     * @param int   $club_id  Club ID
     * @return array Result with import statistics
     */
    public static function import_csv_data( $data, $club_id ) {
        $imported            = 0;
        $errors              = array();
        $created_licence_ids = array();

        // Check quota
        $quota_info   = static::get_club_quota_info( $club_id );
        $needs_payment = false;

        foreach ( $data as $row ) {
            if ( ( $row['status'] ?? '' ) !== 'valid' ) {
                continue;
            }

            // Check if we've exceeded quota
            if ( $quota_info['remaining'] <= 0 && ! $needs_payment ) {
                $needs_payment = true;
            }

            // Create licence record
            $licence_data = array(
                'nom'            => sanitize_text_field( $row['nom'] ),
                'prenom'         => sanitize_text_field( $row['prenom'] ),
                'email'          => sanitize_email( $row['email'] ),
                'telephone'      => sanitize_text_field( $row['telephone'] ?? '' ),
                'date_naissance' => $row['date_naissance'] ?? '',
                'sexe'           => strtoupper( $row['sexe'] ?? '' ),
                'adresse'        => sanitize_textarea_field( $row['adresse'] ?? '' ),
                'ville'          => sanitize_text_field( $row['ville'] ?? '' ),
                'code_postal'    => sanitize_text_field( $row['code_postal'] ?? '' ),
                'statut'         => 'brouillon',
            );

            $licence_id = static::create_licence_record( $club_id, $licence_data );

            if ( $licence_id ) {
                $imported++;
                $created_licence_ids[] = $licence_id;
                $quota_info['remaining']--;

                if ( function_exists( 'ufsc_audit_log' ) ) {
                    ufsc_audit_log( 'licence_imported', array(
                        'licence_id' => $licence_id,
                        'club_id'    => $club_id,
                        'source'     => 'csv_import',
                    ) );
                }
            } else {
                $errors[] = sprintf(
                    __( 'Ligne %d: Échec de création de la licence', 'ufsc-clubs' ),
                    (int) ( $row['line_number'] ?? 0 )
                );
            }
        }

        // Create payment order if quota exceeded
        $payment_url = null;
        if ( $needs_payment && ! empty( $created_licence_ids ) ) {
            $order_id = static::create_payment_order( $club_id, $created_licence_ids );
            if ( $order_id && function_exists( 'wc_get_order' ) ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $payment_url = $order->get_checkout_payment_url();
                }
            }
        }

        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log( 'csv_import_completed', array(
                'club_id'          => $club_id,
                'imported_count'   => $imported,
                'error_count'      => count( $errors ),
                'payment_required' => $needs_payment,
            ) );
        }

        return array(
            'success'          => true,
            'imported'         => $imported,
            'errors'           => $errors,
            'payment_required' => $needs_payment,
            'payment_url'      => $payment_url,
            'licence_ids'      => $created_licence_ids,
        );
    }

    /* ---------------------------------------------------------------------
     * Helper methods
     * ------------------------------------------------------------------ */

    /**
     * Retrieve club licences for export.
     *
     * @param int   $club_id Club identifier.
     * @param array $filters Optional filters (status, season).
     * @return array List of licences.
     */
    protected static function get_club_licences_for_export( $club_id, $filters ) {
        global $wpdb;

        $settings       = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];

        $where  = array( 'club_id = %d' );
        $values = array( $club_id );

        if ( ! empty( $filters['status'] ) ) {
            $where[]  = 'statut = %s';
            $values[] = sanitize_text_field( $filters['status'] );
        }

        if ( ! empty( $filters['season'] ) ) {
            $season_col = function_exists( 'ufsc_get_mapped_column_if_exists' )
                ? ufsc_get_mapped_column_if_exists( $licences_table, 'season', 'licences' )
                : null;
            if ( $season_col ) {
                $where[]  = "`{$season_col}` = %s";
                $values[] = sanitize_text_field( $filters['season'] );
            }
        }

        if ( ! empty( $filters['ids'] ) && is_array( $filters['ids'] ) ) {
            $ids = array_filter( array_map( 'intval', $filters['ids'] ) );
            if ( ! empty( $ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                $where[]      = "id IN ({$placeholders})";
                $values       = array_merge( $values, $ids );
            }
        }

        $sql = "SELECT id, nom, prenom, email, telephone, date_naissance, sexe, adresse, ville, code_postal, statut, date_creation, date_validation
                FROM {$licences_table}
                WHERE " . implode( ' AND ', $where );

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

        return is_array( $results ) ? $results : array();
    }

    /**
     * Get the display name of a club.
     *
     * @param int $club_id Club ID.
     * @return string Club name or default string.
     */
    protected static function get_club_name( $club_id ) {
        global $wpdb;

        $settings    = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        $name_col    = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'name' ) : 'nom';

        $name = $wpdb->get_var( $wpdb->prepare(
            "SELECT {$name_col} FROM {$clubs_table} WHERE id = %d",
            $club_id
        ) );

        return $name ? $name : "Club_{$club_id}";
    }

    /**
     * Retrieve club licence quota information.
     *
     * @param int $club_id Club identifier.
     * @return array{total:int,used:int,remaining:int} Quota details.
     */
    protected static function get_club_quota_info( $club_id ) {
        global $wpdb;

        $settings       = UFSC_SQL::get_settings();
        $clubs_table    = $settings['table_clubs'];
        $licences_table = $settings['table_licences'];

        $quota_total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT quota_licences FROM {$clubs_table} WHERE id = %d",
            $club_id
        ) );

        $used = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$licences_table} WHERE club_id = %d",
            $club_id
        ) );

        return array(
            'total'     => $quota_total,
            'used'      => $used,
            'remaining' => max( 0, $quota_total - $used ),
        );
    }

    /**
     * Insert a licence record into the UFSC licences table.
     *
     * @param int   $club_id Club identifier.
     * @param array $data    Licence data.
     * @return int Inserted licence ID or 0 on failure.
     */
    protected static function create_licence_record( $club_id, $data ) {
        global $wpdb;

        $settings       = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];

        $insert_data = array_merge(
            $data,
            array(
                'club_id'       => $club_id,
                'date_creation' => current_time( 'mysql' ),
            )
        );

        $result = $wpdb->insert( $licences_table, $insert_data );

        if ( false === $result ) {
            UFSC_Audit_Logger::log( 'UFSC: Failed to insert licence - ' . $wpdb->last_error );
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Create a payment order for licences over quota.
     *
     * Delegates order creation to ufsc_create_additional_license_order for
     * consistent WooCommerce handling.
     *
     * @param int   $club_id     Club ID.
     * @param array $licence_ids Licence IDs to attach to the order.
     * @return int|false Order ID on success, false otherwise.
     */
    protected static function create_payment_order( $club_id, $licence_ids ) {
        if ( ! function_exists( 'ufsc_create_additional_license_order' ) ) {
            return false;
        }

        return ufsc_create_additional_license_order( $club_id, $licence_ids, get_current_user_id() );
    }
}

/**
 * Handle export requests
 */
function ufsc_handle_export_request() {
    if ( ! isset( $_GET['ufsc_export'] ) || ! is_user_logged_in() ) {
        return;
    }

    $format  = sanitize_text_field( wp_unslash( $_GET['ufsc_export'] ) );
    $user_id = get_current_user_id();
    $club_id = function_exists( 'ufsc_get_user_club_id' ) ? ufsc_get_user_club_id( $user_id ) : 0;

    if ( ! $club_id ) {
        wp_die( __( 'Aucun club associé à votre compte.', 'ufsc-clubs' ) );
    }

    // Verify nonce if present
    if ( isset( $_GET['_wpnonce'] ) && ! wp_verify_nonce( $_GET['_wpnonce'], 'ufsc_export' ) ) {
        wp_die( __( 'Vérification de sécurité échouée.', 'ufsc-clubs' ) );
    }

    $filters = array(
        'season' => isset( $_GET['season'] ) ? sanitize_text_field( wp_unslash( $_GET['season'] ) ) : null,
        'status' => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : null,
        'ids'    => isset( $_REQUEST['ids'] ) ? array_map( 'intval', (array) $_REQUEST['ids'] ) : array(),
    );

    switch ( $format ) {
        case 'csv':
            $result = UFSC_Import_Export::export_licences_csv( $club_id, $filters );
            break;
        case 'xlsx':
            $result = UFSC_Import_Export::export_licences_xlsx( $club_id, $filters );
            break;
        default:
            wp_die( __( 'Format d\'export non supporté.', 'ufsc-clubs' ) );
    }

    if ( ! empty( $result['success'] ) ) {
        if ( file_exists( $result['file_path'] ) ) {
            // Force download
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Disposition: attachment; filename="' . $result['filename'] . '"' );
            header( 'Content-Length: ' . filesize( $result['file_path'] ) );
            readfile( $result['file_path'] );
            // Clean up file after download
            @unlink( $result['file_path'] );
        }
        exit;
    }

    wp_die( esc_html( $result['message'] ?? __( 'Erreur inconnue lors de l’export.', 'ufsc-clubs' ) ) );
}
add_action( 'init', 'ufsc_handle_export_request' );
