<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Email notification system for UFSC
 * Sends notifications for key events
 */
class UFSC_Email_Notifications {

    /**
     * Initialize email notifications
     */
    public static function init() {
        // Hook into relevant events
        add_action( 'ufsc_licence_created', array( __CLASS__, 'on_licence_created' ), 10, 2 );
        add_action( 'ufsc_licence_validated', array( __CLASS__, 'on_licence_validated' ), 10, 2 );
        add_action( 'ufsc_quota_exceeded', array( __CLASS__, 'on_quota_exceeded' ), 10, 2 );
        add_action( 'ufsc_order_created', array( __CLASS__, 'on_order_created' ), 10, 3 );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_completed' ), 10, 1 );
    }

    /**
     * Send email when licence is created
     * 
     * @param int $licence_id Licence ID
     * @param int $club_id Club ID
     */
    public static function on_licence_created( $licence_id, $club_id ) {
        $licence_data = self::get_licence_data( $licence_id );
        $club_data = self::get_club_data( $club_id );
        $club_email = self::get_club_responsible_email( $club_id );

        if ( ! $club_email || ! $licence_data || ! $club_data ) {
            return;
        }

        $subject = sprintf( 
            __( '[UFSC] Nouvelle licence créée - %s %s', 'ufsc-clubs' ),
            $licence_data['prenom'],
            $licence_data['nom']
        );

        $template_data = array(
            'licence' => $licence_data,
            'club' => $club_data,
            'action_url' => admin_url( 'admin.php?page=ufsc-gestion-licences' )
        );

        $message = self::render_template( 'licence-created', $template_data );

        self::send_email( $club_email, $subject, $message );

        // Log notification
        ufsc_audit_log( 'email_sent', array(
            'type' => 'licence_created',
            'licence_id' => $licence_id,
            'club_id' => $club_id,
            'recipient' => $club_email
        ) );
    }

    /**
     * Send email when licence is validated
     * 
     * @param int $licence_id Licence ID
     * @param int $club_id Club ID
     */
    public static function on_licence_validated( $licence_id, $club_id ) {
        $licence_data = self::get_licence_data( $licence_id );
        $club_data = self::get_club_data( $club_id );
        $club_email = self::get_club_responsible_email( $club_id );

        if ( ! $club_email || ! $licence_data || ! $club_data ) {
            return;
        }

        $subject = sprintf( 
            __( '[UFSC] Licence validée - %s %s', 'ufsc-clubs' ),
            $licence_data['prenom'],
            $licence_data['nom']
        );

        $template_data = array(
            'licence' => $licence_data,
            'club' => $club_data,
            'dashboard_url' => self::get_dashboard_url()
        );

        $message = self::render_template( 'licence-validated', $template_data );

        self::send_email( $club_email, $subject, $message );

        // Also send to licence holder if email provided
        if ( ! empty( $licence_data['email'] ) && $licence_data['email'] !== $club_email ) {
            $licence_subject = sprintf(
                __( '[UFSC] Votre licence a été validée - %s', 'ufsc-clubs' ),
                $club_data['nom']
            );

            $licence_template_data = array(
                'licence' => $licence_data,
                'club' => $club_data,
                'is_licence_holder' => true
            );

            $licence_message = self::render_template( 'licence-validated', $licence_template_data );
            self::send_email( $licence_data['email'], $licence_subject, $licence_message );
        }

        // Log notification
        ufsc_audit_log( 'email_sent', array(
            'type' => 'licence_validated',
            'licence_id' => $licence_id,
            'club_id' => $club_id,
            'recipient' => $club_email
        ) );
    }

    /**
     * Send email when quota is exceeded
     * 
     * @param int $club_id Club ID
     * @param array $context Additional context
     */
    public static function on_quota_exceeded( $club_id, $context = array() ) {
        $club_data = self::get_club_data( $club_id );
        $club_email = self::get_club_responsible_email( $club_id );

        if ( ! $club_email || ! $club_data ) {
            return;
        }

        $subject = sprintf( 
            __( '[UFSC] Quota de licences atteint - %s', 'ufsc-clubs' ),
            $club_data['nom']
        );

        $template_data = array(
            'club' => $club_data,
            'context' => $context,
            'dashboard_url' => self::get_dashboard_url(),
            'shop_url' => wc_get_page_permalink( 'shop' )
        );

        $message = self::render_template( 'quota-exceeded', $template_data );

        self::send_email( $club_email, $subject, $message );

        // Log notification
        ufsc_audit_log( 'email_sent', array(
            'type' => 'quota_exceeded',
            'club_id' => $club_id,
            'recipient' => $club_email
        ) );
    }

