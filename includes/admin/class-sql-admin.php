<?php

if (! defined('ABSPATH')) {
    exit;
}

if ( class_exists( 'UFSC_SQL_Admin', false ) ) {
    return;
}

class UFSC_SQL_Admin
{
    /**
     * Get managed club document keys.
     *
     * @return array<string,string>
     */
    private static function get_club_documents_map()
    {
        return [
            'doc_statuts'         => __('Statuts', 'ufsc-clubs'),
            'doc_recepisse'       => __('Récépissé', 'ufsc-clubs'),
            'doc_jo'              => __('Journal Officiel', 'ufsc-clubs'),
            'doc_pv_ag'           => __('PV AG', 'ufsc-clubs'),
            'doc_cer'             => __('CER', 'ufsc-clubs'),
            'doc_attestation_cer' => __('Attestation CER', 'ufsc-clubs'),
        ];
    }

    /**
     * Status options for club documents.
     *
     * @return array<string,string>
     */
    private static function get_club_document_status_options()
    {
        return [
            'pending'  => __('En attente', 'ufsc-clubs'),
            'approved' => __('Approuvé', 'ufsc-clubs'),
            'rejected' => __('Rejeté', 'ufsc-clubs'),
        ];
    }

    /**
     * Enqueue assets only on targeted club edit screen.
     *
     * @return void
     */
    public static function enqueue_admin_assets()
    {
        $page   = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

        if ('ufsc-sql-clubs' !== $page || 'edit' !== $action) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'ufsc-admin-club-documents',
            UFSC_CL_URL . 'assets/js/ufsc-admin-club-documents.js',
            ['jquery'],
            UFSC_CL_VERSION,
            true
        );

