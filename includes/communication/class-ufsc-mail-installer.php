<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_Mail_Installer {
    const VERSION_OPTION = 'ufsc_mail_module_version';
    const VERSION = '1.1.0';

    public static function ensure_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $campaigns = $wpdb->prefix . 'ufsc_mail_campaigns';
        $queue = $wpdb->prefix . 'ufsc_mail_queue';
        $contacts = $wpdb->prefix . 'ufsc_mail_contacts';
        $lists = $wpdb->prefix . 'ufsc_mail_lists';
        $list_items = $wpdb->prefix . 'ufsc_mail_list_items';

        dbDelta("CREATE TABLE {$campaigns} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL DEFAULT '',
            subject varchar(255) NOT NULL,
            message_html longtext NOT NULL,
            target_type varchar(100) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            total_recipients int unsigned NOT NULL DEFAULT 0,
            sent_count int unsigned NOT NULL DEFAULT 0,
            failed_count int unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            started_at datetime NULL,
            completed_at datetime NULL,
            PRIMARY KEY  (id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$queue} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            club_id bigint(20) unsigned NOT NULL DEFAULT 0,
            recipient_email varchar(191) NOT NULL,
            recipient_name varchar(191) NOT NULL DEFAULT '',
            club_name varchar(191) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts tinyint unsigned NOT NULL DEFAULT 0,
            last_error text NULL,
            sent_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_campaign (campaign_id),
            KEY idx_status (status),
            KEY idx_recipient_email (recipient_email)
        ) {$charset};");

        dbDelta("CREATE TABLE {$contacts} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL DEFAULT '',
            email varchar(191) NOT NULL,
            organization varchar(191) NOT NULL DEFAULT '',
            role_label varchar(191) NOT NULL DEFAULT '',
            phone varchar(60) NOT NULL DEFAULT '',
            notes text NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_email (email)
        ) {$charset};");

        dbDelta("CREATE TABLE {$lists} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            description text NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$list_items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            list_id bigint(20) unsigned NOT NULL,
            contact_id bigint(20) unsigned NULL,
            recipient_type varchar(50) NOT NULL DEFAULT 'custom_contact',
            recipient_id bigint(20) unsigned NULL,
            email varchar(191) NOT NULL,
            name varchar(191) NOT NULL DEFAULT '',
            source varchar(100) NOT NULL DEFAULT 'custom_list',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_list_id (list_id),
            KEY idx_email (email)
        ) {$charset};");

        update_option( self::VERSION_OPTION, self::VERSION, false );
    }
}
