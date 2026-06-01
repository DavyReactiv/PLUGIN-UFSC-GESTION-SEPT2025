<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Clubs List Table
 * Enhanced admin list with filters, search, and pagination
 */
class UFSC_Clubs_List_Table {
    private static function get_current_request_url() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        if ( ! is_string( $request_uri ) || '' === $request_uri ) {
            return admin_url( 'admin.php?page=ufsc-sql-clubs' );
        }

        return $request_uri;
    }

    private static function get_admin_season_label() {
        if ( function_exists( 'ufsc_get_admin_current_season_label' ) ) {
            return (string) ufsc_get_admin_current_season_label();
        }

        return __( 'saison en cours', 'ufsc-clubs' );
    }

    /**
     * Render enhanced clubs list
     */
    public static function render() {
        global $wpdb;

        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        if ( function_exists( 'ufsc_sanitize_table_name' ) ) {
            $clubs_table = ufsc_sanitize_table_name( $clubs_table );
        }
        $club_columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $clubs_table ) : array();
        $licence_counts = UFSC_CL_Utils::get_valid_licence_counts_by_club();

        // Handle filters and search
        $filters = self::get_filters();
        $search = self::get_search_query();
        $pagination = self::get_pagination_params();
        $sorting = self::get_sorting_params();

        // Build WHERE conditions
        $where_conditions = self::build_where_conditions( $filters, $search, $club_columns, $clubs_table );
        $where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

        // Build ORDER BY clause
        $order_clause = self::build_order_clause( $sorting, $club_columns, $clubs_table );

        // Get total count for pagination
        $total_query = "SELECT COUNT(*) FROM `{$clubs_table}` {$where_clause}";
        $total_items = (int) $wpdb->get_var( $total_query );

        // Get clubs with pagination
        $offset = ( $pagination['paged'] - 1 ) * $pagination['per_page'];
        $clubs_query = "
            SELECT *
            FROM `{$clubs_table}`
            {$where_clause}
            {$order_clause}
            LIMIT {$pagination['per_page']} OFFSET {$offset}
        ";

        $clubs = $wpdb->get_results( $clubs_query );

        // Calculate pagination
        $total_pages = ceil( $total_items / $pagination['per_page'] );

        // Render the page
        echo '<div class="wrap ufsc-clubs-admin-page">';
        echo '<div class="ufsc-clubs-shell">';
        echo '<div class="ufsc-clubs-hero">';
        echo '<div>';
        echo '<span class="ufsc-clubs-kicker">' . esc_html__( 'Administration UFSC', 'ufsc-clubs' ) . '</span>';
        echo '<h1 class="ufsc-admin-title">' . esc_html__( 'Clubs UFSC — Affiliations et suivi administratif', 'ufsc-clubs' ) . '</h1>';
        echo '<p class="ufsc-admin-subtitle">' . esc_html__( 'Retrouvez ici l’ensemble des clubs enregistrés, leur région, leur statut d’affiliation, le nombre de licences associées et l’état des documents administratifs. Cette page permet de suivre les clubs actifs, en attente ou à renouveler.', 'ufsc-clubs' ) . '</p>';
        echo '</div>';
        echo '<div class="ufsc-season-pill"><span>' . esc_html__( 'Saison affichée', 'ufsc-clubs' ) . '</span><strong>' . esc_html( self::get_admin_season_label() ) . '</strong></div>';
        echo '</div>';
        echo '<div class="ufsc-renewal-notice"><span class="dashicons dashicons-info"></span><p>' . esc_html__( 'Renouvellement des affiliations : à chaque nouvelle saison, les clubs devront confirmer ou renouveler leur affiliation afin de maintenir leurs licences actives.', 'ufsc-clubs' ) . '</p></div>';
        if ( function_exists( 'ufsc_user_has_all_regions_access' ) && ! ufsc_user_has_all_regions_access() ) {
            $allowed_regions = function_exists( 'ufsc_current_user_allowed_regions' ) ? ufsc_current_user_allowed_regions() : array();
            if ( empty( $allowed_regions ) ) {
                echo '<div class="notice notice-warning"><p>' . esc_html__( 'Aucune région n’est associée à votre compte. Contactez un administrateur UFSC.', 'ufsc-clubs' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>' . esc_html__( 'Résultats filtrés selon vos régions UFSC autorisées.', 'ufsc-clubs' ) . '</p></div>';
            }
        }

        // Affichage des notices
        if ( '1' === self::get_query_value( 'updated', 'key' ) ) {
            echo UFSC_CL_Utils::show_success(__('Club enregistré avec succès', 'ufsc-clubs'));
        }
        if ( '1' === self::get_query_value( 'deleted', 'key' ) ) {
            $deleted_id = absint( self::get_query_value( 'deleted_id' ) );
            echo UFSC_CL_Utils::show_success(__('Le club #'.$deleted_id.' a été supprimé.', 'ufsc-clubs'));
        }
        if ( '' !== self::get_query_value( 'error' ) ) {
            echo UFSC_CL_Utils::show_error( self::get_query_value( 'error' ) );
        }

        // Action buttons
        self::render_action_buttons();

        self::render_statistics_cards( $club_columns, $clubs_table, $licence_counts );

        // Filters and search
        self::render_filters( $filters, $club_columns, $clubs_table, $search );

        self::render_quick_filters( $filters );

        //Action Grop
        //self::bulck_action_grop_by_club();

        // Results info
        self::render_results_info( $total_items, $pagination );

        // Main table
        self::render_clubs_table( $clubs, $sorting, $licence_counts );

        // Pagination
        self::render_pagination( $pagination['paged'], $total_pages );

        echo '</div>';
        echo '</div>';
    }



    private static function get_request_value( $source, $key, $type = 'text' ) {
        if ( ! is_array( $source ) || ! isset( $source[ $key ] ) ) {
            return '';
        }
        $value = wp_unslash( $source[ $key ] );
        if ( is_array( $value ) || null === $value ) {
            return '';
        }
        $value = (string) $value;
        return 'key' === $type ? sanitize_key( $value ) : sanitize_text_field( $value );
    }

    private static function get_query_value( $key, $type = 'text' ) {
        return self::get_request_value( $_GET, $key, $type );
    }

    /**
     * Get current filters
     */
    private static function get_filters() {
        $filters = array(
            'region' => self::get_query_value( 'region' ),
            'statut' => self::get_query_value( 'statut' ),
            'created_from' => self::get_query_value( 'created_from' ),
            'created_to' => self::get_query_value( 'created_to' ),
            'doc_status' => self::get_query_value( 'doc_status', 'key' ),
            'affiliation_status' => self::get_query_value( 'affiliation_status', 'key' ),
            'licence_range' => self::get_query_value( 'licence_range', 'key' ),
            'season' => self::get_query_value( 'season' )
        );

        return $filters;
    }

    /**
     * Get search query
     */
    private static function get_search_query() {
        return self::get_query_value( 'q' );
    }

    /**
     * Get pagination parameters
     */
    private static function get_pagination_params() {
        $per_page_options = array( 20, 50, 100 );
        $requested_per_page = absint( self::get_query_value( 'per_page' ) );
        $per_page = in_array( $requested_per_page, $per_page_options, true ) ? $requested_per_page : 20;

        return array(
            'paged' => max( 1, absint( self::get_query_value( 'paged' ) ) ),
            'per_page' => $per_page
        );
    }

    /**
     * Get sorting parameters
     */
    private static function get_sorting_params() {
        $allowed_orderby = array( 'nom', 'date_creation', 'region' );
        $allowed_order = array( 'asc', 'desc' );
        $requested_orderby = self::get_query_value( 'orderby', 'key' );
        $requested_order = self::get_query_value( 'order', 'key' );

        return array(
            'orderby' => in_array( $requested_orderby, $allowed_orderby, true ) ? $requested_orderby : 'date_creation',
            'order' => in_array( $requested_order, $allowed_order, true ) ? $requested_order : 'desc'
        );
    }

    /**
     * Expand common legacy club status aliases while preserving exact filtering for unknown values.
     */
    private static function get_status_filter_values( $status ) {
        $normalized = sanitize_key( (string) $status );
        if ( in_array( $normalized, array( 'actif', 'active', 'valide', 'validated' ), true ) ) {
            return array( 'actif', 'active', 'valide', 'validated' );
        }
        if ( in_array( $normalized, array( 'en_attente', 'pending', 'a_regler', 'creating', 'en_cours_de_creation' ), true ) ) {
            return array( 'en_attente', 'pending', 'a_regler', 'creating', 'en_cours_de_creation' );
        }
        if ( in_array( $normalized, array( 'suspendu', 'suspended', 'refuse', 'rejected', 'desactive', 'inactive' ), true ) ) {
            return array( 'suspendu', 'suspended', 'refuse', 'rejected', 'desactive', 'inactive' );
        }
        return array( (string) $status );
    }

    /**
     * Build WHERE conditions
     */
    private static function build_where_conditions( $filters, $search, $columns, $clubs_table ) {
        global $wpdb;
        $conditions = array();

        // Search query
        if ( ! empty( $search ) ) {
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $search_parts = array();
            $search_values = array();

            foreach ( array( 'nom', 'email' ) as $column ) {
                if ( self::has_column( $columns, $clubs_table, $column ) ) {
                    $search_parts[]  = "{$column} LIKE %s";
                    $search_values[] = $search_like;
                }
            }

            if ( ! empty( $search_parts ) ) {
                $conditions[] = $wpdb->prepare(
                    '(' . implode( ' OR ', $search_parts ) . ')',
                    $search_values
                );
            }
        }

        // Region filter
        if ( ! empty( $filters['region'] ) && self::has_column( $columns, $clubs_table, 'region' ) ) {
            $conditions[] = $wpdb->prepare( "region = %s", $filters['region'] );
        }

        // Status filter
        if ( ! empty( $filters['statut'] ) && self::has_column( $columns, $clubs_table, 'statut' ) ) {
            $status_values = self::get_status_filter_values( $filters['statut'] );
            if ( count( $status_values ) > 1 ) {
                $placeholders = implode( ',', array_fill( 0, count( $status_values ), '%s' ) );
                $conditions[] = $wpdb->prepare( "statut IN ({$placeholders})", $status_values );
            } else {
                $conditions[] = $wpdb->prepare( "statut = %s", $filters['statut'] );
            }
        }

        // Date range filters
        if ( ! empty( $filters['created_from'] ) && self::is_valid_date( $filters['created_from'] ) && self::has_column( $columns, $clubs_table, 'date_creation' ) ) {
            $conditions[] = $wpdb->prepare( "DATE(date_creation) >= %s", $filters['created_from'] );
        }

        if ( ! empty( $filters['created_to'] ) && self::is_valid_date( $filters['created_to'] ) && self::has_column( $columns, $clubs_table, 'date_creation' ) ) {
            $conditions[] = $wpdb->prepare( "DATE(date_creation) <= %s", $filters['created_to'] );
        }

        $doc_fields = self::get_available_document_fields( $columns, $clubs_table );
        if ( ! empty( $doc_fields ) && ! empty( $filters['doc_status'] ) ) {
            $doc_conditions = array();
            foreach ( $doc_fields as $field ) {
                $doc_conditions[] = "(`{$field}` IS NOT NULL AND `{$field}` != '')";
            }
            if ( 'complete' === $filters['doc_status'] ) {
                $conditions[] = '(' . implode( ' AND ', $doc_conditions ) . ')';
            } elseif ( 'incomplete' === $filters['doc_status'] ) {
                $conditions[] = 'NOT (' . implode( ' AND ', $doc_conditions ) . ')';
            }
        }

        if ( ! empty( $filters['affiliation_status'] ) && self::has_verified_column( $columns, $clubs_table, 'num_affiliation' ) ) {
            if ( 'assigned' === $filters['affiliation_status'] ) {
                $conditions[] = "(num_affiliation IS NOT NULL AND num_affiliation != '')";
            } elseif ( 'missing' === $filters['affiliation_status'] ) {
                $conditions[] = "(num_affiliation IS NULL OR num_affiliation = '')";
            }
        }

        $licence_expression = self::get_licence_count_expression( $clubs_table );
        if ( '' !== $licence_expression && ! empty( $filters['licence_range'] ) ) {
            if ( 'zero' === $filters['licence_range'] ) {
                $conditions[] = $licence_expression . ' = 0';
            } elseif ( 'one_to_nine' === $filters['licence_range'] ) {
                $conditions[] = $licence_expression . ' BETWEEN 1 AND 9';
            } elseif ( 'ten_plus' === $filters['licence_range'] ) {
                $conditions[] = $licence_expression . ' >= 10';
            } elseif ( 'under_ten' === $filters['licence_range'] ) {
                $conditions[] = $licence_expression . ' < 10';
            }
        }

        $season_column = self::get_season_column( $columns, $clubs_table );
        if ( '' !== $season_column && ! empty( $filters['season'] ) ) {
            $conditions[] = $wpdb->prepare( "`{$season_column}` = %s", $filters['season'] );
        }

        if ( self::has_column( $columns, $clubs_table, 'region' ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region' );
            if ( $scope_condition ) {
                $conditions[] = $scope_condition;
            }

            if ( function_exists( 'ufsc_user_has_all_regions_access' ) && ! ufsc_user_has_all_regions_access() ) {
                $allowed_regions = function_exists( 'ufsc_current_user_allowed_regions' ) ? ufsc_current_user_allowed_regions() : array();
                if ( empty( $allowed_regions ) ) {
                    $conditions[] = '1 = 0';
                } else {
                    $placeholders = implode( ',', array_fill( 0, count( $allowed_regions ), '%s' ) );
                    $conditions[] = $wpdb->prepare( "region IN ({$placeholders})", $allowed_regions );
                }
            }
        }

        return $conditions;
    }


    /**
     * Check a SQL column only when its presence can be verified.
     *
     * The generic has_column() keeps a permissive fallback for legacy filters.
     * New optional SQL filters/statistics must be stricter so they are skipped
     * instead of referencing a missing column on older installations.
     */
    private static function has_verified_column( $columns, $table, $column ) {
        if ( is_array( $columns ) && ! empty( $columns ) ) {
            return in_array( $column, $columns, true );
        }

        if ( function_exists( 'ufsc_table_has_column' ) ) {
            return ufsc_table_has_column( $table, $column );
        }

        if ( function_exists( 'ufsc_table_columns' ) ) {
            $fetched = ufsc_table_columns( $table );
            return is_array( $fetched ) && in_array( $column, $fetched, true );
        }

        return false;
    }

    /**
     * Return document fields that are physically available on the clubs table.
     */
    private static function get_available_document_fields( $columns, $clubs_table ) {
        $doc_fields = array(
            'doc_statuts',
            'doc_recepisse',
            'doc_jo',
            'doc_pv_ag',
            'doc_cer',
            'doc_attestation_cer'
        );
        $available = array();
        foreach ( $doc_fields as $field ) {
            if ( self::has_verified_column( $columns, $clubs_table, $field ) ) {
                $available[] = $field;
            }
        }
        return $available;
    }

    /**
     * Find the safest season column to use for optional filtering.
     */
    private static function get_season_column( $columns, $clubs_table ) {
        foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $column ) {
            if ( self::has_verified_column( $columns, $clubs_table, $column ) ) {
                return $column;
            }
        }
        return '';
    }

    /**
     * Build a correlated licence count expression only if the licence table supports it.
     */
    private static function get_licence_count_expression( $clubs_table ) {
        global $wpdb;
        $settings       = UFSC_SQL::get_settings();
        $licences_table = isset( $settings['table_licences'] ) ? $settings['table_licences'] : '';
        if ( function_exists( 'ufsc_sanitize_table_name' ) ) {
            $licences_table = ufsc_sanitize_table_name( $licences_table );
            $clubs_table = ufsc_sanitize_table_name( $clubs_table );
        }
        if ( '' === $licences_table ) {
            return '';
        }
        if ( function_exists( 'ufsc_table_exists' ) && ! ufsc_table_exists( $licences_table ) ) {
            return '';
        }
        $licence_columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $licences_table ) : array();
        if ( ! self::has_verified_column( $licence_columns, $licences_table, 'club_id' ) || ! self::has_verified_column( array(), $clubs_table, 'id' ) ) {
            return '';
        }

        $parts = array( "l.club_id = `{$clubs_table}`.id" );
        if ( self::has_verified_column( $licence_columns, $licences_table, 'statut' ) ) {
            $parts[] = $wpdb->prepare( 'l.statut = %s', 'valide' );
        }
        if ( self::has_verified_column( $licence_columns, $licences_table, 'deleted_at' ) ) {
            $parts[] = "(l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";
        }

        return "(SELECT COUNT(*) FROM `{$licences_table}` l WHERE " . implode( ' AND ', $parts ) . ')';
    }

    /**
     * Build ORDER BY clause
     */
    private static function build_order_clause( $sorting, $columns, $clubs_table ) {
        $orderby_map = array(
            'nom' => 'nom',
            'date_creation' => 'date_creation',
            'region' => 'region'
        );

        $requested = isset( $orderby_map[ $sorting['orderby'] ] ) ? $orderby_map[ $sorting['orderby'] ] : 'date_creation';
        if ( ! self::has_column( $columns, $clubs_table, $requested ) ) {
            $requested = self::has_column( $columns, $clubs_table, 'id' ) ? 'id' : 'date_creation';
        }
        if ( ! self::has_column( $columns, $clubs_table, $requested ) ) {
            $requested = '1';
        }

        $orderby = isset( $orderby_map[ $requested ] ) ? $orderby_map[ $requested ] : $requested;
        $order = strtoupper( $sorting['order'] );

        return "ORDER BY {$orderby} {$order}";
    }

    /**
     * Render action buttons
     */
    private static function render_action_buttons() {
        if ( ! ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            return;
        }
        echo '<p class="ufsc-primary-actions">';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs&action=new' ) ) . '" class="button button-primary">';
        echo esc_html__( 'Ajouter un club', 'ufsc-clubs' );
        echo '</a> ';
        $current_url = self::get_current_request_url();
        echo '<a href="' . esc_url( add_query_arg( 'export', 'csv', $current_url ) ) . '" class="button">';
        echo esc_html__( 'Exporter CSV', 'ufsc-clubs' );
        echo '</a>';

        echo '<a href="' . esc_url( add_query_arg( 'export', 'xlsx', $current_url ) ) . '" class="button">';
        echo esc_html__( 'Exporter XLSX', 'ufsc-clubs' );
        echo '</a>';

        echo '<a href="' . esc_url( admin_url('admin.php?page=ufsc-import') ) . '" class="button">';
        echo esc_html__( 'Importer', 'ufsc-clubs' );
        echo '</a>';

        echo '</p>';
    }

    /**
     * Render statistics cards.
     */
    private static function render_statistics_cards( $columns, $clubs_table, $licence_counts ) {
        global $wpdb;
        $where_scope = '';
        if ( self::has_verified_column( $columns, $clubs_table, 'region' ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region' );
            if ( $scope_condition ) {
                $where_scope = 'WHERE ' . $scope_condition;
            }
        }

        $stats = array(
            'total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$where_scope}" ),
            'active' => 0,
            'pending' => 0,
            'documents_complete' => null,
            'documents_incomplete' => null,
            'licences' => self::sum_licence_counts_for_scope( $columns, $clubs_table, $licence_counts, $where_scope ),
            'missing_affiliation' => null,
        );

        if ( self::has_verified_column( $columns, $clubs_table, 'statut' ) ) {
            $scope_prefix = '' === $where_scope ? 'WHERE' : $where_scope . ' AND';
            $active_statuses = array( 'actif', 'active', 'valide', 'validated' );
            $pending_statuses = array( 'en_attente', 'pending', 'a_regler', 'creating', 'en_cours_de_creation' );
            $active_placeholders = implode( ',', array_fill( 0, count( $active_statuses ), '%s' ) );
            $pending_placeholders = implode( ',', array_fill( 0, count( $pending_statuses ), '%s' ) );
            $stats['active'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$clubs_table}` {$scope_prefix} statut IN ({$active_placeholders})", $active_statuses ) );
            $stats['pending'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$clubs_table}` {$scope_prefix} statut IN ({$pending_placeholders})", $pending_statuses ) );
        }

        $doc_fields = self::get_available_document_fields( $columns, $clubs_table );
        if ( ! empty( $doc_fields ) ) {
            $doc_conditions = array();
            foreach ( $doc_fields as $field ) {
                $doc_conditions[] = "(`{$field}` IS NOT NULL AND `{$field}` != '')";
            }
            $scope_prefix = '' === $where_scope ? 'WHERE' : $where_scope . ' AND';
            $complete_condition = '(' . implode( ' AND ', $doc_conditions ) . ')';
            $stats['documents_complete'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$scope_prefix} {$complete_condition}" );
            $stats['documents_incomplete'] = max( 0, $stats['total'] - $stats['documents_complete'] );
        }

        if ( self::has_verified_column( $columns, $clubs_table, 'num_affiliation' ) ) {
            $scope_prefix = '' === $where_scope ? 'WHERE' : $where_scope . ' AND';
            $stats['missing_affiliation'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$scope_prefix} (num_affiliation IS NULL OR num_affiliation = '')" );
        }

        $cards = array(
            array( 'label' => __( 'Clubs enregistrés', 'ufsc-clubs' ), 'value' => $stats['total'], 'tone' => 'primary' ),
            array( 'label' => __( 'Clubs actifs', 'ufsc-clubs' ), 'value' => $stats['active'], 'tone' => 'success' ),
            array( 'label' => __( 'Clubs en attente', 'ufsc-clubs' ), 'value' => $stats['pending'], 'tone' => 'warning' ),
        );
        if ( null !== $stats['documents_complete'] ) {
            $cards[] = array( 'label' => __( 'Documents complets', 'ufsc-clubs' ), 'value' => $stats['documents_complete'], 'tone' => 'success' );
            $cards[] = array( 'label' => __( 'Documents incomplets', 'ufsc-clubs' ), 'value' => $stats['documents_incomplete'], 'tone' => 'danger' );
        }
        $cards[] = array( 'label' => __( 'Licences associées', 'ufsc-clubs' ), 'value' => $stats['licences'], 'tone' => 'primary' );
        if ( null !== $stats['missing_affiliation'] ) {
            $cards[] = array( 'label' => __( 'Sans n° affiliation', 'ufsc-clubs' ), 'value' => $stats['missing_affiliation'], 'tone' => 'danger' );
        }

        echo '<div class="ufsc-stats-grid">';
        foreach ( $cards as $card ) {
            echo '<div class="ufsc-stat-card ufsc-stat-card--' . esc_attr( $card['tone'] ) . '">';
            echo '<span>' . esc_html( $card['label'] ) . '</span>';
            echo '<strong>' . esc_html( number_format_i18n( (int) $card['value'] ) ) . '</strong>';
            echo '</div>';
        }
        echo '</div>';
    }


    /**
     * Sum licence counts only for clubs in the same regional perimeter as the cards/table.
     */
    private static function sum_licence_counts_for_scope( $columns, $clubs_table, $licence_counts, $where_scope ) {
        if ( '' === $where_scope ) {
            return array_sum( array_map( 'intval', (array) $licence_counts ) );
        }

        global $wpdb;
        $id_column = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id';
        if ( ! self::has_verified_column( $columns, $clubs_table, $id_column ) ) {
            $id_column = 'id';
        }

        $club_ids = $wpdb->get_col( "SELECT `{$id_column}` FROM `{$clubs_table}` {$where_scope}" );
        $total    = 0;
        foreach ( (array) $club_ids as $club_id ) {
            $club_id = (int) $club_id;
            $total  += isset( $licence_counts[ $club_id ] ) ? (int) $licence_counts[ $club_id ] : 0;
        }

        return $total;
    }

    /**
     * Render filters and search in a single panel.
     */
    private static function render_filters( $filters, $columns, $clubs_table, $search = '' ) {
        echo '<div class="ufsc-filters-panel">';
        echo '<div class="ufsc-panel-heading"><h2>' . esc_html__( 'Filtres de recherche', 'ufsc-clubs' ) . '</h2><p>' . esc_html__( 'Affinez la liste sans modifier les exports, la pagination ou les actions existantes.', 'ufsc-clubs' ) . '</p></div>';
        echo '<form method="get" class="ufsc-filters-form">';
        echo '<input type="hidden" name="page" value="ufsc-sql-clubs">';

        echo '<div class="ufsc-filters-grid">';
        echo '<label><span>' . esc_html__( 'Région', 'ufsc-clubs' ) . '</span>';
        self::render_region_filter( $filters['region'], $columns, $clubs_table );
        echo '</label>';

        echo '<label><span>' . esc_html__( 'Statut', 'ufsc-clubs' ) . '</span>';
        self::render_status_filter( $filters['statut'] );
        echo '</label>';

        echo '<label><span>' . esc_html__( 'Créé du', 'ufsc-clubs' ) . '</span><input type="date" name="created_from" value="' . esc_attr( $filters['created_from'] ) . '"></label>';
        echo '<label><span>' . esc_html__( 'Créé au', 'ufsc-clubs' ) . '</span><input type="date" name="created_to" value="' . esc_attr( $filters['created_to'] ) . '"></label>';

        echo '<label><span>' . esc_html__( 'Recherche', 'ufsc-clubs' ) . '</span><input type="search" name="q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Nom ou email...', 'ufsc-clubs' ) . '"></label>';

        echo '<label><span>' . esc_html__( 'Documents', 'ufsc-clubs' ) . '</span><select name="doc_status">';
        echo '<option value="">' . esc_html__( 'Tous', 'ufsc-clubs' ) . '</option>';
        echo '<option value="complete"' . selected( $filters['doc_status'], 'complete', false ) . '>' . esc_html__( 'Complets', 'ufsc-clubs' ) . '</option>';
        echo '<option value="incomplete"' . selected( $filters['doc_status'], 'incomplete', false ) . '>' . esc_html__( 'Incomplets', 'ufsc-clubs' ) . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__( 'N° affiliation', 'ufsc-clubs' ) . '</span><select name="affiliation_status">';
        echo '<option value="">' . esc_html__( 'Tous', 'ufsc-clubs' ) . '</option>';
        echo '<option value="assigned"' . selected( $filters['affiliation_status'], 'assigned', false ) . '>' . esc_html__( 'Attribué', 'ufsc-clubs' ) . '</option>';
        echo '<option value="missing"' . selected( $filters['affiliation_status'], 'missing', false ) . '>' . esc_html__( 'Non attribué', 'ufsc-clubs' ) . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</span><select name="licence_range">';
        echo '<option value="">' . esc_html__( 'Toutes', 'ufsc-clubs' ) . '</option>';
        echo '<option value="zero"' . selected( $filters['licence_range'], 'zero', false ) . '>' . esc_html__( '0 licence', 'ufsc-clubs' ) . '</option>';
        echo '<option value="one_to_nine"' . selected( $filters['licence_range'], 'one_to_nine', false ) . '>' . esc_html__( '1 à 9 licences', 'ufsc-clubs' ) . '</option>';
        echo '<option value="ten_plus"' . selected( $filters['licence_range'], 'ten_plus', false ) . '>' . esc_html__( '10 licences et +', 'ufsc-clubs' ) . '</option>';
        echo '</select></label>';

        $season_column = self::get_season_column( $columns, $clubs_table );
        if ( '' !== $season_column ) {
            echo '<label><span>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</span><input type="text" name="season" value="' . esc_attr( $filters['season'] ) . '" placeholder="' . esc_attr( self::get_admin_season_label() ) . '"></label>';
        }
        echo '</div>';

        echo '<div class="ufsc-filters-actions">';
        submit_button( __( 'Filtrer', 'ufsc-clubs' ), 'primary', null, false );
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs' ) ) . '" class="button ufsc-reset-button">' . esc_html__( 'Réinitialiser', 'ufsc-clubs' ) . '</a>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Backward-compatible no-op search renderer: search now lives in the filter panel.
     */
    private static function render_search( $search ) {
        unset( $search );
    }

    /**
     * Render quick GET filters above the table.
     */
    private static function render_quick_filters( $filters ) {
        unset( $filters );
        $base = admin_url( 'admin.php?page=ufsc-sql-clubs' );
        $links = array(
            array( 'label' => __( 'Tous les clubs', 'ufsc-clubs' ), 'args' => array() ),
            array( 'label' => __( 'Actifs', 'ufsc-clubs' ), 'args' => array( 'statut' => 'actif' ) ),
            array( 'label' => __( 'En attente', 'ufsc-clubs' ), 'args' => array( 'statut' => 'en_attente' ) ),
            array( 'label' => __( 'Documents incomplets', 'ufsc-clubs' ), 'args' => array( 'doc_status' => 'incomplete' ) ),
            array( 'label' => __( 'Sans n° affiliation', 'ufsc-clubs' ), 'args' => array( 'affiliation_status' => 'missing' ) ),
            array( 'label' => __( 'Moins de 10 licences', 'ufsc-clubs' ), 'args' => array( 'licence_range' => 'under_ten' ) ),
            array( 'label' => __( 'Clubs sans licence', 'ufsc-clubs' ), 'args' => array( 'licence_range' => 'zero' ) ),
        );

        echo '<div class="ufsc-quick-filters" aria-label="' . esc_attr__( 'Filtres rapides', 'ufsc-clubs' ) . '">';
        foreach ( $links as $link ) {
            $url = empty( $link['args'] ) ? $base : add_query_arg( $link['args'], $base );
            echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( $link['label'] ) . '</a>';
        }
        echo '</div>';
    }

    //add action groppe
    // private static function bulck_action_grop_by_club(){
    //     echo '<form method="post" id="bulk-actions-form">';
    //     // Bulk actions
    //     echo '<div class="ufsc-bulk-actions">';

    //     wp_nonce_field('ufsc_bulk_actions');
    //     echo '<select name="bulk_action" id="bulk-action-selector">';
    //     echo '<option value="">'.esc_html__('Actions groupées', 'ufsc-clubs').'</option>';
    //     echo '<option value="delete">'.esc_html__('Supprimer', 'ufsc-clubs').'</option>';
    //     echo '</select>';
    //     echo ' <button type="submit" class="button">'.esc_html__('Appliquer', 'ufsc-clubs').'</button>';

    //     echo '</div>';

    // }
    

    /**
     * Render results info
     */
    private static function render_results_info( $total_items, $pagination ) {
        $start = $total_items > 0 ? ( ( $pagination['paged'] - 1 ) * $pagination['per_page'] ) + 1 : 0;
        $end = min( $pagination['paged'] * $pagination['per_page'], $total_items );

        echo '<div class="ufsc-results-info">';
        echo sprintf(
            esc_html__( 'Affichage de %d à %d sur %d clubs', 'ufsc-clubs' ),
            $start,
            $end,
            $total_items
        );

        // Per page selector
        echo ' | ';
        echo '<select onchange="window.location.href=this.value">';
        foreach ( array( 20, 50, 100 ) as $per_page ) {
            $url = add_query_arg( 'per_page', $per_page, self::get_current_request_url() );
            echo '<option value="' . esc_url( $url ) . '"' . selected( $pagination['per_page'], $per_page, false ) . '>';
            echo sprintf( esc_html__( '%d par page', 'ufsc-clubs' ), $per_page );
            echo '</option>';
        }
        echo '</select>';
        echo '</div>';
    }

    /**
     * Render main clubs table
     */
    private static function render_clubs_table( $clubs, $sorting, $licence_counts ) {
        // Affichage des notices
        if ( isset( $_GET['processed'] ) ) {
            $processed = absint( wp_unslash( $_GET['processed'] ) );
            if ( 1 === $processed ) {
                echo UFSC_CL_Utils::show_success( sprintf( __( '%d élément(s) traité(s)', 'ufsc-clubs' ), $processed ) );
            } elseif ( 0 === $processed ) {
                echo UFSC_CL_Utils::show_error( __( 'Impossible de supprimer les clubs - présence probable de licences liées.', 'ufsc-clubs' ) );
            }
        }

        $can_manage_clubs = ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );

        echo '<form method="post" id="bulk-actions-form" class="ufsc-clubs-table-form">';
        echo '<input type="hidden" name="page" value="ufsc-sql-clubs" />';
        if ( $can_manage_clubs ) {
            // Bulk actions are write operations and are hidden for read-only users.
            echo '<div class="ufsc-bulk-actions">';

            wp_nonce_field('ufsc_bulk_clubs_actions');
            echo '<select name="bulk_action" id="bulk-action-selector">';
            echo '<option value="">'.esc_html__('Actions groupées', 'ufsc-clubs').'</option>';
            echo '<option value="delete">'.esc_html__('Supprimer', 'ufsc-clubs').'</option>';
            echo '<option value="actif">'.esc_html__('Actif', 'ufsc-clubs').'</option>';
            echo '<option value="en_attente">'.esc_html__('En attente', 'ufsc-clubs').'</option>';
            echo '<option value="creating">'.esc_html__('En cours de création', 'ufsc-clubs').'</option>';
            echo '<option value="export_selection" disabled="disabled">'.esc_html__('Exporter la sélection (bientôt)', 'ufsc-clubs').'</option>';
            echo '<option value="remind_documents" disabled="disabled">'.esc_html__('Relance documents (bientôt)', 'ufsc-clubs').'</option>';
            echo '<option value="remind_affiliation" disabled="disabled">'.esc_html__('Relance affiliation (bientôt)', 'ufsc-clubs').'</option>';
            echo '</select>';
            echo ' <button type="submit" class="button">'.esc_html__('Appliquer', 'ufsc-clubs').'</button>';
            echo '</div>';
        }

        //table
        echo '<table class="wp-list-table widefat fixed striped ufsc-clubs-table">';
        echo '<thead>';
        echo '<tr>';
        if ( $can_manage_clubs ) {
            echo '<td class="check-column"><input type="checkbox" id="select-all-club" /></td>';
        }
        echo '<th>ID</th>';
        echo '<th>' . self::get_sortable_header( 'nom', __( 'Nom du club', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . self::get_sortable_header( 'region', __( 'Région', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . esc_html__( 'N° Affiliation', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Statut', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Documents', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . self::get_sortable_header( 'date_creation', __( 'Créé le', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'ufsc-clubs' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';



        if ( $clubs ) {
            foreach ( $clubs as $club ) {
                self::render_club_row( $club, $licence_counts, $can_manage_clubs );
            }
        } else {
            echo '<tr><td colspan="' . ( $can_manage_clubs ? '10' : '9' ) . '">' . esc_html__( 'Aucun club trouvé.', 'ufsc-clubs' ) . '</td></tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</form>';

    }

    /**
     * Render individual club row
     */
    private static function render_club_row( $club, $licence_counts, $can_manage_clubs = null ) {
    if ( null === $can_manage_clubs ) {
        $can_manage_clubs = ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }
    echo '<tr>';

    // Checkbox (write-only bulk actions)
    if ( $can_manage_clubs ) {
        echo '<th class="check-column"><input type="checkbox" name="club_ids[]" value="' . (int) ( $club->id ?? 0 ) . '" /></th>';
    }

    // ID
    echo '<td>' . (int) ( $club->id ?? 0 ) . '</td>';

    // Nom + Email
    $club_name = isset( $club->nom ) ? $club->nom : '';
    $club_email = isset( $club->email ) ? $club->email : '';
    echo '<td class="ufsc-club-name-cell"><strong>' . esc_html( $club_name ) . '</strong>';
    if ( ! empty( $club_email ) ) {
        echo '<br><small>' . esc_html( $club_email ) . '</small>';
    }
    echo self::render_alerts( $club, $licence_counts );
    echo '</td>';

    // Région
    echo '<td>' . esc_html( isset( $club->region ) ? $club->region : '' ) . '</td>';

    // Numéro d’affiliation
    echo '<td>';
    echo self::render_affiliation_number( isset( $club->num_affiliation ) ? $club->num_affiliation : '' );
    echo '</td>';

    // Statut
    $status_value = isset( $club->statut ) ? $club->statut : '';
    echo '<td>' . self::render_status_badge( $status_value ) . '</td>';

    // Licences validées
    $club_id = (int) ( $club->id ?? 0 );
    $licence_count = isset( $licence_counts[ $club_id ] ) ? (int) $licence_counts[ $club_id ] : 0;
    $licence_url = add_query_arg(
        array(
            'page' => 'ufsc-sql-licences',
            'filter_club' => $club_id,
            'filter_status' => 'valide'
        ),
        admin_url( 'admin.php' )
    );
    $licence_label = sprintf( _n( '%d licence', '%d licences', $licence_count, 'ufsc-clubs' ), $licence_count );
    echo '<td><a class="ufsc-licence-link" href="' . esc_url( $licence_url ) . '">' . esc_html( $licence_label ) . '</a></td>';

    // Documents
    echo '<td>' . self::render_documents_badge( $club ) . '</td>';

    // Date de création
    $date_creation = isset( $club->date_creation ) ? $club->date_creation : '';
    echo '<td>' . ( $date_creation ? esc_html( mysql2date( 'd/m/Y', $date_creation ) ) : '<em>' . esc_html__( 'Non défini', 'ufsc-clubs' ) . '</em>' ) . '</td>';

    // Actions
    echo '<td class="ufsc-row-actions"><div class="ufsc-actions-grid">';
    $club_id = (int) ( $club->id ?? 0 );
    $view_url = admin_url( 'admin.php?page=ufsc-sql-clubs&action=view&id=' . $club_id );
    $edit_url = admin_url( 'admin.php?page=ufsc-sql-clubs&action=edit&id=' . $club_id );
    $delete_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=ufsc_sql_delete_club&id=' . $club_id ),
        'ufsc_sql_delete_club'
    );
    $documents_url = add_query_arg( array( 'page' => 'ufsc-sql-clubs', 'action' => 'edit', 'id' => $club_id, 'tab' => 'documents' ), admin_url( 'admin.php' ) );
    echo '<a href="' . esc_url( $view_url ) . '" class="button button-small">' . esc_html__( 'Consulter', 'ufsc-clubs' ) . '</a> ';
    echo '<a href="' . esc_url( $licence_url ) . '" class="button button-small">' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</a> ';
    if ( $can_manage_clubs ) {
        echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Modifier', 'ufsc-clubs' ) . '</a> ';
        echo '<a href="' . esc_url( $documents_url ) . '" class="button button-small">' . esc_html__( 'Documents', 'ufsc-clubs' ) . '</a> ';
        echo '<button type="button" class="button button-small ufsc-button-disabled" disabled="disabled" aria-disabled="true" title="' . esc_attr__( 'Relance à brancher sur une action email sécurisée existante.', 'ufsc-clubs' ) . '">' . esc_html__( 'Relancer', 'ufsc-clubs' ) . '</button> ';
        echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Êtes-vous sûr de vouloir supprimer ce club ?', 'ufsc-clubs' ) ) . '\')">' . esc_html__( 'Supprimer', 'ufsc-clubs' ) . '</a>';
    }
    echo '</div></td>';

    echo '</tr>';
}


    /**
     * Render pagination
     */
    private static function render_pagination( $current_page, $total_pages ) {
        if ( $total_pages <= 1 ) {
            return;
        }

        echo '<div class="tablenav bottom">';
        echo '<div class="tablenav-pages">';

        $base_url = remove_query_arg( 'paged', self::get_current_request_url() );

        // Previous page
        if ( $current_page > 1 ) {
            $prev_url = add_query_arg( 'paged', $current_page - 1, $base_url );
            echo '<a href="' . esc_url( $prev_url ) . '" class="button">&laquo; ' . esc_html__( 'Précédent', 'ufsc-clubs' ) . '</a> ';
        }

        // Page numbers
        $start = max( 1, $current_page - 2 );
        $end = min( $total_pages, $current_page + 2 );

        for ( $i = $start; $i <= $end; $i++ ) {
            if ( $i == $current_page ) {
                echo '<strong>' . $i . '</strong> ';
            } else {
                $page_url = add_query_arg( 'paged', $i, $base_url );
                echo '<a href="' . esc_url( $page_url ) . '">' . $i . '</a> ';
            }
        }

        // Next page
        if ( $current_page < $total_pages ) {
            $next_url = add_query_arg( 'paged', $current_page + 1, $base_url );
            echo '<a href="' . esc_url( $next_url ) . '" class="button">' . esc_html__( 'Suivant', 'ufsc-clubs' ) . ' &raquo;</a>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Helper methods for rendering filters
     */
    private static function render_region_filter( $selected, $columns, $clubs_table ) {
        global $wpdb;
        if ( ! self::has_column( $columns, $clubs_table, 'region' ) ) {
            echo '<select name="region" disabled="disabled">';
            echo '<option value="">' . esc_html__( '— Régions indisponibles —', 'ufsc-clubs' ) . '</option>';
            echo '</select>';
            return;
        }

        $scope_slug  = UFSC_Scope::get_user_scope_region();
        $scope_label = $scope_slug ? UFSC_Scope::get_region_label( $scope_slug ) : '';
        if ( $scope_label ) {
            $regions = array( $scope_label );
        } elseif ( function_exists( 'ufsc_user_has_all_regions_access' ) && ! ufsc_user_has_all_regions_access() ) {
            $regions = function_exists( 'ufsc_current_user_allowed_regions' ) ? ufsc_current_user_allowed_regions() : array();
        } else {
            $regions = $wpdb->get_col( "SELECT DISTINCT region FROM `{$clubs_table}` WHERE region IS NOT NULL AND region != '' ORDER BY region" );
        }

        echo '<select name="region">';
        if ( ! $scope_label && ( ! function_exists( 'ufsc_user_has_all_regions_access' ) || ufsc_user_has_all_regions_access() ) ) {
            echo '<option value="">' . esc_html__( '— Toutes les régions —', 'ufsc-clubs' ) . '</option>';
        }
        foreach ( $regions as $region ) {
            echo '<option value="' . esc_attr( $region ) . '"' . selected( $selected, $region, false ) . '>';
            echo esc_html( $region );
            echo '</option>';
        }
        echo '</select>';
    }

    private static function render_status_filter( $selected ) {
        $statuses = UFSC_SQL::statuses();

        echo '<select name="statut">';
        echo '<option value="">' . esc_html__( '— Tous les statuts —', 'ufsc-clubs' ) . '</option>';
        foreach ( $statuses as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>';
            echo esc_html( $label );
            echo '</option>';
        }
        echo '</select>';
    }

    private static function render_date_filters( $from, $to ) {
        echo '<label>' . esc_html__( 'Créé du', 'ufsc-clubs' ) . '</label>';
        echo '<input type="date" name="created_from" value="' . esc_attr( $from ) . '">';
        echo '<label>' . esc_html__( 'au', 'ufsc-clubs' ) . '</label>';
        echo '<input type="date" name="created_to" value="' . esc_attr( $to ) . '">';
    }

    private static function render_quota_filters( $min, $max ) {
        echo '<label>' . esc_html__( 'Quota min', 'ufsc-clubs' ) . '</label>';
        echo '<input type="number" name="quota_min" value="' . esc_attr( $min ) . '" min="0" max="999" style="width: 80px;">';
        echo '<label>' . esc_html__( 'Quota max', 'ufsc-clubs' ) . '</label>';
        echo '<input type="number" name="quota_max" value="' . esc_attr( $max ) . '" min="0" max="999" style="width: 80px;">';
    }

    /**
     * Helper methods
     */
    private static function get_sortable_header( $column, $title, $sorting ) {
        $order = ( $sorting['orderby'] === $column && $sorting['order'] === 'asc' ) ? 'desc' : 'asc';
        $url = add_query_arg( array( 'orderby' => $column, 'order' => $order ), self::get_current_request_url() );

        $arrow = '';
        if ( $sorting['orderby'] === $column ) {
            $arrow = $sorting['order'] === 'asc' ? ' ↑' : ' ↓';
        }

        return '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . $arrow . '</a>';
    }

    private static function render_status_badge( $status ) {
        $raw        = is_scalar( $status ) ? (string) $status : '';
        $normalized = sanitize_key( strtolower( remove_accents( $raw ) ) );
        $label      = '' !== $raw ? $raw : __( 'Inconnu', 'ufsc-clubs' );
        $class      = 'ufsc-badge ufsc-badge--neutral';

        if ( in_array( $normalized, array( 'actif', 'active', 'valide', 'validated' ), true ) ) {
            $label = __( 'Actif', 'ufsc-clubs' );
            $class = 'ufsc-badge ufsc-badge--success';
        } elseif ( in_array( $normalized, array( 'en_attente', 'pending', 'a_regler', 'creating', 'en_cours_de_creation' ), true ) ) {
            $label = __( 'En attente', 'ufsc-clubs' );
            $class = 'ufsc-badge ufsc-badge--warning';
        } elseif ( in_array( $normalized, array( 'suspendu', 'suspended', 'refuse', 'rejected', 'desactive', 'inactive' ), true ) ) {
            $label = __( 'Suspendu / refusé', 'ufsc-clubs' );
            $class = 'ufsc-badge ufsc-badge--danger';
        }

        return '<span class="' . esc_attr( $class ) . '" data-status="' . esc_attr( $normalized ) . '">' . esc_html( $label ) . '</span>';
    }

    private static function render_documents_badge( $club ) {
        $doc_fields = array(
            'doc_statuts',
            'doc_recepisse',
            'doc_jo',
            'doc_pv_ag',
            'doc_cer',
            'doc_attestation_cer'
        );

        $complete_count = 0;
        $total_count = count( $doc_fields );

        foreach ( $doc_fields as $field ) {
            if ( isset( $club->$field ) && ! empty( $club->$field ) ) {
                $complete_count++;
            }
        }

        if ( $complete_count === $total_count ) {
            return '<span class="ufsc-badge ufsc-badge--success" title="' . esc_attr__( 'Documents complets', 'ufsc-clubs' ) . '">' .
                   esc_html__( 'Complet', 'ufsc-clubs' ) . '</span>';
        } else {
            return '<span class="ufsc-badge ufsc-badge--warning" title="' . esc_attr( sprintf( __( '%d/%d documents', 'ufsc-clubs' ), $complete_count, $total_count ) ) . '">' .
                   esc_html__( 'Incomplet', 'ufsc-clubs' ) . '</span>';
        }
    }


    private static function is_documents_complete( $club ) {
        $doc_fields = array(
            'doc_statuts',
            'doc_recepisse',
            'doc_jo',
            'doc_pv_ag',
            'doc_cer',
            'doc_attestation_cer'
        );
        foreach ( $doc_fields as $field ) {
            if ( ! isset( $club->$field ) || empty( $club->$field ) ) {
                return false;
            }
        }
        return true;
    }

    private static function render_affiliation_number( $number ) {
        $number = is_scalar( $number ) ? trim( (string) $number ) : '';
        if ( '' === $number ) {
            return '<span class="ufsc-badge ufsc-badge--muted">' . esc_html__( 'Non attribué', 'ufsc-clubs' ) . '</span>';
        }
        return '<span class="ufsc-affiliation-number">' . esc_html( $number ) . '</span>';
    }

    private static function render_alerts( $club, $licence_counts ) {
        $club_id       = (int) ( $club->id ?? 0 );
        $licence_count = isset( $licence_counts[ $club_id ] ) ? (int) $licence_counts[ $club_id ] : 0;
        $alerts        = array();

        if ( ! self::is_documents_complete( $club ) ) {
            $alerts[] = __( 'Documents incomplets', 'ufsc-clubs' );
        }
        if ( empty( $club->num_affiliation ) ) {
            $alerts[] = __( 'N° affiliation manquant', 'ufsc-clubs' );
        }
        if ( 0 === $licence_count ) {
            $alerts[] = __( 'Club sans licence', 'ufsc-clubs' );
        } elseif ( $licence_count < 10 ) {
            $alerts[] = __( 'Moins de 10 licences', 'ufsc-clubs' );
        }

        if ( empty( $alerts ) ) {
            return '';
        }

        $html = '<div class="ufsc-alert-tags">';
        foreach ( $alerts as $alert ) {
            $html .= '<span>' . esc_html( $alert ) . '</span>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Check if a column exists.
     */
    private static function has_column( $columns, $table, $column ) {
        if ( is_array( $columns ) && ! empty( $columns ) ) {
            return in_array( $column, $columns, true );
        }

        if ( function_exists( 'ufsc_table_has_column' ) ) {
            return ufsc_table_has_column( $table, $column );
        }

        if ( function_exists( 'ufsc_table_columns' ) ) {
            $fetched = ufsc_table_columns( $table );
            return is_array( $fetched ) && in_array( $column, $fetched, true );
        }

        return true;
    }

    private static function is_valid_date( $date ) {
        $d = DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }

    public static function handle_bulk_actions() {
        $page = self::get_request_value( $_REQUEST, 'page', 'key' );
        if ( 'ufsc-sql-clubs' !== $page ) {
            return;
        }
        if ( ! ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            return;
        }

        $nonce = self::get_request_value( $_POST, '_wpnonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ufsc_bulk_clubs_actions' ) ) {
            return;
        }

        $action    = self::get_request_value( $_POST, 'bulk_action', 'key' );
        if ( '' === $action ) {
            return;
        }

        if (!isset($_POST['club_ids']) || empty($_POST['club_ids'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Aucun élément sélectionné', 'ufsc-clubs') . '</p></div>';
            });
            return; 
        }
        $settings  = UFSC_SQL::get_settings();
        $table     = $settings['table_clubs'];
        $raw_ids   = isset( $_POST['club_ids'] ) ? (array) wp_unslash( $_POST['club_ids'] ) : array();
        $item_ids  = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );
        if ( empty( $item_ids ) ) {
            return;
        }
        switch ($action) {
            case 'actif':
                self::bulk_actif_items($item_ids, $table);
                break;
            case 'en_attente':
                self::bulk_pending_items($item_ids, $table);
                break;
            case 'creating':
                self::bulk_creating_items($item_ids, $table);
                break;
            case 'delete':
                $item_ids = self::bulk_delete_items($item_ids, $settings);
                break;
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return;
        }
        $redirect_to = isset( $_POST['_wp_http_referer'] ) ? wp_validate_redirect( wp_unslash( $_POST['_wp_http_referer'] ), admin_url( 'admin.php?page=ufsc-sql-clubs' ) ) : admin_url( 'admin.php?page=ufsc-sql-clubs' );
        wp_safe_redirect( add_query_arg( 'processed', count( $item_ids ), $redirect_to ) );
        exit;
    }

    private static function bulk_delete_items($item_ids, $settings) {
        global $wpdb;
        $deleteds = [];
        foreach ($item_ids as $item_id) {
            UFSC_Scope::assert_club_in_scope( $item_id );
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT count(*) as nb FROM `{$settings['table_licences']}` WHERE club_id = %d", $item_id ) );
            if($row->nb <= 0){
                $deleteds[] = $item_id;
                $wpdb->delete(
                    $settings['table_clubs'],
                    array('id' => $item_id),
                    array('%d')
                );
            }
            
        }
        return $deleteds;
        
    }

    private static function bulk_actif_items($item_ids, $table) {
        global $wpdb;

        foreach ($item_ids as $item_id) {
            UFSC_Scope::assert_club_in_scope( $item_id );
            $wpdb->update(
                $table,
                array('statut' => 'actif'),
                array('id' => $item_id),
                array('%s'),
                array('%d')
            );
        }

        add_action('admin_notices', function() use ($item_ids) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(_n('%d élément actif.', '%d éléments actif.', count($item_ids)), count($item_ids));
            echo '</p></div>';
        });
    }

    private static function bulk_pending_items($item_ids, $table) {
        global $wpdb;

        foreach ($item_ids as $item_id) {
            UFSC_Scope::assert_club_in_scope( $item_id );
            $result = $wpdb->update(
                $table,
                array('statut' => 'en_attente'),
                array('id' => $item_id),
                array('%s'),
                array('%d')
            );
        }

        add_action('admin_notices', function() use ($item_ids) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(_n('%d élément En attente.', '%d éléments En attente.', count($item_ids)), count($item_ids));
            echo '</p></div>';
        });
    }

    private static function bulk_creating_items($item_ids, $table) {
        global $wpdb;

        foreach ($item_ids as $item_id) {
            UFSC_Scope::assert_club_in_scope( $item_id );
            $result = $wpdb->update(
                $table,
                array('statut' => 'en_cours_de_creation'),
                array('id' => $item_id),
                array('%s'),
                array('%d')
            );
        }

        add_action('admin_notices', function() use ($item_ids) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(_n('%d élément en cours de creation.', '%d éléments en cours de creation.', count($item_ids)), count($item_ids));
            echo '</p></div>';
        });
    }


}
