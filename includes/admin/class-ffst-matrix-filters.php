<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Filtres d'affichage pour les exports matrices FFST.
 * Lecture seule : n'écrit aucune donnée métier.
 *
 * Important : le statut affiché/filtré est celui de l'affiliation annuelle
 * pour la saison sélectionnée, et non le statut permanent enregistré sur le club.
 */
final class UFSC_FFST_Matrix_Filters {
    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render' ), 1200 );
    }

    public static function render() {
        if ( ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-ffst-documents' !== $page ) { return; }
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) { return; }

        $club_meta        = self::club_meta();
        $affiliation_meta = self::annual_affiliation_meta();
        ?>
        <style id="ufsc-ffst-matrix-filters-style">
            .ufsc-ffst-matrix__filters{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr)) auto;gap:10px;align-items:end;margin:0 0 12px;padding:12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px}
            .ufsc-ffst-matrix__filters label{display:block;margin-bottom:4px;font-weight:600}
            .ufsc-ffst-matrix__filters select{width:100%;min-height:36px}
            .ufsc-ffst-matrix__status-badge{display:inline-block;margin-left:8px;padding:2px 7px;border-radius:999px;background:#f0f0f1;color:#3c434a;font-size:11px;font-weight:600;vertical-align:middle}
            .ufsc-ffst-matrix__status-badge[data-state="active"]{background:#d7f2dc;color:#116329}
            .ufsc-ffst-matrix__status-badge[data-state="pending_payment"],.ufsc-ffst-matrix__status-badge[data-state="pending"]{background:#fff1c2;color:#7a4b00}
            .ufsc-ffst-matrix__status-badge[data-state="none"]{background:#e9edf3;color:#39465a}
            .ufsc-ffst-matrix__filtered-count{margin-left:8px;color:#646970;font-weight:400}
            @media(max-width:900px){.ufsc-ffst-matrix__filters{grid-template-columns:1fr 1fr}}
            @media(max-width:600px){.ufsc-ffst-matrix__filters{grid-template-columns:1fr}}
        </style>
        <script>
        (function(){
            var form=document.getElementById('ufsc-ffst-matrix-form'), list=document.getElementById('ufsc-matrix-list');
            if(!form||!list)return;
            var clubs=<?php echo wp_json_encode( $club_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
            var annual=<?php echo wp_json_encode( $affiliation_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
            var toolbar=form.querySelector('.ufsc-ffst-matrix__toolbar');
            if(!toolbar)return;

            var filters=document.createElement('div');
            filters.className='ufsc-ffst-matrix__filters';
            filters.innerHTML=''
                +'<div><label for="ufsc-matrix-status">Statut affiliation annuelle</label><select id="ufsc-matrix-status"><option value="">Tous les statuts</option><option value="active">Actif / validé</option><option value="pending_payment">Paiement en attente</option><option value="pending">En attente de validation</option><option value="draft">Brouillon</option><option value="inactive">Refusé / annulé</option><option value="none">Sans affiliation sur la saison</option><option value="other">Autre / non reconnu</option></select></div>'
                +'<div><label for="ufsc-matrix-ffst">N° affiliation FFST</label><select id="ufsc-matrix-ffst"><option value="">Tous</option><option value="yes">N° FFST renseigné</option><option value="no">N° FFST non renseigné</option></select></div>'
                +'<div><label for="ufsc-matrix-complete">Dossier FFST</label><select id="ufsc-matrix-complete"><option value="">Tous</option><option value="complete">Dossier complet</option><option value="incomplete">Dossier incomplet</option></select></div>'
                +'<div><button type="button" class="button" id="ufsc-matrix-reset-filters">Réinitialiser les filtres</button></div>';
            toolbar.insertAdjacentElement('afterend',filters);

            var status=document.getElementById('ufsc-matrix-status'), ffst=document.getElementById('ufsc-matrix-ffst'), complete=document.getElementById('ufsc-matrix-complete'), search=document.getElementById('ufsc-matrix-search'), season=document.getElementById('ufsc-matrix-season'), reset=document.getElementById('ufsc-matrix-reset-filters');
            var count=document.getElementById('ufsc-matrix-count');
            var counter=document.createElement('span');counter.className='ufsc-ffst-matrix__filtered-count';
            if(count&&count.parentNode)count.parentNode.appendChild(counter);

            function norm(v){return(v||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();}
            function annualFor(id){
                var s=(season&&season.value?season.value:'').trim();
                if(annual[s]&&annual[s][id])return annual[s][id];
                return {status:'none',label:'Sans affiliation '+(s||'sur la saison')};
            }
            function apply(){
                var q=norm(search?search.value:''), s=status.value, f=ffst.value, c=complete.value, visible=0;
                list.querySelectorAll('.ufsc-ffst-matrix__club').forEach(function(row){
                    var cb=row.querySelector('input[type=checkbox]'), id=cb?String(cb.value):'', club=clubs[id]||{}, aff=annualFor(id);
                    row.setAttribute('data-club-status',aff.status||'none');
                    row.setAttribute('data-ffst',club.ffst||'no');
                    row.setAttribute('data-complete',club.complete||'incomplete');
                    var okSearch=!q||norm(row.getAttribute('data-search')).indexOf(q)!==-1;
                    var okStatus=!s||(aff.status||'none')===s;
                    var okFfst=!f||(club.ffst||'no')===f;
                    var okComplete=!c||(club.complete||'incomplete')===c;
                    var show=okSearch&&okStatus&&okFfst&&okComplete;
                    row.style.display=show?'grid':'none';
                    if(show)visible++;
                    var name=row.querySelector('.ufsc-ffst-matrix__club-name');
                    if(name){
                        var badge=name.querySelector('.ufsc-ffst-matrix__status-badge');
                        if(!badge){badge=document.createElement('span');badge.className='ufsc-ffst-matrix__status-badge';name.appendChild(badge);}
                        badge.textContent=aff.label||'Sans affiliation';
                        badge.setAttribute('data-state',aff.status||'none');
                    }
                });
                if(counter)counter.textContent='— '+visible+' club(s) affiché(s)';
            }
            [status,ffst,complete].forEach(function(el){el.addEventListener('change',apply);});
            if(search)search.addEventListener('input',apply);
            if(season){season.addEventListener('input',apply);season.addEventListener('change',apply);}
            reset.addEventListener('click',function(){status.value='';ffst.value='';complete.value='';if(search)search.value='';apply();});

            // UX export : si aucune case n'est cochée, exporter automatiquement
            // les clubs actuellement affichés par les filtres. Si l'utilisateur a
            // fait une sélection manuelle, seules les cases cochées et visibles
            // sont envoyées. Les clubs masqués ne peuvent donc pas partir par erreur.
            form.addEventListener('submit',function(e){
                var rows=Array.from(list.querySelectorAll('.ufsc-ffst-matrix__club'));
                var visible=rows.filter(function(row){return row.style.display!=='none';});
                rows.forEach(function(row){
                    if(row.style.display==='none'){
                        var hiddenCb=row.querySelector('input[type=checkbox]');
                        if(hiddenCb)hiddenCb.checked=false;
                    }
                });
                var checkedVisible=visible.filter(function(row){var cb=row.querySelector('input[type=checkbox]');return cb&&cb.checked;});
                if(!checkedVisible.length){
                    visible.forEach(function(row){var cb=row.querySelector('input[type=checkbox]');if(cb)cb.checked=true;});
                }
                if(!visible.length){
                    e.preventDefault();
                    alert('Aucun club ne correspond aux filtres sélectionnés.');
                    return;
                }
                var selected=Array.from(list.querySelectorAll('input[type=checkbox]:checked')).length;
                if(count)count.textContent=selected;
            },true);

            apply();
        })();
        </script>
        <?php
    }

    /** Métadonnées permanentes du club : numéro FFST + complétude uniquement. */
    private static function club_meta() {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        if ( ! $table ) { return array(); }
        $rows = (array) $wpdb->get_results( "SELECT * FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
        $out = array();
        foreach ( $rows as $row ) {
            $id = absint( self::value( $row, array( 'id', 'club_id' ) ) );
            if ( ! $id ) { continue; }
            $out[ (string) $id ] = array(
                'ffst'     => '' !== trim( self::value( $row, array( 'numero_affiliation_ffst' ) ) ) ? 'yes' : 'no',
                'complete' => self::is_complete( $row ) ? 'complete' : 'incomplete',
            );
        }
        return $out;
    }

    /**
     * Statuts réels des affiliations annuelles, indexés par saison puis club.
     * Source identique au filtre Clubs existant : table annuelle d'affiliation.
     */
    private static function annual_affiliation_meta() {
        global $wpdb;
        $table = self::affiliations_table();
        if ( ! $table ) { return array(); }

        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        if ( ! $columns ) {
            $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
        }
        $club_col   = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : '' );
        $season_col = in_array( 'season', $columns, true ) ? 'season' : ( in_array( 'saison', $columns, true ) ? 'saison' : '' );
        $status_col = in_array( 'status', $columns, true ) ? 'status' : ( in_array( 'statut', $columns, true ) ? 'statut' : '' );
        if ( ! $club_col || ! $season_col || ! $status_col ) { return array(); }

        $rows = (array) $wpdb->get_results( "SELECT `{$club_col}` AS club_id, `{$season_col}` AS season, `{$status_col}` AS status FROM `{$table}` ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
        $out = array();
        foreach ( $rows as $row ) {
            $club_id = absint( $row->club_id ?? 0 );
            $season  = str_replace( '/', '-', trim( (string) ( $row->season ?? '' ) ) );
            if ( ! $club_id || '' === $season ) { continue; }
            $raw = trim( (string) ( $row->status ?? '' ) );
            $normalized = self::normalize_status( $raw );
            if ( ! isset( $out[ $season ] ) ) { $out[ $season ] = array(); }
            $out[ $season ][ (string) $club_id ] = array(
                'status' => $normalized,
                'label'  => self::status_label( $normalized, $raw ),
            );
        }
        return $out;
    }

    private static function affiliations_table() {
        global $wpdb;
        if ( class_exists( 'UFSC_Storage_Resolver' ) && method_exists( 'UFSC_Storage_Resolver', 'get_annual_affiliations_table' ) ) {
            return UFSC_Storage_Resolver::get_annual_affiliations_table();
        }
        return $wpdb->prefix . 'ufsc_affiliations_seasons';
    }

    private static function normalize_status( $status ) {
        $s = strtolower( remove_accents( trim( (string) $status ) ) );
        if ( in_array( $s, array( 'actif','active','valide','valid','validated','approved','affilie','affiliee' ), true ) ) { return 'active'; }
        if ( in_array( $s, array( 'pending_payment','awaiting_payment','payment_pending' ), true ) ) { return 'pending_payment'; }
        if ( in_array( $s, array( 'pending','en_attente','attente','submitted','processing','pending_validation' ), true ) ) { return 'pending'; }
        if ( in_array( $s, array( 'draft','brouillon' ), true ) ) { return 'draft'; }
        if ( in_array( $s, array( 'refused','refuse','rejected','rejete','inactive','inactif','cancelled','canceled','annule','archive','archived','expire','expired' ), true ) ) { return 'inactive'; }
        return 'other';
    }

    private static function status_label( $status, $raw ) {
        $labels = array(
            'active'          => 'Actif / validé',
            'pending'         => 'En attente',
            'pending_payment' => 'Paiement en attente',
            'draft'           => 'Brouillon',
            'inactive'        => 'Refusé / annulé',
            'other'           => 'Autre / non renseigné',
        );
        if ( 'other' === $status && '' !== trim( (string) $raw ) ) { return 'Autre : ' . (string) $raw; }
        return $labels[ $status ];
    }

    private static function is_complete( $row ) {
        $required = array(
            array( 'nom','name','club_name' ), array( 'adresse' ), array( 'code_postal' ), array( 'ville' ), array( 'email','mail' ), array( 'telephone','tel' ),
            array( 'president_nom' ), array( 'president_prenom' ), array( 'president_date_naissance' ), array( 'president_adresse' ), array( 'president_code_postal' ), array( 'president_ville' ),
            array( 'secretaire_nom' ), array( 'secretaire_prenom' ), array( 'tresorier_nom' ), array( 'tresorier_prenom' ),
            array( 'adresse_salle' ), array( 'code_postal_salle' ), array( 'ville_salle' ), array( 'disciplines_ffst','disciplines','discipline' )
        );
        foreach ( $required as $keys ) { if ( '' === trim( self::value( $row, $keys ) ) ) { return false; } }
        return true;
    }

    private static function value( $row, array $keys ) {
        foreach ( $keys as $key ) {
            if ( is_object( $row ) && isset( $row->{$key} ) && '' !== trim( (string) $row->{$key} ) ) { return (string) $row->{$key}; }
            if ( is_array( $row ) && isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) { return (string) $row[ $key ]; }
        }
        return '';
    }
}

UFSC_FFST_Matrix_Filters::init();
