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
                                <button type="button" class="ufsc-logo-remove" data-club-id="<?php echo esc_attr( $atts['club_id'] ); ?>">
                                    <?php esc_html_e( 'Supprimer', 'ufsc-clubs' ); ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="ufsc-logo-upload">
                                <input type="file" id="club_logo" name="club_logo" accept="image/*">
                                <label for="club_logo" class="ufsc-upload-label">
                                    <?php esc_html_e( 'Choisir un logo', 'ufsc-clubs' ); ?>
                                </label>
                                <p class="ufsc-help-text">
                                    <?php esc_html_e( 'Formats acceptés: JPG, PNG, SVG. Taille max: 2MB', 'ufsc-clubs' ); ?>
                                </p>
                            </div>
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
                                   <?php echo $is_validated ? 'readonly' : 'required'; ?>>
                        </div>
                        
                        <div class="ufsc-form-field">
                            <label for="sigle"><?php esc_html_e( 'Sigle', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="sigle" name="sigle" 
                                   value="<?php echo esc_attr( $club->sigle ?? '' ); ?>"
                                   <?php echo $is_validated ? 'readonly' : ''; ?>>
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

                    <?php if ( ! $is_validated ): ?>
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
                        <!-- Read-only fields for validated clubs -->
                        <div class="ufsc-readonly-fields">
                            <p><strong><?php esc_html_e( 'Adresse:', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $club->adresse ?? '' ); ?></p>
                            <p><strong><?php esc_html_e( 'Ville:', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $club->ville ?? '' ); ?></p>
                            <p><strong><?php esc_html_e( 'Code postal:', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $club->code_postal ?? '' ); ?></p>
                            <p><strong><?php esc_html_e( 'Région:', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $club->region ?? '' ); ?></p>
                        </div>
                    <?php endif; ?>
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
     * TODO: Implement according to database schema
     */
    private static function get_club_name( $club_id ) {
        // STUB: Return club name
        return "Club Test #{$club_id}";
    }

    /**
     * Get club licences with pagination and filters
     * TODO: Implement according to database schema
     */
    private static function get_club_licences( $club_id, $args ) {
        // STUB: Return array of licence objects
        return array();
    }

    /**
     * Get club licences count
     * TODO: Implement according to database schema
     */
    private static function get_club_licences_count( $club_id, $args ) {
        // STUB: Return total count
        return 0;
    }

    /**
     * Get club statistics
     * TODO: Implement with caching
     */
    private static function get_club_stats( $club_id, $season ) {
        $cache_key = "ufsc_stats_{$club_id}_{$season}";
        $stats = get_transient( $cache_key );
        
        if ( false === $stats ) {
            // STUB: Calculate stats
            $stats = array(
                'total_licences' => 0,
                'paid_licences' => 0,
                'validated_licences' => 0,
                'quota_remaining' => 10
            );
            
            // Cache for 1 hour
            set_transient( $cache_key, $stats, HOUR_IN_SECONDS );
        }
        
        return $stats;
    }

    /**
     * Get club data
     * TODO: Implement according to database schema
     */
    private static function get_club_data( $club_id ) {
        // STUB: Return club object
        return (object) array(
            'id' => $club_id,
            'nom' => 'Club Test',
            'email' => 'test@example.com',
            'telephone' => '01 23 45 67 89'
        );
    }

    /**
     * Check if club is validated
     * TODO: Implement according to validation logic
     */
    private static function is_validated_club( $club_id ) {
        return ufsc_is_validated_club( $club_id );
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
    private static function handle_club_update( $club_id, $data ) {
        // STUB: Handle club update
        return array(
            'success' => true,
            'message' => __( 'Club mis à jour avec succès.', 'ufsc-clubs' )
        );
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