    /**
     * Send email when order is created for additional licences
     * 
     * @param int $order_id Order ID
     * @param int $club_id Club ID
     * @param array $licence_ids Licence IDs
     */
    public static function on_order_created( $order_id, $club_id, $licence_ids = array() ) {
        $order = wc_get_order( $order_id );
        $club_data = self::get_club_data( $club_id );
        $club_email = self::get_club_responsible_email( $club_id );

        if ( ! $order || ! $club_email || ! $club_data ) {
            return;
        }

        $subject = sprintf( 
            __( '[UFSC] Commande créée pour licences additionnelles - Commande #%s', 'ufsc-clubs' ),
            $order->get_order_number()
        );

        $template_data = array(
            'order' => $order,
            'club' => $club_data,
            'licence_ids' => $licence_ids,
            'payment_url' => $order->get_checkout_payment_url(),
            'order_details_url' => $order->get_view_order_url()
        );

        $message = self::render_template( 'order-created', $template_data );

        self::send_email( $club_email, $subject, $message );

        // Log notification
        ufsc_audit_log( 'email_sent', array(
            'type' => 'order_created',
            'order_id' => $order_id,
            'club_id' => $club_id,
            'recipient' => $club_email
        ) );
    }

    /**
     * Send email when order is completed
     * 
     * @param int $order_id Order ID
     */
    public static function on_order_completed( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return;
        }

        $wc_settings = ufsc_get_woocommerce_settings();
        $affiliation_product_id = $wc_settings['product_affiliation_id'];
        $license_product_id = $wc_settings['product_license_id'];

