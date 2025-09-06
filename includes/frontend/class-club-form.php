<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Frontend Club Form class
 */
class UFSC_CL_Club_Form {
    
    /**
     * Initialize the form class
     */
    public static function init() {
        add_shortcode( 'ufsc_club_form', array( __CLASS__, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
    }
    
    /**
     * Render the club form shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Form HTML
     */
    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'affiliation' => '0',
            'club_id' => '0'
        ), $atts, 'ufsc_club_form' );
        
        $affiliation = (bool) $atts['affiliation'];
        $club_id = (int) $atts['club_id'];
        
        // Check permissions for edit mode
        if ( $club_id > 0 && ! UFSC_CL_Permissions::ufsc_user_can_edit_club( $club_id ) ) {
            return '<div class="ufsc-alert error">' . 
                   esc_html__( 'Vous n\'avez pas les permissions pour éditer ce club.', 'ufsc-clubs' ) . 
                   '</div>';
        }
        
        // Check permissions for create mode
        if ( $club_id === 0 && ! UFSC_CL_Permissions::ufsc_user_can_create_club() ) {
            return '<div class="ufsc-alert error">' . 
                   esc_html__( 'Vous devez être connecté pour créer un club.', 'ufsc-clubs' ) . 
                   '</div>';
        }
        
