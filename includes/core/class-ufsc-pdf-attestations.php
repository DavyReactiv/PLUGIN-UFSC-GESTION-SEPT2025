<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC PDF Attestations Manager
 * Handles upload, assignment and secure access to PDF attestations
 */
class UFSC_PDF_Attestations {

	/**
	 * Initialize PDF attestations functionality
	 */
	public static function init() {
		// add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_upload' ) );

		// Download endpoint (AJAX)
		add_action( 'wp_ajax_ufsc_download_attestation', array( __CLASS__, 'handle_secure_download' ) );

		/**
		 * Public download is OFF by default.
		 * If you ever need public download (not recommended), enable via:
		 * add_filter('ufsc_allow_public_attestation_download', '__return_true');
		 */
		if ( apply_filters( 'ufsc_allow_public_attestation_download', false ) ) {
			add_action( 'wp_ajax_nopriv_ufsc_download_attestation', array( __CLASS__, 'handle_secure_download' ) );
		}
	}

	/**
	 * Add admin menu for PDF management
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'ufsc-dashboard',
			__( 'Attestations PDF', 'ufsc-clubs' ),
			__( 'Attestations PDF', 'ufsc-clubs' ),
			'manage_options',
			'ufsc-attestations',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page for PDF management
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
		}

		$current_page = isset( $_GET['ufsc_club_page'] ) ? max( 1, absint( $_GET['ufsc_club_page'] ) ) : 1;
		$per_page     = 200;
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Gestion des Attestations PDF', 'ufsc-clubs' ); ?></h1>

			<?php if ( isset( $_GET['uploaded'] ) ) : ?>
				<div class="notice notice-success">
					<p><?php echo esc_html__( 'Attestation téléchargée avec succès.', 'ufsc-clubs' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['error'] ) ) : ?>
				<div class="notice notice-error">
					<p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p>
				</div>
			<?php endif; ?>

			<div class="ufsc-attestations-admin">

				<!-- Upload Section -->
				<div class="ufsc-card">
					<h2><?php echo esc_html__( 'Télécharger une attestation', 'ufsc-clubs' ); ?></h2>

					<form method="post" enctype="multipart/form-data" action="">
						<?php wp_nonce_field( 'ufsc_upload_attestation', 'ufsc_attestation_nonce' ); ?>

						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="attestation_type"><?php echo esc_html__( 'Type d\'attestation', 'ufsc-clubs' ); ?></label>
								</th>
								<td>
									<select name="attestation_type" id="attestation_type" required>
										<option value=""><?php echo esc_html__( 'Sélectionner un type', 'ufsc-clubs' ); ?></option>
										<option value="licence"><?php echo esc_html__( 'Attestation de licence', 'ufsc-clubs' ); ?></option>
										<option value="affiliation"><?php echo esc_html__( 'Attestation d\'affiliation', 'ufsc-clubs' ); ?></option>
									</select>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="target_type"><?php echo esc_html__( 'Assignation', 'ufsc-clubs' ); ?></label>
								</th>
								<td>
									<select name="target_type" id="target_type" required>
										<option value=""><?php echo esc_html__( 'Sélectionner une assignation', 'ufsc-clubs' ); ?></option>
										<option value="general"><?php echo esc_html__( 'Général (tous les clubs)', 'ufsc-clubs' ); ?></option>
										<option value="region"><?php echo esc_html__( 'Par région', 'ufsc-clubs' ); ?></option>
										<option value="club"><?php echo esc_html__( 'Club spécifique', 'ufsc-clubs' ); ?></option>
									</select>
								</td>
							</tr>

							<tr id="region_selector" style="display: none;">
								<th scope="row">
									<label for="target_region"><?php echo esc_html__( 'Région', 'ufsc-clubs' ); ?></label>
								</th>
								<td>
									<select name="target_region" id="target_region">
										<option value=""><?php echo esc_html__( 'Sélectionner une région', 'ufsc-clubs' ); ?></option>
										<?php foreach ( UFSC_CL_Utils::regions() as $region ) : ?>
											<option value="<?php echo esc_attr( $region ); ?>"><?php echo esc_html( $region ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>

							<tr id="club_selector" style="display: none;">
								<th scope="row">
									<label for="target_club"><?php echo esc_html__( 'Club', 'ufsc-clubs' ); ?></label>
								</th>
								<td>
									<select name="target_club" id="target_club">
										<option value=""><?php echo esc_html__( 'Sélectionner un club', 'ufsc-clubs' ); ?></option>
										<?php echo self::get_clubs_options( $current_page, $per_page ); ?>
									</select>
									<?php self::render_clubs_pagination( $current_page, $per_page ); ?>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="saison"><?php echo esc_html__( 'Saison', 'ufsc-clubs' ); ?></label>
								</th>
								<td>
									<select name="saison" id="saison" required>
										<option value="2025-2026" selected>2025-2026</option>
										<option value="2024-2025">2024-2025</option>
									</select>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="attestation_file"><?php echo esc_html__( 'Fichier PDF', 'ufsc-clubs' ); ?></label>
								</th>
								<td>
									<input type="file" name="attestation_file" id="attestation_file" accept=".pdf" required>
									<p class="description"><?php echo esc_html__( 'Formats acceptés : PDF uniquement. Taille max : 5 MB.', 'ufsc-clubs' ); ?></p>
								</td>
							</tr>
						</table>

						<?php submit_button( __( 'Télécharger l\'attestation', 'ufsc-clubs' ), 'primary', 'upload_attestation' ); ?>
					</form>
				</div>

				<!-- Existing Attestations -->
				<div class="ufsc-card">
					<h2><?php echo esc_html__( 'Attestations existantes', 'ufsc-clubs' ); ?></h2>
					<?php self::render_attestations_list(); ?>
				</div>
			</div>

			<script>
			document.getElementById('target_type').addEventListener('change', function() {
				var regionSelector = document.getElementById('region_selector');
				var clubSelector = document.getElementById('club_selector');

				regionSelector.style.display = this.value === 'region' ? 'table-row' : 'none';
				clubSelector.style.display = this.value === 'club' ? 'table-row' : 'none';
			});
			</script>
		</div>
		<?php
	}

	/**
	 * Get clubs options for select dropdown
	 */
	private static function get_clubs_options( $page = 1, $per_page = 200 ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		global $wpdb;
		$settings = UFSC_SQL::get_settings();
		$table    = $settings['table_clubs'];

		$offset = ( max( 1, absint( $page ) ) - 1 ) * max( 1, absint( $per_page ) );
		$limit  = max( 1, absint( $per_page ) );

		$clubs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, nom FROM `$table` ORDER BY nom LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		$options = '';

		if ( empty( $clubs ) ) {
			$total_clubs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
			if ( function_exists( 'ufsc_admin_debug_log' ) ) {
				$user = wp_get_current_user();
				ufsc_admin_debug_log(
					'UFSC PDF Attestations: clubs list empty',
					array(
						'total_clubs' => $total_clubs,
						'user_id'     => get_current_user_id(),
						'roles'       => $user ? $user->roles : array(),
						'page'        => (int) $page,
						'per_page'    => (int) $per_page,
					)
				);
			}
		}

		foreach ( (array) $clubs as $club ) {
			$options .= '<option value="' . esc_attr( $club->id ) . '">' . esc_html( $club->nom ) . '</option>';
		}

		return $options;
	}

