<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Frontend shortcodes for UFSC Gestion
 * Provides secure, nonce-protected shortcodes for club management
 */
class UFSC_Frontend_Shortcodes {

    /**
     * Register all frontend shortcodes
     */
    public static function register() {
        add_shortcode( 'ufsc_club_dashboard', array( __CLASS__, 'render_club_dashboard' ) );
        add_shortcode( 'ufsc_club_licences', array( __CLASS__, 'render_club_licences' ) );
        add_shortcode( 'ufsc_club_stats', array( __CLASS__, 'render_club_stats' ) );
        add_shortcode( 'ufsc_club_profile', array( __CLASS__, 'render_club_profile' ) );
        add_shortcode( 'ufsc_add_licence', array( __CLASS__, 'render_add_licence' ) );
    }

    /**
     * Render the main club dashboard with 4 sections
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_club_dashboard( $atts = array() ) {
        wp_enqueue_style( 'ufsc-front', UFSC_CL_URL . 'assets/css/ufsc-front.css', array(), UFSC_CL_VERSION );
        $atts = shortcode_atts( array(
            'show_sections' => 'licences,stats,profile,add_licence'
        ), $atts );

        if ( ! is_user_logged_in() ) {
            return '<div class="ufsc-message ufsc-error">' . 
                   esc_html__( 'Vous devez être connecté pour accéder au tableau de bord.', 'ufsc-clubs' ) . 
                   '</div>';
        }

        $user_id = get_current_user_id();
        $club_id = self::get_user_club_id( $user_id );

        if ( ! $club_id ) {
            return '<div class="ufsc-message ufsc-error">' . 
                   esc_html__( 'Aucun club associé à votre compte.', 'ufsc-clubs' ) . 
                   '</div>';
        }

        $sections = explode( ',', $atts['show_sections'] );
        
        ob_start();
        ?>
        <div class="ufsc-club-dashboard" id="ufsc-dashboard">
            <div class="ufsc-dashboard-header">
                <h2><?php esc_html_e( 'Tableau de bord - Mon Club', 'ufsc-clubs' ); ?></h2>
                <p class="ufsc-dashboard-subtitle">
                    <?php 
                    $club_name = self::get_club_name( $club_id );
                    if ( $club_name ) {
                        echo sprintf( esc_html__( 'Club: %s', 'ufsc-clubs' ), esc_html( $club_name ) );
                    }
                    ?>
                </p>
                <?php if ( in_array( 'add_licence', $sections ) ): ?>
                    <div class="ufsc-dashboard-actions">
                        <a href="#ufsc-section-add_licence" class="ufsc-btn ufsc-btn-primary" onclick="document.querySelector('[data-section=\"add_licence\"]').click(); return false;">
                            <?php esc_html_e( 'Ajouter une licence', 'ufsc-clubs' ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ufsc-dashboard-nav">
                <?php if ( in_array( 'licences', $sections ) ): ?>
                    <button class="ufsc-nav-btn active" data-section="licences">
                        <?php esc_html_e( 'Mes Licences', 'ufsc-clubs' ); ?>
                    </button>
                <?php endif; ?>
                <?php if ( in_array( 'stats', $sections ) ): ?>
                    <button class="ufsc-nav-btn" data-section="stats">
                        <?php esc_html_e( 'Statistiques', 'ufsc-clubs' ); ?>
                    </button>
                <?php endif; ?>
                <?php if ( in_array( 'profile', $sections ) ): ?>
                    <button class="ufsc-nav-btn" data-section="profile">
                        <?php esc_html_e( 'Mon Club', 'ufsc-clubs' ); ?>
                    </button>
                <?php endif; ?>
                <?php if ( in_array( 'add_licence', $sections ) ): ?>
                    <button class="ufsc-nav-btn" data-section="add_licence">
                        <?php esc_html_e( 'Ajouter une Licence', 'ufsc-clubs' ); ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="ufsc-dashboard-content">
                <?php if ( in_array( 'licences', $sections ) ): ?>
                    <div id="ufsc-section-licences" class="ufsc-dashboard-section active">
                        <?php echo self::render_club_licences( array( 'club_id' => $club_id ) ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( in_array( 'stats', $sections ) ): ?>
                    <div id="ufsc-section-stats" class="ufsc-dashboard-section">
                        <?php echo self::render_club_stats( array( 'club_id' => $club_id ) ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( in_array( 'profile', $sections ) ): ?>
                    <div id="ufsc-section-profile" class="ufsc-dashboard-section">
                        <?php echo self::render_club_profile( array( 'club_id' => $club_id ) ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( in_array( 'add_licence', $sections ) ): ?>
                    <div id="ufsc-section-add_licence" class="ufsc-dashboard-section">
                        <?php echo self::render_add_licence( array( 'club_id' => $club_id ) ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.ufsc-nav-btn').on('click', function() {
                var section = $(this).data('section');
                
                // Update nav
                $('.ufsc-nav-btn').removeClass('active');
                $(this).addClass('active');
                
                // Show section
                $('.ufsc-dashboard-section').removeClass('active');
                $('#ufsc-section-' + section).addClass('active');
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render club licences section
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_club_licences( $atts = array() ) {
        wp_enqueue_style( 'ufsc-front', UFSC_CL_URL . 'assets/css/ufsc-front.css', array(), UFSC_CL_VERSION );
        $atts = shortcode_atts( array(
            'club_id' => 0,
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => '',
            'sort' => 'created_desc'
        ), $atts );

        if ( ! $atts['club_id'] && is_user_logged_in() ) {
            $atts['club_id'] = self::get_user_club_id( get_current_user_id() );
        }

        if ( ! $atts['club_id'] ) {
            return '<div class="ufsc-message ufsc-error">' . 
                   esc_html__( 'Club non trouvé.', 'ufsc-clubs' ) . 
                   '</div>';
        }

        // Handle pagination and filters from URL
        if ( isset( $_GET['ufsc_page'] ) ) {
            $atts['page'] = max( 1, intval( $_GET['ufsc_page'] ) );
        }
        if ( isset( $_GET['ufsc_status'] ) ) {
            $atts['status'] = sanitize_text_field( $_GET['ufsc_status'] );
        }
        if ( isset( $_GET['ufsc_search'] ) ) {
            $atts['search'] = sanitize_text_field( $_GET['ufsc_search'] );
        }
        if ( isset( $_GET['ufsc_sort'] ) ) {
            $atts['sort'] = sanitize_text_field( $_GET['ufsc_sort'] );
        }

        $licences = self::get_club_licences( $atts['club_id'], $atts );
        $total_count = self::get_club_licences_count( $atts['club_id'], $atts );
        $total_pages = ceil( $total_count / $atts['per_page'] );

        ob_start();
        ?>
        <div class="ufsc-licences-section">
            <div class="ufsc-section-header">
                <h3><?php esc_html_e( 'Mes Licences', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-section-actions">
                    <a href="<?php echo esc_url( add_query_arg( 'ufsc_export', 'csv' ) ); ?>" 
                       class="ufsc-btn ufsc-btn-secondary">
                        <?php esc_html_e( 'Exporter CSV', 'ufsc-clubs' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'ufsc_export', 'xlsx' ) ); ?>" 
                       class="ufsc-btn ufsc-btn-secondary">
                        <?php esc_html_e( 'Exporter Excel', 'ufsc-clubs' ); ?>
                    </a>
                    <button class="ufsc-btn ufsc-btn-secondary" onclick="document.getElementById('ufsc-import-modal').style.display='block'">
                        <?php esc_html_e( 'Importer CSV', 'ufsc-clubs' ); ?>
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="ufsc-licences-filters">
                <form method="get" class="ufsc-filters-form">
                    <div class="ufsc-notices" aria-live="polite"></div>
                    <div class="ufsc-filter-group">
                        <label for="ufsc_search"><?php esc_html_e( 'Recherche:', 'ufsc-clubs' ); ?></label>
                        <input type="text" id="ufsc_search" name="ufsc_search" 
                               value="<?php echo esc_attr( $atts['search'] ); ?>"
                               placeholder="<?php esc_attr_e( 'Nom, prénom, email...', 'ufsc-clubs' ); ?>">
                    </div>
                    
                    <div class="ufsc-filter-group">
                        <label for="ufsc_status"><?php esc_html_e( 'Statut:', 'ufsc-clubs' ); ?></label>
                        <select id="ufsc_status" name="ufsc_status">
                            <option value=""><?php esc_html_e( 'Tous', 'ufsc-clubs' ); ?></option>
                            <option value="brouillon" <?php selected( $atts['status'], 'brouillon' ); ?>>
                                <?php esc_html_e( 'Brouillon', 'ufsc-clubs' ); ?>
                            </option>
                            <option value="paid" <?php selected( $atts['status'], 'paid' ); ?>>
                                <?php esc_html_e( 'Payée', 'ufsc-clubs' ); ?>
                            </option>
                            <option value="validated" <?php selected( $atts['status'], 'validated' ); ?>>
                                <?php esc_html_e( 'Validée', 'ufsc-clubs' ); ?>
                            </option>
                            <option value="applied" <?php selected( $atts['status'], 'applied' ); ?>>
                                <?php esc_html_e( 'Appliquée', 'ufsc-clubs' ); ?>
                            </option>
                        </select>
                    </div>

                    <div class="ufsc-filter-group">
                        <label for="ufsc_sort"><?php esc_html_e( 'Tri:', 'ufsc-clubs' ); ?></label>
                        <select id="ufsc_sort" name="ufsc_sort">
                            <option value="created_desc" <?php selected( $atts['sort'], 'created_desc' ); ?>>
                                <?php esc_html_e( 'Plus récent d\'abord', 'ufsc-clubs' ); ?>
                            </option>
                            <option value="created_asc" <?php selected( $atts['sort'], 'created_asc' ); ?>>
                                <?php esc_html_e( 'Plus ancien d\'abord', 'ufsc-clubs' ); ?>
                            </option>
                            <option value="name_asc" <?php selected( $atts['sort'], 'name_asc' ); ?>>
                                <?php esc_html_e( 'Nom A-Z', 'ufsc-clubs' ); ?>
                            </option>
                            <option value="name_desc" <?php selected( $atts['sort'], 'name_desc' ); ?>>
                                <?php esc_html_e( 'Nom Z-A', 'ufsc-clubs' ); ?>
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="ufsc-btn ufsc-btn-primary">
                        <?php esc_html_e( 'Filtrer', 'ufsc-clubs' ); ?>
                    </button>
                    
                    <a href="?" class="ufsc-btn ufsc-btn-secondary">
                        <?php esc_html_e( 'Réinitialiser', 'ufsc-clubs' ); ?>
                    </a>
                </form>
            </div>

            <!-- Licences List -->
            <div class="ufsc-licences-list">
                <?php if ( empty( $licences ) ): ?>
                    <div class="ufsc-message ufsc-info">
                        <?php esc_html_e( 'Aucune licence trouvée.', 'ufsc-clubs' ); ?>
                    </div>
                <?php else: ?>
                    <table class="ufsc-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Prénom', 'ufsc-clubs' ); ?></th>
                                <th scope="col" class="ufsc-hide-mobile"><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Statut', 'ufsc-clubs' ); ?></th>
                                <th scope="col" class="ufsc-hide-mobile"><?php esc_html_e( 'Date création', 'ufsc-clubs' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Actions', 'ufsc-clubs' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $licences as $licence ): ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html( $licence->nom ?? '' ); ?></th>
                                    <td><?php echo esc_html( $licence->prenom ?? '' ); ?></td>
                                    <td class="ufsc-hide-mobile"><?php echo esc_html( $licence->email ?? '' ); ?></td>
                                    <td>
                                        <span class="ufsc-badge <?php echo esc_attr( self::get_licence_status_badge_class( $licence->statut ?? 'brouillon' ) ); ?>">
                                            <?php echo esc_html( self::get_licence_status_label( $licence->statut ?? 'brouillon' ) ); ?>
                                        </span>
                                    </td>
                                    <td class="ufsc-hide-mobile"><?php echo esc_html( $licence->date_creation ?? '' ); ?></td>
                                    <td class="ufsc-actions" aria-label="<?php esc_attr_e( 'Actions', 'ufsc-clubs' ); ?>">
                                        <div class="ufsc-row-actions">
                                            <?php if ( ! self::is_validated_licence( $licence->id ?? 0 ) ): ?>
                                                <a href="<?php echo esc_url( add_query_arg( 'edit_licence', $licence->id ?? 0 ) ); ?>"
                                                   class="ufsc-btn ufsc-btn-small"
                                                   aria-label="<?php esc_attr_e( 'Modifier la licence', 'ufsc-clubs' ); ?>">
                                                    <?php esc_html_e( 'Modifier', 'ufsc-clubs' ); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="ufsc-text-muted">
                                                    <?php esc_html_e( 'Validée - Non modifiable', 'ufsc-clubs' ); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ): ?>
                <div class="ufsc-pagination">
                    <?php 
                    echo self::render_pagination( $atts['page'], $total_pages, array(
                        'ufsc_status' => $atts['status'],
                        'ufsc_search' => $atts['search'],
                        'ufsc_sort' => $atts['sort']
                    ) );
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Import Modal -->
        <?php echo self::render_import_modal( $atts['club_id'] ); ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render club statistics section
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_club_stats( $atts = array() ) {
        $atts = shortcode_atts( array(
            'club_id' => 0,
            'season' => ''
        ), $atts );

        if ( ! $atts['club_id'] && is_user_logged_in() ) {
            $atts['club_id'] = self::get_user_club_id( get_current_user_id() );
        }

        if ( ! $atts['club_id'] ) {
            return '<div class="ufsc-message ufsc-error">' . 
                   esc_html__( 'Club non trouvé.', 'ufsc-clubs' ) . 
                   '</div>';
        }

        if ( empty( $atts['season'] ) ) {
            $wc_settings = ufsc_get_woocommerce_settings();
            $atts['season'] = $wc_settings['season'];
        }

        $stats = self::get_club_stats( $atts['club_id'], $atts['season'] );

        ob_start();
        ?>
        <div class="ufsc-stats-section">
            <div class="ufsc-section-header">
                <h3><?php esc_html_e( 'Statistiques', 'ufsc-clubs' ); ?></h3>
                <p class="ufsc-season-info">
                    <?php echo sprintf( esc_html__( 'Saison: %s', 'ufsc-clubs' ), esc_html( $atts['season'] ) ); ?>
                </p>
            </div>

            <div class="ufsc-stats-kpi">
                <div class="ufsc-kpi-card">
                    <div class="ufsc-kpi-value"><?php echo esc_html( $stats['total_licences'] ); ?></div>
                    <div class="ufsc-kpi-label"><?php esc_html_e( 'Total Licences', 'ufsc-clubs' ); ?></div>
                </div>
                
                <div class="ufsc-kpi-card">
                    <div class="ufsc-kpi-value"><?php echo esc_html( $stats['paid_licences'] ); ?></div>
                    <div class="ufsc-kpi-label"><?php esc_html_e( 'Licences Payées', 'ufsc-clubs' ); ?></div>
                </div>
                
                <div class="ufsc-kpi-card">
                    <div class="ufsc-kpi-value"><?php echo esc_html( $stats['validated_licences'] ); ?></div>
                    <div class="ufsc-kpi-label"><?php esc_html_e( 'Licences Validées', 'ufsc-clubs' ); ?></div>
                </div>
                
                <div class="ufsc-kpi-card">
                    <div class="ufsc-kpi-value"><?php echo esc_html( $stats['quota_remaining'] ); ?></div>
                    <div class="ufsc-kpi-label"><?php esc_html_e( 'Quota Restant', 'ufsc-clubs' ); ?></div>
                </div>
            </div>

            <div class="ufsc-stats-chart">
                <h4><?php esc_html_e( 'Évolution des licences', 'ufsc-clubs' ); ?></h4>
                <div class="ufsc-chart-placeholder">
                    <p><?php esc_html_e( 'Graphique à implémenter avec Chart.js ou équivalent', 'ufsc-clubs' ); ?></p>
                    <!-- TODO: Add actual chart implementation -->
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render club profile section with all required fields organized in sections
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_club_profile( $atts = array() ) {
        wp_enqueue_style( 'ufsc-front', UFSC_CL_URL . 'assets/css/ufsc-front.css', array(), UFSC_CL_VERSION );
        $atts = shortcode_atts( array(
            'club_id' => 0
        ), $atts );

        if ( ! $atts['club_id'] && is_user_logged_in() ) {
            $atts['club_id'] = self::get_user_club_id( get_current_user_id() );
        }

        if ( ! $atts['club_id'] ) {
            return '<div class="ufsc-message ufsc-error">' . 
                   esc_html__( 'Club non trouvé.', 'ufsc-clubs' ) . 
                   '</div>';
        }

        $club = self::get_club_data( $atts['club_id'] );

        $is_validated = self::is_validated_club( $atts['club_id'] );
        $is_admin = current_user_can( 'manage_options' );

        if ( ! $club ) {
            return '<div class="ufsc-message ufsc-error">' . 
                   esc_html__( 'Données du club non trouvées.', 'ufsc-clubs' ) . 
                   '</div>';
        }
        
        $is_admin = current_user_can( 'manage_options' );
        $can_edit = UFSC_CL_Permissions::ufsc_user_can_edit_club( $atts['club_id'] );
        
        if ( ! $can_edit ) {
            return '<div class="ufsc-message ufsc-error">' . 
                   esc_html__( 'Vous n\'avez pas les permissions pour voir ce club.', 'ufsc-clubs' ) . 
                   '</div>';
        }

        
        // Handle form submission
        if ( isset( $_POST['ufsc_update_club'] ) && wp_verify_nonce( $_POST['ufsc_nonce'], 'ufsc_update_club' ) ) {
            $result = self::handle_club_update( $atts['club_id'], $_POST );
            if ( $result['success'] ) {
                echo '<div class="ufsc-message ufsc-success">' . esc_html( $result['message'] ) . '</div>';
                $club = self::get_club_data( $atts['club_id'] ); // Refresh data
            } else {
                echo '<div class="ufsc-message ufsc-error">' . esc_html( $result['message'] ) . '</div>';
            }
        }

        ob_start();
        ?>
        <div class="ufsc-club-profile">
            <!-- // UFSC: Enhanced club profile with sections and cards -->
            <div class="ufsc-section-header">
                <h3><?php esc_html_e( 'Profil du Club', 'ufsc-clubs' ); ?></h3>
                <?php if ( ! $is_admin ): ?>
                    <p class="ufsc-permission-notice">
                        <?php esc_html_e( 'Seuls l\'email et le téléphone peuvent être modifiés', 'ufsc-clubs' ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ufsc-club-form ufsc-club-profile">
                <div class="ufsc-notices" aria-live="polite"></div>
                <input type="hidden" name="action" value="ufsc_save_club">
                <?php wp_nonce_field( 'ufsc_save_club', '_wpnonce' ); ?>
                
                <div class="ufsc-grid">
                    <!-- // UFSC: Identité du club -->
                    <div class="ufsc-card ufsc-section">
                        <h4><?php esc_html_e( 'Identité du club', 'ufsc-clubs' ); ?></h4>
                        
                        <?php self::render_field( 'nom', $club, __( 'Nom du club', 'ufsc-clubs' ), 'text', true, $is_admin ); ?>
                        <?php self::render_field( 'region', $club, __( 'Région', 'ufsc-clubs' ), 'text', true, $is_admin ); ?>
                        <?php self::render_field( 'num_affiliation', $club, __( 'N° d\'affiliation', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'statut', $club, __( 'Statut', 'ufsc-clubs' ), 'text', true, false ); ?>
                    </div>

                    <!-- // UFSC: Coordonnées -->
                    <div class="ufsc-card ufsc-section">
                        <h4><?php esc_html_e( 'Coordonnées', 'ufsc-clubs' ); ?></h4>
                        
                        <?php self::render_field( 'adresse', $club, __( 'Adresse', 'ufsc-clubs' ), 'textarea', false, $is_admin ); ?>
                        <?php self::render_field( 'code_postal', $club, __( 'Code postal', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'ville', $club, __( 'Ville', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'email', $club, __( 'Email', 'ufsc-clubs' ), 'email', false, true ); ?>
                        <?php self::render_field( 'telephone', $club, __( 'Téléphone', 'ufsc-clubs' ), 'tel', false, true ); ?>
                    </div>
                </div>

                <!-- Legal Section -->
                <fieldset class="ufsc-form-section">
                    <legend><?php esc_html_e( 'Informations légales', 'ufsc-clubs' ); ?></legend>
                    
                    <?php self::render_field( 'siren', $club, __( 'SIREN', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'ape', $club, __( 'APE', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'ccn', $club, __( 'CCN', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'ancv', $club, __( 'ANCV', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'rna_number', $club, __( 'Numéro RNA', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'num_declaration', $club, __( 'N° déclaration', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'date_declaration', $club, __( 'Date déclaration', 'ufsc-clubs' ), 'date', false, $is_admin ); ?>
                </fieldset>

                <!-- Staff Section -->
                <fieldset class="ufsc-form-section">
                    <legend><?php esc_html_e( 'Dirigeants', 'ufsc-clubs' ); ?></legend>
                    
                    <h5><?php esc_html_e( 'Président', 'ufsc-clubs' ); ?></h5>
                    <?php self::render_field( 'president_prenom', $club, __( 'Prénom', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'president_nom', $club, __( 'Nom', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'president_tel', $club, __( 'Téléphone', 'ufsc-clubs' ), 'tel', false, $is_admin ); ?>
                    <?php self::render_field( 'president_email', $club, __( 'Email', 'ufsc-clubs' ), 'email', false, $is_admin ); ?>

                    <h5><?php esc_html_e( 'Secrétaire', 'ufsc-clubs' ); ?></h5>
                    <?php self::render_field( 'secretaire_prenom', $club, __( 'Prénom', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'secretaire_nom', $club, __( 'Nom', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'secretaire_tel', $club, __( 'Téléphone', 'ufsc-clubs' ), 'tel', false, $is_admin ); ?>
                    <?php self::render_field( 'secretaire_email', $club, __( 'Email', 'ufsc-clubs' ), 'email', false, $is_admin ); ?>

                    <h5><?php esc_html_e( 'Trésorier', 'ufsc-clubs' ); ?></h5>
                    <?php self::render_field( 'tresorier_prenom', $club, __( 'Prénom', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'tresorier_nom', $club, __( 'Nom', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'tresorier_tel', $club, __( 'Téléphone', 'ufsc-clubs' ), 'tel', false, $is_admin ); ?>
                    <?php self::render_field( 'tresorier_email', $club, __( 'Email', 'ufsc-clubs' ), 'email', false, $is_admin ); ?>

                    <h5><?php esc_html_e( 'Entraîneur', 'ufsc-clubs' ); ?></h5>
                    <?php self::render_field( 'entraineur_prenom', $club, __( 'Prénom', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'entraineur_nom', $club, __( 'Nom', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'entraineur_tel', $club, __( 'Téléphone', 'ufsc-clubs' ), 'tel', false, $is_admin ); ?>
                    <?php self::render_field( 'entraineur_email', $club, __( 'Email', 'ufsc-clubs' ), 'email', false, $is_admin ); ?>
                </fieldset>

                <!-- Social Section -->
                <fieldset class="ufsc-form-section">
                    <legend><?php esc_html_e( 'Réseaux sociaux', 'ufsc-clubs' ); ?></legend>
                    
                    <?php self::render_field( 'url_site', $club, __( 'Site web', 'ufsc-clubs' ), 'url', false, $is_admin ); ?>
                    <?php self::render_field( 'url_facebook', $club, __( 'Facebook', 'ufsc-clubs' ), 'url', false, $is_admin ); ?>
                    <?php self::render_field( 'url_instagram', $club, __( 'Instagram', 'ufsc-clubs' ), 'url', false, $is_admin ); ?>
                </fieldset>

                <!-- Numbers/Dates Section -->
                <fieldset class="ufsc-form-section">
                    <legend><?php esc_html_e( 'Chiffres et dates', 'ufsc-clubs' ); ?></legend>
                    
                    <?php self::render_field( 'num_affiliation', $club, __( 'N° d\'affiliation', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                    <?php self::render_field( 'quota_licences', $club, __( 'Quota licences', 'ufsc-clubs' ), 'number', false, $is_admin ); ?>
                    <?php self::render_field( 'date_creation', $club, __( 'Date de création', 'ufsc-clubs' ), 'date', false, $is_admin ); ?>
                    <?php self::render_field( 'date_affiliation', $club, __( 'Date d\'affiliation', 'ufsc-clubs' ), 'date', false, $is_admin ); ?>
                    <?php self::render_field( 'responsable_id', $club, __( 'ID responsable', 'ufsc-clubs' ), 'number', true, false ); ?>
                </fieldset>

                <!-- Distribution Section -->
                <fieldset class="ufsc-form-section">
                    <legend><?php esc_html_e( 'Distribution', 'ufsc-clubs' ); ?></legend>
                    
                    <?php self::render_field( 'precision_distribution', $club, __( 'Précision distribution', 'ufsc-clubs' ), 'textarea', false, $is_admin ); ?>
                </fieldset>

                <!-- // UFSC: Documents Section - 6 mandatory documents -->
                <fieldset class="ufsc-form-section">
                    <legend><?php esc_html_e( 'Mes documents', 'ufsc-clubs' ); ?></legend>
                    
                    <?php
                    // // UFSC: 6 mandatory documents as per requirements
                    $mandatory_documents = array(
                        'doc_statuts' => __( 'Statuts', 'ufsc-clubs' ),
                        'doc_recepisse' => __( 'Récépissé', 'ufsc-clubs' ),
                        'doc_jo' => __( 'Journal Officiel', 'ufsc-clubs' ),
                        'doc_pv_ag' => __( 'PV Assemblée Générale', 'ufsc-clubs' ),
                        'doc_cer' => __( 'CER', 'ufsc-clubs' ),
                        'doc_attestation_cer' => __( 'Attestation CER', 'ufsc-clubs' )
                    );
                    ?>
                    
                    <div class="ufsc-grid ufsc-documents-grid">
                        <?php foreach ( $mandatory_documents as $doc_key => $doc_label ): ?>
                            <div class="ufsc-card ufsc-document-card">
                                <div class="ufsc-document-header">
                                    <h5><?php echo esc_html( $doc_label ); ?></h5>
                                    <span class="ufsc-document-status">
                                        <?php
                                        $doc_value = isset( $club->$doc_key ) ? $club->$doc_key : '';
                                        if ( ! empty( $doc_value ) ):
                                        ?>
                                            <span class="ufsc-badge ufsc-badge-success" aria-label="<?php esc_attr_e( 'Transmis', 'ufsc-clubs' ); ?>">✅</span>
                                        <?php else: ?>
                                            <span class="ufsc-badge ufsc-badge-pending" aria-label="<?php esc_attr_e( 'En attente', 'ufsc-clubs' ); ?>">⏳</span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="ufsc-document-content">
                                    <?php if ( ! empty( $doc_value ) ): ?>
                                        <div class="ufsc-document-current">
                                            <p class="ufsc-document-name"><?php echo esc_html( basename( $doc_value ) ); ?></p>
                                            <div class="ufsc-document-actions">
                                                <a href="<?php echo esc_url( $doc_value ); ?>" target="_blank" class="ufsc-btn-small">
                                                    <?php esc_html_e( 'Voir', 'ufsc-clubs' ); ?>
                                                </a>
                                                <a href="<?php echo esc_url( $doc_value ); ?>" download class="ufsc-btn-small">
                                                    <?php esc_html_e( 'Télécharger', 'ufsc-clubs' ); ?>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ( $can_edit ): ?>
                                        <div class="ufsc-document-upload">
                                            <input type="file"
                                                   id="<?php echo esc_attr( $doc_key ); ?>_upload"
                                                   name="<?php echo esc_attr( $doc_key ); ?>_upload"
                                                   accept=".pdf,.jpg,.jpeg,.png"
                                                   class="ufsc-file-input">
                                            <label for="<?php echo esc_attr( $doc_key ); ?>_upload" class="ufsc-upload-label">
                                                <?php if ( ! empty( $doc_value ) ): ?>
                                                    <?php esc_html_e( 'Remplacer le document', 'ufsc-clubs' ); ?>
                                                <?php else: ?>
                                                    <?php esc_html_e( 'Choisir un fichier', 'ufsc-clubs' ); ?>
                                                <?php endif; ?>
                                            </label>
                                            <p class="ufsc-help-text">
                                                <?php esc_html_e( 'Formats: PDF, JPG, PNG - Max 5MB', 'ufsc-clubs' ); ?>
                                            </p>
                                            <div class="ufsc-upload-feedback" role="status" aria-live="polite"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                
                <!-- // UFSC: Submit section -->
                <div class="ufsc-form-actions">
                    <?php if ( $can_edit ): ?>
                        <button type="submit" name="ufsc_save_club" class="ufsc-btn ufsc-btn-primary">
                            <?php esc_html_e( 'Mettre à jour le club', 'ufsc-clubs' ); ?>
                        </button>
                    <?php endif; ?>
                </div>

            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render add licence section
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_add_licence( $atts = array() ) {
        $atts = shortcode_atts( array(
            'club_id' => 0
        ), $atts );

        if ( ! $atts['club_id'] && is_user_logged_in() ) {
            $atts['club_id'] = self::get_user_club_id( get_current_user_id() );
        }

        if ( ! $atts['club_id'] ) {
            return '<div class="ufsc-message ufsc-error">' . 
                   esc_html__( 'Club non trouvé.', 'ufsc-clubs' ) . 
                   '</div>';
        }

        // Check quota
        $quota_info = self::get_club_quota_info( $atts['club_id'] );
        
        // Handle form submission
        if ( isset( $_POST['ufsc_add_licence'] ) && wp_verify_nonce( $_POST['ufsc_nonce'], 'ufsc_add_licence' ) ) {
            $result = self::handle_licence_creation( $atts['club_id'], $_POST );
            if ( $result['success'] ) {
                echo '<div class="ufsc-message ufsc-success">' . esc_html( $result['message'] ) . '</div>';
                if ( isset( $result['payment_url'] ) ) {
                    echo '<div class="ufsc-message ufsc-info">';
                    echo '<p>' . esc_html__( 'Quota atteint. Paiement requis:', 'ufsc-clubs' ) . '</p>';
                    echo '<a href="' . esc_url( $result['payment_url'] ) . '" class="ufsc-btn ufsc-btn-primary">';
                    echo esc_html__( 'Procéder au paiement', 'ufsc-clubs' );
                    echo '</a>';
                    echo '</div>';
                }
            } else {
                echo '<div class="ufsc-message ufsc-error">' . esc_html( $result['message'] ) . '</div>';
            }
        }

        ob_start();
        ?>
        <div class="ufsc-add-licence-section">
            <div class="ufsc-section-header">
                <h3><?php esc_html_e( 'Ajouter une Licence', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-quota-info">
                    <p>
                        <?php echo sprintf( 
                            esc_html__( 'Quota disponible: %d / %d', 'ufsc-clubs' ), 
                            $quota_info['remaining'], 
                            $quota_info['total'] 
                        ); ?>
                    </p>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-licence-form">
                <div class="ufsc-notices" aria-live="polite"></div>
                <input type="hidden" name="action" value="ufsc_save_licence">
                <?php wp_nonce_field( 'ufsc_save_licence', '_wpnonce' ); ?>
                
                <!-- // UFSC: Enhanced form structure with conditional fields -->
                <div class="ufsc-grid">
                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Informations personnelles', 'ufsc-clubs' ); ?></h4>
                        
                        <div class="ufsc-form-field">
                            <label for="nom"><?php esc_html_e( 'Nom *', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="nom" name="nom" required>
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="prenom"><?php esc_html_e( 'Prénom *', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="prenom" name="prenom" required>
                        </div>

                        <div class="ufsc-form-field">
                            <label for="email"><?php esc_html_e( 'Email *', 'ufsc-clubs' ); ?></label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="telephone"><?php esc_html_e( 'Téléphone', 'ufsc-clubs' ); ?></label>
                            <input type="tel" id="telephone" name="telephone">
                        </div>

                        <div class="ufsc-form-field">
                            <label for="date_naissance"><?php esc_html_e( 'Date de naissance *', 'ufsc-clubs' ); ?></label>
                            <input type="date" id="date_naissance" name="date_naissance" required>
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="sexe"><?php esc_html_e( 'Sexe *', 'ufsc-clubs' ); ?></label>
                            <select id="sexe" name="sexe" required>
                                <option value=""><?php esc_html_e( 'Sélectionner', 'ufsc-clubs' ); ?></option>
                                <option value="M"><?php esc_html_e( 'Homme', 'ufsc-clubs' ); ?></option>
                                <option value="F"><?php esc_html_e( 'Femme', 'ufsc-clubs' ); ?></option>
                                <option value="Autre"><?php esc_html_e( 'Autre', 'ufsc-clubs' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Adresse', 'ufsc-clubs' ); ?></h4>
                        
                        <div class="ufsc-form-field">
                            <label for="adresse"><?php esc_html_e( 'Adresse complète', 'ufsc-clubs' ); ?></label>
                            <textarea id="adresse" name="adresse" rows="3"></textarea>
                        </div>

                        <div class="ufsc-form-field">
                            <label for="ville"><?php esc_html_e( 'Ville', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="ville" name="ville">
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="code_postal"><?php esc_html_e( 'Code postal', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="code_postal" name="code_postal" pattern="[0-9]{5}" maxlength="5">
                        </div>
                    </div>
                </div>

                <div class="ufsc-grid">
                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Rôle et activité', 'ufsc-clubs' ); ?></h4>
                        
                        <div class="ufsc-form-field">
                            <label for="role"><?php esc_html_e( 'Rôle dans le club', 'ufsc-clubs' ); ?></label>
                            <select id="role" name="role">
                                <option value=""><?php esc_html_e( 'Sélectionner', 'ufsc-clubs' ); ?></option>
                                <option value="president"><?php esc_html_e( 'Président', 'ufsc-clubs' ); ?></option>
                                <option value="secretaire"><?php esc_html_e( 'Secrétaire', 'ufsc-clubs' ); ?></option>
                                <option value="tresorier"><?php esc_html_e( 'Trésorier', 'ufsc-clubs' ); ?></option>
                                <option value="entraineur"><?php esc_html_e( 'Entraîneur', 'ufsc-clubs' ); ?></option>
                                <option value="adherent"><?php esc_html_e( 'Adhérent', 'ufsc-clubs' ); ?></option>
                            </select>
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="competition"><?php esc_html_e( 'Type de pratique', 'ufsc-clubs' ); ?></label>
                            <select id="competition" name="competition">
                                <option value="0"><?php esc_html_e( 'Loisir', 'ufsc-clubs' ); ?></option>
                                <option value="1"><?php esc_html_e( 'Compétition', 'ufsc-clubs' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Licence antérieure', 'ufsc-clubs' ); ?></h4>
                        <p class="ufsc-help-text"><?php esc_html_e( 'Si le licencié possède déjà un numéro de licence', 'ufsc-clubs' ); ?></p>
                        
                        <!-- // UFSC: Conditional field with toggle -->
                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="has_license_number" name="has_license_number" value="1" class="ufsc-toggle">
                                <?php esc_html_e( 'Possède un numéro de licence antérieur', 'ufsc-clubs' ); ?>
                            </label>
                        </div>
                        
                        <div class="ufsc-form-field ufsc-conditional-field" data-depends="has_license_number">
                            <label for="numero_licence"><?php esc_html_e( 'Numéro de licence', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="numero_licence" name="numero_licence">
                        </div>
                    </div>
                </div>

                <div class="ufsc-grid">
                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Réductions et identifiants', 'ufsc-clubs' ); ?></h4>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="reduction_benevole" name="reduction_benevole" value="1" class="ufsc-toggle">
                                <?php esc_html_e( 'Réduction bénévole', 'ufsc-clubs' ); ?>
                            </label>
                        </div>
                        <div class="ufsc-form-field ufsc-conditional-field" data-depends="reduction_benevole">
                            <label for="reduction_benevole_num"><?php esc_html_e( 'Numéro bénévole', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="reduction_benevole_num" name="reduction_benevole_num">
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="reduction_postier" name="reduction_postier" value="1" class="ufsc-toggle">
                                <?php esc_html_e( 'Réduction postier', 'ufsc-clubs' ); ?>
                            </label>
                        </div>
                        <div class="ufsc-form-field ufsc-conditional-field" data-depends="reduction_postier">
                            <label for="reduction_postier_num"><?php esc_html_e( 'Matricule postier', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="reduction_postier_num" name="reduction_postier_num">
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="identifiant_laposte_flag" name="identifiant_laposte_flag" value="1" class="ufsc-toggle">
                                <?php esc_html_e( 'Identifiant La Poste', 'ufsc-clubs' ); ?>
                            </label>
                        </div>
                        <div class="ufsc-form-field ufsc-conditional-field" data-depends="identifiant_laposte_flag">
                            <label for="identifiant_laposte"><?php esc_html_e( 'Identifiant La Poste', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="identifiant_laposte" name="identifiant_laposte">
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="fonction_publique" name="fonction_publique" value="1">
                                <?php esc_html_e( 'Fonction publique', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="licence_delegataire" name="licence_delegataire" value="1" class="ufsc-toggle">
                                <?php esc_html_e( 'Licence délégataire', 'ufsc-clubs' ); ?>
                            </label>
                        </div>
                        <div class="ufsc-form-field ufsc-conditional-field" data-depends="licence_delegataire">
                            <label for="numero_licence_delegataire"><?php esc_html_e( 'Numéro de licence délégataire', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="numero_licence_delegataire" name="numero_licence_delegataire">
                        </div>
                    </div>

                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Consents et assurances', 'ufsc-clubs' ); ?></h4>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="diffusion_image" name="diffusion_image" value="1">
                                <?php esc_html_e( 'Autoriser la diffusion d\'image', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="infos_fsasptt" name="infos_fsasptt" value="1">
                                <?php esc_html_e( 'Recevoir les informations FSASPTT', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="infos_asptt" name="infos_asptt" value="1">
                                <?php esc_html_e( 'Recevoir les informations ASPTT', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="infos_cr" name="infos_cr" value="1">
                                <?php esc_html_e( 'Recevoir les informations du CR', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="infos_partenaires" name="infos_partenaires" value="1">
                                <?php esc_html_e( 'Recevoir les informations partenaires', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="honorabilite" name="honorabilite" value="1">
                                <?php esc_html_e( 'Je certifie mon honorabilité', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="assurance_dommage_corporel" name="assurance_dommage_corporel" value="1">
                                <?php esc_html_e( 'Assurance dommage corporel', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="assurance_assistance" name="assurance_assistance" value="1">
                                <?php esc_html_e( 'Assurance assistance', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label for="note"><?php esc_html_e( 'Note', 'ufsc-clubs' ); ?></label>
                            <textarea id="note" name="note" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="ufsc-form-actions">
                    <button type="submit" name="ufsc_add_licence" class="ufsc-btn ufsc-btn-primary">
                        <?php if ( $quota_info['remaining'] > 0 ): ?>
                            <?php esc_html_e( 'Créer la licence', 'ufsc-clubs' ); ?>
                        <?php else: ?>
                            <?php esc_html_e( 'Créer et procéder au paiement', 'ufsc-clubs' ); ?>
                        <?php endif; ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // Helper methods - STUBS to be implemented

    /**
     * Get user club ID
     * TODO: Implement according to database schema
     */
    private static function get_user_club_id( $user_id ) {
        // STUB: Return club ID for user
        return ufsc_get_user_club_id( $user_id );
    }

