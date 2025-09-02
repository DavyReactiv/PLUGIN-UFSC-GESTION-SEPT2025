<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_SQL_Public {
    
    /**
     * Render licence form shortcode
     */
    public static function render_licence_form() {
        // Enqueue front-end assets
        wp_enqueue_style('ufsc-frontend');
        wp_enqueue_script('ufsc-frontend');
        
        $settings = UFSC_SQL::get_settings();
        $licence_fields = $settings['licence_fields'];
        
        // Get clubs for dropdown
        global $wpdb;
        $clubs_table = $settings['table_clubs'];
        $clubs = $wpdb->get_results("SELECT id, nom FROM `$clubs_table` WHERE statut != 'desactive' ORDER BY nom");
        
        echo '<div class="ufsc-public-form">';
        echo '<h3>' . esc_html__('Demande de licence', 'ufsc-clubs') . '</h3>';
        
        // Show success/error messages
        if (isset($_GET['ufsc_message'])) {
            $message = sanitize_text_field($_GET['ufsc_message']);
            if ($message === 'success') {
                echo '<div class="ufsc-alert ufsc-alert-success">' . esc_html__('Votre demande de licence a été soumise avec succès. Elle sera examinée prochainement.', 'ufsc-clubs') . '</div>';
            } elseif ($message === 'error') {
                echo '<div class="ufsc-alert ufsc-alert-error">' . esc_html__('Une erreur est survenue. Veuillez réessayer.', 'ufsc-clubs') . '</div>';
            }
        }
        
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('ufsc_sql_public_save_licence');
        echo '<input type="hidden" name="action" value="ufsc_sql_public_save_licence" />';
        
        echo '<div class="ufsc-grid">';
        
        // Club selection
        echo '<div class="ufsc-field">';
        echo '<label>' . esc_html__('Club', 'ufsc-clubs') . ' *</label>';
        echo '<select name="club_id" required>';
        echo '<option value="">' . esc_html__('Sélectionnez un club', 'ufsc-clubs') . '</option>';
        foreach ($clubs as $club) {
            echo '<option value="' . (int)$club->id . '">' . esc_html($club->nom) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Render other fields (excluding club_id and admin fields)
        $excluded_fields = array('club_id', 'statut', 'responsable_id', 'date_inscription', 'is_included');
        foreach ($licence_fields as $k => $conf) {
            if (in_array($k, $excluded_fields)) continue;
            
            $label = $conf[0];
            $type = $conf[1];
            $required = in_array($k, array('nom', 'prenom', 'email')) ? 'required' : '';
            
            echo '<div class="ufsc-field">';
            echo '<label>' . esc_html($label);
            if ($required) echo ' *';
            echo '</label>';
            
            self::render_public_field($k, $type, '', $required);
            echo '</div>';
        }
        
        echo '</div>'; // end ufsc-grid
        
        echo '<p class="ufsc-form-submit">';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Soumettre la demande', 'ufsc-clubs') . '</button>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>'; // end ufsc-public-form
    }
    
    /**
     * Render individual field for public form
     */
    private static function render_public_field($name, $type, $value = '', $required = '') {
        $value = esc_attr($value);
        
        switch ($type) {
            case 'textarea':
                echo '<textarea name="' . esc_attr($name) . '" rows="3" ' . $required . '>' . esc_textarea($value) . '</textarea>';
                break;
                
            case 'number':
                echo '<input type="number" name="' . esc_attr($name) . '" value="' . $value . '" ' . $required . ' />';
                break;
                
            case 'date':
                echo '<input type="date" name="' . esc_attr($name) . '" value="' . $value . '" ' . $required . ' />';
                break;
                
            case 'sex':
                echo '<select name="' . esc_attr($name) . '" ' . $required . '>';
                echo '<option value="">' . esc_html__('Sélectionner', 'ufsc-clubs') . '</option>';
                echo '<option value="M"' . selected($value, 'M', false) . '>' . esc_html__('Masculin', 'ufsc-clubs') . '</option>';
                echo '<option value="F"' . selected($value, 'F', false) . '>' . esc_html__('Féminin', 'ufsc-clubs') . '</option>';
                echo '</select>';
                break;
                
            case 'region':
                echo '<select name="' . esc_attr($name) . '" ' . $required . '>';
                echo '<option value="">' . esc_html__('Sélectionner une région', 'ufsc-clubs') . '</option>';
                foreach (UFSC_CL_Utils::regions() as $region) {
                    echo '<option value="' . esc_attr($region) . '"' . selected($value, $region, false) . '>' . esc_html($region) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'bool':
                echo '<label class="ufsc-checkbox">';
                echo '<input type="hidden" name="' . esc_attr($name) . '" value="0" />';
                echo '<input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked($value, '1', false) . ' />';
                echo '<span class="checkmark"></span>';
                echo '</label>';
                break;
                
            case 'licence_status':
                // For public forms, we don't show status selection
                echo '<input type="hidden" name="' . esc_attr($name) . '" value="en_attente" />';
                break;
                
            case 'certificat_upload':
                echo '<input type="file" name="certificat_upload" accept=".pdf,.jpg,.jpeg,.png" />';
                echo '<p class="description">' . esc_html__('Formats acceptés: PDF, JPG, PNG', 'ufsc-clubs') . '</p>';
                break;
                
            default:
                echo '<input type="text" name="' . esc_attr($name) . '" value="' . $value . '" ' . $required . ' />';
        }
    }
    
    /**
     * Render my club profile shortcode
     */
    public static function render_my_club() {
        if (!is_user_logged_in()) {
            echo '<div class="ufsc-alert">' . esc_html__('Vous devez être connecté pour voir votre profil club.', 'ufsc-clubs') . '</div>';
            return;
        }
        
        // Enqueue front-end assets
        wp_enqueue_style('ufsc-frontend');
        wp_enqueue_script('ufsc-frontend');
        
        $user_id = get_current_user_id();
        $settings = UFSC_SQL::get_settings();
        
        global $wpdb;
        $clubs_table = $settings['table_clubs'];
        $licences_table = $settings['table_licences'];
        
        // Get user's club (where user is responsable)
        $club = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$clubs_table` WHERE responsable_id = %d",
            $user_id
        ));
        
        if (!$club) {
            echo '<div class="ufsc-alert">' . esc_html__('Aucun club trouvé pour votre compte.', 'ufsc-clubs') . '</div>';
            return;
        }
        
        // Get club licences
        $licences = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `$licences_table` WHERE club_id = %d ORDER BY nom, prenom",
            $club->id
        ));
        
        echo '<div class="ufsc-my-club">';
        echo '<h3>' . esc_html($club->nom) . '</h3>';
        
        // Club info summary
        echo '<div class="ufsc-club-summary">';
        echo '<div class="ufsc-club-info">';
        echo '<p><strong>' . esc_html__('Région:', 'ufsc-clubs') . '</strong> ' . esc_html($club->region) . '</p>';
        echo '<p><strong>' . esc_html__('Statut:', 'ufsc-clubs') . '</strong> ' . UFSC_CL_Utils::esc_badge($club->statut, self::get_status_class($club->statut)) . '</p>';
        if ($club->quota_licences) {
            echo '<p><strong>' . esc_html__('Quota licences:', 'ufsc-clubs') . '</strong> ' . (int)$club->quota_licences . '</p>';
        }
        echo '</div>';
        echo '</div>';
        
        // Licences list
        echo '<h4>' . esc_html__('Licences du club', 'ufsc-clubs') . ' (' . count($licences) . ')</h4>';
        
        if ($licences) {
            echo '<div class="ufsc-licences-table">';
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Nom', 'ufsc-clubs') . '</th>';
            echo '<th>' . esc_html__('Prénom', 'ufsc-clubs') . '</th>';
            echo '<th>' . esc_html__('Email', 'ufsc-clubs') . '</th>';
            echo '<th>' . esc_html__('Statut', 'ufsc-clubs') . '</th>';
            echo '<th>' . esc_html__('Date inscription', 'ufsc-clubs') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($licences as $licence) {
                echo '<tr>';
                echo '<td>' . esc_html($licence->nom) . '</td>';
                echo '<td>' . esc_html($licence->prenom) . '</td>';
                echo '<td>' . esc_html($licence->email) . '</td>';
                echo '<td>' . UFSC_CL_Utils::esc_badge($licence->statut, self::get_status_class($licence->statut)) . '</td>';
                echo '<td>' . esc_html(mysql2date('d/m/Y', $licence->date_inscription)) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<p>' . esc_html__('Aucune licence trouvée pour ce club.', 'ufsc-clubs') . '</p>';
        }
        
        echo '</div>'; // end ufsc-my-club
    }
    
    /**
     * Handle public licence form submission
     */
    public static function handle_save_licence() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ufsc_sql_public_save_licence')) {
            wp_die('Sécurité: Nonce invalide');
        }
        
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_licences'];
        $fields = $settings['licence_fields'];
        
        // Sanitize input data
        $data = array();
        foreach ($fields as $k => $conf) {
            if (isset($_POST[$k])) {
                $value = $_POST[$k];
                if ($conf[1] === 'bool') {
                    $data[$k] = (int)$value;
                } elseif ($conf[1] === 'number') {
                    $data[$k] = (int)$value;
                } else {
                    $data[$k] = sanitize_text_field($value);
                }
            }
        }
        
        // Set default values for public submissions
        $data['statut'] = 'en_attente';
        $data['date_inscription'] = current_time('mysql');
        $data['is_included'] = 1;
        
        // Handle certificate upload
        if (!empty($_FILES['certificat_upload']['name'])) {
            $upload = wp_handle_upload($_FILES['certificat_upload'], array('test_form' => false));
            if (!empty($upload['url'])) {
                $data['certificat_url'] = esc_url_raw($upload['url']);
            }
        }
        
        // Validate required fields
        $required_fields = array('club_id', 'nom', 'prenom', 'email');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                wp_safe_redirect(add_query_arg('ufsc_message', 'error', wp_get_referer()));
                exit;
            }
        }
        
        // Insert licence
        $result = $wpdb->insert($table, $data);
        
        if ($result !== false) {
            wp_safe_redirect(add_query_arg('ufsc_message', 'success', wp_get_referer()));
        } else {
            wp_safe_redirect(add_query_arg('ufsc_message', 'error', wp_get_referer()));
        }
        exit;
    }
    
    /**
     * Get CSS class for status badge
     */
    private static function get_status_class($status) {
        switch ($status) {
            case 'valide':
                return 'success';
            case 'en_attente':
                return 'wait';
            case 'a_regler':
                return 'wait';
            case 'desactive':
                return 'off';
            default:
                return 'info';
        }
    }
}