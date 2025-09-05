<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render a responsive licences table with filters and AJAX pagination.
 */
class UFSC_Licences_Table {

    /**
     * Register AJAX handlers.
     */

    public static function render( $licences, $args = array() ) {
        $status = isset( $_GET['ufsc_status'] ) ? sanitize_text_field( wp_unslash( $_GET['ufsc_status'] ) ) : '';

        $status = isset( $args['status'] ) ? $args['status'] : $status;

        // Filter licences in-memory if a status filter is provided.
        if ( $status ) {
            $licences = array_filter( $licences, function( $licence ) use ( $status ) {
                $licence_status = $licence->statut ?? ( $licence->status ?? '' );
                return $licence_status === $status;
            } );

    public static function init() {
        add_action( 'wp_ajax_ufsc_fetch_licences', array( __CLASS__, 'ajax_fetch_licences' ) );
        add_action( 'wp_ajax_nopriv_ufsc_fetch_licences', array( __CLASS__, 'ajax_fetch_licences' ) );
    }

    /**
     * AJAX callback to fetch licences with filters and pagination.
     */
    public static function ajax_fetch_licences() {
        check_ajax_referer( 'ufsc_frontend_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Vous devez être connecté.', 'ufsc-clubs' ) ), 401 );
        }

        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        if ( ! $club_id ) {
            wp_send_json_error( array( 'message' => __( 'Aucun club associé.', 'ufsc-clubs' ) ), 403 );
        }

        $page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
        $per_page = min( 100, max( 1, (int) ( $_GET['per_page'] ?? 25 ) ) );
        $search   = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
        $status   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        $sex      = isset( $_GET['sex'] ) ? sanitize_text_field( wp_unslash( $_GET['sex'] ) ) : '';
        $category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
        $orderby  = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'id';
        $order    = ( isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ) ? 'ASC' : 'DESC';

        global $wpdb;
        $table = ufsc_sanitize_table_name( ufsc_get_licences_table() );
        if ( ! ufsc_table_exists( $table ) ) {
            wp_send_json_success( array( 'items' => array(), 'total' => 0, 'page' => $page ) );
        }

        $where  = array( 'club_id = %d' );
        $params = array( $club_id );

        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(prenom LIKE %s OR nom LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        if ( $status ) {
            $where[]  = 'statut = %s';
            $params[] = $status;
        }
        if ( $sex ) {
            $where[]  = 'LOWER(sexe) = %s';
            $params[] = strtolower( $sex );
        }
        if ( $category ) {
            if ( 'competition' === $category ) {
                $where[] = 'competition = 1';
            } elseif ( 'loisir' === $category ) {
                $where[] = '(competition = 0 OR competition IS NULL)';
            }
        }

        $allowed_orderby = array(
            'id'         => 'id',
            'holder'     => 'nom',
            'gender'     => 'sexe',
            'practice'   => 'competition',
            'age'        => 'date_naissance',
            'status'     => 'statut',
            'expiration' => 'certificat_expiration',
            'included'   => 'is_included',
        );
        $orderby_sql = isset( $allowed_orderby[ $orderby ] ) ? $allowed_orderby[ $orderby ] : 'id';

        $where_sql = implode( ' AND ', $where );
        $offset    = ( $page - 1 ) * $per_page;

        $query = $wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS id, prenom, nom, sexe, competition, date_naissance, statut, certificat_expiration, date_expiration, is_included, paid
             FROM {$table} WHERE {$where_sql}
             ORDER BY {$orderby_sql} {$order}
             LIMIT %d OFFSET %d",
            array_merge( $params, array( $per_page, $offset ) )
        );
        $rows  = $wpdb->get_results( $query );
        $total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

