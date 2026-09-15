<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Export Clubs complet et configurable depuis l'écran Exports.
 * Lecture seule : aucune donnée club/licence/affiliation n'est modifiée.
 */
final class UFSC_Clubs_Flexible_Export {

    public static function init() {
        add_action( 'admin_post_ufsc_export_data', array( __CLASS__, 'maybe_handle_clubs_export' ), 1 );
        add_action( 'admin_footer', array( __CLASS__, 'enhance_exports_screen' ), 40 );
    }

    private static function can_manage() {
        return class_exists( 'UFSC_Permissions' ) && function_exists( 'ufsc_user_can' )
            && ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    private static function clubs_table() {
        global $wpdb;
        if ( class_exists( 'UFSC_Storage_Resolver' ) && method_exists( 'UFSC_Storage_Resolver', 'get_clubs_table' ) ) {
            return UFSC_Storage_Resolver::get_clubs_table();
        }
        if ( class_exists( 'UFSC_SQL' ) ) {
            $settings = UFSC_SQL::get_settings();
            if ( ! empty( $settings['table_clubs'] ) ) { return $settings['table_clubs']; }
        }
        return $wpdb->prefix . 'ufsc_clubs';
    }

    private static function licences_table() {
        global $wpdb;
        if ( class_exists( 'UFSC_Storage_Resolver' ) && method_exists( 'UFSC_Storage_Resolver', 'get_licences_table' ) ) {
            return UFSC_Storage_Resolver::get_licences_table();
        }
        $settings = class_exists( 'UFSC_SQL' ) ? (array) UFSC_SQL::get_settings() : array();
        return ! empty( $settings['table_licences'] ) ? $settings['table_licences'] : $wpdb->prefix . 'ufsc_licences';
    }

    private static function affiliations_table() {
        global $wpdb;
        if ( class_exists( 'UFSC_Storage_Resolver' ) && method_exists( 'UFSC_Storage_Resolver', 'get_annual_affiliations_table' ) ) {
            return UFSC_Storage_Resolver::get_annual_affiliations_table();
        }
        return $wpdb->prefix . 'ufsc_affiliations_seasons';
    }

    private static function current_season() {
        if ( class_exists( 'UFSC_Season_Service' ) ) { return (string) UFSC_Season_Service::get_current_season(); }
        return function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '';
    }

    private static function columns() {
        global $wpdb;
        $table = self::clubs_table();
        if ( function_exists( 'ufsc_table_columns' ) ) {
            $columns = array_values( array_filter( (array) ufsc_table_columns( $table ) ) );
            if ( $columns ) { return $columns; }
        }
        return array_values( array_filter( (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function labels() {
        $labels = array();
        if ( class_exists( 'UFSC_SQL' ) ) {
            foreach ( (array) UFSC_SQL::get_club_fields() as $key => $conf ) {
                $labels[ $key ] = is_array( $conf ) && isset( $conf[0] ) ? (string) $conf[0] : self::pretty_label( $key );
            }
        }
        foreach ( self::columns() as $column ) {
            if ( ! isset( $labels[ $column ] ) ) { $labels[ $column ] = self::pretty_label( $column ); }
        }
        return $labels;
    }

    private static function pretty_label( $key ) {
        $special = array(
            'id' => 'ID',
            'rna_number' => 'RNA',
            'num_declaration' => 'N° déclaration',
            'numero_affiliation_ufsc' => 'N° affiliation UFSC',
            'numero_affiliation_ffst' => 'N° affiliation FFST',
            'url_site' => 'Site Internet',
            'url_facebook' => 'Facebook',
            'url_instagram' => 'Instagram',
            'date_creation' => 'Créé le',
        );
        return isset( $special[ $key ] ) ? $special[ $key ] : ucfirst( str_replace( '_', ' ', (string) $key ) );
    }

    public static function enhance_exports_screen() {
        if ( ! is_admin() || ! self::can_manage() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-exports' !== $page ) { return; }

        $columns = self::columns();
        $labels  = self::labels();
        $season  = self::current_season();
        $club_fields = array();
        foreach ( $columns as $column ) {
            $club_fields[] = array( 'key' => $column, 'label' => $labels[ $column ] ?? self::pretty_label( $column ) );
        }
        ?>
        <style>
            .ufsc-export-entity-switch{display:flex;gap:10px;align-items:end;margin:14px 0 18px;padding:14px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
            .ufsc-export-entity-switch label{display:grid;gap:5px;min-width:240px}
            #ufsc-club-export-filters,#ufsc-club-export-columns{margin:16px 0;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
            #ufsc-club-export-filters h4,#ufsc-club-export-columns h4{margin:0 0 14px}
            .ufsc-club-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px 16px}
            .ufsc-club-filter-grid label{display:grid!important;gap:5px!important;align-items:initial!important}
            .ufsc-club-filter-grid input,.ufsc-club-filter-grid select{width:100%;min-height:38px}
            #ufsc-club-export-columns .ufsc-club-export-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:9px 16px}
            #ufsc-club-export-columns .ufsc-club-export-grid label{display:flex;align-items:center;gap:7px;min-width:0}
            .ufsc-export-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px}
            .ufsc-club-filter-help{margin:12px 0 0;color:#646970}
        </style>
        <script>
        (function(){
            var fields=<?php echo wp_json_encode( $club_fields ); ?>;
            var currentSeason=<?php echo wp_json_encode( $season ); ?>;
            function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
            function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
            ready(function(){
                var actionInput=document.querySelector('form[action*="admin-post.php"] input[name="action"][value="ufsc_export_data"]');
                if(!actionInput)return;
                var form=actionInput.closest('form'); if(!form)return;
                var heading=Array.from(form.querySelectorAll('h4')).find(function(h){return /Colonnes à exporter/i.test(h.textContent||'');});
                if(!heading)return;
                var licenceBox=heading.parentElement;

                var switcher=document.createElement('div'); switcher.className='ufsc-export-entity-switch';
                switcher.innerHTML='<label><strong>Type de données</strong><select name="export_entity" id="ufsc_export_entity"><option value="licences">Licences</option><option value="clubs">Clubs</option></select></label><span class="description">Pour Clubs, les filtres ci-dessous déterminent exactement le périmètre exporté.</span>';
                licenceBox.parentNode.insertBefore(switcher,licenceBox);

                var filterBox=document.createElement('div'); filterBox.id='ufsc-club-export-filters'; filterBox.style.display='none';
                filterBox.innerHTML='<h4>Filtres Clubs</h4><div class="ufsc-club-filter-grid">'
                    +'<label><strong>Saison</strong><input type="text" name="club_filter_season" value="'+esc(currentSeason)+'" pattern="[0-9]{4}-[0-9]{4}" placeholder="2026-2027"></label>'
                    +'<label><strong>Affiliation annuelle</strong><select name="club_filter_affiliation"><option value="all">Tous les clubs</option><option value="active">Actifs</option><option value="pending_payment">Paiement en attente</option><option value="pending">En attente</option><option value="refused">Refusés</option><option value="none">Sans affiliation</option><option value="other">Autre statut</option></select></label>'
                    +'<label><strong>Région</strong><select name="club_filter_region" id="ufsc_club_filter_region"><option value="">Toutes les régions</option></select></label>'
                    +'<label><strong>N° affiliation</strong><select name="club_filter_number"><option value="all">Tous</option><option value="assigned">Attribué</option><option value="missing">Manquant</option></select></label>'
                    +'<label><strong>Licences de la saison</strong><select name="club_filter_licences"><option value="all">Toutes</option><option value="with">Avec au moins 1 licence</option><option value="without">Sans licence</option><option value="under10">Moins de 10 licences</option><option value="tenplus">10 licences ou plus</option></select></label>'
                    +'<label><strong>Recherche club</strong><input type="search" name="club_filter_search" placeholder="Nom, ID, email, n° affiliation…"></label>'
                    +'</div><p class="ufsc-club-filter-help">Exemple : Saison '+esc(currentSeason)+' + Affiliation annuelle « Actifs » = export uniquement des clubs actifs de cette saison.</p>';
                licenceBox.parentNode.insertBefore(filterBox,licenceBox);

                var oldRegion=form.querySelector('select[name="filter_region"]');
                var newRegion=filterBox.querySelector('#ufsc_club_filter_region');
                if(oldRegion&&newRegion){Array.from(oldRegion.options).forEach(function(o){if(!o.value)return;var n=document.createElement('option');n.value=o.value;n.textContent=o.textContent;newRegion.appendChild(n);});}

                var clubBox=document.createElement('div'); clubBox.id='ufsc-club-export-columns'; clubBox.style.display='none';
                var html='<h4>Colonnes Clubs à exporter</h4><div class="ufsc-export-toolbar"><button type="button" class="button" data-club-export="all">Tout sélectionner</button><button type="button" class="button" data-club-export="none">Tout désélectionner</button></div><div class="ufsc-club-export-grid">';
                fields.forEach(function(f){html+='<label><input type="checkbox" name="club_export_columns[]" value="'+esc(f.key)+'" checked> '+esc(f.label)+'</label>';});
                html+='</div>';
                clubBox.innerHTML=html;
                licenceBox.parentNode.insertBefore(clubBox,licenceBox.nextSibling);

                var select=switcher.querySelector('#ufsc_export_entity');
                function toggle(){var clubs=select.value==='clubs'; licenceBox.style.display=clubs?'none':''; filterBox.style.display=clubs?'':'none'; clubBox.style.display=clubs?'':'none';}
                select.addEventListener('change',toggle); toggle();
                clubBox.addEventListener('click',function(e){var mode=e.target&&e.target.getAttribute('data-club-export');if(!mode)return;clubBox.querySelectorAll('input[name="club_export_columns[]"]').forEach(function(cb){cb.checked=mode==='all';});});
            });
        })();
        </script>
        <?php
    }

    private static function append_affiliation_filter( &$where, &$params, $table, $season, $filter ) {
        global $wpdb;
        if ( 'all' === $filter || '' === $season ) { return; }
        $aff = self::affiliations_table();
        $active = array( 'active', 'validated', 'valide' );
        $pending_payment = array( 'pending_payment', 'awaiting_payment', 'payment_pending' );
        $pending = array( 'pending', 'en_attente', 'submitted', 'processing' );
        $refused = array( 'refused', 'refuse', 'rejected' );

        if ( 'none' === $filter ) {
            $where[] = "NOT EXISTS (SELECT 1 FROM `{$aff}` a WHERE a.club_id=`{$table}`.id AND a.season=%s)";
            $params[] = $season;
            return;
        }
        $statuses = array();
        if ( 'active' === $filter ) { $statuses = $active; }
        elseif ( 'pending_payment' === $filter ) { $statuses = $pending_payment; }
        elseif ( 'pending' === $filter ) { $statuses = $pending; }
        elseif ( 'refused' === $filter ) { $statuses = $refused; }

        if ( $statuses ) {
            $ph = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $where[] = "EXISTS (SELECT 1 FROM `{$aff}` a WHERE a.club_id=`{$table}`.id AND a.season=%s AND LOWER(a.status) IN ({$ph}))";
            $params[] = $season;
            foreach ( $statuses as $status ) { $params[] = $status; }
            return;
        }
        if ( 'other' === $filter ) {
            $known = array_merge( $active, $pending_payment, $pending, $refused );
            $ph = implode( ',', array_fill( 0, count( $known ), '%s' ) );
            $where[] = "EXISTS (SELECT 1 FROM `{$aff}` a WHERE a.club_id=`{$table}`.id AND a.season=%s AND COALESCE(a.status,'')<>'' AND LOWER(a.status) NOT IN ({$ph}))";
            $params[] = $season;
            foreach ( $known as $status ) { $params[] = $status; }
        }
    }

    private static function append_licence_filter( &$where, &$params, $table, $season, $filter ) {
        if ( 'all' === $filter || '' === $season ) { return; }
        $licences = self::licences_table();
        $context = function_exists( 'ufsc_get_pack_season_storage_context' )
            ? (array) ufsc_get_pack_season_storage_context( $licences, $season )
            : array( 'column' => 'season', 'value' => $season );
        $column = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $context['column'] ?? '' ) );
        $value  = (string) ( $context['value'] ?? '' );
        if ( '' === $column ) { return; }
        $count_sql = "(SELECT COUNT(*) FROM `{$licences}` l WHERE l.club_id=`{$table}`.id AND l.`{$column}`=%s)";
        if ( 'with' === $filter ) { $where[] = $count_sql . ' >= 1'; }
        elseif ( 'without' === $filter ) { $where[] = $count_sql . ' = 0'; }
        elseif ( 'under10' === $filter ) { $where[] = $count_sql . ' < 10'; }
        elseif ( 'tenplus' === $filter ) { $where[] = $count_sql . ' >= 10'; }
        else { return; }
        $params[] = $value;
    }

    public static function maybe_handle_clubs_export() {
        $entity = isset( $_POST['export_entity'] ) ? sanitize_key( wp_unslash( $_POST['export_entity'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( 'clubs' !== $entity ) { return; }
        if ( ! self::can_manage() ) { wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'ufsc_export_data' );

        global $wpdb;
        $table   = self::clubs_table();
        $columns = self::columns();
        $labels  = self::labels();
        if ( ! $columns ) { wp_die( esc_html__( 'Aucune colonne club disponible.', 'ufsc-clubs' ) ); }

        $requested = isset( $_POST['club_export_columns'] ) && is_array( $_POST['club_export_columns'] )
            ? array_map( 'sanitize_key', wp_unslash( $_POST['club_export_columns'] ) ) : $columns;
        $selected = array_values( array_intersect( $columns, $requested ) );
        if ( ! $selected ) { wp_die( esc_html__( 'Sélectionnez au moins une colonne club à exporter.', 'ufsc-clubs' ), '', array( 'response' => 400 ) ); }

        $where = array( '1=1' );
        $params = array();
        $scope = class_exists( 'UFSC_Scope' ) ? UFSC_Scope::build_scope_condition( 'region' ) : '';
        if ( $scope ) { $where[] = '(' . $scope . ')'; }

        $filter_club = isset( $_POST['filter_club'] ) ? absint( wp_unslash( $_POST['filter_club'] ) ) : 0;
        if ( $filter_club && in_array( 'id', $columns, true ) ) { $where[] = '`id`=%d'; $params[] = $filter_club; }

        $region = isset( $_POST['club_filter_region'] ) ? sanitize_text_field( wp_unslash( $_POST['club_filter_region'] ) ) : '';
        if ( '' === $region && isset( $_POST['filter_region'] ) ) { $region = sanitize_text_field( wp_unslash( $_POST['filter_region'] ) ); }
        if ( $region && in_array( 'region', $columns, true ) ) { $where[] = '`region`=%s'; $params[] = $region; }

        $season = isset( $_POST['club_filter_season'] ) ? sanitize_text_field( wp_unslash( $_POST['club_filter_season'] ) ) : self::current_season();
        if ( function_exists( 'ufsc_normalize_season_reference' ) ) { $season = (string) ufsc_normalize_season_reference( $season ); }
        if ( ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) { $season = self::current_season(); }

        $affiliation_filter = isset( $_POST['club_filter_affiliation'] ) ? sanitize_key( wp_unslash( $_POST['club_filter_affiliation'] ) ) : 'all';
        self::append_affiliation_filter( $where, $params, $table, $season, $affiliation_filter );

        $number_filter = isset( $_POST['club_filter_number'] ) ? sanitize_key( wp_unslash( $_POST['club_filter_number'] ) ) : 'all';
        if ( in_array( $number_filter, array( 'assigned', 'missing' ), true ) ) {
            $aff = self::affiliations_table();
            $number_condition = 'assigned' === $number_filter
                ? "a.num_affiliation IS NOT NULL AND a.num_affiliation<>''"
                : "(a.num_affiliation IS NULL OR a.num_affiliation='')";
            $where[] = "EXISTS (SELECT 1 FROM `{$aff}` a WHERE a.club_id=`{$table}`.id AND a.season=%s AND {$number_condition})";
            $params[] = $season;
        }

        $licence_filter = isset( $_POST['club_filter_licences'] ) ? sanitize_key( wp_unslash( $_POST['club_filter_licences'] ) ) : 'all';
        self::append_licence_filter( $where, $params, $table, $season, $licence_filter );

        $search = isset( $_POST['club_filter_search'] ) ? sanitize_text_field( wp_unslash( $_POST['club_filter_search'] ) ) : '';
        if ( '' !== $search ) {
            $parts = array();
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            foreach ( array( 'nom', 'email', 'ville', 'numero_affiliation_ffst', 'numero_affiliation_ufsc', 'num_affiliation' ) as $column ) {
                if ( in_array( $column, $columns, true ) ) { $parts[] = "`{$column}` LIKE %s"; $params[] = $like; }
            }
            if ( ctype_digit( $search ) && in_array( 'id', $columns, true ) ) { $parts[] = '`id`=%d'; $params[] = absint( $search ); }
            if ( $parts ) { $where[] = '(' . implode( ' OR ', $parts ) . ')'; }
        }

        $visibility = isset( $_POST['filter_visibility'] ) ? sanitize_key( wp_unslash( $_POST['filter_visibility'] ) ) : 'active';
        if ( in_array( 'deleted_at', $columns, true ) ) {
            if ( 'trash' === $visibility ) { $where[] = "`deleted_at` IS NOT NULL AND `deleted_at`<>'' AND `deleted_at`<>'0000-00-00 00:00:00'"; }
            elseif ( 'all' !== $visibility ) { $where[] = "(`deleted_at` IS NULL OR `deleted_at`='' OR `deleted_at`='0000-00-00 00:00:00')"; }
        }

        $select_sql = implode( ', ', array_map( static function( $column ) { return '`' . str_replace( '`', '', $column ) . '`'; }, $selected ) );
        $where_sql  = implode( ' AND ', $where );
        $order_col  = in_array( 'nom', $columns, true ) ? 'nom' : ( in_array( 'id', $columns, true ) ? 'id' : $selected[0] );
        $sql = "SELECT {$select_sql} FROM `{$table}` WHERE {$where_sql} ORDER BY `{$order_col}` ASC";
        if ( $params ) { $sql = $wpdb->prepare( $sql, $params ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ( ! $rows ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'ufsc-exports', 'error' => rawurlencode( __( 'Aucun club ne correspond aux filtres sélectionnés.', 'ufsc-clubs' ) ) ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $headers = array();
        foreach ( $selected as $column ) { $headers[] = $labels[ $column ] ?? self::pretty_label( $column ); }
        $format   = isset( $_POST['export_format'] ) ? sanitize_key( wp_unslash( $_POST['export_format'] ) ) : 'csv';
        $filename = 'ufsc_clubs_' . sanitize_file_name( $season ) . '_' . gmdate( 'Y-m-d_H-i-s' );
        if ( 'xlsx' === $format ) { self::output_xlsx( $rows, $headers, $filename ); }
        self::output_csv( $rows, $headers, $filename );
    }

    private static function output_csv( array $rows, array $headers, $filename ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, $headers, ';' );
        foreach ( $rows as $row ) { fputcsv( $out, array_values( $row ), ';' ); }
        fclose( $out );
        exit;
    }

    private static function output_xlsx( array $rows, array $headers, $filename ) {
        if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\Spreadsheet' ) || ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\IOFactory' ) ) {
            wp_die( esc_html__( 'Export XLSX indisponible : PhpSpreadsheet n’est pas chargé.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle( 'Clubs' );
        foreach ( $headers as $index => $header ) {
            $sheet->setCellValueExplicitByColumnAndRow( $index + 1, 1, (string) $header, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING );
        }
        $r = 2;
        foreach ( $rows as $row ) {
            $c = 1;
            foreach ( array_values( $row ) as $value ) {
                $sheet->setCellValueExplicitByColumnAndRow( $c++, $r, null === $value ? '' : (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING );
            }
            $r++;
        }
        foreach ( range( 1, count( $headers ) ) as $col ) { $sheet->getColumnDimensionByColumn( $col )->setAutoSize( true ); }
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter( $spreadsheet, 'Xlsx' );
        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '.xlsx"' );
        $writer->save( 'php://output' );
        exit;
    }
}
