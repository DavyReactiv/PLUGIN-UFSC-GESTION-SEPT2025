<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce settings module for UFSC Gestion
 * Handles WooCommerce product IDs, quota, and season settings
 */

/**
 * Get default WooCommerce settings
 * 
 * @return array Default WooCommerce settings
 */
function ufsc_get_default_woocommerce_settings() {
    return array(
        'product_affiliation_id' => 4823,
        'product_license_id' => 2934,
        'included_licenses' => 10,
        'season' => '2025-2026',
        'max_profile_photo_size' => 2,
        'auto_consume_included' => 1,
        'renewal_window_day' => 30,
        'renewal_window_month' => 7,
    );
}

/**
 * Get current WooCommerce settings
 * 
 * @return array Current WooCommerce settings merged with defaults
 */
function ufsc_get_woocommerce_settings() {
    $defaults = ufsc_get_default_woocommerce_settings();
    $saved = get_option( 'ufsc_woocommerce_settings', array() );
    
    return wp_parse_args( $saved, $defaults );
}

/**
 * Save WooCommerce settings
 * 
 * @param array $settings WooCommerce settings to save
 * @return bool True on success
 */
function ufsc_save_woocommerce_settings( $settings ) {
    // Sanitize and validate settings
    $sanitized = array();
    
    if ( isset( $settings['product_affiliation_id'] ) ) {
        $sanitized['product_affiliation_id'] = absint( $settings['product_affiliation_id'] );
    }
    
    if ( isset( $settings['product_license_id'] ) ) {
        $sanitized['product_license_id'] = absint( $settings['product_license_id'] );
    }
    
    if ( isset( $settings['included_licenses'] ) ) {
        $sanitized['included_licenses'] = absint( $settings['included_licenses'] );
    }

    if ( isset( $settings['season'] ) ) {
        $sanitized['season'] = sanitize_text_field( $settings['season'] );
    }

    if ( isset( $settings['max_profile_photo_size'] ) ) {
        $sanitized['max_profile_photo_size'] = absint( $settings['max_profile_photo_size'] );
    }

    if ( isset( $settings['auto_consume_included'] ) ) {
        $sanitized['auto_consume_included'] = ! empty( $settings['auto_consume_included'] ) ? 1 : 0;
    }

    if ( isset( $settings['renewal_window_day'] ) ) {
        $sanitized['renewal_window_day'] = max( 1, min( 31, absint( $settings['renewal_window_day'] ) ) );
    }

    if ( isset( $settings['renewal_window_month'] ) ) {
        $sanitized['renewal_window_month'] = max( 1, min( 12, absint( $settings['renewal_window_month'] ) ) );
    }
    
    return update_option( 'ufsc_woocommerce_settings', $sanitized );
}

/**
 * Validate UFSC season label.
 *
 * @param string $season Season label.
 * @return bool
 */
function ufsc_is_valid_season_label( $season ) {
    $season = sanitize_text_field( (string) $season );
    if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
        return false;
    }

    return ( (int) $matches[2] ) === ( (int) $matches[1] + 1 );
}

/**
 * Convert an admin date input (Y-m-d) to timestamp in WP timezone.
 *
 * @param string $date Date string.
 * @return int
 */
function ufsc_admin_date_to_wp_timestamp( $date ) {
    $date = sanitize_text_field( (string) $date );
    if ( '' === $date ) {
        return 0;
    }

    $timezone = wp_timezone();
    $dt       = date_create_immutable_from_format( 'Y-m-d H:i:s', $date . ' 00:00:00', $timezone );

    if ( false === $dt ) {
        return 0;
    }

    return (int) $dt->getTimestamp();
}

/**
 * Check if WooCommerce is active
 * 
 * @return bool True if WooCommerce is active
 */
function ufsc_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Validate WooCommerce product ID
 * 
 * @param int $product_id Product ID to validate
 * @return bool True if product exists
 */
function ufsc_validate_woocommerce_product( $product_id ) {
    if ( ! ufsc_is_woocommerce_active() ) {
        return false;
    }
    
    $product = wc_get_product( $product_id );
    return $product && $product->exists();
}

/**
 * Render WooCommerce settings page
 */