        // Load club data for edit mode
        $club_data = null;
        if ( $club_id > 0 ) {
            global $wpdb;
            $settings = UFSC_SQL::get_settings();
            $table = $settings['table_clubs'];
            $pk = $settings['pk_club'];
            
            $club_data = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE `{$pk}` = %d",
                $club_id
            ), ARRAY_A );
            
            if ( ! $club_data ) {
                return '<div class="ufsc-alert error">' . 
                       esc_html__( 'Club non trouvé.', 'ufsc-clubs' ) . 
                       '</div>';
            }
        }
        
        // Enqueue assets for this form
        self::$form_rendered = true;
        
        ob_start();
        self::render_form( $affiliation, $club_id, $club_data );
        return ob_get_clean();
    }
    
    /**
     * Flag to track if form is rendered on page
     */
    private static $form_rendered = false;
    
    /**
     * Conditionally enqueue assets if form is on page
     */
    public static function maybe_enqueue_assets() {
        global $post;
        
        // Check if shortcode is in content
        if ( $post && has_shortcode( $post->post_content, 'ufsc_club_form' ) ) {
            self::enqueue_assets();
        }
    }
    
    /**
     * Enqueue form assets
     */
    public static function enqueue_assets() {
        wp_enqueue_script( 
            'ufsc-club-form', 
            UFSC_CL_URL . 'assets/frontend/js/ufsc-club-form.js',
            array( 'jquery' ),
            UFSC_CL_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ufsc-club-form',
            UFSC_CL_URL . 'assets/frontend/css/ufsc-club-form.css',
            array(),
            UFSC_CL_VERSION
        );
    }
    
    /**
     * Render the complete form
     * 
     * @param bool $affiliation Whether this is affiliation mode
     * @param int $club_id Club ID for edit mode (0 for create)
     * @param array|null $club_data Existing club data
     */
    private static function render_form( $affiliation, $club_id, $club_data ) {
        $is_edit = $club_id > 0;
        $statuses = UFSC_SQL::statuses();
        $regions = UFSC_CL_Utils::regions();
        
        // Get default status for new clubs
        $default_status = $is_edit ? ($club_data['statut'] ?? '') : self::get_default_status();
        
        // Display messages
        self::display_messages();
        ?>

        <div class="ufsc-club-form-container">
            <div class="ufsc-notices" aria-live="polite"></div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ufsc-club-form">
                <?php wp_nonce_field( 'ufsc_save_club', 'ufsc_club_nonce' ); ?>
                <input type="hidden" name="action" value="ufsc_save_club" />
                <input type="hidden" name="club_id" value="<?php echo esc_attr( $club_id ); ?>" />
                <input type="hidden" name="affiliation" value="<?php echo esc_attr( $affiliation ? '1' : '0' ); ?>" />
                
                <div role="status" aria-live="polite" class="ufsc-form-status" id="ufsc-form-status"></div>
                
                <?php if ( $affiliation ): ?>
                    <h2><?php esc_html_e( 'Demande d\'affiliation', 'ufsc-clubs' ); ?></h2>
                <?php else: ?>
                    <h2><?php echo $is_edit ? esc_html__( 'Éditer le club', 'ufsc-clubs' ) : esc_html__( 'Créer un club', 'ufsc-clubs' ); ?></h2>
                <?php endif; ?>
                
                <!-- General Information Section -->
                <fieldset class="ufsc-form-section ufsc-grid">
                    <legend><?php esc_html_e( 'Informations générales', 'ufsc-clubs' ); ?></legend>

                    <div class="ufsc-field">
                        <label for="profile_photo" class="ufsc-label"><?php esc_html_e( 'Photo du club', 'ufsc-clubs' ); ?></label>
                        <?php if ( ! empty( $club_data['profile_photo_url'] ) ) : ?>
                            <div class="ufsc-profile-photo-preview">
                                <img src="<?php echo esc_url( $club_data['profile_photo_url'] ); ?>" alt="<?php esc_attr_e( 'Photo du club', 'ufsc-clubs' ); ?>" />
                            </div>
                            <div class="ufsc-upload-actions">
                                <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" />
                                <button type="submit" name="remove_profile_photo" value="1" class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Supprimer la photo', 'ufsc-clubs' ); ?></button>
                            </div>
                        <?php else : ?>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" />
                        <?php endif; ?>
                    <div class="ufsc-field-error" aria-live="polite"></div></div>

                    <div class="ufsc-field">
                        <label for="nom" class="ufsc-label required"><?php esc_html_e( 'Nom du club', 'ufsc-clubs' ); ?></label>
                        <input type="text" id="nom" name="nom" value="<?php echo esc_attr( $club_data['nom'] ?? '' ); ?>" required />
                    <div class="ufsc-field-error" aria-live="polite"></div></div>
                    
                    <div class="ufsc-field">
                        <label for="region" class="ufsc-label required"><?php esc_html_e( 'Région', 'ufsc-clubs' ); ?></label>
                        <select id="region" name="region" required>
                            <option value=""><?php esc_html_e( 'Sélectionner une région', 'ufsc-clubs' ); ?></option>
                            <?php foreach ( $regions as $region ): ?>
                                <option value="<?php echo esc_attr( $region ); ?>" <?php selected( $club_data['region'] ?? '', $region ); ?>>
                                    <?php echo esc_html( $region ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <div class="ufsc-field-error" aria-live="polite"></div></div>
                    
                    <div class="ufsc-field">
                        <label for="adresse" class="ufsc-label required"><?php esc_html_e( 'Adresse', 'ufsc-clubs' ); ?></label>
                        <input type="text" id="adresse" name="adresse" value="<?php echo esc_attr( $club_data['adresse'] ?? '' ); ?>" required />
                    <div class="ufsc-field-error" aria-live="polite"></div></div>
                    
                    <div class="ufsc-field">
                        <label for="complement_adresse" class="ufsc-label"><?php esc_html_e( 'Complément d\'adresse', 'ufsc-clubs' ); ?></label>
                        <input type="text" id="complement_adresse" name="complement_adresse" value="<?php echo esc_attr( $club_data['complement_adresse'] ?? '' ); ?>" />
                    <div class="ufsc-field-error" aria-live="polite"></div></div>
                    
                    <div class="ufsc-grid">
                        <div class="ufsc-field">
                            <label for="code_postal" class="ufsc-label required"><?php esc_html_e( 'Code postal', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="code_postal" name="code_postal" value="<?php echo esc_attr( $club_data['code_postal'] ?? '' ); ?>" pattern="\d{5}" required />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                        <div class="ufsc-field">
                            <label for="ville" class="ufsc-label required"><?php esc_html_e( 'Ville', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="ville" name="ville" value="<?php echo esc_attr( $club_data['ville'] ?? '' ); ?>" required />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    </div>
                    
                    <div class="ufsc-grid">
                        <div class="ufsc-field">
                            <label for="email" class="ufsc-label required"><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></label>
                            <input type="email" id="email" name="email" value="<?php echo esc_attr( $club_data['email'] ?? '' ); ?>" required />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                        <div class="ufsc-field">
                            <label for="telephone" class="ufsc-label required"><?php esc_html_e( 'Téléphone', 'ufsc-clubs' ); ?></label>
                            <input type="tel" id="telephone" name="telephone" value="<?php echo esc_attr( $club_data['telephone'] ?? '' ); ?>" required />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    </div>
                </fieldset>
                

                <!-- Logo & Web Section -->
                <fieldset class="ufsc-form-section ufsc-grid">
                    <legend><?php esc_html_e( 'Logo & Web', 'ufsc-clubs' ); ?></legend>
                    
                    <div class="ufsc-field">
                        <label for="logo_upload" class="ufsc-label"><?php esc_html_e( 'Logo du club', 'ufsc-clubs' ); ?></label>
                        <input type="file" id="logo_upload" name="logo_upload" accept=".jpg,.jpeg,.png,.gif" />
                        <p class="ufsc-description"><?php esc_html_e( 'Formats acceptés : JPG, PNG, GIF. Taille max : 2 MB', 'ufsc-clubs' ); ?></p>
                        <?php if ( ! empty( $club_data['logo_url'] ) && UFSC_CL_Permissions::ufsc_user_can_edit_club( $club_id ) ): ?>
                            <p class="ufsc-current-file">
                                <?php esc_html_e( 'Fichier actuel :', 'ufsc-clubs' ); ?>
                                <a href="<?php echo esc_url( $club_data['logo_url'] ); ?>" target="_blank"><?php esc_html_e( 'Voir le logo', 'ufsc-clubs' ); ?></a>
                            </p>
                        <?php endif; ?>
                    <div class="ufsc-field-error" aria-live="polite"></div></div>
                    
                    <div class="ufsc-field">
                        <label for="url_site" class="ufsc-label"><?php esc_html_e( 'Site web', 'ufsc-clubs' ); ?></label>
                        <input type="url" id="url_site" name="url_site" value="<?php echo esc_attr( $club_data['url_site'] ?? '' ); ?>" />
                    <div class="ufsc-field-error" aria-live="polite"></div></div>
                    
                    <div class="ufsc-grid">
                        <div class="ufsc-field">
                            <label for="url_facebook" class="ufsc-label"><?php esc_html_e( 'Facebook', 'ufsc-clubs' ); ?></label>
                            <input type="url" id="url_facebook" name="url_facebook" value="<?php echo esc_attr( $club_data['url_facebook'] ?? '' ); ?>" />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                        <div class="ufsc-field">
                            <label for="url_instagram" class="ufsc-label"><?php esc_html_e( 'Instagram', 'ufsc-clubs' ); ?></label>
                            <input type="url" id="url_instagram" name="url_instagram" value="<?php echo esc_attr( $club_data['url_instagram'] ?? '' ); ?>" />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    </div>
                </fieldset>
                

                <!-- Legal & Financial Section -->
                <fieldset class="ufsc-form-section ufsc-grid">
                    <legend><?php esc_html_e( 'Informations légales et financières', 'ufsc-clubs' ); ?></legend>
                    
                    <div class="ufsc-grid">
                        <div class="ufsc-field">
                            <label for="num_declaration" class="ufsc-label required"><?php esc_html_e( 'N° de déclaration', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="num_declaration" name="num_declaration" value="<?php echo esc_attr( $club_data['num_declaration'] ?? '' ); ?>" required />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                        <div class="ufsc-field">
                            <label for="date_declaration" class="ufsc-label required"><?php esc_html_e( 'Date de déclaration', 'ufsc-clubs' ); ?></label>
                            <input type="date" id="date_declaration" name="date_declaration" value="<?php echo esc_attr( $club_data['date_declaration'] ?? '' ); ?>" required />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    </div>
                    
                    <div class="ufsc-grid">
                        <div class="ufsc-field">
                            <label for="siren" class="ufsc-label"><?php esc_html_e( 'SIREN', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="siren" name="siren" value="<?php echo esc_attr( $club_data['siren'] ?? '' ); ?>" />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                        <div class="ufsc-field">
                            <label for="rna_number" class="ufsc-label"><?php esc_html_e( 'RNA', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="rna_number" name="rna_number" value="<?php echo esc_attr( $club_data['rna_number'] ?? '' ); ?>" />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    </div>
                    
                    <div class="ufsc-field">
                        <label for="iban" class="ufsc-label"><?php esc_html_e( 'IBAN', 'ufsc-clubs' ); ?></label>
                        <input type="text" id="iban" name="iban" value="<?php echo esc_attr( $club_data['iban'] ?? '' ); ?>" />
                    <div class="ufsc-field-error" aria-live="polite"></div></div>
                    
                    <div class="ufsc-grid">
                        <div class="ufsc-field">
                            <label for="ape" class="ufsc-label"><?php esc_html_e( 'APE', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="ape" name="ape" value="<?php echo esc_attr( $club_data['ape'] ?? '' ); ?>" />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                        <div class="ufsc-field">
                            <label for="ccn" class="ufsc-label"><?php esc_html_e( 'CCN', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="ccn" name="ccn" value="<?php echo esc_attr( $club_data['ccn'] ?? '' ); ?>" />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    </div>
                    
                    <div class="ufsc-field">
                        <label for="ancv" class="ufsc-label"><?php esc_html_e( 'ANCV', 'ufsc-clubs' ); ?></label>
                        <input type="text" id="ancv" name="ancv" value="<?php echo esc_attr( $club_data['ancv'] ?? '' ); ?>" />
                    <div class="ufsc-field-error" aria-live="polite"></div></div>
                </fieldset>
                

                <!-- Legal Documents Section -->
                <fieldset class="ufsc-form-section ufsc-grid">
                    <legend><?php esc_html_e( 'Documents légaux', 'ufsc-clubs' ); ?></legend>

                    <?php
                    ?>
                <!-- Documents Section -->
                <fieldset class="ufsc-form-section">
                    <legend><?php esc_html_e( 'Mes documents', 'ufsc-clubs' ); ?></legend>

                    <?php

                    $documents = array(
                        'doc_statuts' => array( 'label' => __( 'Statuts', 'ufsc-clubs' ), 'required' => $affiliation ),
                        'doc_recepisse' => array( 'label' => __( 'Récépissé', 'ufsc-clubs' ), 'required' => $affiliation ),
                        'doc_jo' => array( 'label' => __( 'Journal Officiel', 'ufsc-clubs' ), 'required' => false ),
                        'doc_pv_ag' => array( 'label' => __( 'PV AG', 'ufsc-clubs' ), 'required' => false ),
                        'doc_cer' => array( 'label' => __( 'CER', 'ufsc-clubs' ), 'required' => $affiliation ),
                        'doc_attestation_cer' => array( 'label' => __( 'Attestation CER', 'ufsc-clubs' ), 'required' => false )
                    );

                    foreach ( $documents as $doc_key => $doc_info ):
                        $upload_key = str_replace( 'doc_', '', (string) ( $doc_key ?? '' ) ) . '_upload';
                    ?>
                        <div class="ufsc-field">
                            <label for="<?php echo esc_attr( $upload_key ); ?>" class="ufsc-label <?php echo $doc_info['required'] ? 'required' : ''; ?>">
                                <?php echo esc_html( $doc_info['label'] ); ?>
                            </label>

                            <input type="file" id="<?php echo esc_attr( $upload_key ); ?>" name="<?php echo esc_attr( $upload_key ); ?>" accept=".pdf,.jpg,.jpeg,.png" <?php echo $doc_info['required'] ? 'required' : ''; ?> />
                            <p class="ufsc-description"><?php esc_html_e( 'Formats acceptés : PDF, JPG, PNG. Taille max : 5 MB', 'ufsc-clubs' ); ?></p>
                            <?php if ( ! empty( $club_data[$doc_key] ) && UFSC_CL_Permissions::ufsc_user_can_edit_club( $club_id ) ): ?>

                            <input type="file"
                                   id="<?php echo esc_attr( $upload_key ); ?>"
                                   name="<?php echo esc_attr( $upload_key ); ?>"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   data-max-size="5242880"
                                   <?php echo $doc_info['required'] ? 'required' : ''; ?> />
                            <div class="ufsc-field-error" aria-live="polite"></div>
                            <?php if ( ! empty( $club_data[$doc_key] ) ): ?>

                                <p class="ufsc-current-file">
                                    <?php esc_html_e( 'Fichier actuel :', 'ufsc-clubs' ); ?>
                                    <a href="<?php echo esc_url( $club_data[$doc_key] ); ?>" target="_blank"><?php esc_html_e( 'Voir le document', 'ufsc-clubs' ); ?></a>
                                </p>
                            <?php endif; ?>
                            <?php endif; ?>
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    <?php endforeach; ?>
                </fieldset>
                
                <!-- Dirigeants Section -->
                <fieldset class="ufsc-form-section ufsc-dirigeants">
                    <legend><?php esc_html_e( 'Dirigeants', 'ufsc-clubs' ); ?></legend>

                    <?php
                    $dirigeants_info = array(
                        'president'  => array( 'label' => __( 'Président', 'ufsc-clubs' ),  'required' => true ),
                        'secretaire' => array( 'label' => __( 'Secrétaire', 'ufsc-clubs' ), 'required' => true ),
                        'tresorier'  => array( 'label' => __( 'Trésorier', 'ufsc-clubs' ),  'required' => true ),
                        'entraineur' => array( 'label' => __( 'Entraîneur', 'ufsc-clubs' ), 'required' => false )
                    );

                    foreach ( $dirigeants_info as $dirigeant => $info ) :
                    ?>
                        <div class="ufsc-dirigeant-section">
                            <h4><?php echo esc_html( $info['label'] ); ?> <?php echo $info['required'] ? '<span class="required">*</span>' : ''; ?></h4>

                            <div class="ufsc-field">
                                <label for="<?php echo esc_attr( $dirigeant ); ?>_prenom" class="ufsc-label <?php echo $info['required'] ? 'required' : ''; ?>"><?php esc_html_e( 'Prénom', 'ufsc-clubs' ); ?></label>
                                <input type="text" id="<?php echo esc_attr( $dirigeant ); ?>_prenom" name="<?php echo esc_attr( $dirigeant ); ?>_prenom" value="<?php echo esc_attr( $club_data[ $dirigeant . '_prenom' ] ?? '' ); ?>" <?php echo $info['required'] ? 'required' : ''; ?> />
                                <div class="ufsc-field-error" aria-live="polite"></div>
                            </div>

                            <div class="ufsc-field">
                                <label for="<?php echo esc_attr( $dirigeant ); ?>_nom" class="ufsc-label <?php echo $info['required'] ? 'required' : ''; ?>"><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></label>
                                <input type="text" id="<?php echo esc_attr( $dirigeant ); ?>_nom" name="<?php echo esc_attr( $dirigeant ); ?>_nom" value="<?php echo esc_attr( $club_data[ $dirigeant . '_nom' ] ?? '' ); ?>" <?php echo $info['required'] ? 'required' : ''; ?> />
                                <div class="ufsc-field-error" aria-live="polite"></div>
                            </div>

                            <div class="ufsc-field">
                                <label for="<?php echo esc_attr( $dirigeant ); ?>_email" class="ufsc-label <?php echo $info['required'] ? 'required' : ''; ?>"><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></label>
                                <input type="email" id="<?php echo esc_attr( $dirigeant ); ?>_email" name="<?php echo esc_attr( $dirigeant ); ?>_email" value="<?php echo esc_attr( $club_data[ $dirigeant . '_email' ] ?? '' ); ?>" <?php echo $info['required'] ? 'required' : ''; ?> />
                                <div class="ufsc-field-error" aria-live="polite"></div>
                            </div>

                            <div class="ufsc-field">
                                <label for="<?php echo esc_attr( $dirigeant ); ?>_tel" class="ufsc-label <?php echo $info['required'] ? 'required' : ''; ?>"><?php esc_html_e( 'Téléphone', 'ufsc-clubs' ); ?></label>
                                <input type="tel" id="<?php echo esc_attr( $dirigeant ); ?>_tel" name="<?php echo esc_attr( $dirigeant ); ?>_tel" value="<?php echo esc_attr( $club_data[ $dirigeant . '_tel' ] ?? '' ); ?>" <?php echo $info['required'] ? 'required' : ''; ?> />
                                <div class="ufsc-field-error" aria-live="polite"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                
                <!-- User Association Section for Affiliation -->
                <?php if ( $affiliation && ! $is_edit ): ?>
                <fieldset class="ufsc-form-section ufsc-grid">
                    <legend><?php esc_html_e( 'Association utilisateur', 'ufsc-clubs' ); ?></legend>
                    
                    <div class="ufsc-field">
                        <label class="ufsc-label"><?php esc_html_e( 'Comment souhaitez-vous associer ce club ?', 'ufsc-clubs' ); ?></label>
                        
                        <div class="ufsc-radio-group">
                            <label class="ufsc-radio-label">
                                <input type="radio" name="user_association" value="current" checked />
                                <?php esc_html_e( 'Utiliser mon compte actuel', 'ufsc-clubs' ); ?>
                            </label>
                            
                            <label class="ufsc-radio-label">
                                <input type="radio" name="user_association" value="create" />
                                <?php esc_html_e( 'Créer un nouveau compte', 'ufsc-clubs' ); ?>
                            </label>
                            
                            <?php if ( current_user_can( 'manage_options' ) ): ?>
                            <label class="ufsc-radio-label">
                                <input type="radio" name="user_association" value="existing" />
                                <?php esc_html_e( 'Associer à un utilisateur existant', 'ufsc-clubs' ); ?>
                            </label>
                            <?php endif; ?>
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    </div>
                    
                    <!-- Create User Fields -->
                    <div id="create-user-fields" class="ufsc-conditional-section" style="display: none;">
                        <div class="ufsc-grid">
                            <div class="ufsc-field">
                                <label for="new_user_login" class="ufsc-label"><?php esc_html_e( 'Nom d\'utilisateur', 'ufsc-clubs' ); ?></label>
                                <input type="text" id="new_user_login" name="new_user_login" />
                            <div class="ufsc-field-error" aria-live="polite"></div></div>
                            <div class="ufsc-field">
                                <label for="new_user_email" class="ufsc-label"><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></label>
                                <input type="email" id="new_user_email" name="new_user_email" />
                            <div class="ufsc-field-error" aria-live="polite"></div></div>
                        </div>
                        <div class="ufsc-field">
                            <label for="new_user_display_name" class="ufsc-label"><?php esc_html_e( 'Nom d\'affichage', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="new_user_display_name" name="new_user_display_name" />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    </div>
                    
                    <!-- Existing User Fields (Admin only) -->
                    <?php if ( current_user_can( 'manage_options' ) ): ?>
                    <div id="existing-user-fields" class="ufsc-conditional-section" style="display: none;">
                        <div class="ufsc-field">
                            <label for="existing_user_id" class="ufsc-label"><?php esc_html_e( 'Utilisateur existant', 'ufsc-clubs' ); ?></label>
                            <select id="existing_user_id" name="existing_user_id">
                                <option value=""><?php esc_html_e( 'Sélectionner un utilisateur', 'ufsc-clubs' ); ?></option>
                                <?php 
                                $users = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
                                foreach ( $users as $user ): 
                                ?>
                                    <option value="<?php echo esc_attr( $user->ID ); ?>">
                                        <?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    </div>
                    <?php endif; ?>
                </fieldset>
                <?php endif; ?>
                
                <!-- Admin-only fields -->
                <?php if ( current_user_can( 'manage_options' ) ): ?>
                <fieldset class="ufsc-form-section ufsc-grid">
                    <legend><?php esc_html_e( 'Administration', 'ufsc-clubs' ); ?></legend>
                    
                    <div class="ufsc-grid">
                        <div class="ufsc-field">
                            <label for="statut" class="ufsc-label"><?php esc_html_e( 'Statut', 'ufsc-clubs' ); ?></label>
                            <select id="statut" name="statut">
                                <?php foreach ( $statuses as $status_key => $status_label ): ?>
                                    <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $default_status, $status_key ); ?>>
                                        <?php echo esc_html( $status_label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                        <div class="ufsc-field">
                            <label for="quota_licences" class="ufsc-label"><?php esc_html_e( 'Quota licences', 'ufsc-clubs' ); ?></label>
                            <input type="number" id="quota_licences" name="quota_licences" value="<?php echo esc_attr( $club_data['quota_licences'] ?? '' ); ?>" min="0" />
                        <div class="ufsc-field-error" aria-live="polite"></div></div>
                    </div>
                    
                    <div class="ufsc-field">
                        <label for="num_affiliation" class="ufsc-label"><?php esc_html_e( 'N° Affiliation', 'ufsc-clubs' ); ?></label>
                        <input type="text" id="num_affiliation" name="num_affiliation" value="<?php echo esc_attr( $club_data['num_affiliation'] ?? '' ); ?>" />
                    <div class="ufsc-field-error" aria-live="polite"></div></div>
                </fieldset>
                <?php endif; ?>
                
                <div class="ufsc-form-actions">
                    <button type="submit" class="ufsc-btn ufsc-btn-primary">
                        <?php if ( $affiliation ): ?>
                            <?php esc_html_e( 'Demander l\'affiliation', 'ufsc-clubs' ); ?>
                        <?php else: ?>
                            <?php echo $is_edit ? esc_html__( 'Mettre à jour', 'ufsc-clubs' ) : esc_html__( 'Créer le club', 'ufsc-clubs' ); ?>
                        <?php endif; ?>
                    </button>
                    <button type="button" class="ufsc-btn ufsc-btn-secondary" onclick="history.back();">
                        <?php esc_html_e( 'Annuler', 'ufsc-clubs' ); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <?php
    }
    
    /**
     * Get default status for new clubs
     * 
     * @return string Default status key
     */
    private static function get_default_status() {
        $statuses = UFSC_SQL::statuses();
        
        // Prefer 'en_attente' if available
        if ( isset( $statuses['en_attente'] ) ) {
            return 'en_attente';
        }
        
        // Fall back to first available status
        $keys = array_keys( $statuses );
        return $keys[0] ?? '';
    }
    
    /**
     * Display success/error messages
     */
    private static function display_messages() {
        if ( isset( $_GET['ufsc_success'] ) ) {
            $message = sanitize_text_field( $_GET['ufsc_success'] );
            echo '<div class="ufsc-alert success">' . esc_html( $message ) . '</div>';
        }

        if ( isset( $_GET['ufsc_error'] ) ) {
            $message   = sanitize_text_field( $_GET['ufsc_error'] );
            $clean_url = esc_url( remove_query_arg( 'ufsc_error' ) );

            echo '<div class="ufsc-alert error">' . esc_html( $message ) . '</div>';
            echo '<script>if(window.history.replaceState){window.history.replaceState({},document.title,\'' . $clean_url . '\');}</script>';
        }
    }
}

// Initialize the form class
add_action( 'init', array( 'UFSC_CL_Club_Form', 'init' ) );