        // Check if this order contains UFSC products
        $has_ufsc_products = false;
        $club_id = null;

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            
            if ( $product_id == $affiliation_product_id || $product_id == $license_product_id ) {
                $has_ufsc_products = true;
                $club_id = $item->get_meta( '_ufsc_club_id' );
                break;
            }
        }

        if ( ! $has_ufsc_products || ! $club_id ) {
            return;
        }

        $club_data = self::get_club_data( $club_id );
        $club_email = self::get_club_responsible_email( $club_id );

        if ( ! $club_email || ! $club_data ) {
            return;
        }

        $subject = sprintf( 
            __( '[UFSC] Paiement confirmé - Commande #%s', 'ufsc-clubs' ),
            $order->get_order_number()
        );

        $template_data = array(
            'order' => $order,
            'club' => $club_data,
            'dashboard_url' => self::get_dashboard_url(),
            'order_details_url' => $order->get_view_order_url()
        );

        $message = self::render_template( 'order-completed', $template_data );

        self::send_email( $club_email, $subject, $message );

        // Log notification
        ufsc_audit_log( 'email_sent', array(
            'type' => 'order_completed',
            'order_id' => $order_id,
            'club_id' => $club_id,
            'recipient' => $club_email
        ) );
    }

    /**
     * Send email using wp_mail with enhanced formatting
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Email content
     * @param array $headers Additional headers
     * @return bool Success status
     */
    private static function send_email( $to, $subject, $message, $headers = array() ) {
        // Set up headers
        $default_headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option( 'blogname' ) . ' <' . get_option( 'admin_email' ) . '>'
        );

        $headers = array_merge( $default_headers, $headers );

        // Apply filters for customization
        $to = apply_filters( 'ufsc_email_recipient', $to );
        $subject = apply_filters( 'ufsc_email_subject', $subject );
        $message = apply_filters( 'ufsc_email_message', $message );
        $headers = apply_filters( 'ufsc_email_headers', $headers );

        // Send email
        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( ! $sent ) {
            error_log( "UFSC Email failed to send to: {$to}, Subject: {$subject}" );
        }

        return $sent;
    }

    /**
     * Render email template
     * 
     * @param string $template Template name
     * @param array $data Template data
     * @return string Rendered template
     */
    private static function render_template( $template, $data = array() ) {
        $template_file = UFSC_CL_DIR . "templates/emails/{$template}.php";
        
        // Check if custom template exists
        if ( file_exists( $template_file ) ) {
            ob_start();
            extract( $data );
            include $template_file;
            return ob_get_clean();
        }

        // Fall back to default templates
        return self::get_default_template( $template, $data );
    }

    /**
     * Get default email template
     * 
     * @param string $template Template name
     * @param array $data Template data
     * @return string Template content
     */
    private static function get_default_template( $template, $data ) {
        $common_header = self::get_email_header();
        $common_footer = self::get_email_footer();

        switch ( $template ) {
            case 'licence-created':
                return $common_header . 
                       self::get_licence_created_template( $data ) . 
                       $common_footer;

            case 'licence-validated':
                return $common_header . 
                       self::get_licence_validated_template( $data ) . 
                       $common_footer;

            case 'quota-exceeded':
                return $common_header . 
                       self::get_quota_exceeded_template( $data ) . 
                       $common_footer;

            case 'order-created':
                return $common_header . 
                       self::get_order_created_template( $data ) . 
                       $common_footer;

            case 'order-completed':
                return $common_header . 
                       self::get_order_completed_template( $data ) . 
                       $common_footer;

            default:
                return $common_header . 
                       '<p>' . __( 'Notification UFSC', 'ufsc-clubs' ) . '</p>' . 
                       $common_footer;
        }
    }

    /**
     * Get email header
     */
    private static function get_email_header() {
        $site_name = get_option( 'blogname' );
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html( $site_name ) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 20px; text-align: center; }
                .content { background: white; padding: 30px; border: 1px solid #ddd; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
                .button:hover { background: #2980b9; }
                .info-box { background: #f8f9fa; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; }
                .licence-details { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; }
                .licence-details h4 { margin: 0 0 10px 0; color: #2c3e50; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . esc_html( $site_name ) . '</h1>
                    <p>Union Française des Sports Canins</p>
                </div>
                <div class="content">
        ';
    }

    /**
     * Get email footer
     */
    private static function get_email_footer() {
        $site_url = home_url();
        $site_name = get_option( 'blogname' );
        
        return '
                </div>
                <div class="footer">
                    <p>Cet email a été envoyé automatiquement par <a href="' . esc_url( $site_url ) . '">' . esc_html( $site_name ) . '</a></p>
                    <p>Pour toute question, contactez l\'administration.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }

    /**
     * Licence created template
     */
    private static function get_licence_created_template( $data ) {
        $licence = $data['licence'];
        $club = $data['club'];
        $action_url = $data['action_url'];

        return sprintf( '
            <h2>%s</h2>
            <p>%s</p>
            
            <div class="licence-details">
                <h4>%s</h4>
                <p><strong>%s:</strong> %s %s</p>
                <p><strong>%s:</strong> %s</p>
                <p><strong>%s:</strong> %s</p>
                <p><strong>%s:</strong> %s</p>
            </div>
            
            <div class="info-box">
                <p>%s</p>
            </div>
            
            <p><a href="%s" class="button">%s</a></p>
        ',
            __( 'Nouvelle licence créée', 'ufsc-clubs' ),
            sprintf( __( 'Une nouvelle licence a été créée pour le club %s.', 'ufsc-clubs' ), esc_html( $club['nom'] ) ),
            __( 'Détails de la licence', 'ufsc-clubs' ),
            __( 'Nom', 'ufsc-clubs' ),
            esc_html( $licence['prenom'] ),
            esc_html( $licence['nom'] ),
            __( 'Email', 'ufsc-clubs' ),
            esc_html( $licence['email'] ),
            __( 'Date de naissance', 'ufsc-clubs' ),
            esc_html( $licence['date_naissance'] ),
            __( 'Statut', 'ufsc-clubs' ),
            esc_html( $licence['statut'] ?? 'brouillon' ),
            __( 'Cette licence est en attente de validation. Vous recevrez une notification lorsqu\'elle sera traitée par l\'administration.', 'ufsc-clubs' ),
            esc_url( $action_url ),
            __( 'Gérer mes licences', 'ufsc-clubs' )
        );
    }

    /**
     * Licence validated template
     */
    private static function get_licence_validated_template( $data ) {
        $licence = $data['licence'];
        $club = $data['club'];
        $is_licence_holder = isset( $data['is_licence_holder'] ) && $data['is_licence_holder'];

        if ( $is_licence_holder ) {
            $title = __( 'Votre licence a été validée', 'ufsc-clubs' );
            $message = sprintf( __( 'Félicitations ! Votre licence au sein du club %s a été validée par l\'administration UFSC.', 'ufsc-clubs' ), esc_html( $club['nom'] ) );
        } else {
            $title = __( 'Licence validée', 'ufsc-clubs' );
            $message = sprintf( __( 'La licence de %s %s a été validée par l\'administration UFSC.', 'ufsc-clubs' ), esc_html( $licence['prenom'] ), esc_html( $licence['nom'] ) );
        }

        return sprintf( '
            <h2>%s</h2>
            <p>%s</p>
            
            <div class="licence-details">
                <h4>%s</h4>
                <p><strong>%s:</strong> %s %s</p>
                <p><strong>%s:</strong> %s</p>
                <p><strong>%s:</strong> %s</p>
            </div>
            
            <div class="info-box">
                <p>%s</p>
            </div>
        ',
            $title,
            $message,
            __( 'Détails de la licence', 'ufsc-clubs' ),
            __( 'Nom', 'ufsc-clubs' ),
            esc_html( $licence['prenom'] ),
            esc_html( $licence['nom'] ),
            __( 'Club', 'ufsc-clubs' ),
            esc_html( $club['nom'] ),
            __( 'Date de validation', 'ufsc-clubs' ),
            date( 'd/m/Y' ),
            __( 'Cette licence est maintenant active et ne peut plus être modifiée. Pour toute correction, contactez l\'administration.', 'ufsc-clubs' )
        );
    }

    /**
     * Quota exceeded template
     */
    private static function get_quota_exceeded_template( $data ) {
        $club = $data['club'];
        $dashboard_url = $data['dashboard_url'];
        $shop_url = $data['shop_url'];

        return sprintf( '
            <h2>%s</h2>
            <p>%s</p>
            
            <div class="info-box">
                <p>%s</p>
                <p>%s</p>
            </div>
            
            <p><a href="%s" class="button">%s</a></p>
            <p><a href="%s" class="button">%s</a></p>
        ',
            __( 'Quota de licences atteint', 'ufsc-clubs' ),
            sprintf( __( 'Le club %s a atteint son quota de licences incluses dans l\'affiliation.', 'ufsc-clubs' ), esc_html( $club['nom'] ) ),
            __( 'Pour ajouter de nouvelles licences, vous devez acheter des licences additionnelles.', 'ufsc-clubs' ),
            __( 'Les licences créées au-delà du quota seront automatiquement mises en attente de paiement.', 'ufsc-clubs' ),
            esc_url( $shop_url ),
            __( 'Acheter des licences', 'ufsc-clubs' ),
            esc_url( $dashboard_url ),
            __( 'Accéder au tableau de bord', 'ufsc-clubs' )
        );
    }

    /**
     * Order created template
     */
    private static function get_order_created_template( $data ) {
        $order = $data['order'];
        $club = $data['club'];
        $payment_url = $data['payment_url'];

        return sprintf( '
            <h2>%s</h2>
            <p>%s</p>
            
            <div class="info-box">
                <p><strong>%s:</strong> #%s</p>
                <p><strong>%s:</strong> %s</p>
                <p><strong>%s:</strong> %s</p>
            </div>
            
            <p>%s</p>
            
            <p><a href="%s" class="button">%s</a></p>
        ',
            __( 'Commande créée', 'ufsc-clubs' ),
            sprintf( __( 'Une commande a été créée pour des licences additionnelles pour le club %s.', 'ufsc-clubs' ), esc_html( $club['nom'] ) ),
            __( 'Numéro de commande', 'ufsc-clubs' ),
            $order->get_order_number(),
            __( 'Montant', 'ufsc-clubs' ),
            $order->get_formatted_order_total(),
            __( 'Date', 'ufsc-clubs' ),
            $order->get_date_created()->date( 'd/m/Y H:i' ),
            __( 'Veuillez procéder au paiement pour activer les licences.', 'ufsc-clubs' ),
            esc_url( $payment_url ),
            __( 'Payer maintenant', 'ufsc-clubs' )
        );
    }

    /**
     * Order completed template
     */
    private static function get_order_completed_template( $data ) {
        $order = $data['order'];
        $club = $data['club'];
        $dashboard_url = $data['dashboard_url'];

        return sprintf( '
            <h2>%s</h2>
            <p>%s</p>
            
            <div class="info-box">
                <p><strong>%s:</strong> #%s</p>
                <p><strong>%s:</strong> %s</p>
                <p><strong>%s:</strong> %s</p>
            </div>
            
            <p>%s</p>
            
            <p><a href="%s" class="button">%s</a></p>
        ',
            __( 'Paiement confirmé', 'ufsc-clubs' ),
            sprintf( __( 'Le paiement de la commande #%s a été confirmé pour le club %s.', 'ufsc-clubs' ), $order->get_order_number(), esc_html( $club['nom'] ) ),
            __( 'Numéro de commande', 'ufsc-clubs' ),
            $order->get_order_number(),
            __( 'Montant payé', 'ufsc-clubs' ),
            $order->get_formatted_order_total(),
            __( 'Date de paiement', 'ufsc-clubs' ),
            $order->get_date_paid()->date( 'd/m/Y H:i' ),
            __( 'Vos licences additionnelles ont été créditées à votre quota. Vous pouvez maintenant créer de nouvelles licences.', 'ufsc-clubs' ),
            esc_url( $dashboard_url ),
            __( 'Accéder au tableau de bord', 'ufsc-clubs' )
        );
    }

    // Helper methods

    private static function get_licence_data( $licence_id ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
            return null;
        }

        $table  = ufsc_get_licences_table();
        $id_col = function_exists( 'ufsc_lic_col' ) ? ufsc_lic_col( 'id' ) : 'id';

        $columns = array(
            'id'            => $id_col,
            'club_id'       => function_exists( 'ufsc_lic_col' ) ? ufsc_lic_col( 'club_id' ) : 'club_id',
            'nom'           => function_exists( 'ufsc_lic_col' ) ? ufsc_lic_col( 'nom' ) : 'nom',
            'prenom'        => function_exists( 'ufsc_lic_col' ) ? ufsc_lic_col( 'prenom' ) : 'prenom',
            'email'         => function_exists( 'ufsc_lic_col' ) ? ufsc_lic_col( 'email' ) : 'email',
            'date_naissance'=> function_exists( 'ufsc_lic_col' ) ? ufsc_lic_col( 'date_naissance' ) : 'date_naissance',
            'statut'        => function_exists( 'ufsc_lic_col' ) ? ufsc_lic_col( 'statut' ) : 'statut',
        );

        $select = array();
        foreach ( $columns as $alias => $col ) {
            $select[] = "`{$col}` AS {$alias}";
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ' . implode( ', ', $select ) . " FROM `{$table}` WHERE `{$id_col}` = %d LIMIT 1",
                (int) $licence_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    private static function get_club_data( $club_id ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return null;
        }

        $table = ufsc_get_clubs_table();

        $columns = array(
            'id'             => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id',
            'nom'            => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'nom' ) : 'nom',
            'email'          => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'email' ) : 'email',
            'responsable_id' => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'responsable_id' ) : 'responsable_id',
        );

        $select = array();
        foreach ( $columns as $alias => $col ) {
            $select[] = "`{$col}` AS {$alias}";
        }

        $id_col = $columns['id'];
        $row    = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ' . implode( ', ', $select ) . " FROM `{$table}` WHERE `{$id_col}` = %d LIMIT 1",
                (int) $club_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    private static function get_club_responsible_email( $club_id ) {
        $club = self::get_club_data( $club_id );
        if ( ! $club || empty( $club['responsable_id'] ) ) {
            return '';
        }

        $user = get_userdata( (int) $club['responsable_id'] );
        return $user ? $user->user_email : '';
    }

    private static function get_dashboard_url() {
        $dashboard_page = get_option( 'ufsc_dashboard_page' );
        if ( $dashboard_page ) {
            $url = get_permalink( $dashboard_page );
            if ( $url ) {
                return $url;
            }
        }

        if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
            return wc_get_account_endpoint_url( 'ufsc-tableau-de-bord' );
        }

        return home_url( '/tableau-de-bord/' );
    }
}

// Initialize email notifications
UFSC_Email_Notifications::init();
