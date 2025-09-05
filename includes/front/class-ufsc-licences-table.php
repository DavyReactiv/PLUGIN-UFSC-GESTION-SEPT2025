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
        }
    }

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

}
UFSC_Licences_Table::init();
