<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Espace admin FFST.
 *
 * Réservé à l'administration UFSC : aucun document FFST n'est exposé dans
 * l'espace du représentant du club. Le module consolide les données présentes
 * dans UFSC Gestion et génère les fichiers de travail FFST depuis les modèles
 * officiels déposés par l'administration.
 */
final class UFSC_FFST_Documents_Admin {
    const TEMPLATE_OPTION = 'ufsc_ffst_licences_template';

    public static function render() {
        if ( ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Vous n’avez pas l’autorisation d’accéder aux dossiers FFST.', 'ufsc-clubs' ) );
        }

        $season = class_exists( 'UFSC_Season_Service' )
            ? (string) UFSC_Season_Service::get_current_season()
            : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
        $club_id = isset( $_GET['club_id'] ) ? absint( $_GET['club_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtre lecture seule.
        $clubs = self::get_clubs();
        $club = $club_id ? self::get_club( $club_id ) : null;

        echo '<div class="wrap ufsc-ffst-admin">';
        if ( class_exists( 'UFSC_SQL_Admin' ) ) { UFSC_SQL_Admin::render_admin_quick_nav(); }
        echo '<h1>' . esc_html__( 'Dossiers FFST', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Espace interne UFSC : préparation et génération des documents FFST à partir des données clubs et licences déjà enregistrées.', 'ufsc-clubs' ) . '</p>';
        self::render_flash_notice();

        echo '<form method="get" style="margin:20px 0;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:8px;">';
        echo '<input type="hidden" name="page" value="ufsc-ffst-documents">';
        echo '<label for="ufsc_ffst_club"><strong>' . esc_html__( 'Club à préparer', 'ufsc-clubs' ) . '</strong></label> ';
        echo '<select id="ufsc_ffst_club" name="club_id">';
        echo '<option value="">' . esc_html__( 'Sélectionner un club…', 'ufsc-clubs' ) . '</option>';
        foreach ( $clubs as $row ) {
            $id = absint( self::value( $row, array( 'id', 'club_id', 'ID' ) ) );
            $name = self::value( $row, array( 'nom', 'name', 'club_name' ) );
            echo '<option value="' . esc_attr( $id ) . '"' . selected( $club_id, $id, false ) . '>' . esc_html( $name ?: sprintf( __( 'Club #%d', 'ufsc-clubs' ), $id ) ) . '</option>';
        }
        echo '</select> <button class="button button-primary">' . esc_html__( 'Ouvrir le dossier', 'ufsc-clubs' ) . '</button>';
        echo '</form>';

        self::render_template_box();

        if ( ! $club ) {
            echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Sélectionnez un club pour afficher la complétude de son dossier FFST.', 'ufsc-clubs' ) . '</p></div>';
            echo '</div>';
            return;
        }

        $readiness = self::build_readiness( $club, $club_id, $season );
        self::render_summary( $club, $season, $readiness );
        self::render_affiliation_section( $readiness );
        self::render_licences_section( $readiness );
        self::render_documents_section( $club_id, $season, $readiness );
        echo '</div>';
    }

    public static function handle_upload_template() {
        self::guard_admin_action( 'ufsc_ffst_upload_template' );
        if ( empty( $_FILES['ufsc_ffst_template']['name'] ) ) {
            self::redirect_with_message( 'template_missing' );
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $uploaded = wp_handle_upload( $_FILES['ufsc_ffst_template'], array( 'test_form' => false ) );
        if ( ! empty( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
            self::redirect_with_message( 'template_upload_error' );
        }
        $ext = strtolower( pathinfo( $uploaded['file'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'xls', 'xlsx' ), true ) ) {
            @unlink( $uploaded['file'] );
            self::redirect_with_message( 'template_type_error' );
        }
        update_option( self::TEMPLATE_OPTION, array(
            'file' => $uploaded['file'],
            'url'  => isset( $uploaded['url'] ) ? esc_url_raw( $uploaded['url'] ) : '',
            'name' => sanitize_file_name( wp_basename( $uploaded['file'] ) ),
        ), false );
        self::redirect_with_message( 'template_saved' );
    }

    public static function handle_generate_licences() {
        self::guard_admin_action( 'ufsc_ffst_generate_licences' );
        $club_id = isset( $_POST['club_id'] ) ? absint( $_POST['club_id'] ) : 0;
        $season  = isset( $_POST['season'] ) ? sanitize_text_field( wp_unslash( $_POST['season'] ) ) : '';
        $template = get_option( self::TEMPLATE_OPTION, array() );
        $template_file = is_array( $template ) && ! empty( $template['file'] ) ? (string) $template['file'] : '';
        if ( ! $club_id || ! $season || ! $template_file || ! is_readable( $template_file ) ) {
            self::redirect_with_message( 'generation_missing_data', $club_id );
        }
        if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\IOFactory' ) ) {
            self::redirect_with_message( 'spreadsheet_unavailable', $club_id );
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $template_file );
            $sheet = $spreadsheet->getActiveSheet();
            $mapping = self::detect_columns( $sheet );
            if ( empty( $mapping['nom'] ) || empty( $mapping['prenom'] ) ) {
                self::redirect_with_message( 'template_headers_missing', $club_id );
            }
            $licences = self::get_licences( $club_id, $season );
            $rows = array_values( array_filter( $licences, array( __CLASS__, 'is_ffst_official_person' ) ) );
            $row = (int) $mapping['_header_row'] + 1;
            foreach ( $rows as $licence ) {
                self::write_person_row( $sheet, $row, $mapping, $licence, $season );
                $row++;
            }

            $club = self::get_club( $club_id );
            $club_name = $club ? self::value( $club, array( 'nom', 'name', 'club_name' ) ) : 'club-' . $club_id;
            $safe_club = sanitize_title( $club_name ?: 'club-' . $club_id );
            $uploads = wp_upload_dir();
            $dir = trailingslashit( $uploads['basedir'] ) . 'ufsc-ffst-generated';
            wp_mkdir_p( $dir );
            $filename = 'ffst-dirigeants-' . $safe_club . '-' . sanitize_title( $season ) . '-' . gmdate( 'Ymd-His' ) . '.xlsx';
            $target = trailingslashit( $dir ) . $filename;
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter( $spreadsheet, 'Xlsx' );
            $writer->save( $target );

            if ( function_exists( 'ufsc_audit_log' ) ) {
                ufsc_audit_log( 'ffst_document_generated', array( 'club_id' => $club_id, 'season' => $season, 'file' => $filename, 'rows' => count( $rows ) ) );
            }

            nocache_headers();
            header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . filesize( $target ) );
            readfile( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
            exit;
        } catch ( Throwable $e ) {
            if ( function_exists( 'ufsc_log_error' ) ) { ufsc_log_error( 'FFST generation error: ' . $e->getMessage() ); }
            self::redirect_with_message( 'generation_error', $club_id );
        }
    }

    private static function render_template_box() {
        $template = get_option( self::TEMPLATE_OPTION, array() );
        echo '<div class="postbox" style="padding:18px;margin:18px 0;max-width:900px;">';
        echo '<h2 style="margin-top:0;">' . esc_html__( 'Modèle officiel FFST — bordereau licences', 'ufsc-clubs' ) . '</h2>';
        if ( is_array( $template ) && ! empty( $template['file'] ) && is_readable( $template['file'] ) ) {
            echo '<p><span style="color:#008a20;font-weight:700;">✓ ' . esc_html__( 'Modèle chargé :', 'ufsc-clubs' ) . '</span> ' . esc_html( $template['name'] ?? wp_basename( $template['file'] ) ) . '</p>';
        } else {
            echo '<p><strong>' . esc_html__( 'Aucun modèle Excel FFST chargé.', 'ufsc-clubs' ) . '</strong> ' . esc_html__( 'Chargez le fichier officiel reçu de la FFST avant génération.', 'ufsc-clubs' ) . '</p>';
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="ufsc_ffst_upload_template">';
        wp_nonce_field( 'ufsc_ffst_upload_template' );
        echo '<input type="file" name="ufsc_ffst_template" accept=".xls,.xlsx" required> ';
        echo '<button class="button">' . esc_html__( 'Charger / remplacer le modèle FFST', 'ufsc-clubs' ) . '</button>';
        echo '</form></div>';
    }

    private static function get_clubs() {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $name_col = in_array( 'nom', $columns, true ) ? 'nom' : ( in_array( 'name', $columns, true ) ? 'name' : '' );
        $order = $name_col ? " ORDER BY `{$name_col}` ASC" : '';
        return (array) $wpdb->get_results( "SELECT * FROM `{$table}`{$order}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function get_club( $club_id ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $pk = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) ) : 'id';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$pk}`=%d LIMIT 1", $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function get_licences( $club_id, $season ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $club_col = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : 'club_id' );
        $season_col = in_array( 'season', $columns, true ) ? 'season' : ( in_array( 'saison', $columns, true ) ? 'saison' : '' );
        if ( $season_col ) {
            return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND `{$season_col}`=%s", $club_id, $season ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d", $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function build_readiness( $club, $club_id, $season ) {
        $licences = self::get_licences( $club_id, $season );
        $fields = array(
            'Nom du club' => self::value( $club, array( 'nom', 'name', 'club_name' ) ),
            'Adresse du siège' => self::value( $club, array( 'adresse', 'adresse_siege', 'adresse_complete', 'address' ) ),
            'Téléphone' => self::value( $club, array( 'telephone', 'tel', 'phone' ) ),
            'Mail club' => self::value( $club, array( 'email', 'mail', 'club_email' ) ),
            'Site Internet' => self::value( $club, array( 'site', 'site_web', 'website', 'url' ) ),
            'Déclaration préfecture' => self::value( $club, array( 'numero_recepisse', 'declaration_prefecture', 'numero_declaration', 'rna' ) ),
            'Adresse salle d’entraînement' => self::value( $club, array( 'adresse_salle', 'salle_adresse', 'training_address' ) ),
        );
        $filled = count( array_filter( $fields, static function( $value ) { return '' !== trim( (string) $value ); } ) );
        $roles = array(
            'Président' => self::find_role_licence( $licences, array( 'president', 'président' ) ),
            'Secrétaire' => self::find_role_licence( $licences, array( 'secretaire', 'secrétaire' ) ),
            'Trésorier' => self::find_role_licence( $licences, array( 'tresorier', 'trésorier' ) ),
            'Entraîneur / instructeur' => self::find_role_licence( $licences, array( 'entraineur', 'entraîneur', 'instructeur', 'coach' ) ),
        );
        $role_count = count( array_filter( $roles ) );
        $total = count( $licences );
        $percent = (int) round( ( ( $filled + $role_count + min( 10, $total ) ) / ( count( $fields ) + count( $roles ) + 10 ) ) * 100 );
        return array( 'fields' => $fields, 'roles' => $roles, 'licences' => $licences, 'licence_count' => $total, 'minimum_licences_ok' => $total >= 10, 'percent' => max( 0, min( 100, $percent ) ) );
    }

    private static function render_summary( $club, $season, $readiness ) {
        $name = self::value( $club, array( 'nom', 'name', 'club_name' ) );
        echo '<div class="ufsc-dashboard-cards" style="margin:20px 0;">';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Club', 'ufsc-clubs' ) . '</div><div class="card-value" style="font-size:20px;">' . esc_html( $name ) . '</div></div>';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Saison FFST', 'ufsc-clubs' ) . '</div><div class="card-value" style="font-size:20px;">' . esc_html( $season ) . '</div></div>';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Dossier prêt', 'ufsc-clubs' ) . '</div><div class="card-value">' . esc_html( $readiness['percent'] ) . '%</div></div>';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Licences saison', 'ufsc-clubs' ) . '</div><div class="card-value">' . esc_html( $readiness['licence_count'] ) . '/10</div></div>';
        echo '</div>';
    }

    private static function render_affiliation_section( $readiness ) {
        echo '<div class="postbox" style="padding:18px;margin-top:20px;"><h2 style="margin-top:0;">' . esc_html__( '1. Dossier affiliation / réaffiliation FFST', 'ufsc-clubs' ) . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Information officielle FFST', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Valeur UFSC', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'État', 'ufsc-clubs' ) . '</th></tr></thead><tbody>';
        foreach ( $readiness['fields'] as $label => $value ) {
            $ok = '' !== trim( (string) $value );
            echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( $value ?: '—' ) . '</td><td>' . ( $ok ? '<span style="color:#008a20;font-weight:700;">✓ Complet</span>' : '<span style="color:#b32d2e;font-weight:700;">À compléter</span>' ) . '</td></tr>';
        }
        foreach ( $readiness['roles'] as $label => $licence ) {
            echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( $licence ? self::person_name( $licence ) : '—' ) . '</td><td>' . ( $licence ? '<span style="color:#008a20;font-weight:700;">✓ Identifié</span>' : '<span style="color:#b32d2e;font-weight:700;">Licence dirigeant manquante</span>' ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function render_licences_section( $readiness ) {
        echo '<div class="postbox" style="padding:18px;"><h2 style="margin-top:0;">' . esc_html__( '2. Bordereau licences dirigeants FFST', 'ufsc-clubs' ) . '</h2>';
        echo $readiness['minimum_licences_ok']
            ? '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'Minimum FFST atteint : au moins 10 licences pour la saison.', 'ufsc-clubs' ) . '</strong></p></div>'
            : '<div class="notice notice-warning inline"><p><strong>' . esc_html( sprintf( __( 'Minimum FFST non atteint : %1$d licence(s) enregistrée(s), 10 requises.', 'ufsc-clubs' ), $readiness['licence_count'] ) ) . '</strong></p></div>';
        echo '<p>' . esc_html__( 'Le président, le secrétaire, le trésorier et le ou les entraîneurs doivent être identifiés parmi les licences de la saison avant génération du bordereau.', 'ufsc-clubs' ) . '</p></div>';
    }

    private static function render_documents_section( $club_id, $season, $readiness ) {
        $template = get_option( self::TEMPLATE_OPTION, array() );
        $template_ok = is_array( $template ) && ! empty( $template['file'] ) && is_readable( $template['file'] );
        echo '<div class="postbox" style="padding:18px;"><h2 style="margin-top:0;">' . esc_html__( '3. Génération des documents', 'ufsc-clubs' ) . '</h2>';
        echo '<p>' . esc_html__( 'Le bordereau est généré sur une copie du modèle FFST chargé : le modèle original reste intact.', 'ufsc-clubs' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="ufsc_ffst_generate_licences"><input type="hidden" name="club_id" value="' . esc_attr( $club_id ) . '"><input type="hidden" name="season" value="' . esc_attr( $season ) . '">';
        wp_nonce_field( 'ufsc_ffst_generate_licences' );
        $disabled = ( ! $template_ok || $readiness['percent'] < 100 ) ? ' disabled' : '';
        echo '<button class="button button-primary"' . $disabled . '>' . esc_html__( 'Générer le bordereau dirigeants FFST', 'ufsc-clubs' ) . '</button>';
        echo '</form>';
        if ( ! $template_ok ) { echo '<p class="description">' . esc_html__( 'Chargez d’abord le modèle Excel officiel FFST.', 'ufsc-clubs' ) . '</p>'; }
        echo '<p class="description">' . esc_html__( 'Aucune donnée ni document FFST n’est exposé dans l’espace du représentant du club.', 'ufsc-clubs' ) . '</p></div>';
    }

    private static function detect_columns( $sheet ) {
        $aliases = array(
            'nom' => array( 'nom', 'nom de famille' ), 'prenom' => array( 'prenom', 'prénom' ),
            'naissance' => array( 'date de naissance', 'naissance', 'date naissance' ),
            'fonction' => array( 'fonction', 'poste', 'qualite', 'qualité' ),
            'email' => array( 'email', 'e-mail', 'mail' ), 'numero' => array( 'numero licence', 'n° licence', 'licence', 'n licence' ),
            'saison' => array( 'saison' ),
        );
        for ( $row = 1; $row <= min( 30, $sheet->getHighestRow() ); $row++ ) {
            $found = array();
            for ( $col = 1; $col <= min( 30, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString( $sheet->getHighestColumn() ) ); $col++ ) {
                $text = self::normalize_header( (string) $sheet->getCellByColumnAndRow( $col, $row )->getFormattedValue() );
                foreach ( $aliases as $key => $terms ) {
                    foreach ( $terms as $term ) {
                        if ( $text === self::normalize_header( $term ) || false !== strpos( $text, self::normalize_header( $term ) ) ) { $found[ $key ] = $col; break 2; }
                    }
                }
            }
            if ( isset( $found['nom'], $found['prenom'] ) ) { $found['_header_row'] = $row; return $found; }
        }
        return array();
    }

    private static function write_person_row( $sheet, $row, $mapping, $licence, $season ) {
        $values = array(
            'nom' => self::value( $licence, array( 'nom', 'last_name' ) ), 'prenom' => self::value( $licence, array( 'prenom', 'first_name' ) ),
            'naissance' => self::value( $licence, array( 'date_naissance', 'birth_date', 'naissance' ) ),
            'fonction' => self::value( $licence, array( 'role', 'fonction', 'poste', 'position' ) ),
            'email' => self::value( $licence, array( 'email', 'mail' ) ),
            'numero' => self::value( $licence, array( 'numero_licence', 'licence_number', 'num_licence' ) ), 'saison' => $season,
        );
        foreach ( $values as $key => $value ) { if ( ! empty( $mapping[ $key ] ) ) { $sheet->setCellValueByColumnAndRow( $mapping[ $key ], $row, $value ); } }
    }

    private static function is_ffst_official_person( $licence ) {
        $role = self::normalize_header( self::value( $licence, array( 'role', 'fonction', 'poste', 'position' ) ) );
        foreach ( array( 'president', 'secretaire', 'tresorier', 'entraineur', 'instructeur', 'coach' ) as $needle ) { if ( false !== strpos( $role, $needle ) ) { return true; } }
        return false;
    }

    private static function normalize_header( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( remove_accents( (string) $value ) ) ) ); }

    private static function find_role_licence( $licences, $aliases ) {
        foreach ( (array) $licences as $licence ) {
            $role = self::normalize_header( self::value( $licence, array( 'role', 'fonction', 'poste', 'position' ) ) );
            foreach ( $aliases as $alias ) { if ( false !== strpos( $role, self::normalize_header( $alias ) ) ) { return $licence; } }
        }
        return null;
    }

    private static function person_name( $licence ) { return trim( self::value( $licence, array( 'prenom', 'first_name' ) ) . ' ' . self::value( $licence, array( 'nom', 'last_name' ) ) ); }
    private static function value( $object, $keys ) { foreach ( (array) $keys as $key ) { if ( is_object( $object ) && isset( $object->{$key} ) && '' !== trim( (string) $object->{$key} ) ) { return (string) $object->{$key}; } if ( is_array( $object ) && isset( $object[$key] ) && '' !== trim( (string) $object[$key] ) ) { return (string) $object[$key]; } } return ''; }

    private static function guard_admin_action( $nonce_action ) {
        if ( ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) { wp_die( esc_html__( 'Action FFST non autorisée.', 'ufsc-clubs' ) ); }
        check_admin_referer( $nonce_action );
    }
    private static function redirect_with_message( $message, $club_id = 0 ) {
        $url = add_query_arg( array_filter( array( 'page' => 'ufsc-ffst-documents', 'club_id' => $club_id, 'ufsc_ffst_message' => sanitize_key( $message ) ) ), admin_url( 'admin.php' ) );
        wp_safe_redirect( $url ); exit;
    }
    private static function render_flash_notice() {
        if ( empty( $_GET['ufsc_ffst_message'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $key = sanitize_key( wp_unslash( $_GET['ufsc_ffst_message'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $messages = array(
            'template_saved' => array( 'success', 'Modèle FFST enregistré.' ), 'template_missing' => array( 'error', 'Sélectionnez un fichier Excel FFST.' ),
            'template_upload_error' => array( 'error', 'Le modèle FFST n’a pas pu être chargé.' ), 'template_type_error' => array( 'error', 'Le modèle doit être un fichier .xls ou .xlsx.' ),
            'generation_missing_data' => array( 'error', 'Club, saison ou modèle FFST manquant.' ), 'spreadsheet_unavailable' => array( 'error', 'Le moteur Excel PhpSpreadsheet est indisponible.' ),
            'template_headers_missing' => array( 'error', 'Impossible de repérer les colonnes NOM et PRÉNOM dans le modèle FFST.' ), 'generation_error' => array( 'error', 'La génération FFST a échoué. Consultez les journaux techniques.' ),
        );
        if ( isset( $messages[ $key ] ) ) { echo '<div class="notice notice-' . esc_attr( $messages[ $key ][0] ) . ' inline"><p>' . esc_html__( $messages[ $key ][1], 'ufsc-clubs' ) . '</p></div>'; }
    }
}

add_action( 'admin_post_ufsc_ffst_upload_template', array( 'UFSC_FFST_Documents_Admin', 'handle_upload_template' ) );
add_action( 'admin_post_ufsc_ffst_generate_licences', array( 'UFSC_FFST_Documents_Admin', 'handle_generate_licences' ) );
