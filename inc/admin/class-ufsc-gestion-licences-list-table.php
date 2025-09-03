<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table implementation for UFSC licences.
 */
class UFSC_Gestion_Licences_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'ufsc_licence',
                'plural'   => 'ufsc_licences',
                'ajax'     => false,
            )
        );
    }

    /**
     * Table columns.
     */
    public function get_columns() {
        return array(
            'cb'            => '<input type="checkbox" />',
            'full_name'     => __( 'Nom', 'ufsc-clubs' ),
            'club_nom'      => __( 'Club', 'ufsc-clubs' ),
            'statut'        => __( 'Statut', 'ufsc-clubs' ),
            'payment_status'=> __( 'Paiement', 'ufsc-clubs' ),
            'date_creation' => __( 'Créée le', 'ufsc-clubs' ),
        );
    }

    protected function get_sortable_columns() {
        return array(
            'full_name'     => array( 'full_name', false ),
            'club_nom'      => array( 'club_nom', false ),
            'date_creation' => array( 'date_creation', true ),
        );
    }

    protected function get_bulk_actions() {
        return array(
            'delete' => __( 'Supprimer', 'ufsc-clubs' ),
        );
    }

    protected function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="licence_ids[]" value="%d" />', $item['id'] );
    }

    protected function column_full_name( $item ) {
        $edit_url   = admin_url( 'admin.php?page=ufsc-gestion-licences&action=edit&id=' . $item['id'] );
        $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=ufsc_sql_delete_licence&id=' . $item['id'] ), 'ufsc_sql_delete_licence' );

        $actions = array(
            'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), __( 'Modifier', 'ufsc-clubs' ) ),
            'delete' => sprintf( '<a href="%s">%s</a>', esc_url( $delete_url ), __( 'Supprimer', 'ufsc-clubs' ) ),
        );

        return sprintf( '<strong>%1$s</strong> %2$s', esc_html( $item['full_name'] ), $this->row_actions( $actions ) );
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'club_nom':
            case 'statut':
            case 'payment_status':
                return esc_html( $item[ $column_name ] );
            case 'date_creation':
                return esc_html( mysql2date( 'Y-m-d', $item['date_creation'] ) );
            default:
                return '';
        }
    }

    public function no_items() {
        esc_html_e( 'Aucune licence trouvée.', 'ufsc-clubs' );
    }

    public function prepare_items() {
        global $wpdb;

        $licences_table = ufsc_get_licences_table();
        $clubs_table    = ufsc_get_clubs_table();
        $per_page       = 20;
        $current_page   = $this->get_pagenum();
        $offset         = ( $current_page - 1 ) * $per_page;

        $allowed_orderby = array( 'full_name', 'club_nom', 'statut', 'payment_status', 'date_creation', 'id' );
        $orderby = isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], $allowed_orderby, true ) ? $_REQUEST['orderby'] : 'date_creation';
        $order   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC';

        $total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$licences_table}`" );

        $query = $wpdb->prepare(
            "SELECT l.id, CONCAT(l.prenom, ' ', l.nom_licence) AS full_name, c.nom AS club_nom, l.statut, l.payment_status, l.date_creation
             FROM `{$licences_table}` l
             LEFT JOIN `{$clubs_table}` c ON l.club_id = c.id
             ORDER BY {$orderby} {$order}
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        $items = $wpdb->get_results( $query, ARRAY_A );

        $this->items = $items;

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
            )
        );

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
    }
}