	/**
	 * Render pagination controls for clubs selector.
	 */
	private static function render_clubs_pagination( $page, $per_page ) {
		global $wpdb;
		$settings = UFSC_SQL::get_settings();
		$table    = $settings['table_clubs'];

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
		$pages = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );
		$page  = min( max( 1, (int) $page ), $pages );

		if ( $pages <= 1 ) {
			return;
		}

		$prev_url = add_query_arg( 'ufsc_club_page', max( 1, $page - 1 ) );
		$next_url = add_query_arg( 'ufsc_club_page', min( $pages, $page + 1 ) );

		echo '<p class="description" style="margin-top:8px;">';
		echo sprintf(
			esc_html__( 'Page %1$d sur %2$d', 'ufsc-clubs' ),
			(int) $page,
			(int) $pages
		);
		echo ' &mdash; ';
		echo '<a href="' . esc_url( $prev_url ) . '">' . esc_html__( 'Précédent', 'ufsc-clubs' ) . '</a> | ';
		echo '<a href="' . esc_url( $next_url ) . '">' . esc_html__( 'Suivant', 'ufsc-clubs' ) . '</a>';
		echo '</p>';
	}

	/**
	 * Handle file upload
	 */
	public static function handle_upload() {
		if ( ! isset( $_POST['upload_attestation'] ) ) {
			return;
		}

		check_admin_referer( 'ufsc_upload_attestation', 'ufsc_attestation_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
		}

		$attestation_type = isset( $_POST['attestation_type'] ) ? sanitize_text_field( wp_unslash( $_POST['attestation_type'] ) ) : '';
		$target_type      = isset( $_POST['target_type'] ) ? sanitize_text_field( wp_unslash( $_POST['target_type'] ) ) : '';
		$saison           = isset( $_POST['saison'] ) ? sanitize_text_field( wp_unslash( $_POST['saison'] ) ) : '';

		$target_id = '';
		if ( $target_type === 'region' ) {
			$target_id = isset( $_POST['target_region'] ) ? sanitize_text_field( wp_unslash( $_POST['target_region'] ) ) : '';
		} elseif ( $target_type === 'club' ) {
			$target_id = isset( $_POST['target_club'] ) ? sanitize_text_field( wp_unslash( $_POST['target_club'] ) ) : '';
		}

		if ( ! isset( $_FILES['attestation_file'] ) || ! isset( $_FILES['attestation_file']['error'] ) || $_FILES['attestation_file']['error'] !== UPLOAD_ERR_OK ) {
			self::redirect_with_error( __( 'Erreur lors du téléchargement du fichier.', 'ufsc-clubs' ) );
		}

		$file = $_FILES['attestation_file'];

		$file_type = wp_check_filetype( $file['name'] );
		if ( ( $file_type['ext'] ?? '' ) !== 'pdf' ) {
			self::redirect_with_error( __( 'Seuls les fichiers PDF sont acceptés.', 'ufsc-clubs' ) );
		}

		if ( (int) $file['size'] > 5 * 1024 * 1024 ) {
			self::redirect_with_error( __( 'Le fichier est trop volumineux (5 MB max).', 'ufsc-clubs' ) );
		}

		$upload_dir = self::get_upload_directory();
		if ( ! wp_mkdir_p( $upload_dir ) ) {
			self::redirect_with_error( __( 'Impossible de créer le dossier de téléchargement.', 'ufsc-clubs' ) );
		}

		$filename  = self::generate_filename( $attestation_type, $target_type, $target_id, $saison );
		$file_path = $upload_dir . '/' . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			self::redirect_with_error( __( 'Erreur lors de la sauvegarde du fichier.', 'ufsc-clubs' ) );
		}

		self::save_attestation_info( $attestation_type, $target_type, $target_id, $saison, $filename );

		wp_redirect( admin_url( 'admin.php?page=ufsc-attestations&uploaded=1' ) );
		exit;
	}

	/**
	 * Render list of existing attestations
	 */
	private static function render_attestations_list() {
		$attestations = self::get_attestations();

		if ( empty( $attestations ) ) {
			echo '<p>' . esc_html__( 'Aucune attestation téléchargée.', 'ufsc-clubs' ) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Type', 'ufsc-clubs' ) . '</th>';
		echo '<th>' . esc_html__( 'Assignation', 'ufsc-clubs' ) . '</th>';
		echo '<th>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</th>';
		echo '<th>' . esc_html__( 'Date ajout', 'ufsc-clubs' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'ufsc-clubs' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( (array) $attestations as $attestation ) {
			echo '<tr>';
			echo '<td>' . esc_html( $attestation->type ) . '</td>';
			echo '<td>' . esc_html( self::format_target( $attestation->target_type, $attestation->target_id ) ) . '</td>';
			echo '<td>' . esc_html( $attestation->saison ) . '</td>';
			echo '<td>' . esc_html( mysql2date( 'd/m/Y H:i', $attestation->created_at ) ) . '</td>';
			echo '<td>';
			echo '<a href="' . esc_url( self::get_download_url( $attestation->id ) ) . '" class="button button-small" target="_blank">' . esc_html__( 'Télécharger', 'ufsc-clubs' ) . '</a> ';
			echo '<a href="#" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Êtes-vous sûr de vouloir supprimer cette attestation ?', 'ufsc-clubs' ) ) . '\')">' . esc_html__( 'Supprimer', 'ufsc-clubs' ) . '</a>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Get upload directory for attestations
	 */
	private static function get_upload_directory() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/ufsc-attestations';
	}

	/**
	 * Generate secure filename for attestation
	 */
	private static function generate_filename( $type, $target_type, $target_id, $saison ) {
		$prefix = $type . '_' . $target_type;
		if ( $target_id ) {
			$prefix .= '_' . $target_id;
		}
		$prefix .= '_' . $saison;

		return $prefix . '_' . wp_generate_password( 8, false ) . '.pdf';
	}

	/**
	 * Save attestation info to database
	 */
	private static function save_attestation_info( $type, $target_type, $target_id, $saison, $filename ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ufsc_attestations';

		self::create_attestations_table();

		$wpdb->insert(
			$table_name,
			array(
				'type'       => $type,
				'target_type'=> $target_type,
				'target_id'  => $target_id,
				'saison'     => $saison,
				'filename'   => $filename,
				'created_at' => current_time( 'mysql' ),
				'created_by' => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Create attestations table
	 */
	private static function create_attestations_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ufsc_attestations';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			type varchar(50) NOT NULL,
			target_type varchar(50) NOT NULL,
			target_id varchar(100) DEFAULT '',
			saison varchar(20) NOT NULL,
			filename varchar(255) NOT NULL,
			created_at datetime NOT NULL,
			created_by int(11) NOT NULL,
			PRIMARY KEY (id),
			KEY type_target (type, target_type, target_id),
			KEY saison (saison)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get all attestations
	 */
	private static function get_attestations() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ufsc_attestations';
		return $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC" );
	}

	/**
	 * Format target display
	 */
	private static function format_target( $target_type, $target_id ) {
		switch ( $target_type ) {
			case 'general':
				return __( 'Général', 'ufsc-clubs' );
			case 'region':
				return __( 'Région : ', 'ufsc-clubs' ) . $target_id;
			case 'club':
				return __( 'Club ID : ', 'ufsc-clubs' ) . $target_id;
			default:
				return $target_type;
		}
	}

	/**
	 * Get secure download URL
	 */
	private static function get_download_url( $attestation_id ) {
		return wp_nonce_url(
			admin_url( 'admin-ajax.php?action=ufsc_download_attestation&id=' . (int) $attestation_id ),
			'ufsc_download_' . (int) $attestation_id
		);
	}

	/**
	 * Handle secure download
	 */
	public static function handle_secure_download() {

		$attestation_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $attestation_id ) {
			wp_die( __( 'Attestation non trouvée.', 'ufsc-clubs' ) );
		}

		$allow_public = apply_filters( 'ufsc_allow_public_attestation_download', false, $attestation_id );

		// Accès : connecté OU public explicitement autorisé
		if ( ! is_user_logged_in() && ! $allow_public ) {
			wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
		}

		// Sécurité minimale si connecté
		if ( is_user_logged_in() && ! current_user_can( 'read' ) ) {
			wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
		}

		// Nonce obligatoire (même en public, on garde une signature de lien)
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ufsc_download_' . $attestation_id ) ) {
			wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'ufsc_attestations';

		$attestation = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $attestation_id )
		);

		if ( ! $attestation ) {
			wp_die( __( 'Attestation non trouvée.', 'ufsc-clubs' ) );
		}

		// Si public : on s’arrête là (accès déjà signé par nonce + filtre)
		if ( ! $allow_public ) {

			// Admin / lecture fédé : OK
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'ufsc_manage_read' ) ) {

				$user_club_id = function_exists( 'ufsc_get_user_club_id' ) ? (int) ufsc_get_user_club_id( get_current_user_id() ) : 0;
				$allowed      = false;

				if ( $user_club_id && 'club' === $attestation->target_type && (string) $attestation->target_id === (string) $user_club_id ) {
					$allowed = true;
				} elseif ( $user_club_id && 'region' === $attestation->target_type && class_exists( 'UFSC_Scope' ) ) {
					$club_region = UFSC_Scope::get_club_region( (int) $user_club_id );
					if ( $club_region && (string) $attestation->target_id === (string) $club_region ) {
						$allowed = true;
					}
				} elseif ( 'general' === $attestation->target_type ) {
					$allowed = (bool) $user_club_id; // tout club connecté
				}

				if ( ! $allowed ) {
					wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
				}
			}
		}

		$file_path = self::get_upload_directory() . '/' . $attestation->filename;

		if ( ! file_exists( $file_path ) ) {
			wp_die( __( 'Fichier non trouvé.', 'ufsc-clubs' ) );
		}

		if ( headers_sent() ) {
			wp_die( __( 'Headers already sent, cannot serve file.', 'ufsc-clubs' ) );
		}

		// Nettoyage buffers + no-cache => évite les PDF corrompus / erreurs headers
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();

		$download_name = sanitize_file_name( (string) $attestation->filename );
		if ( '' === $download_name ) {
			$download_name = 'attestation.pdf';
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );

		readfile( $file_path );
		exit;
	}

	/**
	 * Get attestation for specific club/context
	 */
	public static function get_attestation_for_club( $club_id, $type = 'affiliation', $saison = '2025-2026' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ufsc_attestations';

		$attestation = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE type = %s AND target_type = 'club' AND target_id = %s AND saison = %s ORDER BY created_at DESC LIMIT 1",
			$type, $club_id, $saison
		) );

		if ( $attestation ) {
			return self::get_download_url( $attestation->id );
		}

		$club_region = self::get_club_region( $club_id );
		if ( $club_region ) {
			$attestation = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $table_name WHERE type = %s AND target_type = 'region' AND target_id = %s AND saison = %s ORDER BY created_at DESC LIMIT 1",
				$type, $club_region, $saison
			) );

			if ( $attestation ) {
				return self::get_download_url( $attestation->id );
			}
		}

		$attestation = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE type = %s AND target_type = 'general' AND saison = %s ORDER BY created_at DESC LIMIT 1",
			$type, $saison
		) );

		if ( $attestation ) {
			return self::get_download_url( $attestation->id );
		}

		return false;
	}

	/**
	 * Get club region
	 */
	private static function get_club_region( $club_id ) {
		global $wpdb;

		$settings = UFSC_SQL::get_settings();
		$table    = $settings['table_clubs'];

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT region FROM `$table` WHERE id = %d",
			$club_id
		) );
	}

	/**
	 * Redirect with error message
	 */
	private static function redirect_with_error( $message ) {
		wp_redirect( admin_url( 'admin.php?page=ufsc-attestations&error=' . rawurlencode( $message ) ) );
		exit;
	}
}

// Initialize PDF attestations
UFSC_PDF_Attestations::init();
