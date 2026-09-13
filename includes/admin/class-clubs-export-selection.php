<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Sélection multi-clubs ergonomique pour l'export Clubs.
 *
 * Complète UFSC_Clubs_Flexible_Export sans modifier les exports licences.
 */
final class UFSC_Clubs_Export_Selection {
    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render_selector' ), 45 );
        add_action( 'admin_post_ufsc_export_data', array( __CLASS__, 'handle_multi_club_export' ), 0 );
    }

    private static function can_manage() {
        return class_exists( 'UFSC_Permissions' ) && function_exists( 'ufsc_user_can' )
            && ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    private static function table() {
        global $wpdb;
        if ( class_exists( 'UFSC_Storage_Resolver' ) && method_exists( 'UFSC_Storage_Resolver', 'get_clubs_table' ) ) {
            return UFSC_Storage_Resolver::get_clubs_table();
        }
        $settings = class_exists( 'UFSC_SQL' ) ? UFSC_SQL::get_settings() : array();
        return ! empty( $settings['table_clubs'] ) ? $settings['table_clubs'] : $wpdb->prefix . 'ufsc_clubs';
    }

    private static function columns() {
        global $wpdb;
        $table = self::table();
        return function_exists( 'ufsc_table_columns' )
            ? array_values( array_filter( (array) ufsc_table_columns( $table ) ) )
            : array_values( array_filter( (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function clubs() {
        global $wpdb;
        $table   = self::table();
        $columns = self::columns();
        if ( ! in_array( 'id', $columns, true ) || ! in_array( 'nom', $columns, true ) ) { return array(); }

        $select = array( '`id`', '`nom`' );
        foreach ( array( 'region', 'num_affiliation', 'numero_affiliation_ufsc', 'numero_affiliation_ffst' ) as $column ) {
            if ( in_array( $column, $columns, true ) ) { $select[] = '`' . $column . '`'; }
        }
        $where = class_exists( 'UFSC_Scope' ) ? UFSC_Scope::build_scope_condition( 'region' ) : '';
        $sql = 'SELECT ' . implode( ',', $select ) . " FROM `{$table}`" . ( $where ? ' WHERE ' . $where : '' ) . ' ORDER BY `nom` ASC';
        return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public static function render_selector() {
        if ( ! is_admin() || ! self::can_manage() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-exports' !== $page ) { return; }

        $clubs = self::clubs();
        if ( ! $clubs ) { return; }
        ?>
        <style>
        #ufsc-club-multiselect{display:none;margin:16px 0;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
        #ufsc-club-multiselect .ufsc-club-select-head{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:12px}
        #ufsc-club-multiselect .ufsc-club-search{flex:1 1 320px;max-width:620px}
        #ufsc-club-multiselect .ufsc-club-search label{display:grid;gap:5px}
        #ufsc-club-multiselect .ufsc-club-search input{width:100%;min-height:38px}
        #ufsc-club-multiselect .ufsc-club-actions{display:flex;gap:7px;flex-wrap:wrap}
        #ufsc-club-multiselect .ufsc-club-list{max-height:330px;overflow:auto;border:1px solid #dcdcde;border-radius:6px;background:#f9f9f9;padding:5px}
        #ufsc-club-multiselect .ufsc-club-row{display:grid;grid-template-columns:auto minmax(220px,1.6fr) minmax(120px,.8fr) minmax(160px,1fr);gap:9px;align-items:center;padding:8px 9px;border-bottom:1px solid #e9e9e9;background:#fff}
        #ufsc-club-multiselect .ufsc-club-row:last-child{border-bottom:0}
        #ufsc-club-multiselect .ufsc-club-row:hover{background:#f0f6fc}
        #ufsc-club-multiselect .ufsc-club-row small{color:#646970}
        #ufsc-club-multiselect .ufsc-club-summary{margin-top:10px;font-weight:600}
        @media(max-width:782px){#ufsc-club-multiselect .ufsc-club-row{grid-template-columns:auto 1fr}.ufsc-club-meta{grid-column:2}}
        </style>
        <script>
        (function(){
            var clubs=<?php echo wp_json_encode( $clubs ); ?>;
            function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
            function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
            ready(function(){
                var entity=document.getElementById('ufsc_export_entity');
                if(!entity)return;
                var form=entity.closest('form'); if(!form)return;
                var host=document.createElement('div'); host.id='ufsc-club-multiselect';
                host.innerHTML='<div class="ufsc-club-select-head"><div class="ufsc-club-search"><label><strong>Sélection des clubs</strong><input type="search" id="ufsc-club-search" placeholder="Rechercher par nom, ID, région ou n° d’affiliation…" autocomplete="off"></label></div><div class="ufsc-club-actions"><button type="button" class="button" data-club-select="visible">Sélectionner les résultats</button><button type="button" class="button" data-club-select="all">Tout sélectionner</button><button type="button" class="button" data-club-select="none">Tout désélectionner</button></div></div><div class="ufsc-club-list"></div><div class="ufsc-club-summary"><span id="ufsc-club-selected-count">0</span> club(s) sélectionné(s) sur '+clubs.length+'</div>';
                var switcher=entity.closest('.ufsc-export-entity-switch');
                if(switcher&&switcher.parentNode){switcher.parentNode.insertBefore(host,switcher.nextSibling);}else{form.insertBefore(host,form.firstChild);}
                var list=host.querySelector('.ufsc-club-list');
                clubs.forEach(function(c){
                    var aff=c.numero_affiliation_ffst||c.numero_affiliation_ufsc||c.num_affiliation||'';
                    var row=document.createElement('label'); row.className='ufsc-club-row';
                    row.dataset.search=[c.id,c.nom,c.region||'',aff].join(' ').toLowerCase();
                    row.innerHTML='<input type="checkbox" name="club_export_ids[]" value="'+esc(c.id)+'"><strong>'+esc(c.nom)+'</strong><span class="ufsc-club-meta"><small>ID #'+esc(c.id)+(c.region?' · '+esc(c.region):'')+'</small></span><span class="ufsc-club-meta"><small>'+(aff?'Affiliation : '+esc(aff):'N° affiliation non renseigné')+'</small></span>';
                    list.appendChild(row);
                });
                var search=host.querySelector('#ufsc-club-search');
                var count=host.querySelector('#ufsc-club-selected-count');
                function updateCount(){count.textContent=String(host.querySelectorAll('input[name="club_export_ids[]"]:checked').length);}
                function visibleRows(){return Array.from(host.querySelectorAll('.ufsc-club-row')).filter(function(r){return r.style.display!=='none';});}
                search.addEventListener('input',function(){var q=(search.value||'').trim().toLowerCase();host.querySelectorAll('.ufsc-club-row').forEach(function(r){r.style.display=!q||r.dataset.search.indexOf(q)!==-1?'':'none';});});
                host.addEventListener('change',function(e){if(e.target.matches('input[name="club_export_ids[]"]'))updateCount();});
                host.addEventListener('click',function(e){var mode=e.target&&e.target.getAttribute('data-club-select');if(!mode)return;var rows=mode==='visible'?visibleRows():Array.from(host.querySelectorAll('.ufsc-club-row'));rows.forEach(function(r){var cb=r.querySelector('input[type="checkbox"]');if(cb)cb.checked=mode!=='none';});updateCount();});
                function toggle(){host.style.display=entity.value==='clubs'?'':'none';}
                entity.addEventListener('change',toggle); toggle(); updateCount();
            });
        })();
        </script>
        <?php
    }

    public static function handle_multi_club_export() {
        $entity = isset( $_POST['export_entity'] ) ? sanitize_key( wp_unslash( $_POST['export_entity'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( 'clubs' !== $entity || empty( $_POST['club_export_ids'] ) || ! is_array( $_POST['club_export_ids'] ) ) { return; }
        if ( ! self::can_manage() ) { wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'ufsc_export_data' );

        global $wpdb;
        $table   = self::table();
        $columns = self::columns();
        $ids     = array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['club_export_ids'] ) ) ) ) );
        if ( ! $ids ) { return; }

        $requested = isset( $_POST['club_export_columns'] ) && is_array( $_POST['club_export_columns'] )
            ? array_map( 'sanitize_key', wp_unslash( $_POST['club_export_columns'] ) ) : $columns;
        $selected = array_values( array_intersect( $columns, $requested ) );
        if ( ! $selected ) { wp_die( esc_html__( 'Sélectionnez au moins une colonne club à exporter.', 'ufsc-clubs' ), '', array( 'response' => 400 ) ); }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $where = array( "`id` IN ({$placeholders})" );
        $params = $ids;
        $scope = class_exists( 'UFSC_Scope' ) ? UFSC_Scope::build_scope_condition( 'region' ) : '';
        if ( $scope ) { $where[] = '(' . $scope . ')'; }

        $select_sql = implode( ', ', array_map( static function( $c ){ return '`' . str_replace( '`', '', $c ) . '`'; }, $selected ) );
        $order_col = in_array( 'nom', $columns, true ) ? 'nom' : 'id';
        $sql = $wpdb->prepare( "SELECT {$select_sql} FROM `{$table}` WHERE " . implode( ' AND ', $where ) . " ORDER BY `{$order_col}` ASC", $params ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ( ! $rows ) { wp_die( esc_html__( 'Aucun club sélectionné n’est accessible.', 'ufsc-clubs' ), '', array( 'response' => 404 ) ); }

        $labels = array();
        if ( class_exists( 'UFSC_SQL' ) ) {
            foreach ( (array) UFSC_SQL::get_club_fields() as $key => $conf ) { $labels[$key]=is_array($conf)&&isset($conf[0])?(string)$conf[0]:$key; }
        }
        $headers=array(); foreach($selected as $c){$headers[]=$labels[$c]??ucfirst(str_replace('_',' ',$c));}
        $format=isset($_POST['export_format'])?sanitize_key(wp_unslash($_POST['export_format'])):'csv';
        $filename='ufsc_clubs_selection_'.gmdate('Y-m-d_H-i-s');

        if ( 'xlsx' === $format && class_exists( '\\PhpOffice\\PhpSpreadsheet\\Spreadsheet' ) ) {
            $ss=new \\PhpOffice\\PhpSpreadsheet\\Spreadsheet(); $sh=$ss->getActiveSheet(); $sh->setTitle('Clubs');
            foreach($headers as $i=>$h){$sh->setCellValueExplicitByColumnAndRow($i+1,1,(string)$h,\\PhpOffice\\PhpSpreadsheet\\Cell\\DataType::TYPE_STRING);} $r=2;
            foreach($rows as $row){$c=1;foreach(array_values($row) as $v){$sh->setCellValueExplicitByColumnAndRow($c++,$r,null===$v?'':(string)$v,\\PhpOffice\\PhpSpreadsheet\\Cell\\DataType::TYPE_STRING);}$r++;}
            $writer=\\PhpOffice\\PhpSpreadsheet\\IOFactory::createWriter($ss,'Xlsx'); nocache_headers(); header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="'.sanitize_file_name($filename).'.xlsx"'); $writer->save('php://output'); exit;
        }
        nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="'.sanitize_file_name($filename).'.csv"'); $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,$headers,';'); foreach($rows as $row){fputcsv($out,array_values($row),';');} fclose($out); exit;
    }
}
