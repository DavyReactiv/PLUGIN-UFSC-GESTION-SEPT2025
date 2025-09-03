<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table implementation for UFSC clubs.
 */
class UFSC_Gestion_Clubs_List_Table extends WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'ufsc_club',
                'plural'   => 'ufsc_clubs',
                'ajax'     => false,
            )
        );
    }

    /**
     * Retrieve table columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'             => '<input type="checkbox" />',
            'nom'            => __( 'Nom', 'ufsc-clubs' ),
            'region'         => __( 'Région', 'ufsc-clubs' ),
            'statut'         => __( 'Statut', 'ufsc-clubs' ),
            'quota_licences' => __( 'Quota', 'ufsc-clubs' ),
            'date_creation'  => __( 'Créé le', 'ufsc-clubs' ),
        );
    }

    /**
     * Sortable columns.
     */
    protected function get_sortable_columns() {
        return array(
            'nom'           => array( 'nom', false ),
            'region'        => array( 'region', false ),
            'date_creation' => array( 'date_creation', true ),
        );
    }

    /**
     * Bulk actions.
     */
    protected function get_bulk_actions() {
        return array(
            'delete' => __( 'Supprimer', 'ufsc-clubs' ),
        );
    }

    /**
     * Checkbox column.
     */
    protected function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="club_ids[]" value="%d" />', $item['id'] );
    }

    /**
     * Render club name column with row actions.
     */
    protected function column_nom( $item ) {
        $edit_url   = admin_url( 'admin.php?page=ufsc-gestion-clubs&action=edit&id=' . $item['id'] );
        $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=ufsc_sql_delete_club&id=' . $item['id'] ), 'ufsc_sql_delete_club' );

        $actions = array(
            'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), __( 'Modifier', 'ufsc-clubs' ) ),
            'delete' => sprintf( '<a href="%s">%s</a>', esc_url( $delete_url ), __( 'Supprimer', 'ufsc-clubs' ) ),
        );

        return sprintf( '<strong>%1$s</strong> %2$s', esc_html( $item['nom'] ), $this->row_actions( $actions ) );
    }

    /**
     * Default column handler.
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'region':
            case 'statut':
            case 'quota_licences':
                return esc_html( $item[ $column_name ] );
            case 'date_creation':
                return esc_html( mysql2date( 'Y-m-d', $item['date_creation'] ) );
            default:
                return '';
        }
    }

    /**
     * Message displayed when no items found.
     */
    public function no_items() {
        esc_html_e( 'Aucun club trouvé.', 'ufsc-clubs' );
    }

    /**
     * Prepare table items.
     */
    public function prepare_items() {
        global $wpdb;

        $clubs_table = ufsc_get_clubs_table();
        $per_page    = 20;
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        $allowed_orderby = array( 'id', 'nom', 'region', 'statut', 'quota_licences', 'date_creation' );
        $orderby = isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], $allowed_orderby, true ) ? $_REQUEST['orderby'] : 'date_creation';
        $order   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC';

        $total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}`" );

        $query = $wpdb->prepare(
            "SELECT id, nom, region, statut, quota_licences, date_creation FROM `{$clubs_table}` ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
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

