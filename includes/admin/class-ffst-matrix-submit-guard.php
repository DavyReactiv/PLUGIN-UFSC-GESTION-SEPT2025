<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Garde-fou UX pour les exports matrices FFST.
 * Lecture seule : prépare uniquement les champs POST du formulaire d'export.
 */
final class UFSC_FFST_Matrix_Submit_Guard {
    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render' ), 1400 );
    }

    public static function render() {
        if ( ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-ffst-documents' !== $page ) { return; }
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) { return; }
        ?>
        <script id="ufsc-ffst-matrix-submit-guard-script">
        (function(){
            var form=document.getElementById('ufsc-ffst-matrix-form');
            var list=document.getElementById('ufsc-matrix-list');
            if(!form||!list)return;

            function visibleRows(){
                return Array.from(list.querySelectorAll('.ufsc-ffst-matrix__club')).filter(function(row){
                    return window.getComputedStyle(row).display!=='none';
                });
            }

            function prepare(button){
                var rows=Array.from(list.querySelectorAll('.ufsc-ffst-matrix__club'));
                var visible=visibleRows();

                // Un club masqué par les filtres ne doit jamais partir dans l'export.
                rows.forEach(function(row){
                    if(window.getComputedStyle(row).display==='none'){
                        var cb=row.querySelector('input[type="checkbox"][name="club_ids[]"]');
                        if(cb)cb.checked=false;
                    }
                });

                var selected=visible.filter(function(row){
                    var cb=row.querySelector('input[type="checkbox"][name="club_ids[]"]');
                    return cb&&cb.checked;
                });

                // Sans sélection manuelle : exporter tous les résultats visibles.
                if(!selected.length){
                    visible.forEach(function(row){
                        var cb=row.querySelector('input[type="checkbox"][name="club_ids[]"]');
                        if(cb)cb.checked=true;
                    });
                    selected=visible;
                }

                var counter=document.getElementById('ufsc-matrix-count');
                if(counter)counter.textContent=String(selected.length);

                // Fixer explicitement le type d'export pour fiabiliser le POST.
                var hidden=form.querySelector('input[data-ufsc-matrix-type="1"]');
                if(!hidden){
                    hidden=document.createElement('input');
                    hidden.type='hidden';
                    hidden.name='matrix_type';
                    hidden.setAttribute('data-ufsc-matrix-type','1');
                    form.appendChild(hidden);
                }
                hidden.value=button&&button.value?button.value:'';

                return selected.length>0;
            }

            form.querySelectorAll('button[type="submit"][name="matrix_type"]').forEach(function(button){
                button.addEventListener('click',function(e){
                    if(!prepare(button)){
                        e.preventDefault();
                        alert('Aucun club ne correspond aux filtres sélectionnés.');
                    }
                },true);
            });
        })();
        </script>
        <?php
    }
}

UFSC_FFST_Matrix_Submit_Guard::init();
