<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Générateur V2 du bordereau officiel FFST dirigeants.
 *
 * Correctif ciblé pour respecter les cellules fusionnées du modèle officiel.
 * Lecture seule sur les données métier : aucune licence, aucun club, aucune
 * commande, aucun quota et aucun renouvellement ne sont modifiés.
 */
final class UFSC_FFST_Leaders_Export_V2 {
    const ACTION = 'ufsc_ffst_generate_licences';
    const TEMPLATE_BASENAME = '02-BORDEREAU-LICENCES-DIRIGEANTS-26-27.xls';

    public static function init() {
        remove_action( 'admin_post_' . self::ACTION, array( 'UFSC_FFST_Export_Admin', 'handle_generate_licences' ) );
        remove_action( 'admin_post_' . self::ACTION, array( 'UFSC_FFST_Leaders_Export_Fix', 'handle_generate' ) );
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_generate' ), 1 );
    }

    private static function can_manage() {
        return class_exists( 'UFSC_Permissions' ) && current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    public static function handle_generate() {
        if ( ! self::can_manage() || 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
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

        $club = self::get_club( $club_id );
        if ( ! $club ) {
            wp_die( esc_html__( 'Club introuvable.', 'ufsc-clubs' ), '', array( 'response' => 404 ) );
        }

        $template = self::resolve_template();
        if ( ! $template ) {
            wp_die( esc_html__( 'Le modèle officiel FFST dirigeants est introuvable sur le serveur.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }

        $licences = self::get_licences( $club_id, $season );
        $officers = self::build_officers( $club, $licences );
        $people   = array_values( array_filter( $officers, array( __CLASS__, 'has_identity' ) ) );

        try {
            $spreadsheet = \\PhpOffice\\PhpSpreadsheet\\IOFactory::load( $template );
            $sheet = $spreadsheet->getActiveSheet();

            self::fill_club_header( $sheet, $club );
            self::fill_official_blocks( $sheet, $people );
            self::write_control_sheet( $spreadsheet, $club, $officers, $people, $season );

            $filename = 'ffst-licences-dirigeants-' . sanitize_title( self::value( $club, array( 'nom', 'name', 'club_name' ) ) ?: 'club-' . $club_id ) . '-' . sanitize_title( $season ) . '.xlsx';
            $tmp = wp_tempnam( $filename );
            if ( ! $tmp ) { throw new RuntimeException( 'Impossible de créer le fichier temporaire.' ); }

            $writer = \\PhpOffice\\PhpSpreadsheet\\IOFactory::createWriter( $spreadsheet, 'Xlsx' );
            $writer->save( $tmp );

            if ( function_exists( 'ufsc_audit_log' ) ) {
                ufsc_audit_log( 'ffst_leaders_bordereau_generated_v2', array( 'club_id' => $club_id, 'season' => $season, 'rows' => count( $people ) ) );
            }

            nocache_headers();
            header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
            header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
            header( 'Content-Length: ' . filesize( $tmp ) );
            readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
            @unlink( $tmp );
            exit;
        } catch ( Throwable $e ) {
            if ( function_exists( 'ufsc_log_error' ) ) { ufsc_log_error( 'FFST leaders export V2: ' . $e->getMessage() ); }
            wp_die( esc_html__( 'Le bordereau FFST n’a pas pu être prérempli. Le modèle officiel original n’a pas été modifié.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }
    }

    private static function resolve_template() {
        $uploads = wp_upload_dir();
        $candidates = array(
            trailingslashit( $uploads['basedir'] ) . '2026/09/' . self::TEMPLATE_BASENAME,
            trailingslashit( $uploads['basedir'] ) . '2026/09/02 BORDEREAU LICENCES DIRIGEANTS 26-27.xls',
            defined( 'UFSC_CL_DIR' ) ? UFSC_CL_DIR . 'assets/ffst/' . self::TEMPLATE_BASENAME : '',
        );
        foreach ( $candidates as $candidate ) {
            if ( $candidate && is_readable( $candidate ) ) { return $candidate; }
        }
        return '';
    }

    private static function get_club( $club_id ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $pk = in_array( 'id', $columns, true ) ? 'id' : ( in_array( 'club_id', $columns, true ) ? 'club_id' : 'id' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$pk}`=%d LIMIT 1", absint( $club_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function get_licences( $club_id, $season ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $club_col = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : '' );
        if ( ! $club_col ) { return array(); }

        foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $candidate ) {
            if ( ! in_array( $candidate, $columns, true ) ) { continue; }
            if ( 'season_end_year' === $candidate ) {
                $end_year = preg_match( '/^\d{4}-(\d{4})$/', $season, $m ) ? (int) $m[1] : 0;
                return $end_year ? (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND `season_end_year`=%d", $club_id, $end_year ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
            return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND REPLACE(TRIM(`{$candidate}`), '/', '-')=%s", $club_id, $season ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return array();
    }

    private static function build_officers( $club, array $licences ) {
        $roles = array(
            'president'  => array( 'label' => 'PR', 'name' => 'Président', 'needles' => array( 'president', 'président' ) ),
            'secretaire' => array( 'label' => 'S',  'name' => 'Secrétaire', 'needles' => array( 'secretaire', 'secrétaire' ) ),
            'tresorier'  => array( 'label' => 'TR', 'name' => 'Trésorier', 'needles' => array( 'tresorier', 'trésorier' ) ),
            'entraineur' => array( 'label' => 'E',  'name' => 'Entraîneur / instructeur', 'needles' => array( 'entraineur', 'entraîneur', 'instructeur', 'coach' ) ),
        );
        $people = array();

        foreach ( $roles as $prefix => $role ) {
            $licence = self::find_role_licence( $licences, $role['needles'] );
            $person = array(
                'role_name' => $role['name'],
                'fonction' => $role['label'],
                'nom' => self::value( $club, array( $prefix . '_nom' ) ),
                'prenom' => self::value( $club, array( $prefix . '_prenom' ) ),
                'date_naissance' => self::clean_date( self::value( $club, array( $prefix . '_date_naissance' ) ) ),
                'ville_naissance' => self::value( $club, array( $prefix . '_ville_naissance' ) ),
                'departement_naissance' => self::value( $club, array( $prefix . '_departement_naissance' ) ),
                'pays_naissance' => self::value( $club, array( $prefix . '_pays_naissance' ) ),
                'pere' => self::value( $club, array( $prefix . '_pere', $prefix . '_pere_nom_prenom' ) ),
                'mere' => self::value( $club, array( $prefix . '_mere', $prefix . '_mere_nom_prenom' ) ),
                'adresse' => self::value( $club, array( $prefix . '_adresse' ) ),
                'complement_adresse' => self::value( $club, array( $prefix . '_complement_adresse' ) ),
                'code_postal' => self::value( $club, array( $prefix . '_code_postal' ) ),
                'ville' => self::value( $club, array( $prefix . '_ville' ) ),
                'telephone' => self::value( $club, array( $prefix . '_tel', $prefix . '_telephone' ) ),
                'email' => self::value( $club, array( $prefix . '_email' ) ),
                'genre' => '',
                'numero_ffst' => $licence ? self::value( $licence, array( 'numero_licence_ffst', 'licence_ffst' ) ) : '',
            );

            if ( $licence ) {
                $fallbacks = array(
                    'nom' => array( 'nom', 'nom_licence', 'last_name' ),
                    'prenom' => array( 'prenom', 'first_name' ),
                    'date_naissance' => array( 'date_naissance', 'birth_date', 'dob' ),
                    'email' => array( 'email', 'mail' ),
                    'telephone' => array( 'telephone', 'tel', 'phone' ),
                    'adresse' => array( 'adresse', 'address' ),
                    'code_postal' => array( 'code_postal', 'postal_code', 'cp' ),
                    'ville' => array( 'ville', 'city' ),
                    'genre' => array( 'sexe', 'genre', 'gender' ),
                    'ville_naissance' => array( 'ville_naissance', 'birth_city' ),
                    'departement_naissance' => array( 'departement_naissance', 'dept_naissance', 'birth_department' ),
                    'pays_naissance' => array( 'pays_naissance', 'birth_country' ),
                );
                foreach ( $fallbacks as $key => $keys ) {
                    if ( '' === trim( (string) $person[ $key ] ) ) {
                        $person[ $key ] = self::value( $licence, $keys );
                        if ( 'date_naissance' === $key ) { $person[ $key ] = self::clean_date( $person[ $key ] ); }
                    }
                }
            }
            $people[] = $person;
        }
        return $people;
    }

    public static function has_identity( array $person ) {
        return '' !== trim( (string) $person['nom'] ) || '' !== trim( (string) $person['prenom'] );
    }

    private static function find_role_licence( array $licences, array $needles ) {
        foreach ( $licences as $licence ) {
            $role = strtolower( remove_accents( self::value( $licence, array( 'role', 'fonction', 'poste', 'position' ) ) ) );
            foreach ( $needles as $needle ) {
                if ( false !== strpos( $role, strtolower( remove_accents( $needle ) ) ) ) { return $licence; }
            }
        }
        return null;
    }

    /**
     * Dans le modèle FFST, les en-têtes sont en 15/24/32/40/48 et les lignes
     * réellement éditables sont 17/25/33/41/49.
     */
    private static function fill_official_blocks( $sheet, array $people ) {
        $data_rows = array( 17, 25, 33, 41, 49 );
        foreach ( array_slice( $people, 0, count( $data_rows ) ) as $index => $person ) {
            self::fill_official_person_block( $sheet, $data_rows[ $index ], $person );
        }
    }

    private static function fill_official_person_block( $sheet, $row, array $person ) {
        $address = self::join_non_empty( array( $person['adresse'], $person['complement_adresse'], $person['code_postal'], $person['ville'] ), ' ' );
        $birth_fr = self::join_non_empty( array( $person['ville_naissance'], $person['departement_naissance'] ), ' - ' );
        $birth_foreign = self::join_non_empty( array( $person['pays_naissance'], $person['ville_naissance'] ), ' - ' );
        $country = strtolower( remove_accents( trim( (string) $person['pays_naissance'] ) ) );
        $is_foreign = $country && ! in_array( $country, array( 'france', 'fr', 'francaise', 'francais' ), true );
        $gender = strtolower( remove_accents( trim( (string) $person['genre'] ) ) );

        self::set_cell( $sheet, 'B' . $row, $person['numero_ffst'] );
        self::set_cell( $sheet, 'C' . $row, $person['nom'] );
        self::set_cell( $sheet, 'D' . $row, $person['prenom'] );
        if ( in_array( $gender, array( 'm', 'masculin', 'homme', 'male' ), true ) ) { self::set_cell( $sheet, 'E' . $row, 'X' ); }
        if ( in_array( $gender, array( 'f', 'feminin', 'femme', 'female' ), true ) ) { self::set_cell( $sheet, 'F' . $row, 'X' ); }
        self::set_cell( $sheet, 'G' . $row, $person['date_naissance'] );
        self::set_cell( $sheet, 'H' . $row, $address );
        self::set_cell( $sheet, 'I' . $row, $person['fonction'] );

        self::set_cell( $sheet, 'C' . ( $row + 3 ), $person['telephone'] );
        self::set_cell( $sheet, 'G' . ( $row + 3 ), $person['email'] );
        if ( $is_foreign ) {
            self::set_cell( $sheet, 'C' . ( $row + 6 ), $birth_foreign );
            self::set_cell( $sheet, 'G' . ( $row + 5 ), $person['pere'] );
            self::set_cell( $sheet, 'G' . ( $row + 6 ), $person['mere'] );
        } else {
            self::set_cell( $sheet, 'C' . ( $row + 5 ), $birth_fr );
        }
    }

    /**
     * Les cellules de l'entête sont fusionnées. On écrit donc dans la cellule
     * visible (en haut à gauche) en conservant le libellé officiel.
     */
    private static function fill_club_header( $sheet, $club ) {
        $club_name = self::value( $club, array( 'nom', 'name', 'club_name' ) );
        $club_address = self::join_non_empty( array(
            self::value( $club, array( 'adresse' ) ),
            self::value( $club, array( 'complement_adresse' ) ),
            self::value( $club, array( 'code_postal' ) ),
            self::value( $club, array( 'ville' ) ),
        ), ' ' );
        $affiliation = self::value( $club, array( 'numero_affiliation_ffst' ) );
        $discipline = self::value( $club, array( 'disciplines_ffst', 'discipline', 'disciplines' ) );
        $code = self::value( $club, array( 'codes_disciplines_ffst', 'code_discipline' ) );

        $sheet->setCellValue( 'B11', 'NOM DU CLUB : ' . $club_name );
        $sheet->setCellValue( 'B12', 'ADRESSE : ' . $club_address );
        $sheet->setCellValue( 'H11', 'N° Affiliation : ' . $affiliation );
        $sheet->setCellValue( 'H12', 'Discipline : ' . $discipline );
        $sheet->setCellValue( 'H13', 'Code Discipline : ' . $code );
    }

    private static function set_cell( $sheet, $coordinate, $value ) {
        if ( '' === trim( (string) $value ) ) { return; }
        $sheet->setCellValue( $coordinate, (string) $value );
    }

    private static function write_control_sheet( $spreadsheet, $club, array $officers, array $people, $season ) {
        $sheet = $spreadsheet->getSheetByName( 'Contrôle UFSC' );
        if ( ! $sheet ) { $sheet = $spreadsheet->createSheet(); $sheet->setTitle( 'Contrôle UFSC' ); }
        $sheet->fromArray( array(
            array( 'Contrôle export dirigeants FFST', '' ),
            array( 'Club', self::value( $club, array( 'nom', 'name', 'club_name' ) ) ),
            array( 'Saison', $season ),
            array( 'Dirigeants réellement exportés', count( $people ) ),
            array(),
            array( 'Fonction', 'Nom', 'Prénom', 'N° licence FFST', 'État' ),
        ), null, 'A1' );

        $row = 7;
        foreach ( $officers as $person ) {
            $missing = array();
            foreach ( array( 'nom' => 'nom', 'prenom' => 'prénom', 'date_naissance' => 'date de naissance' ) as $key => $label ) {
                if ( '' === trim( (string) $person[ $key ] ) ) { $missing[] = $label; }
            }
            $state = self::has_identity( $person )
                ? ( $missing ? 'À compléter : ' . implode( ', ', $missing ) : 'OK' )
                : 'Fonction obligatoire à renseigner dans le compte club';
            $sheet->fromArray( array( $person['role_name'], $person['nom'], $person['prenom'], $person['numero_ffst'], $state ), null, 'A' . $row );
            $row++;
        }
        foreach ( range( 'A', 'E' ) as $col ) { $sheet->getColumnDimension( $col )->setAutoSize( true ); }
    }

    private static function clean_date( $date ) {
        $date = trim( (string) $date );
        return in_array( $date, array( '', '0000-00-00', '00/00/0000' ), true ) ? '' : $date;
    }

    private static function join_non_empty( array $values, $separator ) {
        $values = array_values( array_filter( array_map( static function( $value ) { return trim( (string) $value ); }, $values ), static function( $value ) { return '' !== $value; } ) );
        return implode( $separator, $values );
    }

    private static function value( $row, array $keys ) {
        foreach ( $keys as $key ) {
            if ( is_object( $row ) && isset( $row->{$key} ) && '' !== trim( (string) $row->{$key} ) ) { return (string) $row->{$key}; }
            if ( is_array( $row ) && isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) { return (string) $row[ $key ]; }
        }
        return '';
    }
}

UFSC_FFST_Leaders_Export_V2::init();
