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
                                <th><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Prénom', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Statut', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Date création', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'ufsc-clubs' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $licences as $licence ): ?>
                                <tr>
                                    <td><?php echo esc_html( $licence->nom ?? '' ); ?></td>
                                    <td><?php echo esc_html( $licence->prenom ?? '' ); ?></td>
                                    <td><?php echo esc_html( $licence->email ?? '' ); ?></td>
                                    <td>
                                        <span class="ufsc-status ufsc-status-<?php echo esc_attr( $licence->statut ?? 'brouillon' ); ?>">
                                            <?php echo esc_html( self::get_licence_status_label( $licence->statut ?? 'brouillon' ) ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( $licence->date_creation ?? '' ); ?></td>
                                    <td class="ufsc-actions">
                                        <?php if ( ! self::is_validated_licence( $licence->id ?? 0 ) ): ?>
                                            <a href="<?php echo esc_url( add_query_arg( 'edit_licence', $licence->id ?? 0 ) ); ?>" 
                                               class="ufsc-btn ufsc-btn-small">
                                                <?php esc_html_e( 'Modifier', 'ufsc-clubs' ); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="ufsc-text-muted">
                                                <?php esc_html_e( 'Validée - Non modifiable', 'ufsc-clubs' ); ?>
                                            </span>
                                        <?php endif; ?>
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
     * Render club profile section
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_club_profile( $atts = array() ) {
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
        <div class="ufsc-profile-section">
            <div class="ufsc-section-header">
                <h3><?php esc_html_e( 'Mon Club', 'ufsc-clubs' ); ?></h3>
                <?php if ( $is_validated ): ?>
                    <p class="ufsc-validation-notice">
                        <?php esc_html_e( 'Club validé - Seuls le téléphone et l\'email peuvent être modifiés', 'ufsc-clubs' ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <form method="post" enctype="multipart/form-data" class="ufsc-club-form">
                <?php wp_nonce_field( 'ufsc_update_club', 'ufsc_nonce' ); ?>
                
                <!-- Logo Section -->
                <div class="ufsc-form-section">
                    <h4><?php esc_html_e( 'Logo du Club', 'ufsc-clubs' ); ?></h4>
                    <div class="ufsc-logo-section">
                        <?php 
                        $logo_id = get_option( 'ufsc_club_logo_' . $atts['club_id'] );
                        if ( $logo_id ): 
                            $logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
                        ?>
                            <div class="ufsc-logo-preview">
                                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo du club', 'ufsc-clubs' ); ?>">
                                <?php if ( $is_admin ): ?>
                                    <button type="button" class="ufsc-logo-remove" data-club-id="<?php echo esc_attr( $atts['club_id'] ); ?>">
                                        <?php esc_html_e( 'Supprimer', 'ufsc-clubs' ); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php elseif ( $is_admin ): ?>
                            <div class="ufsc-logo-upload">
                                <input type="file" id="club_logo" name="club_logo" accept="image/*">
                                <label for="club_logo" class="ufsc-upload-label">
                                    <?php esc_html_e( 'Choisir un logo', 'ufsc-clubs' ); ?>
                                </label>
                                <p class="ufsc-help-text">
                                    <?php esc_html_e( 'Formats acceptés: JPG, PNG, SVG. Taille max: 2MB', 'ufsc-clubs' ); ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <p class="ufsc-text-muted"><?php esc_html_e( 'Aucun logo configuré.', 'ufsc-clubs' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Club Information -->
                <div class="ufsc-form-section">
                    <h4><?php esc_html_e( 'Informations du Club', 'ufsc-clubs' ); ?></h4>
                    
                    <div class="ufsc-form-row">
                        <div class="ufsc-form-field">
                            <label for="nom"><?php esc_html_e( 'Nom du club', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="nom" name="nom" 
                                   value="<?php echo esc_attr( $club->nom ?? '' ); ?>"
                                   <?php echo ( $is_validated || ! $is_admin ) ? 'readonly' : 'required'; ?>>
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="sigle"><?php esc_html_e( 'Sigle', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="sigle" name="sigle" 
                                   value="<?php echo esc_attr( $club->sigle ?? '' ); ?>"
                                   <?php echo ( $is_validated || ! $is_admin ) ? 'readonly' : ''; ?>>
                        </div>
                    </div>

                    <div class="ufsc-form-row">
                        <div class="ufsc-form-field">
                            <label for="email"><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo esc_attr( $club->email ?? '' ); ?>" 
                                   required>
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="telephone"><?php esc_html_e( 'Téléphone', 'ufsc-clubs' ); ?></label>
                            <input type="tel" id="telephone" name="telephone" 
                                   value="<?php echo esc_attr( $club->telephone ?? '' ); ?>">
                        </div>
                    </div>

                    <?php if ( ! $is_validated && $is_admin ): ?>
                        <div class="ufsc-form-row">
                            <div class="ufsc-form-field">
                                <label for="adresse"><?php esc_html_e( 'Adresse', 'ufsc-clubs' ); ?></label>
                                <textarea id="adresse" name="adresse" rows="3"><?php echo esc_textarea( $club->adresse ?? '' ); ?></textarea>
                            </div>
                        </div>

                        <div class="ufsc-form-row">
                            <div class="ufsc-form-field">
                                <label for="ville"><?php esc_html_e( 'Ville', 'ufsc-clubs' ); ?></label>
                                <input type="text" id="ville" name="ville" 
                                       value="<?php echo esc_attr( $club->ville ?? '' ); ?>">
                            </div>
                            
                            <div class="ufsc-form-field">
                                <label for="code_postal"><?php esc_html_e( 'Code postal', 'ufsc-clubs' ); ?></label>
                                <input type="text" id="code_postal" name="code_postal" 
                                       value="<?php echo esc_attr( $club->code_postal ?? '' ); ?>">
                            </div>
                        </div>

                        <div class="ufsc-form-row">
                            <div class="ufsc-form-field">
                                <label for="region"><?php esc_html_e( 'Région', 'ufsc-clubs' ); ?></label>
                                <select id="region" name="region">
                                    <option value=""><?php esc_html_e( 'Sélectionner une région', 'ufsc-clubs' ); ?></option>
                                    <?php 
                                    $regions = ufsc_get_regions_labels();
                                    foreach ( $regions as $region ): 
                                    ?>
                                        <option value="<?php echo esc_attr( $region ); ?>" 
                                                <?php selected( $club->region ?? '', $region ); ?>>
                                            <?php echo esc_html( $region ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Read-only fields for validated clubs or non-admin users -->
                        <div class="ufsc-readonly-fields">
                            <p><strong><?php esc_html_e( 'Adresse:', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $club->adresse ?? '' ); ?></p>
                            <p><strong><?php esc_html_e( 'Ville:', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $club->ville ?? '' ); ?></p>
                            <p><strong><?php esc_html_e( 'Code postal:', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $club->code_postal ?? '' ); ?></p>
                            <p><strong><?php esc_html_e( 'Région:', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $club->region ?? '' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Documents Section -->
                <div class="ufsc-form-section">
                    <h4><?php esc_html_e( 'Documents du Club', 'ufsc-clubs' ); ?></h4>
                    <div class="ufsc-documents-section">
                        <?php
                        $document_types = apply_filters( 'ufsc_club_documents_types', array(
                            'statuts' => __( 'Statuts', 'ufsc-clubs' ),
                            'rib' => __( 'RIB', 'ufsc-clubs' ),
                            'assurance' => __( 'Assurance', 'ufsc-clubs' )
                        ) );
                        
                        $has_documents = false;
                        foreach ( $document_types as $slug => $label ) {
                            $doc_id = get_option( 'ufsc_club_doc_' . $slug . '_' . $atts['club_id'] );
                            if ( $doc_id ) {
                                $has_documents = true;
                                break;
                            }
                        }
                        
                        if ( $has_documents ): ?>
                            <ul class="ufsc-docs-list">
                                <?php foreach ( $document_types as $slug => $label ):
                                    $doc_id = get_option( 'ufsc_club_doc_' . $slug . '_' . $atts['club_id'] );
                                    if ( $doc_id ):
                                        $doc_url = wp_get_attachment_url( $doc_id );
                                        $doc_filename = get_the_title( $doc_id );
                                ?>
                                    <li>
                                        <strong><?php echo esc_html( $label ); ?>:</strong>
                                        <a href="<?php echo esc_url( $doc_url ); ?>" target="_blank">
                                            <?php esc_html_e( 'Voir', 'ufsc-clubs' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( $doc_url ); ?>" download="<?php echo esc_attr( $doc_filename ); ?>">
                                            <?php esc_html_e( 'Télécharger', 'ufsc-clubs' ); ?>
                                        </a>
                                    </li>
                                <?php endif; endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="ufsc-text-muted"><?php esc_html_e( 'Aucun document configuré.', 'ufsc-clubs' ); ?></p>
                        <?php endif; ?>
                        
                        <?php if ( $is_admin ): ?>
                            <p class="ufsc-help-text">
                                <?php esc_html_e( 'Les documents peuvent être gérés depuis l\'administration.', 'ufsc-clubs' ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ufsc-form-actions">
                    <button type="submit" name="ufsc_update_club" class="ufsc-btn ufsc-btn-primary">
                        <?php esc_html_e( 'Mettre à jour', 'ufsc-clubs' ); ?>
                    </button>
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

            <form method="post" class="ufsc-licence-form">
                <?php wp_nonce_field( 'ufsc_add_licence', 'ufsc_nonce' ); ?>
                
                <div class="ufsc-form-section">
                    <h4><?php esc_html_e( 'Informations du licencié', 'ufsc-clubs' ); ?></h4>
                    
                    <div class="ufsc-form-row">
                        <div class="ufsc-form-field">
                            <label for="nom"><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="nom" name="nom" required>
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="prenom"><?php esc_html_e( 'Prénom', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="prenom" name="prenom" required>
                        </div>
                    </div>

                    <div class="ufsc-form-row">
                        <div class="ufsc-form-field">
                            <label for="email"><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="telephone"><?php esc_html_e( 'Téléphone', 'ufsc-clubs' ); ?></label>
                            <input type="tel" id="telephone" name="telephone">
                        </div>
                    </div>

                    <div class="ufsc-form-row">
                        <div class="ufsc-form-field">
                            <label for="date_naissance"><?php esc_html_e( 'Date de naissance', 'ufsc-clubs' ); ?></label>
                            <input type="date" id="date_naissance" name="date_naissance" required>
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="sexe"><?php esc_html_e( 'Sexe', 'ufsc-clubs' ); ?></label>
                            <select id="sexe" name="sexe" required>
                                <option value=""><?php esc_html_e( 'Sélectionner', 'ufsc-clubs' ); ?></option>
                                <option value="M"><?php esc_html_e( 'Masculin', 'ufsc-clubs' ); ?></option>
                                <option value="F"><?php esc_html_e( 'Féminin', 'ufsc-clubs' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="ufsc-form-row">
                        <div class="ufsc-form-field">
                            <label for="adresse"><?php esc_html_e( 'Adresse', 'ufsc-clubs' ); ?></label>
                            <textarea id="adresse" name="adresse" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="ufsc-form-row">
                        <div class="ufsc-form-field">
                            <label for="ville"><?php esc_html_e( 'Ville', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="ville" name="ville">
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="code_postal"><?php esc_html_e( 'Code postal', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="code_postal" name="code_postal">
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
        if ( ! function_exists( 'ufsc_get_licences_table' ) ) { return array(); }
        $licences_table = ufsc_get_licences_table();
        
        $defaults = array(
            'search' => '',
            'status' => '',
            'season' => '',
            'paged' => 1,
            'per_page' => 20
        );
        $args = wp_parse_args( $args, $defaults );
        
        // Get table columns for dynamic detection
        $columns = $wpdb->get_col( "DESCRIBE `{$licences_table}`" );
        
        // Build WHERE conditions
        $where_conditions = array();
        $where_conditions[] = $wpdb->prepare( "club_id = %d", $club_id );
        
        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $search_fields = array();
            foreach ( ['nom', 'nom_licence', 'prenom', 'email'] as $field ) {
                if ( in_array( $field, $columns ) ) {
                    $search_fields[] = "`{$field}` LIKE %s";
                }
            }
            if ( ! empty( $search_fields ) ) {
                $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                $search_params = array_fill( 0, count( $search_fields ), $search_term );
                $where_conditions[] = '(' . implode( ' OR ', $search_fields ) . ')';
            }
        }
        
        // Status filter
        if ( ! empty( $args['status'] ) ) {
            $status_column = null;
            foreach ( ['status', 'statut'] as $col ) {
                if ( in_array( $col, $columns ) ) {
                    $status_column = $col;
                    break;
                }
            }
            if ( $status_column ) {
                $where_conditions[] = $wpdb->prepare( "`{$status_column}` = %s", $args['status'] );
            }
        }
        
        // Season filter
        if ( ! empty( $args['season'] ) ) {
            $season_column = null;
            foreach ( ['season', 'saison', 'paid_season'] as $col ) {
                if ( in_array( $col, $columns ) ) {
                    $season_column = $col;
                    break;
                }
            }
            if ( $season_column ) {
                $where_conditions[] = $wpdb->prepare( "`{$season_column}` = %s", $args['season'] );
            }
        }
        
        $where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';
        
        // Pagination
        $offset = ( $args['paged'] - 1 ) * $args['per_page'];
        $limit = $wpdb->prepare( "LIMIT %d OFFSET %d", $args['per_page'], $offset );
        
        $query = "SELECT * FROM `{$licences_table}` {$where_clause} ORDER BY id DESC {$limit}";
        
        // Add search parameters if needed
        if ( ! empty( $args['search'] ) && ! empty( $search_params ) ) {
            $query = $wpdb->prepare( $query, $search_params );
        }
        
        return $wpdb->get_results( $query );
    }

    /**
     * Get club licences count
     */
    private static function get_club_licences_count( $club_id, $args ) {
        global $wpdb;
        if ( ! function_exists( 'ufsc_get_licences_table' ) ) { return 0; }
        $licences_table = ufsc_get_licences_table();
        
        $defaults = array(
            'search' => '',
            'status' => '',
            'season' => ''
        );
        $args = wp_parse_args( $args, $defaults );
        
        // Get table columns for dynamic detection
        $columns = $wpdb->get_col( "DESCRIBE `{$licences_table}`" );
        
        // Build WHERE conditions (same logic as get_club_licences)
        $where_conditions = array();
        $where_conditions[] = $wpdb->prepare( "club_id = %d", $club_id );
        
        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $search_fields = array();
            foreach ( ['nom', 'nom_licence', 'prenom', 'email'] as $field ) {
                if ( in_array( $field, $columns ) ) {
                    $search_fields[] = "`{$field}` LIKE %s";
                }
            }
            if ( ! empty( $search_fields ) ) {
                $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                $search_params = array_fill( 0, count( $search_fields ), $search_term );
                $where_conditions[] = '(' . implode( ' OR ', $search_fields ) . ')';
            }
        }
        
        // Status filter
        if ( ! empty( $args['status'] ) ) {
            $status_column = null;
            foreach ( ['status', 'statut'] as $col ) {
                if ( in_array( $col, $columns ) ) {
                    $status_column = $col;
                    break;
                }
            }
            if ( $status_column ) {
                $where_conditions[] = $wpdb->prepare( "`{$status_column}` = %s", $args['status'] );
            }
        }
        
        // Season filter
        if ( ! empty( $args['season'] ) ) {
            $season_column = null;
            foreach ( ['season', 'saison', 'paid_season'] as $col ) {
                if ( in_array( $col, $columns ) ) {
                    $season_column = $col;
                    break;
                }
            }
            if ( $season_column ) {
                $where_conditions[] = $wpdb->prepare( "`{$season_column}` = %s", $args['season'] );
            }
        }
        
        $where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';
        
        $query = "SELECT COUNT(*) FROM `{$licences_table}` {$where_clause}";
        
        // Add search parameters if needed
        if ( ! empty( $args['search'] ) && ! empty( $search_params ) ) {
            $query = $wpdb->prepare( $query, $search_params );
        }
        
        return (int) $wpdb->get_var( $query );
    }

    /**
     * Get club statistics with caching
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
            return (object) array( 'id' => $club_id, 'nom' => 'Club Test', 'email' => '', 'telephone' => '' );
        }
        $clubs_table = ufsc_get_clubs_table();
        $club = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$clubs_table}` WHERE id = %d LIMIT 1",
            $club_id
        ) );
        return $club ?: (object) array( 'id' => $club_id, 'nom' => 'Club Test', 'email' => '', 'telephone' => '' );
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
            'applied' => __( 'Appliquée', 'ufsc-clubs' )
        );
        
        return $labels[ $status ] ?? $status;
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
     * Handle club update
     * TODO: Implement update logic with validation restrictions
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
}

// STUB FUNCTIONS - To be implemented according to existing database schema

if ( ! function_exists( 'ufsc_is_validated_club' ) ) {
    function ufsc_is_validated_club( $club_id ) {
        // TODO: Implement validation check
        return false;
    }
}

if ( ! function_exists( 'ufsc_is_validated_licence' ) ) {
    function ufsc_is_validated_licence( $licence_id ) {
        // TODO: Implement validation check
        return false;
    }
}