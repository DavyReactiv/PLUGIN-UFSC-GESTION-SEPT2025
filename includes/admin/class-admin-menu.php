<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_CL_Admin_Menu {

	public static function register() {

		// Menu principal unifié UFSC
		add_menu_page(
			__( 'UFSC Gestion', 'ufsc-clubs' ),
			__( 'UFSC Gestion', 'ufsc-clubs' ),
			UFSC_Capabilities::CAP_MANAGE_READ,
			'ufsc-dashboard',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-groups',
			58
		);

		// Sous-menus organisés
		add_submenu_page(
			'ufsc-dashboard',
			__( 'Tableau de bord', 'ufsc-clubs' ),
			__( 'Tableau de bord', 'ufsc-clubs' ),
			UFSC_Capabilities::CAP_MANAGE_READ,
			'ufsc-dashboard',
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			'ufsc-dashboard',
			__( 'Clubs', 'ufsc-clubs' ),
			__( 'Clubs', 'ufsc-clubs' ),
			UFSC_Capabilities::CAP_MANAGE_READ,
			'ufsc-clubs',
			array( 'UFSC_SQL_Admin', 'render_clubs' )
		);

		add_submenu_page(
			'ufsc-dashboard',
			__( 'Licences', 'ufsc-clubs' ),
			__( 'Licences', 'ufsc-clubs' ),
			UFSC_Capabilities::CAP_MANAGE_READ,
			'ufsc-licences',
			array( 'UFSC_SQL_Admin', 'render_licences' )
		);

		add_submenu_page(
			'ufsc-dashboard',
			__( 'Exports', 'ufsc-clubs' ),
			__( 'Exports', 'ufsc-clubs' ),
			UFSC_Capabilities::CAP_MANAGE_READ,
			'ufsc-exports',
			array( 'UFSC_SQL_Admin', 'render_exports' )
		);

		add_submenu_page(
			'ufsc-dashboard',
			__( 'Import', 'ufsc-clubs' ),
			__( 'Import', 'ufsc-clubs' ),
			UFSC_Capabilities::CAP_MANAGE_READ,
			'ufsc-import',
			array( 'UFSC_SQL_Admin', 'render_import' )
		);

		add_submenu_page(
			'ufsc-dashboard',
			__( 'Paramètres', 'ufsc-clubs' ),
			__( 'Paramètres', 'ufsc-clubs' ),
			UFSC_Capabilities::CAP_MANAGE_READ,
			'ufsc-settings',
			array( 'UFSC_Settings_Page', 'render' )
		);

		add_submenu_page(
			'ufsc-dashboard',
			__( 'WooCommerce', 'ufsc-clubs' ),
			__( 'WooCommerce', 'ufsc-clubs' ),
			UFSC_Capabilities::CAP_MANAGE_READ,
			'ufsc-woocommerce',
			array( 'UFSC_SQL_Admin', 'render_woocommerce_settings' )
		);
	}

	public static function enqueue_admin( $hook ) {
		$hook = (string) ( $hook ?? '' );
		$page = isset( $_GET['page'] ) ? (string) wp_unslash( $_GET['page'] ) : '';
		if ( 0 === strpos( $page, 'ufsc-' ) || false !== strpos( $hook, 'ufsc' ) ) {
			wp_enqueue_style( 'ufsc-admin', UFSC_CL_URL . 'assets/admin/css/admin.css', array(), UFSC_CL_VERSION );
			wp_enqueue_script( 'ufsc-admin', UFSC_CL_URL . 'assets/admin/js/admin.js', array( 'jquery' ), UFSC_CL_VERSION, true );

			// Enqueue license form validation script on license pages
			if ( false !== strpos( $hook, 'ufsc-sql-licences' ) || 'ufsc-sql-licences' === $page || 'ufsc-licences' === $page ) {
				wp_enqueue_script( 'ufsc-license-form', UFSC_CL_URL . 'assets/js/ufsc-license-form.js', array( 'jquery' ), UFSC_CL_VERSION, true );
			}
		}
	}

	public static function register_front() {
		wp_register_style( 'ufsc-frontend', UFSC_CL_URL . 'assets/frontend/css/frontend.css', array(), UFSC_CL_VERSION );
		wp_register_script( 'ufsc-frontend', UFSC_CL_URL . 'assets/frontend/js/frontend.js', array( 'jquery' ), UFSC_CL_VERSION, true );
	}

	public static function render_dashboard() {
		global $wpdb;

		$opts    = get_option( 'ufsc_sql_settings', array() );
		$t_clubs = isset( $opts['table_clubs'] ) ? $opts['table_clubs'] : 'clubs';
		$t_lics  = isset( $opts['table_licences'] ) ? $opts['table_licences'] : 'licences';

		echo '<div class="wrap">';

		include UFSC_CL_DIR . 'templates/partials/notice.php';

		// Header moderne
		echo '<div class="ufsc-header">';
		echo '<div style="display: flex; justify-content: space-between; align-items: center;">';
		echo '<div>';
		echo '<h1>' . esc_html__( 'UFSC – Gestion des Clubs et Licences', 'ufsc-clubs' ) . '</h1>';
		echo '<p>' . esc_html__( 'Tableau de bord de gestion des clubs et licences sportives UFSC', 'ufsc-clubs' ) . '</p>';
		echo '</div>';

		// Cache refresh button
		if ( current_user_can( 'manage_options' ) ) {
			$refresh_url = add_query_arg( 'ufsc_refresh_cache', '1', admin_url( 'admin.php?page=ufsc-dashboard' ) );
			$refresh_url = wp_nonce_url( $refresh_url, 'ufsc_refresh_cache' );
			echo '<a href="' . esc_url( $refresh_url ) . '" class="button" style="color: white; border-color: rgba(255,255,255,0.3);" title="' . esc_attr__( 'Actualiser les données (cache: 10 min)', 'ufsc-clubs' ) . '">' . esc_html__( '⟳ Actualiser', 'ufsc-clubs' ) . '</a>';
		}
		echo '</div>';
		echo '</div>';

		// Handle cache refresh
		if ( isset( $_GET['ufsc_refresh_cache'] ) && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'ufsc_refresh_cache' );
			delete_transient( 'ufsc_dashboard_data' );

			// UFSC PATCH: also flush columns cache (non bloquant)
			if ( function_exists( 'ufsc_flush_table_columns_cache' ) ) {
				ufsc_flush_table_columns_cache();
			}

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cache du tableau de bord actualisé.', 'ufsc-clubs' ) . '</p></div>';
		}

		// Vérification des tables avant d'afficher les KPI
		$tables_exist = true;
		try {
			$club_table_exists    = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $t_clubs ) ) === $t_clubs;
			$licence_table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $t_lics ) ) === $t_lics;
			$tables_exist         = $club_table_exists && $licence_table_exists;
		} catch ( Exception $e ) {
			$tables_exist = false;
		}

		if ( ! $tables_exist ) {
			echo '<div class="ufsc-alert error">';
			echo '<strong>' . esc_html__( 'Configuration requise', 'ufsc-clubs' ) . '</strong><br>';
			echo esc_html__( 'Les tables de données ne sont pas encore configurées.', 'ufsc-clubs' ) . ' ';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=ufsc-settings' ) ) . '">' . esc_html__( 'Configurer maintenant', 'ufsc-clubs' ) . '</a>';
			echo '</div>';
			echo '</div>';
			return;
		}

		// Get dashboard data with caching
		$dashboard_data = self::get_dashboard_data_cached( $t_clubs, $t_lics );

		// Enhanced KPI cards
		echo '<div class="ufsc-dashboard-cards">';

		// Clubs KPIs
		echo '<div class="ufsc-dashboard-card">';
		echo '<div class="card-label">' . esc_html__( 'Clubs Total', 'ufsc-clubs' ) . '</div>';
		echo '<div class="card-value">' . esc_html( $dashboard_data['clubs_total'] ) . '</div>';
		echo '<div class="card-description">' . sprintf( esc_html__( '%d actifs', 'ufsc-clubs' ), (int) $dashboard_data['clubs_active'] ) . '</div>';
		echo '</div>';

		// Licenses by status
		echo '<div class="ufsc-dashboard-card">';
		echo '<div class="card-label">' . esc_html__( 'Licences Validées', 'ufsc-clubs' ) . '</div>';
		echo '<div class="card-value" style="color: #00a32a;">' . esc_html( $dashboard_data['licenses_valid'] ) . '</div>';
		echo '<div class="card-description">' . sprintf( esc_html__( 'sur %d total', 'ufsc-clubs' ), (int) $dashboard_data['licenses_total'] ) . '</div>';
		echo '</div>';

		echo '<div class="ufsc-dashboard-card">';
		echo '<div class="card-label">' . esc_html__( 'En Attente', 'ufsc-clubs' ) . '</div>';
		echo '<div class="card-value" style="color: #f0b000;">' . esc_html( $dashboard_data['licenses_pending'] ) . '</div>';
		echo '<div class="card-description">' . esc_html__( 'paiement requis', 'ufsc-clubs' ) . '</div>';
		echo '</div>';

		echo '<div class="ufsc-dashboard-card">';
		echo '<div class="card-label">' . esc_html__( 'Licences Refusées', 'ufsc-clubs' ) . '</div>';
		echo '<div class="card-value" style="color: #d63638;">' . esc_html( $dashboard_data['licenses_rejected'] ) . '</div>';
		echo '<div class="card-description">' . esc_html__( 'révision nécessaire', 'ufsc-clubs' ) . '</div>';
		echo '</div>';

		// Expiring licenses (if available)
		if ( isset( $dashboard_data['licenses_expiring_soon'] ) ) {
			echo '<div class="ufsc-dashboard-card">';
			echo '<div class="card-label">' . esc_html__( 'Expirent < 30j', 'ufsc-clubs' ) . '</div>';
			echo '<div class="card-value" style="color: #f56e28;">' . esc_html( $dashboard_data['licenses_expiring_soon'] ) . '</div>';
			echo '<div class="card-description">' . esc_html__( 'renouvellement requis', 'ufsc-clubs' ) . '</div>';
			echo '</div>';
		}

		echo '</div>';


		// Licence creation KPIs
		echo '<div class="ufsc-dashboard-card">';
		echo '<div class="card-label">' . esc_html__( 'Licences (7 jours)', 'ufsc-clubs' ) . '</div>';
		echo '<div class="card-value">' . esc_html( (int) $dashboard_data['licenses_new_7d'] ) . '</div>';
		echo '<div class="card-description">' . sprintf( esc_html__( '%d sur 30 jours', 'ufsc-clubs' ), (int) $dashboard_data['licenses_new_30d'] ) . '</div>';
		echo '</div>';

		echo '<div class="ufsc-dashboard-card">';
		echo '<div class="card-label">' . esc_html__( 'Paiement', 'ufsc-clubs' ) . '</div>';
		echo '<div class="card-value" style="color:#00a32a;">' . esc_html( (int) $dashboard_data['licenses_paid'] ) . '</div>';
		echo '<div class="card-description">' . sprintf( esc_html__( 'À régler: %d · Taux: %s%%', 'ufsc-clubs' ), (int) $dashboard_data['licenses_unpaid'], esc_html( $dashboard_data['payment_rate'] ) ) . '</div>';
		echo '</div>';

		echo '<div class="ufsc-dashboard-card">';
		echo '<div class="card-label">' . esc_html__( 'Brouillons', 'ufsc-clubs' ) . '</div>';
		echo '<div class="card-value" style="color:#6c757d;">' . esc_html( (int) $dashboard_data['licenses_draft'] ) . '</div>';
		echo '<div class="card-description">' . sprintf( esc_html__( 'Désactivées: %d', 'ufsc-clubs' ), (int) $dashboard_data['licenses_inactive'] ) . '</div>';
		echo '</div>';

		if ( ! empty( $dashboard_data['alerts_paid_draft'] ) || ! empty( $dashboard_data['alerts_paid_not_valid'] ) ) {
			echo '<div class="ufsc-dashboard-card" style="border-left:4px solid #d63638;">';
			echo '<div class="card-label">' . esc_html__( 'Alertes', 'ufsc-clubs' ) . '</div>';
			echo '<div class="card-description">' . sprintf( esc_html__( 'Payées + brouillon: %d', 'ufsc-clubs' ), (int) $dashboard_data['alerts_paid_draft'] ) . '</div>';
			echo '<div class="card-description">' . sprintf( esc_html__( 'Payées non validées: %d', 'ufsc-clubs' ), (int) $dashboard_data['alerts_paid_not_valid'] ) . '</div>';
			echo '</div>';
		}

		// Regional breakdown chart
		if ( ! empty( $dashboard_data['regions_data'] ) ) {
			echo '<div class="ufsc-chart-section" style="margin-top: 30px;">';
			echo '<h2>' . esc_html__( 'Répartition par Région', 'ufsc-clubs' ) . '</h2>';
			echo '<div class="ufsc-chart-container">';
			echo '<canvas id="ufsc-regions-chart" width="400" height="200"></canvas>';
			echo '</div>';
			echo '</div>';
		}

		// License evolution chart
		if ( ! empty( $dashboard_data['evolution_data'] ) ) {
			echo '<div class="ufsc-chart-section" style="margin-top: 30px;">';
			echo '<h2>' . esc_html__( 'Évolution des Licences (30 derniers jours)', 'ufsc-clubs' ) . '</h2>';
			echo '<div class="ufsc-chart-container">';
			echo '<canvas id="ufsc-evolution-chart" width="400" height="200"></canvas>';
			echo '</div>';
			echo '</div>';
		}


		if ( ! empty( $dashboard_data['status_chart'] ) || ! empty( $dashboard_data['payment_chart'] ) ) {
			echo '<div class="ufsc-chart-section" style="margin-top: 30px;">';
			echo '<div class="ufsc-chart-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">';
			echo '<div class="ufsc-chart-container"><canvas id="ufsc-status-chart" width="400" height="200"></canvas></div>';
			echo '<div class="ufsc-chart-container"><canvas id="ufsc-payment-chart" width="400" height="200"></canvas></div>';
			echo '</div>';
			echo '</div>';
		}

		// Recent activity
		echo '<div class="ufsc-recent-activity" style="margin-top: 30px;">';
		echo '<h2>' . esc_html__( 'Activité Récente', 'ufsc-clubs' ) . '</h2>';
		echo '<div class="ufsc-activity-list">';

		if ( ! empty( $dashboard_data['recent_licenses'] ) ) {
			foreach ( $dashboard_data['recent_licenses'] as $license ) {
				$raw_status    = isset( $license->statut ) ? (string) $license->statut : '';
				$status_norm   = function_exists( 'ufsc_normalize_licence_status' ) ? ufsc_normalize_licence_status( $raw_status ) : $raw_status;
				$status_class  = self::get_status_class( $status_norm );

				$status_label = $raw_status;
				if ( class_exists( 'UFSC_SQL' ) && method_exists( 'UFSC_SQL', 'statuses' ) ) {
					$map          = UFSC_SQL::statuses();
					$status_label = $map[ $raw_status ] ?? $map[ $status_norm ] ?? $status_norm;
				}

				$date_insc = ! empty( $license->date_inscription ) ? $license->date_inscription : current_time( 'mysql' );

				echo '<div class="activity-item">';
				echo '<span class="activity-date">' . esc_html( mysql2date( 'd/m/Y', $date_insc ) ) . '</span>';
				echo '<span class="activity-desc">Nouvelle licence: <strong>' . esc_html( trim( ( $license->prenom ?? '' ) . ' ' . ( $license->nom ?? '' ) ) ) . '</strong></span>';
				echo '<span class="activity-status ufsc-status-badge ufsc-status-' . esc_attr( $status_class ) . '"><span class="ufsc-status-dot"></span>' . esc_html( $status_label ) . '</span>';
				echo '</div>';
			}
		} else {
			echo '<p>' . esc_html__( 'Aucune activité récente', 'ufsc-clubs' ) . '</p>';
		}

		echo '</div>';
		echo '</div>';

		// Actions rapides améliorées
		echo '<div class="ufsc-quick-actions" style="margin-top: 30px;">';
		echo '<h2>' . esc_html__( 'Actions Rapides', 'ufsc-clubs' ) . '</h2>';
		if ( current_user_can( 'manage_options' ) ) {
			echo '<div class="ufsc-button-group" style="gap: 12px; flex-wrap: wrap; margin-top: 15px;">';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs&action=new' ) ) . '" class="button button-primary">' . esc_html__( 'Nouveau Club', 'ufsc-clubs' ) . '</a>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-licences&action=new' ) ) . '" class="button button-primary">' . esc_html__( 'Nouvelle Licence', 'ufsc-clubs' ) . '</a>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs' ) ) . '" class="button">' . esc_html__( 'Gérer les Clubs', 'ufsc-clubs' ) . '</a>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-licences' ) ) . '" class="button">' . esc_html__( 'Gérer les Licences', 'ufsc-clubs' ) . '</a>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=ufsc-settings' ) ) . '" class="button">' . esc_html__( 'Réglages', 'ufsc-clubs' ) . '</a>';
			echo '</div>';
		}
		echo '</div>';

		// Add charts JavaScript
		self::enqueue_dashboard_scripts( $dashboard_data );

		echo '</div>';
	}

	/**
	 * Get dashboard data with caching
	 *
	 * @param string $t_clubs Clubs table.
	 * @param string $t_lics  Licences table.
	 * @return array
	 */
	private static function get_dashboard_data_cached( $t_clubs, $t_lics ) {
		$scope_slug  = UFSC_Scope::get_user_scope_region();
		$cache_key   = 'ufsc_dashboard_data_' . ( $scope_slug ? $scope_slug : 'all' );
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		global $wpdb;
		$data = array();

		try {
			// Basic club stats
			$scope_clubs = UFSC_Scope::build_scope_condition( 'region' );
			$clubs_where = $scope_clubs ? "WHERE {$scope_clubs}" : '';
			$data['clubs_total']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_clubs` {$clubs_where}" );
			$data['clubs_active'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_clubs` " . ( $clubs_where ? "{$clubs_where} AND" : 'WHERE' ) . " statut IN ('actif', 'active', 'valide')" );

			// License stats by status
			$scope_lics = UFSC_Scope::build_scope_condition( 'region' );
			$lics_where = $scope_lics ? "WHERE {$scope_lics}" : '';
			$data['licenses_total']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` {$lics_where}" );
			$data['licenses_valid']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` " . ( $lics_where ? "{$lics_where} AND" : 'WHERE' ) . " statut IN ('valide', 'validee', 'active')" );
			// Intentionally excludes draft/brouillon which is tracked separately in licenses_draft.
			$data['licenses_pending']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` " . ( $lics_where ? "{$lics_where} AND" : 'WHERE' ) . " statut IN ('en_attente', 'attente', 'pending', 'a_regler')" );
			$data['licenses_rejected'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` " . ( $lics_where ? "{$lics_where} AND" : 'WHERE' ) . " statut IN ('refuse', 'rejected')" );


			// Expiring licenses (if certificat_expiration / date_expiration exist)
			$columns = function_exists( 'ufsc_table_columns' )
				? ufsc_table_columns( $t_lics )
				: $wpdb->get_col( "DESCRIBE `$t_lics`" );

			$data['licenses_draft']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` " . ( $lics_where ? "{$lics_where} AND" : 'WHERE' ) . " (statut IS NULL OR statut = '' OR statut IN ('brouillon','draft'))" );
			$data['licenses_inactive'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` " . ( $lics_where ? "{$lics_where} AND" : 'WHERE' ) . " statut IN ('desactive','inactive')" );

			$date_source = in_array( 'date_inscription', $columns, true ) ? 'date_inscription' : ( in_array( 'date_creation', $columns, true ) ? 'date_creation' : '' );
			if ( $date_source ) {
				$data['licenses_new_7d'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` " . ( $lics_where ? "{$lics_where} AND" : 'WHERE' ) . " DATE({$date_source}) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)" );
				$data['licenses_new_30d'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` " . ( $lics_where ? "{$lics_where} AND" : 'WHERE' ) . " DATE({$date_source}) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)" );
			} else {
				$data['licenses_new_7d'] = 0;
				$data['licenses_new_30d'] = 0;
			}

			$paid_parts = array();
			if ( in_array( 'payment_status', $columns, true ) ) {
				$paid_parts[] = "payment_status IN ('paid','completed','processing')";
			}
			foreach ( array( 'paid', 'payee', 'is_paid' ) as $paid_col ) {
				if ( in_array( $paid_col, $columns, true ) ) {
					$paid_parts[] = "{$paid_col} = 1";
				}
			}
			$paid_condition = ! empty( $paid_parts ) ? '(' . implode( ' OR ', $paid_parts ) . ')' : '0 = 1';
			$data['licenses_paid']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` " . ( $lics_where ? "{$lics_where} AND" : 'WHERE' ) . " {$paid_condition}" );
			$data['licenses_unpaid'] = max( 0, (int) $data['licenses_total'] - (int) $data['licenses_paid'] );
			$data['payment_rate']    = $data['licenses_total'] > 0 ? round( ( $data['licenses_paid'] / $data['licenses_total'] ) * 100, 1 ) : 0;
			$data['alerts_paid_draft'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` " . ( $lics_where ? "{$lics_where} AND" : 'WHERE' ) . " {$paid_condition} AND (statut IS NULL OR statut = '' OR statut IN ('brouillon','draft'))" );
			$data['alerts_paid_not_valid'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t_lics` " . ( $lics_where ? "{$lics_where} AND" : 'WHERE' ) . " {$paid_condition} AND statut NOT IN ('valide','validee','active')" );
			$data['status_chart'] = array(
				'Brouillon' => (int) $data['licenses_draft'],
				'En attente' => (int) $data['licenses_pending'],
				'Validée' => (int) $data['licenses_valid'],
				'Refusée' => (int) $data['licenses_rejected'],
				'Désactivée' => (int) $data['licenses_inactive'],
			);
			$data['payment_chart'] = array(
				'Payées' => (int) $data['licenses_paid'],
				'Non payées' => (int) $data['licenses_unpaid'],
			);

			$has_certificat = function_exists( 'ufsc_table_has_column' )
				? ufsc_table_has_column( $t_lics, 'certificat_expiration' )
				: in_array( 'certificat_expiration', $columns, true );

			$has_expiration = function_exists( 'ufsc_table_has_column' )
				? ufsc_table_has_column( $t_lics, 'date_expiration' )
				: in_array( 'date_expiration', $columns, true );

			if ( $has_certificat || $has_expiration ) {
				$expiration_field      = $has_certificat ? 'certificat_expiration' : 'date_expiration';
				$thirty_days_from_now  = gmdate( 'Y-m-d', strtotime( '+30 days' ) );

				$data['licenses_expiring_soon'] = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM `$t_lics`
						 WHERE `$expiration_field` IS NOT NULL
						 AND `$expiration_field` <= %s
						 AND statut IN ('valide', 'validee', 'active')"
						 . ( $scope_lics ? " AND {$scope_lics}" : '' ),
						$thirty_days_from_now
					)
				);
			}

			// Regional breakdown
			$regions_query       = "SELECT region, COUNT(*) as count FROM `$t_lics` WHERE region IS NOT NULL AND region != ''"
				. ( $scope_lics ? " AND {$scope_lics}" : '' )
				. " GROUP BY region ORDER BY count DESC LIMIT 10";
			$data['regions_data'] = $wpdb->get_results( $regions_query );

			// License evolution (last 30 days)
			$evolution_query        = "SELECT DATE(date_inscription) as date, COUNT(*) as count
				FROM `$t_lics`
				WHERE date_inscription >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
				. ( $scope_lics ? " AND {$scope_lics}" : '' )
				. "
				GROUP BY DATE(date_inscription)
				ORDER BY date ASC";
			$data['evolution_data'] = $wpdb->get_results( $evolution_query );

			// Recent licenses (last 10)
			$recent_query           = "SELECT prenom, nom, statut, date_inscription
				FROM `$t_lics`"
				. ( $scope_lics ? " WHERE {$scope_lics}" : '' )
				. "
				ORDER BY date_inscription DESC
				LIMIT 10";
			$data['recent_licenses'] = $wpdb->get_results( $recent_query );

		} catch ( Exception $e ) {
			if ( class_exists( 'UFSC_Audit_Logger' ) ) {
				UFSC_Audit_Logger::log( 'UFSC Dashboard data error: ' . $e->getMessage() );
			}

			// Return default empty data
			$data = array(
				'clubs_total'      => 0,
				'clubs_active'     => 0,
				'licenses_total'   => 0,
				'licenses_valid'   => 0,
				'licenses_pending' => 0,
				'licenses_rejected'=> 0,
				'licenses_draft'   => 0,
				'licenses_inactive'=> 0,
				'licenses_new_7d'  => 0,
				'licenses_new_30d' => 0,
				'licenses_paid'    => 0,
				'licenses_unpaid'  => 0,
				'payment_rate'     => 0,
				'alerts_paid_draft' => 0,
				'alerts_paid_not_valid' => 0,
				'status_chart'     => array(),
				'payment_chart'    => array(),
				'regions_data'     => array(),
				'evolution_data'   => array(),
				'recent_licenses'  => array(),
			);
		}

		// Cache for 10 minutes
		set_transient( $cache_key, $data, 10 * MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Get status CSS class for display
	 */
	private static function get_status_class( $status ) {
		$status = (string) $status;

		$status_map = array(
			'valide'      => 'valid',
			'validee'     => 'valid',
			'active'      => 'valid',
			'en_attente'  => 'pending',
			'attente'     => 'pending',
			'pending'     => 'pending',
			'a_regler'    => 'pending',
			'refuse'      => 'rejected',
			'rejected'    => 'rejected',
			'desactive'   => 'inactive',
			'inactive'    => 'inactive',
		);

		return isset( $status_map[ $status ] ) ? $status_map[ $status ] : 'inactive';
	}

	/**
	 * Enqueue dashboard scripts and data
	 */
	private static function enqueue_dashboard_scripts( $dashboard_data ) {
		// Enqueue Chart.js from CDN
		wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true );

		// Enqueue our dashboard script
		wp_add_inline_script( 'chartjs', self::get_dashboard_js( $dashboard_data ) );
	}

	/**
	 * Generate dashboard JavaScript for charts
	 */
	private static function get_dashboard_js( $dashboard_data ) {
		$regions_labels = array();
		$regions_values = array();

		if ( ! empty( $dashboard_data['regions_data'] ) ) {
			foreach ( $dashboard_data['regions_data'] as $region ) {
				$regions_labels[] = $region->region;
				$regions_values[] = (int) $region->count;
			}
		}

		$evolution_labels = array();
		$evolution_values = array();

		if ( ! empty( $dashboard_data['evolution_data'] ) ) {
			foreach ( $dashboard_data['evolution_data'] as $evolution ) {
				$evolution_labels[] = date( 'd/m', strtotime( $evolution->date ) );
				$evolution_values[] = (int) $evolution->count;
			}
		}

		ob_start();
		?>
document.addEventListener('DOMContentLoaded', function() {
	// Regions donut chart
	const regionsCanvas = document.getElementById('ufsc-regions-chart');
	if (regionsCanvas && <?php echo wp_json_encode( $regions_labels ); ?>.length > 0) {
		new Chart(regionsCanvas, {
			type: 'doughnut',
			data: {
				labels: <?php echo wp_json_encode( $regions_labels ); ?>,
				datasets: [{
					data: <?php echo wp_json_encode( $regions_values ); ?>
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { position: 'right' },
					title: { display: true, text: 'Licences par Région' }
				}
			}
		});
	}


	// Status chart
	const statusCanvas = document.getElementById('ufsc-status-chart');
	const statusChartData = <?php echo wp_json_encode( $dashboard_data['status_chart'] ?? array() ); ?>;
	if (statusCanvas && Object.keys(statusChartData).length > 0) {
		new Chart(statusCanvas, {
			type: 'doughnut',
			data: { labels: Object.keys(statusChartData), datasets: [{ data: Object.values(statusChartData), backgroundColor: ['#6c757d','#f0ad4e','#198754','#dc3545','#343a40'] }] },
			options: { responsive:true, maintainAspectRatio:false, plugins:{ title:{display:true,text:'Répartition par statut'} } }
		});
	}

	// Payment chart
	const paymentCanvas = document.getElementById('ufsc-payment-chart');
	const paymentChartData = <?php echo wp_json_encode( $dashboard_data['payment_chart'] ?? array() ); ?>;
	if (paymentCanvas && Object.keys(paymentChartData).length > 0) {
		new Chart(paymentCanvas, {
			type: 'doughnut',
			data: { labels: Object.keys(paymentChartData), datasets: [{ data: Object.values(paymentChartData), backgroundColor: ['#198754','#dc3545'] }] },
			options: { responsive:true, maintainAspectRatio:false, plugins:{ title:{display:true,text:'Paiement'} } }
		});
	}

	// Evolution line chart
	const evolutionCanvas = document.getElementById('ufsc-evolution-chart');
	if (evolutionCanvas && <?php echo wp_json_encode( $evolution_labels ); ?>.length > 0) {
		new Chart(evolutionCanvas, {
			type: 'line',
			data: {
				labels: <?php echo wp_json_encode( $evolution_labels ); ?>,
				datasets: [{
					label: 'Nouvelles Licences',
					data: <?php echo wp_json_encode( $evolution_values ); ?>,
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
						ticks: { stepSize: 1 }
					}
				},
				plugins: {
					title: { display: true, text: 'Évolution des Inscriptions' }
				}
			}
		});
	}
});
		<?php
		return ob_get_clean();
	}
}