        wp_localize_script('ufsc-admin-club-documents', 'ufscClubDocsL10n', [
            'chooseFile'  => __('Choisir un fichier', 'ufsc-clubs'),
            'useFile'     => __('Utiliser ce fichier', 'ufsc-clubs'),
            'addFile'     => __('Ajouter', 'ufsc-clubs'),
        ]);
    }

    private static function get_doc_option_key($club_id, $doc_key)
    {
        $slug = str_replace('doc_', '', (string) $doc_key);
        return 'ufsc_club_doc_' . $slug . '_' . (int) $club_id;
    }

    private static function ufsc_docs_detect_storage_mode($club_id)
    {
        foreach (array_keys(self::get_club_documents_map()) as $doc_key) {
            if ((int) get_post_meta($club_id, $doc_key, true) > 0) {
                return 'sql';
            }
        }

        foreach (array_keys(self::get_club_documents_map()) as $doc_key) {
            if ((int) get_option(self::get_doc_option_key($club_id, $doc_key)) > 0) {
                return 'option';
            }
        }

        return 'sql';
    }

    private static function ufsc_docs_get_file($club_id, $doc_key)
    {
        $sql_id    = (int) get_post_meta($club_id, $doc_key, true);
        $option_id = (int) get_option(self::get_doc_option_key($club_id, $doc_key));

        $storage = 'sql';
        $id      = 0;
        if ($sql_id > 0) {
            $id      = $sql_id;
            $storage = 'sql';
        } elseif ($option_id > 0) {
            $id      = $option_id;
            $storage = 'option';
        } else {
            $storage = self::ufsc_docs_detect_storage_mode($club_id);
        }

        $url      = $id ? wp_get_attachment_url($id) : '';
        $filename = '';
        $filesize = '';
        $date     = '';

        if ($id && $url) {
            $filename = get_the_title($id);
            if ('' === $filename) {
                $filename = basename((string) get_attached_file($id));
            }
            $file_path = get_attached_file($id);
            $filesize  = ($file_path && file_exists($file_path)) ? size_format((int) filesize($file_path)) : '';
            $date      = get_the_date('d/m/Y H:i', $id);
        }

        return [
            'attachment_id' => $id,
            'url'           => $url,
            'filename'      => $filename,
            'filesize'      => $filesize,
            'date'          => $date,
            'storage'       => $storage,
        ];
    }

    private static function ufsc_docs_set_file($club_id, $doc_key, $attachment_id)
    {
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return false;
        }

        $file    = self::ufsc_docs_get_file($club_id, $doc_key);
        $storage = $file['storage'];

        if ('option' === $storage) {
            update_option(self::get_doc_option_key($club_id, $doc_key), $attachment_id);
            return true;
        }

        update_post_meta($club_id, $doc_key, $attachment_id);
        return true;
    }

    private static function ufsc_docs_remove_file($club_id, $doc_key)
    {
        $file    = self::ufsc_docs_get_file($club_id, $doc_key);
        $storage = $file['storage'];

        if ('option' === $storage) {
            delete_option(self::get_doc_option_key($club_id, $doc_key));
            return;
        }

        delete_post_meta($club_id, $doc_key);
    }

    private static function ufsc_docs_get_allowed_status_values($club_id, $doc_key)
    {
        $allowed = array_keys(self::get_club_document_status_options());
        $legacy  = ['en_attente', 'approuve', 'approuvé', 'rejete', 'rejeté', 'valide', 'refuse', 'refusé'];

        $current = sanitize_key((string) get_option('ufsc_club_' . $doc_key . '_status_' . $club_id, ''));
        if ($current !== '') {
            $legacy[] = $current;
        }

        return array_values(array_unique(array_merge($allowed, $legacy)));
    }

    private static function ufsc_docs_get_status($club_id, $doc_key)
    {
        $status = sanitize_key((string) get_option('ufsc_club_' . $doc_key . '_status_' . $club_id, 'pending'));
        return $status !== '' ? $status : 'pending';
    }

    private static function ufsc_docs_set_status($club_id, $doc_key, $status)
    {
        $status  = sanitize_key((string) $status);
        $allowed = self::ufsc_docs_get_allowed_status_values($club_id, $doc_key);

        if (! in_array($status, $allowed, true)) {
            return new WP_Error('invalid_status', __('Statut de document invalide.', 'ufsc-clubs'));
        }

        update_option('ufsc_club_' . $doc_key . '_status_' . $club_id, $status);
        return true;
    }

    /**
     * Determine if running under WP-CLI.
     *
     * @return bool
     */
    private static function is_cli()
    {
        return defined('WP_CLI') && WP_CLI;
    }

    /**
     * Redirect safely unless running in CLI.
     *
     * @param string $url Redirect URL.
     * @param bool   $safe Whether to use wp_safe_redirect.
     * @return void
     */
    private static function maybe_redirect($url, $safe = true)
    {
        if (self::is_cli()) {
            return;
        }

        if ($safe) {
            wp_safe_redirect($url);
        } else {
            wp_redirect($url);
        }
        exit;
    }

    /**
     * Filter data array to columns that exist in the table.
     *
     * @param string $table Table name.
     * @param array  $data  Data to filter.
     * @return array
     */
    private static function filter_data_by_columns($table, $data)
    {
        $columns = self::get_table_columns($table);
        if (empty($columns) || empty($data) || ! is_array($data)) {
            return array();
        }

        return array_intersect_key($data, array_flip($columns));
    }
    /**
     * Generate status badge with colored dot
     */
    private static function get_status_badge($status, $label = '')
    {
        $normalized = function_exists( 'ufsc_normalize_license_status' )
            ? ufsc_normalize_license_status( $status )
            : (string) $status;

        if (empty($label)) {
            $label = function_exists( 'ufsc_license_status_label' )
                ? ufsc_license_status_label( $normalized )
                : ( UFSC_SQL::statuses()[ $normalized ] ?? $normalized );
        }

        $status_map = [
            'brouillon'  => 'draft',
            'en_attente' => 'pending',
            'valide'     => 'valid',
            'a_regler'   => 'pending',
            'refuse'     => 'rejected',
            'desactive'  => 'inactive',
            'expire'     => 'inactive',
            'non_payee'  => 'pending',
        ];

        $css_class = isset($status_map[$normalized]) ? $status_map[$normalized] : 'inactive';

        return '<span class="ufsc-status-badge ufsc-status-' . esc_attr($css_class) . '">' .
        '<span class="ufsc-status-dot"></span>' .
        esc_html($label) .
            '</span>';
    }

    /**
     * Determine if a licence row is paid.
     *
     * @param object $row Licence row.
     * @return bool
     */
    private static function is_licence_paid( $row ) {
        $payment_status = isset( $row->payment_status ) ? strtolower( (string) $row->payment_status ) : '';
        if ( in_array( $payment_status, array( 'paid', 'completed', 'processing' ), true ) ) {
            return true;
        }

        foreach ( array( 'paid', 'payee', 'is_paid' ) as $paid_key ) {
            if ( ! isset( $row->{$paid_key} ) ) {
                continue;
            }

            $paid_value = (string) $row->{$paid_key};
            if ( in_array( $paid_value, array( '1', 'yes', 'oui', 'true' ), true ) ) {
                return true;
            }
        }

        return false;
    }

    private static function get_licence_delete_block_reason( $row ) {
        $status_raw  = isset( $row->statut ) ? $row->statut : ( $row->status ?? '' );
        $status_norm = function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $status_raw ) : strtolower( trim( (string) $status_raw ) );
        if ( 'valide' === $status_norm ) {
            return __( 'Licence validée — suppression impossible.', 'ufsc-clubs' );
        }

        if ( self::is_licence_paid( $row ) || ! empty( $row->order_id ) || ! empty( $row->order_item_id ) ) {
            return __( 'Licence liée à une commande — suppression impossible.', 'ufsc-clubs' );
        }

        return '';
    }

    /**
     * Retrieve table columns with a DESCRIBE fallback.
     *
     * @param string $table Table name.
     * @return array
     */
    private static function get_table_columns($table)
    {
        global $wpdb;
        if (function_exists('ufsc_table_columns')) {
            $columns = ufsc_table_columns($table);
            return is_array($columns) ? $columns : [];
        }

        $columns = $wpdb->get_col("DESCRIBE {$table}", 0);
        return is_array($columns) ? $columns : [];
    }

    /**
     * Build a safe SELECT expression for a column (or empty fallback).
     *
     * @param string $alias Table alias.
     * @param string $column Column name.
     * @param array  $columns Available columns list.
     * @return string
     */
    private static function build_select_column($alias, $column, $columns)
    {
        if (in_array($column, $columns, true)) {
            return "{$alias}.{$column}";
        }

        return "'' AS {$column}";
    }

    /**
     * Build a safe export SELECT expression with column presence checks.
     *
     * @param string $key Export key.
     * @param string $expression SQL expression.
     * @param array  $licence_columns Licence columns.
     * @param array  $club_columns Club columns.
     * @param bool   $has_club_id Whether licence table has club_id.
     * @return string
     */
    private static function build_export_select_field($key, $expression, $licence_columns, $club_columns, $has_club_id)
    {
        if ($key === 'club_nom') {
            if ($has_club_id && in_array('nom', $club_columns, true)) {
                return $expression;
            }
            return "'' AS {$key}";
        }

        if (in_array($key, $licence_columns, true)) {
            return $expression;
        }

        return "'' AS {$key}";
    }

    /* ---------------- Menus cachés pour accès direct ---------------- */
    public static function register_hidden_pages()
    {
        // Enregistrer les pages cachées pour les actions directes (mentionnées dans les specs)
        add_submenu_page(null, __('Clubs (SQL)', 'ufsc-clubs'), __('Clubs (SQL)', 'ufsc-clubs'), UFSC_Capabilities::CAP_MANAGE_READ, 'ufsc-sql-clubs', [__CLASS__, 'render_clubs']);
        add_submenu_page(null, __('Licences (SQL)', 'ufsc-clubs'), __('Licences (SQL)', 'ufsc-clubs'), UFSC_Capabilities::CAP_MANAGE_READ, 'ufsc-sql-licences', [__CLASS__, 'render_licences']);
        // Alias pour compatibilité avec la spec (licenses vs licences)
        add_submenu_page(null, __('Licences (SQL)', 'ufsc-clubs'), __('Licences (SQL)', 'ufsc-clubs'), UFSC_Capabilities::CAP_MANAGE_READ, 'ufsc-sql-licenses', [__CLASS__, 'render_licences']);
    }

    /* ---------------- Menus complets (obsolète - remplacé par menu unifié) ---------------- */
    public static function register_menus()
    {
        add_menu_page(__('UFSC – Données (SQL)', 'ufsc-clubs'), __('UFSC – Données (SQL)', 'ufsc-clubs'), UFSC_Capabilities::CAP_MANAGE_READ, 'ufsc-sql', [__CLASS__, 'render_dashboard'], 'dashicons-database', 59);
        add_submenu_page('ufsc-sql', __('Clubs (SQL)', 'ufsc-clubs'), __('Clubs (SQL)', 'ufsc-clubs'), UFSC_Capabilities::CAP_MANAGE_READ, 'ufsc-sql-clubs', [__CLASS__, 'render_clubs']);
        add_submenu_page('ufsc-sql', __('Licences (SQL)', 'ufsc-clubs'), __('Licences (SQL)', 'ufsc-clubs'), UFSC_Capabilities::CAP_MANAGE_READ, 'ufsc-sql-licences', [__CLASS__, 'render_licences']);
        add_submenu_page('ufsc-sql', __('Réglages (SQL)', 'ufsc-clubs'), __('Réglages (SQL)', 'ufsc-clubs'), UFSC_Capabilities::CAP_MANAGE_READ, 'ufsc-sql-settings', [__CLASS__, 'render_settings']);
    }

    /* ---------------- Dashboard ---------------- */
    public static function render_dashboard()
    {
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $c = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$s['table_clubs']}`");
        $l = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$s['table_licences']}`");
        echo '<div class="wrap"><h1>UFSC – SQL</h1>';
        echo UFSC_CL_Utils::kpi_cards([
            ['label' => __('Clubs (SQL)', 'ufsc-clubs'), 'value' => $c],
            ['label' => __('Licences (SQL)', 'ufsc-clubs'), 'value' => $l],
        ]);
        echo '</div>';
    }

    /* ---------------- Réglages ---------------- */
    public static function render_settings()
    {
        if (isset($_POST['ufsc_sql_save']) && check_admin_referer('ufsc_sql_settings')) {
            $in                     = UFSC_CL_Utils::sanitize_text_arr($_POST);
            $opts                   = UFSC_SQL::get_settings();
            if ( function_exists( 'ufsc_sanitize_table_name' ) ) {
                $opts['table_clubs']    = ufsc_sanitize_table_name( $in['table_clubs'] );
                $opts['table_licences'] = ufsc_sanitize_table_name( $in['table_licences'] );
            } else {
                $opts['table_clubs']    = $in['table_clubs'];
                $opts['table_licences'] = $in['table_licences'];
            }
            update_option('ufsc_sql_settings', $opts);
            echo '<div class="updated"><p>' . esc_html__('Réglages enregistrés.', 'ufsc-clubs') . '</p></div>';
        }
        $s = UFSC_SQL::get_settings();
        echo '<div class="wrap"><h1>' . esc_html__('Réglages (SQL)', 'ufsc-clubs') . '</h1><form method="post">';
        wp_nonce_field('ufsc_sql_settings');
        echo '<table class="form-table">';
        echo '<tr><th>Table Clubs</th><td><input type="text" name="table_clubs" value="' . esc_attr($s['table_clubs']) . '" /></td></tr>';
        echo '<tr><th>Table Licences</th><td><input type="text" name="table_licences" value="' . esc_attr($s['table_licences']) . '" /></td></tr>';
        echo '</table>';
        echo '<p class="description">' . esc_html__('Booléens: 1 = Oui / 0 = Non.', 'ufsc-clubs') . '</p>';
        submit_button('Enregistrer', 'primary', 'ufsc_sql_save');
        echo '</form></div>';
    }

    /* ---------------- Liste Clubs ---------------- */
    public static function render_clubs()
    {
        if ( ! UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        // Handle save first
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ufsc_sql_save_club') {
            self::handle_save_club();
        }
        // Check if we should show edit/new form
        if (isset($_GET['action']) && $_GET['action'] === 'edit') {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
            }
            $id = (int) $_GET['id'];
            self::render_club_form($id);
            return;
        } elseif (isset($_GET['action']) && $_GET['action'] === 'view') {
            $id = (int) $_GET['id'];
            self::render_club_form($id, true); // true = readonly mode
            return;
        } elseif (isset($_GET['action']) && $_GET['action'] === 'new') {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
            }
            self::render_club_form(0);
            return;
        } elseif (isset($_GET['export'])) {
            self::handle_clubs_export();
            return;
        }

        // Use enhanced list table
        UFSC_Clubs_List_Table::render();
    }

    /* ---------------- Handle clubs export ---------------- */

    private static function handle_clubs_export()
    {
        if ( ! UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        global $wpdb;
        $s      = UFSC_SQL::get_settings();
        $t      = $s['table_clubs'];
        $pk     = $s['pk_club'];
        $format = sanitize_text_field(wp_unslash($_GET['export']));
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $columns = self::get_table_columns($t);
        $where_conditions = array();
        if ( $status && in_array( 'statut', $columns, true ) ) {
            $where_conditions[] = $wpdb->prepare( "statut=%s", $status );
        }
        if ( in_array( 'region', $columns, true ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region' );
            if ( $scope_condition ) {
                $where_conditions[] = $scope_condition;
            }
        }
        $where  = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';
        $select_columns = array(
            $pk,
            'nom',
            'region',
            'statut',
            'adresse',
            'code_postal',
            'complement_adresse',
            'ville',
            'email',
            'telephone',
            'siren',
            'ape',
            'ccn',
            'ancv',
            'num_declaration',
            'date_declaration',
            'president_prenom',
            'president_nom',
            'president_tel',
            'president_email',
            'secretaire_prenom',
            'secretaire_nom',
            'secretaire_tel',
            'secretaire_email',
            'tresorier_prenom',
            'tresorier_nom',
            'tresorier_tel',
            'tresorier_email',
            'entraineur_prenom',
            'entraineur_nom',
            'entraineur_tel',
            'entraineur_email',
        );
        $select_parts = array();
        foreach ($select_columns as $column) {
            if (in_array($column, $columns, true)) {
                $select_parts[] = "`{$column}`";
            } else {
                $select_parts[] = "'' AS `{$column}`";
            }
        }
        $order_column = in_array($pk, $columns, true) ? "`{$pk}`" : (in_array('id', $columns, true) ? '`id`' : '1');
        $rows   = $wpdb->get_results("SELECT " . implode(', ', $select_parts) . " FROM `$t` $where ORDER BY {$order_column} DESC");

        switch ($format) {
            case 'csv':
                $result = self::export_clubs_csv($rows);
                break;
            case 'xlsx':
                $result = self::export_clubs_xlsx($rows);
                break;
            default:
                wp_die(__('Format d\'export non supporté.', 'ufsc-clubs'));
        }
    }

    private static function export_clubs_csv($rows)
    {
        // Vérifier si des headers ont déjà été envoyés
        if (headers_sent()) {
            wp_die(__('Headers already sent, cannot export.', 'ufsc-clubs'));
        }

        // Nettoyer les buffers de sortie
        while (ob_get_level()) {
            ob_end_clean();
        }

        $date     = date('Y-m-d_H-i-s');
        $filename = sanitize_file_name("list_clubs_{$date}.csv");

        // Headers pour le téléchargement
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output BOM pour UTF-8
        echo "\xEF\xBB\xBF";

        // Headers CSV
        $headers = [
            __('ID', 'ufsc-clubs'),
            __('Nom', 'ufsc-clubs'),
            __('Region', 'ufsc-clubs'),
            __('Statut', 'ufsc-clubs'),
            __('Adresse', 'ufsc-clubs'),
            __('Code Postal', 'ufsc-clubs'),
            __('Complement adresse', 'ufsc-clubs'),
            __('Ville', 'ufsc-clubs'),
            __('Email', 'ufsc-clubs'),
            __('Telephone', 'ufsc-clubs'),
            __('Numéro SIREN', 'ufsc-clubs'),
            __('Code APE / NAF', 'ufsc-clubs'),
            __('Convention collective (CCNS, Animation, autres)', 'ufsc-clubs'),
            __('Numéro ANCV', 'ufsc-clubs'),
            __('N° de déclaration en préfecture', 'ufsc-clubs'),
            __('Date de déclaration en préfecture', 'ufsc-clubs'),
            __('Prénom du président', 'ufsc-clubs'),
            __('Nom du président', 'ufsc-clubs'),
            __('Téléphone du président', 'ufsc-clubs'),
            __('Email du président', 'ufsc-clubs'),
            __('Prénom du secrétaire', 'ufsc-clubs'),
            __('Nom du secrétaire', 'ufsc-clubs'),
            __('Téléphone du secrétaire', 'ufsc-clubs'),
            __('Email du secrétaire', 'ufsc-clubs'),
            __('Prénom du trésorier', 'ufsc-clubs'),
            __('Nom du trésorier', 'ufsc-clubs'),
            __('Téléphone du trésorier', 'ufsc-clubs'),
            __('Email du trésorier', 'ufsc-clubs'),
            __('Prénom de l\'entraineur', 'ufsc-clubs'),
            __('Nom de l\'entraineur', 'ufsc-clubs'),
            __('Téléphone de l\'entraineur', 'ufsc-clubs'),
            __('Email de l\'entraineur', 'ufsc-clubs'),
        ];

        // Écrire les headers
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers, ';');

        // Data rows
        foreach ($rows as $row) {
            $line = [
                $row->id ?? '',
                $row->nom ?? '',
                $row->region ?? '',
                $row->statut ?? '',
                $row->adresse ?? '',
                $row->code_postal ?? '',
                $row->complement_adresse ?? '',
                $row->ville ?? '',
                $row->email ?? '',
                $row->telephone ?? '',
                $row->siren ?? '',
                $row->ape ?? '',
                $row->ccn ?? '',
                $row->ancv ?? '',
                $row->num_declaration ?? '',
                $row->date_declaration ?? '',
                $row->president_prenom ?? '',
                $row->president_nom ?? '',
                $row->president_tel ?? '',
                $row->president_email ?? '',
                $row->secretaire_prenom ?? '',
                $row->secretaire_nom ?? '',
                $row->secretaire_tel ?? '',
                $row->secretaire_email ?? '',
                $row->tresorier_prenom ?? '',
                $row->tresorier_nom ?? '',
                $row->tresorier_tel ?? '',
                $row->tresorier_email ?? '',
                $row->entraineur_prenom ?? '',
                $row->entraineur_nom ?? '',
                $row->entraineur_tel ?? '',
                $row->entraineur_email ?? '',

            ];
            fputcsv($output, $line, ';');
        }

        fclose($output);
        exit();
    }

    /**
     * Export clubs to Excel (XLSX)
     *
     * @param array $rows
     * @return array Result with success status and file info
     */
    public static function export_clubs_xlsx($rows)
    {
        // Vérifier si des headers ont déjà été envoyés
        if (headers_sent()) {
            wp_die(__('Headers already sent, cannot export.', 'ufsc-clubs'));
        }

        // Check if PhpSpreadsheet is available
        if (! class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Fall back to CSV
            static::export_clubs_csv($rows);
            exit(); // CSV export already exits
        }

        if (empty($rows)) {
            wp_die(__('Aucune clubs à exporter.', 'ufsc-clubs'));
        }

        try {
            // Nettoyer les buffers de sortie
            while (ob_get_level()) {
                ob_end_clean();
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle(__('Clubs', 'ufsc-clubs'));

            // Headers
            $headers = [
                'A1'  => __('ID', 'ufsc-clubs'),
                'B1'  => __('Nom', 'ufsc-clubs'),
                'C1'  => __('Region', 'ufsc-clubs'),
                'D1'  => __('Statut', 'ufsc-clubs'),
                'E1'  => __('Adresse', 'ufsc-clubs'),
                'F1'  => __('Code postal', 'ufsc-clubs'),
                'G1'  => __('Complément adresse', 'ufsc-clubs'),
                'H1'  => __('Ville', 'ufsc-clubs'),
                'I1'  => __('Email', 'ufsc-clubs'),
                'J1'  => __('Téléphone', 'ufsc-clubs'),
                'K1'  => __('Siren', 'ufsc-clubs'),
                'L1'  => __('APE', 'ufsc-clubs'),
                'M1'  => __('CCN', 'ufsc-clubs'),
                'N1'  => __('ANCV', 'ufsc-clubs'),
                'O1'  => __('Num déclaration', 'ufsc-clubs'),
                'P1'  => __('Date déclaration', 'ufsc-clubs'),

                'Q1'  => __('Président prénom', 'ufsc-clubs'),
                'R1'  => __('Président nom', 'ufsc-clubs'),
                'S1'  => __('Président tel', 'ufsc-clubs'),
                'T1'  => __('Président email', 'ufsc-clubs'),

                'U1'  => __('Secrétaire prénom', 'ufsc-clubs'),
                'V1'  => __('Secrétaire nom', 'ufsc-clubs'),
                'W1'  => __('Secrétaire tel', 'ufsc-clubs'),
                'X1'  => __('Secrétaire email', 'ufsc-clubs'),

                'Y1'  => __('Trésorier prénom', 'ufsc-clubs'),
                'Z1'  => __('Trésorier nom', 'ufsc-clubs'),
                'AA1' => __('Trésorier tel', 'ufsc-clubs'),
                'AB1' => __('Trésorier email', 'ufsc-clubs'),

                'AC1' => __('Entraîneur prénom', 'ufsc-clubs'),
                'AD1' => __('Entraîneur nom', 'ufsc-clubs'),
                'AE1' => __('Entraîneur tel', 'ufsc-clubs'),
                'AF1' => __('Entraîneur email', 'ufsc-clubs'),
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }

            // Style headers
            $headerStyle = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF'],
                ],
            ];
            $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

            // Data
            $row = 2;
            foreach ($rows as $line) {
                $sheet->setCellValue("A{$row}", $line->id ?? '');
                $sheet->setCellValue("B{$row}", $line->nom ?? '');
                $sheet->setCellValue("C{$row}", $line->region ?? '');
                $sheet->setCellValue("D{$row}", $line->statut ?? '');
                $sheet->setCellValue("E{$row}", $line->adresse ?? '');
                $sheet->setCellValue("F{$row}", $line->code_postal ?? '');
                $sheet->setCellValue("G{$row}", $line->complement_adresse ?? '');
                $sheet->setCellValue("H{$row}", $line->ville ?? '');
                $sheet->setCellValue("I{$row}", $line->email ?? '');
                $sheet->setCellValue("J{$row}", $line->telephone ?? '');
                $sheet->setCellValue("K{$row}", $line->siren ?? '');
                $sheet->setCellValue("L{$row}", $line->ape ?? '');
                $sheet->setCellValue("M{$row}", $line->ccn ?? '');
                $sheet->setCellValue("N{$row}", $line->ancv ?? '');
                $sheet->setCellValue("O{$row}", $line->num_declaration ?? '');
                $sheet->setCellValue("P{$row}", $line->date_declaration ?? '');

                $sheet->setCellValue("Q{$row}", $line->president_prenom ?? '');
                $sheet->setCellValue("R{$row}", $line->president_nom ?? '');
                $sheet->setCellValue("S{$row}", $line->president_tel ?? '');
                $sheet->setCellValue("T{$row}", $line->president_email ?? '');

                $sheet->setCellValue("U{$row}", $line->secretaire_prenom ?? '');
                $sheet->setCellValue("V{$row}", $line->secretaire_nom ?? '');
                $sheet->setCellValue("W{$row}", $line->secretaire_tel ?? '');
                $sheet->setCellValue("X{$row}", $line->secretaire_email ?? '');

                $sheet->setCellValue("Y{$row}", $line->tresorier_prenom ?? '');
                $sheet->setCellValue("Z{$row}", $line->tresorier_nom ?? '');
                $sheet->setCellValue("AA{$row}", $line->tresorier_tel ?? '');
                $sheet->setCellValue("AB{$row}", $line->tresorier_email ?? '');

                $sheet->setCellValue("AC{$row}", $line->entraineur_prenom ?? '');
                $sheet->setCellValue("AD{$row}", $line->entraineur_nom ?? '');
                $sheet->setCellValue("AE{$row}", $line->entraineur_tel ?? '');
                $sheet->setCellValue("AF{$row}", $line->entraineur_email ?? '');

                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'E') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Generate filename
            $date     = date('Y-m-d_H-i-s');
            $filename = sanitize_file_name("list_clubs_{$date}.xlsx");

            // Headers pour le téléchargement
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');

            // Output directly to browser
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            // Sauvegarder dans un tampon de sortie puis l'envoyer
            $writer->save('php://output');

            exit();

        } catch (\Exception $e) {
            // Nettoyer les buffers en cas d'erreur
            while (ob_get_level()) {
                ob_end_clean();
            }

            wp_die(sprintf(__('Erreur lors de la création du fichier Excel: %s', 'ufsc-clubs'), $e->getMessage()));
        }
    }

    private static function csv_clubs($rows)
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="clubs_sql.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id', 'nom', 'region', 'statut']);
        if ($rows) {
            foreach ($rows as $r) {
                fputcsv($out, [$r->id, $r->nom, $r->region, $r->statut]);
            }
        }
        fclose($out);
    }

    private static function render_club_form($id, $readonly = false)
    {
        global $wpdb;
        $s      = UFSC_SQL::get_settings();
        $t      = $s['table_clubs'];
        $pk     = $s['pk_club'];
        $fields = UFSC_SQL::get_club_fields();
        $row    = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM `$t` WHERE `$pk`=%d", $id)) : null;
        if ( $row && property_exists( $row, 'region' ) ) {
            UFSC_Scope::assert_in_scope( $row->region );
        }

        if ($readonly) {
            echo '<h2>' . ($id ? esc_html__('Consulter le club', 'ufsc-clubs') : esc_html__('Nouveau club', 'ufsc-clubs')) . '</h2>';
        } else {
            echo '<h2>' . ($id ? esc_html__('Éditer le club', 'ufsc-clubs') : esc_html__('Nouveau club', 'ufsc-clubs')) . '</h2>';
        }

        // Affichage des messages
        if (isset($_GET['updated']) && $_GET['updated'] == '1') {
            echo UFSC_CL_Utils::show_success(__('Club enregistré avec succès', 'ufsc-clubs'));
        }
        if (isset($_GET['error'])) {
            echo UFSC_CL_Utils::show_error(sanitize_text_field($_GET['error']));
        }

        $docs_error = get_transient('ufsc_docs_errors_' . get_current_user_id());
        if ($docs_error) {
            delete_transient('ufsc_docs_errors_' . get_current_user_id());
            echo UFSC_CL_Utils::show_error(sanitize_text_field($docs_error));
        }

        if (! $readonly) {
            echo '<form method="post" enctype="multipart/form-data">';
            wp_nonce_field('ufsc_sql_save_club');
            echo '<input type="hidden" name="action" value="ufsc_sql_save_club" />';
            echo '<input type="hidden" name="id" value="' . (int) $id . '" />';
            echo '<input type="hidden" name="page" value="ufsc-sql-clubs"/>';
        }

        echo '<div class="ufsc-grid">';
        foreach ($fields as $k => $conf) {
            if ( 'quota_licences' === $k ) {
                continue;
            }
            $val = $row ? (isset($row->$k) ? $row->$k : '') : '';
            self::render_field_club($k, $conf, $val, $readonly);
        }
        echo '</div>';

        // Add Documents section for non-readonly mode
        // if (!$readonly) {
        //     echo '<h3>' . esc_html__('Documents du club zh', 'ufsc-clubs') . '</h3>';
        //     echo '<div class="ufsc-documents-section">';

        //     // Logo du club
        //     echo '<div class="ufsc-document-upload">';
        //     echo '<h4>' . esc_html__('Logo du club', 'ufsc-clubs') . '</h4>';
        //     $logo_id = get_option('ufsc_club_logo_' . $id);
        //     if ($logo_id) {
        //         $logo_url = wp_get_attachment_url($logo_id);
        //         $logo_title = get_the_title($logo_id);
        //         echo '<div class="ufsc-current-file">';
        //         echo '<p><strong>' . esc_html__('Fichier actuel:', 'ufsc-clubs') . '</strong></p>';
        //         echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($logo_title) . '" style="max-width: 200px; max-height: 150px;">';
        //         echo '<p>';
        //         echo '<a href="' . esc_url($logo_url) . '" target="_blank" rel="noopener">' . esc_html__('Voir', 'ufsc-clubs') . '</a> | ';
        //         echo '<a href="' . esc_url($logo_url) . '" download>' . esc_html__('Télécharger', 'ufsc-clubs') . '</a>';
        //         echo '</p>';
        //         echo '</div>';
        //     }
        //     echo '<input type="file" name="club_logo_upload" accept="image/*">';
        //     echo '<p class="description">' . esc_html__('Formats acceptés: JPG, PNG, SVG. Taille max: 2MB', 'ufsc-clubs') . '</p>';
        //     echo '</div>';

        //     // Attestation UFSC
        //     echo '<div class="ufsc-document-upload">';
        //     echo '<h4>' . esc_html__('Attestation UFSC', 'ufsc-clubs') . '</h4>';
        //     $attestation_id = get_option('ufsc_club_doc_attestation_affiliation_' . $id);
        //     if ($attestation_id) {
        //         $attestation_url = wp_get_attachment_url($attestation_id);
        //         $attestation_title = get_the_title($attestation_id);
        //         echo '<div class="ufsc-current-file">';
        //         echo '<p><strong>' . esc_html__('Fichier actuel:', 'ufsc-clubs') . '</strong> ' . esc_html($attestation_title) . '</p>';
        //         echo '<p>';
        //         echo '<a href="' . esc_url($attestation_url) . '" target="_blank" rel="noopener">' . esc_html__('Voir', 'ufsc-clubs') . '</a> | ';
        //         echo '<a href="' . esc_url($attestation_url) . '" download>' . esc_html__('Télécharger', 'ufsc-clubs') . '</a>';
        //         echo '</p>';
        //         echo '</div>';
        //     }
        //     echo '<input type="file" name="attestation_ufsc_upload" accept=".pdf,.jpg,.jpeg,.png">';
        //     echo '<p class="description">' . esc_html__('Formats acceptés: PDF, JPG, PNG. Taille max: 5MB', 'ufsc-clubs') . '</p>';
        //     echo '</div>';

        //     echo '</div>';
        // }

        // Add Documents panel for club editing
        if ($id && ! $readonly) {
            self::render_club_documents_panel($id);

        }

        if (! $readonly) {
            echo '<p><button class="button button-primary">' . esc_html__('Enregistrer', 'ufsc-clubs') . '</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=ufsc-sql-clubs')) . '">' . esc_html__('Annuler', 'ufsc-clubs') . '</a></p>';
            echo '</form>';
        } else {
            echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=ufsc-sql-clubs')) . '">' . esc_html__('Retour à la liste', 'ufsc-clubs') . '</a>';
            if (current_user_can('manage_options')) {
                echo ' <a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=ufsc-sql-clubs&action=edit&id=' . $id)) . '">' . esc_html__('Modifier', 'ufsc-clubs') . '</a>';
            }
            echo '</p>';
        }
    }

    private static function render_field_club($k, $conf, $val, $readonly = false)
    {
        $label         = $conf[0];
        $type          = $conf[1];
        $readonly_attr = $readonly ? 'readonly disabled' : '';
        $disabled_attr = $readonly ? 'disabled' : '';

        echo '<div class="ufsc-field"><label>' . esc_html($label) . '</label>';
        if ($type === 'textarea') {
            echo '<textarea name="' . esc_attr($k) . '" rows="3" ' . $readonly_attr . '>' . esc_textarea($val) . '</textarea>';
        } elseif ($type === 'number') {
            echo '<input type="number" step="1" name="' . esc_attr($k) . '" value="' . esc_attr($val) . '" ' . $readonly_attr . ' />';
        } elseif ($type === 'region') {
            echo '<select name="' . esc_attr($k) . '" ' . $disabled_attr . '>';
            $scope_slug  = UFSC_Scope::get_user_scope_region();
            $scope_label = $scope_slug ? UFSC_Scope::get_region_label( $scope_slug ) : '';
            $regions = $scope_label ? array( $scope_label ) : UFSC_CL_Utils::regions();
            foreach ( $regions as $r ) {
                echo '<option value="' . esc_attr($r) . '" ' . selected($val, $r, false) . '>' . esc_html($r) . '</option>';
            }
            echo '</select>';
        } elseif ($label === 'Statuts') {
            echo '<select name="' . esc_attr($k) . '" ' . $disabled_attr . '>';
            foreach (UFSC_CL_Utils::statuts() as $key => $r) {
                echo '<option value="' . esc_attr($key) . '" ' . selected($val, $key, false) . '>' . esc_html($r) . '</option>';
            }
            echo '</select>';
        } elseif ($label === 'Status') {
            echo '<select name="' . esc_attr($k) . '" ' . $disabled_attr . '>';
            foreach (UFSC_CL_Utils::status() as $key => $r) {
                echo '<option value="' . esc_attr($key) . '" ' . selected($val, $key, false) . '>' . esc_html($r) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'licence_status') {
            $st = UFSC_SQL::statuses();
            echo '<select name="' . esc_attr($k) . '" ' . $disabled_attr . '>';
            foreach ($st as $sv => $sl) {
                echo '<option value="' . esc_attr($sv) . '" ' . selected($val, $sv, false) . '>' . esc_html($sl) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" name="' . esc_attr($k) . '" value="' . esc_attr($val) . '" ' . $readonly_attr . ' />';
        }
        echo '</div>';
    }

    /**
     * Render club documents panel for logo and attestation uploads
     */
    private static function render_club_documents_panel($club_id)
    {
        wp_nonce_field('ufsc_club_docs_action', 'ufsc_club_docs_nonce');

        echo '<div class="ufsc-documents-panel" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">';
        echo '<h3>' . esc_html__('Documents du club', 'ufsc-clubs') . '</h3>';

        // Logo section
        echo '<div style="margin-bottom: 20px;">';
        echo '<h4>' . esc_html__('Logo du club', 'ufsc-clubs') . '</h4>';

        $logo_id = get_option('ufsc_club_logo_' . $club_id);
        if ($logo_id) {
            $logo_url = wp_get_attachment_url($logo_id);
            if ($logo_url) {
                echo '<div style="margin-bottom: 10px;">';
                echo '<img src="' . esc_url($logo_url) . '" style="max-width: 200px; max-height: 150px;" alt="Logo actuel">';
                echo '</div>';
                echo '<p>';
                echo '<a href="' . esc_url($logo_url) . '" target="_blank" class="button">' . esc_html__('Voir', 'ufsc-clubs') . '</a> ';
                echo '<a href="' . esc_url($logo_url) . '" download class="button">' . esc_html__('Télécharger', 'ufsc-clubs') . '</a> ';
                echo '<label><input type="checkbox" name="remove_logo" value="1"> ' . esc_html__('Supprimer le logo actuel', 'ufsc-clubs') . '</label>';
                echo '</p>';
            }
        }

        echo '<p>';
        echo '<label for="club_logo_upload">' . esc_html__('Nouveau logo (JPG, PNG, GIF, max 2MB):', 'ufsc-clubs') . '</label><br>';
        echo '<input type="file" id="club_logo_upload" name="club_logo_upload" accept=".jpg,.jpeg,.png,.gif">';
        echo '</p>';
        echo '</div>';

        // Attestation UFSC section
        echo '<div style="margin-bottom: 20px;">';
        echo '<h4>' . esc_html__('Attestation UFSC', 'ufsc-clubs') . '</h4>';

        $attestation_id = get_option('ufsc_club_doc_attestation_affiliation_' . $club_id);
        if ($attestation_id) {
            $attestation_url   = wp_get_attachment_url($attestation_id);
            $attestation_title = get_the_title($attestation_id);
            echo '<div class="ufsc-current-file">';
            echo '<p><strong>' . esc_html__('Fichier actuel:', 'ufsc-clubs') . '</strong> ' . esc_html($attestation_title) . '</p>';
            echo '<p>';
            echo '<a href="' . esc_url($attestation_url) . '" target="_blank" rel="noopener" class="button">' . esc_html__('Voir', 'ufsc-clubs') . '</a> ';
            echo '<a href="' . esc_url($attestation_url) . '" download class="button">' . esc_html__('Télécharger', 'ufsc-clubs') . '</a>';
            echo '</p>';
            echo '</div>';
        }
        echo '<input type="file" name="attestation_ufsc_upload" accept=".pdf,.jpg,.jpeg,.png">';
        echo '<p class="description">' . esc_html__('Formats acceptés: PDF, JPG, PNG. Taille max: 5MB', 'ufsc-clubs') . '</p>';
        echo '</div>';

        echo '</div>';

        // echo '<p>';
        // echo '<label for="attestation_ufsc_upload">' . esc_html__('Nouvelle attestation (PDF, JPG, PNG, max 5MB):', 'ufsc-clubs') . '</label><br>';
        // echo '<input type="file" id="attestation_ufsc_upload" name="attestation_ufsc_upload" accept=".pdf,.jpg,.jpeg,.png">';
        // echo '</p>';
        // echo '</div>';

        // Other club documents
        $documents = self::get_club_documents_map();

        $status_options = self::get_club_document_status_options();

        echo '<style>.ufsc-doc-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600}.ufsc-doc-badge.no-file{background:#f0f0f1;color:#50575e}.ufsc-doc-status{display:inline-flex;align-items:center;gap:4px;margin-right:8px;font-size:12px}.ufsc-doc-status.pending{color:#996800}.ufsc-doc-status.approved{color:#007017}.ufsc-doc-status.rejected{color:#a02222}.ufsc-doc-file-meta{font-size:12px;color:#50575e}</style>';

        echo '<table class="widefat striped" style="margin-top:10px;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Document', 'ufsc-clubs') . '</th>';
        echo '<th>' . esc_html__('Fichier', 'ufsc-clubs') . '</th>';
        echo '<th>' . esc_html__('Statut', 'ufsc-clubs') . '</th>';
        echo '<th>' . esc_html__('Actions', 'ufsc-clubs') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($documents as $doc_key => $label) {
            $file_info    = self::ufsc_docs_get_file($club_id, $doc_key);
            $doc_id       = (int) $file_info['attachment_id'];
            $doc_url      = $file_info['url'];
            $status       = self::ufsc_docs_get_status($club_id, $doc_key);
            $status_label = isset($status_options[$status]) ? $status_options[$status] : __('Statut existant', 'ufsc-clubs');
            $meta_bits    = array_filter([$file_info['filesize'], $file_info['date']]);
            $file_meta    = implode(' · ', $meta_bits);

            echo '<tr class="ufsc-doc-row" data-doc-key="' . esc_attr($doc_key) . '">';
            echo '<td><strong>' . esc_html($label) . '</strong></td>';
            echo '<td>';

            if ($doc_id && $doc_url) {
                echo '<div class="ufsc-doc-file-name" data-default-label="' . esc_attr($file_info['filename']) . '">' . esc_html($file_info['filename']) . '</div>';
                echo '<div class="ufsc-doc-file-meta" data-default-meta="' . esc_attr($file_meta) . '">' . esc_html($file_meta) . '</div>';
            } else {
                echo '<span class="ufsc-doc-badge no-file"><span class="dashicons dashicons-warning" style="font-size:14px;width:14px;height:14px"></span>' . esc_html__('Aucun fichier', 'ufsc-clubs') . '</span>';
                echo '<div class="ufsc-doc-file-name" data-default-label=""></div><div class="ufsc-doc-file-meta" data-default-meta=""></div>';
            }

            echo '<input type="hidden" class="ufsc-doc-attachment-id" name="' . esc_attr('ufsc_docs[' . $doc_key . '][attachment_id]') . '" value="' . esc_attr($doc_id) . '">';
            echo '<input type="hidden" class="ufsc-doc-remove-flag" name="' . esc_attr('ufsc_docs[' . $doc_key . '][remove]') . '" value="0">';
            echo '</td>';

            echo '<td>';
            echo '<span class="ufsc-doc-status ' . esc_attr($status) . '"><span class="dashicons dashicons-yes-alt" style="font-size:14px;width:14px;height:14px"></span>' . esc_html($status_label) . '</span>';
            echo '<select name="' . esc_attr('ufsc_docs[' . $doc_key . '][status]') . '">';
            foreach ($status_options as $value => $label_option) {
                echo '<option value="' . esc_attr($value) . '" ' . selected($status, $value, false) . '>' . esc_html($label_option) . '</option>';
            }
            echo '</select>';
            echo '</td>';

            echo '<td>';
            $disabled = $doc_url ? '' : ' disabled';
            echo '<a href="' . esc_url($doc_url ?: '#') . '" target="_blank" rel="noopener" class="button ufsc-doc-view"' . $disabled . '>' . esc_html__('Voir', 'ufsc-clubs') . '</a> ';
            echo '<a href="' . esc_url($doc_url ?: '#') . '" class="button ufsc-doc-download" download' . $disabled . '>' . esc_html__('Télécharger', 'ufsc-clubs') . '</a> ';
            $replace_label = $doc_url ? __('Remplacer', 'ufsc-clubs') : __('Ajouter', 'ufsc-clubs');
            echo '<button type="button" class="button ufsc-doc-replace" data-doc-key="' . esc_attr($doc_key) . '">' . esc_html($replace_label) . '</button> ';
            echo '<button type="button" class="button ufsc-doc-remove" data-doc-key="' . esc_attr($doc_key) . '">' . esc_html__('Supprimer', 'ufsc-clubs') . '</button>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '</div>';
    }

    public static function handle_save_club()
    {
        if (! current_user_can('read')) {
            wp_die(__('Accès refusé.', 'ufsc-clubs'));
        }

        check_admin_referer('ufsc_sql_save_club');

        $user_id = get_current_user_id();
        $id      = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        // Permission check to ensure user can manage this club
        if (! current_user_can('manage_options') && ufsc_get_user_club_id($user_id) !== $id) {
            set_transient('ufsc_error_' . $user_id, __('Permissions insuffisantes', 'ufsc-clubs'), 30);
            self::maybe_redirect(wp_get_referer());
            return; // Abort processing if user lacks rights
        }

        global $wpdb;
        $s      = UFSC_SQL::get_settings();
        $t      = $s['table_clubs'];
        $pk     = $s['pk_club'];
        $fields = UFSC_SQL::get_club_fields();

        $data = [];
        foreach ($fields as $k => $conf) {
            if (array_key_exists($k, $_POST)) {
                $data[$k] = sanitize_text_field(wp_unslash($_POST[$k]));
            }
        }
        if ( $id ) {
            UFSC_Scope::assert_club_in_scope( $id );
        } elseif ( isset( $data['region'] ) ) {
            UFSC_Scope::assert_in_scope( $data['region'] );
        }
        if (! isset($data['statut']) || $data['statut'] === '') {
            $data['statut'] = 'en_attente';
        }

        // Handle file uploads before validation
        $upload_errors = [];

        // Handle logo upload
        if (! empty($_FILES['club_logo_upload']['name'])) {
            $upload_result = self::handle_document_upload($_FILES['club_logo_upload'], ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml'], 2 * 1024 * 1024); // 2MB max
            if (is_wp_error($upload_result)) {
                $upload_errors[] = __('Logo: ', 'ufsc-clubs') . $upload_result->get_error_message();
            } else {
                // Save logo attachment ID
                update_option('ufsc_club_logo_' . ($id ?: 'new'), $upload_result);
            }
        }

        // Handle Attestation UFSC upload
        if (! empty($_FILES['attestation_ufsc_upload']['name'])) {
            $upload_result = self::handle_document_upload($_FILES['attestation_ufsc_upload'], ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'], 5 * 1024 * 1024); // 5MB max
            if (is_wp_error($upload_result)) {
                $upload_errors[] = __('Attestation UFSC: ', 'ufsc-clubs') . $upload_result->get_error_message();
            } else {
                // Save attachment ID
                update_option('ufsc_club_doc_attestation_affiliation_' . ($id ?: 'new'), $upload_result);
            }
        }

        // Check for upload errors
        if (! empty($upload_errors)) {
            $error_message = implode(', ', $upload_errors);
            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-clubs&action=' . ($id ? 'edit&id=' . $id : 'new') . '&error=' . urlencode($error_message)));
            return;
        }

        // Validation des données
        $validation_errors = UFSC_CL_Utils::validate_club_data($data, false);
        if (! empty($validation_errors)) {
            UFSC_CL_Utils::log('Erreurs de validation club: ' . implode(', ', $validation_errors), 'warning');
            $error_message = implode(', ', $validation_errors);
            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-clubs&action=' . ($id ? 'edit&id=' . $id : 'new') . '&error=' . urlencode($error_message)));
            return;
        }

        try {
            $data_db = self::filter_data_by_columns($t, $data);
            if (empty($data_db)) {
                throw new Exception('Aucune colonne valide à enregistrer.');
            }
            if ($id) {
                $result = $wpdb->update($t, $data_db, [$pk => $id]);
                if ($result === false) {
                    throw new Exception('Erreur lors de la mise à jour du club');
                }
                UFSC_CL_Utils::log('Club mis à jour: ID ' . $id, 'info');

                // Update option keys with real ID if we had uploads
                if (get_option('ufsc_club_logo_new')) {
                    $logo_id = get_option('ufsc_club_logo_new');
                    update_option('ufsc_club_logo_' . $id, $logo_id);
                    delete_option('ufsc_club_logo_new');
                }
                if (get_option('ufsc_club_doc_attestation_affiliation_new')) {
                    $doc_id = get_option('ufsc_club_doc_attestation_affiliation_new');
                    update_option('ufsc_club_doc_attestation_affiliation_' . $id, $doc_id);
                    delete_option('ufsc_club_doc_attestation_affiliation_new');
                }
            } else {
                $result = $wpdb->insert($t, $data_db);
                if ($result === false) {
                    throw new Exception('Erreur lors de la création du club');
                }
                $id = (int) $wpdb->insert_id;
                UFSC_CL_Utils::log('Nouveau club créé: ID ' . $id, 'info');

                // Update option keys with real ID
                if (get_option('ufsc_club_logo_new')) {
                    $logo_id = get_option('ufsc_club_logo_new');
                    update_option('ufsc_club_logo_' . $id, $logo_id);
                    delete_option('ufsc_club_logo_new');
                }
                if (get_option('ufsc_club_doc_attestation_affiliation_new')) {
                    $doc_id = get_option('ufsc_club_doc_attestation_affiliation_new');
                    update_option('ufsc_club_doc_attestation_affiliation_' . $id, $doc_id);
                    delete_option('ufsc_club_doc_attestation_affiliation_new');
                }
            }

            // Handle file uploads after club is saved/updated
            if ($id) {
                self::handle_club_document_uploads($id);

                $can_manage_docs = current_user_can('manage_options') || current_user_can('edit_post', $id) || (class_exists('UFSC_Capabilities') && UFSC_Capabilities::user_can(UFSC_Capabilities::CAP_MANAGE_READ));
                $has_docs_nonce  = isset($_POST['ufsc_club_docs_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ufsc_club_docs_nonce'])), 'ufsc_club_docs_action');
                $doc_errors      = [];

                if ($can_manage_docs && $has_docs_nonce) {
                    $documents   = array_keys(self::get_club_documents_map());
                    $posted_docs = isset($_POST['ufsc_docs']) && is_array($_POST['ufsc_docs']) ? wp_unslash($_POST['ufsc_docs']) : [];

                    foreach ($documents as $doc_key) {
                        $row = isset($posted_docs[$doc_key]) && is_array($posted_docs[$doc_key]) ? $posted_docs[$doc_key] : [];

                        $attachment_id = isset($row['attachment_id']) ? absint($row['attachment_id']) : 0;
                        $is_remove     = isset($row['remove']) && '1' === sanitize_text_field((string) $row['remove']);
                        $status_input  = isset($row['status']) ? sanitize_key((string) $row['status']) : '';

                        if ($is_remove) {
                            self::ufsc_docs_remove_file($id, $doc_key);
                        } elseif ($attachment_id > 0) {
                            self::ufsc_docs_set_file($id, $doc_key, $attachment_id);
                        }

                        if ($status_input !== '') {
                            $status_result = self::ufsc_docs_set_status($id, $doc_key, $status_input);
                            if (is_wp_error($status_result)) {
                                $doc_labels = self::get_club_documents_map();
                                $doc_errors[] = sprintf(
                                    __('%1$s : statut ignoré (valeur invalide).', 'ufsc-clubs'),
                                    isset($doc_labels[$doc_key]) ? $doc_labels[$doc_key] : $doc_key
                                );
                            }
                        }
                    }
                }

                if (! empty($doc_errors)) {
                    set_transient('ufsc_docs_errors_' . get_current_user_id(), implode(' ', $doc_errors), 120);
                }
            }

            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-clubs&action=edit&id=' . $id . '&updated=1'));
            return;
        } catch (Exception $e) {
            UFSC_CL_Utils::log('Erreur sauvegarde club: ' . $e->getMessage(), 'error');
            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-clubs&action=' . ($id ? 'edit&id=' . $id : 'new') . '&error=' . urlencode($e->getMessage())));
            return;
        }
    }

    /**

     * Handle document upload with validation
     *
     * @param array $file $_FILES array element
     * @param array $allowed_mime_types Allowed MIME types
     * @param int $max_size Maximum file size in bytes
     * @return int|WP_Error Attachment ID on success, WP_Error on failure
     */
    private static function handle_document_upload($file, $allowed_mime_types, $max_size)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('Erreur lors du téléchargement du fichier.', 'ufsc-clubs'));
        }

        // Check file size
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('Le fichier est trop volumineux.', 'ufsc-clubs'));
        }

        // Check MIME type
        $file_type = wp_check_filetype($file['name']);
        if (! in_array($file_type['type'], $allowed_mime_types)) {
            return new WP_Error('invalid_file_type', __('Type de fichier non autorisé.', 'ufsc-clubs'));
        }

        // Handle the upload
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error']);
        }

        // Create attachment
        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate attachment metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return $attachment_id;
    }

    /**
     * Handle club document uploads (logo and attestation)
     */
    private static function handle_club_document_uploads($club_id)
    {
        // Load required WordPress upload functions
        if (! function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (! function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Handle logo upload
        if (! empty($_FILES['club_logo_upload']['name'])) {
            $logo_mimes = [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'gif'          => 'image/gif',
                'png'          => 'image/png',
            ];

            $logo_upload = wp_handle_upload($_FILES['club_logo_upload'], [
                'test_form' => false,
                'mimes'     => $logo_mimes,
            ]);

            if (! isset($logo_upload['error'])) {
                // Create attachment
                $logo_attachment = [
                    'post_mime_type' => $logo_upload['type'],
                    'post_title'     => sanitize_file_name($_FILES['club_logo_upload']['name']),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];

                $logo_attachment_id = wp_insert_attachment($logo_attachment, $logo_upload['file']);

                if ($logo_attachment_id) {
                    // Generate metadata
                    $logo_metadata = wp_generate_attachment_metadata($logo_attachment_id, $logo_upload['file']);
                    wp_update_attachment_metadata($logo_attachment_id, $logo_metadata);

                    // Remove old logo if exists
                    $old_logo_id = get_option('ufsc_club_logo_' . $club_id);
                    if ($old_logo_id) {
                        wp_delete_attachment($old_logo_id, true);
                    }

                    // Save new logo
                    update_option('ufsc_club_logo_' . $club_id, $logo_attachment_id);
                    UFSC_CL_Utils::log('Logo uploaded for club ID ' . $club_id, 'info');
                }
            } else {
                UFSC_CL_Utils::log('Logo upload error: ' . $logo_upload['error'], 'warning');
            }
        }

        // Handle logo removal
        if (isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
            $old_logo_id = get_option('ufsc_club_logo_' . $club_id);
            if ($old_logo_id) {
                wp_delete_attachment($old_logo_id, true);
                delete_option('ufsc_club_logo_' . $club_id);
                UFSC_CL_Utils::log('Logo removed for club ID ' . $club_id, 'info');
            }
        }

        // Handle attestation UFSC upload
        if (! empty($_FILES['attestation_ufsc_upload']['name'])) {
            $doc_mimes = [
                'pdf'          => 'application/pdf',
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
            ];

            $doc_upload = wp_handle_upload($_FILES['attestation_ufsc_upload'], [
                'test_form' => false,
                'mimes'     => $doc_mimes,
            ]);

            if (! isset($doc_upload['error'])) {
                // Create attachment
                $doc_attachment = [
                    'post_mime_type' => $doc_upload['type'],
                    'post_title'     => sanitize_file_name($_FILES['attestation_ufsc_upload']['name']),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];

                $doc_attachment_id = wp_insert_attachment($doc_attachment, $doc_upload['file']);

                if ($doc_attachment_id) {
                    // Remove old attestation if exists
                    $old_doc_id = get_option('ufsc_club_doc_attestation_ufsc_' . $club_id);
                    if ($old_doc_id) {
                        wp_delete_attachment($old_doc_id, true);
                    }

                    // Save new attestation
                    update_option('ufsc_club_doc_attestation_ufsc_' . $club_id, $doc_attachment_id);
                    update_option('ufsc_attestation_' . $club_id, $doc_attachment_id);
                    delete_transient( "ufsc_documents_{$club_id}" );
                    // UFSC PATCH: Persist attestation URL when column exists.
                    if ( function_exists( 'ufsc_get_clubs_table' ) ) {
                        global $wpdb;

                        $clubs_table = ufsc_get_clubs_table();

                        $columns = self::get_table_columns( $clubs_table );
                        $has_col = in_array( 'attestation_url', $columns, true );

                        if ( $has_col ) {
                            $wpdb->update(
                                $clubs_table,
                                array( 'attestation_url' => wp_get_attachment_url( $doc_attachment_id ) ),
                                array( 'id' => (int) $club_id ),
                                array( '%s' ),
                                array( '%d' )
                            );
                        }
                    }

                    if ( function_exists( 'ufsc_flush_table_columns_cache' ) ) {
                        ufsc_flush_table_columns_cache();
                    }
                    UFSC_CL_Utils::log('Attestation UFSC uploaded for club ID ' . $club_id, 'info');
                }
            } else {
                UFSC_CL_Utils::log('Attestation upload error: ' . $doc_upload['error'], 'warning');
            }
        }

        // Handle attestation removal
        if (isset($_POST['remove_attestation_ufsc']) && $_POST['remove_attestation_ufsc'] == '1') {
            $old_doc_id = get_option('ufsc_club_doc_attestation_ufsc_' . $club_id);
            if ($old_doc_id) {
                wp_delete_attachment($old_doc_id, true);
                delete_option('ufsc_club_doc_attestation_ufsc_' . $club_id);
                delete_option('ufsc_attestation_' . $club_id);
                delete_transient( "ufsc_documents_{$club_id}" );
                if ( function_exists( 'ufsc_get_clubs_table' ) ) {
                    global $wpdb;

                    $clubs_table = ufsc_get_clubs_table();

                    $columns = self::get_table_columns( $clubs_table );
                    $has_col = in_array( 'attestation_url', $columns, true );

                    if ( $has_col ) {
                        $wpdb->update(
                            $clubs_table,
                            array( 'attestation_url' => '' ),
                            array( 'id' => (int) $club_id ),
                            array( '%s' ),
                            array( '%d' )
                        );
                    }
                }

                UFSC_CL_Utils::log('Attestation UFSC removed for club ID ' . $club_id, 'info');
            }
        }

    }

    public static function handle_delete_club()
    {
        if (! current_user_can('read')) {
            wp_die(__('Accès refusé.', 'ufsc-clubs'));
        }
        if (! current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        check_admin_referer('ufsc_sql_delete_club');

        global $wpdb;
        $s  = UFSC_SQL::get_settings();
        $t  = $s['table_clubs'];
        $pk = $s['pk_club'];
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id) {
            UFSC_Scope::assert_club_in_scope( $id );
            $result = $wpdb->delete($t, [$pk => $id]);
            if ($result !== false) {
                UFSC_CL_Utils::log('Club supprimé: ID ' . $id, 'info');
                self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-clubs&deleted=1&deleted_id=' . $id));
            } else {
                UFSC_CL_Utils::log('Erreur suppression club: ID ' . $id, 'error');
                self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-clubs&error=' . urlencode(__('Erreur lors de la suppression du club', 'ufsc-clubs'))));
            }
        } else {
            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-clubs&error=' . urlencode(__('ID de club invalide', 'ufsc-clubs'))));
        }
        return;
    }

    /* ---------------- Licences ---------------- */
    public static function render_licences()
    {
        if ( ! UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        // Handle save first
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ufsc_sql_save_licence') {
            self::handle_save_licence();
        }
        global $wpdb;
        $s              = UFSC_SQL::get_settings();
        $licences_table = $s['table_licences'];
        $clubs_table    = $s['table_clubs'];
        $licence_columns = self::get_table_columns($licences_table);
        $club_columns    = self::get_table_columns($clubs_table);
        $has_club_id     = in_array('club_id', $licence_columns, true);
        $pk             = $s['pk_licence'];

        // Handle search and filters
        $search        = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $filter_region = isset($_GET['filter_region']) ? sanitize_text_field($_GET['filter_region']) : '';
        $filter_club   = isset($_GET['filter_club']) ? intval($_GET['filter_club']) : 0;
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        $scope_slug    = UFSC_Scope::get_user_scope_region();
        $scope_label   = $scope_slug ? UFSC_Scope::get_region_label( $scope_slug ) : '';
        if ( $scope_slug ) {
            $enforced_region = $scope_label ?: $scope_slug;
            if ( $filter_region !== $enforced_region ) {
                $filter_region = $enforced_region;
            }
        }

        // Pagination
        $per_page = 20;
        $page     = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset   = ($page - 1) * $per_page;

        // Build WHERE conditions
        $where_conditions = [];

        if (! empty($search)) {
            $search_like   = '%' . $wpdb->esc_like($search) . '%';
            $search_parts  = array();
            $search_values = array();

            foreach (array('nom', 'prenom', 'email') as $column) {
                if (in_array($column, $licence_columns, true)) {
                    $search_parts[]  = "l.{$column} LIKE %s";
                    $search_values[] = $search_like;
                }
            }

            if (! empty($search_parts)) {
                $where_conditions[] = $wpdb->prepare(
                    '(' . implode(' OR ', $search_parts) . ')',
                    $search_values
                );
            }
        }

     if ( ! empty( $filter_region ) ) {
	// Région: priorité au club (c.region) si jointure dispo, sinon fallback licence (l.region) si présent.
	if ( $has_club_id && in_array( 'region', $club_columns, true ) ) {
		if ( in_array( 'region', $licence_columns, true ) ) {
			$where_conditions[] = $wpdb->prepare(
				"COALESCE(NULLIF(c.region,''), NULLIF(l.region,''), '') = %s",
				$filter_region
			);
		} else {
			$where_conditions[] = $wpdb->prepare(
				"NULLIF(c.region,'') = %s",
				$filter_region
			);
		}
	} elseif ( in_array( 'region', $licence_columns, true ) ) {
		$where_conditions[] = $wpdb->prepare(
			"NULLIF(l.region,'') = %s",
			$filter_region
		);
	}
}

        $scope_condition = '';
        if ( $has_club_id && in_array( 'region', $club_columns, true ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region', 'c' );
        } elseif ( in_array( 'region', $licence_columns, true ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region', 'l' );
        }
        if ( $scope_condition ) {
            $where_conditions[] = $scope_condition;
        }

        if (! empty($filter_club) && $has_club_id) {
            $where_conditions[] = $wpdb->prepare("l.club_id = %d", $filter_club);
        }

        if (! empty($filter_status) && in_array('statut', $licence_columns, true)) {
            $normalized_filter = function_exists( 'ufsc_normalize_license_status' )
                ? ufsc_normalize_license_status( $filter_status )
                : $filter_status;

            if ( 'brouillon' === $normalized_filter ) {
                $where_conditions[] = "(l.statut IS NULL OR l.statut = '' OR l.statut IN ('brouillon','draft'))";
            } else {
                $where_conditions[] = $wpdb->prepare("l.statut = %s", $normalized_filter);
            }
        }

        $where_clause = ! empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count for pagination
        $join_sql = $has_club_id ? "LEFT JOIN `{$clubs_table}` c ON l.club_id = c.id" : '';
        $total_query = "SELECT COUNT(*) FROM `{$licences_table}` l {$join_sql} {$where_clause}";
        $total_items = $wpdb->get_var($total_query);
        $total_pages = ceil($total_items / $per_page);

        // Get data with JOIN to show club names
        $select_fields = array(
            (in_array($pk, $licence_columns, true) ? "l.{$pk}" : "0 AS {$pk}"),
            self::build_select_column('l', 'prenom', $licence_columns),
            self::build_select_column('l', 'nom', $licence_columns),
            self::build_select_column('l', 'date_naissance', $licence_columns),
            self::build_select_column('l', 'club_id', $licence_columns),
            ( $join_sql && in_array( 'region', $club_columns, true ) )
	? (
		in_array( 'region', $licence_columns, true )
			? "COALESCE(NULLIF(c.region,''), NULLIF(" . self::build_select_column( 'l', 'region', $licence_columns ) . ",''), '') AS region_resolved"
			: "COALESCE(NULLIF(c.region,''), '') AS region_resolved"
	)
	: (
		in_array( 'region', $licence_columns, true )
			? "COALESCE(NULLIF(" . self::build_select_column( 'l', 'region', $licence_columns ) . ",''), '') AS region_resolved"
			: "'' AS region_resolved"
	),
            self::build_select_column('l', 'statut', $licence_columns),
            self::build_select_column('l', 'payment_status', $licence_columns),
            self::build_select_column('l', 'paid', $licence_columns),
            self::build_select_column('l', 'payee', $licence_columns),
            self::build_select_column('l', 'is_paid', $licence_columns),
            self::build_select_column('l', 'date_creation', $licence_columns),
        );

        if ($join_sql && in_array('nom', $club_columns, true)) {
            $select_fields[] = 'c.nom AS club_nom';
        } else {
            $select_fields[] = "'' AS club_nom";
        }

        $order_column = in_array($pk, $licence_columns, true) ? "l.{$pk}" : (in_array('id', $licence_columns, true) ? 'l.id' : '1');

        $query = "SELECT " . implode(', ', $select_fields) . "
                  FROM `{$licences_table}` l
                  {$join_sql}
                  {$where_clause}
                  ORDER BY {$order_column} DESC
                  LIMIT {$per_page} OFFSET {$offset}";

        $rows = $wpdb->get_results($query);
        foreach ($rows as &$row) {
            $row->statut = function_exists( 'ufsc_normalize_license_status' )
                ? ufsc_normalize_license_status( $row->statut ?? '' )
                : ( $row->statut ?? 'brouillon' );
        }
        unset($row);

        echo '<div class="wrap"><h1>' . esc_html__('Licences (SQL)', 'ufsc-clubs') . '</h1>';

        // Affichage des notices
        if (isset($_GET['updated']) && $_GET['updated'] == '1') {
            echo UFSC_CL_Utils::show_success(__('Licence enregistrée avec succès', 'ufsc-clubs'));
        }
        if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
            $deleted_id = isset($_GET['deleted_id']) ? (int) $_GET['deleted_id'] : '';
            echo UFSC_CL_Utils::show_success(__('La licence #' . $deleted_id . ' a été supprimée.', 'ufsc-clubs'));
        }
        if (isset($_GET['error'])) {
            echo UFSC_CL_Utils::show_error(sanitize_text_field($_GET['error']));
        }

        // Add nonce for AJAX operations
        echo '<input type="hidden" id="ufsc-ajax-nonce" value="' . wp_create_nonce('ufsc_ajax_nonce') . '" />';

        if ( current_user_can( 'manage_options' ) ) {
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=ufsc-sql-licences&action=new')) . '" class="button button-primary">' . esc_html__('Ajouter une licence', 'ufsc-clubs') . '</a> ';
            echo '<a href="' . esc_url(admin_url('admin.php?page=ufsc-exports')) . '" class="button">' . esc_html__('Exporter', 'ufsc-clubs') . '</a></p>';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'edit') {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
            }
            $id = (int) $_GET['id'];
            self::render_licence_form($id);
            echo '</div>';
            return;
        } elseif (isset($_GET['action']) && $_GET['action'] === 'view') {
            $id = (int) $_GET['id'];
            self::render_licence_form($id, true); // true = readonly mode
            echo '</div>';
            return;
        } elseif (isset($_GET['action']) && $_GET['action'] === 'new') {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
            }
            self::render_licence_form(0);
            echo '</div>';
            return;
        }

        // Search and Filters
        echo '<div class="ufsc-list-filters" style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 5px;">';
        echo '<form method="get" class="ufsc-filters-form">';
        echo '<input type="hidden" name="page" value="ufsc-sql-licences" />';

        echo '<div style="display: grid; grid-template-columns: 1fr 200px 200px 150px auto; gap: 10px; align-items: end;">';

        // Search
        echo '<div>';
        echo '<label for="search"><strong>' . esc_html__('Recherche', 'ufsc-clubs') . '</strong></label>';
        echo '<input type="text" name="search" id="search" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Nom, prénom, email...', 'ufsc-clubs') . '" />';
        echo '</div>';

        // Region filter
        echo '<div>';
        echo '<label for="filter_region"><strong>' . esc_html__('Région', 'ufsc-clubs') . '</strong></label>';
        $regions = $scope_label ? array( $scope_label ) : UFSC_CL_Utils::regions();
        echo '<select name="filter_region" id="filter_region">';
        if ( ! $scope_label ) {
            echo '<option value="">' . esc_html__('Toutes', 'ufsc-clubs') . '</option>';
        }
        foreach ($regions as $region) {
            echo '<option value="' . esc_attr($region) . '" ' . selected($filter_region, $region, false) . '>' . esc_html($region) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Club filter
        echo '<div>';
        echo '<label for="filter_club"><strong>' . esc_html__('Club', 'ufsc-clubs') . '</strong></label>';
        echo '<select name="filter_club" id="filter_club">';
        echo '<option value="">' . esc_html__('Tous', 'ufsc-clubs') . '</option>';
        $club_scope_condition = UFSC_Scope::build_scope_condition( 'region' );
        $club_where = $club_scope_condition ? 'WHERE ' . $club_scope_condition : '';
        $clubs = $wpdb->get_results("SELECT id, nom FROM `{$clubs_table}` {$club_where} ORDER BY nom");
        foreach ($clubs as $club) {
            echo '<option value="' . esc_attr($club->id) . '" ' . selected($filter_club, $club->id, false) . '>' . esc_html($club->nom) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Status filter
        echo '<div>';
        echo '<label for="filter_status"><strong>' . esc_html__('Statut', 'ufsc-clubs') . '</strong></label>';
        echo '<select name="filter_status" id="filter_status">';
        echo '<option value="">' . esc_html__('Tous', 'ufsc-clubs') . '</option>';
        foreach (UFSC_SQL::statuses() as $status_key => $status_label) {
            echo '<option value="' . esc_attr($status_key) . '" ' . selected($filter_status, $status_key, false) . '>' . esc_html($status_label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Filter button
        echo '<div>';
        echo '<button type="submit" class="button">' . esc_html__('Filtrer', 'ufsc-clubs') . '</button>';
        if (! empty($search) || ! empty($filter_region) || ! empty($filter_club) || ! empty($filter_status)) {
            echo ' <a href="' . admin_url('admin.php?page=ufsc-sql-licences') . '" class="button">' . esc_html__('Effacer', 'ufsc-clubs') . '</a>';
        }
        echo '</div>';

        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<form method="post" id="bulk-actions-form">';
        // Bulk actions
        echo '<div class="ufsc-bulk-actions" style="margin: 15px 0;">';

        wp_nonce_field('ufsc_bulk_actions');
        echo '<select name="bulk_action" id="bulk-action-selector">';
        echo '<option value="">' . esc_html__('Actions groupées', 'ufsc-clubs') . '</option>';
        echo '<option value="validate">' . esc_html__('Valider', 'ufsc-clubs') . '</option>';
        echo '<option value="reject">' . esc_html__('Refuser', 'ufsc-clubs') . '</option>';
        echo '<option value="pending">' . esc_html__('En attente', 'ufsc-clubs') . '</option>';
        echo '<option value="delete">' . esc_html__('Supprimer', 'ufsc-clubs') . '</option>';
        echo '</select>';
        echo ' <button type="submit" class="button">' . esc_html__('Appliquer', 'ufsc-clubs') . '</button>';
        echo ' <button type="button" class="button ufsc-send-to-payment">' . esc_html__('Envoyer au paiement', 'ufsc-clubs') . '</button>';

        echo '</div>';

        // Results info
        echo '<div class="ufsc-results-info" style="margin: 10px 0;">';
        echo '<p>' . sprintf(esc_html__('%d licence(s) trouvée(s)', 'ufsc-clubs'), $total_items);
        if (! empty($search) || ! empty($filter_region) || ! empty($filter_club) || ! empty($filter_status)) {
            echo ' ' . esc_html__('(filtré)', 'ufsc-clubs');
        }
        echo '</p>';
        echo '</div>';

        // Table
        echo '<table class="wp-list-table widefat fixed striped ufsc-enhanced">';
        echo '<thead><tr>';
        echo '<td class="check-column"><input type="checkbox" id="select-all-licences" /></td>';
        echo '<th class="column-id">' . esc_html__('ID', 'ufsc-clubs') . '</th>';
        echo '<th>' . esc_html__('Licencié', 'ufsc-clubs') . '</th>';
        echo '<th class="column-date">' . esc_html__('Naissance', 'ufsc-clubs') . '</th>';
        echo '<th>' . esc_html__('Club', 'ufsc-clubs') . '</th>';
        echo '<th class="column-region">' . esc_html__('Région', 'ufsc-clubs') . '</th>';
        echo '<th class="column-statut">' . esc_html__('Statut', 'ufsc-clubs') . '</th>';
        echo '<th class="column-date">' . esc_html__('Date création', 'ufsc-clubs') . '</th>';
        echo '<th class="column-actions">' . esc_html__('Actions', 'ufsc-clubs') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if ($rows) {
            foreach ($rows as $r) {
                $normalized_status = function_exists( 'ufsc_normalize_license_status' )
                    ? ufsc_normalize_license_status( $r->statut ?? '' )
                    : ( $r->statut ?? 'brouillon' );
                $is_paid = self::is_licence_paid( $r );
                if ( $is_paid && 'brouillon' === $normalized_status ) {
                    $normalized_status = 'en_attente';
                }

                $view_url     = admin_url('admin.php?page=ufsc-sql-licences&action=view&id=' . $r->$pk);
                $edit_url     = admin_url('admin.php?page=ufsc-sql-licences&action=edit&id=' . $r->$pk);
                $del_url      = wp_nonce_url(admin_url('admin-post.php?action=ufsc_sql_delete_licence&id=' . $r->$pk), 'ufsc_sql_delete_licence');
                $name         = trim($r->prenom . ' ' . $r->nom);
                $club_display = $r->club_nom ? esc_html($r->club_nom) : esc_html__('Club #', 'ufsc-clubs') . $r->club_id;

                echo '<tr>';
                echo '<th class="check-column"><input type="checkbox" name="licence_ids[]" value="' . (int) $r->$pk . '" /></th>';
                echo '<td>' . (int) $r->$pk . '</td>';
                echo '<td><strong>' . esc_html($name) . '</strong></td>';
                echo '<td>' . esc_html($r->date_naissance) . '</td>';
                echo '<td>' . $club_display . '</td>';echo '<td>' . esc_html( '' !== trim( (string) ( $r->region_resolved ?? '' ) ) ? (string) $r->region_resolved : ( '' !== trim( (string) ( $r->region ?? '' ) ) ? (string) $r->region : '—' ) ) . '</td>';
                echo '<td>';

                // Display status badge with colored dot
                echo self::get_status_badge($normalized_status);

                echo '</td>';
                echo '<td>' . esc_html($r->date_creation ?: '') . '</td>';
                echo '<td class="column-actions">';
                echo '<div class="ufsc-button-group">';
                echo '<a class="button button-small" href="' . esc_url($view_url) . '" title="' . esc_attr__('Consulter la licence', 'ufsc-clubs') . '" aria-label="' . esc_attr__('Consulter la licence', 'ufsc-clubs') . '">' . esc_html__('Consulter', 'ufsc-clubs') . '</a>';
                echo '<a class="button button-small" href="' . esc_url($edit_url) . '" title="' . esc_attr__('Éditer la licence', 'ufsc-clubs') . '" aria-label="' . esc_attr__('Éditer la licence', 'ufsc-clubs') . '">' . esc_html__('Éditer', 'ufsc-clubs') . '</a>';
                $is_paid = self::is_licence_paid( $r );
                if ( ! $is_paid ) {
                    $payment_url = wp_nonce_url(admin_url('admin-post.php?action=ufsc_send_license_payment&license_id=' . $r->$pk), 'ufsc_send_license_payment_' . $r->$pk);
                    echo '<a class="button button-small" href="' . esc_url($payment_url) . '" title="' . esc_attr__('Envoyer pour paiement', 'ufsc-clubs') . '" aria-label="' . esc_attr__('Envoyer pour paiement', 'ufsc-clubs') . '" style="background: #00a32a; border-color: #00a32a; color: white;">' . esc_html__('Paiement', 'ufsc-clubs') . '</a>';
                }
                $delete_block_reason = self::get_licence_delete_block_reason( $r );
                if ( '' === $delete_block_reason ) {
                    echo '<a class="button button-small button-link-delete" href="' . esc_url($del_url) . '" title="' . esc_attr__('Supprimer la licence', 'ufsc-clubs') . '" aria-label="' . esc_attr__('Supprimer la licence', 'ufsc-clubs') . '" onclick="return confirm(\'' . esc_js(__('Êtes-vous sûr de vouloir supprimer cette licence ?', 'ufsc-clubs')) . '\')">' . esc_html__('Supprimer', 'ufsc-clubs') . '</a>';
                } else {
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline" onsubmit="var r=prompt(\'' . esc_js( __( 'Motif d\'annulation (obligatoire)', 'ufsc-clubs' ) ) . '\'); if(!r){return false;} this.querySelector(\'[name=cancel_reason]\').value=r; return true;">';
                    wp_nonce_field( 'ufsc_cancel_licence' );
                    echo '<input type="hidden" name="action" value="ufsc_cancel_licence" />';
                    echo '<input type="hidden" name="licence_id" value="' . (int) $r->$pk . '" />';
                    echo '<input type="hidden" name="cancel_reason" value="" />';
                    echo '<button type="submit" class="button button-small" title="' . esc_attr( $delete_block_reason ) . '">' . esc_html__( 'Annuler', 'ufsc-clubs' ) . '</button>';
                    echo '</form>';
                }
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="9">' . esc_html__('Aucune licence trouvée', 'ufsc-clubs') . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</form>';

        // Pagination
        if ($total_pages > 1) {
            echo '<div class="ufsc-pagination" style="margin: 20px 0; text-align: center;">';

            $pagination_base = admin_url('admin.php?page=ufsc-sql-licences');
            if (! empty($search)) {
                $pagination_base .= '&search=' . urlencode($search);
            }
            if (! empty($filter_region)) {
                $pagination_base .= '&filter_region=' . urlencode($filter_region);
            }
            if (! empty($filter_club)) {
                $pagination_base .= '&filter_club=' . $filter_club;
            }
            if (! empty($filter_status)) {
                $pagination_base .= '&filter_status=' . urlencode($filter_status);
            }

            // Previous page
            if ($page > 1) {
                echo '<a href="' . esc_url($pagination_base . '&paged=' . ($page - 1)) . '" class="button">« ' . esc_html__('Précédent', 'ufsc-clubs') . '</a> ';
            }

            // Page numbers
            $start_page = max(1, $page - 2);
            $end_page   = min($total_pages, $page + 2);

            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo '<span class="button button-primary">' . $i . '</span> ';
                } else {
                    echo '<a href="' . esc_url($pagination_base . '&paged=' . $i) . '" class="button">' . $i . '</a> ';
                }
            }

            // Next page
            if ($page < $total_pages) {
                echo '<a href="' . esc_url($pagination_base . '&paged=' . ($page + 1)) . '" class="button">' . esc_html__('Suivant', 'ufsc-clubs') . ' »</a>';
            }

            echo '<p style="margin-top: 10px;">' . sprintf(esc_html__('Page %d sur %d', 'ufsc-clubs'), $page, $total_pages) . '</p>';
            echo '</div>';
        }

        echo '</div>';
    }

    private static function csv_licences($rows)
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="licences_sql.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id', 'prenom', 'nom', 'date_naissance', 'club_id', 'region', 'statut']);
        if ($rows) {
            foreach ($rows as $r) {
                fputcsv($out, [$r->id, $r->prenom, $r->nom, $r->date_naissance, $r->club_id, $r->region, $r->statut]);
            }
        }
        fclose($out);
    }

    private static function render_licence_form($id, $readonly = false)
    {
        global $wpdb;
        $s      = UFSC_SQL::get_settings();
        $t      = $s['table_licences'];
        $pk     = $s['pk_licence'];
        $fields = UFSC_SQL::get_licence_fields();
        $row    = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM `$t` WHERE `$pk`=%d", $id)) : null;
        if ( $row ) {
            if ( isset( $row->club_id ) ) {
                UFSC_Scope::assert_club_in_scope( (int) $row->club_id );
            } elseif ( isset( $row->region ) ) {
                UFSC_Scope::assert_in_scope( $row->region );
            }
        }

        if ($readonly) {
            echo '<h1>' . ($id ? esc_html__('Consulter la licence', 'ufsc-clubs') : esc_html__('Nouvelle licence', 'ufsc-clubs')) . '</h1>';
        } else {
            echo '<h1>' . ($id ? esc_html__('Éditer la licence', 'ufsc-clubs') : esc_html__('Ajouter une nouvelle licence', 'ufsc-clubs')) . '</h1>';
            if (! $id) {
                echo '<div class="ufsc-form-intro" style="background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #2271b1; border-radius: 4px;">';
                echo '<p><strong>' . esc_html__('Instructions pour l\'ajout d\'une licence', 'ufsc-clubs') . '</strong></p>';
                echo '<p>' . esc_html__('Veuillez remplir tous les champs obligatoires marqués d\'un astérisque (*). Les informations saisies seront vérifiées avant validation.', 'ufsc-clubs') . '</p>';
                echo '<ul style="margin: 10px 0 0 20px;">';
                echo '<li>' . esc_html__('Email: utilisé pour l\'envoi des notifications et du lien de paiement', 'ufsc-clubs') . '</li>';
                echo '<li>' . esc_html__('Téléphone: format accepté avec ou sans espaces/tirets', 'ufsc-clubs') . '</li>';
                echo '<li>' . esc_html__('Date de naissance: format JJ/MM/AAAA', 'ufsc-clubs') . '</li>';
                echo '</ul>';
                echo '</div>';
            }
        }

        // Affichage des messages
        if (isset($_GET['updated']) && $_GET['updated'] == '1') {
            echo UFSC_CL_Utils::show_success(__('Licence enregistrée avec succès', 'ufsc-clubs'));
        }
        if ( get_transient( 'ufsc_notice_status_adjusted_' . get_current_user_id() ) ) {
            delete_transient( 'ufsc_notice_status_adjusted_' . get_current_user_id() );
            echo UFSC_CL_Utils::show_success( __( 'Statut ajusté automatiquement vers « En attente » : une licence payée ne peut pas rester en brouillon.', 'ufsc-clubs' ) );
        }

        if (isset($_GET['payment_sent']) && $_GET['payment_sent'] == '1') {
            $order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : '';
            $message  = __('La licence a été enregistrée et envoyée pour paiement.', 'ufsc-clubs');
            if ($order_id) {
                $message .= ' ' . sprintf(__('Commande #%d créée.', 'ufsc-clubs'), $order_id);
            }
            echo UFSC_CL_Utils::show_success($message);
        }
        if (isset($_GET['error'])) {
            echo UFSC_CL_Utils::show_error(sanitize_text_field($_GET['error']));
        }

        if (! $readonly) {
            echo '<form method="post" enctype="multipart/form-data">';
            wp_nonce_field('ufsc_sql_save_licence');
            echo '<input type="hidden" name="action" value="ufsc_sql_save_licence" />';
            echo '<input type="hidden" name="id" value="' . (int) $id . '" />';
            echo '<input type="hidden" name="page" value="ufsc-sql-licences"/>';
        }

        echo '<div class="ufsc-grid">';
        foreach ($fields as $k => $conf) {
            if ( 'is_included' === $k ) {
                continue;
            }
            $val = $row ? (isset($row->$k) ? $row->$k : '') : '';
            self::render_field_licence($k, $conf, $val, $readonly);
        }
        echo '</div>';

        if (! $readonly) {
            echo '<div class="ufsc-form-actions" style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px;">';
            echo '<div class="ufsc-button-group">';
            echo '<a class="button" href="javascript:history.back()">' . esc_html__('Retour', 'ufsc-clubs') . '</a>';
            echo '<button type="submit" name="save_action" value="save" class="button button-primary">' . esc_html__('Enregistrer', 'ufsc-clubs') . '</button>';
            if ($id) {
                // Only show payment button for existing licenses
                echo '<button type="submit" name="save_action" value="save_and_payment" class="button button-secondary" style="background: #00a32a; border-color: #00a32a; color: white;">' . esc_html__('Enregistrer et envoyer pour paiement', 'ufsc-clubs') . '</button>';
            }
            echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=ufsc-sql-licences')) . '">' . esc_html__('Annuler', 'ufsc-clubs') . '</a>';
            echo '</div>';
            if (! $id) {
                echo '<p class="description" style="margin-top: 10px;">' . esc_html__('Note: Le bouton "Envoyer pour paiement" sera disponible après le premier enregistrement.', 'ufsc-clubs') . '</p>';
            }
            echo '</div>';
            echo '</form>';
        } else {
            echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=ufsc-sql-licences')) . '">' . esc_html__('Retour à la liste', 'ufsc-clubs') . '</a>';
            if (current_user_can('manage_options')) {
                echo ' <a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=ufsc-sql-licences&action=edit&id=' . $id)) . '">' . esc_html__('Modifier', 'ufsc-clubs') . '</a>';
            }
            echo '</p>';
        }
    }

    private static function render_field_licence($k, $conf, $val, $readonly = false)
    {
        $label         = $conf[0];
        $type          = $conf[1];
        $readonly_attr = $readonly ? 'readonly disabled' : '';
        $disabled_attr = $readonly ? 'disabled' : '';

        echo '<div class="ufsc-field"><label>' . esc_html($label) . '</label>';

        if ($k === 'club_id') {
            // Special handling for club selector
            global $wpdb;
            $s           = UFSC_SQL::get_settings();
            $clubs_table = $s['table_clubs'];

            $selected_club   = null;
            $selected_region = '';

            if ($val) {
                $selected_club = $wpdb->get_row($wpdb->prepare("SELECT id, nom, region FROM `$clubs_table` WHERE id = %d", $val));
                if ($selected_club) {
                    $selected_region = $selected_club->region;
                }
            }

            echo '<select name="' . esc_attr($k) . '" id="ufsc-club-selector" class="ufsc-club-selector" data-current-region="' . esc_attr($selected_region) . '" ' . $disabled_attr . '>';
            echo '<option value="">' . esc_html__('Sélectionner un club...', 'ufsc-clubs') . '</option>';

            // Get all clubs for the dropdown
            $club_scope_condition = UFSC_Scope::build_scope_condition( 'region' );
            $club_where = $club_scope_condition ? 'WHERE ' . $club_scope_condition : '';
            $clubs = $wpdb->get_results("SELECT id, nom, region FROM `$clubs_table` {$club_where} ORDER BY nom");
            foreach ($clubs as $club) {
                echo '<option value="' . esc_attr($club->id) . '" ' . selected($val, $club->id, false) . ' data-region="' . esc_attr($club->region) . '">' . esc_html($club->nom . ' — ' . $club->region) . '</option>';
            }
            echo '</select>';

            // Auto-populated region field (read-only)
            if (! $readonly) {
                echo '<p class="description">' . esc_html__('La région sera automatiquement remplie selon le club sélectionné.', 'ufsc-clubs') . '</p>';
            }

        } elseif ($k === 'region') {
            // Make region read-only when displayed after club_id
            echo '<input type="text" name="' . esc_attr($k) . '" id="ufsc-auto-region" value="' . esc_attr($val) . '" readonly class="ufsc-readonly-field" ' . $disabled_attr . ' />';
            if (! $readonly) {
                echo '<p class="description">' . esc_html__('Ce champ est automatiquement rempli selon le club sélectionné.', 'ufsc-clubs') . '</p>';
            }

        } elseif ($type === 'textarea') {
            echo '<textarea name="' . esc_attr($k) . '" rows="3" ' . $readonly_attr . '>' . esc_textarea($val) . '</textarea>';
        } elseif ($type === 'number') {
            echo '<input type="number" step="1" name="' . esc_attr($k) . '" value="' . esc_attr($val) . '" ' . $readonly_attr . ' />';
        } elseif ($type === 'region') {
            echo '<select name="' . esc_attr($k) . '" ' . $disabled_attr . '>';
            $scope_slug  = UFSC_Scope::get_user_scope_region();
            $scope_label = $scope_slug ? UFSC_Scope::get_region_label( $scope_slug ) : '';
            $regions = $scope_label ? array( $scope_label ) : UFSC_CL_Utils::regions();
            foreach ( $regions as $r ) {
                echo '<option value="' . esc_attr($r) . '" ' . selected($val, $r, false) . '>' . esc_html($r) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'bool') {
            echo '<select name="' . esc_attr($k) . '" ' . $disabled_attr . '><option value="0" ' . selected($val, '0', false) . '>Non</option><option value="1" ' . selected($val, '1', false) . '>Oui</option></select>';
        } elseif ($type === 'sex') {
            if ($readonly) {
                echo '<span>' . esc_html($val === 'M' ? 'M' : ($val === 'F' ? 'F' : '')) . '</span>';
            } else {
                echo '<label><input type="radio" name="' . esc_attr($k) . '" value="M" ' . checked($val, 'M', false) . '/> M</label> <label style="margin-left:10px"><input type="radio" name="' . esc_attr($k) . '" value="F" ' . checked($val, 'F', false) . '/> F</label>';
            }
        } elseif ($type === 'licence_status') {
            $st = function_exists( 'ufsc_get_license_statuses' ) ? ufsc_get_license_statuses() : UFSC_SQL::statuses();
            $val = function_exists( 'ufsc_normalize_license_status' ) ? ufsc_normalize_license_status( $val ) : $val;
            if (empty($val) || ! array_key_exists($val, $st)) {
                $val = 'brouillon';
            }
            echo '<select name="' . esc_attr($k) . '" ' . $disabled_attr . '>';
            foreach ($st as $sv => $sl) {
                echo '<option value="' . esc_attr($sv) . '" ' . selected($val, $sv, false) . '>' . esc_html($sl) . '</option>';
            }
            echo '</select>';
        } elseif ($k === 'certificat_url') {
            echo '<input type="url" name="certificat_url" value="' . esc_attr($val) . '" placeholder="https://..." ' . $readonly_attr . '/>';
            if (! $readonly) {
                echo '<p class="description">Uploader un fichier ci-dessous alimentera ce champ.</p><input type="file" name="certificat_upload" accept=".jpg,.jpeg,.png,.pdf" />';
            } elseif ($val) {
                echo '<p class="description"><a href="' . esc_url($val) . '" target="_blank">' . esc_html__('Voir le certificat', 'ufsc-clubs') . '</a></p>';
            }
        } elseif ($k === 'email') {
            echo '<input type="email" name="' . esc_attr($k) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr__('exemple@email.com', 'ufsc-clubs') . '" required ' . $readonly_attr . ' />';
        } elseif ($k === 'telephone' || $k === 'tel') {
            echo '<input type="tel" name="' . esc_attr($k) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr__('01 23 45 67 89', 'ufsc-clubs') . '" ' . $readonly_attr . ' />';
        } elseif ($k === 'date_naissance' || strpos($k, 'date_') === 0) {
            echo '<input type="date" name="' . esc_attr($k) . '" value="' . esc_attr($val) . '" ' . $readonly_attr . ' />';
        } elseif ($k === 'prenom') {
            echo '<input type="text" name="' . esc_attr($k) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr__('Prénom', 'ufsc-clubs') . '" required ' . $readonly_attr . ' />';
        } elseif ($k === 'nom') {
            echo '<input type="text" name="' . esc_attr($k) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr__('Nom de famille', 'ufsc-clubs') . '" required ' . $readonly_attr . ' />';
        } else {
            // Default text input
            $placeholder = '';
            if ($k === 'adresse') {
                $placeholder = __('Adresse complète', 'ufsc-clubs');
            } elseif ($k === 'code_postal') {
                $placeholder = __('12345', 'ufsc-clubs');
            } elseif ($k === 'ville') {
                $placeholder = __('Ville', 'ufsc-clubs');
            }

            echo '<input type="text" name="' . esc_attr($k) . '" value="' . esc_attr($val) . '" ' . ($placeholder ? 'placeholder="' . esc_attr($placeholder) . '"' : '') . ' ' . $readonly_attr . ' />';
        }
        echo '</div>';
    }

    public static function handle_save_licence()
    {
        if (! current_user_can('read')) {
            wp_die(__('Accès refusé.', 'ufsc-clubs'));
        }

        check_admin_referer('ufsc_sql_save_licence');

        $user_id = get_current_user_id();
        $club_id = isset($_POST['club_id']) ? (int) $_POST['club_id'] : 0;
        if ( $club_id ) {
            UFSC_Scope::assert_club_in_scope( $club_id );
        }

        // Vérifier droits sur le club
        if (! current_user_can('manage_options') && ufsc_get_user_club_id($user_id) !== $club_id) {
            set_transient('ufsc_error_' . $user_id, __('Permissions insuffisantes', 'ufsc-clubs'), 30);
            self::maybe_redirect(wp_get_referer());
            return;
        }

        global $wpdb;
        $s      = UFSC_SQL::get_settings();
        $t      = $s['table_licences'];
        $pk     = $s['pk_licence'];
        $fields = UFSC_SQL::get_licence_fields();
        $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ( $id ) {
            $existing_club_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT club_id FROM `{$t}` WHERE `{$pk}` = %d",
                $id
            ) );
            if ( $existing_club_id ) {
                UFSC_Scope::assert_club_in_scope( $existing_club_id );
            }
        }

        $data = [];
        foreach ($fields as $k => $conf) {
            if ($k === 'certificat_url') {
                continue;
            }
            if (! array_key_exists($k, $_POST)) {
                continue;
            }

            $type = $conf[1];
            if ($type === 'bool') {
                $data[$k] = wp_unslash($_POST[$k]) == '1' ? 1 : 0;
            } elseif ($type === 'sex') {
                $value = wp_unslash($_POST[$k]);
                $data[$k] = in_array($value, ['M', 'F'], true) ? $value : 'M';
            } else {
                $data[$k] = sanitize_text_field(wp_unslash($_POST[$k]));
            }
        }

        $is_paid_submission = false;
        foreach ( array( 'paid', 'payee', 'is_paid' ) as $paid_key ) {
            if ( isset( $data[ $paid_key ] ) && in_array( (string) $data[ $paid_key ], array( '1', 'yes', 'oui', 'true' ), true ) ) {
                $is_paid_submission = true;
                break;
            }
        }

        if ( isset( $data['payment_status'] ) && in_array( strtolower( (string) $data['payment_status'] ), array( 'paid', 'completed', 'processing' ), true ) ) {
            $is_paid_submission = true;
        }

        if ( isset( $data['statut'] ) && function_exists( 'ufsc_normalize_license_status' ) ) {
            $data['statut'] = ufsc_normalize_license_status( $data['statut'] );
        }

        if ( isset( $data['statut'] ) && 'brouillon' === $data['statut'] && $is_paid_submission ) {
            $data['statut'] = 'en_attente';
            set_transient( 'ufsc_notice_status_adjusted_' . get_current_user_id(), 1, 120 );
        }

        // Validation
        $validation_errors = UFSC_CL_Utils::validate_licence_data($data);
        if (! empty($validation_errors)) {
            $error_message = implode(', ', $validation_errors);
            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&action=' . ($id ? 'edit&id=' . $id : 'new') . '&error=' . urlencode($error_message)));
            return;
        }

        // Upload certificat
        if (! empty($_FILES['certificat_upload']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload = wp_handle_upload($_FILES['certificat_upload'], ['test_form' => false]);
            if (! empty($upload['url'])) {
                $data['certificat_url'] = esc_url_raw($upload['url']);
            } elseif (! empty($upload['error'])) {
                self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&action=' . ($id ? 'edit&id=' . $id : 'new') . '&error=' . urlencode('Erreur upload fichier: ' . $upload['error'])));
                return;
            }
        } else {
            $data['certificat_url'] = isset($_POST['certificat_url']) ? esc_url_raw($_POST['certificat_url']) : '';
        }

        // ⚡ Gérer date_creation
        if ($id) {
            // Update → ne pas toucher date_creation
            unset($data['date_creation']);
            $data_db = self::filter_data_by_columns($t, $data);
            if (empty($data_db)) {
                throw new Exception('Aucune colonne valide à enregistrer.');
            }
            $result = $wpdb->update($t, $data_db, [$pk => $id]);
            if ($result === false) {
                throw new Exception('Erreur lors de la mise à jour de la licence');
            }

            if ( function_exists( 'ufsc_get_licence_season' ) && function_exists( 'ufsc_set_licence_season' ) ) {
                $stored_season = ufsc_get_licence_season( $id );
                if ( ! is_string( $stored_season ) || '' === trim( $stored_season ) ) {
                    ufsc_set_licence_season( $id, ufsc_get_current_season() );
                }
            }
            UFSC_CL_Utils::log('Licence mise à jour: ID ' . $id, 'info');
        } else {
            // Nouvelle licence → ajouter date_creation
            $data['date_creation'] = current_time('mysql');
            $data_db               = self::filter_data_by_columns($t, $data);
            if (empty($data_db)) {
                throw new Exception('Aucune colonne valide à enregistrer.');
            }
            $result                = $wpdb->insert($t, $data_db);
            if ($result === false) {
                throw new Exception('Erreur lors de la création de la licence');
            }

            $id = (int) $wpdb->insert_id;
            if ( function_exists( 'ufsc_get_licence_season' ) && function_exists( 'ufsc_set_licence_season' ) ) {
                $stored_season = ufsc_get_licence_season( $id );
                if ( ! is_string( $stored_season ) || '' === trim( $stored_season ) ) {
                    ufsc_set_licence_season( $id, ufsc_get_current_season() );
                }
            }
            UFSC_CL_Utils::log('Nouvelle licence créée: ID ' . $id, 'info');
        }

        // Gestion bouton "save_and_payment"
        $save_action = isset($_POST['save_action']) ? sanitize_text_field($_POST['save_action']) : 'save';
        if ($save_action === 'save_and_payment' && $id) {
            self::maybe_redirect(admin_url('admin-post.php?action=ufsc_send_license_payment&license_id=' . $id . '&_wpnonce=' . wp_create_nonce('ufsc_send_license_payment_' . $id)));
        } else {
            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&action=edit&id=' . $id . '&updated=1'));
        }
        return;
    }

    /**
     * Handle sending license to payment
     */
    public static function handle_send_license_payment()
    {
        if (! current_user_can('read')) {
            wp_die(__('Accès refusé.', 'ufsc-clubs'));
        }
        if (! current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }

        $license_id = isset($_GET['license_id']) ? (int) $_GET['license_id'] : 0;
        check_admin_referer('ufsc_send_license_payment_' . $license_id);

        if (! $license_id) {
            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&error=' . urlencode(__('ID de licence invalide', 'ufsc-clubs'))));
            return;
        }
        global $wpdb;
        $s  = UFSC_SQL::get_settings();
        $t  = $s['table_licences'];
        $pk = $s['pk_licence'];
        $club_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT club_id FROM {$t} WHERE {$pk} = %d",
            $license_id
        ) );
        if ( $club_id ) {
            UFSC_Scope::assert_club_in_scope( $club_id );
        }

        // Create WooCommerce order for license payment
        $order_id = self::create_order_for_license($license_id);

        if ($order_id) {
            UFSC_CL_Utils::log('Commande créée pour licence ID ' . $license_id . ': Order ID ' . $order_id, 'info');
            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&action=edit&id=' . $license_id . '&payment_sent=1&order_id=' . $order_id));
        } else {
            UFSC_CL_Utils::log('Erreur création commande pour licence ID ' . $license_id, 'error');
            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&action=edit&id=' . $license_id . '&error=' . urlencode(__('Erreur lors de la création de la commande de paiement', 'ufsc-clubs'))));
        }
        return;
    }

    /**
     * Create WooCommerce order for license payment
     */
    private static function create_order_for_license($license_id)
    {
        // Check if WooCommerce is active
        if (! function_exists('wc_create_order')) {
            return false;
        }

        global $wpdb;
        $s  = UFSC_SQL::get_settings();
        $t  = $s['table_licences'];
        $pk = $s['pk_licence'];

        // Get license data
        $license = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$t` WHERE `$pk`=%d", $license_id));
        if (! $license || empty($license->email)) {
            return false;
        }

        try {
            // Calculate license price using configurable rules
            $price = self::calculate_license_price($license);

            // Find or create user by email
            $user = get_user_by('email', $license->email);
            if (! $user) {
                // Create user if not exists
                $user_id = wp_create_user($license->email, wp_generate_password(), $license->email);
                if (is_wp_error($user_id)) {
                    return false;
                }
                $user = get_user_by('id', $user_id);
            }

            // Find or create product
            $product_id = self::get_or_create_license_product();
            if (! $product_id) {
                return false;
            }

            // Create order
            $order = wc_create_order();
            $order->set_customer_id($user->ID);
            $order->set_billing_email($license->email);

            // Set billing info from license data
            if (! empty($license->prenom) && ! empty($license->nom)) {
                $order->set_billing_first_name($license->prenom);
                $order->set_billing_last_name($license->nom);
            }

            // Add product to order with calculated price
            $product = wc_get_product($product_id);
            $product->set_price($price);
            $order->add_product($product, 1);

            // Add order meta
            $order->add_meta_data('_ufsc_license_id', $license_id);
            $order->add_meta_data('_ufsc_license_type', 'individual');

            // Calculate totals
            $order->calculate_totals();

            // Set status to pending payment
            $order->update_status('pending', __('Commande créée pour licence UFSC', 'ufsc-clubs'));

            // Save order
            $order->save();

            // Send invoice email
            if (class_exists('WC_Email_Customer_Invoice')) {
                $mailer = WC()->mailer();
                $emails = $mailer->get_emails();
                if (isset($emails['WC_Email_Customer_Invoice'])) {
                    $emails['WC_Email_Customer_Invoice']->trigger($order->get_id());
                }
            }

            return $order->get_id();

        } catch (Exception $e) {
            UFSC_CL_Utils::log('Erreur création commande WooCommerce: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Calculate license price based on type, region, quota and discounts.
     *
     * Rules can be customized via the `ufsc_license_pricing_rules` filter or
     * by storing an array in the `ufsc_license_pricing_rules` option. The
     * computed price can be further adjusted with the `ufsc_license_price`
     * filter.
     *
     * @param object $license Licence data object.
     * @return float Calculated licence price.
     */
    private static function calculate_license_price($license)
    {
        // Default pricing configuration
        $default_rules = [
            'base_price'         => 50.00,
            'type_prices'        => [
                'standard'    => 50.00,
                'benevole'    => 40.00,
                'postier'     => 45.00,
                'competition' => 60.00,
            ],
            'region_adjustments' => [],
            'quota_surcharge'    => 0.00,
            'discounts'          => [],
        ];

        // Allow configuration through options or filters
        $rules = get_option('ufsc_license_pricing_rules', []);
        $rules = apply_filters('ufsc_license_pricing_rules', $rules, $license);
        $rules = wp_parse_args($rules, $default_rules);

        // Determine license type
        $type = 'standard';
        if (! empty($license->reduction_benevole)) {
            $type = 'benevole';
        } elseif (! empty($license->reduction_postier)) {
            $type = 'postier';
        } elseif (! empty($license->competition)) {
            $type = 'competition';
        }

        // Base price according to type
        $price = isset($rules['type_prices'][$type])
            ? floatval($rules['type_prices'][$type])
            : floatval($rules['base_price']);

        // Regional adjustments
        if (! empty($license->region) && ! empty($rules['region_adjustments'][$license->region])) {
            $price += floatval($rules['region_adjustments'][$license->region]);
        }

        // Quota surcharge when licence not included
        if (isset($license->is_included) && ! $license->is_included && ! empty($rules['quota_surcharge'])) {
            $price += floatval($rules['quota_surcharge']);
        }

        // Additional discounts (percentage or flat amount)
        if (! empty($license->discount_code) && ! empty($rules['discounts'][$license->discount_code])) {
            $discount = floatval($rules['discounts'][$license->discount_code]);
            if ($discount >= 1) {
                $price -= $discount;
            } else {
                $price -= ($price * $discount);
            }
        }

        $price = max(0, $price);

        return apply_filters('ufsc_license_price', $price, $license, $rules);
    }

    /**
     * Get or create license product in WooCommerce
     */
    private static function get_or_create_license_product()
    {
        $current_year = date('Y');
        $product_name = 'Licence UFSC ' . $current_year;

        // Check if product already exists
        $existing_products = get_posts([
            'post_type'      => 'product',
            'meta_query'     => [
                [
                    'key'     => '_ufsc_license_product',
                    'value'   => $current_year,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
        ]);

        if (! empty($existing_products)) {
            return $existing_products[0]->ID;
        }

        // Create new product
        $product = new WC_Product_Simple();
        $product->set_name($product_name);
        $product->set_description(__('Licence UFSC pour l\'année en cours', 'ufsc-clubs'));
        $product->set_short_description(__('Licence UFSC', 'ufsc-clubs'));
        $product->set_price(50.00); // Default price, will be overridden per order
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden'); // Hide from catalog

        $product_id = $product->save();

        if ($product_id) {
            // Add meta to identify this as UFSC license product
            update_post_meta($product_id, '_ufsc_license_product', $current_year);
        }

        return $product_id;
    }

    public static function handle_delete_licence()
    {
        if (! current_user_can('read')) {
            wp_die(__('Accès refusé.', 'ufsc-clubs'));
        }

        check_admin_referer('ufsc_sql_delete_licence');

        global $wpdb;
        $s  = UFSC_SQL::get_settings();
        $t  = $s['table_licences'];
        $pk = $s['pk_licence'];
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        // Fetch the club ID for the licence to validate permissions
        $club_id = 0;
        if ($id && (! function_exists('ufsc_table_has_column') || ufsc_table_has_column($t, 'club_id'))) {
            $club_id = (int) $wpdb->get_var($wpdb->prepare("SELECT club_id FROM {$t} WHERE {$pk} = %d", $id));
        }
        $user_id = get_current_user_id();
        if ( $club_id ) {
            UFSC_Scope::assert_club_in_scope( $club_id );
        }

        // Verify capability and club ownership before proceeding
        if (! current_user_can('manage_options') && ufsc_get_user_club_id($user_id) !== $club_id) {
            set_transient('ufsc_error_' . $user_id, __('Permissions insuffisantes', 'ufsc-clubs'), 30);
            self::maybe_redirect(wp_get_referer());
            return; // Abort if user lacks rights on this club
        }

        if ($id) {
            $licence = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$pk} = %d", $id ) );
            if ( ! $licence ) {
                self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&error=' . urlencode(__('Licence introuvable', 'ufsc-clubs'))));
                return;
            }

            $blocked_reason = self::get_licence_delete_block_reason( $licence );
            if ( '' !== $blocked_reason ) {
                self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&error=' . urlencode($blocked_reason)));
                return;
            }

            $result = $wpdb->delete($t, [$pk => $id]);
            if ($result !== false) {
                UFSC_CL_Utils::log('Licence supprimée: ID ' . $id, 'info');
                self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&deleted=1&deleted_id=' . $id));
            } else {
                UFSC_CL_Utils::log('Erreur suppression licence: ID ' . $id, 'error');
                self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&error=' . urlencode(__('Erreur lors de la suppression de la licence', 'ufsc-clubs'))));
            }
        } else {
            self::maybe_redirect(admin_url('admin.php?page=ufsc-sql-licences&error=' . urlencode(__('ID de licence invalide', 'ufsc-clubs'))));
        }
        return;
    }

    /**
     * Render WooCommerce settings page
     */
    public static function render_woocommerce_settings()
    {
        ufsc_render_woocommerce_settings_page();
    }

    /**
     * Handle AJAX request to update licence status
     */
    public static function handle_ajax_update_licence_status()
    {
        // Check nonce and permissions
        if (! wp_verify_nonce($_POST['nonce'], 'ufsc_ajax_nonce') || ! current_user_can('manage_options')) {
            wp_die();
        }

        $licence_id     = intval($_POST['licence_id']);
        $new_status_raw = sanitize_text_field($_POST['status']);
        $new_status     = class_exists('UFSC_Licence_Status') ? UFSC_Licence_Status::normalize( $new_status_raw ) : strtolower( trim( $new_status_raw ) );

        if (! $licence_id || empty($new_status)) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }

        // Validate status
        $valid_statuses = class_exists('UFSC_Licence_Status') ? UFSC_Licence_Status::allowed() : array_keys(UFSC_SQL::statuses());
        if (! in_array($new_status, $valid_statuses, true)) {
            wp_send_json_error(['message' => 'Invalid status']);
        }

        global $wpdb;
        $s     = UFSC_SQL::get_settings();
        $table = $s['table_licences'];
        $pk    = $s['pk_licence'];
        $club_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT club_id FROM {$table} WHERE {$pk} = %d",
            $licence_id
        ) );
        if ( $club_id ) {
            UFSC_Scope::assert_club_in_scope( $club_id );
        }

        if (function_exists('ufsc_table_has_column') && ! ufsc_table_has_column($table, 'statut')) {
            wp_send_json_error(['message' => 'Missing status column']);
        }

        // Update the status
        if ( class_exists('UFSC_Licence_Status') ) {
            $result = UFSC_Licence_Status::update_status_columns( $table, array( $pk => $licence_id ), $new_status, array( '%d' ) );
        } else {
            $result = $wpdb->update(
                $table,
                ['statut' => $new_status],
                [$pk => $licence_id],
                ['%s'],
                ['%d']
            );
        }

        if ($result !== false) {
            // Get badge class for the new status
            $status_map   = ['valide' => 'success', 'a_regler' => 'info', 'desactive' => 'off', 'en_attente' => 'wait'];
            $badge_class  = isset($status_map[$new_status]) ? $status_map[$new_status] : 'info';
            $status_label = UFSC_SQL::statuses()[$new_status];

            wp_send_json_success([
                'message'      => 'Status updated',
                'badge_class'  => $badge_class,
                'status_label' => $status_label,
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to update status']);
        }
    }

    /**
     * Handle AJAX request to send licences to payment
     */
    public static function handle_ajax_send_to_payment()
    {
        // Check nonce and permissions
        if (! wp_verify_nonce($_POST['nonce'], 'ufsc_ajax_nonce') || ! current_user_can('manage_options')) {
            wp_die();
        }

        $licence_ids = isset($_POST['licence_ids']) ? array_map('intval', $_POST['licence_ids']) : [];

        if (empty($licence_ids)) {
            wp_send_json_error(['message' => 'No licences selected']);
        }

        // Check if WooCommerce is active
        if (! function_exists('WC')) {
            wp_send_json_error(['message' => 'WooCommerce is not active']);
        }

        try {
            // Get licence details
            global $wpdb;
            $s              = UFSC_SQL::get_settings();
            $licences_table = $s['table_licences'];
            $clubs_table    = $s['table_clubs'];
            if ( ! empty( $licence_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $licence_ids ), '%d' ) );
                $club_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT club_id FROM {$licences_table} WHERE id IN ({$placeholders})",
                        $licence_ids
                    )
                );
                foreach ( $club_ids as $club_id ) {
                    UFSC_Scope::assert_club_in_scope( (int) $club_id );
                }
            }

            $licence_ids_placeholder = implode(',', array_fill(0, count($licence_ids), '%d'));
            $query                   = "SELECT l.*, c.nom as club_nom, c.email as club_email
                     FROM `{$licences_table}` l
                     LEFT JOIN `{$clubs_table}` c ON l.club_id = c.id
                     WHERE l.id IN ({$licence_ids_placeholder})";

            $licences = $wpdb->get_results($wpdb->prepare($query, $licence_ids));

            if (empty($licences)) {
                wp_send_json_error(['message' => 'No valid licences found']);
            }

            // Create WooCommerce order
            $order = wc_create_order();

            // Get or create a product for licence fees (you might want to create this in WooCommerce admin)
            $product_id = self::get_or_create_licence_product();

            // Add licences to order
            foreach ($licences as $licence) {
                $product = wc_get_product($product_id);
                $order->add_product($product, 1, [
                    'ufsc_licence_id'   => $licence->id,
                    'ufsc_licence_name' => $licence->prenom . ' ' . $licence->nom,
                    'ufsc_club_name'    => $licence->club_nom,
                ]);
            }

            // Set billing information (use first licence's club info)
            $first_licence = $licences[0];
            $order->set_billing_email($first_licence->club_email ?: get_option('admin_email'));
            $order->set_billing_first_name($first_licence->club_nom ?: 'Club');

            // Calculate totals and save
            $order->calculate_totals();

            // Get payment URL
            $payment_url = $order->get_checkout_payment_url();

            wp_send_json_success([
                'message'     => 'Order created successfully',
                'order_id'    => $order->get_id(),
                'payment_url' => $payment_url,
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error creating order: ' . $e->getMessage()]);
        }
    }

    /**
     * Get or create licence product for WooCommerce
     */
    private static function get_or_create_licence_product()
    {
        // Check if product already exists
        $existing_product = get_posts([
            'post_type'   => 'product',
            'meta_key'    => '_ufsc_licence_product',
            'meta_value'  => '1',
            'numberposts' => 1,
        ]);

        if (! empty($existing_product)) {
            return $existing_product[0]->ID;
        }

        // Create product
        $product = new WC_Product_Simple();
        $product->set_name('Licence UFSC');
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_price(50); // Default price - should be configurable
        $product->set_regular_price(50);
        $product->set_virtual(true);
        $product->set_sold_individually(true);

        $product_id = $product->save();

        // Mark as UFSC licence product
        update_post_meta($product_id, '_ufsc_licence_product', '1');

        return $product_id;
    }

    /**
     * Handle export data request
     */
    public static function handle_export_data()
    {
        if (! current_user_can('read')) {
            wp_die(__('Accès refusé.', 'ufsc-clubs'));
        }
        if (! current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        check_admin_referer('ufsc_export_data');

        global $wpdb;
        $s              = UFSC_SQL::get_settings();
        $licences_table = $s['table_licences'];
        $clubs_table    = $s['table_clubs'];
        $licence_columns = self::get_table_columns($licences_table);
        $club_columns    = self::get_table_columns($clubs_table);
        $has_club_id     = in_array('club_id', $licence_columns, true);

        // Get filters
        $filter_club    = isset($_POST['filter_club']) ? sanitize_text_field($_POST['filter_club']) : '';
        $filter_region  = isset($_POST['filter_region']) ? sanitize_text_field($_POST['filter_region']) : '';
        $filter_status  = isset($_POST['filter_status']) ? sanitize_text_field($_POST['filter_status']) : '';
        $export_format  = isset($_POST['export_format']) ? sanitize_text_field($_POST['export_format']) : 'csv';
        $export_columns = isset($_POST['export_columns']) ? $_POST['export_columns'] : [];
        $scope_slug     = UFSC_Scope::get_user_scope_region();
        $scope_label    = $scope_slug ? UFSC_Scope::get_region_label( $scope_slug ) : '';
        if ( $scope_slug ) {
            $enforced_region = $scope_label ?: $scope_slug;
            if ( $filter_region !== $enforced_region ) {
                $filter_region = $enforced_region;
            }
        }

        // Build query with filters
        $where_conditions = [];
        if (! empty($filter_club) && $has_club_id) {
            $where_conditions[] = $wpdb->prepare("l.club_id = %d", intval($filter_club));
        }
        if (! empty($filter_region) && in_array('region', $licence_columns, true)) {
            $where_conditions[] = $wpdb->prepare("l.region = %s", $filter_region);
        }
        if (! empty($filter_status) && in_array('statut', $licence_columns, true)) {
            $normalized_filter = function_exists( 'ufsc_normalize_license_status' )
                ? ufsc_normalize_license_status( $filter_status )
                : $filter_status;

            if ( 'brouillon' === $normalized_filter ) {
                $where_conditions[] = "(l.statut IS NULL OR l.statut = '' OR l.statut IN ('brouillon','draft'))";
            } else {
                $where_conditions[] = $wpdb->prepare("l.statut = %s", $normalized_filter);
            }
        }
        if ( $has_club_id && in_array( 'region', $club_columns, true ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region', 'c' );
            if ( $scope_condition ) {
                $where_conditions[] = $scope_condition;
            }
        } elseif ( in_array( 'region', $licence_columns, true ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region', 'l' );
            if ( $scope_condition ) {
                $where_conditions[] = $scope_condition;
            }
        }

        $where_clause = ! empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Column mapping for DB
        $column_mapping = [
            'id'                         => 'l.id',
            'nom'                        => 'l.nom',
            'prenom'                     => 'l.prenom',
            'date_naissance'             => 'l.date_naissance',
            'sexe'                       => 'l.sexe',
            'email'                      => 'l.email',
            'adresse'                    => 'l.adresse',
            'suite_adresse'              => 'l.suite_adresse',
            'code_postal'                => 'l.code_postal',
            'ville'                      => 'l.ville',
            'tel_fixe'                   => 'l.tel_fixe',
            'tel_mobile'                 => 'l.tel_mobile',
            'reduction_benevole'         => "CASE WHEN l.reduction_benevole = 1 THEN 'Oui' ELSE 'Non' END AS reduction_benevole",
            'reduction_postier'          => "CASE WHEN l.reduction_postier = 1 THEN 'Oui' ELSE 'Non' END AS reduction_postier",
            'identifiant_laposte'        => 'l.identifiant_laposte',
            'profession'                 => 'l.profession',
            'fonction_publique'          => "CASE WHEN l.fonction_publique = 1 THEN 'Oui' ELSE 'Non' END AS fonction_publique",
            'diffusion_image'            => "CASE WHEN l.diffusion_image = 1 THEN 'Oui' ELSE 'Non' END AS diffusion_image",
            'infos_fsasptt'              => "CASE WHEN l.infos_fsasptt = 1 THEN 'Oui' ELSE 'Non' END AS infos_fsasptt",
            'infos_asptt'                => "CASE WHEN l.infos_asptt = 1 THEN 'Oui' ELSE 'Non' END AS infos_asptt",
            'infos_cr'                   => "CASE WHEN l.infos_cr = 1 THEN 'Oui' ELSE 'Non' END AS infos_cr",
            'infos_partenaires'          => "CASE WHEN l.infos_partenaires = 1 THEN 'Oui' ELSE 'Non' END AS infos_partenaires",
            'honorabilite'               => "CASE WHEN l.honorabilite = 1 THEN 'Oui' ELSE 'Non' END AS honorabilite",
            'competition'                => "CASE WHEN l.competition = 1 THEN 'Oui' ELSE 'Non' END AS competition",
            'licence_delegataire'        => "CASE WHEN l.licence_delegataire = 1 THEN 'Oui' ELSE 'Non' END AS licence_delegataire",
            'numero_licence_delegataire' => 'l.numero_licence_delegataire',
            'note'                       => 'l.note',
            'assurance_dommage_corporel' => "CASE WHEN l.assurance_dommage_corporel = 1 THEN 'Oui' ELSE 'Non' END AS assurance_dommage_corporel",
            'assurance_assistance'       => "CASE WHEN l.assurance_assistance = 1 THEN 'Oui' ELSE 'Non' END AS assurance_assistance",
            'statut'                     => 'l.statut',
            'club_nom'                   => 'c.nom AS club_nom',
            'region'                     => 'l.region',
        ];

        // Column labels for export
        $export_labels = [
            'id'                         => 'ID',
            'nom'                        => 'Nom',
            'prenom'                     => 'Prénom',
            'date_naissance'             => 'Date de naissance',
            'sexe'                       => 'Sexe',
            'email'                      => 'Email',
            'adresse'                    => 'Adresse',
            'suite_adresse'              => 'Suite adresse',
            'code_postal'                => 'Code postal',
            'ville'                      => 'Ville',
            'tel_fixe'                   => 'Tel fixe',
            'tel_mobile'                 => 'Tel Mobile',
            'reduction_benevole'         => 'Réduction bénévole',
            'reduction_postier'          => 'Réduction postier',
            'identifiant_laposte'        => 'Identifiant La Poste',
            'profession'                 => 'Profession',
            'fonction_publique'          => 'Fonction publique',
            'diffusion_image'            => 'Diffusion image',
            'infos_fsasptt'              => 'Recevoir infos FSASPTT',
            'infos_asptt'                => 'Recevoir infos ASPTT',
            'infos_cr'                   => 'Recevoir infos Comite Regional',
            'infos_partenaires'          => 'Recevoir infos partenaires',
            'honorabilite'               => 'Soumis à l\'honorabilité',
            'competition'                => 'Compétition',
            'licence_delegataire'        => 'Licence délégataire',
            'numero_licence_delegataire' => 'Numéro licence délégataire',
            'note'                       => 'Note',
            'assurance_dommage_corporel' => 'Assurance dommage corporel',
            'assurance_assistance'       => 'Assurance assistance',
            'statut'                     => 'Statut',
            'club_nom'                   => 'Nom du club',
            'region'                     => 'Région',
        ];

        // Build SELECT fields
        $select_fields = [];
        foreach ($export_columns as $col) {
            if (isset($column_mapping[$col])) {
                $select_fields[] = self::build_export_select_field($col, $column_mapping[$col], $licence_columns, $club_columns, $has_club_id);
            }
        }
        if (empty($select_fields)) {
            foreach ($column_mapping as $col_key => $col_expression) {
                $select_fields[] = self::build_export_select_field($col_key, $col_expression, $licence_columns, $club_columns, $has_club_id);
            }
        }

        // Run query
        $join_sql = $has_club_id ? "LEFT JOIN `{$clubs_table}` c ON l.club_id = c.id" : '';
        $order_by = in_array('id', $licence_columns, true) ? 'l.id' : '1';
        $query = "SELECT " . implode(', ', $select_fields) . "
              FROM `{$licences_table}` l
              {$join_sql}
              {$where_clause}
              ORDER BY {$order_by} DESC";

        $results = $wpdb->get_results($query, ARRAY_A);
        if (empty($results)) {
            $error_msg = 'Aucune licence trouvée pour le filtre sélectionné.';
            self::maybe_redirect(admin_url('admin.php?page=ufsc-exports&error=' . urlencode($error_msg)));
            return;
        }

        // Build headers with pretty labels
        $headers = [];
        foreach (array_keys($results[0]) as $col_key) {
            $headers[] = isset($export_labels[$col_key]) ? $export_labels[$col_key] : $col_key;
        }

        // Generate filename
        $filename = 'ufsc_licences_' . date('Y-m-d_H-i-s');

        if ($export_format === 'xlsx') {
            self::export_xlsx($results, $filename, $headers);
        } else {
            self::export_csv($results, $filename, $headers);
        }
    }

    /**
     * Export data as CSV
     */
    private static function export_csv($data, $filename, $headers = [])
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        // Headers
        if (! empty($headers)) {
            fputcsv($output, $headers, ';');
        } elseif (! empty($data)) {
            // fallback: keys of first row
            fputcsv($output, array_keys($data[0]), ';');
        }

        // Data rows
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Export data as XLSX (basic implementation)
     */
    private static function export_xlsx($data, $filename)
    {
        // For now, fallback to CSV - in production, you'd use a library like PhpSpreadsheet
        self::export_csv($results, $filename, $headers);
    }

    /**
     * Render Exports page
     */
    public static function render_exports()
    {
        if ( ! UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Exports', 'ufsc-clubs') . '</h1>';
        echo '<p>' . esc_html__('Exportez vos données de clubs et licences avec des filtres personnalisés.', 'ufsc-clubs') . '</p>';
        if (! empty($_GET['error'])) {
            echo '<div class="notice notice-error" style="margin:15px 0; padding:10px; border-left:4px solid red;">';
            echo esc_html($_GET['error']);
            echo '</div>';
        }

        // Filters form
        echo '<div class="ufsc-export-filters" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">';
        echo '<h3>' . esc_html__('Filtres d\'export', 'ufsc-clubs') . '</h3>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('ufsc_export_data');
        echo '<input type="hidden" name="action" value="ufsc_export_data" />';

        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">';

        // Club filter
        echo '<div>';
        echo '<label for="filter_club"><strong>' . esc_html__('Club', 'ufsc-clubs') . '</strong></label>';
        echo '<select name="filter_club" id="filter_club">';
        echo '<option value="">' . esc_html__('Tous les clubs', 'ufsc-clubs') . '</option>';

        // Get clubs from database
        global $wpdb;
        $s           = UFSC_SQL::get_settings();
        $clubs_table = $s['table_clubs'];
        $club_scope_condition = UFSC_Scope::build_scope_condition( 'region' );
        $club_where = $club_scope_condition ? 'WHERE ' . $club_scope_condition : '';
        $clubs       = $wpdb->get_results("SELECT id, nom FROM `{$clubs_table}` {$club_where} ORDER BY nom");
        foreach ($clubs as $club) {
            echo '<option value="' . esc_attr($club->id) . '">' . esc_html($club->nom) . '</option>';
        }

        echo '</select>';
        echo '</div>';

        // Region filter
        echo '<div>';
        echo '<label for="filter_region"><strong>' . esc_html__('Région', 'ufsc-clubs') . '</strong></label>';
        echo '<select name="filter_region" id="filter_region">';
        $scope_slug  = UFSC_Scope::get_user_scope_region();
        $scope_label = $scope_slug ? UFSC_Scope::get_region_label( $scope_slug ) : '';
        if ( ! $scope_label ) {
            echo '<option value="">' . esc_html__('Toutes les régions', 'ufsc-clubs') . '</option>';
        }
        $regions = $scope_label ? array( $scope_label ) : UFSC_CL_Utils::regions();
        foreach ( $regions as $region ) {
            echo '<option value="' . esc_attr($region) . '">' . esc_html($region) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Status filter
        echo '<div>';
        echo '<label for="filter_status"><strong>' . esc_html__('Statut', 'ufsc-clubs') . '</strong></label>';
        echo '<select name="filter_status" id="filter_status">';
        echo '<option value="">' . esc_html__('Tous les statuts', 'ufsc-clubs') . '</option>';
        foreach (UFSC_SQL::statuses() as $status_key => $status_label) {
            echo '<option value="' . esc_attr($status_key) . '">' . esc_html($status_label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '</div>';

        // Column selection
        echo '<div style="margin: 20px 0;">';
        echo '<h4>' . esc_html__('Colonnes à exporter', 'ufsc-clubs') . '</h4>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">';

        $export_columns = [
            'id'                         => __('ID', 'ufsc-clubs'),
            'nom'                        => __('Nom', 'ufsc-clubs'),
            'prenom'                     => __('Prénom', 'ufsc-clubs'),
            'date_naissance'             => __('Date de naissance', 'ufsc-clubs'),
            'sexe'                       => __('Sexe', 'ufsc-clubs'),
            'email'                      => __('Email', 'ufsc-clubs'),
            'adresse'                    => __('Adresse', 'ufsc-clubs'),
            'suite_adresse'              => __('Suite adresse', 'ufsc-clubs'),
            'code_postal'                => __('Code postal', 'ufsc-clubs'),
            'ville'                      => __('Ville', 'ufsc-clubs'),
            'tel_fixe'                   => __('Téléphone fixe', 'ufsc-clubs'),
            'tel_mobile'                 => __('Téléphone mobile', 'ufsc-clubs'),
            'reduction_benevole'         => __('Réduction bénévole', 'ufsc-clubs'),
            'reduction_postier'          => __('Réduction postier', 'ufsc-clubs'),
            'identifiant_laposte'        => __('Identifiant la poste', 'ufsc-clubs'),
            'profession'                 => __('Profession', 'ufsc-clubs'),
            'fonction_publique'          => __('Fonction publique', 'ufsc-clubs'),
            'diffusion_image'            => __('Diffusion image', 'ufsc-clubs'),
            'infos_fsasptt'              => __('Recevoir infos FSASPTT', 'ufsc-clubs'),
            'infos_asptt'                => __('Recevoir infos ASPTT', 'ufsc-clubs'),
            'infos_cr'                   => __('Recevoir infos Comité Régional', 'ufsc-clubs'),
            'infos_partenaires'          => __('Recevoir infos partenaires', 'ufsc-clubs'),
            'honorabilite'               => __('Soumis à l\'honorabilité', 'ufsc-clubs'),
            'competition'                => __('Compétition', 'ufsc-clubs'),
            'licence_delegataire'        => __('Licence Délégataire', 'ufsc-clubs'),
            'numero_licence_delegataire' => __('Numéro licence Délégataire', 'ufsc-clubs'),
            'note'                       => __('Note', 'ufsc-clubs'),
            'assurance_dommage_corporel' => __('Assurance dommage corporel', 'ufsc-clubs'),
            'assurance_assistance'       => __('Assurance assistance', 'ufsc-clubs'),
            'statut'                     => __('Statut', 'ufsc-clubs'),
            'club_nom'                   => __('Nom du club', 'ufsc-clubs'),
            'region'                     => __('Région', 'ufsc-clubs'),
        ];

        foreach ($export_columns as $col_key => $col_label) {
            echo '<label style="display: flex; align-items: center; gap: 5px;">';
            echo '<input type="checkbox" name="export_columns[]" value="' . esc_attr($col_key) . '" checked />';
            echo esc_html($col_label);
            echo '</label>';
        }

        echo '</div>';
        echo '</div>';

        // Export buttons
        echo '<div style="margin: 20px 0;">';
        echo '<button type="submit" name="export_format" value="csv" class="button button-primary">';
        echo esc_html__('Exporter CSV', 'ufsc-clubs');
        echo '</button>';
        echo ' ';
        echo '<button type="submit" name="export_format" value="xlsx" class="button button-secondary">';
        echo esc_html__('Exporter XLSX', 'ufsc-clubs');
        echo '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render Import page
     */
    public static function render_import()
    {
        if ( ! UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Import', 'ufsc-clubs') . '</h1>';
        // Afficher les résultats de l'importation
        if (isset($_GET['imported'])) {
            self::display_import_results();
        }
        echo '<p>' . esc_html__('Importez vos données de clubs et licences avec des filtres personnalisés.', 'ufsc-clubs') . '</p>';

        // Vérifier si nous devons afficher le formulaire de mapping
        if (isset($_GET['step']) && $_GET['step'] === 'mapping' && isset($_GET['entity']) && isset($_GET['filename'])) {
            self::render_mapping_form($_GET['entity'], $_GET['filename']);
        } else {
            self::render_upload_form();
        }

        echo '</div>';
    }
    /**
     * Affiche le formulaire d'upload initial
     */
    private static function render_upload_form()
    {
        // Filters form
        echo '<div class="card-body" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">';
        echo '<h3>' . esc_html__('Importation de données', 'ufsc-clubs') . '</h3>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '" enctype="multipart/form-data">';
        wp_nonce_field('ufsc_import_data');
        echo '<input type="hidden" name="action" value="ufsc_import_data" />';

        // Sélection de l'entité
        echo '<div class="form-group">';
        echo '<div class="form-group row js-entity-select select-widget"><label class="form-control-label required" for="entity"><span class="text-danger">*</span>';
        echo esc_html__('Que voulez-vous importer ?', 'ufsc-clubs');
        echo '</label><div class="col-sm input-container"><select id="entity" name="entity" class="custom-select form-control"><option value="clubs">Clubs</option><option value="licences">Licences</option></select></div></div>';
        echo '</div>';

        // Upload de fichier
        echo '<hr>';
        echo '<div class="form-group js-file-upload-form-group">';
        echo '<label class="form-control-label" for="file">' . esc_html__('Sélectionnez un fichier à importer', 'ufsc-clubs') . '</label>';
        echo '<div class="row">';
        echo '<div class="col">';
        echo '<style>';
        echo '.custom-file-label:after {content: "' . esc_html__('Parcourir', 'ufsc-clubs') . '";}';
        echo '</style>';
        echo '<div class="custom-file">';
        echo '<input type="file" id="file" name="file" class="js-import-file custom-file-input" data-max-file-upload-size="67108864" accept=".csv,.txt" required>';
        echo '<label class="custom-file-label" for="file">' . esc_html__('Choisir un fichier', 'ufsc-clubs') . '</label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Séparateur
        echo '<hr>';
        echo '<div class="form-group row text-widget">';
        echo '<label class="form-control-label required" for="separator"><span class="text-danger">*</span>' . esc_html__('Séparateur de champs', 'ufsc-clubs') . '</label>';
        echo '<div class="col-sm input-container"><input type="text" id="separator" name="separator" required="required" aria-label="separator input" class="form-control" value=";"></div></div>';
        // Encodage du fichier
        echo '<hr>';
        echo '<div class="form-group row text-widget">';
        echo '<label class="form-control-label" for="encoding">' . esc_html__('Encodage du fichier', 'ufsc-clubs') . '</label>';
        echo '<div class="col-sm input-container">';
        echo '<select id="encoding" name="encoding" class="form-control">';
        echo '<option value="auto">' . esc_html__('Détection automatique', 'ufsc-clubs') . '</option>';
        echo '<option value="UTF-8">UTF-8</option>';
        echo '<option value="ISO-8859-1">ISO-8859-1 (Latin-1)</option>';
        echo '<option value="Windows-1252">Windows-1252</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';

        // Bouton d'import
        echo '<div style="margin: 20px 0;">';
        echo '<button type="submit" name="submitImportFile" value="csv" class="button button-primary">';
        echo esc_html__('Importer', 'ufsc-clubs');
        echo '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Affiche le formulaire de mapping des colonnes
     */
    private static function render_mapping_form($entity_type, $filename)
    {
        $file_path = WP_CONTENT_DIR . '/uploads/imports/' . sanitize_file_name($filename);

        if (! file_exists($file_path)) {
            echo '<div class="error"><p>' . esc_html__('Fichier introuvable.', 'ufsc-clubs') . '</p></div>';
            self::render_upload_form();
            return;
        }

        // Lire les en-têtes du CSV avec gestion d'encodage
        $separator = isset($_GET['separator']) ? sanitize_text_field($_GET['separator']) : ';';
        $headers   = self::read_csv_headers($file_path, $separator);

        if (! $headers) {
            echo '<div class="error"><p>' . esc_html__('Impossible de lire le fichier CSV.', 'ufsc-clubs') . '</p></div>';
            self::render_upload_form();
            return;
        }

        // Obtenir les colonnes de la table SQL selon l'entité
        $settings     = UFSC_SQL::get_settings();
        $table_name   = ( $entity_type === 'clubs' ) ? $settings['table_clubs'] : $settings['table_licences'];
        $table_columns = self::get_table_columns( $table_name );

        // Obtenir la liste des clubs si on importe des licences
        $clubs = [];
        if ($entity_type === 'licences') {
            $clubs = self::get_clubs_list();
        }

        echo '<div class="card-body" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">';
        echo '<h3>' . sprintf(esc_html__('Mapping des colonnes - %s', 'ufsc-clubs'), $entity_type) . '</h3>';

        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('ufsc_process_import');
        echo '<input type="hidden" name="action" value="ufsc_process_import" />';
        echo '<input type="hidden" name="entity" value="' . esc_attr($entity_type) . '" />';
        echo '<input type="hidden" name="filename" value="' . esc_attr($filename) . '" />';
        echo '<input type="hidden" name="separator" value="' . esc_attr($separator) . '" />';

        // Ajouter le choix de club pour les licences
        if ($entity_type === 'licences' && ! empty($clubs)) {
            echo '<div class="form-group" style="margin-bottom: 20px;">';
            echo '<label for="club_id" class="form-control-label required">';
            echo '<span class="text-danger">*</span> ' . esc_html__('Club associé', 'ufsc-clubs');
            echo '</label>';
            echo '<select id="club_id" name="club_id" class="form-control" required>';
            echo '<option value="">' . esc_html__('-- Sélectionner un club --', 'ufsc-clubs') . '</option>';

            foreach ($clubs as $club) {
                echo '<option value="' . esc_attr($club->id) . '">';
                echo esc_html($club->nom . ' (ID: ' . $club->id . ')');
                echo '</option>';
            }

            echo '</select>';
            echo '<p class="description">' . esc_html__('Sélectionnez le club auquel seront associées toutes les licences importées.', 'ufsc-clubs') . '</p>';
            echo '</div>';
            echo '<hr>';
        }

        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Colonne CSV', 'ufsc-clubs') . '</th>';
        echo '<th>' . esc_html__('Correspondance avec la table', 'ufsc-clubs') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($headers as $index => $csv_column) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($csv_column) . '</strong></td>';
            echo '<td>';
            echo '<select name="mapping[' . esc_attr($index) . ']" class="form-control">';
            echo '<option value="">' . esc_html__('-- Ignorer cette colonne --', 'ufsc-clubs') . '</option>';

            foreach ($table_columns as $table_column) {
                echo '<option value="' . esc_attr($table_column) . '">' . esc_html($table_column) . '</option>';
            }

            echo '</select>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<div style="margin: 20px 0;">';
        echo '<button type="submit" name="process_import" class="button button-primary">';
        echo esc_html__('Procéder à l\'importation', 'ufsc-clubs');
        echo '</button>';
        echo ' <a href="' . admin_url('admin.php?page=ufsc-import') . '" class="button">';
        echo esc_html__('Annuler', 'ufsc-clubs');
        echo '</a>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Obtenir la liste des clubs
     */
    private static function get_clubs_list()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ufsc_clubs';

        return $wpdb->get_results("SELECT id, nom FROM $table_name ORDER BY nom");
    }

    /**
     * Détecter et corriger l'encodage d'un fichier CSV
     */
    private static function detect_file_encoding($file_path)
    {
        $content = file_get_contents($file_path);

        // Détecter l'encodage
        $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'];

        foreach ($encodings as $encoding) {
            if (mb_check_encoding($content, $encoding)) {
                return $encoding;
            }
        }

        // Si on ne peut pas détecter, essayer UTF-8
        if (mb_check_encoding($content, 'UTF-8')) {
            return 'UTF-8';
        }

        // Par défaut, supposer ISO-8859-1 (Latin-1)
        return 'ISO-8859-1';
    }

    /**
     * Lire les en-têtes CSV avec gestion d'encodage avancée
     */
    private static function read_csv_headers($file_path, $separator)
    {
        $encoding = self::detect_file_encoding($file_path);

        $file = fopen($file_path, 'r');
        if (! $file) {
            return false;
        }

        // Lire la première ligne
        $headers = fgetcsv($file, 0, $separator);
        fclose($file);

        if (! $headers) {
            return false;
        }

        // Convertir en UTF-8 si nécessaire
        if ($encoding !== 'UTF-8') {
            foreach ($headers as &$header) {
                $header = mb_convert_encoding($header, 'UTF-8', $encoding);
                $header = iconv('UTF-8', 'UTF-8//IGNORE', $header);
            }
        }

        return $headers;
    }

    /**
     * Corriger l'encodage des chaînes
     */
    private static function fix_encoding($string)
    {
        // Détecter l'encodage
        $encoding = mb_detect_encoding($string, 'UTF-8, ISO-8859-1, Windows-1252', true);

        if ($encoding === false) {
            // Essayer de détecter avec plus d'encodages
            $encoding = mb_detect_encoding($string, 'auto', true);
        }

        // Convertir en UTF-8 si nécessaire
        if ($encoding && $encoding !== 'UTF-8') {
            $string = mb_convert_encoding($string, 'UTF-8', $encoding);
        }

        // Corriger les caractères mal encodés
        $string = iconv('UTF-8', 'UTF-8//IGNORE', $string);

        return trim($string);
    }

    /**
     * Gérer l'importation des données
     */
    public static function handle_import_data()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }

        check_admin_referer('ufsc_import_data');

        if (empty($_FILES['file']['tmp_name'])) {
            wp_die(__('Veuillez sélectionner un fichier.', 'ufsc-clubs'));
        }

        $entity_type = sanitize_text_field($_POST['entity']);
        $separator   = sanitize_text_field($_POST['separator']);

        // Créer le dossier d'upload si nécessaire
        $upload_dir = WP_CONTENT_DIR . '/uploads/imports/';
        if (! file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }

        // Sauvegarder le fichier temporairement
        $filename  = 'import_' . time() . '_' . sanitize_file_name($_FILES['file']['name']);
        $file_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
            // Rediriger vers le formulaire de mapping
            $redirect_url = add_query_arg([
                'page'      => 'ufsc-import',
                'step'      => 'mapping',
                'entity'    => $entity_type,
                'filename'  => $filename,
                'separator' => $separator,
            ], admin_url('admin.php'));

            self::maybe_redirect($redirect_url, false);
            return;
        } else {
            wp_die(__('Erreur lors du téléchargement du fichier.', 'ufsc-clubs'));
        }
    }

    /**
     * Gérer le traitement final de l'importation
     */
    public static function handle_process_import()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }

        check_admin_referer('ufsc_process_import');

        $entity_type = sanitize_text_field($_POST['entity']);
        $filename    = sanitize_text_field($_POST['filename']);
        $separator   = sanitize_text_field($_POST['separator']);
        $mapping     = $_POST['mapping'];

        // Validation pour les licences
        if ($entity_type === 'licences') {
            if (! isset($_POST['club_id']) || empty($_POST['club_id'])) {
                wp_die(__('Veuillez sélectionner un club.', 'ufsc-clubs'));
            }

            $club_id = intval($_POST['club_id']);
            if ($club_id <= 0) {
                wp_die(__('Club invalide.', 'ufsc-clubs'));
            }
            UFSC_Scope::assert_club_in_scope( $club_id );
        }

        $file_path = WP_CONTENT_DIR . '/uploads/imports/' . $filename;

        if (! file_exists($file_path)) {
            wp_die(__('Fichier introuvable.', 'ufsc-clubs'));
        }

        // Traiter le fichier CSV avec le mapping
        $result = self::process_csv_import($file_path, $entity_type, $mapping, $separator);

        // Nettoyer le fichier temporaire
        unlink($file_path);

        // Rediriger avec un message de statut
        $redirect_url = add_query_arg([
            'page'          => 'ufsc-import',
            'imported'      => $result['success'] ? '1' : '0',
            'count'         => $result['count'],
            'skipped_empty' => $result['skipped'],
        ], admin_url('admin.php'));

        self::maybe_redirect($redirect_url, false);
        return;
    }

    /**
     * Traiter l'importation CSV avec conversion booléenne intelligente
     */
    private static function process_csv_import($file_path, $entity_type, $mapping, $separator)
    {
        global $wpdb;

        $table_name    = $wpdb->prefix . (($entity_type === 'clubs') ? 'ufsc_clubs' : 'ufsc_licences');
        $count         = 0;
        $skipped_empty = 0;

        // Récupérer l'ID du club sélectionné pour les licences
        $club_id = null;
        if ($entity_type === 'licences' && isset($_POST['club_id'])) {
            $club_id = intval($_POST['club_id']);

            // Vérifier que le club existe
            if ($club_id > 0) {
                $club_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ufsc_clubs WHERE id = %d",
                    $club_id
                ));

                if (! $club_exists) {
                    return ['success' => false, 'count' => 0, 'skipped' => 0, 'error' => 'Club invalide'];
                }
            }
        }

        $file = fopen($file_path, 'r');
        if (! $file) {
            return ['success' => false, 'count' => 0, 'skipped' => 0];
        }

        // Lire les en-têtes
        $headers = fgetcsv($file, 0, $separator);

        while (($row = fgetcsv($file, 0, $separator)) !== false) {
            // Vérifier si la ligne est vide
            if (self::is_empty_row($row)) {
                $skipped_empty++;
                continue;
            }

            $data     = [];
            $has_data = false;

            // Ajouter l'ID du club pour les licences si spécifié
            if ($entity_type === 'licences' && $club_id > 0) {
                $data['club_id'] = $club_id;
                $has_data        = true;
            }

            foreach ($mapping as $csv_index => $table_column) {
                if (! empty($table_column) && isset($row[$csv_index])) {
                    $value = trim($row[$csv_index]);

                    // Ignorer les valeurs vides sauf si c'est nécessaire
                    if (! empty($value) && $value !== '""' && $value !== "''") {
                        $has_data = true;

                        // Convertir les valeurs "oui"/"non" en 1/0
                        $value = self::convert_yes_no_to_boolean($value);

                        // Vérifier dynamiquement si c'est une colonne DATE
                        if (self::is_date_column($table_name, $table_column)) {
                            $value = self::convert_date_format($value);
                        }

                        // Concaténer "UFSC" aux valeurs de la colonne "region" si nécessaire
                        if ($table_column === 'region' && ! empty($value)) {
                            $value = self::add_ufsc_prefix($value);
                        }

                        $data[$table_column] = sanitize_text_field($value);
                    }
                }
            }

            // Insérer seulement si il y a des données
            if ($has_data && ! empty($data)) {
                $result = $wpdb->insert($table_name, $data);
                if ($result !== false) {
                    $count++;
                }
            } else {
                $skipped_empty++;
            }
        }

        fclose($file);

        return ['success' => true, 'count' => $count, 'skipped' => $skipped_empty];
    }
    /**
     * Convertir une valeur en booléen (1/0)
     */
    private static function convert_to_boolean($value)
    {
        $value       = trim($value);
        $lower_value = strtolower($value);

        // Valeurs considérées comme "true"
        $true_patterns = [
            '/^oui$/i', '/^yes$/i', '/^true$/i', '/^vrai$/i',
            '/^1$/', '/^ok$/i', '/^activ[ée]$/i', '/^coch[ée]$/i',
            '/^vrai$/i', '/^on$/i', '/^enabled$/i', '/^checked$/i',
        ];

        // Valeurs considérées comme "false"
        $false_patterns = [
            '/^non$/i', '/^no$/i', '/^false$/i', '/^faux$/i',
            '/^0$/', '/^d[ée]sactiv[ée]$/i', '/^d[ée]coch[ée]$/i',
            '/^off$/i', '/^disabled$/i', '/^unchecked$/i',
        ];

        foreach ($true_patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return '1';
            }
        }

        foreach ($false_patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return '0';
            }
        }

        // Si la valeur contient "oui" ou "non" dans le texte
        if (preg_match('/\b(oui|yes|true|vrai)\b/i', $value)) {
            return '1';
        }

        if (preg_match('/\b(non|no|false|faux)\b/i', $value)) {
            return '0';
        }

        // Si c'est un nombre, interpréter comme booléen
        if (is_numeric($value)) {
            return $value != 0 ? '1' : '0';
        }

        // Par défaut, retourner la valeur originale
        return $value;
    }

    /**
     * Vérifier si une ligne CSV est vide
     */
    private static function is_empty_row($row)
    {
        if (! is_array($row)) {
            return true;
        }

        foreach ($row as $cell) {
            $cell = trim($cell);
            if (! empty($cell) && $cell !== '""' && $cell !== "''" && $cell !== 'NULL') {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifier si la colonne est un champ de date en consultant la structure de la table
     */
    private static function is_date_column($table_name, $column_name)
    {
        global $wpdb;

        static $column_types = [];

        if (! isset($column_types[$table_name])) {
            $columns                   = $wpdb->get_results("DESCRIBE $table_name", ARRAY_A);
            $column_types[$table_name] = [];

            foreach ($columns as $column) {
                $column_types[$table_name][$column['Field']] = [
                    'type'    => strtolower($column['Type']),
                    'is_date' => false,
                ];

                $type = $column_types[$table_name][$column['Field']]['type'];

                // Détection des types date
                if (preg_match('/^(date|datetime|timestamp)/', $type)) {
                    $column_types[$table_name][$column['Field']]['is_date'] = true;
                }
            }
        }

        return isset($column_types[$table_name][$column_name]) ?
        $column_types[$table_name][$column_name]['is_date'] : false;
    }

    /**
     * Convertir le format de date vers le format SQL
     */
    private static function convert_date_format($date_string)
    {
        if (empty($date_string) || $date_string === '0000-00-00' || $date_string === 'NULL') {
            return null;
        }

        $date_string = trim($date_string);

        // Liste des formats à essayer
        $formats = [
            'd/m/Y', // Format français
            'd/m/y', // Format français avec année sur 2 chiffres
            'Y-m-d', // Format SQL
            'm/d/Y', // Format américain
            'd-m-Y', // Format européen avec tirets
            'd.m.Y', // Format européen avec points
        ];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_string);
            if ($date && $date->format($format) === $date_string) {
                return $date->format('Y-m-d');
            }
        }

        // Dernier essai avec la détection automatique
        try {
            $date = new DateTime($date_string);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            error_log('Format de date non reconnu: ' . $date_string);
            return null;
        }
    }

    /**
     * Ajouter le préfixe UFSC seulement si il n'existe pas déjà
     */
    private static function add_ufsc_prefix($value)
    {
        $value = trim((string) ($value ?? ''));

        // Normaliser la casse pour la comparaison
        $lower_value = strtolower($value);
        $lower_ufsc  = 'ufsc';

        // Vérifier si la valeur commence par "UFSC" (avec ou sans espace après)
        if (preg_match('/^ufsc\s+/i', $value)) {
            return $value; // Déjà correctement formaté
        }

        // Vérifier si "UFSC" existe ailleurs dans la chaîne
        if (strpos((string) ($lower_value ?? ''), (string) ($lower_ufsc ?? '')) !== false) {
            // Retirer toutes les occurrences de "UFSC" (insensible à la casse)
            $cleaned_value = preg_replace('/\bufsc\b/i', '', $value);
            $cleaned_value = trim(preg_replace('/\s+/', ' ', $cleaned_value)); // Nettoyer les espaces multiples
            return $cleaned_value . ' UFSC';
        }

        // Aucun "UFSC" trouvé, on ajoute le préfixe
        return $value . ' UFSC';
    }

    /**
     * Convertir les valeurs "oui"/"non" en 1/0
     */
    private static function convert_yes_no_to_boolean($value)
    {
        $value       = trim($value);
        $lower_value = strtolower($value);

        // Mapping des valeurs booléennes
        $true_values  = ['oui', 'yes', 'true', 'vrai', '1', 'ok', 'activé', 'active', 'checked'];
        $false_values = ['non', 'no', 'false', 'faux', '0', 'désactivé', 'inactive', 'unchecked'];

        if (in_array($lower_value, $true_values)) {
            return '1';
        }

        if (in_array($lower_value, $false_values)) {
            return '0';
        }

        // Si la valeur contient "oui" ou "non" dans le texte
        if (preg_match('/\b(oui|yes)\b/i', $value)) {
            return '1';
        }

        if (preg_match('/\b(non|no)\b/i', $value)) {
            return '0';
        }

        return $value;
    }
    /**
     * Vérifier si une colonne est de type booléen (TINYINT(1))
     */
    private static function is_boolean_column($table_name, $column_name)
    {
        global $wpdb;

        static $column_types = [];

        if (! isset($column_types[$table_name])) {
            $columns                   = $wpdb->get_results("DESCRIBE $table_name", ARRAY_A);
            $column_types[$table_name] = [];

            foreach ($columns as $column) {
                $type                                        = strtolower($column['Type']);
                $column_types[$table_name][$column['Field']] = preg_match('/^(tinyint\(1\)|bool|boolean)/', $type);
            }
        }

        return isset($column_types[$table_name][$column_name]) ?
        $column_types[$table_name][$column_name] : false;
    }

    /**
     * Afficher les résultats de l'importation
     */
    private static function display_import_results()
    {
        if ($_GET['imported'] === '1') {
            $count           = isset($_GET['count']) ? intval($_GET['count']) : 0;
            $skipped_empty   = isset($_GET['skipped_empty']) ? intval($_GET['skipped_empty']) : 0;
            $skipped_invalid = isset($_GET['skipped_invalid']) ? intval($_GET['skipped_invalid']) : 0;

            echo '<div class="notice notice-success is-dismissible"><p>';

            if ($count > 0) {
                echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
                echo sprintf(
                    _n(
                        '<strong>%d enregistrement</strong> a été importé avec succès.',
                        '<strong>%d enregistrements</strong> ont été importés avec succès.',
                        $count,
                        'ufsc-clubs'
                    ),
                    $count
                );
                echo '<br>';
            }

            if ($skipped_empty > 0) {
                echo '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span> ';
                echo sprintf(
                    _n(
                        '%d ligne vide a été ignorée.',
                        '%d lignes vides ont été ignorées.',
                        $skipped_empty,
                        'ufsc-clubs'
                    ),
                    $skipped_empty
                );
                echo '<br>';
            }

            if ($skipped_invalid > 0) {
                echo '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> ';
                echo sprintf(
                    _n(
                        '%d ligne invalide a été ignorée.',
                        '%d lignes invalides ont été ignorées.',
                        $skipped_invalid,
                        'ufsc-clubs'
                    ),
                    $skipped_invalid
                );
                echo '<br>';
            }

            if ($count === 0 && $skipped_empty === 0 && $skipped_invalid === 0) {
                echo '<span class="dashicons dashicons-info" style="color: #00a0d2;"></span> ';
                echo __('Aucune donnée à importer.', 'ufsc-clubs');
            }

            echo '</p></div>';

        } else {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> ';
            echo __('Erreur lors de l\'importation. Veuillez vérifier votre fichier.', 'ufsc-clubs');
            echo '</p></div>';
        }
    }

    public static function handle_bulk_actions()
    {

        if (! isset($_GET['page']) || $_GET['page'] !== 'ufsc-sql-licences') {
            return;
        }

        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'ufsc_bulk_actions')) {
            return;
        }
        if (! isset($_POST['bulk_action']) || empty($_POST['bulk_action'])) {
            return;
        }

        if (! isset($_POST['licence_ids']) || empty($_POST['licence_ids'])) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning is-dismissible"><p>Aucun élément sélectionné';
                echo '</p></div>';
            });
            return;
        }

        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_licences'];
        $action   = sanitize_text_field($_POST['bulk_action']);
        $item_ids = array_map('intval', $_POST['licence_ids']);
        switch ($action) {
            case 'validate':
                self::bulk_validate_items($item_ids, $table);
                break;
            case 'reject':
                self::bulk_reject_items($item_ids, $table);
                break;
            case 'pending':
                self::bulk_pending_items($item_ids, $table);
                break;
            case 'delete':
                self::bulk_delete_items($item_ids, $table);
                break;
        }

        if (! self::is_cli()) {
            wp_redirect(add_query_arg('processed', count($item_ids), $_POST['_wp_http_referer']));
            exit;
        }
    }

    private static function bulk_validate_items($item_ids, $table)
    {
        global $wpdb;
        if (function_exists('ufsc_table_has_column') && ! ufsc_table_has_column($table, 'statut')) {
            return;
        }

        foreach ($item_ids as $item_id) {
            if ( class_exists( 'UFSC_Licence_Status' ) ) {
                UFSC_Licence_Status::update_status_columns( $table, array( 'id' => $item_id ), 'valide', array( '%d' ) );
            } else {
                $wpdb->update(
                    $table,
                    ['statut' => 'valide'],
                    ['id' => $item_id],
                    ['%s'],
                    ['%d']
                );
            }
        }

        add_action('admin_notices', function () use ($item_ids) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(_n('%d élément validé.', '%d éléments validés.', count($item_ids)), count($item_ids));
            echo '</p></div>';
        });
    }

    private static function bulk_reject_items($item_ids, $table)
    {
        global $wpdb;
        if (function_exists('ufsc_table_has_column') && ! ufsc_table_has_column($table, 'statut')) {
            return;
        }

        foreach ($item_ids as $item_id) {
            if ( class_exists( 'UFSC_Licence_Status' ) ) {
                UFSC_Licence_Status::update_status_columns( $table, array( 'id' => $item_id ), 'refuse', array( '%d' ) );
            } else {
                $result = $wpdb->update(
                    $table,
                    ['statut' => 'refuse'],
                    ['id' => $item_id],
                    ['%s'],
                    ['%d']
                );
            }
        }

        add_action('admin_notices', function () use ($item_ids) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(_n('%d élément refusé.', '%d éléments refusés.', count($item_ids)), count($item_ids));
            echo '</p></div>';
        });
    }

    private static function bulk_pending_items($item_ids, $table)
    {
        global $wpdb;
        if (function_exists('ufsc_table_has_column') && ! ufsc_table_has_column($table, 'statut')) {
            return;
        }

        foreach ($item_ids as $item_id) {
            if ( class_exists( 'UFSC_Licence_Status' ) ) {
                UFSC_Licence_Status::update_status_columns( $table, array( 'id' => $item_id ), 'en_attente', array( '%d' ) );
            } else {
                $result = $wpdb->update(
                    $table,
                    ['statut' => 'en_attente'],
                    ['id' => $item_id],
                    ['%s'],
                    ['%d']
                );
            }
        }

        add_action('admin_notices', function () use ($item_ids) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(_n('%d élément refusé.', '%d éléments en attente.', count($item_ids)), count($item_ids));
            echo '</p></div>';
        });
    }

    private static function bulk_delete_items($item_ids, $table)
    {
        global $wpdb;

        $deleted = 0;
        $blocked = 0;
        foreach ($item_ids as $item_id) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $item_id ) );
            if ( $row && '' !== self::get_licence_delete_block_reason( $row ) ) {
                $blocked++;
                continue;
            }

            $result = $wpdb->delete(
                $table,
                ['id' => $item_id],
                ['%d']
            );
            if ( false !== $result ) {
                $deleted++;
            }
        }

        add_action('admin_notices', function () use ($deleted, $blocked) {
            if ( $deleted > 0 ) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                printf(_n('%d élément supprimé.', '%d éléments supprimés.', $deleted), $deleted);
                echo '</p></div>';
            }
            if ( $blocked > 0 ) {
                echo '<div class="notice notice-warning is-dismissible"><p>';
                printf(_n('%d suppression bloquée (licence validée/commande).', '%d suppressions bloquées (licence validée/commande).', $blocked), $blocked);
                echo '</p></div>';
            }
        });
    }

} /* end class */
