<?php
/**
 * Club Dashboard Template
 * Enhanced frontend dashboard for club administrators
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once UFSC_CL_DIR . 'includes/front/class-ufsc-stats.php';
$ufsc_stats     = new UFSC_Stats();
$stats_gender   = $ufsc_stats->get_gender_counts();
$stats_practice = $ufsc_stats->get_practice_counts();
$stats_age      = $ufsc_stats->get_age_group_counts();

$attestation_data = function_exists( 'ufsc_get_affiliation_attestation_data' )
    ? ufsc_get_affiliation_attestation_data( $club->id, $club )
    : array( 'url' => '', 'status' => 'pending', 'can_view' => false );

$wc_settings       = function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings() : array();
$current_season    = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
$renewal_affiliation_season = $current_season;
$renew_window_open = function_exists( 'ufsc_is_renewal_window_open' ) ? ufsc_is_renewal_window_open() : true;

$affiliation_product_id = function_exists( 'ufsc_get_affiliation_product_id' ) ? ufsc_get_affiliation_product_id() : 4823;
$affiliation_product_diagnostic = function_exists( 'ufsc_get_woocommerce_product_diagnostic' ) ? ufsc_get_woocommerce_product_diagnostic( $affiliation_product_id ) : array();
$affiliation_product_available = function_exists( 'ufsc_is_woocommerce_product_available' ) ? ufsc_is_woocommerce_product_available( $affiliation_product_id ) : false;

$renew_start_ts    = function_exists( 'ufsc_get_renewal_window_start_ts' ) ? (int) ufsc_get_renewal_window_start_ts() : 0;
$renew_open_label  = $renew_start_ts > 0 ? wp_date( 'd/m/Y', $renew_start_ts ) : __( '30/07', 'ufsc-clubs' );

$affiliation_state = function_exists( 'ufsc_get_affiliation_renewal_state' ) ? ufsc_get_affiliation_renewal_state( $club->id, $renewal_affiliation_season ) : array( 'status' => 'renewal_required', 'label' => __( 'À renouveler', 'ufsc-clubs' ), 'action' => 'renew', 'affiliation' => null );
$annual_affiliation = $affiliation_state['affiliation'];
$pending_order = function_exists( 'ufsc_wc_has_pending_renewal_order' ) ? ufsc_wc_has_pending_renewal_order( 'renew_affiliation', $club->id, $renewal_affiliation_season ) : false;
$renewal_url = function_exists( 'ufsc_get_affiliation_renewal_url' ) ? ufsc_get_affiliation_renewal_url( $club->id, $renewal_affiliation_season, $annual_affiliation->id ?? 0 ) : '';
$pending_payment_url = function_exists( 'ufsc_get_pending_affiliation_payment_url' ) ? ufsc_get_pending_affiliation_payment_url( $club->id, $renewal_affiliation_season ) : '';
$can_manage_current_club = is_user_logged_in() && class_exists( 'UFSC_CL_Permissions' ) && UFSC_CL_Permissions::ufsc_user_can_edit_club( $club->id );
$can_renew_affiliation = $can_manage_current_club && 'renew' === $affiliation_state['action'] && ! $pending_order && $affiliation_product_available;

?>

<div class="ufsc-club-dashboard" id="ufsc-club-dashboard">
    <div class="ufsc-feedback" id="ufsc-feedback" aria-live="polite" role="status" tabindex="-1"></div>
    
    <!-- 1. En-tête Club -->
    <div class="ufsc-dashboard-header">
        <div class="ufsc-club-header">
            <?php if ( ! empty( $club->profile_photo_url ) ) : ?>
                <div class="ufsc-club-photo">
                    <img src="<?php echo esc_url( $club->profile_photo_url ); ?>" alt="<?php esc_attr_e( 'Photo du club', 'ufsc-clubs' ); ?>" />
                    <?php if ( UFSC_CL_Permissions::ufsc_user_can_edit_club( $club->id ) ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-remove-photo-form">
                            <?php wp_nonce_field( 'ufsc_remove_profile_photo', 'ufsc_remove_profile_photo_nonce' ); ?>
                            <input type="hidden" name="action" value="ufsc_remove_profile_photo" />
                            <input type="hidden" name="club_id" value="<?php echo esc_attr( $club->id ); ?>" />
                            <button type="submit" class="button ufsc-remove-photo"><?php esc_html_e( 'Supprimer la photo', 'ufsc-clubs' ); ?></button>
                        </form>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ufsc-change-photo-form">
                            <?php wp_nonce_field( 'ufsc_upload_profile_photo', 'ufsc_upload_profile_photo_nonce' ); ?>
                            <input type="hidden" name="action" value="ufsc_upload_profile_photo" />
                            <input type="hidden" name="club_id" value="<?php echo esc_attr( $club->id ); ?>" />
                            <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" />
                            <button type="submit" class="button ufsc-upload-photo"><?php esc_html_e( 'Changer la photo', 'ufsc-clubs' ); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php elseif ( UFSC_CL_Permissions::ufsc_user_can_edit_club( $club->id ) ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ufsc-upload-photo-form">
                    <?php wp_nonce_field( 'ufsc_upload_profile_photo', 'ufsc_upload_profile_photo_nonce' ); ?>
                    <input type="hidden" name="action" value="ufsc_upload_profile_photo" />
                    <input type="hidden" name="club_id" value="<?php echo esc_attr( $club->id ); ?>" />
                    <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" />
                    <button type="submit" class="button ufsc-upload-photo"><?php esc_html_e( 'Ajouter une photo', 'ufsc-clubs' ); ?></button>
                </form>
            <?php endif; ?>

            <h1 class="ufsc-club-name"><?php echo esc_html( $club->nom ); ?></h1>
            <div class="ufsc-club-meta">
                <span class="ufsc-region"><?php echo esc_html( $club->region ); ?></span>
                <span class="ufsc-affiliation">
                    <?php if ( $club->num_affiliation ) : ?>
                        <?php echo esc_html__( 'N° affiliation :', 'ufsc-clubs' ); ?> <?php echo esc_html( $club->num_affiliation ); ?>
                    <?php endif; ?>
                </span>
                <div class="ufsc-status">
                    <?php echo UFSC_Badges::render_club_badge( $club->statut ); ?>
                </div>
            </div>
            
            <?php if ( ! empty( $attestation_data['can_view'] ) ) : ?>
                <div class="ufsc-attestation-download">
                    <?php if ( ! empty( $attestation_data['url'] ) ) : ?>
                        <a href="<?php echo esc_url( $attestation_data['url'] ); ?>" class="button button-primary" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-download" aria-hidden="true"></span>
                            <?php echo esc_html__( 'Télécharger attestation d\'affiliation', 'ufsc-clubs' ); ?>
                        </a>
                    <?php else : ?>
                        <span class="ufsc-badge ufsc-document-status -pending">
                            <?php echo esc_html__( 'Attestation en cours de génération', 'ufsc-clubs' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. Filtres et KPI -->
    <div class="ufsc-dashboard-section ufsc-kpi-section">
        <!-- // UFSC: Filtres pour les statistiques -->
        <div class="ufsc-filters-bar">
            <h2><?php echo esc_html__( 'Aperçu rapide', 'ufsc-clubs' ); ?></h2>
            <div class="ufsc-filters">
                <select id="filter-periode" class="ufsc-filter">
                    <option value="7"><?php echo esc_html__( '7 derniers jours', 'ufsc-clubs' ); ?></option>
                    <option value="30" selected><?php echo esc_html__( '30 derniers jours', 'ufsc-clubs' ); ?></option>
                    <option value="90"><?php echo esc_html__( '90 derniers jours', 'ufsc-clubs' ); ?></option>
                    <option value="365"><?php echo esc_html__( 'Cette année', 'ufsc-clubs' ); ?></option>
                </select>
                <select id="filter-genre" class="ufsc-filter">
                    <option value=""><?php echo esc_html__( 'Tous les genres', 'ufsc-clubs' ); ?></option>
                    <option value="M"><?php echo esc_html__( 'Homme', 'ufsc-clubs' ); ?></option>
                    <option value="F"><?php echo esc_html__( 'Femme', 'ufsc-clubs' ); ?></option>
                    <option value="Autre"><?php echo esc_html__( 'Autre', 'ufsc-clubs' ); ?></option>
                </select>
                <select id="filter-role" class="ufsc-filter">
                    <option value=""><?php echo esc_html__( 'Tous les rôles', 'ufsc-clubs' ); ?></option>
                    <option value="president"><?php echo esc_html__( 'Président', 'ufsc-clubs' ); ?></option>
                    <option value="secretaire"><?php echo esc_html__( 'Secrétaire', 'ufsc-clubs' ); ?></option>
                    <option value="tresorier"><?php echo esc_html__( 'Trésorier', 'ufsc-clubs' ); ?></option>
                    <option value="entraineur"><?php echo esc_html__( 'Entraîneur', 'ufsc-clubs' ); ?></option>
                    <option value="adherent"><?php echo esc_html__( 'Adhérent', 'ufsc-clubs' ); ?></option>
                </select>
                <select id="filter-competition" class="ufsc-filter">
                    <option value=""><?php echo esc_html__( 'Tous types', 'ufsc-clubs' ); ?></option>
                    <option value="1"><?php echo esc_html__( 'Compétition', 'ufsc-clubs' ); ?></option>
                    <option value="0"><?php echo esc_html__( 'Loisir', 'ufsc-clubs' ); ?></option>
                </select>
                <label class="ufsc-filter-checkbox-label">
                    <input type="checkbox" id="filter-drafts" class="ufsc-filter-checkbox" />
                    <?php echo esc_html__( 'Afficher seulement les brouillons', 'ufsc-clubs' ); ?>
                </label>
                <button id="btn-export-csv" class="ufsc-btn ufsc-btn-secondary">
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    <?php echo esc_html__( 'Export CSV', 'ufsc-clubs' ); ?>
                </button>
            </div>
        </div>

        <!-- // UFSC: KPIs selon les exigences (Validées, Payées, En attente, Refusées) -->
        <div class="ufsc-grid ufsc-kpi-grid" id="ufsc-kpi-grid" aria-live="polite" role="region" aria-label="<?php echo esc_attr__( 'Statistiques des licences', 'ufsc-clubs' ); ?>">
            <div class="ufsc-card ufsc-kpi-card -validees">
                <div class="ufsc-kpi-value" id="kpi-licences-validees" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'Licences Validées', 'ufsc-clubs' ); ?></div>
            </div>
            <div class="ufsc-card ufsc-kpi-card -payees">
                <div class="ufsc-kpi-value" id="kpi-licences-payees" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'Payées (en cours)', 'ufsc-clubs' ); ?></div>
            </div>
            <div class="ufsc-card ufsc-kpi-card -attente">
                <div class="ufsc-kpi-value" id="kpi-licences-attente" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'En attente', 'ufsc-clubs' ); ?></div>
            </div>
            <div class="ufsc-card ufsc-kpi-card -rejected">
                <div class="ufsc-kpi-value" id="kpi-licences-rejected" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'Refusées', 'ufsc-clubs' ); ?></div>
            </div>
        </div>
    </div>

    <!-- 3. Licences récentes -->
    <div class="ufsc-dashboard-section ufsc-recent-licences-section">
        <h2><?php echo esc_html__( 'Licences récentes', 'ufsc-clubs' ); ?></h2>
        <div class="ufsc-card">
            <div class="ufsc-recent-licences" id="ufsc-recent-licences" aria-live="polite" role="region" aria-label="<?php echo esc_attr__( 'Licences récentes', 'ufsc-clubs' ); ?>">
                <!-- // UFSC: Section populated via JavaScript -->
                <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
            </div>
        </div>
    </div>

    <!-- 4. Documents du club -->
    <div class="ufsc-dashboard-section ufsc-documents-section">
        <h2><?php echo esc_html__( 'Documents du club', 'ufsc-clubs' ); ?></h2>
        <div class="ufsc-card">
            <div class="ufsc-grid ufsc-documents-status" id="ufsc-documents-status" aria-live="polite" role="region" aria-label="<?php echo esc_attr__( 'Documents du club', 'ufsc-clubs' ); ?>">
                <div class="ufsc-document-item" data-doc="statuts" tabindex="0">
                    <span class="ufsc-document-icon" aria-hidden="true">📄</span>
                    <span class="ufsc-document-name"><?php echo esc_html__( 'Statuts', 'ufsc-clubs' ); ?></span>
                    <span class="ufsc-badge ufsc-document-status -pending">⏳</span>
                    <div class="ufsc-row-actions"></div>
                </div>
                <div class="ufsc-document-item" data-doc="recepisse" tabindex="0">
                    <span class="ufsc-document-icon" aria-hidden="true">📄</span>
                    <span class="ufsc-document-name"><?php echo esc_html__( 'Récépissé', 'ufsc-clubs' ); ?></span>
                    <span class="ufsc-badge ufsc-document-status -pending">⏳</span>
                    <div class="ufsc-row-actions"></div>
                </div>
                <div class="ufsc-document-item" data-doc="jo" tabindex="0">
                    <span class="ufsc-document-icon" aria-hidden="true">📄</span>
                    <span class="ufsc-document-name"><?php echo esc_html__( 'Journal Officiel', 'ufsc-clubs' ); ?></span>
                    <span class="ufsc-badge ufsc-document-status -pending">⏳</span>
                    <div class="ufsc-row-actions"></div>
                </div>
                <div class="ufsc-document-item" data-doc="pv_ag" tabindex="0">
                    <span class="ufsc-document-icon" aria-hidden="true">📄</span>
                    <span class="ufsc-document-name"><?php echo esc_html__( 'PV Assemblée Générale', 'ufsc-clubs' ); ?></span>
                    <span class="ufsc-badge ufsc-document-status -pending">⏳</span>
                    <div class="ufsc-row-actions"></div>
                </div>
                <div class="ufsc-document-item" data-doc="cer" tabindex="0">
                    <span class="ufsc-document-icon" aria-hidden="true">📄</span>
                    <span class="ufsc-document-name"><?php echo esc_html__( 'CER', 'ufsc-clubs' ); ?></span>
                    <span class="ufsc-badge ufsc-document-status -pending">⏳</span>
                    <div class="ufsc-row-actions"></div>
                </div>
                <div class="ufsc-document-item" data-doc="attestation_cer" tabindex="0">
                    <span class="ufsc-document-icon" aria-hidden="true">📄</span>
                    <span class="ufsc-document-name"><?php echo esc_html__( 'Attestation CER', 'ufsc-clubs' ); ?></span>
                    <span class="ufsc-badge ufsc-document-status -pending">⏳</span>
                    <div class="ufsc-row-actions"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Actions Rapides -->
    <div class="ufsc-dashboard-section ufsc-actions-section">
        <h2><?php echo esc_html__( 'Actions rapides', 'ufsc-clubs' ); ?></h2>
        <div class="ufsc-grid ufsc-actions-grid">
            <div class="ufsc-card">
                <a href="<?php echo esc_url( add_query_arg( 'add_licence', '1' ) ); ?>" class="ufsc-btn ufsc-btn-primary" id="btn-ajouter-licence">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php echo esc_html__( 'Ajouter une licence', 'ufsc-clubs' ); ?>
                </a>
            </div>
            <div class="ufsc-card">
                <a href="<?php echo esc_url( add_query_arg( 'edit_club', '1' ) ); ?>" class="ufsc-btn ufsc-btn-secondary" id="btn-mettre-a-jour-club">
                    <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                    <?php echo esc_html__( 'Mettre à jour infos club', 'ufsc-clubs' ); ?>
                </a>
            </div>
            <div class="ufsc-card">
                <a href="<?php echo esc_url( add_query_arg( 'upload_documents', '1' ) ); ?>" class="ufsc-btn ufsc-btn-secondary" id="btn-televerser-document">
                    <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                    <?php echo esc_html__( 'Téléverser un document', 'ufsc-clubs' ); ?>
                </a>
            </div>

            <div class="ufsc-card ufsc-affiliation-renewal-alert">
                <h3><?php echo esc_html( sprintf( __( 'Affiliation %1$s %2$s', 'ufsc-clubs' ), $renewal_affiliation_season, strtolower( $affiliation_state['label'] ) ) ); ?></h3>
                <?php if ( 'active' === $affiliation_state['status'] ) : ?>
                    <strong><?php echo esc_html( sprintf( __( 'Affiliation %s active', 'ufsc-clubs' ), $renewal_affiliation_season ) ); ?></strong>
                <?php elseif ( 'pending_payment' === $affiliation_state['status'] || $pending_order ) : ?>
                    <strong><?php esc_html_e( 'Paiement en attente', 'ufsc-clubs' ); ?></strong>
                    <p><?php echo esc_html( sprintf( __( 'Une demande d’affiliation %s est déjà présente dans votre panier ou en attente de traitement.', 'ufsc-clubs' ), $renewal_affiliation_season ) ); ?></p>
                    <?php if ( $pending_payment_url ) : ?><a class="ufsc-btn ufsc-btn-primary" href="<?php echo esc_url( $pending_payment_url ); ?>"><?php esc_html_e( 'Finaliser mon paiement', 'ufsc-clubs' ); ?></a><?php else : ?><span class="ufsc-text-muted"><?php esc_html_e( 'Votre demande existe déjà, mais le lien de paiement n’est plus disponible. Merci de contacter l’UFSC.', 'ufsc-clubs' ); ?></span><?php endif; ?>
                <?php elseif ( in_array( $affiliation_state['action'], array( 'wait', 'contact', 'correct' ), true ) ) : ?>
                    <strong><?php echo esc_html( $affiliation_state['label'] ); ?></strong>
                    <p><?php esc_html_e( 'Votre dossier d’affiliation est en cours de traitement ou nécessite une action. Merci de suivre les consignes UFSC.', 'ufsc-clubs' ); ?></p>
                    <?php if ( 'correct' === $affiliation_state['action'] ) : ?><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( add_query_arg( 'edit_club', '1' ) ); ?>"><?php esc_html_e( 'Corriger mon dossier d’affiliation', 'ufsc-clubs' ); ?></a><?php endif; ?>
                <?php else : ?>
                    <p><?php echo esc_html( sprintf( __( 'Votre club n’est pas encore affilié pour la saison %s. Vérifiez vos informations puis procédez au renouvellement.', 'ufsc-clubs' ), $renewal_affiliation_season ) ); ?></p>
                    <?php if ( $renew_window_open && $can_renew_affiliation && $renewal_url ) : ?>
                        <a class="ufsc-btn ufsc-btn-primary" href="<?php echo esc_url( $renewal_url ); ?>"><?php echo esc_html( sprintf( __( 'Renouveler mon affiliation %s', 'ufsc-clubs' ), $renewal_affiliation_season ) ); ?></a>
                        <p class="ufsc-text-muted"><?php esc_html_e( 'Produit WooCommerce : Pack Affiliation UFSC / FSASPTT', 'ufsc-clubs' ); ?></p>
                    <?php elseif ( ! $renew_window_open ) : ?>
                        <span class="ufsc-text-muted"><?php echo esc_html( sprintf( __( 'Renouvellement %1$s ouvert à partir du %2$s', 'ufsc-clubs' ), $renewal_affiliation_season, $renew_open_label ) ); ?></span>
                    <?php else : ?>
                        <span class="ufsc-text-muted"><?php echo esc_html( function_exists( 'ufsc_get_affiliation_product_unavailable_message' ) ? ufsc_get_affiliation_product_unavailable_message( $affiliation_product_diagnostic['unavailable_reason'] ?? '' ) : __( 'Le renouvellement en ligne est temporairement indisponible. Veuillez contacter l’UFSC.', 'ufsc-clubs' ) ); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- 6. Statistiques détaillées -->
    <div class="ufsc-dashboard-section ufsc-advanced-stats-section">
        <h2><?php echo esc_html__( 'Statistiques détaillées', 'ufsc-clubs' ); ?></h2>
        
        <div class="ufsc-grid ufsc-stats-grid">
            <div class="ufsc-card ufsc-stat-card">
                <h3><?php echo esc_html__( 'Répartition par sexe', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-stat-content" id="stats-sexe" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
            </div>
            
            <div class="ufsc-card ufsc-stat-card">
                <h3><?php echo esc_html__( 'Tranches d\'âge', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-stat-content" id="stats-age" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
            </div>
            
            <div class="ufsc-card ufsc-stat-card">
                <h3><?php echo esc_html__( 'Compétition vs Loisir', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-stat-content" id="stats-competition" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
            </div>
            
            <div class="ufsc-card ufsc-stat-card">
                <h3><?php echo esc_html__( 'Répartition par rôles', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-stat-content" id="stats-roles" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
            </div>
            
            <div class="ufsc-card ufsc-stat-card -wide">
                <h3><?php echo esc_html__( 'Évolution 30 derniers jours', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-evolution-stats" id="stats-evolution" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
            </div>
            
            <div class="ufsc-card ufsc-stat-card -wide">
                <h3><?php echo esc_html__( 'Alertes', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-alerts" id="stats-alerts" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Graphiques visuels -->
    <div class="ufsc-dashboard-section ufsc-charts-section">
        <h2><?php echo esc_html__( 'Graphiques visuels', 'ufsc-clubs' ); ?></h2>

        <div class="ufsc-charts-grid">
            <div class="ufsc-chart-container">
                <h3><?php echo esc_html__( 'Répartition par sexe', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-chart-wrapper" style="position:relative;height:300px;">
                    <canvas id="chart-gender"></canvas>
                </div>
            </div>

            <div class="ufsc-chart-container">
                <h3><?php echo esc_html__( 'Répartition par pratique', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-chart-wrapper" style="position:relative;height:300px;">
                    <canvas id="chart-practice"></canvas>
                </div>
            </div>

            <div class="ufsc-chart-container ufsc-chart-wide">
                <h3><?php echo esc_html__( 'Tranches d\'âge', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-chart-wrapper" style="position:relative;height:300px;">
                    <canvas id="chart-age"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    (function(){
        const genderData = <?php echo wp_json_encode( $stats_gender ); ?>;
        const practiceData = <?php echo wp_json_encode( $stats_practice ); ?>;
        const ageData = <?php echo wp_json_encode( $stats_age ); ?>;

        function buildPie(el, dataset, key) {
            const labels = dataset.map(d => d[key] || '<?php echo esc_js__( 'Inconnu', 'ufsc-clubs' ); ?>');
            const values = dataset.map(d => parseInt(d.total, 10));
            return new Chart(el.getContext('2d'), {
                type: 'pie',
                data: { labels: labels, datasets: [{ data: values }] },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        buildPie(document.getElementById('chart-gender'), genderData, 'gender');
        buildPie(document.getElementById('chart-practice'), practiceData, 'practice');

        const ageLabels = ageData.map(d => d.age_group);
        const ageValues = ageData.map(d => parseInt(d.total, 10));
        new Chart(document.getElementById('chart-age').getContext('2d'), {
            type: 'bar',
            data: { labels: ageLabels, datasets: [{ data: ageValues }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    })();
    </script>

    <!-- 8. Notifications et Alertes -->
    <div class="ufsc-dashboard-section ufsc-notifications-section">
        <h2><?php echo esc_html__( 'Notifications & Journal d\'activité', 'ufsc-clubs' ); ?></h2>
        
        <div class="ufsc-grid">
            <div class="ufsc-card">
                <h3><?php echo esc_html__( 'Notifications', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-notifications-container" id="ufsc-notifications" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
            </div>
            
            <div class="ufsc-card">
                <h3><?php echo esc_html__( 'Journal d\'activité', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-audit-container" id="ufsc-audit-log" aria-live="polite">
                    <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Toast notifications -->
<div class="ufsc-toast-container" id="ufsc-toast-container" aria-live="polite"></div>
