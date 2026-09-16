<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Correctif ciblé du bordereau officiel FFST dirigeants.
 *
 * Lecture seule : les données sont lues depuis le club et les licences de la
 * saison. Aucune fiche, licence, saison, commande ou logique de paiement n'est
 * modifiée.
 */
final class UFSC_FFST_Leaders_Export_Fix {
    const ACTION = 'ufsc_ffst_generate_licences';
    const TEMPLATE_BASENAME = '02-BORDEREAU-LICENCES-DIRIGEANTS-26-27.xls';

    public static function init() {
        // Le générateur historique reste intact ; seule cette action d'export
        // est remplacée pour garantir le préremplissage du modèle officiel.
        remove_action( 'admin_post_' . self::ACTION, array( 'UFSC_FFST_Export_Admin', 'handle_generate_licences' ) );
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_generate' ) );
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
        $people   = self::build_officers( $club, $licences );

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $template );
            $sheet = $spreadsheet->getActiveSheet();
            $mapping = self::detect_table_mapping( $sheet );

            if ( empty( $mapping['header_row'] ) || empty( $mapping['nom'] ) || empty( $mapping['prenom'] ) ) {
                throw new RuntimeException( 'En-têtes du tableau dirigeants non détectés dans le modèle officiel.' );
            }

            self::fill_club_header( $sheet, $club, $season );
            $row = (int) $mapping['header_row'] + 1;
            foreach ( $people as $person ) {
                self::write_person( $sheet, $row, $mapping, $person, $season );
                $row++;
            }
            self::write_control_sheet( $spreadsheet, $club, $people, $season );

            $filename = 'ffst-licences-dirigeants-' . sanitize_title( self::value( $club, array( 'nom', 'name', 'club_name' ) ) ?: 'club-' . $club_id ) . '-' . sanitize_title( $season ) . '.xlsx';
            $tmp = wp_tempnam( $filename );
            if ( ! $tmp ) { throw new RuntimeException( 'Impossible de créer le fichier temporaire.' ); }
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter( $spreadsheet, 'Xlsx' );
            $writer->save( $tmp );

            if ( function_exists( 'ufsc_audit_log' ) ) {
                ufsc_audit_log( 'ffst_leaders_bordereau_generated', array( 'club_id' => $club_id, 'season' => $season, 'rows' => count( $people ) ) );
            }

