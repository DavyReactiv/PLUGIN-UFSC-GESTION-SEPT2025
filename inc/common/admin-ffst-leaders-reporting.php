<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin FFST reporting and pack-office presentation.
 *
 * Read-only compatibility layer:
 * - exposes the canonical annual "active" club filter through the existing KPI filter;
 * - displays the canonical FFST licence number in the admin licences table;
 * - exports leaders/coaches for active clubs as CSV;
 * - explains the 10 included licences as 3 office places + 7 other places.
 *
 * No licence, club, affiliation, order or quota row is mutated here.
 */

function ufsc_ffst_reporting_club_admin_pages() {
    return array( 'ufsc-sql-clubs', 'ufsc-clubs', 'ufsc_clubs', 'ufsc-gestion-clubs' );
}

function ufsc_ffst_reporting_licence_admin_pages() {
    return array( 'ufsc_lc_licences', 'ufsc-sql-licences', 'ufsc-sql-licenses', 'ufsc-gestion-licences', 'ufsc-licences' );
}

function ufsc_ffst_reporting_admin_page() {
    if ( ! is_admin() ) { return ''; }
    return isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
}

/**
 * Bridge the requested "Actif" status to the canonical annual-affiliation KPI filter.
 * The clubs list already centralises annual status logic under kpi_filter; reuse it
 * instead of creating a second SQL definition.
 */
function ufsc_ffst_reporting_bridge_active_club_filter() {
    if ( ! is_admin() || ! in_array( ufsc_ffst_reporting_admin_page(), ufsc_ffst_reporting_club_admin_pages(), true ) ) {
        return;
    }

    $status = isset( $_GET['statut'] ) && ! is_array( $_GET['statut'] ) ? sanitize_key( wp_unslash( $_GET['statut'] ) ) : '';
    if ( ! in_array( $status, array( 'active', 'actif', 'affilie', 'affiliee' ), true ) ) {
        return;
    }

    $_GET['kpi_filter'] = 'affiliations_active';
    unset( $_GET['statut'] );
    $GLOBALS['ufsc_ffst_reporting_active_filter'] = true;
}
add_action( 'admin_init', 'ufsc_ffst_reporting_bridge_active_club_filter', 1 );

/** Return the requested season, falling back to the canonical current season. */
function ufsc_ffst_reporting_season( $raw = '' ) {
    $season = is_string( $raw ) ? trim( $raw ) : '';
    if ( function_exists( 'ufsc_normalize_season_reference' ) ) {
        $season = (string) ufsc_normalize_season_reference( $season );
    }
    if ( ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
        $season = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : '';
    }
    return preg_match( '/^\d{4}-\d{4}$/', $season ) ? $season : '';
}

/** Excel-safe scalar for CSV output. */
function ufsc_ffst_reporting_csv_value( $value ) {
    $value = preg_replace( '/[\r\n]+/u', ' ', trim( (string) $value ) );
    if ( preg_match( '/^[=+\-@]/', $value ) ) {
        $value = "'" . $value;
    }
    return $value;
}

/** Pick the first existing value from an object/array. */
function ufsc_ffst_reporting_value( $row, $fields, $default = '' ) {
    foreach ( (array) $fields as $field ) {
        $value = is_array( $row ) ? ( $row[ $field ] ?? '' ) : ( is_object( $row ) ? ( $row->{$field} ?? '' ) : '' );
        if ( '' !== trim( (string) $value ) ) {
            return trim( (string) $value );
        }
    }
    return $default;
}

/** Canonical role label for reporting. */
function ufsc_ffst_reporting_role_label( $role ) {
    $role = function_exists( 'ufsc_normalize_club_role' ) ? ufsc_normalize_club_role( $role ) : sanitize_key( (string) $role );
    $labels = array(
        'president' => 'Président',
        'secretaire' => 'Secrétaire',
        'tresorier' => 'Trésorier',
        'entraineur' => 'Entraîneur',
        'coach' => 'Coach',
        'educateur' => 'Éducateur',
        'encadrant' => 'Encadrant',
        'responsable_technique' => 'Responsable technique',
        'dirigeant' => 'Dirigeant',
    );
    return $labels[ $role ] ?? ucfirst( str_replace( '_', ' ', (string) $role ) );
}

