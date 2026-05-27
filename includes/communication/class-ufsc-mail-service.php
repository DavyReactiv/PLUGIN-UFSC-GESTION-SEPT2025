<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_Mail_Service {
    const CRON_HOOK = 'ufsc_process_mail_queue';
    const LOCK_KEY = 'ufsc_mail_queue_lock';
    const NONCE_ACTION = 'ufsc_mail_campaign_action';
    const FORM_TRANSIENT_PREFIX = 'ufsc_mail_form_';
    const IDEMPOTENCY_TRANSIENT_PREFIX = 'ufsc_mail_idempotency_';

    public static function init() {
        add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
        add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ) );
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_ufsc_mail_preview', array( __CLASS__, 'handle_preview' ) );
        add_action( 'admin_post_ufsc_mail_test', array( __CLASS__, 'handle_test' ) );
        add_action( 'admin_post_ufsc_mail_create_campaign', array( __CLASS__, 'handle_create_campaign' ) );
        add_action( 'admin_post_ufsc_mail_process_now', array( __CLASS__, 'handle_process_now' ) );
        add_action( 'admin_post_ufsc_mail_retry_failed', array( __CLASS__, 'handle_retry_failed' ) );

        UFSC_Mail_Installer::ensure_schema();
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 60, 'ufsc_every_five_minutes', self::CRON_HOOK );
        }
    }

    public static function cron_schedules( $schedules ) {
        $schedules['ufsc_every_five_minutes'] = array( 'interval' => 300, 'display' => 'UFSC 5 minutes' );
        return $schedules;
    }

    private static function capability() {
        return defined( 'UFSC_Capabilities::CAP_MANAGE_COMMUNICATION' ) ? UFSC_Capabilities::CAP_MANAGE_COMMUNICATION : 'manage_options';
    }

    private static function can_manage() {
        return current_user_can( self::capability() ) || current_user_can( 'manage_options' );
    }

    public static function register_menu() {
        add_submenu_page( 'ufsc-dashboard', __( 'Communication clubs', 'ufsc-clubs' ), __( 'Communication clubs', 'ufsc-clubs' ), self::capability(), 'ufsc-communication-clubs', array( __CLASS__, 'render_admin' ) );
    }

    public static function render_admin() {
        if ( ! self::can_manage() ) { wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) ); }
        global $wpdb;
        $campaigns = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ufsc_mail_campaigns ORDER BY id DESC LIMIT 30" );
        $view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list';

        echo '<div class="wrap"><h1>' . esc_html__( 'Communication clubs', 'ufsc-clubs' ) . '</h1>';
        self::render_notices();
        echo '<p>' . esc_html__( 'Les emails sont envoyés progressivement pour éviter de surcharger le serveur.', 'ufsc-clubs' ) . '</p>';
        echo '<p>' . esc_html__( 'Les emails sont envoyés via le système email WordPress. Pour une bonne délivrabilité, vérifiez que le SMTP du site est correctement configuré, idéalement avec Brevo, SPF, DKIM et DMARC.', 'ufsc-clubs' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=ufsc-communication-clubs&view=new' ) ) . '">' . esc_html__( 'Nouvelle campagne', 'ufsc-clubs' ) . '</a></p>';

        if ( 'new' === $view ) {
            self::render_new_campaign_form();
        } elseif ( 'detail' === $view ) {
            self::render_campaign_detail();
        } else {
            self::render_campaigns_list( $campaigns );
        }
        echo '</div>';
    }

    private static function render_notices() {
        $code = isset( $_GET['ufsc_mail_notice'] ) ? sanitize_key( wp_unslash( $_GET['ufsc_mail_notice'] ) ) : '';
        $messages = array(
            'campaign_created' => array( 'success', __( 'Campagne créée et file alimentée.', 'ufsc-clubs' ) ),
            'test_sent' => array( 'success', __( 'Email de test envoyé.', 'ufsc-clubs' ) ),
            'test_failed' => array( 'error', __( 'Échec de l’envoi du test.', 'ufsc-clubs' ) ),
            'queue_processed' => array( 'success', __( 'File traitée.', 'ufsc-clubs' ) ),
            'no_recipients' => array( 'warning', __( 'Aucun destinataire trouvé.', 'ufsc-clubs' ) ),
            'failed_requeued' => array( 'success', __( 'Échecs remis en file d’attente.', 'ufsc-clubs' ) ),
        );
        if ( isset( $messages[ $code ] ) ) {
            list( $type, $text ) = $messages[ $code ];
            echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $text ) . '</p></div>';
        }
    }

    private static function render_new_campaign_form() {
        $data = get_transient( self::FORM_TRANSIENT_PREFIX . get_current_user_id() );
        $data = is_array( $data ) ? $data : array();
        $name = isset( $data['name'] ) ? $data['name'] : '';
        $subject = isset( $data['subject'] ) ? $data['subject'] : '';
        $message = isset( $data['message_html'] ) ? $data['message_html'] : '';
        $target = isset( $data['target_type'] ) ? $data['target_type'] : 'affiliated_up_to_date';
        $test_email = isset( $data['test_email'] ) ? $data['test_email'] : get_bloginfo( 'admin_email' );
        $idempotency_token = wp_generate_uuid4();

        echo '<h2>' . esc_html__( 'Nouvelle campagne', 'ufsc-clubs' ) . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( self::NONCE_ACTION );
        echo '<input type="hidden" name="action" value="ufsc_mail_preview" />';
        echo '<input type="hidden" name="idempotency_token" value="' . esc_attr( $idempotency_token ) . '" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="ufsc_name">' . esc_html__( 'Titre interne', 'ufsc-clubs' ) . '</label></th><td><input id="ufsc_name" name="name" class="regular-text" value="' . esc_attr( $name ) . '" required /></td></tr>';
        echo '<tr><th><label for="ufsc_subject">' . esc_html__( 'Objet', 'ufsc-clubs' ) . '</label></th><td><input id="ufsc_subject" name="subject" class="regular-text" value="' . esc_attr( $subject ) . '" required /></td></tr>';
        echo '<tr><th>' . esc_html__( 'Message', 'ufsc-clubs' ) . '</th><td>';
        wp_editor( $message, 'ufsc_message_html', array( 'textarea_name' => 'message_html', 'textarea_rows' => 10 ) );
        echo '<p><code>{club_name}</code> <code>{recipient_name}</code> <code>{season}</code> <code>{club_dashboard_url}</code> <code>{ufsc_site_url}</code></p></td></tr>';
        echo '<tr><th><label for="ufsc_target">' . esc_html__( 'Cible', 'ufsc-clubs' ) . '</label></th><td><select id="ufsc_target" name="target_type">';
        echo '<option value="affiliated_email_valid" ' . selected( $target, 'affiliated_email_valid', false ) . '>' . esc_html__( 'Clubs affiliés avec email valide', 'ufsc-clubs' ) . '</option>';
        echo '<option value="affiliated_up_to_date" ' . selected( $target, 'affiliated_up_to_date', false ) . '>' . esc_html__( 'Clubs affiliés et à jour', 'ufsc-clubs' ) . '</option>';
        echo '</select></td></tr>';
        echo '<tr><th><label for="ufsc_test_email">' . esc_html__( 'Email de test', 'ufsc-clubs' ) . '</label></th><td><input id="ufsc_test_email" name="test_email" type="email" class="regular-text" value="' . esc_attr( $test_email ) . '" /></td></tr>';
        $limit_recipients = isset( $data['limit_recipients'] ) ? absint( $data['limit_recipients'] ) : 0;
        echo '<tr><th><label for="ufsc_limit_recipients">' . esc_html__( 'Limiter la campagne (destinataires)', 'ufsc-clubs' ) . '</label></th><td><input id="ufsc_limit_recipients" name="limit_recipients" type="number" min="0" step="1" class="small-text" value="' . esc_attr( (string) $limit_recipients ) . '" /> <p class="description">' . esc_html__( 'Option facultative pour un envoi réel de test (ex: 1, 2, 3).', 'ufsc-clubs' ) . '</p></td></tr>';
        echo '</table>';
        echo '<p><button class="button button-secondary" name="submit_type" value="preview">' . esc_html__( 'Prévisualiser les destinataires', 'ufsc-clubs' ) . '</button> ';
        echo '<button class="button" formaction="' . esc_url( admin_url( 'admin-post.php?action=ufsc_mail_test' ) ) . '">' . esc_html__( 'Envoyer un test', 'ufsc-clubs' ) . '</button> ';
        echo '<button class="button button-primary" name="submit_type" value="queue">' . esc_html__( 'Créer la campagne et mettre en file', 'ufsc-clubs' ) . '</button></p>';
        echo '</form>';

        if ( ! empty( $data['preview'] ) && is_array( $data['preview'] ) ) {
            self::render_preview_block( $data['preview'] );
        }
    }

    private static function render_preview_block( $preview ) {
        echo '<h3>' . esc_html__( 'Prévisualisation destinataires', 'ufsc-clubs' ) . '</h3>';
        echo '<ul><li>' . esc_html__( 'Clubs trouvés :', 'ufsc-clubs' ) . ' ' . (int) $preview['total_clubs'] . '</li><li>' . esc_html__( 'Emails valides :', 'ufsc-clubs' ) . ' ' . (int) $preview['valid_count'] . '</li><li>' . esc_html__( 'Ignorés :', 'ufsc-clubs' ) . ' ' . (int) $preview['ignored_count'] . '</li></ul>';

        if ( ! empty( $preview['limit_recipients'] ) ) {
            echo '<div class="notice notice-info inline"><p>' . sprintf( esc_html__( 'Mode test limité : seuls %d destinataires seront mis en file.', 'ufsc-clubs' ), (int) $preview['limit_recipients'] ) . '</p></div>';
        }

        if ( ! empty( $preview['valid_recipients'] ) ) {
            echo '<table class="widefat"><tr><th>Club</th><th>Email</th></tr>';
            foreach ( array_slice( $preview['valid_recipients'], 0, 20 ) as $row ) {
                echo '<tr><td>' . esc_html( $row['club_name'] ) . '</td><td>' . esc_html( $row['email'] ) . '</td></tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Aucun destinataire trouvé.', 'ufsc-clubs' ) . '</p></div>';
        }

        if ( ! empty( $preview['ignored_reasons'] ) && is_array( $preview['ignored_reasons'] ) ) {
            $reason_counts = array();
            foreach ( $preview['ignored_reasons'] as $ignored_row ) {
                $reason_key = isset( $ignored_row['reason'] ) ? (string) $ignored_row['reason'] : 'autre raison défensive';
                if ( ! isset( $reason_counts[ $reason_key ] ) ) { $reason_counts[ $reason_key ] = 0; }
                $reason_counts[ $reason_key ]++;
            }
            echo '<h4>' . esc_html__( 'Motifs d’exclusion', 'ufsc-clubs' ) . '</h4><ul>';
            foreach ( $reason_counts as $reason_label => $reason_total ) {
                echo '<li>' . esc_html( $reason_label ) . ': ' . (int) $reason_total . '</li>';
            }
            echo '</ul>';

            echo '<table class="widefat striped"><tr><th>' . esc_html__( 'Club', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Email', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Raison', 'ufsc-clubs' ) . '</th></tr>';
            foreach ( array_slice( $preview['ignored_reasons'], 0, 50 ) as $ignored_row ) {
                echo '<tr><td>' . esc_html( (string) ( $ignored_row['club'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $ignored_row['email'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $ignored_row['reason'] ?? 'autre raison défensive' ) ) . '</td></tr>';
            }
            echo '</table>';
        }
    }

    private static function render_campaigns_list( $campaigns ) {
        echo '<h2>' . esc_html__( 'Dernières campagnes', 'ufsc-clubs' ) . '</h2><table class="widefat striped"><tr><th>ID</th><th>Date</th><th>Objet</th><th>Cible</th><th>Statut</th><th>Total</th><th>Envoyés</th><th>Échecs</th><th>Actions</th></tr>';
        foreach ( (array) $campaigns as $c ) {
            $detail_url = add_query_arg( array( 'page' => 'ufsc-communication-clubs', 'view' => 'detail', 'campaign_id' => (int) $c->id ), admin_url( 'admin.php' ) );
            echo '<tr><td>' . (int) $c->id . '</td><td>' . esc_html( (string) $c->created_at ) . '</td><td>' . esc_html( (string) $c->subject ) . '</td><td>' . esc_html( (string) $c->target_type ) . '</td><td>' . esc_html( (string) $c->status ) . '</td><td>' . (int) $c->total_recipients . '</td><td>' . (int) $c->sent_count . '</td><td>' . (int) $c->failed_count . '</td><td><a class="button button-small" href="' . esc_url( $detail_url ) . '">' . esc_html__( 'Voir', 'ufsc-clubs' ) . '</a></td></tr>';
        }
        echo '</table>';
    }

    private static function render_campaign_detail() {
        global $wpdb;
        $id = isset( $_GET['campaign_id'] ) ? absint( wp_unslash( $_GET['campaign_id'] ) ) : 0;
        $campaigns = $wpdb->prefix . 'ufsc_mail_campaigns';
        $queue = $wpdb->prefix . 'ufsc_mail_queue';
        $campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$campaigns} WHERE id=%d", $id ) );
        if ( ! $campaign ) { echo '<div class="notice notice-error"><p>Campagne introuvable.</p></div>'; return; }

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$queue} WHERE campaign_id=%d ORDER BY id ASC LIMIT 200", $id ) );
        $pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id=%d AND status IN ('pending','processing')", $id ) );
        echo '<h2>' . esc_html( $campaign->name ) . ' (#' . (int) $campaign->id . ')</h2>';
        echo '<p><strong>Objet:</strong> ' . esc_html( $campaign->subject ) . '</p><p><strong>Statut:</strong> ' . esc_html( $campaign->status ) . '</p>';
        echo '<p><strong>Total:</strong> ' . (int) $campaign->total_recipients . ' | <strong>Envoyés:</strong> ' . (int) $campaign->sent_count . ' | <strong>Échecs:</strong> ' . (int) $campaign->failed_count . ' | <strong>En attente:</strong> ' . $pending . '</p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;">';
        wp_nonce_field( self::NONCE_ACTION );
        echo '<input type="hidden" name="action" value="ufsc_mail_process_now" /><input type="hidden" name="campaign_id" value="' . (int) $id . '" /><button class="button">Traiter la file maintenant</button></form>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
        wp_nonce_field( self::NONCE_ACTION );
        echo '<input type="hidden" name="action" value="ufsc_mail_retry_failed" /><input type="hidden" name="campaign_id" value="' . (int) $id . '" /><button class="button">Relancer les échecs</button></form>';

        echo '<h3>Destinataires (200 max)</h3><table class="widefat striped"><tr><th>Club</th><th>Email</th><th>Statut</th><th>Tentatives</th><th>Erreur</th><th>Envoyé le</th></tr>';
        foreach ( (array) $rows as $r ) {
            echo '<tr><td>' . esc_html( $r->club_name ) . '</td><td>' . esc_html( $r->recipient_email ) . '</td><td>' . esc_html( $r->status ) . '</td><td>' . (int) $r->attempts . '</td><td>' . esc_html( (string) $r->last_error ) . '</td><td>' . esc_html( (string) $r->sent_at ) . '</td></tr>';
        }
        echo '</table>';
    }

    public static function handle_preview() {
        self::guard_action();
        $data = self::read_form_data();
        $preview = self::compute_recipients_preview( $data['target_type'], isset( $data['limit_recipients'] ) ? absint( $data['limit_recipients'] ) : 0 );
        $data['preview'] = $preview;
        set_transient( self::FORM_TRANSIENT_PREFIX . get_current_user_id(), $data, 20 * MINUTE_IN_SECONDS );

        if ( 'queue' === $data['submit_type'] ) {
            self::create_campaign_from_form( $data, $preview );
            wp_safe_redirect( add_query_arg( array( 'page' => 'ufsc-communication-clubs', 'ufsc_mail_notice' => empty( $preview['valid_recipients'] ) ? 'no_recipients' : 'campaign_created' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'ufsc-communication-clubs', 'view' => 'new' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_test() {
        self::guard_action();
        $data = self::read_form_data();
        $to = is_email( $data['test_email'] ) ? $data['test_email'] : get_bloginfo( 'admin_email' );
        $vars = self::build_template_vars( array( 'club_name' => 'Club Test UFSC', 'recipient_name' => 'Responsable Test' ) );
        $html = self::render_message( $data['message_html'], $vars );
        $sent = self::send_html_mail( $to, $data['subject'], $html );
        set_transient( self::FORM_TRANSIENT_PREFIX . get_current_user_id(), $data, 20 * MINUTE_IN_SECONDS );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ufsc-communication-clubs', 'view' => 'new', 'ufsc_mail_notice' => $sent ? 'test_sent' : 'test_failed' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_create_campaign() { self::handle_preview(); }

    public static function handle_process_now() {
        self::guard_action();
        $campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
        self::process_queue( 20, $campaign_id );
        $args = array( 'page' => 'ufsc-communication-clubs', 'ufsc_mail_notice' => 'queue_processed' );
        if ( $campaign_id > 0 ) { $args['view'] = 'detail'; $args['campaign_id'] = $campaign_id; }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit;
    }

    public static function handle_retry_failed() {
        self::guard_action();
        global $wpdb;
        $campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
        if ( $campaign_id > 0 ) {
            $queue = $wpdb->prefix . 'ufsc_mail_queue';
            $wpdb->query( $wpdb->prepare( "UPDATE {$queue} SET status='pending', updated_at=%s WHERE campaign_id=%d AND status='failed'", current_time( 'mysql' ), $campaign_id ) );
            if ( class_exists( 'UFSC_Audit_Logger' ) ) { UFSC_Audit_Logger::log( 'UFSC Mail retry failed for campaign #' . $campaign_id ); }
        }
        wp_safe_redirect( add_query_arg( array( 'page' => 'ufsc-communication-clubs', 'view' => 'detail', 'campaign_id' => $campaign_id, 'ufsc_mail_notice' => 'failed_requeued' ), admin_url( 'admin.php' ) ) ); exit;
    }

    private static function guard_action() {
        if ( ! self::can_manage() ) { wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) ); }
        check_admin_referer( self::NONCE_ACTION );
    }

    private static function read_form_data() {
        return array(
            'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'subject' => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
            'message_html' => wp_kses_post( wp_unslash( $_POST['message_html'] ?? '' ) ),
            'target_type' => sanitize_key( wp_unslash( $_POST['target_type'] ?? 'affiliated_up_to_date' ) ),
            'test_email' => sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) ),
            'submit_type' => sanitize_key( wp_unslash( $_POST['submit_type'] ?? 'preview' ) ),
            'idempotency_token' => sanitize_text_field( wp_unslash( $_POST['idempotency_token'] ?? '' ) ),
            'limit_recipients' => absint( wp_unslash( $_POST['limit_recipients'] ?? 0 ) ),
        );
    }

    private static function create_campaign_from_form( $data, $preview ) {
        if ( empty( $preview['valid_recipients'] ) ) { return; }
        global $wpdb;
        $campaigns = $wpdb->prefix . 'ufsc_mail_campaigns';
        $queue = $wpdb->prefix . 'ufsc_mail_queue';
        $now = current_time( 'mysql' );

        $token = isset( $data['idempotency_token'] ) ? sanitize_text_field( (string) $data['idempotency_token'] ) : '';
        if ( '' !== $token ) {
            $token_key = self::IDEMPOTENCY_TRANSIENT_PREFIX . md5( get_current_user_id() . '|' . $token );
            if ( get_transient( $token_key ) ) { return; }
            set_transient( $token_key, 1, 15 * MINUTE_IN_SECONDS );
        }

        $recent = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$campaigns} WHERE created_by=%d AND updated_at >= DATE_SUB(%s, INTERVAL 2 MINUTE) AND subject=%s", get_current_user_id(), $now, $data['subject'] ) );
        if ( $recent ) { return; }

        $wpdb->insert( $campaigns, array(
            'name' => $data['name'], 'subject' => $data['subject'], 'message_html' => $data['message_html'], 'target_type' => $data['target_type'],
            'status' => 'queued', 'total_recipients' => count( $preview['valid_recipients'] ), 'sent_count' => 0, 'failed_count' => 0,
            'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now,
        ) );
        $campaign_id = (int) $wpdb->insert_id;
        if ( $campaign_id <= 0 ) { return; }

        foreach ( $preview['valid_recipients'] as $recipient ) {
            $wpdb->insert( $queue, array(
                'campaign_id' => $campaign_id,
                'club_id' => (int) $recipient['club_id'],
                'recipient_email' => sanitize_email( $recipient['email'] ),
                'recipient_name' => sanitize_text_field( $recipient['recipient_name'] ),
                'club_name' => sanitize_text_field( $recipient['club_name'] ),
                'status' => 'pending', 'attempts' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ) );
        }

        if ( class_exists( 'UFSC_Audit_Logger' ) ) {
            UFSC_Audit_Logger::log( 'UFSC Mail campaign created #' . $campaign_id . ' with ' . count( $preview['valid_recipients'] ) . ' recipients' );
        }
    }

    private static function compute_recipients_preview( $target, $limit_recipients = 0 ) {
        $result = self::get_targeted_recipients( $target );
        $limit_recipients = absint( $limit_recipients );
        $valid_recipients = $result['valid'];
        if ( $limit_recipients > 0 ) {
            $valid_recipients = array_slice( $valid_recipients, 0, $limit_recipients );
        }
        return array(
            'total_clubs' => count( $result['raw'] ),
            'valid_count' => count( $result['valid'] ),
            'ignored_count' => count( $result['ignored'] ),
            'valid_recipients' => $valid_recipients,
            'ignored_reasons' => $result['ignored'],
            'limit_recipients' => $limit_recipients,
        );
    }

    private static function get_targeted_recipients( $target ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $rows = $wpdb->get_results( "SELECT id, nom, email, statut FROM `{$table}`" );
        $valid = array(); $ignored = array(); $seen = array(); $current = function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '';

        foreach ( (array) $rows as $row ) {
            $status = strtolower( trim( (string) ( $row->statut ?? '' ) ) );
            if ( in_array( $status, array( 'supprime', 'supprimé', 'corbeille', 'deleted', 'trash' ), true ) ) { $ignored[] = array( 'club' => (string) $row->nom, 'email' => (string) ( $row->email ?? '' ), 'reason' => 'club supprimé/corbeille' ); continue; }

            $email_raw = (string) ( $row->email ?? '' );
            if ( '' === trim( $email_raw ) ) { $ignored[] = array( 'club' => (string) $row->nom, 'email' => '', 'reason' => 'email absent' ); continue; }
            $email = sanitize_email( $email_raw );
            if ( ! is_email( $email ) ) { $ignored[] = array( 'club' => (string) $row->nom, 'email' => $email_raw, 'reason' => 'email invalide' ); continue; }
            if ( isset( $seen[ strtolower( $email ) ] ) ) { $ignored[] = array( 'club' => (string) $row->nom, 'email' => $email, 'reason' => 'doublon email' ); continue; }
            if ( 'affiliated_up_to_date' === $target && function_exists( 'ufsc_is_affiliation_renewed' ) && ! ufsc_is_affiliation_renewed( (int) $row->id, $current ) ) { $ignored[] = array( 'club' => (string) $row->nom, 'email' => $email, 'reason' => 'affiliation non à jour' ); continue; }

            $seen[ strtolower( $email ) ] = true;
            $valid[] = array( 'club_id' => (int) $row->id, 'club_name' => (string) $row->nom, 'email' => $email, 'recipient_name' => (string) $row->nom );
        }

        return array( 'raw' => (array) $rows, 'valid' => $valid, 'ignored' => $ignored );
    }

    public static function process_queue( $limit = 15, $campaign_id = 0 ) {
        global $wpdb;
        if ( get_transient( self::LOCK_KEY ) ) { return; }
        set_transient( self::LOCK_KEY, 1, 120 );

        $queue = $wpdb->prefix . 'ufsc_mail_queue';
        $campaigns = $wpdb->prefix . 'ufsc_mail_campaigns';
        $where = "status IN ('pending','failed') AND attempts < 3";
        $params = array();
        if ( $campaign_id > 0 ) { $where .= ' AND campaign_id=%d'; $params[] = $campaign_id; }
        $params[] = (int) $limit;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$queue} WHERE {$where} ORDER BY id ASC LIMIT %d", $params ) );

        foreach ( (array) $rows as $row ) {
            $wpdb->update( $queue, array( 'status' => 'processing', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $row->id ) );
            $campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$campaigns} WHERE id=%d", (int) $row->campaign_id ) );
            if ( ! $campaign ) { continue; }
            $vars = self::build_template_vars( array( 'club_name' => $row->club_name, 'recipient_name' => $row->recipient_name ) );
            $ok = self::send_html_mail( $row->recipient_email, $campaign->subject, self::render_message( $campaign->message_html, $vars ) );
            if ( $ok ) {
                $wpdb->update( $queue, array( 'status' => 'sent', 'sent_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $row->id ) );
            } else {
                $wpdb->update( $queue, array( 'status' => 'failed', 'attempts' => (int) $row->attempts + 1, 'last_error' => 'wp_mail failed', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $row->id ) );
            }
        }

        self::refresh_campaign_statuses();
        delete_transient( self::LOCK_KEY );
    }

    private static function refresh_campaign_statuses() {
        global $wpdb;
        $campaigns = $wpdb->prefix . 'ufsc_mail_campaigns';
        $queue = $wpdb->prefix . 'ufsc_mail_queue';
        $all = $wpdb->get_results( "SELECT id FROM {$campaigns}" );
        foreach ( (array) $all as $c ) {
            $id = (int) $c->id;
            $sent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id=%d AND status='sent'", $id ) );
            $failed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id=%d AND status='failed'", $id ) );
            $pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id=%d AND status IN ('pending','processing')", $id ) );
            $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id=%d", $id ) );

            $status = 'queued';
            if ( $pending > 0 && ( $sent > 0 || $failed > 0 ) ) { $status = 'sending'; }
            if ( 0 === $pending && $total > 0 ) { $status = $failed > 0 ? 'completed_with_errors' : 'completed'; }
            if ( $failed > 0 && 0 === $sent && 0 === $pending ) { $status = 'failed'; }

            $wpdb->update( $campaigns, array(
                'sent_count' => $sent,
                'failed_count' => $failed,
                'total_recipients' => $total,
                'status' => $status,
                'updated_at' => current_time( 'mysql' ),
                'completed_at' => ( 0 === $pending ? current_time( 'mysql' ) : null ),
            ), array( 'id' => $id ) );
        }
    }

    private static function build_template_vars( $recipient ) {
        return array(
            '{club_name}' => (string) ( $recipient['club_name'] ?? '' ),
            '{recipient_name}' => (string) ( $recipient['recipient_name'] ?? '' ),
            '{season}' => function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '',
            '{club_dashboard_url}' => (string) admin_url( 'admin.php?page=ufsc-dashboard' ),
            '{ufsc_site_url}' => (string) home_url( '/' ),
        );
    }

    private static function render_message( $html, $vars ) {
        $rendered = (string) $html;
        foreach ( $vars as $token => $value ) {
            $rendered = str_replace( $token, esc_html( (string) $value ), $rendered );
        }
        return $rendered;
    }

    private static function send_html_mail( $to, $subject, $message ) {
        $from_name = apply_filters( 'ufsc_communication_from_name', apply_filters( 'ufsc_mail_from_name', 'UFSC France' ) );
        $from_email = apply_filters( 'ufsc_communication_from_email', apply_filters( 'ufsc_mail_from_email', 'infos@ufsc-france.org' ) );
        $reply_to = apply_filters( 'ufsc_communication_reply_to', apply_filters( 'ufsc_mail_reply_to', 'secretariat@ufsc-france.org' ) );
        if ( ! is_email( $reply_to ) ) { $reply_to = $from_email; }
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_email ) . '>',
            'Reply-To: ' . sanitize_email( $reply_to ),
        );
        return (bool) wp_mail( sanitize_email( $to ), sanitize_text_field( $subject ), wp_kses_post( $message ), $headers );
    }
}
