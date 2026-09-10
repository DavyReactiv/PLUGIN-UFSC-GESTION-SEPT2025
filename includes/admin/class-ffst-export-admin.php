<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Génération interne des documents FFST.
 *
 * Les exports sont volontairement non destructifs : ils lisent les données
 * existantes, signalent les champs absents et n'écrivent jamais dans les fiches
 * clubs/licences ni dans les saisons historiques.
 */
final class UFSC_FFST_Export_Admin {
    const AFFILIATION_TEMPLATE_URL = 'https://ufsc-france.fr/wp-content/uploads/2026/09/01-AFFIL-REAFFIL-FFST-26-27.doc';
    const LEADERS_TEMPLATE_URL     = 'https://ufsc-france.fr/wp-content/uploads/2026/09/02-BORDEREAU-LICENCES-DIRIGEANTS-26-27.xls';
    const HISTORY_OPTION_PREFIX    = 'ufsc_ffst_export_history_';

    public static function init() {
        add_action( 'admin_post_ufsc_ffst_generate_affiliation', array( __CLASS__, 'handle_generate_affiliation' ) );
        add_action( 'admin_post_ufsc_ffst_generate_licences', array( __CLASS__, 'handle_generate_licences' ) );
    }

    private static function can_manage() {
        return current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    /**
     * Render the two canonical admin actions. Completeness is advisory only.
     */
    public static function render_actions( $club_id, $season, $readiness ) {
        $club_id = absint( $club_id );
        $season  = sanitize_text_field( (string) $season );
        if ( ! self::can_manage() || ! $club_id || ! $season ) { return; }

        $missing = self::missing_labels( $readiness );
        echo '<div class="postbox" style="padding:18px;">';
        echo '<h2 style="margin-top:0;">' . esc_html__( '3. Génération des documents FFST', 'ufsc-clubs' ) . '</h2>';
        echo '<p>' . esc_html__( 'Les documents sont préremplis avec les données disponibles. Un administrateur peut les générer même si le dossier est incomplet : les informations absentes restent clairement signalées à compléter.', 'ufsc-clubs' ) . '</p>';

        if ( $missing ) {
            echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Génération forcée autorisée.', 'ufsc-clubs' ) . '</strong> ';
            echo esc_html( sprintf( _n( '%d élément reste à compléter.', '%d éléments restent à compléter.', count( $missing ), 'ufsc-clubs' ), count( $missing ) ) );
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'Dossier prêt : les contrôles disponibles sont complets.', 'ufsc-clubs' ) . '</strong></p></div>';
        }

        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Document', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Action rapide', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Modèle officiel', 'ufsc-clubs' ) . '</th></tr></thead><tbody>';
        echo '<tr><td><strong>' . esc_html__( 'Demande affiliation / réaffiliation FFST', 'ufsc-clubs' ) . '</strong><br><span class="description">' . esc_html__( 'Vue A4 préremplie, imprimable ou enregistrable en PDF.', 'ufsc-clubs' ) . '</span></td><td>';
        self::render_post_button( 'ufsc_ffst_generate_affiliation', $club_id, $season, __( 'Générer la demande d’affiliation', 'ufsc-clubs' ), true );
        echo '</td><td><a href="' . esc_url( self::AFFILIATION_TEMPLATE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Télécharger le .doc officiel', 'ufsc-clubs' ) . '</a></td></tr>';

        echo '<tr><td><strong>' . esc_html__( 'Bordereau licences dirigeants FFST', 'ufsc-clubs' ) . '</strong><br><span class="description">' . esc_html__( 'Excel prérempli depuis les licences de la saison. Les rôles manquants ne bloquent pas l’export.', 'ufsc-clubs' ) . '</span></td><td>';
        self::render_post_button( 'ufsc_ffst_generate_licences', $club_id, $season, __( 'Télécharger le bordereau licences', 'ufsc-clubs' ), false );
        echo '</td><td><a href="' . esc_url( self::LEADERS_TEMPLATE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Télécharger le .xls officiel', 'ufsc-clubs' ) . '</a></td></tr>';
        echo '</tbody></table>';

        $history = self::get_history( $club_id, $season );
        if ( $history ) {
            $last = end( $history );
            $user = ! empty( $last['user_id'] ) ? get_userdata( absint( $last['user_id'] ) ) : false;
            $who  = $user ? $user->display_name : __( 'Administration UFSC', 'ufsc-clubs' );
            echo '<p class="description">' . esc_html( sprintf( __( 'Dernière génération : %1$s — %2$s — %3$s.', 'ufsc-clubs' ), $last['date'] ?? '—', $last['label'] ?? '—', $who ) ) . '</p>';
        }
        echo '<p class="description">' . esc_html__( 'Ces actions ne modifient aucune fiche club/licence et ne réécrivent aucune saison antérieure.', 'ufsc-clubs' ) . '</p>';
        echo '</div>';
    }

    private static function render_post_button( $action, $club_id, $season, $label, $new_tab ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $new_tab ? ' target="_blank"' : '' ) . '>';
        echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
        echo '<input type="hidden" name="club_id" value="' . esc_attr( $club_id ) . '">';
        echo '<input type="hidden" name="season" value="' . esc_attr( $season ) . '">';
        wp_nonce_field( $action . '_' . $club_id . '_' . $season );
        echo '<button type="submit" class="button button-primary">' . esc_html( $label ) . '</button>';
        echo '</form>';
    }

    public static function handle_generate_affiliation() {
        list( $club_id, $season ) = self::guard_request( 'ufsc_ffst_generate_affiliation' );
        $club = self::get_club( $club_id );
        if ( ! $club ) { wp_die( esc_html__( 'Club introuvable.', 'ufsc-clubs' ) ); }
        $licences = self::get_licences( $club_id, $season );
        $leaders  = array_values( array_filter( $licences, array( __CLASS__, 'is_official_person' ) ) );

        self::record_generation( $club_id, $season, 'affiliation', __( 'Demande affiliation', 'ufsc-clubs' ) );
        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log( 'ffst_affiliation_generated', array( 'club_id' => $club_id, 'season' => $season, 'forced' => true ) );
        }

        nocache_headers();
        header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
        echo '<!doctype html><html><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '"><title>' . esc_html__( 'Demande affiliation FFST', 'ufsc-clubs' ) . '</title>';
        echo '<style>@page{size:A4;margin:12mm}body{font-family:Arial,sans-serif;color:#1d2327;font-size:12px;line-height:1.35;max-width:190mm;margin:0 auto}h1{font-size:21px;margin:0 0 2mm}h2{font-size:15px;margin:6mm 0 2mm}.meta{color:#50575e;margin-bottom:5mm}.warning{padding:3mm;border:1px solid #dba617;background:#fff8e5;margin:4mm 0}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #8c8f94;padding:2.2mm;vertical-align:top;text-align:left}th{background:#f0f0f1;width:34%}.missing{font-weight:700;color:#8a2424}.actions{margin:6mm 0}@media print{.actions{display:none}}</style></head><body>';
        echo '<div class="actions"><button type="button" onclick="window.print()">' . esc_html__( 'Imprimer / enregistrer en PDF', 'ufsc-clubs' ) . '</button></div>';
        echo '<h1>' . esc_html__( 'Demande d’affiliation / réaffiliation FFST', 'ufsc-clubs' ) . '</h1>';
        echo '<div class="meta">' . esc_html( sprintf( __( 'Saison %1$s — Club #%2$d — généré le %3$s', 'ufsc-clubs' ), $season, $club_id, current_time( 'd/m/Y H:i' ) ) ) . '</div>';
        echo '<div class="warning">' . esc_html__( 'Document prérempli depuis UFSC Gestion. Les mentions « À compléter » doivent être vérifiées avant envoi à la FFST.', 'ufsc-clubs' ) . '</div>';
        echo '<h2>' . esc_html__( 'Informations du club', 'ufsc-clubs' ) . '</h2><table><tbody>';
        $rows = self::affiliation_rows( $club );
        foreach ( $rows as $label => $value ) { self::render_document_row( $label, $value ); }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__( 'Dirigeants / encadrement', 'ufsc-clubs' ) . '</h2>';
        echo '<table><thead><tr><th>' . esc_html__( 'Fonction', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Nom', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Prénom', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Date de naissance', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'E-mail', 'ufsc-clubs' ) . '</th></tr></thead><tbody>';
        foreach ( $leaders as $licence ) {
            echo '<tr><td>' . esc_html( self::role_value( $licence ) ?: __( 'À compléter', 'ufsc-clubs' ) ) . '</td><td>' . esc_html( self::value( $licence, array( 'nom', 'last_name', 'nom_licence' ) ) ?: __( 'À compléter', 'ufsc-clubs' ) ) . '</td><td>' . esc_html( self::value( $licence, array( 'prenom', 'first_name' ) ) ?: __( 'À compléter', 'ufsc-clubs' ) ) . '</td><td>' . esc_html( self::value( $licence, array( 'date_naissance', 'birth_date', 'dob' ) ) ?: __( 'À compléter', 'ufsc-clubs' ) ) . '</td><td>' . esc_html( self::value( $licence, array( 'email', 'mail' ) ) ?: __( 'À compléter', 'ufsc-clubs' ) ) . '</td></tr>';
        }
        if ( ! $leaders ) { echo '<tr><td colspan="5" class="missing">' . esc_html__( 'Dirigeants à compléter dans UFSC Gestion.', 'ufsc-clubs' ) . '</td></tr>'; }
        echo '</tbody></table>';
        echo '<p><small>' . esc_html__( 'Modèle officiel FFST disponible depuis l’écran Dossiers FFST pour contrôle.', 'ufsc-clubs' ) . '</small></p>';
        echo '</body></html>';
        exit;
    }

    public static function handle_generate_licences() {
        list( $club_id, $season ) = self::guard_request( 'ufsc_ffst_generate_licences' );
        if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) || ! class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
            self::redirect_with_message( $club_id, 'spreadsheet_unavailable' );
        }