/** True for the leadership/coaching roles requested in the FFST export. */
function ufsc_ffst_reporting_is_export_role( $role ) {
    $role = function_exists( 'ufsc_normalize_club_role' ) ? ufsc_normalize_club_role( $role ) : sanitize_key( (string) $role );
    return in_array( $role, array( 'president', 'secretaire', 'tresorier', 'entraineur', 'coach', 'educateur', 'encadrant', 'responsable_technique', 'dirigeant' ), true );
}

/** Restrict exported clubs to the connected user's authorised regional scope. */
function ufsc_ffst_reporting_region_allowed( $region ) {
    if ( function_exists( 'ufsc_user_has_all_regions_access' ) && ufsc_user_has_all_regions_access() ) {
        return true;
    }
    if ( function_exists( 'ufsc_current_user_allowed_regions' ) ) {
        $allowed = array_filter( array_map( 'strval', (array) ufsc_current_user_allowed_regions() ) );
        if ( ! empty( $allowed ) ) {
            return in_array( (string) $region, $allowed, true );
        }
    }
    return true;
}

/**
 * Download one row per leader/coach for active clubs in the selected season.
 * Mandatory office posts are emitted as "MANQUANT" when absent, making the CSV
 * useful as both a contact list and an administrative completeness check.
 */
