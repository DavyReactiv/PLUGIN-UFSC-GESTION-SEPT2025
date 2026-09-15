<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Canonical licence export aligned with the admin/front licence forms.
 *
 * Read-only by design: this class never mutates licence, club, order or payment data.
 */
final class UFSC_Licences_Canonical_Export {
    private static $canonical_fields = array(
        'numero_licence'        => 'N° licence UFSC',
        'numero_licence_ffst'   => 'N° licence FFST',
        'role'                  => 'Rôle au club',
        'ville_naissance'       => 'Ville de naissance',
        'departement_naissance' => 'Département de naissance',
        'pays_naissance'        => 'Pays de naissance',
        'season'                => 'Saison',
        'age_export'            => 'Âge au jour de l’export',
        'moins_12_ans'          => 'Moins de 12 ans',
    );

    private static $legacy_hidden = array(
        'infos_fsasptt',
        'infos_asptt',
    );

    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'enhance_export_screen' ), 50 );
        add_action( 'admin_post_ufsc_export_data', array( __CLASS__, 'maybe_export' ), 0 );
    }

    private static function can_manage() {
        return current_user_can( 'read' )
            && function_exists( 'ufsc_user_can' )
            && class_exists( 'UFSC_Permissions' )
            && ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    private static function current_season() {
        if ( class_exists( 'UFSC_Season_Service' ) ) {
            return str_replace( '/', '-', (string) UFSC_Season_Service::get_current_season() );
        }
        return function_exists( 'ufsc_get_current_season' ) ? str_replace( '/', '-', (string) ufsc_get_current_season() ) : '';
    }

    private static function affiliations_table() {
        global $wpdb;
        if ( class_exists( 'UFSC_Storage_Resolver' ) && method_exists( 'UFSC_Storage_Resolver', 'get_annual_affiliations_table' ) ) {
            return preg_replace( '/[^A-Za-z0-9_]/', '', (string) UFSC_Storage_Resolver::get_annual_affiliations_table() );
        }
        return $wpdb->prefix . 'ufsc_affiliations_seasons';
    }

    private static function table_columns( $table ) {
        global $wpdb;
        if ( function_exists( 'ufsc_table_columns' ) ) {
            $columns = (array) ufsc_table_columns( $table );
            if ( $columns ) { return array_values( $columns ); }
        }
        return (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function enhance_export_screen() {
        if ( ! is_admin() || ! self::can_manage() ) { return; }
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-exports' !== $page ) { return; }
        ?>
        <script>
        (function(){
            var canonical=<?php echo wp_json_encode( self::$canonical_fields ); ?>;
            var hiddenLegacy=<?php echo wp_json_encode( self::$legacy_hidden ); ?>;
            function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
            ready(function(){
                var form=document.querySelector('form[action*="admin-post.php"] input[name="action"][value="ufsc_export_data"]');
                form=form?form.closest('form'):null;
                if(!form)return;
                var heading=Array.from(form.querySelectorAll('h4')).find(function(h){return /Colonnes à exporter/i.test(h.textContent||'');});
                if(!heading)return;
                var grid=heading.nextElementSibling;
                if(!grid)return;

                hiddenLegacy.forEach(function(key){
                    var input=grid.querySelector('input[name="export_columns[]"][value="'+key+'"]');
                    if(input&&input.closest('label'))input.closest('label').style.display='none';
                });

                Object.keys(canonical).forEach(function(key){
                    if(grid.querySelector('input[name="export_columns[]"][value="'+key+'"]'))return;
                    var label=document.createElement('label');
                    label.style.display='flex';label.style.alignItems='center';label.style.gap='5px';
                    var input=document.createElement('input');
                    input.type='checkbox';input.name='export_columns[]';input.value=key;input.checked=true;
                    label.appendChild(input);label.appendChild(document.createTextNode(' '+canonical[key]));grid.appendChild(label);
                });
            });
        })();
        </script>
        <?php
    }

    public static function maybe_export() {
        if ( ! self::can_manage() ) { return; }
        $entity = isset( $_POST['export_entity'] ) && ! is_array( $_POST['export_entity'] ) ? sanitize_key( wp_unslash( $_POST['export_entity'] ) ) : 'licences';
        if ( 'clubs' === $entity ) { return; }

        $requested = isset( $_POST['export_columns'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['export_columns'] ) ) : array();
        if ( ! array_intersect( array_keys( self::$canonical_fields ), $requested ) ) { return; }

        check_admin_referer( 'ufsc_export_data' );

        global $wpdb;
        $settings       = class_exists( 'UFSC_SQL' ) ? (array) UFSC_SQL::get_settings() : array();
        $licences_table = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['table_licences'] ?? '' ) );
        $clubs_table    = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['table_clubs'] ?? '' ) );
        if ( '' === $licences_table || '' === $clubs_table ) {
            wp_die( esc_html__( 'Tables licences/clubs indisponibles.', 'ufsc-clubs' ) );
        }

        $licence_columns = self::table_columns( $licences_table );
        $club_columns    = self::table_columns( $clubs_table );
        $has_club_id     = in_array( 'club_id', $licence_columns, true );
        $join            = $has_club_id ? " LEFT JOIN `{$clubs_table}` c ON c.id=l.club_id " : '';
        $where           = array( '1=1' );
        $params          = array();

        $filter_club = isset( $_POST['filter_club'] ) ? absint( wp_unslash( $_POST['filter_club'] ) ) : 0;
        if ( $filter_club && $has_club_id ) { $where[] = 'l.club_id=%d'; $params[] = $filter_club; }

        $filter_region = isset( $_POST['filter_region'] ) && ! is_array( $_POST['filter_region'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_region'] ) ) : '';
        if ( function_exists( 'ufsc_user_can' ) && class_exists( 'UFSC_Scope' ) ) {
            $scope_slug  = UFSC_Scope::get_user_scope_region();
            $scope_label = $scope_slug ? UFSC_Scope::get_region_label( $scope_slug ) : '';
            if ( $scope_slug ) { $filter_region = $scope_label ?: $scope_slug; }
        }
        if ( '' !== $filter_region && $join && in_array( 'region', $club_columns, true ) ) { $where[] = 'c.region=%s'; $params[] = $filter_region; }

        $filter_status = isset( $_POST['filter_status'] ) && ! is_array( $_POST['filter_status'] ) ? sanitize_key( wp_unslash( $_POST['filter_status'] ) ) : '';
        if ( '' !== $filter_status && in_array( 'statut', $licence_columns, true ) ) { $where[] = 'l.statut=%s'; $params[] = $filter_status; }

        $visibility = isset( $_POST['filter_visibility'] ) && ! is_array( $_POST['filter_visibility'] ) ? sanitize_key( wp_unslash( $_POST['filter_visibility'] ) ) : 'active';
        if ( in_array( 'deleted_at', $licence_columns, true ) ) {
            if ( 'trash' === $visibility ) { $where[] = "l.deleted_at IS NOT NULL AND l.deleted_at<>'0000-00-00 00:00:00'"; }
            elseif ( 'all' !== $visibility ) { $where[] = "(l.deleted_at IS NULL OR l.deleted_at='0000-00-00 00:00:00')"; }
        }

        $filter_season = isset( $_POST['filter_season'] ) && ! is_array( $_POST['filter_season'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_season'] ) ) : self::current_season();
        if ( '' === $filter_season || '__current' === $filter_season ) { $filter_season = self::current_season(); }
        $filter_season = str_replace( '/', '-', $filter_season );

        if ( 'all' !== $filter_season && '' !== $filter_season ) {
            $season_column = '';
            foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $candidate ) {
                if ( in_array( $candidate, $licence_columns, true ) ) { $season_column = $candidate; break; }
            }
            if ( $season_column ) { $where[] = "REPLACE(l.`{$season_column}`,'/','-')=%s"; $params[] = $filter_season; }
        }

        $club_affiliation = isset( $_POST['filter_club_affiliation'] ) && ! is_array( $_POST['filter_club_affiliation'] )
            ? sanitize_key( wp_unslash( $_POST['filter_club_affiliation'] ) )
            : 'active';
        if ( ! in_array( $club_affiliation, array( 'active', 'inactive', 'all' ), true ) ) { $club_affiliation = 'active'; }

        if ( $has_club_id && 'all' !== $club_affiliation ) {
            $affiliation_season = ( 'all' === $filter_season || '' === $filter_season ) ? self::current_season() : $filter_season;
            $affiliations_table = self::affiliations_table();
            $active_statuses    = array( 'active', 'validated', 'valide' );
            $placeholders       = implode( ',', array_fill( 0, count( $active_statuses ), '%s' ) );
            $active_sql         = "EXISTS (SELECT 1 FROM `{$affiliations_table}` a WHERE a.club_id=l.club_id AND REPLACE(a.season,'/','-')=%s AND LOWER(a.status) IN ({$placeholders}))";
            $where[]            = ( 'active' === $club_affiliation ) ? $active_sql : 'NOT ' . $active_sql;
            $params[]           = $affiliation_season;
            foreach ( $active_statuses as $active_status ) { $params[] = $active_status; }
        }

        $sql = "SELECT l.*";
        if ( $join ) {
            $sql .= in_array( 'nom', $club_columns, true ) ? ', c.nom AS _club_nom' : ", '' AS _club_nom";
            $sql .= in_array( 'region', $club_columns, true ) ? ', c.region AS _club_region' : ", '' AS _club_region";
        } else {
            $sql .= ", '' AS _club_nom, '' AS _club_region";
        }
        $sql .= " FROM `{$licences_table}` l {$join} WHERE " . implode( ' AND ', $where ) . ' ORDER BY l.id ASC';
        if ( $params ) { $sql = $wpdb->prepare( $sql, $params ); }
        $rows = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

        $available = array_fill_keys( $licence_columns, true );
        $selected  = array();
        foreach ( $requested as $key ) {
            if ( isset( self::$canonical_fields[ $key ] ) || isset( $available[ $key ] ) || in_array( $key, array( 'club_nom', 'region' ), true ) ) {
                if ( ! in_array( $key, self::$legacy_hidden, true ) ) { $selected[] = $key; }
            }
        }
        $selected = array_values( array_unique( $selected ) );
        if ( ! $selected ) { wp_die( esc_html__( 'Sélectionnez au moins une colonne à exporter.', 'ufsc-clubs' ) ); }

        $labels = self::labels_for( $selected );
        $data   = array();
        foreach ( $rows as $row ) {
            $line = array();
            foreach ( $selected as $key ) { $line[] = self::value_for( $row, $key ); }
            $data[] = $line;
        }

        $format = isset( $_POST['export_format'] ) && ! is_array( $_POST['export_format'] ) ? sanitize_key( wp_unslash( $_POST['export_format'] ) ) : 'csv';
        self::send_export( $format, $labels, $data );
    }

    private static function labels_for( array $selected ) {
        $known = array(
            'id'=>'ID','nom'=>'Nom','prenom'=>'Prénom','email'=>'Email','date_naissance'=>'Date de naissance','sexe'=>'Sexe',
            'adresse'=>'Adresse','suite_adresse'=>'Suite adresse','code_postal'=>'Code postal','ville'=>'Ville','tel_fixe'=>'Téléphone fixe','tel_mobile'=>'Téléphone mobile',
            'profession'=>'Profession','fonction_publique'=>'Fonction publique','diffusion_image'=>'Diffusion image','infos_cr'=>'Recevoir infos Comité Régional',
            'infos_partenaires'=>'Recevoir infos partenaires','honorabilite'=>'Soumis à l’honorabilité','competition'=>'Compétition','note'=>'Note',
            'statut'=>'Statut','club_nom'=>'Nom du club','region'=>'Région','fighter_level'=>'Niveau sportif'
        );
        $labels = array();
        foreach ( $selected as $key ) {
            $labels[] = self::$canonical_fields[ $key ] ?? ( $known[ $key ] ?? ucfirst( str_replace( '_', ' ', $key ) ) );
        }
        return $labels;
    }

    private static function value_for( array $row, $key ) {
        if ( 'club_nom' === $key ) { return (string) ( $row['_club_nom'] ?? '' ); }
        if ( 'region' === $key ) { return (string) ( $row['_club_region'] ?? '' ); }
        if ( 'numero_licence' === $key ) {
            foreach ( array( 'numero_licence', 'num_licence', 'licence_number', 'numero' ) as $candidate ) {
                if ( isset( $row[ $candidate ] ) && '' !== (string) $row[ $candidate ] ) { return (string) $row[ $candidate ]; }
            }
            return '';
        }
        if ( 'numero_licence_ffst' === $key ) { return (string) ( $row['numero_licence_ffst'] ?? '' ); }
        if ( 'season' === $key ) {
            foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $candidate ) {
                if ( isset( $row[ $candidate ] ) && '' !== (string) $row[ $candidate ] ) { return (string) $row[ $candidate ]; }
            }
            return '';
        }
        if ( 'role' === $key ) {
            $role = sanitize_key( (string) ( $row['role'] ?? 'adherent' ) );
            $labels = array(
                'adherent'=>'Adhérent / pratiquant','president'=>'Président','secretaire'=>'Secrétaire','tresorier'=>'Trésorier','dirigeant'=>'Dirigeant',
                'entraineur'=>'Entraîneur','encadrant'=>'Encadrant','responsable_technique'=>'Responsable technique','instructeur'=>'Instructeur','coach'=>'Coach',
                'educateur'=>'Éducateur','enseignant'=>'Enseignant'
            );
            return $labels[ $role ] ?? ucfirst( str_replace( '_', ' ', $role ) );
        }
        if ( 'age_export' === $key || 'moins_12_ans' === $key ) {
            $age = self::age_from_birthdate( $row['date_naissance'] ?? '' );
            if ( null === $age ) { return ''; }
            return 'age_export' === $key ? (string) $age : ( $age < 12 ? 'Oui' : 'Non' );
        }
        $booleans = array( 'fonction_publique','diffusion_image','infos_cr','infos_partenaires','honorabilite','competition','licence_delegataire','reduction_benevole','reduction_postier','assurance_dommage_corporel','assurance_assistance' );
        if ( in_array( $key, $booleans, true ) ) { return ! empty( $row[ $key ] ) ? 'Oui' : 'Non'; }
        if ( 'fighter_level' === $key && function_exists( 'ufsc_fighter_level_label' ) ) { return ufsc_fighter_level_label( $row[ $key ] ?? '' ); }
        if ( 'statut' === $key && function_exists( 'ufsc_license_status_label' ) ) { return ufsc_license_status_label( $row[ $key ] ?? '' ); }
        return isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
    }

    /**
     * Calculate age on the local WordPress export date from the stored birth date.
     * Returns null for a missing, invalid or future birth date so the export never
     * classifies an uncertain record as an under-12 licence.
     */
    private static function age_from_birthdate( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) { return null; }

        $year = $month = $day = 0;
        if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
            $year = (int) $matches[1]; $month = (int) $matches[2]; $day = (int) $matches[3];
        } elseif ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches ) ) {
            $day = (int) $matches[1]; $month = (int) $matches[2]; $year = (int) $matches[3];
        } else {
            return null;
        }
        if ( ! checkdate( $month, $day, $year ) ) { return null; }

        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : null;
        $birth     = new DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, $day ), $timezone );
        $today     = new DateTimeImmutable( current_time( 'Y-m-d' ), $timezone );
        if ( $birth > $today ) { return null; }

        return (int) $birth->diff( $today )->y;
    }

    private static function send_export( $format, array $headers, array $rows ) {
        $stamp = gmdate( 'Ymd-His' );
        if ( 'xlsx' === $format && class_exists( 'PhpOffice\\PhpSpreadsheet\\Spreadsheet' ) ) {
            $filename = 'ufsc-licences-' . $stamp . '.xlsx';
            nocache_headers();
            header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $active = $sheet->getActiveSheet();
            foreach ( $headers as $index => $label ) { $active->setCellValueByColumnAndRow( $index + 1, 1, $label ); }
            foreach ( $rows as $row_index => $row ) {
                foreach ( $row as $col_index => $value ) { $active->setCellValueByColumnAndRow( $col_index + 1, $row_index + 2, $value ); }
            }
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $sheet );
            $writer->save( 'php://output' );
            exit;
        }

        $filename = 'ufsc-licences-' . $stamp . '.csv';
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, $headers, ';' );
        foreach ( $rows as $row ) { fputcsv( $out, $row, ';' ); }
        fclose( $out );
        exit;
    }
}