        $items = array();
        foreach ( $rows as $row ) {
            $full_name  = trim( ( $row->prenom ?? '' ) . ' ' . ( $row->nom ?? '' ) );
            $gender_code = strtolower( $row->sexe ?? '' );
            switch ( $gender_code ) {
                case 'm':
                case 'h':
                    $gender = __( 'Homme', 'ufsc-clubs' );
                    break;
                case 'f':
                    $gender = __( 'Femme', 'ufsc-clubs' );
                    break;
                default:
                    $gender = $row->sexe ?? '';
            }
            $practice = (int) $row->competition ? __( 'Compétition', 'ufsc-clubs' ) : __( 'Loisir', 'ufsc-clubs' );
            $age = '';
            if ( ! empty( $row->date_naissance ) ) {
                $birth = strtotime( $row->date_naissance );
                if ( $birth ) {
                    $age = floor( ( current_time( 'timestamp' ) - $birth ) / YEAR_IN_SECONDS );
                }
            }
            $status_badge = UFSC_Badges::render_licence_badge( $row->statut, array( 'custom_class' => 'ufsc-badge' ) );
            $expiration = '';
            if ( ! empty( $row->certificat_expiration ) ) {
                $expiration = mysql2date( get_option( 'date_format' ), $row->certificat_expiration );
            } elseif ( ! empty( $row->date_expiration ) ) {
                $expiration = mysql2date( get_option( 'date_format' ), $row->date_expiration );
            }
            $included = ! empty( $row->is_included )
                ? '<span class="ufsc-badge badge-success ufsc-badge-included">' . esc_html__( 'Incluse', 'ufsc-clubs' ) . '</span>'
                : '';

            $actions  = '<div class="ufsc-actions">';
            $actions .= '<a class="ufsc-action" href="' . esc_url( add_query_arg( array( 'ufsc_action' => 'view', 'licence_id' => $row->id ) ) ) . '">' . esc_html__( 'Consulter', 'ufsc-clubs' ) . '</a>';
            if ( empty( $row->statut ) || ! UFSC_Badges::is_active_licence_status( $row->statut ) ) {
                $actions .= ' <a class="ufsc-action" href="' . esc_url( add_query_arg( array( 'ufsc_action' => 'edit', 'licence_id' => $row->id ) ) ) . '">' . esc_html__( 'Modifier', 'ufsc-clubs' ) . '</a>';
                if ( current_user_can( 'manage_options' ) ) {
                    $actions .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ufsc-inline-form">';
                    $actions .= '<input type="hidden" name="action" value="ufsc_delete_licence" />';
                    $actions .= '<input type="hidden" name="licence_id" value="' . intval( $row->id ) . '" />';
                    $actions .= wp_nonce_field( 'ufsc_delete_licence', '_wpnonce', true, false );
                    $actions .= '<button type="submit" class="ufsc-action ufsc-delete">' . esc_html__( 'Supprimer', 'ufsc-clubs' ) . '</button>';
                    $actions .= '</form>';
                }
            }
            $actions .= '</div>';

            $items[] = array(
                'id'         => (int) $row->id,
                'holder'     => esc_html( $full_name ),
                'gender'     => esc_html( $gender ),
                'practice'   => esc_html( $practice ),
                'age'        => '' !== $age ? (int) $age : '',
                'status'     => $status_badge,
                'expiration' => esc_html( $expiration ),
                'included'   => $included,
                'actions'    => $actions,
            );

        }

