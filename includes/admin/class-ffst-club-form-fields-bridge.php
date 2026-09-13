<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Rend visibles les champs FFST de naissance dans les écrans Club admin
 * (création, modification et consultation), même lorsque le renderer historique
 * utilise une liste blanche de champs pour la section Dirigeants.
 *
 * Aucun stockage parallèle : les champs utilisent les noms canoniques déjà
 * enregistrés par UFSC_FFST_Birthplace_Fields / UFSC_SQL.
 */
final class UFSC_FFST_Club_Form_Fields_Bridge {
    private static $prefixes = array( 'president', 'secretaire', 'tresorier', 'entraineur' );

    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render' ), 99 );
    }

    public static function render() {
        if ( ! is_admin() ) { return; }

        $page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-sql-clubs' !== $page || ! in_array( $action, array( 'new', 'edit', 'view' ), true ) ) { return; }

        $club_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $payload = self::payload( $club_id );
        $readonly = 'view' === $action;
        ?>
        <style>
            .ufsc-ffst-native-field{min-width:0}
            .ufsc-ffst-native-field label{display:block;margin:0 0 6px;font-size:12px;font-weight:500}
            .ufsc-ffst-native-field input{width:100%;max-width:100%;box-sizing:border-box}
            .ufsc-ffst-native-field input[readonly]{background:#f0f0f1;color:#50575e}
        </style>
        <script>
        (function(){
            var payload=<?php echo wp_json_encode( $payload ); ?>;
            var readonly=<?php echo $readonly ? 'true' : 'false'; ?>;
            var prefixes=['president','secretaire','tresorier','entraineur'];
            var labels={president:'Président',secretaire:'Secrétaire',tresorier:'Trésorier',entraineur:'Entraîneur'};

            function wrapper(el){
                return el.closest('.ufsc-field,.ufsc-admin-field,.ufsc-form-field,.form-field,.field') || el.parentElement;
            }
            function makeField(name,label,value,type){
                var box=document.createElement('div');
                box.className='ufsc-field ufsc-ffst-native-field';
                var lab=document.createElement('label');
                lab.setAttribute('for',name);
                lab.textContent=label;
                var input=document.createElement('input');
                input.type=type||'text';
                input.name=name;
                input.id=name;
                input.value=value||'';
                input.autocomplete='off';
                if(readonly){input.readOnly=true;input.setAttribute('aria-readonly','true');}
                box.appendChild(lab);box.appendChild(input);
                return box;
            }
            function insertAfter(reference,node){
                if(!reference||!reference.parentNode)return false;
                reference.parentNode.insertBefore(node,reference.nextSibling);
                return true;
            }
            function ensurePrefix(prefix){
                var data=payload[prefix]||{};
                var date=document.querySelector('[name="'+prefix+'_date_naissance"]');
                var email=document.querySelector('[name="'+prefix+'_email"]');
                var nom=document.querySelector('[name="'+prefix+'_nom"]');
                var anchor=date||email||nom;
                if(!anchor)return false;

                var pos=wrapper(anchor);
                if(!date){
                    var dateBox=makeField(prefix+'_date_naissance',labels[prefix]+' – Date de naissance',data.date||'','date');
                    if(insertAfter(pos,dateBox)){pos=dateBox;}
                } else {
                    pos=wrapper(date);
                }

                [
                    ['ville_naissance','Ville de naissance',data.ville||''],
                    ['departement_naissance','Département de naissance',data.departement||''],
                    ['pays_naissance','Pays de naissance',data.pays||'France']
                ].forEach(function(item){
                    var name=prefix+'_'+item[0];
                    var existing=document.querySelector('[name="'+name+'"]');
                    if(existing){pos=wrapper(existing);return;}
                    var box=makeField(name,labels[prefix]+' – '+item[1],item[2],'text');
                    if(insertAfter(pos,box)){pos=box;}
                });
                return true;
            }
            function enhance(){
                var done=0;
                prefixes.forEach(function(prefix){if(ensurePrefix(prefix))done++;});
                return done===prefixes.length;
            }
            function boot(){
                if(enhance())return;
                var tries=0;
                var timer=setInterval(function(){tries++;if(enhance()||tries>20)clearInterval(timer);},150);
            }
            if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}
        })();
        </script>
        <?php
    }

    private static function payload( $club_id ) {
        $row = array();
        if ( $club_id && class_exists( 'UFSC_SQL' ) ) {
            global $wpdb;
            $settings = UFSC_SQL::get_settings();
            $table = isset( $settings['table_clubs'] ) ? $settings['table_clubs'] : '';
            if ( $table ) {
                $row = (array) $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d LIMIT 1", $club_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        }

        $payload = array();
        foreach ( self::$prefixes as $prefix ) {
            $payload[ $prefix ] = array(
                'date'        => (string) ( $row[ $prefix . '_date_naissance' ] ?? '' ),
                'ville'       => (string) ( $row[ $prefix . '_ville_naissance' ] ?? '' ),
                'departement' => (string) ( $row[ $prefix . '_departement_naissance' ] ?? '' ),
                'pays'        => (string) ( $row[ $prefix . '_pays_naissance' ] ?? '' ),
            );
        }
        return $payload;
    }
}
