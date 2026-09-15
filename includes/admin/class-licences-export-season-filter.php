<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Adds a season selector to the licences export screen.
 *
 * UI-only/read-only: the selected season is posted through the existing
 * filter_season parameter already handled by UFSC_Licences_Canonical_Export.
 */
final class UFSC_Licences_Export_Season_Filter {
    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render' ), 45 );
    }

    private static function current_season() {
        if ( class_exists( 'UFSC_Season_Service' ) ) {
            return (string) UFSC_Season_Service::get_current_season();
        }
        return function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '';
    }

    private static function seasons() {
        $current = self::current_season();
        $seasons = array( $current );

        if ( class_exists( 'UFSC_Season_Service' ) ) {
            $seasons = array_merge(
                $seasons,
                (array) UFSC_Season_Service::get_available_seasons(),
                array( UFSC_Season_Service::get_previous_season() )
            );
        }

        $seasons = array_filter( array_map( static function( $season ) {
            return trim( str_replace( '/', '-', (string) $season ) );
        }, $seasons ) );
        $seasons = array_values( array_unique( $seasons ) );

        usort( $seasons, static function( $a, $b ) {
            return strcmp( $b, $a );
        } );

        return $seasons;
    }

    public static function render() {
        if ( ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-exports' !== $page ) { return; }
        if ( ! current_user_can( 'read' ) ) { return; }

        $current = self::current_season();
        if ( '' === $current ) { return; }
        $seasons = self::seasons();
        ?>
        <script>
        (function(){
            var currentSeason=<?php echo wp_json_encode( $current ); ?>;
            var seasons=<?php echo wp_json_encode( $seasons ); ?>;
            function init(){
                var action=document.querySelector('form[action*="admin-post.php"] input[name="action"][value="ufsc_export_data"]');
                var form=action?action.closest('form'):null;
                if(!form)return;

                var entity=form.querySelector('[name="export_entity"]');
                var existing=form.querySelector('select[name="filter_season"]');
                if(existing){
                    if(!existing.value||existing.value==='__current')existing.value=currentSeason;
                    return;
                }

                var visibility=form.querySelector('select[name="filter_visibility"]');
                if(!visibility)return;
                var anchor=visibility.parentElement;
                var row=anchor&&anchor.parentElement?anchor.parentElement:null;
                if(!row)return;

                var wrap=document.createElement('div');
                wrap.className='ufsc-export-season-filter';
                wrap.style.minWidth='190px';

                var label=document.createElement('label');
                label.htmlFor='ufsc_filter_season';
                label.textContent='Saison';
                label.style.display='block';
                label.style.marginBottom='6px';

                var select=document.createElement('select');
                select.id='ufsc_filter_season';
                select.name='filter_season';
                select.style.width='100%';
                select.style.minHeight='32px';

                seasons.forEach(function(season){
                    var option=document.createElement('option');
                    option.value=season;
                    option.textContent=season+(season===currentSeason?' — saison en cours':'');
                    option.selected=season===currentSeason;
                    select.appendChild(option);
                });

                var all=document.createElement('option');
                all.value='all';
                all.textContent='Toutes les saisons';
                select.appendChild(all);

                wrap.appendChild(label);
                wrap.appendChild(select);
                row.appendChild(wrap);

                function syncVisibility(){
                    var isLicences=!entity||entity.value!=='clubs';
                    wrap.style.display=isLicences?'block':'none';
                    select.disabled=!isLicences;
                }
                if(entity)entity.addEventListener('change',syncVisibility);
                syncVisibility();
            }
            if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
        })();
        </script>
        <?php
    }
}
