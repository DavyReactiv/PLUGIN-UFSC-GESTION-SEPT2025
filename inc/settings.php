<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Settings module for UFSC Gestion
 * Handles core settings including tables and regions
 */

/**
 * Get default settings
 * 
 * @return array Default settings
 */
function ufsc_get_default_settings() {
    global $wpdb;
    
    return array(
        'clubs_table' => $wpdb->prefix . 'ufsc_clubs',
        'licences_table' => $wpdb->prefix . 'ufsc_licences'
    );
}

/**
 * Get current settings
 * 
 * @return array Current settings merged with defaults
 */
function ufsc_get_settings() {
    $defaults = ufsc_get_default_settings();
    $saved = get_option( 'ufsc_gestion_settings', array() );
    
    return wp_parse_args( $saved, $defaults );
}

/**
 * Save settings
 * 
 * @param array $settings Settings to save
 * @return bool True on success
 */
function ufsc_save_settings( $settings ) {
    // Sanitize table names
    if ( isset( $settings['clubs_table'] ) ) {
        $settings['clubs_table'] = ufsc_sanitize_table_name( $settings['clubs_table'] );
    }
    
    if ( isset( $settings['licences_table'] ) ) {
        $settings['licences_table'] = ufsc_sanitize_table_name( $settings['licences_table'] );
    }
    
    return update_option( 'ufsc_gestion_settings', $settings );
}

/**
 * Render settings page
 */


if ( ! function_exists( 'ufsc_is_valid_season_label' ) ) {
    /**
     * Validate UFSC season label (YYYY-YYYY with consecutive years).
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
}

if ( ! function_exists( 'ufsc_admin_date_to_wp_timestamp' ) ) {
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
}
function ufsc_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    // Handle form submission
    if ( isset( $_POST['ufsc_save_settings'] ) && check_admin_referer( 'ufsc_gestion_settings' ) ) {
        $settings      = array();
        $season_errors = array();

        if ( isset( $_POST['clubs_table'] ) ) {
            $settings['clubs_table'] = sanitize_text_field( wp_unslash( $_POST['clubs_table'] ) );
        }

        if ( isset( $_POST['licences_table'] ) ) {
            $settings['licences_table'] = sanitize_text_field( wp_unslash( $_POST['licences_table'] ) );
        }

        if ( ufsc_save_settings( $settings ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Paramètres enregistrés avec succès.', 'ufsc-clubs' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Erreur lors de l\'enregistrement des paramètres.', 'ufsc-clubs' ) . '</p></div>';
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
                    update_option( 'ufsc_renewal_window_start_ts', (int) $renewal_ts );
                } else {
                    $season_errors[] = __( "Date d'ouverture de renouvellement invalide.", 'ufsc-clubs' );
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

    $current_settings      = ufsc_get_settings();
    $saved_current_season  = (string) get_option( 'ufsc_current_season', '' );
    $saved_next_season     = (string) get_option( 'ufsc_next_season', '' );
    $saved_renewal_start_ts = absint( get_option( 'ufsc_renewal_window_start_ts', 0 ) );
    $effective_season      = function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '';
    $effective_renewal_ts  = function_exists( 'ufsc_get_renewal_window_start_ts' ) ? absint( ufsc_get_renewal_window_start_ts() ) : 0;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Paramètres UFSC Gestion', 'ufsc-clubs' ); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'ufsc_gestion_settings' ); ?>
            
            <h2><?php esc_html_e( 'Configuration des tables', 'ufsc-clubs' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="clubs_table"><?php esc_html_e( 'Table des clubs', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="clubs_table" name="clubs_table" 
                               value="<?php echo esc_attr( $current_settings['clubs_table'] ); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e( 'Nom de la table contenant les données des clubs. Caractères autorisés: A-Z, a-z, 0-9, _', 'ufsc-clubs' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="licences_table"><?php esc_html_e( 'Table des licences', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="licences_table" name="licences_table" 
                               value="<?php echo esc_attr( $current_settings['licences_table'] ); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e( 'Nom de la table contenant les données des licences. Caractères autorisés: A-Z, a-z, 0-9, _', 'ufsc-clubs' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h2><?php esc_html_e( 'Régions disponibles', 'ufsc-clubs' ); ?></h2>
            <p><?php esc_html_e( 'Les régions suivantes sont configurées dans le système et communes à tous les formulaires:', 'ufsc-clubs' ); ?></p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <?php foreach ( ufsc_get_regions_labels() as $region ): ?>
                    <li><?php echo esc_html( $region ); ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="description">
                <?php esc_html_e( 'Cette liste est fixe et ne peut pas être modifiée. Elle garantit la cohérence entre les formulaires Clubs et Licences.', 'ufsc-clubs' ); ?>
            </p>
            

            <h2><?php esc_html_e( 'Gestion des saisons', 'ufsc-clubs' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ufsc_current_season"><?php esc_html_e( 'Saison courante', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ufsc_current_season" name="ufsc_current_season"
                               value="<?php echo esc_attr( $saved_current_season ); ?>"
                               class="regular-text" pattern="\d{4}-\d{4}" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ufsc_renewal_window_start_date"><?php esc_html_e( 'Date ouverture renouvellement', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="date" id="ufsc_renewal_window_start_date" name="ufsc_renewal_window_start_date"
                               value="<?php echo esc_attr( $saved_renewal_start_ts > 0 ? wp_date( 'Y-m-d', $saved_renewal_start_ts ) : '' ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ufsc_next_season"><?php esc_html_e( 'Saison suivante (optionnel)', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ufsc_next_season" name="ufsc_next_season"
                               value="<?php echo esc_attr( $saved_next_season ); ?>"
                               class="regular-text" pattern="\d{4}-\d{4}" />
                        <p class="description"><?php esc_html_e( 'Laisser vide pour calcul automatique à partir de la saison courante.', 'ufsc-clubs' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Récapitulatif', 'ufsc-clubs' ); ?></h2>
            <p>
                <strong><?php esc_html_e( 'Saison effective:', 'ufsc-clubs' ); ?></strong>
                <?php echo esc_html( $effective_season ); ?>
                <br />
                <strong><?php esc_html_e( 'Ouverture renouvellement:', 'ufsc-clubs' ); ?></strong>
                <?php echo esc_html( $effective_renewal_ts > 0 ? wp_date( 'd/m/Y', $effective_renewal_ts ) : '—' ); ?>
            </p>

            <?php submit_button( __( 'Enregistrer les paramètres', 'ufsc-clubs' ), 'primary', 'ufsc_save_settings' ); ?>
        </form>
    </div>
    <?php
}