function ufsc_ffst_reporting_export_active_leaders_csv() {
    if ( ! current_user_can( 'read' ) || ( function_exists( 'ufsc_user_can' ) && class_exists( 'UFSC_Permissions' ) && ! ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }
    check_admin_referer( 'ufsc_export_active_leaders_csv' );

    global $wpdb;
    $settings = class_exists( 'UFSC_SQL' ) ? (array) UFSC_SQL::get_settings() : array();
    $clubs_table = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['table_clubs'] ?? '' ) );
    $licences_table = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['table_licences'] ?? '' ) );
    $affiliations_table = class_exists( 'UFSC_Storage_Resolver' )
        ? preg_replace( '/[^A-Za-z0-9_]/', '', (string) UFSC_Storage_Resolver::get_annual_affiliations_table() )
        : $wpdb->prefix . 'ufsc_affiliations_seasons';

    if ( '' === $clubs_table || '' === $licences_table || '' === $affiliations_table ) {
        wp_die( esc_html__( 'Les tables UFSC nécessaires sont indisponibles.', 'ufsc-clubs' ) );
    }

    $season = ufsc_ffst_reporting_season( isset( $_POST['season'] ) && ! is_array( $_POST['season'] ) ? sanitize_text_field( wp_unslash( $_POST['season'] ) ) : '' );
    if ( '' === $season ) {
        wp_die( esc_html__( 'Saison invalide.', 'ufsc-clubs' ) );
    }
    $region_filter = isset( $_POST['region'] ) && ! is_array( $_POST['region'] ) ? sanitize_text_field( wp_unslash( $_POST['region'] ) ) : '';

    $active_statuses = array( 'active', 'validated', 'valide' );
    $status_placeholders = implode( ',', array_fill( 0, count( $active_statuses ), '%s' ) );
    $sql = $wpdb->prepare(
        "SELECT c.*, a.num_affiliation AS annual_num_affiliation
         FROM `{$clubs_table}` c
         INNER JOIN `{$affiliations_table}` a ON a.club_id = c.id
         WHERE a.season = %s AND LOWER(a.status) IN ({$status_placeholders})
         ORDER BY c.nom ASC, c.id ASC",
        array_merge( array( $season ), $active_statuses )
    );
    $clubs = (array) $wpdb->get_results( $sql );

    $club_map = array();
    foreach ( $clubs as $club ) {
        $region = ufsc_ffst_reporting_value( $club, array( 'region' ) );
        if ( '' !== $region_filter && $region !== $region_filter ) { continue; }
        if ( ! ufsc_ffst_reporting_region_allowed( $region ) ) { continue; }
        $club_map[ absint( $club->id ?? 0 ) ] = $club;
    }
    $club_map = array_filter( $club_map );

    $licences_by_club = array();
    if ( ! empty( $club_map ) ) {
        $season_context = function_exists( 'ufsc_get_pack_season_storage_context' ) ? ufsc_get_pack_season_storage_context( $licences_table, $season ) : array( 'column' => '', 'value' => '' );
        $season_column = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $season_context['column'] ?? '' ) );
        $season_value  = (string) ( $season_context['value'] ?? '' );
        if ( '' !== $season_column ) {
            $ids = array_keys( $club_map );
            $id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $licence_sql = $wpdb->prepare(
                "SELECT * FROM `{$licences_table}` WHERE club_id IN ({$id_placeholders}) AND `{$season_column}` = %s ORDER BY club_id ASC, id ASC",
                array_merge( $ids, array( $season_value ) )
            );
            foreach ( (array) $wpdb->get_results( $licence_sql ) as $licence ) {
                $role = function_exists( 'ufsc_normalize_club_role' ) ? ufsc_normalize_club_role( $licence->role ?? '' ) : sanitize_key( (string) ( $licence->role ?? '' ) );
                if ( ! ufsc_ffst_reporting_is_export_role( $role ) ) { continue; }
                $licence->_ufsc_export_role = $role;
                $licences_by_club[ absint( $licence->club_id ?? 0 ) ][] = $licence;
            }
        }
    }

    if ( headers_sent() ) {
        wp_die( esc_html__( 'Les en-têtes ont déjà été envoyés : export impossible.', 'ufsc-clubs' ) );
    }

    $filename = 'ufsc-dirigeants-clubs-actifs-' . sanitize_file_name( $season ) . '-' . gmdate( 'Ymd-His' ) . '.csv';
    nocache_headers();
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

    $out = fopen( 'php://output', 'w' );
    if ( false === $out ) { exit; }
    fwrite( $out, "\xEF\xBB\xBF" );
    fputcsv( $out, array( 'Saison', 'Club', 'Région', 'N° affiliation', 'Fonction', 'État poste', 'Nom', 'Prénom', 'Date de naissance', 'Ville naissance + Dept / Pays', 'E-mail', 'Téléphone', 'N° licence FFST', 'Statut licence' ), ';' );

    $mandatory = array( 'president', 'secretaire', 'tresorier' );
    foreach ( $club_map as $club_id => $club ) {
        $rows = (array) ( $licences_by_club[ $club_id ] ?? array() );
        $present = array();
        foreach ( $rows as $licence ) {
            $role = (string) ( $licence->_ufsc_export_role ?? '' );
            if ( in_array( $role, $mandatory, true ) ) { $present[ $role ] = true; }
            $birth_place = ufsc_ffst_reporting_value( $licence, array( 'ville_naissance_departement', 'lieu_naissance', 'ville_naissance', 'birthplace' ) );
            if ( '' === $birth_place ) {
                $city = ufsc_ffst_reporting_value( $licence, array( 'ville_naissance' ) );
                $dept = ufsc_ffst_reporting_value( $licence, array( 'departement_naissance', 'dept_naissance' ) );
                $country = ufsc_ffst_reporting_value( $licence, array( 'pays_naissance', 'birth_country' ) );
                $birth_place = trim( $city . ( $dept ? ' - ' . $dept : '' ) . ( $country ? ' - ' . $country : '' ), ' -' );
            }
            $status_raw = ufsc_ffst_reporting_value( $licence, array( 'statut', 'status' ) );
            $status_label = function_exists( 'ufsc_get_licence_status_label_fr' ) ? ufsc_get_licence_status_label_fr( $status_raw ) : $status_raw;
            $line = array(
                $season,
                ufsc_ffst_reporting_value( $club, array( 'nom', 'nom_club', 'name' ) ),
                ufsc_ffst_reporting_value( $club, array( 'region' ) ),
                ufsc_ffst_reporting_value( $club, array( 'annual_num_affiliation', 'num_affiliation', 'numero_affiliation_ufsc' ) ),
                ufsc_ffst_reporting_role_label( $role ),
                'Renseigné',
                ufsc_ffst_reporting_value( $licence, array( 'nom', 'nom_licence' ) ),
                ufsc_ffst_reporting_value( $licence, array( 'prenom' ) ),
                ufsc_ffst_reporting_value( $licence, array( 'date_naissance' ) ),
                $birth_place,
                ufsc_ffst_reporting_value( $licence, array( 'email' ) ),
                ufsc_ffst_reporting_value( $licence, array( 'tel_mobile', 'telephone', 'tel_fixe' ) ),
                ufsc_ffst_reporting_value( $licence, array( 'numero_licence_ffst' ) ),
                $status_label,
            );
            fputcsv( $out, array_map( 'ufsc_ffst_reporting_csv_value', $line ), ';' );
        }

        foreach ( $mandatory as $missing_role ) {
            if ( ! empty( $present[ $missing_role ] ) ) { continue; }
            $line = array(
                $season,
                ufsc_ffst_reporting_value( $club, array( 'nom', 'nom_club', 'name' ) ),
                ufsc_ffst_reporting_value( $club, array( 'region' ) ),
                ufsc_ffst_reporting_value( $club, array( 'annual_num_affiliation', 'num_affiliation', 'numero_affiliation_ufsc' ) ),
                ufsc_ffst_reporting_role_label( $missing_role ),
                'MANQUANT', '', '', '', '', '', '', '', '',
            );
            fputcsv( $out, array_map( 'ufsc_ffst_reporting_csv_value', $line ), ';' );
        }
    }
    fclose( $out );
    exit;
}
add_action( 'admin_post_ufsc_export_active_leaders_csv', 'ufsc_ffst_reporting_export_active_leaders_csv' );

