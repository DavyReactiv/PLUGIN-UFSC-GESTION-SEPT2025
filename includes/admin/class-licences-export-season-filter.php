<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Adds season and active-club selectors to the licences export screen.
 *
 * UI-only/read-only: values are posted through filters handled by
 * UFSC_Licences_Canonical_Export. No licence or club data is mutated here.
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
        <style>
            .ufsc-export-primary-filters{display:grid!important;grid-template-columns:repeat(3,minmax(190px,1fr))!important;gap:14px 18px!important;align-items:end!important;padding:16px!important;background:#f8fafc!important;border:1px solid #dfe7ef!important;border-radius:10px!important}
            .ufsc-export-primary-filters>div,.ufsc-export-primary-filters>label{min-width:0!important;margin:0!important}
            .ufsc-export-primary-filters select,.ufsc-export-primary-filters input{width:100%!important;max-width:none!important;min-height:38px!important}
            .ufsc-export-season-filter label,.ufsc-export-active-club-filter label{font-weight:600!important;color:#1d2327!important}
            @media (max-width:1180px){.ufsc-export-primary-filters{grid-template-columns:repeat(2,minmax(190px,1fr))!important}}
            @media (max-width:782px){.ufsc-export-primary-filters{grid-template-columns:1fr!important;padding:12px!important}}
        </style>
        <script>
        (function(){
            var currentSeason=<?php echo wp_json_encode( $current ); ?>;
            var seasons=<?php echo wp_json_encode( $seasons ); ?>;
            function init(){
                var action=document.querySelector('form[action*="admin-post.php"] input[name="action"][value="ufsc_export_data"]');
                var form=action?action.closest('form'):null;
                if(!form)return;

                var entity=form.querySelector('[name="export_entity"]');
                var visibility=form.querySelector('select[name="filter_visibility"]');
                if(!visibility)return;
                var anchor=visibility.parentElement;
                var row=anchor&&anchor.parentElement?anchor.parentElement:null;
                if(!row)return;
                row.classList.add('ufsc-export-primary-filters');

                var seasonWrap=document.createElement('div');
                seasonWrap.className='ufsc-export-season-filter';

                var seasonLabel=document.createElement('label');
                seasonLabel.htmlFor='ufsc_filter_season';
                seasonLabel.textContent='Saison';
                seasonLabel.style.display='block';
                seasonLabel.style.marginBottom='6px';

                var seasonSelect=form.querySelector('select[name="filter_season"]');
                if(!seasonSelect){
                    seasonSelect=document.createElement('select');
                    seasonSelect.id='ufsc_filter_season';
                    seasonSelect.name='filter_season';

                    seasons.forEach(function(season){
                        var option=document.createElement('option');
                        option.value=season;
                        option.textContent=season+(season===currentSeason?' — saison en cours':'');
                        option.selected=season===currentSeason;
                        seasonSelect.appendChild(option);
                    });

                    var all=document.createElement('option');
                    all.value='all';
                    all.textContent='Toutes les saisons';
                    seasonSelect.appendChild(all);
                    seasonWrap.appendChild(seasonLabel);
                    seasonWrap.appendChild(seasonSelect);
                    row.appendChild(seasonWrap);
                }else{
                    if(!seasonSelect.value||seasonSelect.value==='__current')seasonSelect.value=currentSeason;
                    seasonWrap=seasonSelect.closest('.ufsc-export-season-filter')||seasonSelect.parentElement;
                }

                var clubSelect=form.querySelector('select[name="filter_club_affiliation"]');
                var clubWrap=clubSelect?clubSelect.closest('.ufsc-export-active-club-filter'):null;
                if(!clubSelect){
                    clubWrap=document.createElement('div');
                    clubWrap.className='ufsc-export-active-club-filter';

                    var clubLabel=document.createElement('label');
                    clubLabel.htmlFor='ufsc_filter_club_affiliation';
                    clubLabel.textContent='Affiliation du club';
                    clubLabel.style.display='block';
                    clubLabel.style.marginBottom='6px';

                    clubSelect=document.createElement('select');
                    clubSelect.id='ufsc_filter_club_affiliation';
                    clubSelect.name='filter_club_affiliation';
                    clubSelect.innerHTML='<option value="active" selected>Clubs actifs — par défaut</option><option value="inactive">Clubs non actifs</option><option value="all">Tous les clubs</option>';
                    clubWrap.appendChild(clubLabel);
                    clubWrap.appendChild(clubSelect);
                    row.appendChild(clubWrap);
                }

                function syncVisibility(){
                    var isLicences=!entity||entity.value!=='clubs';
                    seasonWrap.style.display=isLicences?'block':'none';
                    clubWrap.style.display=isLicences?'block':'none';
                    seasonSelect.disabled=!isLicences;
                    clubSelect.disabled=!isLicences;
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
