<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Filtre de l'export Clubs selon l'affiliation annuelle courante.
 * Lecture seule : aucune donnée club/licence/affiliation n'est modifiée.
 */
final class UFSC_Clubs_Export_Filter {
    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render' ), 50 );
    }

    private static function can_manage() {
        return class_exists( 'UFSC_Permissions' ) && function_exists( 'ufsc_user_can' )
            && ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    private static function current_season() {
        if ( class_exists( 'UFSC_Season_Service' ) ) {
            return (string) UFSC_Season_Service::get_current_season();
        }
        return function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '';
    }

    private static function annual_status_map( $season ) {
        global $wpdb;
        if ( ! $season ) { return array(); }
        $table = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::get_annual_affiliations_table()
            : $wpdb->prefix . 'ufsc_affiliations_seasons';
        if ( function_exists( 'ufsc_table_exists' ) && ! ufsc_table_exists( $table ) ) { return array(); }

        $sql = $wpdb->prepare(
            "SELECT club_id, status FROM `{$table}` WHERE season = %s ORDER BY id DESC",
            $season
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $map = array();
        foreach ( $rows as $row ) {
            $club_id = absint( $row['club_id'] ?? 0 );
            if ( ! $club_id || isset( $map[ $club_id ] ) ) { continue; }
            $map[ $club_id ] = sanitize_key( (string) ( $row['status'] ?? '' ) );
        }
        return $map;
    }

    public static function render() {
        if ( ! is_admin() || ! self::can_manage() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-exports' !== $page ) { return; }

        $season = self::current_season();
        $statuses = self::annual_status_map( $season );
        ?>
        <style>
            #ufsc-club-export-status-filter{display:grid;gap:5px;min-width:220px}
            #ufsc-club-export-status-filter select{min-height:38px}
            #ufsc-club-multiselect .ufsc-club-filter-note{margin:8px 0 0;color:#646970}
        </style>
        <script>
        (function(){
            var annualStatuses=<?php echo wp_json_encode( $statuses ); ?>;
            var season=<?php echo wp_json_encode( $season ); ?>;
            function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
            function category(status){
                status=String(status||'').toLowerCase();
                if(['active','validated','valide'].indexOf(status)!==-1)return 'active';
                if(['pending','en_attente','submitted','processing'].indexOf(status)!==-1)return 'pending';
                if(['refused','refuse','rejected'].indexOf(status)!==-1)return 'refused';
                return status ? 'other' : 'none';
            }
            ready(function(){
                var entity=document.getElementById('ufsc_export_entity');
                var host=document.getElementById('ufsc-club-multiselect');
                if(!entity||!host)return;
                var form=entity.closest('form');
                var head=host.querySelector('.ufsc-club-select-head');
                var search=host.querySelector('#ufsc-club-search');
                if(!form||!head||!search)return;

                var filter=document.createElement('label');
                filter.id='ufsc-club-export-status-filter';
                filter.innerHTML='<strong>Affiliation '+String(season||'saison courante')+'</strong><select id="ufsc-club-status-filter"><option value="all">Tous les clubs</option><option value="active">Actifs</option><option value="pending">En attente</option><option value="refused">Refusés</option><option value="none">Sans affiliation</option><option value="other">Autre statut</option></select>';
                head.insertBefore(filter,head.querySelector('.ufsc-club-actions'));
                var statusSelect=filter.querySelector('select');

                function applyFilters(){
                    var q=(search.value||'').trim().toLowerCase();
                    var wanted=statusSelect.value;
                    host.querySelectorAll('.ufsc-club-row').forEach(function(row){
                        var cb=row.querySelector('input[name="club_export_ids[]"]');
                        var id=cb?String(cb.value):'';
                        var textOk=!q||String(row.dataset.search||'').indexOf(q)!==-1;
                        var statusOk=wanted==='all'||category(annualStatuses[id])===wanted;
                        row.style.display=textOk&&statusOk?'':'none';
                    });
                }
                search.addEventListener('input',applyFilters);
                statusSelect.addEventListener('change',applyFilters);
                applyFilters();

                var note=document.createElement('p');
                note.className='ufsc-club-filter-note';
                note.textContent='Si aucun club n’est coché, l’export prendra automatiquement les résultats actuellement visibles après filtrage.';
                host.appendChild(note);

                form.addEventListener('submit',function(e){
                    if(entity.value!=='clubs')return;
                    var checked=host.querySelectorAll('input[name="club_export_ids[]"]:checked');
                    if(checked.length)return;
                    var visible=Array.from(host.querySelectorAll('.ufsc-club-row')).filter(function(row){return row.style.display!=='none';});
                    if(!visible.length){
                        e.preventDefault();
                        window.alert('Aucun club ne correspond aux filtres sélectionnés.');
                        return;
                    }
                    visible.forEach(function(row){var cb=row.querySelector('input[name="club_export_ids[]"]');if(cb)cb.checked=true;});
                });
            });
        })();
        </script>
        <?php
    }
}