        wp_send_json_success(
            array(
                'items'    => $items,
                'total'    => $total,
                'page'     => $page,
                'per_page' => $per_page,
            )
        );
    }

    /**
     * Output licences table container and filters.
     *
     * @param array $licences Unused; kept for backward compatibility.
     * @param array $args     Optional arguments.
     */
    public static function render( $licences = array(), $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        $nonce = wp_create_nonce( 'ufsc_frontend_nonce' );

        echo '<form id="ufsc-licences-filters" class="ufsc-licences-filters" role="search">';
        echo '<div class="ufsc-filter-group">';
        echo '<label for="ufsc_search">' . esc_html__( 'Recherche', 'ufsc-clubs' ) . '</label>';
        echo '<input type="search" id="ufsc_search" name="search" />';
        echo '</div>';

        echo '<div class="ufsc-filter-group">';
        echo '<label for="ufsc_status">' . esc_html__( 'Statut', 'ufsc-clubs' ) . '</label>';
        echo '<select id="ufsc_status" name="status">';
        echo '<option value="">' . esc_html__( 'Tous', 'ufsc-clubs' ) . '</option>';
        $status_options = array(
            'valide'     => __( 'Validée', 'ufsc-clubs' ),
            'en_attente' => __( 'En attente', 'ufsc-clubs' ),
            'rejete'     => __( 'Rejetée', 'ufsc-clubs' ),
            'paye'       => __( 'Payée', 'ufsc-clubs' ),
            'refuse'     => __( 'Refusée', 'ufsc-clubs' ),
        );
        foreach ( $status_options as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';


        echo '<button type="submit" class="ufsc-btn ufsc-btn-primary">' . esc_html__( 'Filtrer', 'ufsc-clubs' ) . '</button>';

        echo '<div class="ufsc-filter-group">';
        echo '<label for="ufsc_sex">' . esc_html__( 'Sexe', 'ufsc-clubs' ) . '</label>';
        echo '<select id="ufsc_sex" name="sex">';
        echo '<option value="">' . esc_html__( 'Tous', 'ufsc-clubs' ) . '</option>';
        echo '<option value="m">' . esc_html__( 'Homme', 'ufsc-clubs' ) . '</option>';
        echo '<option value="f">' . esc_html__( 'Femme', 'ufsc-clubs' ) . '</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="ufsc-filter-group">';
        echo '<label for="ufsc_category">' . esc_html__( 'Catégorie', 'ufsc-clubs' ) . '</label>';
        echo '<select id="ufsc_category" name="category">';
        echo '<option value="">' . esc_html__( 'Toutes', 'ufsc-clubs' ) . '</option>';
        echo '<option value="loisir">' . esc_html__( 'Loisir', 'ufsc-clubs' ) . '</option>';
        echo '<option value="competition">' . esc_html__( 'Compétition', 'ufsc-clubs' ) . '</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="ufsc-filter-group">';
        echo '<label for="ufsc_per_page">' . esc_html__( 'Par page', 'ufsc-clubs' ) . '</label>';
        echo '<select id="ufsc_per_page" name="per_page" class="ufsc-page-size">';
        echo '<option value="25">25</option><option value="50">50</option><option value="100">100</option>';
        echo '</select>';
        echo '</div>';

        echo '<button type="submit" class="ufsc-btn ufsc-btn-primary">' . esc_html__( 'Appliquer', 'ufsc-clubs' ) . '</button>';

        echo '</form>';

        echo '<table class="ufsc-table ufsc-licences-table" tabindex="-1" aria-live="polite" data-nonce="' . esc_attr( $nonce ) . '">';
        echo '<thead><tr>';
        $headers = array(
            'id'         => __( 'ID', 'ufsc-clubs' ),
            'holder'     => __( 'Titulaire', 'ufsc-clubs' ),
            'gender'     => __( 'Sexe', 'ufsc-clubs' ),
            'practice'   => __( 'Pratique', 'ufsc-clubs' ),
            'age'        => __( 'Âge', 'ufsc-clubs' ),
            'status'     => __( 'Statut', 'ufsc-clubs' ),
            'expiration' => __( 'Expiration', 'ufsc-clubs' ),
            'included'   => __( 'Incluse', 'ufsc-clubs' ),
            'actions'    => __( 'Actions', 'ufsc-clubs' ),
        );
        foreach ( $headers as $key => $label ) {
            if ( 'actions' === $key ) {
                echo '<th scope="col">' . esc_html( $label ) . '</th>';
            } else {
                echo '<th scope="col" data-key="' . esc_attr( $key ) . '" tabindex="0" aria-sort="none">' . esc_html( $label ) . '</th>';
            }
        }
        echo '</tr></thead><tbody></tbody></table>';

        echo '<div class="ufsc-pagination">';
        echo '<button type="button" class="ufsc-prev" aria-label="' . esc_attr__( 'Page précédente', 'ufsc-clubs' ) . '">&laquo;</button>';
        echo '<span class="ufsc-page-info"></span>';
        echo '<button type="button" class="ufsc-next" aria-label="' . esc_attr__( 'Page suivante', 'ufsc-clubs' ) . '">&raquo;</button>';
        echo '</div>';

        ?>
        <script>
        jQuery(function($){
            var state = {
                page: 1,
                per_page: 25,
                search: '',
                status: '',
                sex: '',
                category: '',
                orderby: 'id',
                order: 'asc'
            };
            var $table = $('.ufsc-licences-table');

            function updateSort(){
                $table.find('th[data-key]').attr('aria-sort','none');
                $table.find('th[data-key="'+state.orderby+'"]').attr('aria-sort', state.order === 'asc' ? 'ascending' : 'descending');
            }

            function fetchLicences(){
                $table.attr('aria-busy','true');
                $.ajax({
                    url: ufsc_frontend_vars.ajax_url,
                    data: $.extend({action:'ufsc_fetch_licences', nonce: $table.data('nonce')}, state),
                    dataType: 'json',
                    success: function(resp){
                        if(!resp.success){ return; }
                        var $tbody = $table.find('tbody').empty();
                        $.each(resp.data.items, function(i,item){
                            var row = '<tr>'+
                                '<td>'+item.id+'</td>'+
                                '<td>'+item.holder+'</td>'+
                                '<td>'+item.gender+'</td>'+
                                '<td>'+item.practice+'</td>'+
                                '<td>'+item.age+'</td>'+
                                '<td>'+item.status+'</td>'+
                                '<td>'+item.expiration+'</td>'+
                                '<td>'+item.included+'</td>'+
                                '<td>'+item.actions+'</td>'+
                                '</tr>';
                            $tbody.append(row);
                        });
                        var totalPages = Math.ceil(resp.data.total / state.per_page) || 1;
                        $('.ufsc-page-info').text(state.page + ' / ' + totalPages);
                        $('.ufsc-prev').prop('disabled', state.page <= 1);
                        $('.ufsc-next').prop('disabled', state.page >= totalPages);
                        $table.attr('aria-busy','false').focus();
                    }
                });
            }

            $('#ufsc-licences-filters').on('submit', function(e){
                e.preventDefault();
                state.search = $('#ufsc_search').val();
                state.status = $('#ufsc_status').val();
                state.sex = $('#ufsc_sex').val();
                state.category = $('#ufsc_category').val();
                state.per_page = $('#ufsc_per_page').val();
                state.page = 1;
                fetchLicences();
            });

            $('.ufsc-prev').on('click', function(){
                if(state.page > 1){
                    state.page--;
                    fetchLicences();
                }

                $licence_status = $licence->statut ?? ( $licence->status ?? '' );
                $badge_options  = array( 'custom_class' => 'ufsc-badge' );
                if ( isset( $status_options[ $licence_status ] ) ) {
                    $badge_options['custom_label'] = $status_options[ $licence_status ];
                }
                $status_badge = UFSC_Badges::render_licence_badge( $licence_status, $badge_options );
                $expiration = '';
                if ( ! empty( $licence->certificat_expiration ) ) {
                    $expiration = mysql2date( get_option( 'date_format' ), $licence->certificat_expiration );
                } elseif ( ! empty( $licence->date_expiration ) ) {
                    $expiration = mysql2date( get_option( 'date_format' ), $licence->date_expiration );
                }
                echo '<tr>';
                echo '<td>' . intval( $licence->id ?? 0 ) . '</td>';
                echo '<td>' . esc_html( $full_name ) . '</td>';
                echo '<td>' . esc_html( $gender ) . '</td>';
                echo '<td>' . esc_html( $practice ) . '</td>';
                echo '<td>' . ( '' !== $age ? intval( $age ) : '' ) . '</td>';
                echo '<td>' . $status_badge . '</td>';
                echo '<td>' . esc_html( $expiration ) . '</td>';
                echo '<td>';
                if ( ! empty( $licence->is_included ) ) {
                    echo '<span class="ufsc-badge badge-success ufsc-badge-included">' . esc_html__( 'Incluse', 'ufsc-clubs' ) . '</span>';
                }
                echo '</td>';
                echo '<td>';
                echo '<div class="ufsc-actions">';
                echo '<a class="ufsc-action" href="' . esc_url( add_query_arg( array( 'ufsc_action' => 'view', 'licence_id' => $licence->id ) ) ) . '">' . esc_html__( 'Consulter', 'ufsc-clubs' ) . '</a>';
                if ( empty( $licence->statut ) || ! UFSC_Badges::is_active_licence_status( $licence->statut ) ) {
                    echo ' <a class="ufsc-action" href="' . esc_url( add_query_arg( array( 'ufsc_action' => 'edit', 'licence_id' => $licence->id ) ) ) . '">' . esc_html__( 'Modifier', 'ufsc-clubs' ) . '</a>';
                    if ( current_user_can( 'manage_options' ) ) {
                        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ufsc-inline-form">';
                        echo '<input type="hidden" name="action" value="ufsc_delete_licence" />';
                        echo '<input type="hidden" name="licence_id" value="' . intval( $licence->id ) . '" />';
                        wp_nonce_field( 'ufsc_delete_licence' );
                        echo '<button type="submit" class="ufsc-action ufsc-delete">' . esc_html__( 'Supprimer', 'ufsc-clubs' ) . '</button>';
                        echo '</form>';

            });
            $('.ufsc-next').on('click', function(){
                state.page++;
                fetchLicences();
            });

            $table.on('click keypress', 'th[data-key]', function(e){
                if(e.type === 'click' || e.key === 'Enter'){
                    var key = $(this).data('key');
                    if(state.orderby === key){
                        state.order = state.order === 'asc' ? 'desc' : 'asc';
                    } else {
                        state.orderby = key;
                        state.order = 'asc';

                    }
                    updateSort();
                    fetchLicences();
                }
            });

            updateSort();
            fetchLicences();
        });
        </script>
        <?php
    }
}

UFSC_Licences_Table::init();