        $club = self::get_club( $club_id );
        if ( ! $club ) { self::redirect_with_message( $club_id, 'club_missing' ); }
        $licences = self::get_licences( $club_id, $season );
        $leaders  = array_values( array_filter( $licences, array( __CLASS__, 'is_official_person' ) ) );

        try {
            $template_file = self::local_upload_path_from_url( self::LEADERS_TEMPLATE_URL );
            if ( $template_file && is_readable( $template_file ) ) {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $template_file );
                $sheet = $spreadsheet->getActiveSheet();
                $mapping = self::detect_columns( $sheet );
            } else {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle( 'Dirigeants' );
                $headers = array( 'NOM', 'PRÉNOM', 'DATE DE NAISSANCE', 'FONCTION', 'E-MAIL', 'N° LICENCE FFST', 'N° LICENCE UFSC', 'SAISON' );
                foreach ( $headers as $index => $header ) { $sheet->setCellValueByColumnAndRow( $index + 1, 1, $header ); }
                $mapping = array( 'nom' => 1, 'prenom' => 2, 'date_naissance' => 3, 'fonction' => 4, 'email' => 5, 'numero_ffst' => 6, 'numero_ufsc' => 7, 'saison' => 8, '_header_row' => 1 );
            }

            if ( empty( $mapping['nom'] ) || empty( $mapping['prenom'] ) ) {
                self::redirect_with_message( $club_id, 'template_headers_missing' );
            }

