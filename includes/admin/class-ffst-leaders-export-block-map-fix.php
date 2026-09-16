<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Correctif ciblé du mapping des blocs du bordereau FFST dirigeants.
 *
 * Le modèle officiel utilise 5 blocs de 8 lignes. Les lignes 16/24/32/40/48
 * sont les en-têtes des blocs ; les données principales doivent être écrites
 * aux lignes 19/27/35/43/51.
 *
 * Lecture seule : aucune donnée club/licence, aucun quota, panier ou paiement.
 */
final class UFSC_FFST_Leaders_Export_Block_Map_Fix {
    const ACTION = 'ufsc_ffst_generate_licences';

    public static function init() {
        remove_action( 'admin_post_' . self::ACTION, array( 'UFSC_FFST_Leaders_Export_Fix', 'handle_generate' ) );
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_generate' ) );
    }

    private static function call_private( $method, array $args = array() ) {
        $ref = new ReflectionMethod( 'UFSC_FFST_Leaders_Export_Fix', $method );
        return $ref->invokeArgs( null, $args );
    }

    public static function handle_generate() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ), '', array( 'response' => 403 ) );
        }

        $club_id = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
        $season  = isset( $_POST['season'] ) && ! is_array( $_POST['season'] ) ? sanitize_text_field( wp_unslash( $_POST['season'] ) ) : '';
        if ( ! $club_id || ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
            wp_die( esc_html__( 'Club ou saison FFST invalide.', 'ufsc-clubs' ), '', array( 'response' => 400 ) );
        }
        check_admin_referer( self::ACTION . '_' . $club_id . '_' . $season );

        if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\IOFactory' ) ) {
            wp_die( esc_html__( 'Le moteur Excel nécessaire à la génération FFST est indisponible.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }

        $club = self::call_private( 'get_club', array( $club_id ) );
        $template = self::call_private( 'resolve_template' );
        if ( ! $club || ! $template ) {
            wp_die( esc_html__( 'Club ou modèle officiel FFST introuvable.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }

        $licences = self::call_private( 'get_licences', array( $club_id, $season ) );
        $officers = self::call_private( 'build_officers', array( $club, $licences ) );
        $people = array_values( array_filter( $officers, static function( $person ) {
            return ! empty( trim( (string) ( $person['nom'] ?? '' ) ) ) || ! empty( trim( (string) ( $person['prenom'] ?? '' ) ) );
        } ) );

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $template );
            $sheet = $spreadsheet->getActiveSheet();

            self::call_private( 'fill_club_header', array( $sheet, $club, $season ) );
            self::fill_blocks( $sheet, $people );
            self::call_private( 'write_control_sheet', array( $spreadsheet, $club, $officers, $people, $season ) );

            $club_name = self::value( $club, array( 'nom', 'name', 'club_name' ) );
            $filename = 'ffst-licences-dirigeants-' . sanitize_title( $club_name ?: 'club-' . $club_id ) . '-' . sanitize_title( $season ) . '.xlsx';
            $tmp = wp_tempnam( $filename );
            if ( ! $tmp ) { throw new RuntimeException( 'Impossible de créer le fichier temporaire.' ); }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter( $spreadsheet, 'Xlsx' );
            $writer->save( $tmp );

            nocache_headers();
            header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
            header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
            header( 'Content-Length: ' . filesize( $tmp ) );
            readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
            @unlink( $tmp );
            exit;
        } catch ( Throwable $e ) {
            if ( function_exists( 'ufsc_log_error' ) ) { ufsc_log_error( 'FFST leaders block mapping: ' . $e->getMessage() ); }
            wp_die( esc_html__( 'Le bordereau FFST n’a pas pu être prérempli.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }
    }

    private static function fill_blocks( $sheet, array $people ) {
        $data_rows = array( 19, 27, 35, 43, 51 );
        foreach ( array_slice( $people, 0, 5 ) as $index => $person ) {
            self::fill_person( $sheet, $data_rows[ $index ], $person );
        }
    }

    private static function fill_person( $sheet, $row, array $person ) {
        $address = self::join_non_empty( array(
            $person['adresse'] ?? '',
            $person['complement_adresse'] ?? '',
            $person['code_postal'] ?? '',
            $person['ville'] ?? '',
        ), ' ' );
        $country = strtolower( remove_accents( trim( (string) ( $person['pays_naissance'] ?? '' ) ) ) );
        $foreign = $country && ! in_array( $country, array( 'france', 'fr', 'francaise', 'francais' ), true );
        $birth_fr = self::join_non_empty( array( $person['ville_naissance'] ?? '', $person['departement_naissance'] ?? '' ), ' - ' );
        $birth_foreign = self::join_non_empty( array( $person['pays_naissance'] ?? '', $person['ville_naissance'] ?? '' ), ' - ' );
        $gender = strtolower( remove_accents( trim( (string) ( $person['genre'] ?? '' ) ) ) );

        // Ligne principale réelle du bloc : 19 / 27 / 35 / 43 / 51.
        self::set_cell( $sheet, 'B' . $row, $person['numero_ffst'] ?? '' );
        self::set_cell( $sheet, 'C' . $row, $person['nom'] ?? '' );
        self::set_cell( $sheet, 'D' . $row, $person['prenom'] ?? '' );
        if ( in_array( $gender, array( 'm', 'masculin', 'homme', 'male' ), true ) ) { self::set_cell( $sheet, 'E' . $row, 'X' ); }
        if ( in_array( $gender, array( 'f', 'feminin', 'femme', 'female' ), true ) ) { self::set_cell( $sheet, 'F' . $row, 'X' ); }
        self::set_cell( $sheet, 'G' . $row, $person['date_naissance'] ?? '' );
        self::set_cell( $sheet, 'H' . $row, $address );
        self::set_cell( $sheet, 'I' . $row, $person['fonction'] ?? '' );

        // Les lignes complémentaires sont relatives à la ligne de données.
        self::set_cell( $sheet, 'C' . ( $row + 1 ), $person['telephone'] ?? '' );
        self::set_cell( $sheet, 'G' . ( $row + 1 ), $person['email'] ?? '' );
        if ( $foreign ) {
            self::set_cell( $sheet, 'C' . ( $row + 4 ), $birth_foreign );
            self::set_cell( $sheet, 'G' . ( $row + 3 ), $person['pere'] ?? '' );
            self::set_cell( $sheet, 'G' . ( $row + 4 ), $person['mere'] ?? '' );
        } else {
            self::set_cell( $sheet, 'C' . ( $row + 3 ), $birth_fr );
        }
    }

    private static function set_cell( $sheet, $coordinate, $value ) {
        if ( '' === trim( (string) $value ) ) { return; }
        $sheet->setCellValue( $coordinate, (string) $value );
    }

    private static function join_non_empty( array $values, $separator ) {
        $values = array_values( array_filter( array_map( 'strval', $values ), static function( $value ) { return '' !== trim( $value ); } ) );
        return implode( $separator, array_map( 'trim', $values ) );
    }

    private static function value( $row, array $keys ) {
        foreach ( $keys as $key ) {
            if ( is_object( $row ) && isset( $row->{$key} ) && '' !== trim( (string) $row->{$key} ) ) { return (string) $row->{$key}; }
            if ( is_array( $row ) && isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) { return (string) $row[ $key ]; }
        }
        return '';
    }
}

UFSC_FFST_Leaders_Export_Block_Map_Fix::init();
