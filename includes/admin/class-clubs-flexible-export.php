<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Export Clubs complet et configurable depuis l'écran Exports.
 *
 * - ne remplace pas l'export Licences existant ;
 * - intercepte uniquement les requêtes export_entity=clubs ;
 * - toutes les colonnes SQL du club sont proposées/cochées par défaut ;
 * - respecte le périmètre régional UFSC ;
 * - aucune écriture en base.
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
        if ( isset( $special[ $key ] ) ) { return $special[ $key ]; }
        return ucfirst( str_replace( '_', ' ', (string) $key ) );
    }

    public static function enhance_exports_screen() {
        if ( ! is_admin() || ! self::can_manage() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-exports' !== $page ) { return; }

        $columns = self::columns();
        $labels  = self::labels();
        $club_fields = array();
        foreach ( $columns as $column ) {
            $club_fields[] = array( 'key' => $column, 'label' => $labels[ $column ] ?? self::pretty_label( $column ) );
        }
        ?>
        <style>
            .ufsc-export-entity-switch{display:flex;gap:10px;align-items:end;margin:14px 0 18px;padding:14px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
            .ufsc-export-entity-switch label{display:grid;gap:5px;min-width:240px}
            #ufsc-club-export-columns{margin:20px 0;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
            #ufsc-club-export-columns .ufsc-club-export-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:9px 16px}
            #ufsc-club-export-columns label{display:flex;align-items:center;gap:7px;min-width:0}
            .ufsc-export-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px}
        </style>
        <script>
        (function(){
            var fields=<?php echo wp_json_encode( $club_fields ); ?>;
            function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
            ready(function(){
                var actionInput=document.querySelector('form[action*="admin-post.php"] input[name="action"][value="ufsc_export_data"]');
                if(!actionInput)return;
                var form=actionInput.closest('form'); if(!form)return;
                var heading=Array.from(form.querySelectorAll('h4')).find(function(h){return /Colonnes à exporter/i.test(h.textContent||'');});
                if(!heading)return;
                var licenceBox=heading.parentElement;

                var switcher=document.createElement('div'); switcher.className='ufsc-export-entity-switch';
                switcher.innerHTML='<label><strong>Type de données</strong><select name="export_entity" id="ufsc_export_entity"><option value="licences">Licences</option><option value="clubs">Clubs</option></select></label><span class="description">Tous les champs clubs disponibles sont sélectionnés par défaut.</span>';
                licenceBox.parentNode.insertBefore(switcher,licenceBox);

                var clubBox=document.createElement('div'); clubBox.id='ufsc-club-export-columns'; clubBox.style.display='none';
                var html='<h4>Colonnes Clubs à exporter</h4><div class="ufsc-export-toolbar"><button type="button" class="button" data-club-export="all">Tout sélectionner</button><button type="button" class="button" data-club-export="none">Tout désélectionner</button></div><div class="ufsc-club-export-grid">';
                fields.forEach(function(f){html+='<label><input type="checkbox" name="club_export_columns[]" value="'+String(f.key).replace(/"/g,'&quot;')+'" checked> '+String(f.label).replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</label>';});
                html+='</div>';
                clubBox.innerHTML=html;
                licenceBox.parentNode.insertBefore(clubBox,licenceBox.nextSibling);

                var select=switcher.querySelector('#ufsc_export_entity');
                function toggle(){var clubs=select.value==='clubs'; licenceBox.style.display=clubs?'none':''; clubBox.style.display=clubs?'':'none';}
                select.addEventListener('change',toggle); toggle();
                clubBox.addEventListener('click',function(e){var mode=e.target&&e.target.getAttribute('data-club-export');if(!mode)return;clubBox.querySelectorAll('input[name="club_export_columns[]"]').forEach(function(cb){cb.checked=mode==='all';});});
            });
        })();
        </script>
        <?php
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
            ? array_map( 'sanitize_key', wp_unslash( $_POST['club_export_columns'] ) )
            : $columns;
        $selected = array_values( array_intersect( $columns, $requested ) );
        if ( ! $selected ) {
            wp_die( esc_html__( 'Sélectionnez au moins une colonne club à exporter.', 'ufsc-clubs' ), '', array( 'response' => 400 ) );
        }

        $where  = array( '1=1' );
        $params = array();
        $scope = class_exists( 'UFSC_Scope' ) ? UFSC_Scope::build_scope_condition( 'region' ) : '';
        if ( $scope ) { $where[] = '(' . $scope . ')'; }

        $filter_club = isset( $_POST['filter_club'] ) ? absint( wp_unslash( $_POST['filter_club'] ) ) : 0;
        if ( $filter_club && in_array( 'id', $columns, true ) ) { $where[] = '`id`=%d'; $params[] = $filter_club; }

        $filter_region = isset( $_POST['filter_region'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_region'] ) ) : '';
        if ( $filter_region && in_array( 'region', $columns, true ) ) { $where[] = '`region`=%s'; $params[] = $filter_region; }

        $filter_status = isset( $_POST['filter_status'] ) ? sanitize_key( wp_unslash( $_POST['filter_status'] ) ) : '';
        if ( $filter_status && in_array( 'statut', $columns, true ) ) { $where[] = '`statut`=%s'; $params[] = $filter_status; }

        $visibility = isset( $_POST['filter_visibility'] ) ? sanitize_key( wp_unslash( $_POST['filter_visibility'] ) ) : 'active';
        if ( in_array( 'deleted_at', $columns, true ) ) {
            if ( 'trash' === $visibility ) { $where[] = "`deleted_at` IS NOT NULL AND `deleted_at` <> '' AND `deleted_at` <> '0000-00-00 00:00:00'"; }
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
        $filename = 'ufsc_clubs_' . gmdate( 'Y-m-d_H-i-s' );
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