            $row = (int) $mapping['_header_row'] + 1;
            foreach ( $leaders as $licence ) {
                self::write_person_row( $sheet, $row, $mapping, $licence, $season );
                $row++;
            }

            $control = $spreadsheet->getSheetByName( 'Contrôle UFSC' );
            if ( ! $control ) {
                $control = $spreadsheet->createSheet();
                $control->setTitle( 'Contrôle UFSC' );
            }
            self::write_control_sheet( $control, $club, $leaders, $season );

            $uploads = wp_upload_dir();
            $dir = trailingslashit( $uploads['basedir'] ) . 'ufsc-ffst-generated/' . sanitize_title( $season ) . '/' . $club_id;
            if ( ! wp_mkdir_p( $dir ) && ! is_dir( $dir ) ) { throw new RuntimeException( 'Cannot create FFST export directory.' ); }
            $club_name = self::value( $club, array( 'nom', 'name', 'club_name' ) );
            $filename = 'ffst-licences-dirigeants-' . sanitize_title( $club_name ?: 'club-' . $club_id ) . '-' . sanitize_title( $season ) . '-' . gmdate( 'Ymd-His' ) . '.xlsx';
            $target = trailingslashit( $dir ) . $filename;
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter( $spreadsheet, 'Xlsx' );
            $writer->save( $target );

            self::record_generation( $club_id, $season, 'licences', __( 'Bordereau licences dirigeants', 'ufsc-clubs' ), $filename );
            if ( function_exists( 'ufsc_audit_log' ) ) {
                ufsc_audit_log( 'ffst_licences_generated', array( 'club_id' => $club_id, 'season' => $season, 'file' => $filename, 'rows' => count( $leaders ), 'forced' => true ) );
            }