function ufsc_render_woocommerce_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    // Handle form submission
    if ( isset( $_POST['ufsc_save_woocommerce_settings'] ) && check_admin_referer( 'ufsc_woocommerce_settings' ) ) {
        $settings = array();
        $season_errors = array();
        
        if ( isset( $_POST['product_affiliation_id'] ) ) {
            $settings['product_affiliation_id'] = absint( $_POST['product_affiliation_id'] );
        }
        
        if ( isset( $_POST['product_license_id'] ) ) {
            $settings['product_license_id'] = absint( $_POST['product_license_id'] );
        }
        
        if ( isset( $_POST['included_licenses'] ) ) {
            $settings['included_licenses'] = absint( $_POST['included_licenses'] );
        }
        
        if ( isset( $_POST['season'] ) ) {
            $settings['season'] = sanitize_text_field( wp_unslash( $_POST['season'] ) );
        }

        if ( isset( $_POST['renewal_window_day'] ) ) {
            $settings['renewal_window_day'] = absint( $_POST['renewal_window_day'] );
        }

        if ( isset( $_POST['renewal_window_month'] ) ) {
            $settings['renewal_window_month'] = absint( $_POST['renewal_window_month'] );
        }
        
        if ( ufsc_save_woocommerce_settings( $settings ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Paramètres WooCommerce enregistrés avec succès.', 'ufsc-clubs' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Erreur lors de l\'enregistrement des paramètres WooCommerce.', 'ufsc-clubs' ) . '</p></div>';
        }

        if ( isset( $_POST['ufsc_current_season'] ) ) {
            $current_season = sanitize_text_field( wp_unslash( $_POST['ufsc_current_season'] ) );
            if ( ufsc_is_valid_season_label( $current_season ) ) {
                update_option( 'ufsc_current_season', $current_season );
            } else {
                $season_errors[] = __( 'Saison courante invalide (format attendu : YYYY-YYYY avec année N+1).', 'ufsc-clubs' );
            }
        }

        if ( isset( $_POST['ufsc_next_season'] ) ) {
            $next_season = sanitize_text_field( wp_unslash( $_POST['ufsc_next_season'] ) );
            if ( '' === $next_season ) {
                delete_option( 'ufsc_next_season' );
            } elseif ( ufsc_is_valid_season_label( $next_season ) ) {
                update_option( 'ufsc_next_season', $next_season );
            } else {
                $season_errors[] = __( 'Saison suivante invalide (format attendu : YYYY-YYYY avec année N+1).', 'ufsc-clubs' );
            }
        }

        if ( isset( $_POST['ufsc_renewal_window_start_date'] ) ) {
            $renewal_date = sanitize_text_field( wp_unslash( $_POST['ufsc_renewal_window_start_date'] ) );
            if ( '' === $renewal_date ) {
                delete_option( 'ufsc_renewal_window_start_ts' );
            } else {
                $renewal_ts = ufsc_admin_date_to_wp_timestamp( $renewal_date );
                if ( $renewal_ts > 0 ) {
                    update_option( 'ufsc_renewal_window_start_ts', $renewal_ts );
                } else {
                    $season_errors[] = __( 'Date d\'ouverture de renouvellement invalide.', 'ufsc-clubs' );
                }
            }
        }

        if ( ! empty( $season_errors ) ) {
            foreach ( $season_errors as $season_error ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $season_error ) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gestion des saisons enregistrée avec succès.', 'ufsc-clubs' ) . '</p></div>';
        }
    }
    
    $current_settings = ufsc_get_woocommerce_settings();
    $saved_current_season = get_option( 'ufsc_current_season', '' );
    $saved_next_season = get_option( 'ufsc_next_season', '' );
    $saved_renewal_start_ts = absint( get_option( 'ufsc_renewal_window_start_ts', 0 ) );
    $woocommerce_active = ufsc_is_woocommerce_active();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Paramètres WooCommerce - UFSC Gestion', 'ufsc-clubs' ); ?></h1>
        
        <?php if ( ! $woocommerce_active ): ?>
            <div class="notice notice-warning">
                <p><?php esc_html_e( 'WooCommerce n\'est pas activé. Certaines fonctionnalités ne seront pas disponibles.', 'ufsc-clubs' ); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'ufsc_woocommerce_settings' ); ?>
            
            <h2><?php esc_html_e( 'Produits WooCommerce', 'ufsc-clubs' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="product_affiliation_id"><?php esc_html_e( 'ID du produit pack affiliation', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="product_affiliation_id" name="product_affiliation_id" 
                               value="<?php echo esc_attr( $current_settings['product_affiliation_id'] ); ?>" 
                               class="regular-text" min="1" />
                        <p class="description">
                            <?php esc_html_e( 'ID du produit "Pack Affiliation UFSC" dans WooCommerce (par défaut: 4823)', 'ufsc-clubs' ); ?>
                            <?php if ( $woocommerce_active && ufsc_validate_woocommerce_product( $current_settings['product_affiliation_id'] ) ): ?>
                                <span style="color: green;">✓ Produit trouvé</span>
                            <?php elseif ( $woocommerce_active ): ?>
                                <span style="color: red;">✗ Produit non trouvé</span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="product_license_id"><?php esc_html_e( 'ID du produit licence additionnelle', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="product_license_id" name="product_license_id" 
                               value="<?php echo esc_attr( $current_settings['product_license_id'] ); ?>" 
                               class="regular-text" min="1" />
                        <p class="description">
                            <?php esc_html_e( 'ID du produit "Licence UFSC/ASPTT" dans WooCommerce (par défaut: 2934)', 'ufsc-clubs' ); ?>
                            <?php if ( $woocommerce_active && ufsc_validate_woocommerce_product( $current_settings['product_license_id'] ) ): ?>
                                <span style="color: green;">✓ Produit trouvé</span>
                            <?php elseif ( $woocommerce_active ): ?>
                                <span style="color: red;">✗ Produit non trouvé</span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h2><?php esc_html_e( 'Configuration des quotas', 'ufsc-clubs' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="included_licenses"><?php esc_html_e( 'Licences incluses par pack', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="included_licenses" name="included_licenses" 
                               value="<?php echo esc_attr( $current_settings['included_licenses'] ); ?>" 
                               class="regular-text" min="0" />
                        <p class="description">
                            <?php esc_html_e( 'Nombre de licences incluses dans chaque pack d\'affiliation (par défaut: 10)', 'ufsc-clubs' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="season"><?php esc_html_e( 'Saison courante', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="season" name="season" 
                               value="<?php echo esc_attr( $current_settings['season'] ); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e( 'Saison courante pour la gestion des quotas (par défaut: 2025-2026)', 'ufsc-clubs' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="renewal_window_day"><?php esc_html_e( 'Ouverture renouvellement', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="renewal_window_day" name="renewal_window_day" value="<?php echo esc_attr( $current_settings['renewal_window_day'] ); ?>" min="1" max="31" class="small-text" />
                        /
                        <input type="number" id="renewal_window_month" name="renewal_window_month" value="<?php echo esc_attr( $current_settings['renewal_window_month'] ); ?>" min="1" max="12" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Date (jour/mois) à partir de laquelle les boutons de renouvellement sont activés (par défaut 30/07).', 'ufsc-clubs' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Gestion des saisons', 'ufsc-clubs' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ufsc_current_season"><?php esc_html_e( 'Saison courante', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ufsc_current_season" name="ufsc_current_season"
                               value="<?php echo esc_attr( $saved_current_season ); ?>"
                               class="regular-text" pattern="\d{4}-\d{4}" placeholder="2025-2026" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ufsc_renewal_window_start_date"><?php esc_html_e( 'Date ouverture renouvellement', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="date" id="ufsc_renewal_window_start_date" name="ufsc_renewal_window_start_date"
                               value="<?php echo esc_attr( $saved_renewal_start_ts ? wp_date( 'Y-m-d', $saved_renewal_start_ts ) : '' ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ufsc_next_season"><?php esc_html_e( 'Saison suivante (optionnel)', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ufsc_next_season" name="ufsc_next_season"
                               value="<?php echo esc_attr( $saved_next_season ); ?>"
                               class="regular-text" pattern="\d{4}-\d{4}" placeholder="2026-2027" />
                        <p class="description"><?php esc_html_e( 'Laisser vide pour calcul automatique à partir de la saison courante.', 'ufsc-clubs' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button( __( 'Enregistrer les paramètres WooCommerce', 'ufsc-clubs' ), 'primary', 'ufsc_save_woocommerce_settings' ); ?>
        </form>
        
        <h2><?php esc_html_e( 'Informations sur l\'intégration WooCommerce', 'ufsc-clubs' ); ?></h2>
        <div class="notice notice-info">
            <p><strong><?php esc_html_e( 'Fonctionnement du système de quotas:', 'ufsc-clubs' ); ?></strong></p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><?php esc_html_e( 'Chaque pack d\'affiliation payé crédite le quota de licences incluses pour le club', 'ufsc-clubs' ); ?></li>
                <li><?php esc_html_e( 'Au-delà du quota inclus, les licences supplémentaires sont payantes', 'ufsc-clubs' ); ?></li>
                <li><?php esc_html_e( 'Le système génère automatiquement des commandes pour les licences additionnelles', 'ufsc-clubs' ); ?></li>
            </ul>
        </div>
    </div>
    <?php
}
