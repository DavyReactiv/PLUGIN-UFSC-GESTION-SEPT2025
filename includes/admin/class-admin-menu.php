<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_CL_Admin_Menu {
    public static function register(){
        // Menu principal unifié UFSC
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'ufsc-attestations' ) {
            wp_safe_redirect( admin_url( 'admin.php?page=ufsc-gestion' ) );
            exit;
        }

        add_menu_page(
            __( 'UFSC Gestion', 'ufsc-clubs' ),
            __( 'UFSC Gestion', 'ufsc-clubs' ),
            'manage_options',
            'ufsc-gestion',
            array( __CLASS__, 'render_dashboard' ),
            'dashicons-groups',
            58
        );

        // Sous-menus organisés
        add_submenu_page(
            'ufsc-gestion',
            __('Clubs','ufsc-clubs'),
            __('Clubs','ufsc-clubs'),
            'manage_options',
            'ufsc-clubs',
            array( 'UFSC_SQL_Admin', 'render_clubs' )
        );

        add_submenu_page(
            'ufsc-gestion',
            __('Licences','ufsc-clubs'),
            __('Licences','ufsc-clubs'),
            'manage_options',
            'ufsc-licences',
            array( 'UFSC_SQL_Admin', 'render_licences' )
        );

        add_submenu_page(
            'ufsc-gestion',
            __('Exports/Imports','ufsc-clubs'),
            __('Exports/Imports','ufsc-clubs'),
            'manage_options',
            'ufsc-exports',
            array( 'UFSC_SQL_Admin', 'render_exports' )
        );

        add_submenu_page(
            'ufsc-gestion',
            __('Réglages','ufsc-clubs'),
            __('Réglages','ufsc-clubs'),
            'manage_options',
            'ufsc-settings',
            array( 'UFSC_Settings_Page', 'render' )
        );

        remove_submenu_page( 'ufsc-gestion', 'ufsc-gestion' );
        remove_menu_page( 'ufsc-attestations' );
    }
    public static function enqueue_admin( $hook ){
        if ( strpos($hook, 'ufsc') !== false ){
            wp_enqueue_style( 'ufsc-admin', UFSC_CL_URL.'assets/admin/css/admin.css', array(), UFSC_CL_VERSION );
            wp_enqueue_script( 'ufsc-admin', UFSC_CL_URL.'assets/admin/js/admin.js', array('jquery'), UFSC_CL_VERSION, true );
            
            // Enqueue license form validation script on license pages
            if (strpos($hook, 'ufsc-sql-licences') !== false || (isset($_GET['page']) && $_GET['page'] === 'ufsc-sql-licences')) {
                wp_enqueue_script( 'ufsc-license-form', UFSC_CL_URL.'assets/js/ufsc-license-form.js', array('jquery'), UFSC_CL_VERSION, true );
            }
        }
    }
    public static function register_front(){
        wp_register_style( 'ufsc-frontend', UFSC_CL_URL.'assets/frontend/css/frontend.css', array(), UFSC_CL_VERSION );
        wp_register_script( 'ufsc-frontend', UFSC_CL_URL.'assets/frontend/js/frontend.js', array('jquery'), UFSC_CL_VERSION, true );
    }
    public static function render_dashboard(){
        global $wpdb; 
        $opts = get_option('ufsc_sql_settings', array());
        $t_clubs = isset($opts['table_clubs']) ? $opts['table_clubs'] : 'clubs';
        $t_lics  = isset($opts['table_licences']) ? $opts['table_licences'] : 'licences';
        
        echo '<div class="wrap">';

        include UFSC_CL_DIR . 'templates/partials/notice.php';

        // Header moderne
        echo '<div class="ufsc-header">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center;">';
        echo '<div>';
        echo '<h1>'.esc_html__('UFSC – Gestion des Clubs et Licences','ufsc-clubs').'</h1>';
        echo '<p>'.esc_html__('Tableau de bord de gestion des clubs et licences sportives UFSC','ufsc-clubs').'</p>';
        echo '</div>';
        // Cache refresh button
        if (current_user_can('manage_options')) {
            $refresh_url = add_query_arg('ufsc_refresh_cache', '1', admin_url('admin.php?page=ufsc-gestion'));
            $refresh_url = wp_nonce_url($refresh_url, 'ufsc_refresh_cache');
            echo '<a href="'.esc_url($refresh_url).'" class="button" style="color: white; border-color: rgba(255,255,255,0.3);" title="'.esc_attr__('Actualiser les données (cache: 10 min)','ufsc-clubs').'">'.esc_html__('⟳ Actualiser','ufsc-clubs').'</a>';
        }
        echo '</div>';
        echo '</div>';
        
        // Handle cache refresh
        if (isset($_GET['ufsc_refresh_cache']) && current_user_can('manage_options')) {
            check_admin_referer('ufsc_refresh_cache');
            delete_transient('ufsc_dashboard_data');
            echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Cache du tableau de bord actualisé.','ufsc-clubs').'</p></div>';
        }
        
        // Vérification des tables avant d'afficher les KPI
        $tables_exist = true;
        try {
            $club_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$t_clubs'") === $t_clubs;
            $licence_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$t_lics'") === $t_lics;
            $tables_exist = $club_table_exists && $licence_table_exists;
        } catch (Exception $e) {
            $tables_exist = false;
        }
        
        if (!$tables_exist) {
            echo '<div class="ufsc-alert error">';
            echo '<strong>'.esc_html__('Configuration requise','ufsc-clubs').'</strong><br>';
            echo esc_html__('Les tables de données ne sont pas encore configurées.','ufsc-clubs').' ';
            echo '<a href="'.admin_url('admin.php?page=ufsc-settings').'">'.esc_html__('Configurer maintenant','ufsc-clubs').'</a>';
            echo '</div>';
            echo '</div>';
            return;
        }
        
        // Get dashboard data with caching
        $dashboard_data = self::get_dashboard_data_cached($t_clubs, $t_lics);
        
        // Enhanced KPI cards
        echo '<div class="ufsc-dashboard-cards">';
        
        // Clubs KPIs
        echo '<div class="ufsc-dashboard-card">';
        echo '<div class="card-label">'.esc_html__('Clubs Total','ufsc-clubs').'</div>';
        echo '<div class="card-value">'.esc_html($dashboard_data['clubs_total']).'</div>';
        echo '<div class="card-description">'.sprintf(esc_html__('%d actifs','ufsc-clubs'), $dashboard_data['clubs_active']).'</div>';
        echo '</div>';
        
        // Licenses by status
        echo '<div class="ufsc-dashboard-card">';
        echo '<div class="card-label">'.esc_html__('Licences Validées','ufsc-clubs').'</div>';
        echo '<div class="card-value" style="color: #00a32a;">'.esc_html($dashboard_data['licenses_valid']).'</div>';
        echo '<div class="card-description">'.sprintf(esc_html__('sur %d total','ufsc-clubs'), $dashboard_data['licenses_total']).'</div>';
        echo '</div>';
        
        echo '<div class="ufsc-dashboard-card">';
        echo '<div class="card-label">'.esc_html__('En Attente','ufsc-clubs').'</div>';
        echo '<div class="card-value" style="color: #f0b000;">'.esc_html($dashboard_data['licenses_pending']).'</div>';
        echo '<div class="card-description">'.esc_html__('paiement requis','ufsc-clubs').'</div>';
        echo '</div>';
        
        echo '<div class="ufsc-dashboard-card">';
        echo '<div class="card-label">'.esc_html__('Licences Refusées','ufsc-clubs').'</div>';
        echo '<div class="card-value" style="color: #d63638;">'.esc_html($dashboard_data['licenses_rejected']).'</div>';
        echo '<div class="card-description">'.esc_html__('révision nécessaire','ufsc-clubs').'</div>';
        echo '</div>';
        
        // Expiring licenses (if available)
        if (isset($dashboard_data['licenses_expiring_soon'])) {
            echo '<div class="ufsc-dashboard-card">';
            echo '<div class="card-label">'.esc_html__('Expirent < 30j','ufsc-clubs').'</div>';
            echo '<div class="card-value" style="color: #f56e28;">'.esc_html($dashboard_data['licenses_expiring_soon']).'</div>';
            echo '<div class="card-description">'.esc_html__('renouvellement requis','ufsc-clubs').'</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Regional breakdown chart
        if (!empty($dashboard_data['regions_data'])) {
            echo '<div class="ufsc-chart-section" style="margin-top: 30px;">';
            echo '<h2>'.esc_html__('Répartition par Région','ufsc-clubs').'</h2>';
            echo '<div class="ufsc-chart-container">';
            echo '<canvas id="ufsc-regions-chart" width="400" height="200"></canvas>';
            echo '</div>';
            echo '</div>';
        }
        
        // License evolution chart
        if (!empty($dashboard_data['evolution_data'])) {
            echo '<div class="ufsc-chart-section" style="margin-top: 30px;">';
            echo '<h2>'.esc_html__('Évolution des Licences (30 derniers jours)','ufsc-clubs').'</h2>';
            echo '<div class="ufsc-chart-container">';
            echo '<canvas id="ufsc-evolution-chart" width="400" height="200"></canvas>';
            echo '</div>';
            echo '</div>';
        }
        
        // Recent activity
        echo '<div class="ufsc-recent-activity" style="margin-top: 30px;">';
        echo '<h2>'.esc_html__('Activité Récente','ufsc-clubs').'</h2>';
        echo '<div class="ufsc-activity-list">';
        
        if (!empty($dashboard_data['recent_licenses'])) {
            foreach ($dashboard_data['recent_licenses'] as $license) {
                $status_class = self::get_status_class($license->statut);
                echo '<div class="activity-item">';
                echo '<span class="activity-date">'.esc_html(mysql2date('d/m/Y', $license->date_inscription)).'</span>';
                echo '<span class="activity-desc">Nouvelle licence: <strong>'.esc_html($license->prenom.' '.$license->nom).'</strong></span>';
                echo '<span class="activity-status ufsc-status-badge ufsc-status-'.$status_class.'"><span class="ufsc-status-dot"></span>'.esc_html(UFSC_SQL::statuses()[$license->statut] ?? $license->statut).'</span>';
                echo '</div>';
            }
        } else {
            echo '<p>'.esc_html__('Aucune activité récente','ufsc-clubs').'</p>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Actions rapides améliorées
        echo '<div class="ufsc-quick-actions" style="margin-top: 30px;">';
        echo '<h2>'.esc_html__('Actions Rapides','ufsc-clubs').'</h2>';
        echo '<div class="ufsc-button-group" style="gap: 12px; flex-wrap: wrap; margin-top: 15px;">';
        echo '<a href="'.admin_url('admin.php?page=ufsc-sql-clubs&action=new').'" class="button button-primary">'.esc_html__('Nouveau Club','ufsc-clubs').'</a>';
        echo '<a href="'.admin_url('admin.php?page=ufsc-sql-licences&action=new').'" class="button button-primary">'.esc_html__('Nouvelle Licence','ufsc-clubs').'</a>';
        echo '<a href="'.admin_url('admin.php?page=ufsc-sql-clubs').'" class="button">'.esc_html__('Gérer les Clubs','ufsc-clubs').'</a>';
        echo '<a href="'.admin_url('admin.php?page=ufsc-sql-licences').'" class="button">'.esc_html__('Gérer les Licences','ufsc-clubs').'</a>';
        echo '<a href="'.admin_url('admin.php?page=ufsc-settings').'" class="button">'.esc_html__('Réglages','ufsc-clubs').'</a>';
        echo '</div>';
        echo '</div>';
        
        // Add charts JavaScript
        self::enqueue_dashboard_scripts($dashboard_data);
        
        echo '</div>';
    }

    /**
     * Get dashboard data with caching
     */
    private static function get_dashboard_data_cached($t_clubs, $t_lics) {
        $cache_key = 'ufsc_dashboard_data';
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        global $wpdb;
        $data = array();
        
        try {
            // Basic club stats
            $data['clubs_total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_clubs`");
            $data['clubs_active'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_clubs` WHERE statut IN ('actif', 'active', 'valide')");
            
            // License stats by status
            $data['licenses_total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_lics`");
            $data['licenses_valid'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_lics` WHERE statut IN ('valide', 'validee', 'active')");
            $data['licenses_pending'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_lics` WHERE statut IN ('en_attente', 'attente', 'pending', 'a_regler')");
            $data['licenses_rejected'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_lics` WHERE statut IN ('refuse', 'rejected')");
            
            // Expiring licenses (if certificat_expiration field exists)
            $columns = $wpdb->get_col("DESCRIBE `$t_lics`");
            if (in_array('certificat_expiration', $columns) || in_array('date_expiration', $columns)) {
                $expiration_field = in_array('certificat_expiration', $columns) ? 'certificat_expiration' : 'date_expiration';
                $thirty_days_from_now = date('Y-m-d', strtotime('+30 days'));
                $data['licenses_expiring_soon'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$t_lics` WHERE `$expiration_field` IS NOT NULL AND `$expiration_field` <= %s AND statut IN ('valide', 'validee', 'active')",
                    $thirty_days_from_now
                ));
            }
            
            // Regional breakdown
            $regions_query = "SELECT region, COUNT(*) as count FROM `$t_lics` WHERE region IS NOT NULL AND region != '' GROUP BY region ORDER BY count DESC LIMIT 10";
            $regions_data = $wpdb->get_results($regions_query);
            $data['regions_data'] = $regions_data;
            
            // License evolution (last 30 days)
            $evolution_query = "SELECT DATE(date_inscription) as date, COUNT(*) as count 
                               FROM `$t_lics` 
                               WHERE date_inscription >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                               GROUP BY DATE(date_inscription) 
                               ORDER BY date ASC";
            $evolution_data = $wpdb->get_results($evolution_query);
            $data['evolution_data'] = $evolution_data;
            
            // Recent licenses (last 10)
            $recent_query = "SELECT prenom, nom, statut, date_inscription 
                            FROM `$t_lics` 
                            ORDER BY date_inscription DESC 
                            LIMIT 10";
            $recent_licenses = $wpdb->get_results($recent_query);
            $data['recent_licenses'] = $recent_licenses;
            
        } catch (Exception $e) {
            UFSC_Audit_Logger::log('UFSC Dashboard data error: ' . $e->getMessage());
            // Return default empty data
            $data = array(
                'clubs_total' => 0,
                'clubs_active' => 0,
                'licenses_total' => 0,
                'licenses_valid' => 0,
                'licenses_pending' => 0,
                'licenses_rejected' => 0,
                'regions_data' => array(),
                'evolution_data' => array(),
                'recent_licenses' => array()
            );
        }
        
        // Cache for 10 minutes
        set_transient($cache_key, $data, 10 * MINUTE_IN_SECONDS);
        
        return $data;
    }

    /**
     * Get status CSS class for display
     */
    private static function get_status_class($status) {
        $status_map = array(
            'valide' => 'valid',
            'validee' => 'valid',
            'active' => 'valid',
            'en_attente' => 'pending',
            'attente' => 'pending',
            'pending' => 'pending',
            'a_regler' => 'pending',
            'refuse' => 'rejected',
            'rejected' => 'rejected',
            'desactive' => 'inactive',
            'inactive' => 'inactive'
        );
        
        return isset($status_map[$status]) ? $status_map[$status] : 'inactive';
    }

    /**
     * Enqueue dashboard scripts and data
     */
    private static function enqueue_dashboard_scripts($dashboard_data) {
        // Enqueue Chart.js from CDN
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true);
        
        // Enqueue our dashboard script
        wp_add_inline_script('chartjs', self::get_dashboard_js($dashboard_data));
    }

    /**
     * Generate dashboard JavaScript for charts
     */
    private static function get_dashboard_js($dashboard_data) {
        $regions_labels = array();
        $regions_values = array();
        
        if (!empty($dashboard_data['regions_data'])) {
            foreach ($dashboard_data['regions_data'] as $region) {
                $regions_labels[] = $region->region;
                $regions_values[] = (int) $region->count;
            }
        }
        
        $evolution_labels = array();
        $evolution_values = array();
        
        if (!empty($dashboard_data['evolution_data'])) {
            foreach ($dashboard_data['evolution_data'] as $evolution) {
                $evolution_labels[] = date('d/m', strtotime($evolution->date));
                $evolution_values[] = (int) $evolution->count;
            }
        }
        
        ob_start();
        ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Regions donut chart
            const regionsCanvas = document.getElementById('ufsc-regions-chart');
            if (regionsCanvas && <?php echo json_encode($regions_labels); ?>.length > 0) {
                new Chart(regionsCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($regions_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($regions_values); ?>,
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF',
                                '#4BC0C0', '#FF6384'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            },
                            title: {
                                display: true,
                                text: 'Licences par Région'
                            }
                        }
                    }
                });
            }

            // Evolution line chart
            const evolutionCanvas = document.getElementById('ufsc-evolution-chart');
            if (evolutionCanvas && <?php echo json_encode($evolution_labels); ?>.length > 0) {
                new Chart(evolutionCanvas, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($evolution_labels); ?>,
                        datasets: [{
                            label: 'Nouvelles Licences',
                            data: <?php echo json_encode($evolution_values); ?>,
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34, 113, 177, 0.1)',
                            tension: 0.1,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Évolution des Inscriptions'
                            }
                        }
                    }
                });
            }
        });
        <?php
        return ob_get_clean();
    }
}
