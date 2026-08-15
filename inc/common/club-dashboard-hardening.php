<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Additive hardening for licence chronology and the Club Account UX.
 * Historical rows are never backfilled with an invented date.
 */

function ufsc_traceability_licence_table() {
    if ( function_exists( 'ufsc_get_licences_table' ) ) {
        return ufsc_get_licences_table();
    }
    if ( class_exists( 'UFSC_SQL' ) ) {
        $settings = UFSC_SQL::get_settings();
        return isset( $settings['table_licences'] ) ? $settings['table_licences'] : '';
    }
    return '';
}

function ufsc_traceability_table_columns( $table ) {
    global $wpdb;
    if ( ! $table ) { return array(); }
    if ( function_exists( 'ufsc_table_columns' ) ) {
        return (array) ufsc_table_columns( $table );
    }
    return (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
}

/** Ensure only new nullable chronology columns. Never updates existing row values. */
function ufsc_ensure_licence_traceability_columns() {
    global $wpdb;
    $table = ufsc_traceability_licence_table();
    if ( ! $table ) { return; }

    $columns = ufsc_traceability_table_columns( $table );
    $definitions = array(
        'submitted_at' => 'datetime NULL DEFAULT NULL',
        'validated_at' => 'datetime NULL DEFAULT NULL',
        'validated_by' => 'bigint(20) unsigned NULL DEFAULT NULL',
    );

    $changed = false;
    foreach ( $definitions as $column => $definition ) {
        if ( ! in_array( $column, $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}" );
            $changed = true;
        }
    }
    if ( $changed && function_exists( 'ufsc_flush_table_columns_cache' ) ) {
        ufsc_flush_table_columns_cache();
    }
}
add_action( 'init', 'ufsc_ensure_licence_traceability_columns', 6 );

function ufsc_traceability_submit_intent() {
    return isset( $_POST['ufsc_submit_action'] ) && ! is_array( $_POST['ufsc_submit_action'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) )
        : '';
}

/** Stamp the first real club submission only; drafts remain unstamped. */
function ufsc_stamp_licence_submitted_at( $licence_id ) {
    global $wpdb;
    $licence_id = absint( $licence_id );
    if ( $licence_id < 1 || ! in_array( ufsc_traceability_submit_intent(), array( 'add_to_cart', 'submit', 'finalize' ), true ) ) {
        return;
    }
    $table = ufsc_traceability_licence_table();
    $columns = ufsc_traceability_table_columns( $table );
    if ( ! in_array( 'submitted_at', $columns, true ) ) { return; }

    $existing = (string) $wpdb->get_var( $wpdb->prepare( "SELECT submitted_at FROM `{$table}` WHERE id = %d", $licence_id ) );
    if ( '' === trim( $existing ) || '0000-00-00 00:00:00' === $existing ) {
        $wpdb->update( $table, array( 'submitted_at' => current_time( 'mysql' ) ), array( 'id' => $licence_id ), array( '%s' ), array( '%d' ) );
    }
}
add_action( 'ufsc_licence_created', 'ufsc_stamp_licence_submitted_at', 20, 1 );

function ufsc_stamp_existing_licence_submission_from_request() {
    $licence_id = isset( $_POST['licence_id'] ) ? absint( wp_unslash( $_POST['licence_id'] ) ) : 0;
    if ( $licence_id > 0 ) {
        ufsc_stamp_licence_submitted_at( $licence_id );
    }
}
add_action( 'ufsc_licence_updated', 'ufsc_stamp_existing_licence_submission_from_request', 20 );

/**
 * Capture pre-validation states before the admin handler runs. We only stamp a
 * later transition when the database actually changed from non-valid to valid.
 */
function ufsc_capture_admin_validation_candidates() {
    global $wpdb;
    $table = ufsc_traceability_licence_table();
    if ( ! $table || empty( $_POST ) ) { return; }

    $ids = array();
    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
    $requested_status = isset( $_POST['statut'] ) && ! is_array( $_POST['statut'] ) ? sanitize_key( wp_unslash( $_POST['statut'] ) ) : '';

    if ( 'ufsc_sql_save_licence' === $action && 'valide' === $requested_status ) {
        $id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
        if ( $id ) { $ids[] = $id; }
    }

    $bulk_action = isset( $_POST['bulk_action'] ) && ! is_array( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
    if ( 'validate' === $bulk_action && isset( $_POST['licence_ids'] ) && is_array( $_POST['licence_ids'] ) ) {
        $ids = array_merge( $ids, array_map( 'absint', wp_unslash( $_POST['licence_ids'] ) ) );
    }

    $ids = array_values( array_unique( array_filter( $ids ) ) );
    if ( ! $ids ) { return; }

    $previous = array();
    foreach ( $ids as $id ) {
        $status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT statut FROM `{$table}` WHERE id = %d", $id ) );
        $previous[ $id ] = function_exists( 'ufsc_normalize_license_status' ) ? ufsc_normalize_license_status( $status ) : sanitize_key( $status );
    }
    $GLOBALS['ufsc_validation_trace_candidates'] = $previous;
}
add_action( 'admin_init', 'ufsc_capture_admin_validation_candidates', 1 );

function ufsc_commit_admin_validation_trace() {
    global $wpdb;
    $candidates = isset( $GLOBALS['ufsc_validation_trace_candidates'] ) && is_array( $GLOBALS['ufsc_validation_trace_candidates'] )
        ? $GLOBALS['ufsc_validation_trace_candidates'] : array();
    if ( ! $candidates ) { return; }

    $table = ufsc_traceability_licence_table();
    $columns = ufsc_traceability_table_columns( $table );
    if ( ! in_array( 'validated_at', $columns, true ) || ! in_array( 'validated_by', $columns, true ) ) { return; }

    foreach ( $candidates as $id => $previous_status ) {
        if ( 'valide' === $previous_status ) { continue; }
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT statut, validated_at FROM `{$table}` WHERE id = %d", absint( $id ) ) );
        if ( ! $row ) { continue; }
        $current_status = function_exists( 'ufsc_normalize_license_status' ) ? ufsc_normalize_license_status( $row->statut ) : sanitize_key( $row->statut );
        if ( 'valide' !== $current_status ) { continue; }
        if ( ! empty( $row->validated_at ) && '0000-00-00 00:00:00' !== $row->validated_at ) { continue; }
        $wpdb->update(
            $table,
            array( 'validated_at' => current_time( 'mysql' ), 'validated_by' => get_current_user_id() ),
            array( 'id' => absint( $id ) ),
            array( '%s', '%d' ),
            array( '%d' )
        );
    }
}
add_action( 'shutdown', 'ufsc_commit_admin_validation_trace', 2 );

function ufsc_format_trace_date( $value, $historical_label = true ) {
    $value = trim( (string) $value );
    if ( '' === $value || '0000-00-00 00:00:00' === $value || '0000-00-00' === $value ) {
        return $historical_label ? __( 'Historique : date non disponible', 'ufsc-clubs' ) : '—';
    }
    return function_exists( 'mysql2date' ) ? mysql2date( 'd/m/Y H:i', $value ) : $value;
}

/** Show chronology where administrators make decisions, without rewriting history. */
function ufsc_admin_licence_traceability_notice() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) { return; }
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( ! in_array( $page, array( 'ufsc-sql-licences', 'ufsc-gestion-licences', 'ufsc-licences', 'ufsc_lc_licences' ), true ) ) { return; }
    $licence_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : ( isset( $_GET['licence_id'] ) ? absint( wp_unslash( $_GET['licence_id'] ) ) : 0 );
    if ( $licence_id < 1 ) { return; }

    global $wpdb;
    $table = ufsc_traceability_licence_table();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT date_creation, submitted_at, validated_at, validated_by, statut FROM `{$table}` WHERE id = %d", $licence_id ) );
    if ( ! $row ) { return; }

    $validator = ! empty( $row->validated_by ) ? get_userdata( absint( $row->validated_by ) ) : null;
    $validator_label = $validator ? $validator->display_name : ( ! empty( $row->validated_by ) ? '#' . absint( $row->validated_by ) : '—' );
    echo '<div class="notice notice-info ufsc-traceability-notice"><p><strong>' . esc_html__( 'Traçabilité de la licence', 'ufsc-clubs' ) . '</strong><br>';
    echo esc_html__( 'Créée :', 'ufsc-clubs' ) . ' ' . esc_html( ufsc_format_trace_date( $row->date_creation, true ) ) . ' · ';
    echo esc_html__( 'Soumise :', 'ufsc-clubs' ) . ' ' . esc_html( ufsc_format_trace_date( $row->submitted_at, false ) ) . ' · ';
    echo esc_html__( 'Validation admin :', 'ufsc-clubs' ) . ' ' . esc_html( ufsc_format_trace_date( $row->validated_at, 'valide' === sanitize_key( (string) $row->statut ) ) ) . ' · ';
    echo esc_html__( 'Validée par :', 'ufsc-clubs' ) . ' ' . esc_html( $validator_label ) . '</p></div>';
}
add_action( 'admin_notices', 'ufsc_admin_licence_traceability_notice' );