            nocache_headers();
            header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
            header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
            header( 'Content-Length: ' . filesize( $target ) );
            readfile( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
            exit;
        } catch ( Throwable $e ) {
            if ( function_exists( 'ufsc_log_error' ) ) { ufsc_log_error( 'FFST export error: ' . $e->getMessage() ); }
            self::redirect_with_message( $club_id, 'generation_error' );
        }
    }

    private static function guard_request( $action ) {
        if ( ! self::can_manage() || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ), 403 );
        }
        $club_id = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
        $season  = isset( $_POST['season'] ) && ! is_array( $_POST['season'] ) ? sanitize_text_field( wp_unslash( $_POST['season'] ) ) : '';
        if ( ! $club_id || ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
            wp_die( esc_html__( 'Club ou saison FFST invalide.', 'ufsc-clubs' ), 400 );
        }
        check_admin_referer( $action . '_' . $club_id . '_' . $season );
        return array( $club_id, $season );
    }

    private static function get_club( $club_id ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $pk = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) ) : 'id';
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
                $end_year = preg_match( '/^\d{4}-(\d{4})$/', $season, $matches ) ? (int) $matches[1] : 0;
                return $end_year ? (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND `season_end_year`=%d ORDER BY id ASC", $club_id, $end_year ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
            return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND REPLACE(TRIM(`{$candidate}`), '/', '-')=%s ORDER BY id ASC", $club_id, $season ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return array();
    }

    private static function affiliation_rows( $club ) {
        return array(
            __( 'Nom du club', 'ufsc-clubs' ) => self::value( $club, array( 'nom', 'name', 'club_name' ) ),
            __( 'N° affiliation FFST', 'ufsc-clubs' ) => self::value( $club, array( 'numero_affiliation_ffst' ) ),
            __( 'N° affiliation UFSC', 'ufsc-clubs' ) => self::value( $club, array( 'numero_affiliation_ufsc', 'num_affiliation' ) ),
            __( 'Adresse du siège', 'ufsc-clubs' ) => self::value( $club, array( 'adresse', 'adresse_siege', 'adresse_complete', 'address' ) ),
            __( 'Code postal', 'ufsc-clubs' ) => self::value( $club, array( 'code_postal', 'postal_code', 'cp' ) ),
            __( 'Ville', 'ufsc-clubs' ) => self::value( $club, array( 'ville', 'city' ) ),
            __( 'Téléphone', 'ufsc-clubs' ) => self::value( $club, array( 'telephone', 'tel', 'phone' ) ),
            __( 'E-mail', 'ufsc-clubs' ) => self::value( $club, array( 'email', 'mail', 'club_email' ) ),
            __( 'Site Internet', 'ufsc-clubs' ) => self::value( $club, array( 'site', 'site_web', 'website', 'url_site', 'url' ) ),
            __( 'N° RNA / déclaration', 'ufsc-clubs' ) => self::value( $club, array( 'rna_number', 'rna', 'numero_recepisse', 'declaration_prefecture', 'num_declaration' ) ),
            __( 'Salle d’entraînement', 'ufsc-clubs' ) => self::value( $club, array( 'adresse_salle', 'salle_adresse', 'training_address' ) ),
        );
    }

    private static function render_document_row( $label, $value ) {
        $missing = '' === trim( (string) $value );
        echo '<tr><th>' . esc_html( $label ) . '</th><td' . ( $missing ? ' class="missing"' : '' ) . '>' . esc_html( $missing ? __( 'À compléter', 'ufsc-clubs' ) : $value ) . '</td></tr>';
    }

    private static function role_value( $licence ) {
        return self::value( $licence, array( 'role', 'fonction', 'poste', 'position' ) );
    }

    public static function is_official_person( $licence ) {
        $role = strtolower( remove_accents( self::role_value( $licence ) ) );
        foreach ( array( 'president', 'secretaire', 'tresorier', 'entraineur', 'instructeur', 'coach' ) as $needle ) {
            if ( false !== strpos( $role, $needle ) ) { return true; }
        }
        return false;
    }

    private static function licence_identifier( $licence, $type ) {
        if ( class_exists( 'UFSC_Identifier_Resolver' ) && method_exists( 'UFSC_Identifier_Resolver', 'read' ) ) {
            $value = UFSC_Identifier_Resolver::read( $licence, $type );
            if ( $value ) { return (string) $value; }
        }
        return 'licence_ffst' === $type
            ? self::value( $licence, array( 'numero_licence_ffst' ) )
            : self::value( $licence, array( 'numero_licence_ufsc' ) );
    }

    private static function detect_columns( $sheet ) {
        $mapping = array();
        $max_row = min( 25, (int) $sheet->getHighestDataRow() );
        $max_col = min( 40, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString( $sheet->getHighestDataColumn() ) );
        for ( $row = 1; $row <= max( 1, $max_row ); $row++ ) {
            for ( $col = 1; $col <= max( 1, $max_col ); $col++ ) {
                $raw = trim( (string) $sheet->getCellByColumnAndRow( $col, $row )->getFormattedValue() );
                if ( '' === $raw ) { continue; }
                $label = strtoupper( remove_accents( preg_replace( '/\s+/u', ' ', $raw ) ) );
                if ( ! isset( $mapping['nom'] ) && preg_match( '/(^|\b)NOM(\b|$)/', $label ) && false === strpos( $label, 'PRENOM' ) ) { $mapping['nom'] = $col; $mapping['_header_row'] = $row; }
                elseif ( ! isset( $mapping['prenom'] ) && false !== strpos( $label, 'PRENOM' ) ) { $mapping['prenom'] = $col; $mapping['_header_row'] = $row; }
                elseif ( ! isset( $mapping['date_naissance'] ) && false !== strpos( $label, 'NAISSANCE' ) ) { $mapping['date_naissance'] = $col; }
                elseif ( ! isset( $mapping['fonction'] ) && ( false !== strpos( $label, 'FONCTION' ) || false !== strpos( $label, 'QUALITE' ) ) ) { $mapping['fonction'] = $col; }
                elseif ( ! isset( $mapping['email'] ) && ( false !== strpos( $label, 'MAIL' ) || false !== strpos( $label, 'E-MAIL' ) ) ) { $mapping['email'] = $col; }
                elseif ( ! isset( $mapping['saison'] ) && false !== strpos( $label, 'SAISON' ) ) { $mapping['saison'] = $col; }
                elseif ( ! isset( $mapping['numero_ffst'] ) && false !== strpos( $label, 'LICENCE' ) && false !== strpos( $label, 'FFST' ) ) { $mapping['numero_ffst'] = $col; }
                elseif ( ! isset( $mapping['numero_ufsc'] ) && false !== strpos( $label, 'LICENCE' ) && false !== strpos( $label, 'UFSC' ) ) { $mapping['numero_ufsc'] = $col; }
                elseif ( ! isset( $mapping['numero_generic'] ) && ( false !== strpos( $label, 'LICENCE' ) || false !== strpos( $label, 'N°') || false !== strpos( $label, 'NUMERO' ) ) ) { $mapping['numero_generic'] = $col; }
            }
            if ( isset( $mapping['nom'], $mapping['prenom'] ) ) { break; }
        }
        $mapping['_header_row'] = isset( $mapping['_header_row'] ) ? (int) $mapping['_header_row'] : 1;
        return $mapping;
    }

    private static function write_person_row( $sheet, $row, $mapping, $licence, $season ) {
        $values = array(
            'nom' => self::value( $licence, array( 'nom', 'nom_licence', 'last_name' ) ),
            'prenom' => self::value( $licence, array( 'prenom', 'first_name' ) ),
            'date_naissance' => self::value( $licence, array( 'date_naissance', 'birth_date', 'dob' ) ),
            'fonction' => self::role_value( $licence ),
            'email' => self::value( $licence, array( 'email', 'mail' ) ),
            'numero_ffst' => self::licence_identifier( $licence, 'licence_ffst' ),
            'numero_ufsc' => self::licence_identifier( $licence, 'licence_ufsc' ),
            'saison' => $season,
        );
        foreach ( $values as $key => $value ) {
            if ( ! empty( $mapping[ $key ] ) ) { $sheet->setCellValueByColumnAndRow( (int) $mapping[ $key ], $row, $value ); }
        }
        if ( ! empty( $mapping['numero_generic'] ) ) {
            // Une colonne générique d'un modèle FFST ne doit jamais recevoir un numéro UFSC par substitution.
            $sheet->setCellValueByColumnAndRow( (int) $mapping['numero_generic'], $row, $values['numero_ffst'] );
        }
    }

    private static function write_control_sheet( $sheet, $club, $leaders, $season ) {
        $sheet->setCellValue( 'A1', 'Contrôle UFSC avant envoi FFST' );
        $sheet->setCellValue( 'A2', 'Saison' ); $sheet->setCellValue( 'B2', $season );
        $sheet->setCellValue( 'A3', 'Club' ); $sheet->setCellValue( 'B3', self::value( $club, array( 'nom', 'name', 'club_name' ) ) );
        $sheet->setCellValue( 'A5', 'Point de contrôle' ); $sheet->setCellValue( 'B5', 'État' );
        $required = array( 'Président' => 'president', 'Secrétaire' => 'secretaire', 'Trésorier' => 'tresorier', 'Entraîneur / instructeur' => 'coach' );
        $row = 6;
        foreach ( $required as $label => $needle ) {
            $found = false;
            foreach ( $leaders as $leader ) {
                $role = strtolower( remove_accents( self::role_value( $leader ) ) );
                if ( 'coach' === $needle ? preg_match( '/entraineur|instructeur|coach/', $role ) : false !== strpos( $role, $needle ) ) { $found = true; break; }
            }
            $sheet->setCellValueByColumnAndRow( 1, $row, $label );
            $sheet->setCellValueByColumnAndRow( 2, $row, $found ? 'Complet' : 'À compléter' );
            $row++;
        }
        $sheet->setCellValueByColumnAndRow( 1, $row + 1, 'Note' );
        $sheet->setCellValueByColumnAndRow( 2, $row + 1, 'Export généré même si le dossier est incomplet. Vérifier les éléments « À compléter » avant envoi.' );
    }

    private static function local_upload_path_from_url( $url ) {
        $uploads = wp_upload_dir();
        $baseurl = trailingslashit( (string) $uploads['baseurl'] );
        if ( 0 !== strpos( (string) $url, $baseurl ) ) { return ''; }
        $relative = ltrim( substr( (string) $url, strlen( $baseurl ) ), '/' );
        $path = trailingslashit( (string) $uploads['basedir'] ) . $relative;
        return is_readable( $path ) ? $path : '';
    }

    private static function missing_labels( $readiness ) {
        $missing = array();
        foreach ( (array) ( $readiness['fields'] ?? array() ) as $label => $value ) {
            if ( '' === trim( (string) $value ) ) { $missing[] = (string) $label; }
        }
        foreach ( (array) ( $readiness['roles'] ?? array() ) as $label => $value ) {
            if ( ! $value ) { $missing[] = (string) $label; }
        }
        if ( empty( $readiness['minimum_licences_ok'] ) ) { $missing[] = __( 'Minimum de 10 licences', 'ufsc-clubs' ); }
        return array_values( array_unique( $missing ) );
    }

    private static function history_key( $club_id, $season ) {
        return self::HISTORY_OPTION_PREFIX . absint( $club_id ) . '_' . sanitize_key( str_replace( '/', '-', $season ) );
    }

    private static function get_history( $club_id, $season ) {
        $history = get_option( self::history_key( $club_id, $season ), array() );
        return is_array( $history ) ? $history : array();
    }

    private static function record_generation( $club_id, $season, $type, $label, $filename = '' ) {
        $history = self::get_history( $club_id, $season );
        $history[] = array(
            'type' => sanitize_key( $type ),
            'label' => sanitize_text_field( $label ),
            'filename' => sanitize_file_name( $filename ),
            'date' => current_time( 'mysql' ),
            'user_id' => get_current_user_id(),
        );
        if ( count( $history ) > 20 ) { $history = array_slice( $history, -20 ); }
        update_option( self::history_key( $club_id, $season ), $history, false );
    }

    private static function redirect_with_message( $club_id, $code ) {
        $url = add_query_arg( array( 'page' => 'ufsc-ffst-documents', 'club_id' => absint( $club_id ), 'ffst_export' => sanitize_key( $code ) ), admin_url( 'admin.php' ) );
        wp_safe_redirect( $url );
        exit;
    }

    private static function value( $object, $keys ) {
        foreach ( (array) $keys as $key ) {
            if ( is_object( $object ) && isset( $object->{$key} ) && '' !== trim( (string) $object->{$key} ) ) { return (string) $object->{$key}; }
            if ( is_array( $object ) && isset( $object[ $key ] ) && '' !== trim( (string) $object[ $key ] ) ) { return (string) $object[ $key ]; }
        }
        return '';
    }
}

UFSC_FFST_Export_Admin::init();