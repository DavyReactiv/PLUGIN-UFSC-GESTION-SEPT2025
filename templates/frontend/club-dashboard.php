<?php
/**
 * Club Dashboard Template
 * Enhanced frontend dashboard for club administrators
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

?>

<div class="ufsc-club-dashboard" id="ufsc-club-dashboard">
    
    <!-- 1. En-tête Club -->
    <div class="ufsc-dashboard-header">
        <div class="ufsc-club-header">
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
            
            <?php if ( isset( $attestation_affiliation ) && $attestation_affiliation ) : ?>
            <div class="ufsc-attestation-download">
                <a href="<?php echo esc_url( $attestation_affiliation ); ?>" class="button button-primary" target="_blank">
                    <span class="dashicons dashicons-download"></span>
                    <?php echo esc_html__( 'Télécharger attestation d\'affiliation', 'ufsc-clubs' ); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. KPI Rapides -->
    <div class="ufsc-dashboard-section ufsc-kpi-section">
        <h2><?php echo esc_html__( 'Aperçu rapide', 'ufsc-clubs' ); ?></h2>
        <div class="ufsc-kpi-grid" id="ufsc-kpi-grid">
            <div class="ufsc-kpi-card">
                <div class="ufsc-kpi-value" id="kpi-licences-total">-</div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'Licences total', 'ufsc-clubs' ); ?></div>
            </div>
            <div class="ufsc-kpi-card">
                <div class="ufsc-kpi-value" id="kpi-licences-validees">-</div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'Licences validées', 'ufsc-clubs' ); ?></div>
            </div>
            <div class="ufsc-kpi-card">
                <div class="ufsc-kpi-value" id="kpi-licences-attente">-</div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'En attente', 'ufsc-clubs' ); ?></div>
            </div>
            <div class="ufsc-kpi-card">
                <div class="ufsc-kpi-value" id="kpi-licences-expirees">-</div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'Expirées', 'ufsc-clubs' ); ?></div>
            </div>
            <div class="ufsc-kpi-card">
                <div class="ufsc-kpi-value" id="kpi-paiements-a-payer">-</div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'À payer', 'ufsc-clubs' ); ?></div>
            </div>
            <div class="ufsc-kpi-card">
                <div class="ufsc-kpi-value" id="kpi-paiements-payes">-</div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'Payé', 'ufsc-clubs' ); ?></div>
            </div>
            <div class="ufsc-kpi-card">
                <div class="ufsc-kpi-value"><?php echo (int) $club->quota_licences; ?></div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'Quota autorisé', 'ufsc-clubs' ); ?></div>
            </div>
            <div class="ufsc-kpi-card">
                <div class="ufsc-kpi-value" id="kpi-documents">-</div>
                <div class="ufsc-kpi-label"><?php echo esc_html__( 'Documents', 'ufsc-clubs' ); ?></div>
            </div>
        </div>
    </div>

    <!-- 3. Actions Rapides -->
    <div class="ufsc-dashboard-section ufsc-actions-section">
        <h2><?php echo esc_html__( 'Actions rapides', 'ufsc-clubs' ); ?></h2>
        <div class="ufsc-actions-grid">
            <a href="#" class="ufsc-action-btn" id="btn-nouvelle-licence">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php echo esc_html__( 'Nouvelle licence', 'ufsc-clubs' ); ?>
            </a>
            <a href="#" class="ufsc-action-btn" id="btn-importer-csv">
                <span class="dashicons dashicons-upload"></span>
                <?php echo esc_html__( 'Importer CSV', 'ufsc-clubs' ); ?>
            </a>
            <a href="#" class="ufsc-action-btn" id="btn-exporter-selection">
                <span class="dashicons dashicons-download"></span>
                <?php echo esc_html__( 'Exporter la sélection', 'ufsc-clubs' ); ?>
            </a>
            <a href="#" class="ufsc-action-btn" id="btn-generer-attestation">
                <span class="dashicons dashicons-media-document"></span>
                <?php echo esc_html__( 'Générer attestation', 'ufsc-clubs' ); ?>
            </a>
            <a href="#" class="ufsc-action-btn" id="btn-configurer-club">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php echo esc_html__( 'Configurer le club', 'ufsc-clubs' ); ?>
            </a>
        </div>
    </div>

    <!-- 4. Graphiques -->
    <div class="ufsc-dashboard-section ufsc-charts-section">
        <h2><?php echo esc_html__( 'Statistiques visuelles', 'ufsc-clubs' ); ?></h2>
        
        <div class="ufsc-charts-grid">
            <div class="ufsc-chart-container">
                <h3><?php echo esc_html__( 'Répartition par sexe', 'ufsc-clubs' ); ?></h3>
                <canvas id="chart-sexe" width="300" height="300"></canvas>
            </div>
            
            <div class="ufsc-chart-container">
                <h3><?php echo esc_html__( 'Tranches d\'âge', 'ufsc-clubs' ); ?></h3>
                <canvas id="chart-age" width="400" height="300"></canvas>
            </div>
            
            <div class="ufsc-chart-container ufsc-chart-wide">
                <h3><?php echo esc_html__( 'Paiements par mois', 'ufsc-clubs' ); ?></h3>
                <canvas id="chart-paiements" width="600" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- 5. Documents -->
    <div class="ufsc-dashboard-section ufsc-documents-section">
        <h2><?php echo esc_html__( 'Documents', 'ufsc-clubs' ); ?></h2>
        
        <div class="ufsc-documents-grid">
            <div class="ufsc-document-item">
                <div class="ufsc-document-icon">
                    <span class="dashicons dashicons-media-document"></span>
                </div>
                <div class="ufsc-document-info">
                    <h4><?php echo esc_html__( 'Attestation d\'affiliation', 'ufsc-clubs' ); ?></h4>
                    <?php if ( isset( $attestation_affiliation ) && $attestation_affiliation ) : ?>
                        <p class="ufsc-document-status ufsc-available"><?php echo esc_html__( 'Disponible', 'ufsc-clubs' ); ?></p>
                        <a href="<?php echo esc_url( $attestation_affiliation ); ?>" class="button button-small" target="_blank"><?php echo esc_html__( 'Télécharger', 'ufsc-clubs' ); ?></a>
                    <?php else : ?>
                        <p class="ufsc-document-status ufsc-unavailable"><?php echo esc_html__( 'Non disponible', 'ufsc-clubs' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Notifications -->
    <div class="ufsc-dashboard-section ufsc-notifications-section">
        <h2><?php echo esc_html__( 'Notifications', 'ufsc-clubs' ); ?></h2>
        <div class="ufsc-notifications-container" id="ufsc-notifications">
            <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
        </div>
    </div>

    <!-- 7. Journal d'audit -->
    <div class="ufsc-dashboard-section ufsc-audit-section">
        <h2><?php echo esc_html__( 'Journal d\'activité', 'ufsc-clubs' ); ?></h2>
        <div class="ufsc-audit-container" id="ufsc-audit-log">
            <div class="ufsc-loading"><?php echo esc_html__( 'Chargement...', 'ufsc-clubs' ); ?></div>
        </div>
    </div>

</div>

<!-- Toast notifications -->
<div class="ufsc-toast-container" id="ufsc-toast-container" aria-live="polite"></div>