function ufsc_account_missing_profile_actions( $club_id, $season ) {
    global $wpdb;
    $actions = array();
    if ( $club_id < 1 || ! class_exists( 'UFSC_SQL' ) ) { return $actions; }

    $settings = UFSC_SQL::get_settings();
    $licences_table = $settings['table_licences'];
    $clubs_table = $settings['table_clubs'];
    $columns = ufsc_traceability_table_columns( $licences_table );
    $season_column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $licences_table ) : '';

    $role_counts = array( 'president' => 0, 'secretaire' => 0, 'tresorier' => 0 );
    if ( $season_column && preg_match( '/^\d{4}-\d{4}$/', (string) $season ) ) {
        if ( 'season_end_year' === $season_column && preg_match( '/^\d{4}-(\d{4})$/', $season, $m ) ) {
            $season_sql = $wpdb->prepare( 'season_end_year = %d', (int) $m[1] );
        } else {
            $season_sql = $wpdb->prepare( "REPLACE(TRIM(`{$season_column}`), '/', '-') = %s", str_replace( '/', '-', $season ) );
        }
        $deleted_sql = in_array( 'deleted_at', $columns, true ) ? " AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')" : '';
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT role, COUNT(*) total FROM `{$licences_table}` WHERE club_id = %d AND role IN ('president','secretaire','tresorier') AND {$season_sql}{$deleted_sql} GROUP BY role", $club_id ) );
        foreach ( (array) $rows as $row ) {
            if ( isset( $role_counts[ $row->role ] ) ) { $role_counts[ $row->role ] = (int) $row->total; }
        }
    }

    $role_labels = array( 'president' => __( 'Président : licence à renseigner pour la saison', 'ufsc-clubs' ), 'secretaire' => __( 'Secrétaire : licence à renseigner pour la saison', 'ufsc-clubs' ), 'tresorier' => __( 'Trésorier : licence à renseigner pour la saison', 'ufsc-clubs' ) );
    foreach ( $role_counts as $role => $count ) {
        if ( $count < 1 ) {
            $actions[] = array( 'type' => 'role', 'label' => $role_labels[ $role ] );
        }
    }

    $club = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$clubs_table}` WHERE id = %d", $club_id ) );
    if ( $club ) {
        $docs = array(
            'doc_statuts' => __( 'Statuts de l’association', 'ufsc-clubs' ),
            'doc_recepisse' => __( 'Récépissé de déclaration', 'ufsc-clubs' ),
            'doc_jo' => __( 'Publication au Journal Officiel', 'ufsc-clubs' ),
            'doc_pv_ag' => __( 'Procès-verbal d’assemblée générale', 'ufsc-clubs' ),
            'doc_cer' => __( 'Contrat d’engagement républicain', 'ufsc-clubs' ),
            'doc_attestation_cer' => __( 'Attestation CER', 'ufsc-clubs' ),
        );
        foreach ( $docs as $key => $label ) {
            $attachment_id = isset( $club->{$key} ) ? absint( $club->{$key} ) : 0;
            if ( $attachment_id < 1 || ! wp_get_attachment_url( $attachment_id ) ) {
                $actions[] = array( 'type' => 'document', 'label' => $label );
            }
        }
    }
    return $actions;
}

function ufsc_render_account_action_box( $club_id, $season ) {
    $actions = ufsc_account_missing_profile_actions( $club_id, $season );
    ob_start();
    if ( $actions ) {
        echo '<section class="ufsc-card ufsc-profile-actions" aria-labelledby="ufsc-profile-actions-title"><div class="ufsc-profile-actions__heading"><h4 id="ufsc-profile-actions-title">' . esc_html__( 'Éléments à compléter', 'ufsc-clubs' ) . '</h4><p>' . esc_html__( 'Voici exactement ce qu’il reste à corriger ou transmettre sur le profil du club.', 'ufsc-clubs' ) . '</p></div><ul>';
        foreach ( $actions as $action ) {
            $target = 'role' === $action['type'] ? 'club-officers' : 'club-documents';
            $button = 'role' === $action['type'] ? __( 'Compléter le bureau', 'ufsc-clubs' ) : __( 'Ajouter le document', 'ufsc-clubs' );
            echo '<li><span>' . ( 'role' === $action['type'] ? '👥 ' : '📄 ' ) . esc_html( $action['label'] ) . '</span><a class="ufsc-btn ufsc-btn-secondary" href="' . esc_url( UFSC_Frontend_Shortcodes::get_club_portal_url( $target ) ) . '">' . esc_html( $button ) . '</a></li>';
        }
        echo '</ul></section>';
    } else {
        echo '<div class="ufsc-message ufsc-success ufsc-profile-complete"><strong>' . esc_html__( 'Profil club complet', 'ufsc-clubs' ) . '</strong> ' . esc_html__( 'Aucune action requise actuellement.', 'ufsc-clubs' ) . '</div>';
    }
    return ob_get_clean();
}

function ufsc_render_affiliation_trace_box( $club_id, $season ) {
    if ( ! class_exists( 'UFSC_Season_Archive_Manager' ) ) { return ''; }
    $affiliation = UFSC_Season_Archive_Manager::get_affiliation( $club_id, $season );
    if ( ! $affiliation ) { return ''; }
    $validator = ! empty( $affiliation->validated_by ) ? get_userdata( absint( $affiliation->validated_by ) ) : null;
    $validator_label = $validator ? $validator->display_name : ( ! empty( $affiliation->validated_by ) ? '#' . absint( $affiliation->validated_by ) : '—' );

    return '<section class="ufsc-card ufsc-affiliation-trace"><h4>' . esc_html( sprintf( __( 'Traçabilité affiliation %s', 'ufsc-clubs' ), $season ) ) . '</h4><dl>'
        . '<div><dt>' . esc_html__( 'Créée', 'ufsc-clubs' ) . '</dt><dd>' . esc_html( ufsc_format_trace_date( $affiliation->created_at ?? '', true ) ) . '</dd></div>'
        . '<div><dt>' . esc_html__( 'Soumise', 'ufsc-clubs' ) . '</dt><dd>' . esc_html( ufsc_format_trace_date( $affiliation->requested_at ?? '', false ) ) . '</dd></div>'
        . '<div><dt>' . esc_html__( 'Payée', 'ufsc-clubs' ) . '</dt><dd>' . esc_html( ufsc_format_trace_date( $affiliation->paid_at ?? '', false ) ) . '</dd></div>'
        . '<div><dt>' . esc_html__( 'Validée', 'ufsc-clubs' ) . '</dt><dd>' . esc_html( ufsc_format_trace_date( $affiliation->validated_at ?? '', false ) ) . '</dd></div>'
        . '<div><dt>' . esc_html__( 'Validée par', 'ufsc-clubs' ) . '</dt><dd>' . esc_html( $validator_label ) . '</dd></div>'
        . '</dl></section>';
}

/** Inject server-derived actions/trace into the existing profile without duplicating the shortcode. */
function ufsc_enrich_club_profile_shortcode_output( $output, $tag, $attr, $m ) {
    unset( $attr, $m );
    if ( 'ufsc_club_profile' !== $tag || ! is_user_logged_in() || ! class_exists( 'UFSC_Frontend_Shortcodes' ) ) {
        return $output;
    }
    $club_id = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
    if ( $club_id < 1 ) { return $output; }
    $season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
    $insert = ufsc_render_account_action_box( $club_id, $season ) . ufsc_render_affiliation_trace_box( $club_id, $season );
    if ( '' === $insert ) { return $output; }

    $needle = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) );
    $pos = strpos( $output, $needle );
    if ( false !== $pos ) {
        return substr( $output, 0, $pos ) . $insert . substr( $output, $pos );
    }
    return $output . $insert;
}
add_filter( 'do_shortcode_tag', 'ufsc_enrich_club_profile_shortcode_output', 20, 4 );

function ufsc_enqueue_club_mobile_v2() {
    if ( is_admin() || ! defined( 'UFSC_CL_URL' ) ) { return; }
    wp_enqueue_style( 'ufsc-club-mobile-v2', UFSC_CL_URL . 'assets/css/ufsc-club-mobile-v2.css', array( 'ufsc-front' ), defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
}
add_action( 'wp_enqueue_scripts', 'ufsc_enqueue_club_mobile_v2', 30 );