            nocache_headers();
            header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
            header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
            header( 'Content-Length: ' . filesize( $tmp ) );
            readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
            @unlink( $tmp );
            exit;
        } catch ( Throwable $e ) {
            if ( function_exists( 'ufsc_log_error' ) ) { ufsc_log_error( 'FFST leaders export: ' . $e->getMessage() ); }
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
            'president'  => array( 'label' => 'PR', 'needles' => array( 'president', 'président' ) ),
            'secretaire' => array( 'label' => 'S',  'needles' => array( 'secretaire', 'secrétaire' ) ),
            'tresorier'  => array( 'label' => 'TR', 'needles' => array( 'tresorier', 'trésorier' ) ),
            'entraineur' => array( 'label' => 'E',  'needles' => array( 'entraineur', 'entraîneur', 'instructeur', 'coach' ) ),
        );
        $people = array();

        foreach ( $roles as $prefix => $role ) {
            $licence = self::find_role_licence( $licences, $role['needles'] );
            $person = array(
                'fonction' => $role['label'],
                'nom' => self::value( $club, array( $prefix . '_nom' ) ),
                'prenom' => self::value( $club, array( $prefix . '_prenom' ) ),
                'date_naissance' => self::value( $club, array( $prefix . '_date_naissance' ) ),
                'lieu_naissance' => self::join_non_empty( array(
                    self::value( $club, array( $prefix . '_ville_naissance' ) ),
                    self::value( $club, array( $prefix . '_departement_naissance' ) ),
                    self::value( $club, array( $prefix . '_pays_naissance' ) ),
                ), ' - ' ),
                'adresse' => self::value( $club, array( $prefix . '_adresse' ) ),
                'complement_adresse' => self::value( $club, array( $prefix . '_complement_adresse' ) ),
                'code_postal' => self::value( $club, array( $prefix . '_code_postal' ) ),
                'ville' => self::value( $club, array( $prefix . '_ville' ) ),
                'telephone' => self::value( $club, array( $prefix . '_tel', $prefix . '_telephone' ) ),
                'email' => self::value( $club, array( $prefix . '_email' ) ),
                'numero_ffst' => $licence ? self::value( $licence, array( 'numero_licence_ffst', 'licence_ffst' ) ) : '',
                'numero_ufsc' => $licence ? self::value( $licence, array( 'numero_licence_ufsc', 'numero_licence', 'licence_number' ) ) : '',
            );

            if ( $licence ) {
                foreach ( array( 'nom', 'prenom', 'date_naissance', 'email', 'telephone', 'adresse', 'code_postal', 'ville' ) as $key ) {
                    if ( '' === trim( (string) $person[ $key ] ) ) {
                        $fallbacks = array(
                            'nom' => array( 'nom', 'nom_licence', 'last_name' ),
                            'prenom' => array( 'prenom', 'first_name' ),
                            'date_naissance' => array( 'date_naissance', 'birth_date', 'dob' ),
                            'email' => array( 'email', 'mail' ),
                            'telephone' => array( 'telephone', 'tel', 'phone' ),
                            'adresse' => array( 'adresse', 'address' ),
                            'code_postal' => array( 'code_postal', 'postal_code', 'cp' ),
                            'ville' => array( 'ville', 'city' ),
                        );
                        $person[ $key ] = self::value( $licence, $fallbacks[ $key ] );
                    }
                }
            }

            // Le président, secrétaire et trésorier viennent du compte club :
            // ils doivent apparaître même si la licence FFST n'est pas encore attribuée.
            if ( 'entraineur' !== $prefix || self::has_identity( $person ) ) {
                $people[] = $person;
            }
        }
        return $people;
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

    private static function has_identity( array $person ) {
        return '' !== trim( (string) $person['nom'] ) || '' !== trim( (string) $person['prenom'] );
    }

    private static function normalize_label( $value ) {
        $value = strtolower( remove_accents( trim( (string) $value ) ) );
        $value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
        return trim( preg_replace( '/\s+/', ' ', $value ) );
    }

    private static function detect_table_mapping( $sheet ) {
        $mapping = array( 'header_row' => 0 );
        $highest_row = min( 80, (int) $sheet->getHighestDataRow() );
        $highest_col = min( 40, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString( $sheet->getHighestDataColumn() ) );
        $best_score = 0;

        for ( $row = 1; $row <= $highest_row; $row++ ) {
            $candidate = array( 'header_row' => $row );
            for ( $col = 1; $col <= $highest_col; $col++ ) {
                $label = self::normalize_label( $sheet->getCellByColumnAndRow( $col, $row )->getFormattedValue() );
                if ( '' === $label ) { continue; }
                if ( false !== strpos( $label, 'nom de naissance' ) || 'nom' === $label ) { $candidate['nom'] = $col; }
                elseif ( false !== strpos( $label, 'prenom' ) && false === strpos( $label, 'pere' ) && false === strpos( $label, 'mere' ) ) { $candidate['prenom'] = $col; }
                elseif ( false !== strpos( $label, 'date de naissance' ) ) { $candidate['date_naissance'] = $col; }
                elseif ( false !== strpos( $label, 'lieu de naissance' ) ) { $candidate['lieu_naissance'] = $col; }
                elseif ( false !== strpos( $label, 'adresse personnelle' ) || 'adresse' === $label ) { $candidate['adresse'] = $col; }
                elseif ( false !== strpos( $label, 'code postal' ) ) { $candidate['code_postal'] = $col; }
                elseif ( 'ville' === $label ) { $candidate['ville'] = $col; }
                elseif ( false !== strpos( $label, 'fonction' ) ) { $candidate['fonction'] = $col; }
                elseif ( false !== strpos( $label, 'mail' ) || false !== strpos( $label, 'email' ) ) { $candidate['email'] = $col; }
                elseif ( false !== strpos( $label, 'licence' ) && ( false !== strpos( $label, 'ffst' ) || false !== strpos( $label, 'n licence' ) || false !== strpos( $label, 'no licence' ) ) ) { $candidate['numero_ffst'] = $col; }
            }
            $score = count( array_intersect( array( 'nom', 'prenom', 'date_naissance', 'fonction', 'adresse' ), array_keys( $candidate ) ) );
            if ( $score > $best_score ) { $best_score = $score; $mapping = $candidate; }
            if ( $score >= 4 && ! empty( $candidate['nom'] ) && ! empty( $candidate['prenom'] ) ) { break; }
        }
        return $mapping;
    }

    private static function write_person( $sheet, $row, array $mapping, array $person, $season ) {
        $address = self::join_non_empty( array( $person['adresse'], $person['complement_adresse'], $person['code_postal'], $person['ville'] ), ' ' );
        $values = array(
            'nom' => $person['nom'],
            'prenom' => $person['prenom'],
            'date_naissance' => $person['date_naissance'],
            'lieu_naissance' => $person['lieu_naissance'],
            'adresse' => $address,
            'code_postal' => $person['code_postal'],
            'ville' => $person['ville'],
            'fonction' => $person['fonction'],
            'email' => $person['email'],
            'numero_ffst' => $person['numero_ffst'],
            'saison' => $season,
        );
        foreach ( $values as $key => $value ) {
            if ( ! empty( $mapping[ $key ] ) ) { $sheet->setCellValueByColumnAndRow( (int) $mapping[ $key ], (int) $row, (string) $value ); }
        }
    }

    private static function fill_club_header( $sheet, $club, $season ) {
        $highest_row = min( 35, (int) $sheet->getHighestDataRow() );
        $highest_col = min( 20, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString( $sheet->getHighestDataColumn() ) );
        $replacements = array(
            'nom du club' => self::value( $club, array( 'nom', 'name', 'club_name' ) ),
            'adresse' => self::join_non_empty( array( self::value( $club, array( 'adresse' ) ), self::value( $club, array( 'complement_adresse' ) ), self::value( $club, array( 'code_postal' ) ), self::value( $club, array( 'ville' ) ) ), ' ' ),
            'n affiliation' => self::value( $club, array( 'numero_affiliation_ffst' ) ),
            'no affiliation' => self::value( $club, array( 'numero_affiliation_ffst' ) ),
            'discipline' => self::value( $club, array( 'disciplines_ffst', 'discipline', 'disciplines' ) ),
            'code discipline' => self::value( $club, array( 'codes_disciplines_ffst', 'code_discipline' ) ),
        );
        foreach ( $replacements as $needle => $value ) {
            if ( '' === trim( (string) $value ) ) { continue; }
            for ( $r = 1; $r <= $highest_row; $r++ ) {
                for ( $c = 1; $c <= $highest_col; $c++ ) {
                    $text = self::normalize_label( $sheet->getCellByColumnAndRow( $c, $r )->getFormattedValue() );
                    if ( false === strpos( $text, $needle ) ) { continue; }
                    $target_col = min( $highest_col, $c + 1 );
                    $target = $sheet->getCellByColumnAndRow( $target_col, $r );
                    if ( '' === trim( (string) $target->getValue() ) ) { $target->setValue( (string) $value ); }
                    break 2;
                }
            }
        }
    }

    private static function write_control_sheet( $spreadsheet, $club, array $people, $season ) {
        $sheet = $spreadsheet->getSheetByName( 'Contrôle UFSC' );
        if ( ! $sheet ) { $sheet = $spreadsheet->createSheet(); $sheet->setTitle( 'Contrôle UFSC' ); }
        $sheet->fromArray( array( array( 'Contrôle export dirigeants FFST', '' ), array( 'Club', self::value( $club, array( 'nom', 'name', 'club_name' ) ) ), array( 'Saison', $season ), array( 'Dirigeants exportés', count( $people ) ), array(), array( 'Fonction', 'Nom', 'Prénom', 'N° licence FFST', 'État' ) ), null, 'A1' );
        $row = 7;
        foreach ( $people as $person ) {
            $missing = array();
            foreach ( array( 'nom' => 'nom', 'prenom' => 'prénom', 'date_naissance' => 'date de naissance' ) as $key => $label ) { if ( '' === trim( (string) $person[ $key ] ) ) { $missing[] = $label; } }
            $sheet->fromArray( array( $person['fonction'], $person['nom'], $person['prenom'], $person['numero_ffst'], $missing ? 'À compléter : ' . implode( ', ', $missing ) : 'OK' ), null, 'A' . $row );
            $row++;
        }
        foreach ( range( 'A', 'E' ) as $col ) { $sheet->getColumnDimension( $col )->setAutoSize( true ); }
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

UFSC_FFST_Leaders_Export_Fix::init();