/** Render a compact export action and quota rule on the Clubs admin screen. */
function ufsc_ffst_reporting_admin_notice() {
    $page = ufsc_ffst_reporting_admin_page();
    if ( ! in_array( $page, ufsc_ffst_reporting_club_admin_pages(), true ) ) { return; }
    if ( function_exists( 'ufsc_user_can' ) && class_exists( 'UFSC_Permissions' ) && ! ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) { return; }

    $season = ufsc_ffst_reporting_season( isset( $_GET['season'] ) && ! is_array( $_GET['season'] ) ? sanitize_text_field( wp_unslash( $_GET['season'] ) ) : '' );
    $region = isset( $_GET['region'] ) && ! is_array( $_GET['region'] ) ? sanitize_text_field( wp_unslash( $_GET['region'] ) ) : '';
    $limit = function_exists( 'ufsc_get_pack_included_limit' ) ? absint( ufsc_get_pack_included_limit() ) : 10;
    $other = max( 0, $limit - 3 );
    ?>
    <div class="notice notice-info ufsc-ffst-reporting-notice">
        <p><strong><?php esc_html_e( 'Pack affiliation UFSC', 'ufsc-clubs' ); ?></strong> — <?php echo esc_html( sprintf( __( '%1$d licences incluses : 3 places identifiées pour le bureau (Président, Secrétaire, Trésorier) + %2$d autres licences incluses.', 'ufsc-clubs' ), $limit, $other ) ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:8px 0 10px;">
            <input type="hidden" name="action" value="ufsc_export_active_leaders_csv">
            <input type="hidden" name="season" value="<?php echo esc_attr( $season ); ?>">
            <input type="hidden" name="region" value="<?php echo esc_attr( $region ); ?>">
            <?php wp_nonce_field( 'ufsc_export_active_leaders_csv' ); ?>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Exporter les dirigeants des clubs actifs (CSV)', 'ufsc-clubs' ); ?></button>
            <span class="description"><?php echo esc_html( $region ? sprintf( __( 'Saison %1$s · région %2$s', 'ufsc-clubs' ), $season, $region ) : sprintf( __( 'Saison %s · toutes les régions autorisées', 'ufsc-clubs' ), $season ) ); ?></span>
        </form>
    </div>
    <?php
}
add_action( 'admin_notices', 'ufsc_ffst_reporting_admin_notice', 20 );

/** Read-only AJAX endpoint used to replace the obsolete ASPTT column on screen. */
function ufsc_ffst_reporting_ajax_numbers() {
    if ( ! current_user_can( 'read' ) ) { wp_send_json_error( array( 'message' => 'forbidden' ), 403 ); }
    check_ajax_referer( 'ufsc_ffst_reporting_numbers', 'nonce' );
    $ids = isset( $_POST['ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) ) ) : array();
    $ids = array_slice( $ids, 0, 100 );
    if ( empty( $ids ) ) { wp_send_json_success( array() ); }

    global $wpdb;
    $settings = class_exists( 'UFSC_SQL' ) ? (array) UFSC_SQL::get_settings() : array();
    $table = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['table_licences'] ?? '' ) );
    $pk = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['pk_licence'] ?? 'id' ) );
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
    if ( '' === $table || ! in_array( 'numero_licence_ffst', $columns, true ) ) { wp_send_json_success( array() ); }

    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT `{$pk}` AS id, numero_licence_ffst FROM `{$table}` WHERE `{$pk}` IN ({$placeholders})", $ids ) );
    $map = array();
    foreach ( (array) $rows as $row ) {
        $map[ (string) absint( $row->id ?? 0 ) ] = trim( (string) ( $row->numero_licence_ffst ?? '' ) );
    }
    wp_send_json_success( $map );
}
add_action( 'wp_ajax_ufsc_ffst_reporting_numbers', 'ufsc_ffst_reporting_ajax_numbers' );

