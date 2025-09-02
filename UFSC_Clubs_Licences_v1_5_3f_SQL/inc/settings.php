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
function ufsc_render_settings_page() {
    // Handle form submission
    if ( isset( $_POST['ufsc_save_settings'] ) && check_admin_referer( 'ufsc_gestion_settings' ) ) {
        $settings = array();
        
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
    }
    
    $current_settings = ufsc_get_settings();
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
            
            <?php submit_button( __( 'Enregistrer les paramètres', 'ufsc-clubs' ), 'primary', 'ufsc_save_settings' ); ?>
        </form>
    </div>
    <?php
}