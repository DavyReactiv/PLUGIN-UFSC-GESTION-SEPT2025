<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Filtres d'affichage pour les exports matrices FFST.
 * Lecture seule : n'écrit aucune donnée métier.
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

        $meta = self::club_meta();
        ?>
        <style id="ufsc-ffst-matrix-filters-style">
            .ufsc-ffst-matrix__filters{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr)) auto;gap:10px;align-items:end;margin:0 0 12px;padding:12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px}
            .ufsc-ffst-matrix__filters label{display:block;margin-bottom:4px;font-weight:600}
            .ufsc-ffst-matrix__filters select{width:100%;min-height:36px}
            .ufsc-ffst-matrix__status-badge{display:inline-block;margin-left:8px;padding:2px 7px;border-radius:999px;background:#f0f0f1;color:#3c434a;font-size:11px;font-weight:600;vertical-align:middle}
            .ufsc-ffst-matrix__filtered-count{margin-left:8px;color:#646970;font-weight:400}
            @media(max-width:900px){.ufsc-ffst-matrix__filters{grid-template-columns:1fr 1fr}}
            @media(max-width:600px){.ufsc-ffst-matrix__filters{grid-template-columns:1fr}}
        </style>
        <script>
        (function(){
            var form=document.getElementById('ufsc-ffst-matrix-form'), list=document.getElementById('ufsc-matrix-list');
            if(!form||!list)return;
            var data=<?php echo wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
            var toolbar=form.querySelector('.ufsc-ffst-matrix__toolbar');
            if(!toolbar)return;

            var filters=document.createElement('div');
            filters.className='ufsc-ffst-matrix__filters';
            filters.innerHTML=''
                +'<div><label for="ufsc-matrix-status">Statut du club</label><select id="ufsc-matrix-status"><option value="">Tous les statuts</option><option value="active">Actif / validé</option><option value="pending">En attente</option><option value="pending_payment">Paiement en attente</option><option value="draft">Brouillon</option><option value="inactive">Refusé / inactif / annulé</option><option value="other">Autre / non reconnu</option></select></div>'
                +'<div><label for="ufsc-matrix-ffst">N° affiliation FFST</label><select id="ufsc-matrix-ffst"><option value="">Tous</option><option value="yes">N° FFST renseigné</option><option value="no">N° FFST non renseigné</option></select></div>'
                +'<div><label for="ufsc-matrix-complete">Dossier FFST</label><select id="ufsc-matrix-complete"><option value="">Tous</option><option value="complete">Dossier complet</option><option value="incomplete">Dossier incomplet</option></select></div>'
                +'<div><button type="button" class="button" id="ufsc-matrix-reset-filters">Réinitialiser les filtres</button></div>';
            toolbar.insertAdjacentElement('afterend',filters);

            var status=document.getElementById('ufsc-matrix-status'), ffst=document.getElementById('ufsc-matrix-ffst'), complete=document.getElementById('ufsc-matrix-complete'), search=document.getElementById('ufsc-matrix-search'), reset=document.getElementById('ufsc-matrix-reset-filters');
            var count=document.getElementById('ufsc-matrix-count');
            var counter=document.createElement('span');counter.className='ufsc-ffst-matrix__filtered-count';
            if(count&&count.parentNode)count.parentNode.appendChild(counter);

            function norm(v){return(v||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();}
            function apply(){
                var q=norm(search?search.value:''), s=status.value, f=ffst.value, c=complete.value, visible=0;
                list.querySelectorAll('.ufsc-ffst-matrix__club').forEach(function(row){
                    var cb=row.querySelector('input[type=checkbox]'), id=cb?String(cb.value):'', m=data[id]||{};
                    row.setAttribute('data-club-status',m.status||'other');
                    row.setAttribute('data-ffst',m.ffst||'no');
                    row.setAttribute('data-complete',m.complete||'incomplete');
                    var okSearch=!q||norm(row.getAttribute('data-search')).indexOf(q)!==-1;
                    var okStatus=!s||(m.status||'other')===s;
                    var okFfst=!f||(m.ffst||'no')===f;
                    var okComplete=!c||(m.complete||'incomplete')===c;
                    var show=okSearch&&okStatus&&okFfst&&okComplete;
                    row.style.display=show?'grid':'none';
                    if(show)visible++;
                    var name=row.querySelector('.ufsc-ffst-matrix__club-name');
                    if(name&&!name.querySelector('.ufsc-ffst-matrix__status-badge')){
                        var badge=document.createElement('span');badge.className='ufsc-ffst-matrix__status-badge';badge.textContent=m.label||'Statut non renseigné';name.appendChild(badge);
                    }
                });
                if(counter)counter.textContent='— '+visible+' club(s) affiché(s)';
            }
            [status,ffst,complete].forEach(function(el){el.addEventListener('change',apply);});
            if(search)search.addEventListener('input',apply);
            reset.addEventListener('click',function(){status.value='';ffst.value='';complete.value='';if(search)search.value='';apply();});
            apply();
        })();
        </script>
        <?php
    }

    private static function club_meta() {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        if ( ! $table ) { return array(); }
        $rows = (array) $wpdb->get_results( "SELECT * FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
        $out = array();
        foreach ( $rows as $row ) {
            $id = absint( self::value( $row, array( 'id', 'club_id' ) ) );
            if ( ! $id ) { continue; }
            $raw = self::value( $row, array( 'statut', 'status', 'etat', 'club_status', 'affiliation_status' ) );
            $status = self::normalize_status( $raw );
            $ffst = '' !== trim( self::value( $row, array( 'numero_affiliation_ffst' ) ) ) ? 'yes' : 'no';
            $complete = self::is_complete( $row ) ? 'complete' : 'incomplete';
            $out[ (string) $id ] = array( 'status' => $status, 'label' => self::status_label( $status, $raw ), 'ffst' => $ffst, 'complete' => $complete );
        }
        return $out;
    }

    private static function normalize_status( $status ) {
        $s = strtolower( remove_accents( trim( (string) $status ) ) );
        if ( in_array( $s, array( 'actif','active','valide','valid','validated','approved','affilie','affiliee' ), true ) ) { return 'active'; }
        if ( in_array( $s, array( 'pending_payment','awaiting_payment','payment_pending','en_attente_paiement' ), true ) ) { return 'pending_payment'; }
        if ( in_array( $s, array( 'pending','en_attente','attente','submitted','processing','pending_validation' ), true ) ) { return 'pending'; }
        if ( in_array( $s, array( 'draft','brouillon' ), true ) ) { return 'draft'; }
        if ( in_array( $s, array( 'refused','refuse','rejected','rejete','inactive','inactif','cancelled','canceled','annule','archive','archived','expire','expired' ), true ) ) { return 'inactive'; }
        return 'other';
    }

    private static function status_label( $status, $raw ) {
        $labels = array( 'active'=>'Actif / validé','pending'=>'En attente','pending_payment'=>'Paiement en attente','draft'=>'Brouillon','inactive'=>'Refusé / inactif','other'=>'Autre / non renseigné' );
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