/**
 * Admin UI bridge:
 * - add "Actif" to the Clubs status selector and keep it selected;
 * - replace the obsolete N° ASPTT presentation with the canonical FFST number.
 */
function ufsc_ffst_reporting_admin_footer() {
    $page = ufsc_ffst_reporting_admin_page();
    $is_clubs = in_array( $page, ufsc_ffst_reporting_club_admin_pages(), true );
    $is_licences = in_array( $page, ufsc_ffst_reporting_licence_admin_pages(), true );
    if ( ! $is_clubs && ! $is_licences ) { return; }
    $active_filter = ! empty( $GLOBALS['ufsc_ffst_reporting_active_filter'] );
    $ajax_url = admin_url( 'admin-ajax.php' );
    $nonce = wp_create_nonce( 'ufsc_ffst_reporting_numbers' );
    ?>
    <script>
    (function(){
        'use strict';
        <?php if ( $is_clubs ) : ?>
        var statusSelect = document.querySelector('select[name="statut"]');
        if (statusSelect) {
            var activeOption = statusSelect.querySelector('option[value="active"]');
            if (!activeOption) {
                activeOption = document.createElement('option');
                activeOption.value = 'active';
                activeOption.textContent = 'Actif (affiliation de la saison)';
                statusSelect.insertBefore(activeOption, statusSelect.options.length > 1 ? statusSelect.options[1] : null);
            }
            if (<?php echo $active_filter ? 'true' : 'false'; ?>) { statusSelect.value = 'active'; }
        }
        <?php endif; ?>

        <?php if ( $is_licences ) : ?>
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));
        tables.forEach(function(table){
            var headers = Array.prototype.slice.call(table.querySelectorAll('thead th'));
            var ffstIndex = headers.findIndex(function(th){ return /N°\s*ASPTT/i.test((th.textContent || '').trim()); });
            var idIndex = headers.findIndex(function(th){ return /^N°\s*licence$/i.test((th.textContent || '').trim()); });
            if (ffstIndex < 0 || idIndex < 0) { return; }
            headers[ffstIndex].textContent = 'N° licence FFST';
            var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
            var ids = [];
            var rowById = {};
            rows.forEach(function(row){
                var cells = row.querySelectorAll('td,th');
                if (!cells[idIndex]) { return; }
                var id = (cells[idIndex].textContent || '').trim().match(/\d+/);
                if (!id) { return; }
                ids.push(id[0]); rowById[id[0]] = row;
                if (cells[ffstIndex]) { cells[ffstIndex].textContent = '—'; }
            });
            if (!ids.length) { return; }
            var body = new URLSearchParams();
            body.append('action','ufsc_ffst_reporting_numbers');
            body.append('nonce',<?php echo wp_json_encode( $nonce ); ?>);
            ids.forEach(function(id){ body.append('ids[]', id); });
            fetch(<?php echo wp_json_encode( $ajax_url ); ?>,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
                .then(function(r){ return r.json(); })
                .then(function(payload){
                    if (!payload || !payload.success || !payload.data) { return; }
                    Object.keys(rowById).forEach(function(id){
                        var row = rowById[id]; var cells = row.querySelectorAll('td,th');
                        if (cells[ffstIndex]) { cells[ffstIndex].textContent = payload.data[id] || '—'; }
                    });
                }).catch(function(){ /* presentation-only fallback keeps — */ });
        });
        <?php endif; ?>
    })();
    </script>
    <?php
}
add_action( 'admin_footer', 'ufsc_ffst_reporting_admin_footer', 99 );

