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
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_upload' ) );
        add_action( 'wp_ajax_ufsc_download_attestation', array( __CLASS__, 'handle_secure_download' ) );
        add_action( 'wp_ajax_nopriv_ufsc_download_attestation', array( __CLASS__, 'handle_secure_download' ) );
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
                    <p><?php echo esc_html( sanitize_text_field( $_GET['error'] ) ); ?></p>
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
                                        <?php echo self::get_clubs_options(); ?>
                                    </select>
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
    private static function get_clubs_options() {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        
        $clubs = $wpdb->get_results( "SELECT id, nom FROM `$table` ORDER BY nom" );
        $options = '';
        
        foreach ( $clubs as $club ) {
            $options .= '<option value="' . esc_attr( $club->id ) . '">' . esc_html( $club->nom ) . '</option>';
        }
        
        return $options;
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

        $attestation_type = sanitize_text_field( $_POST['attestation_type'] );
        $target_type = sanitize_text_field( $_POST['target_type'] );
        $saison = sanitize_text_field( $_POST['saison'] );
        
        $target_id = '';
        if ( $target_type === 'region' ) {
            $target_id = sanitize_text_field( $_POST['target_region'] );
        } elseif ( $target_type === 'club' ) {
            $target_id = sanitize_text_field( $_POST['target_club'] );
        }

        // Validate file
        if ( ! isset( $_FILES['attestation_file'] ) || $_FILES['attestation_file']['error'] !== UPLOAD_ERR_OK ) {
            self::redirect_with_error( __( 'Erreur lors du téléchargement du fichier.', 'ufsc-clubs' ) );
            return;
        }

        $file = $_FILES['attestation_file'];
        
        // Check file type
        $file_type = wp_check_filetype( $file['name'] );
        if ( $file_type['ext'] !== 'pdf' ) {
            self::redirect_with_error( __( 'Seuls les fichiers PDF sont acceptés.', 'ufsc-clubs' ) );
            return;
        }

        // Check file size (5MB max)
        if ( $file['size'] > 5 * 1024 * 1024 ) {
            self::redirect_with_error( __( 'Le fichier est trop volumineux (5 MB max).', 'ufsc-clubs' ) );
            return;
        }

        // Create upload directory
        $upload_dir = self::get_upload_directory();
        if ( ! wp_mkdir_p( $upload_dir ) ) {
            self::redirect_with_error( __( 'Impossible de créer le dossier de téléchargement.', 'ufsc-clubs' ) );
            return;
        }

        // Generate unique filename
        $filename = self::generate_filename( $attestation_type, $target_type, $target_id, $saison );
        $file_path = $upload_dir . '/' . $filename;

        // Move uploaded file
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            self::redirect_with_error( __( 'Erreur lors de la sauvegarde du fichier.', 'ufsc-clubs' ) );
            return;
        }

        // Save file info to database
        self::save_attestation_info( $attestation_type, $target_type, $target_id, $saison, $filename );

        // Success redirect
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
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__( 'Type', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Assignation', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Date ajout', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'ufsc-clubs' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ( $attestations as $attestation ) {
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

        echo '</tbody>';
        echo '</table>';
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
        
        // Create table if it doesn't exist
        self::create_attestations_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'type' => $type,
                'target_type' => $target_type,
                'target_id' => $target_id,
                'saison' => $saison,
                'filename' => $filename,
                'created_at' => current_time( 'mysql' ),
                'created_by' => get_current_user_id()
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
        );
    }

    /**
     * Create attestations table
     */
    private static function create_attestations_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ufsc_attestations';
        
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
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
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
            admin_url( 'admin-ajax.php?action=ufsc_download_attestation&id=' . $attestation_id ),
            'ufsc_download_' . $attestation_id
        );
    }

    /**
     * Handle secure download
     */
    public static function handle_secure_download() {
        if ( ! isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
            wp_die( __( 'Paramètres manquants.', 'ufsc-clubs' ) );
        }

        $attestation_id = absint( $_GET['id'] );
        $nonce          = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

        if ( ! wp_verify_nonce( $nonce, 'ufsc_download_' . $attestation_id ) ) {
            wp_die( __( 'Nonce de sécurité invalide.', 'ufsc-clubs' ) );
        }

        // Get attestation info
        global $wpdb;
        $table_name = $wpdb->prefix . 'ufsc_attestations';
        
        $attestation = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $attestation_id
        ) );

        if ( ! $attestation ) {
            wp_die( __( 'Attestation non trouvée.', 'ufsc-clubs' ) );
        }

        $file_path = self::get_upload_directory() . '/' . $attestation->filename;
        
        if ( ! file_exists( $file_path ) ) {
            wp_die( __( 'Fichier non trouvé.', 'ufsc-clubs' ) );
        }

        // Serve file
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $attestation->filename . '"' );
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
        
        // Try to find specific club attestation first
        $attestation = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE type = %s AND target_type = 'club' AND target_id = %s AND saison = %s ORDER BY created_at DESC LIMIT 1",
            $type, $club_id, $saison
        ) );
        
        if ( $attestation ) {
            return self::get_download_url( $attestation->id );
        }
        
        // Try region-specific attestation
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
        
        // Try general attestation
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
        $table = $settings['table_clubs'];
        
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT region FROM `$table` WHERE id = %d",
            $club_id
        ) );
    }

    /**
     * Redirect with error message
     */
    private static function redirect_with_error( $message ) {
        wp_redirect( admin_url( 'admin.php?page=ufsc-attestations&error=' . urlencode( $message ) ) );
        exit;
    }
}

// Initialize PDF attestations
UFSC_PDF_Attestations::init();