    /**
     * Get club name
     */
    private static function get_club_name( $club_id ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) { return "Club #{$club_id}"; }
        $clubs_table = ufsc_get_clubs_table();
        $name = $wpdb->get_var( $wpdb->prepare(
            "SELECT nom FROM `{$clubs_table}` WHERE id = %d LIMIT 1",
            $club_id
        ) );
        return $name ? $name : "Club #{$club_id}";
    }

    /**
     * Get club licences with pagination and filters
     */
    private static function get_club_licences( $club_id, $args ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_licences_table' ) ) { 
            return array(); 
        }
        $licences_table = ufsc_get_licences_table();

        $defaults = array(
            'search'   => '',
            'status'   => '',
            'season'   => '',
            'page'     => 1,
            'per_page' => 20,
            'sort'     => 'created_desc',
        );
        $args = wp_parse_args( $args, $defaults );

        // Colonnes disponibles
        $columns = $wpdb->get_col( "DESCRIBE `{$licences_table}`" );

        // Clauses et valeurs de préparation
        $clauses = array( 'club_id = %d' );
        $values  = array( (int) $club_id );

        // Recherche
        if ( ! empty( $args['search'] ) ) {
            $search_fields = array();
            $search_values = array();
            foreach ( array( 'nom', 'nom_licence', 'prenom', 'email' ) as $field ) {
                if ( in_array( $field, $columns, true ) ) {
                    $search_fields[] = "`{$field}` LIKE %s";
                    $search_values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                }
            }
            if ( $search_fields ) {
                $clauses[] = '(' . implode( ' OR ', $search_fields ) . ')';
                $values    = array_merge( $values, $search_values );
            }
        }

        // Statut
        if ( ! empty( $args['status'] ) ) {
            $status_col = null;
            foreach ( array( 'status', 'statut' ) as $col ) {
                if ( in_array( $col, $columns, true ) ) { $status_col = $col; break; }
            }
            if ( $status_col ) {
                $clauses[] = "`{$status_col}` = %s";
                $values[]  = $args['status'];
            }
        }

        // Saison
        if ( ! empty( $args['season'] ) ) {
            $season_col = null;
            foreach ( array( 'season', 'saison', 'paid_season' ) as $col ) {
                if ( in_array( $col, $columns, true ) ) { $season_col = $col; break; }
            }
            if ( $season_col ) {
                $clauses[] = "`{$season_col}` = %s";
                $values[]  = $args['season'];
            }
        }

        // Tri
        $order_by = 'id DESC';
        switch ( $args['sort'] ) {
            case 'created_asc':
                $order_by = 'id ASC';
                break;
            case 'name_asc':
                if ( in_array( 'nom', $columns, true ) ) { $order_by = 'nom ASC'; }
                break;
            case 'name_desc':
                if ( in_array( 'nom', $columns, true ) ) { $order_by = 'nom DESC'; }
                break;
        }

        // Pagination
        $per_page = max( 1, (int) $args['per_page'] );
        $page     = isset( $args['page'] ) ? (int) $args['page'] : ( isset( $args['paged'] ) ? (int) $args['paged'] : 1 );
        $page     = max( 1, $page );
        $offset   = ( $page - 1 ) * $per_page;

        $where_sql = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';
        $sql       = "SELECT * FROM `{$licences_table}` {$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d";
        $values[]  = $per_page;
        $values[]  = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Get club licences count
     */
    private static function get_club_licences_count( $club_id, $args ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
            return 0;
        }
        $licences_table = ufsc_get_licences_table();

        $defaults = array(
            'search' => '',
            'status' => '',
            'season' => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $columns = $wpdb->get_col( "DESCRIBE `{$licences_table}`" );

        $clauses = array( 'club_id = %d' );
        $values  = array( (int) $club_id );

        // Recherche
        if ( ! empty( $args['search'] ) ) {
            $search_fields = array();
            $search_values = array();
            foreach ( array( 'nom', 'nom_licence', 'prenom', 'email' ) as $field ) {
                if ( in_array( $field, $columns, true ) ) {
                    $search_fields[] = "`{$field}` LIKE %s";
                    $search_values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                }
            }
            if ( $search_fields ) {
                $clauses[] = '(' . implode( ' OR ', $search_fields ) . ')';
                $values    = array_merge( $values, $search_values );
            }
        }

        // Statut
        if ( ! empty( $args['status'] ) ) {
            $status_col = null;
            foreach ( array( 'status', 'statut' ) as $col ) {
                if ( in_array( $col, $columns, true ) ) { $status_col = $col; break; }
            }
            if ( $status_col ) {
                $clauses[] = "`{$status_col}` = %s";
                $values[]  = $args['status'];
            }
        }

        // Saison
        if ( ! empty( $args['season'] ) ) {
            $season_col = null;
            foreach ( array( 'season', 'saison', 'paid_season' ) as $col ) {
                if ( in_array( $col, $columns, true ) ) { $season_col = $col; break; }
            }
            if ( $season_col ) {
                $clauses[] = "`{$season_col}` = %s";
                $values[]  = $args['season'];
            }
        }

        $where_sql = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';
        $sql       = "SELECT COUNT(*) FROM `{$licences_table}` {$where_sql}";

        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Get club statistics
     */
    private static function get_club_stats( $club_id, $season ) {
        $cache_key = "ufsc_stats_{$club_id}_{$season}";
        $stats = get_transient( $cache_key );
        
        if ( false === $stats ) {
            global $wpdb;

            if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
                $stats = array( 'total_licences' => 0, 'paid_licences' => 0, 'validated_licences' => 0, 'quota_remaining' => 10 );
            } else {
                $licences_table = ufsc_get_licences_table();
                
                // Get table columns for dynamic detection
                $columns = $wpdb->get_col( "DESCRIBE `{$licences_table}`" );
                
                // Total licences
                $total_licences = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$licences_table}` WHERE club_id = %d",
                    $club_id
                ) );
                
                // Paid licences with dynamic column detection
                $paid_licences = 0;
                $paid_where = array();
                
                if ( in_array( 'paid_season', $columns ) ) {
                    $paid_where[] = $wpdb->prepare( "paid_season = %s", $season );
                }
                if ( in_array( 'is_paid', $columns ) ) {
                    $paid_where[] = "is_paid = 1";
                }
                
                if ( ! empty( $paid_where ) ) {
                    $paid_query = "SELECT COUNT(*) FROM `{$licences_table}` WHERE club_id = %d AND (" . implode( ' OR ', $paid_where ) . ")";
                    $paid_licences = (int) $wpdb->get_var( $wpdb->prepare( $paid_query, $club_id ) );
                }
                
                // Validated licences with dynamic column detection
                $validated_licences = 0;
                $status_column = null;
                foreach ( ['status', 'statut'] as $col ) {
                    if ( in_array( $col, $columns ) ) {
                        $status_column = $col;
                        break;
                    }
                }
                
                if ( $status_column ) {
                    $validated_statuses = ['valide', 'validée', 'validé', 'validated', 'approved'];
                    $status_placeholders = implode( ',', array_fill( 0, count( $validated_statuses ), '%s' ) );
                    $validated_query = "SELECT COUNT(*) FROM `{$licences_table}` WHERE club_id = %d AND `{$status_column}` IN ({$status_placeholders})";
                    $validated_licences = (int) $wpdb->get_var( $wpdb->prepare( $validated_query, array_merge( [$club_id], $validated_statuses ) ) );
                }
                
                $stats = array(
                    'total_licences' => $total_licences,
                    'paid_licences' => $paid_licences,
                    'validated_licences' => $validated_licences,
                    'quota_remaining' => max( 0, 50 - $total_licences ) // Default quota of 50
                );
            }
            
            // Cache for 1 hour
            set_transient( $cache_key, $stats, HOUR_IN_SECONDS );
        }
        
        return $stats;
    }

    /**
     * Get club data
     */
    private static function get_club_data( $club_id ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
            // Fallback minimal si la table n'est pas disponible
            return (object) array(
                'id'        => (int) $club_id,
                'nom'       => 'Club',
                'email'     => '',
                'telephone' => '',
            );
        }

        $clubs_table = ufsc_get_clubs_table();

        $club = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$clubs_table}` WHERE id = %d LIMIT 1",
                (int) $club_id
            )
        );

        return $club ?: (object) array(
            'id'        => (int) $club_id,
            'nom'       => 'Club',
            'email'     => '',
            'telephone' => '',
        );
    }

    /**
     * Check if club is validated
     */
    private static function is_validated_club( $club_id ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) { return false; }
        $clubs_table = ufsc_get_clubs_table();
        
        // Check for common status/validation columns
        $columns = $wpdb->get_col( "DESCRIBE `{$clubs_table}`" );
        $status_column = null;
        foreach ( ['status', 'statut', 'validated', 'validation'] as $col ) {
            if ( in_array( $col, $columns ) ) {
                $status_column = $col;
                break;
            }
        }
        
        if ( ! $status_column ) { return false; }
        
        $status = $wpdb->get_var( $wpdb->prepare(
            "SELECT `{$status_column}` FROM `{$clubs_table}` WHERE id = %d LIMIT 1",
            $club_id
        ) );
        
        return $status && in_array( strtolower( $status ), ['actif', 'validé', 'validée', 'approved', 'validate', 'validated'] );

        
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $pk = ufsc_club_col( 'id' );
        $statut_col = ufsc_club_col( 'statut' );
        
        $statut = $wpdb->get_var( $wpdb->prepare(
            "SELECT `{$statut_col}` FROM `{$table}` WHERE `{$pk}` = %d LIMIT 1",
            $club_id
        ) );
        
        if ( ! $statut ) {
            return false;
        }
        
        // Consider various forms of active/validated status
        $valid_statuses = array( 'actif', 'active', 'valide', 'validé', 'validée', 'approved' );
        return in_array( strtolower( $statut ), $valid_statuses );

    }

    /**
     * Check if licence is validated
     * TODO: Implement according to validation logic
     */
    private static function is_validated_licence( $licence_id ) {
        return ufsc_is_validated_licence( $licence_id );
    }

    /**
     * Get licence status label
     */
    private static function get_licence_status_label( $status ) {
        $labels = array(
            'brouillon' => __( 'Brouillon', 'ufsc-clubs' ),
            'paid' => __( 'Payée', 'ufsc-clubs' ),
            'validated' => __( 'Validée', 'ufsc-clubs' ),
            'applied' => __( 'Appliquée', 'ufsc-clubs' ),
            'rejected' => __( 'Refusée', 'ufsc-clubs' )
        );

        return $labels[ $status ] ?? $status;
    }

    /**
     * Map licence status to badge class
     */
    private static function get_licence_status_badge_class( $status ) {
        $classes = array(
            'brouillon' => '-draft',
            'paid' => '-pending',
            'validated' => '-ok',
            'applied' => '-ok',
            'rejected' => '-rejected'
        );

        return $classes[ $status ] ?? '-draft';
    }

    /**
     * Get club quota information
     * TODO: Implement according to quota logic
     */
    private static function get_club_quota_info( $club_id ) {
        // STUB: Return quota info
        return array(
            'total' => 10,
            'used' => 3,
            'remaining' => 7
        );
    }

    /**
     * Render club documents list for frontend display
     */
    private static function render_club_documents_list( $club_id ) {
        // Get default document types and allow filtering
        $doc_types = apply_filters( 'ufsc_club_documents_types', array(
            'statuts' => __( 'Statuts', 'ufsc-clubs' ),
            'assurance' => __( 'Attestation d\'assurance', 'ufsc-clubs' ),
            'rib' => __( 'RIB', 'ufsc-clubs' ),
            'attestation_ufsc' => __( 'Attestation UFSC', 'ufsc-clubs' )
        ) );
        
        echo '<div class="ufsc-documents-list">';
        
        $has_documents = false;
        foreach ( $doc_types as $slug => $label ) {
            $attachment_id = (int) get_option( 'ufsc_club_doc_' . $slug . '_' . $club_id );
            
            if ( $attachment_id ) {
                $attachment_url = wp_get_attachment_url( $attachment_id );
                if ( $attachment_url ) {
                    $has_documents = true;
                    echo '<div class="ufsc-document-item">';
                    echo '<span class="ufsc-document-label">' . esc_html( $label ) . ':</span>';
                    echo '<div class="ufsc-document-actions">';
                    echo '<a href="' . esc_url( $attachment_url ) . '" target="_blank" rel="noopener" class="ufsc-btn ufsc-btn-small">' . esc_html__( 'Voir', 'ufsc-clubs' ) . '</a> ';
                    echo '<a href="' . esc_url( $attachment_url ) . '" download class="ufsc-btn ufsc-btn-small">' . esc_html__( 'Télécharger', 'ufsc-clubs' ) . '</a>';
                    echo '</div>';
                    echo '</div>';
                }
            }
        }
        
        if ( ! $has_documents ) {
            echo '<p class="ufsc-no-documents">' . esc_html__( 'Aucun document disponible.', 'ufsc-clubs' ) . '</p>';
        }
        
        echo '</div>';
    }

    /**
     * Handle club update
     */
    /**
     * Handle club update with validation restrictions
     */
    private static function handle_club_update( $club_id, $data ) {

        global $wpdb;
        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) { 
            return array( 'success' => false, 'message' => __( 'Erreur de configuration.', 'ufsc-clubs' ) );
        }
        
        $clubs_table = ufsc_get_clubs_table();
        $is_admin = current_user_can( 'manage_options' );
        
        // Verify club exists and user has permission
        $club = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$clubs_table}` WHERE id = %d", $club_id ) );
        if ( ! $club ) {
            return array( 'success' => false, 'message' => __( 'Club non trouvé.', 'ufsc-clubs' ) );
        }
        
        if ( ! $is_admin ) {
            $user_club_id = ufsc_get_user_club_id( get_current_user_id() );
            if ( $user_club_id !== (int) $club_id ) {
                return array( 'success' => false, 'message' => __( 'Permission refusée.', 'ufsc-clubs' ) );
            }
        }
        
        $update_data = array();
        
        if ( $is_admin ) {
            // Admin can update all fields present in the data
            $allowed_fields = ['nom', 'sigle', 'email', 'telephone', 'adresse', 'code_postal', 'ville', 'region'];
            foreach ( $allowed_fields as $field ) {
                if ( isset( $data[$field] ) ) {
                    $update_data[$field] = sanitize_text_field( $data[$field] );
                }
            }
            
            // Handle logo upload for admin
            if ( ! empty( $_FILES['club_logo']['name'] ) ) {
                $upload_result = wp_handle_upload( $_FILES['club_logo'], array( 'test_form' => false ) );
                if ( ! isset( $upload_result['error'] ) ) {
                    $attachment_id = wp_insert_attachment( array(
                        'post_title' => sanitize_file_name( $_FILES['club_logo']['name'] ),
                        'post_content' => '',
                        'post_status' => 'inherit',
                        'post_mime_type' => $upload_result['type']
                    ), $upload_result['file'] );
                    
                    if ( $attachment_id ) {
                        update_option( 'ufsc_club_logo_' . $club_id, $attachment_id );
                    }
                }
            }
            
        } else {
            // Non-admin can only update email and telephone
            $allowed_fields = ['email', 'telephone'];
            foreach ( $allowed_fields as $field ) {
                if ( isset( $data[$field] ) ) {
                    $update_data[$field] = sanitize_text_field( $data[$field] );
                }
            }
        }
        
        if ( empty( $update_data ) ) {
            return array( 'success' => false, 'message' => __( 'Aucune donnée à mettre à jour.', 'ufsc-clubs' ) );
        }
        
        // Validate email if present
        if ( isset( $update_data['email'] ) && ! empty( $update_data['email'] ) && ! is_email( $update_data['email'] ) ) {
            return array( 'success' => false, 'message' => __( 'Adresse email invalide.', 'ufsc-clubs' ) );
        }
        
        $result = $wpdb->update( $clubs_table, $update_data, array( 'id' => $club_id ), array(), array( '%d' ) );
        
        if ( $result !== false ) {
            return array( 'success' => true, 'message' => __( 'Club mis à jour avec succès.', 'ufsc-clubs' ) );
        } else {
            return array( 'success' => false, 'message' => __( 'Erreur lors de la mise à jour.', 'ufsc-clubs' ) );
        }


        global $wpdb;
        
        // Check permissions
        if ( ! UFSC_CL_Permissions::ufsc_user_can_edit_club( $club_id ) ) {
            return array(
                'success' => false,
                'message' => __( 'Vous n\'avez pas les permissions pour éditer ce club.', 'ufsc-clubs' )
            );
        }
        
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $pk = ufsc_club_col( 'id' );
        
        // Determine editable fields based on user role
        $is_admin = current_user_can( 'manage_options' );
        
        if ( $is_admin ) {
            // Admin can edit all fields
            $allowed_fields = array_keys( UFSC_Column_Map::get_clubs_columns() );
        } else {
            // Non-admin can only edit email and telephone
            $allowed_fields = array( 'email', 'telephone' );
        }
        
        $update_data = array();
        foreach ( $allowed_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $column = ufsc_club_col( $field );
                if ( $field === 'email' ) {
                    $update_data[ $column ] = sanitize_email( $data[ $field ] );
                } else {
                    $update_data[ $column ] = sanitize_text_field( $data[ $field ] );
                }
            }
        }
        
        if ( empty( $update_data ) ) {
            return array(
                'success' => false,
                'message' => __( 'Aucune donnée à mettre à jour.', 'ufsc-clubs' )
            );
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array( $pk => $club_id ),
            null,
            array( '%d' )
        );
        
        if ( $result !== false ) {
            return array(
                'success' => true,
                'message' => __( 'Club mis à jour avec succès.', 'ufsc-clubs' )
            );
        } else {
            return array(
                'success' => false,
                'message' => __( 'Erreur lors de la mise à jour du club.', 'ufsc-clubs' )
            );
        }

        if ( ! is_user_logged_in() ) {
            return array( 'success' => false, 'message' => __( 'Non autorisé', 'ufsc-clubs' ) );
        }
        
        $is_admin = current_user_can( 'manage_options' );
        global $wpdb;
        
        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return array( 'success' => false, 'message' => __( 'Configuration manquante', 'ufsc-clubs' ) );
        }
        
        $table = ufsc_get_clubs_table();
        
        // Determine allowed fields based on user permissions
        $allowed_fields = $is_admin ? null : array( 'email', 'telephone' );
        
        $update_data = array();
        
        // Map logical field names to actual database columns
        $field_mappings = array(
            'name' => 'nom',
            'email' => 'email', 
            'phone' => 'telephone',
            'address' => 'adresse',
            'city' => 'ville',
            'zipcode' => 'code_postal',
            'region' => 'region'
        );
        
        foreach ( $field_mappings as $logical_key => $form_field ) {
            if ( isset( $data[ $form_field ] ) ) {
                $db_column = ufsc_club_col( $logical_key );
                if ( $db_column ) {
                    // Check if user is allowed to update this field
                    if ( $allowed_fields && ! in_array( $form_field, $allowed_fields, true ) ) {
                        continue;
                    }
                    
                    $value = sanitize_text_field( wp_unslash( $data[ $form_field ] ) );
                    $update_data[ $db_column ] = $value;
                }
            }
        }
        
        // Update database if there are changes
        if ( ! empty( $update_data ) ) {
            $result = $wpdb->update( $table, $update_data, array( 'id' => (int) $club_id ) );
            if ( $result === false ) {
                return array( 'success' => false, 'message' => __( 'Échec de la mise à jour', 'ufsc-clubs' ) );
            }
        }
        
        // Handle logo upload for admins only
        if ( $is_admin && ! empty( $_FILES['club_logo']['name'] ) ) {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }
            
            $logo_mimes = array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'gif' => 'image/gif', 
                'png' => 'image/png'
            );
            
            $logo_upload = wp_handle_upload( $_FILES['club_logo'], array(
                'test_form' => false,
                'mimes' => $logo_mimes
            ) );
            
            if ( ! isset( $logo_upload['error'] ) ) {
                $logo_attachment = array(
                    'post_mime_type' => $logo_upload['type'],
                    'post_title' => sanitize_file_name( $_FILES['club_logo']['name'] ),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                
                $logo_attachment_id = wp_insert_attachment( $logo_attachment, $logo_upload['file'] );
                
                if ( $logo_attachment_id ) {
                    $logo_metadata = wp_generate_attachment_metadata( $logo_attachment_id, $logo_upload['file'] );
                    wp_update_attachment_metadata( $logo_attachment_id, $logo_metadata );
                    
                    // Remove old logo if exists
                    $old_logo_id = get_option( 'ufsc_club_logo_' . $club_id );
                    if ( $old_logo_id ) {
                        wp_delete_attachment( $old_logo_id, true );
                    }
                    
                    // Save new logo using same storage pattern as admin
                    update_option( 'ufsc_club_logo_' . $club_id, $logo_attachment_id );
                }
            }
        }
        
        return array( 'success' => true, 'message' => __( 'Club mis à jour avec succès.', 'ufsc-clubs' ) );


    }

    /**
     * Handle licence creation
     * TODO: Implement creation logic with quota checks
     */
    private static function handle_licence_creation( $club_id, $data ) {
        // STUB: Handle licence creation
        return array(
            'success' => true,
            'message' => __( 'Licence créée avec succès.', 'ufsc-clubs' )
        );
    }

    /**
     * Render pagination
     */
    private static function render_pagination( $current_page, $total_pages, $query_args = array() ) {
        $output = '<div class="ufsc-pagination-wrapper">';
        
        for ( $page = 1; $page <= $total_pages; $page++ ) {
            $args = array_merge( $query_args, array( 'ufsc_page' => $page ) );
            $url = add_query_arg( $args );
            $class = $page === $current_page ? 'current' : '';
            
            $output .= sprintf(
                '<a href="%s" class="ufsc-page-link %s">%d</a>',
                esc_url( $url ),
                esc_attr( $class ),
                $page
            );
        }
        
        $output .= '</div>';
        return $output;
    }

    /**
     * Render import modal
     */
    private static function render_import_modal( $club_id ) {
        ob_start();
        ?>
        <div id="ufsc-import-modal" class="ufsc-modal" style="display:none;">
            <div class="ufsc-modal-content">
                <span class="ufsc-modal-close" onclick="document.getElementById('ufsc-import-modal').style.display='none'">&times;</span>
                <h3><?php esc_html_e( 'Importer des licences CSV', 'ufsc-clubs' ); ?></h3>
                <form method="post" enctype="multipart/form-data" class="ufsc-import-form">
                    <div class="ufsc-notices" aria-live="polite"></div>
                    <?php wp_nonce_field( 'ufsc_import_csv', 'ufsc_nonce' ); ?>
                    <input type="hidden" name="club_id" value="<?php echo esc_attr( $club_id ); ?>">
                    
                    <div class="ufsc-form-field">
                        <label for="csv_file"><?php esc_html_e( 'Fichier CSV', 'ufsc-clubs' ); ?></label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                        <p class="ufsc-help-text">
                            <?php esc_html_e( 'Format attendu: nom,prenom,email,telephone,date_naissance,sexe', 'ufsc-clubs' ); ?>
                        </p>
                    </div>
                    
                    <div class="ufsc-form-actions">
                        <button type="submit" name="ufsc_import_preview" class="ufsc-btn ufsc-btn-primary">
                            <?php esc_html_e( 'Prévisualiser', 'ufsc-clubs' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a form field with proper permissions and validation
     * 
     * @param string $field_key Field key
     * @param object $club Club data object
     * @param string $label Field label
     * @param string $type Input type
     * @param bool $readonly Force readonly
     * @param bool $editable Whether field is editable for current user
     */
    private static function render_field( $field_key, $club, $label, $type = 'text', $readonly = false, $editable = false ) {
        $value = isset( $club->{$field_key} ) ? $club->{$field_key} : '';
        $field_readonly = $readonly || ! $editable;
        
        echo '<div class="ufsc-form-field">';
        echo '<label for="' . esc_attr( $field_key ) . '">' . esc_html( $label ) . '</label>';
        
        if ( $type === 'textarea' ) {
            echo '<textarea id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '"';
            if ( $field_readonly ) {
                echo ' readonly';
            }
            echo '>' . esc_textarea( $value ) . '</textarea>';
        } else {
            echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '"';
            echo ' value="' . esc_attr( $value ) . '"';
            if ( $field_readonly ) {
                echo ' readonly';
            }
            echo '>';
        }
        
        echo '</div>';
    }
}

// STUB FUNCTIONS - To be implemented according to existing database schema

if ( ! function_exists( 'ufsc_is_validated_club' ) ) {
    function ufsc_is_validated_club( $club_id ) {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $pk = ufsc_club_col( 'id' );
        $statut_col = ufsc_club_col( 'statut' );
        
        $statut = $wpdb->get_var( $wpdb->prepare(
            "SELECT `{$statut_col}` FROM `{$table}` WHERE `{$pk}` = %d LIMIT 1",
            $club_id
        ) );
        
        if ( ! $statut ) {
            return false;
        }
        
        // Consider various forms of active/validated status
        $valid_statuses = array( 'actif', 'active', 'valide', 'validé', 'validée', 'approved' );
        return in_array( strtolower( $statut ), $valid_statuses );
    }
}

if ( ! function_exists( 'ufsc_is_validated_licence' ) ) {
    function ufsc_is_validated_licence( $licence_id ) {
        // TODO: Implement validation check
        return false;
    }
}