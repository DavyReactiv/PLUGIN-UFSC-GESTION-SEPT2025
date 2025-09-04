<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Cron tasks for UFSC Gestion
 */
class UFSC_Cron {

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'ufsc_daily', array( __CLASS__, 'process_licences' ) );
    }

    /**
     * Process licence expirations and send reminders.
     */
    public static function process_licences() {
        global $wpdb;

        $settings       = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        $clubs_table    = $settings['table_clubs'];

        // Expire licences past their expiration date.
        $expired_ids = $wpdb->get_col(
            "SELECT id FROM {$licences_table} WHERE expires_at IS NOT NULL AND expires_at < NOW() AND statut <> 'expired'"
        );

        foreach ( $expired_ids as $licence_id ) {
            $wpdb->update(
                $licences_table,
                array( 'statut' => 'expired' ),
                array( 'id' => $licence_id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        // Send reminders 30 days before expiry.
        $reminder_date = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
        $reminders     = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.id, l.email, l.expires_at, c.email AS club_email FROM {$licences_table} l LEFT JOIN {$clubs_table} c ON l.club_id = c.id WHERE DATE(l.expires_at) = %s",
                $reminder_date
            )
        );

        foreach ( $reminders as $row ) {
            $to = $row->email ? $row->email : $row->club_email;
            if ( ! $to ) {
                continue;
            }

            $subject = __( 'UFSC Licence expiring soon', 'ufsc-clubs' );
            $message = sprintf(
                __( 'Your licence will expire on %s.', 'ufsc-clubs' ),
                date_i18n( get_option( 'date_format' ), strtotime( $row->expires_at ) )
            );

            wp_mail( $to, $subject, $message );
        }
    }
}
UFSC_Cron::init();