/** Render the pack rule and current office coverage inside the club dashboard. */
function ufsc_ffst_reporting_front_pack_summary() {
    if ( ! is_user_logged_in() || ! function_exists( 'ufsc_get_user_club_id' ) ) { return; }
    if ( function_exists( 'ufsc_is_club_portal_request' ) && ! ufsc_is_club_portal_request() ) { return; }
    $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
    if ( ! $club_id ) { return; }
    $season = ufsc_ffst_reporting_season();
    $limit = function_exists( 'ufsc_get_pack_included_limit' ) ? absint( ufsc_get_pack_included_limit() ) : 10;
    $usage = function_exists( 'ufsc_get_pack_usage' ) ? (array) ufsc_get_pack_usage( $club_id, $season ) : array();
    $office = (array) ( $usage['roles'] ?? array() );
    $office_count = absint( $usage['bureau'] ?? count( array_filter( $office ) ) );
    $used = min( $limit, absint( $usage['total'] ?? 0 ) );
    $other_limit = max( 0, $limit - 3 );
    $other_used = max( 0, $used - $office_count );
    $role_text = sprintf(
        'Président %s · Secrétaire %s · Trésorier %s',
        ! empty( $office['president'] ) ? '✓' : '✕',
        ! empty( $office['secretaire'] ) ? '✓' : '✕',
        ! empty( $office['tresorier'] ) ? '✓' : '✕'
    );
    ?>
    <div id="ufsc-pack-office-summary" style="display:none;margin:12px 0;padding:12px 14px;border-radius:10px;background:#fff;border:1px solid rgba(15,76,129,.16);color:#123;">
        <strong><?php echo esc_html( sprintf( __( 'Quota affiliation : %1$d/%2$d utilisées', 'ufsc-clubs' ), $used, $limit ) ); ?></strong>
        <div style="margin-top:5px;"><?php echo esc_html( sprintf( __( 'Bureau : %1$d/3 · %2$s', 'ufsc-clubs' ), $office_count, $role_text ) ); ?></div>
        <div style="margin-top:3px;"><?php echo esc_html( sprintf( __( 'Répartition du pack : 3 places identifiées pour le bureau + %1$d autres licences incluses (%2$d/%1$d utilisées hors bureau).', 'ufsc-clubs' ), $other_limit, min( $other_limit, $other_used ) ) ); ?></div>
    </div>
    <script>
    (function(){
        var box=document.getElementById('ufsc-pack-office-summary');
        if(!box){return;}
        var target=document.getElementById('ufsc-pack-title') || document.querySelector('.ufsc-pack-summary__heading');
        if(target){ target.insertAdjacentElement('afterend',box); box.style.display='block'; }
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'ufsc_ffst_reporting_front_pack_summary', 90 );
