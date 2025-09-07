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
    $defaults = UFSC_SQL::default_settings();

    return array(
        'table_clubs'    => $defaults['table_clubs'],
        'table_licences' => $defaults['table_licences'],
    );
}

/**
 * Get current settings
 * 
 * @return array Current settings merged with defaults
 */
function ufsc_get_settings() {
    return UFSC_SQL::get_settings();
}

/**
 * Save settings
 * 
 * @param array $settings Settings to save
 * @return bool True on success
 */
function ufsc_save_settings( $settings ) {
    return UFSC_SQL::update_settings( $settings );
}

/**
 * Render settings page
 */
function ufsc_render_core_settings_page() {
    // Handle form submission
    if ( isset( $_POST['ufsc_save_settings'] ) && check_admin_referer( 'ufsc_sql_settings' ) ) {
        $settings = array();

        if ( isset( $_POST['table_clubs'] ) ) {
            $settings['table_clubs'] = sanitize_text_field( wp_unslash( $_POST['table_clubs'] ) );
        }

        if ( isset( $_POST['table_licences'] ) ) {
            $settings['table_licences'] = sanitize_text_field( wp_unslash( $_POST['table_licences'] ) );
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
            <?php wp_nonce_field( 'ufsc_sql_settings' ); ?>
            
            <h2><?php esc_html_e( 'Configuration des tables', 'ufsc-clubs' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="table_clubs"><?php esc_html_e( 'Table des clubs', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="table_clubs" name="table_clubs"
                               value="<?php echo esc_attr( $current_settings['table_clubs'] ); ?>"
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e( 'Nom de la table contenant les données des clubs. Caractères autorisés: A-Z, a-z, 0-9, _', 'ufsc-clubs' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="table_licences"><?php esc_html_e( 'Table des licences', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="table_licences" name="table_licences"
                               value="<?php echo esc_attr( $current_settings['table_licences'] ); ?>"
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