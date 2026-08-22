<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Final hardening for the read-only multi-region federation access layer.
 *
 * This module is deliberately additive. It never calls licence/affiliation
 * mutation handlers and never writes business data. It only:
 * - strengthens regional read scope on detail/dashboard queries;
 * - provides an administrator overview of configured read-only accounts;
 * - removes accounting/payment surfaces from the read-only interface;
 * - replaces the first dashboard implementation with a schema-safe version.
 */

/** Replace the assignment page callback with the same form plus an overview. */
function ufsc_readonly_access_hardening_register_admin_page() {
    if ( ! function_exists( 'ufsc_readonly_access_render_admin_page' ) ) {
        return;
    }

    remove_submenu_page( 'ufsc-dashboard', 'ufsc-readonly-access' );
    add_submenu_page(
        'ufsc-dashboard',
        __( 'Accès responsables', 'ufsc-clubs' ),
        __( 'Accès responsables', 'ufsc-clubs' ),
        'manage_options',
        'ufsc-readonly-access',
        'ufsc_readonly_access_render_admin_page_hardened'
    );
}
add_action( 'admin_menu', 'ufsc_readonly_access_hardening_register_admin_page', 32 );

/** Render the existing assignment form then the currently configured accounts. */
function ufsc_readonly_access_render_admin_page_hardened() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    ufsc_readonly_access_render_admin_page();
    ufsc_readonly_access_render_managed_accounts_table();
}

/** Administrator overview: who has access, which profile and which regions. */
function ufsc_readonly_access_render_managed_accounts_table() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $users = get_users(
        array(
            'meta_key' => UFSC_READONLY_ACCESS_PROFILE_META,
            'orderby'  => 'display_name',
            'order'    => 'ASC',
            'fields'   => 'all_with_meta',
        )
    );

    echo '<div class="wrap ufsc-readonly-access-overview" style="margin-top:26px;">';
    echo '<h2>' . esc_html__( 'Responsables actuellement configurés', 'ufsc-clubs' ) . '</h2>';
    echo '<p class="description">' . esc_html__( 'Cette liste est uniquement un résumé des accès de consultation gérés par ce module.', 'ufsc-clubs' ) . '</p>';
    echo '<table class="widefat striped" style="max-width:1100px;margin-top:12px;">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Utilisateur', 'ufsc-clubs' ) . '</th>';
    echo '<th>' . esc_html__( 'Profil UFSC', 'ufsc-clubs' ) . '</th>';
    echo '<th>' . esc_html__( 'Périmètre', 'ufsc-clubs' ) . '</th>';
    echo '<th>' . esc_html__( 'Rôle WordPress / UFSC', 'ufsc-clubs' ) . '</th>';
    echo '<th>' . esc_html__( 'Action', 'ufsc-clubs' ) . '</th>';
    echo '</tr></thead><tbody>';

    $shown = 0;
    foreach ( (array) $users as $user ) {
        if ( ! $user instanceof WP_User ) {
            continue;
        }
        $profile = sanitize_key( (string) get_user_meta( $user->ID, UFSC_READONLY_ACCESS_PROFILE_META, true ) );
        if ( ! in_array( $profile, array( UFSC_READONLY_ACCESS_REGIONAL, UFSC_READONLY_ACCESS_NATIONAL ), true ) ) {
            continue;
        }

        $shown++;
        $profile_label = UFSC_READONLY_ACCESS_NATIONAL === $profile
            ? __( 'Responsable national – Consultation', 'ufsc-clubs' )
            : __( 'Responsable de ligue – Consultation', 'ufsc-clubs' );
        $regions = UFSC_READONLY_ACCESS_NATIONAL === $profile
            ? array( __( 'Toutes les régions', 'ufsc-clubs' ) )
            : ( function_exists( 'ufsc_get_user_regions' ) ? (array) ufsc_get_user_regions( $user->ID ) : array() );
        if ( empty( $regions ) ) {
            $regions = array( __( 'Aucune région configurée', 'ufsc-clubs' ) );
        }

        $configure_url = add_query_arg(
            array(
                'page'    => 'ufsc-readonly-access',
                'user_id' => (int) $user->ID,
            ),
            admin_url( 'admin.php' )
        );

        echo '<tr>';
        echo '<td><strong>' . esc_html( $user->display_name ) . '</strong><br><span class="description">' . esc_html( $user->user_email ) . '</span></td>';
        echo '<td>' . esc_html( $profile_label ) . '</td>';
        echo '<td>' . esc_html( implode( ' · ', $regions ) ) . '</td>';
        echo '<td>' . esc_html( implode( ', ', (array) $user->roles ) ) . '</td>';
        echo '<td><a class="button" href="' . esc_url( $configure_url ) . '">' . esc_html__( 'Configurer', 'ufsc-clubs' ) . '</a></td>';
        echo '</tr>';
    }

    if ( 0 === $shown ) {
        echo '<tr><td colspan="5">' . esc_html__( 'Aucun responsable en consultation n’est encore configuré.', 'ufsc-clubs' ) . '</td></tr>';
    }

    echo '</tbody></table></div>';
}

/** Return a safe current-season SQL condition for the licences table. */
function ufsc_readonly_access_hardening_licence_season_condition( $licences_table, $season, $alias = 'l' ) {
    global $wpdb;

    $season = sanitize_text_field( (string) $season );
    if ( ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
        return '0=1';
    }

    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $licences_table ) : array();
    foreach ( array( 'season', 'saison', 'ufsc_season' ) as $column ) {
        if ( in_array( $column, $columns, true ) ) {
            return $wpdb->prepare( "REPLACE(`{$alias}`.`{$column}`, '/', '-') = %s", $season );
        }
    }

    if ( in_array( 'season_end_year', $columns, true ) && preg_match( '/^\d{4}-(\d{4})$/', $season, $match ) ) {
        return $wpdb->prepare( "`{$alias}`.`season_end_year` = %d", (int) $match[1] );
    }

    return '0=1';
}

/** Build the canonical regional condition against the clubs alias. */
function ufsc_readonly_access_hardening_club_scope_condition( $clubs_table, $alias = 'c' ) {
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $clubs_table ) : array();
    if ( ! in_array( 'region', $columns, true ) ) {
        return function_exists( 'ufsc_user_has_all_regions_access' ) && ufsc_user_has_all_regions_access() ? '1=1' : '0=1';
    }

    if ( class_exists( 'UFSC_Scope' ) ) {
        $condition = UFSC_Scope::build_scope_condition( 'region', $alias );
        if ( $condition ) {
            return $condition;
        }
    }

    return function_exists( 'ufsc_user_has_all_regions_access' ) && ufsc_user_has_all_regions_access() ? '1=1' : '0=1';
}

/** Replace the initial dashboard callback with a schema-safe joined version. */
function ufsc_readonly_access_hardening_replace_dashboard() {
    if ( ! function_exists( 'ufsc_readonly_access_is_user' ) || ! ufsc_readonly_access_is_user() ) {
        return;
    }

    remove_action( 'toplevel_page_ufsc-dashboard', 'ufsc_readonly_access_render_dashboard', 1 );
    remove_action( 'toplevel_page_ufsc-dashboard', array( 'UFSC_CL_Admin_Menu', 'render_dashboard' ) );
    add_action( 'toplevel_page_ufsc-dashboard', 'ufsc_readonly_access_render_dashboard_hardened', 1 );
}
add_action( 'admin_menu', 'ufsc_readonly_access_hardening_replace_dashboard', 10001 );

/** Dedicated dashboard with regional joins and no accounting/payment values. */
function ufsc_readonly_access_render_dashboard_hardened() {
    if ( ! ufsc_readonly_access_is_user() ) {
        return;
    }

    global $wpdb;

    $settings = class_exists( 'UFSC_SQL' ) ? (array) UFSC_SQL::get_settings() : array();
    $clubs_table = isset( $settings['table_clubs'] ) ? (string) $settings['table_clubs'] : $wpdb->prefix . 'ufsc_clubs';
    $licences_table = isset( $settings['table_licences'] ) ? (string) $settings['table_licences'] : $wpdb->prefix . 'ufsc_licences';
    if ( function_exists( 'ufsc_sanitize_table_name' ) ) {
        $clubs_table    = ufsc_sanitize_table_name( $clubs_table );
        $licences_table = ufsc_sanitize_table_name( $licences_table );
    }

    $season = class_exists( 'UFSC_Season_Service' )
        ? (string) UFSC_Season_Service::get_current_season()
        : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );

    $club_columns    = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $clubs_table ) : array();
    $licence_columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $licences_table ) : array();
    $scope_condition = ufsc_readonly_access_hardening_club_scope_condition( $clubs_table, 'c' );

    $club_conditions = array( $scope_condition );
    if ( in_array( 'deleted_at', $club_columns, true ) ) {
        $club_conditions[] = "(c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')";
    }
    $club_where = implode( ' AND ', $club_conditions );
    $clubs_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` c WHERE {$club_where}" );

    $licences_total = 0;
    $licences_valid = 0;
    $licences_wait  = 0;
    if ( in_array( 'club_id', $licence_columns, true ) && in_array( 'id', $club_columns, true ) ) {
        $licence_conditions = array(
            $scope_condition,
            ufsc_readonly_access_hardening_licence_season_condition( $licences_table, $season, 'l' ),
        );
        if ( in_array( 'deleted_at', $licence_columns, true ) ) {
            $licence_conditions[] = "(l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";
        }
        $licence_where = implode( ' AND ', $licence_conditions );
        $from = "FROM `{$licences_table}` l INNER JOIN `{$clubs_table}` c ON c.id = l.club_id WHERE {$licence_where}";
        $licences_total = (int) $wpdb->get_var( "SELECT COUNT(*) {$from}" );

        if ( in_array( 'statut', $licence_columns, true ) ) {
            $licences_valid = (int) $wpdb->get_var( "SELECT COUNT(*) {$from} AND LOWER(l.statut) IN ('valide','validee','validated','active','actif')" );
            $licences_wait  = (int) $wpdb->get_var( "SELECT COUNT(*) {$from} AND LOWER(l.statut) IN ('en_attente','attente','pending','a_regler','brouillon','draft')" );
        }
    }

    $affiliations_active  = 0;
    $affiliations_pending = 0;
    if ( class_exists( 'UFSC_Season_Archive_Manager' ) && $season ) {
        $aff_table   = UFSC_Season_Archive_Manager::get_affiliations_table();
        $aff_columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $aff_table ) : array();
        $aff_club_col = in_array( 'club_id', $aff_columns, true ) ? 'club_id' : ( in_array( 'id_club', $aff_columns, true ) ? 'id_club' : '' );
        $aff_season_col = in_array( 'season', $aff_columns, true ) ? 'season' : ( in_array( 'saison', $aff_columns, true ) ? 'saison' : '' );
        $aff_status_col = in_array( 'status', $aff_columns, true ) ? 'status' : ( in_array( 'statut', $aff_columns, true ) ? 'statut' : '' );

        if ( $aff_club_col && $aff_season_col && $aff_status_col && in_array( 'id', $club_columns, true ) ) {
            $aff_base = $wpdb->prepare(
                "FROM `{$aff_table}` a INNER JOIN `{$clubs_table}` c ON c.id = a.`{$aff_club_col}` WHERE REPLACE(a.`{$aff_season_col}`, '/', '-') = %s AND {$scope_condition}",
                $season
            );
            $affiliations_active = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT a.`{$aff_club_col}`) {$aff_base} AND LOWER(a.`{$aff_status_col}`) IN ('validated','valide','active','actif')"
            );
            $affiliations_pending = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT a.`{$aff_club_col}`) {$aff_base} AND LOWER(a.`{$aff_status_col}`) IN ('pending_payment','pending_validation','pending','en_attente','correction_required')"
            );
        }
    }

    $is_national = function_exists( 'ufsc_user_has_all_regions_access' ) && ufsc_user_has_all_regions_access();
    $regions = $is_national
        ? array( __( 'Toutes les régions', 'ufsc-clubs' ) )
        : ( function_exists( 'ufsc_get_user_regions' ) ? (array) ufsc_get_user_regions() : array() );

    echo '<div class="wrap ufsc-readonly-dashboard">';
    echo '<h1>' . esc_html__( 'UFSC – Tableau de bord consultation', 'ufsc-clubs' ) . '</h1>';
    echo '<p class="description">' . esc_html__( 'Vue strictement en lecture seule. Les commandes, règlements, montants et données comptables ne sont jamais affichés.', 'ufsc-clubs' ) . '</p>';
    echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Périmètre autorisé : ', 'ufsc-clubs' ) . '</strong>' . esc_html( implode( ' · ', $regions ) ) . '</p></div>';

    $cards = array(
        __( 'Saison', 'ufsc-clubs' )                  => $season,
        __( 'Clubs', 'ufsc-clubs' )                   => $clubs_total,
        __( 'Affiliations actives', 'ufsc-clubs' )    => $affiliations_active,
        __( 'Affiliations en attente', 'ufsc-clubs' ) => $affiliations_pending,
        __( 'Licences', 'ufsc-clubs' )                => $licences_total,
        __( 'Licences validées', 'ufsc-clubs' )       => $licences_valid,
        __( 'Licences en attente', 'ufsc-clubs' )     => $licences_wait,
    );

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin:22px 0;">';
    foreach ( $cards as $label => $value ) {
        echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;box-shadow:0 1px 2px rgba(0,0,0,.03);">';
        echo '<div style="color:#646970;font-weight:600;margin-bottom:7px;">' . esc_html( $label ) . '</div>';
        echo '<div style="font-size:28px;font-weight:700;line-height:1.1;">' . esc_html( (string) $value ) . '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=ufsc-clubs' ) ) . '">' . esc_html__( 'Consulter les clubs', 'ufsc-clubs' ) . '</a> ';
    echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=ufsc_lc_licences' ) ) . '">' . esc_html__( 'Consulter les licences', 'ufsc-clubs' ) . '</a></p>';
    echo '</div>';
}

/**
 * Enforce direct detail scope before any read-only detail page is rendered.
 * Existing list queries already include UFSC_Scope; this closes direct URL gaps.
 */
function ufsc_readonly_access_hardening_scope_guard() {
    if ( ! is_admin() || ! function_exists( 'ufsc_readonly_access_is_user' ) || ! ufsc_readonly_access_is_user() || ! class_exists( 'UFSC_Scope' ) ) {
        return;
    }

    $method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
    if ( 'GET' !== $method ) {
        return;
    }

    $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $action = isset( $_GET['action'] ) && ! is_array( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    if ( ! in_array( $action, array( 'edit', 'view' ), true ) ) {
        return;
    }

    if ( false !== strpos( $page, 'club' ) ) {
        $club_id = 0;
        foreach ( array( 'club_id', 'id' ) as $key ) {
            if ( isset( $_GET[ $key ] ) && ! is_array( $_GET[ $key ] ) ) {
                $club_id = absint( wp_unslash( $_GET[ $key ] ) );
                if ( $club_id ) { break; }
            }
        }
        if ( $club_id ) {
            UFSC_Scope::assert_club_in_scope( $club_id );
        }
        return;
    }

    if ( false !== strpos( $page, 'licence' ) || false !== strpos( $page, 'license' ) ) {
        $licence_id = 0;
        foreach ( array( 'licence_id', 'license_id', 'id' ) as $key ) {
            if ( isset( $_GET[ $key ] ) && ! is_array( $_GET[ $key ] ) ) {
                $licence_id = absint( wp_unslash( $_GET[ $key ] ) );
                if ( $licence_id ) { break; }
            }
        }
        if ( ! $licence_id ) {
            return;
        }

        global $wpdb;
        $settings = class_exists( 'UFSC_SQL' ) ? (array) UFSC_SQL::get_settings() : array();
        $licences_table = isset( $settings['table_licences'] ) ? (string) $settings['table_licences'] : $wpdb->prefix . 'ufsc_licences';
        if ( function_exists( 'ufsc_sanitize_table_name' ) ) {
            $licences_table = ufsc_sanitize_table_name( $licences_table );
        }
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $licences_table ) : array();
        if ( ! in_array( 'id', $columns, true ) || ! in_array( 'club_id', $columns, true ) ) {
            return;
        }
        $club_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT club_id FROM `{$licences_table}` WHERE id = %d LIMIT 1", $licence_id ) );
        if ( $club_id ) {
            UFSC_Scope::assert_club_in_scope( $club_id );
        }
    }
}
add_action( 'admin_init', 'ufsc_readonly_access_hardening_scope_guard', 6 );

/** Remove accounting/payment vocabulary and mutation actions from read-only tables. */
function ufsc_readonly_access_hardening_admin_footer() {
    if ( ! function_exists( 'ufsc_readonly_access_is_user' ) || ! ufsc_readonly_access_is_user() ) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const accountingWords = ['paiement', 'règlement', 'reglement', 'montant', 'prix', 'commande', 'chiffre d’affaires', 'chiffre d\'affaires', 'revenu'];
        document.querySelectorAll('table').forEach(function (table) {
            const headers = Array.from(table.querySelectorAll('thead th'));
            headers.forEach(function (header, index) {
                const text = (header.textContent || '').trim().toLowerCase();
                if (!accountingWords.some(function (word) { return text.includes(word); })) return;
                Array.from(table.rows).forEach(function (row) {
                    if (row.cells[index]) row.cells[index].style.display = 'none';
                });
            });
        });

        document.querySelectorAll('[name="payment_status"], [name="order_status"], [name="amount"], [name="price"]').forEach(function (field) {
            const box = field.closest('.ufsc-filter-group, .ufsc-form-field, .form-field, label, td') || field;
            box.style.display = 'none';
        });

        const mutationWords = ['modifier', 'supprimer', 'relancer', 'renouveler', 'gérer l’affiliation', "gérer l'affiliation", 'valider', 'refuser', 'ajouter', 'créer'];
        document.querySelectorAll('.row-actions a, td a.button, td button, .ufsc-actions a, .ufsc-actions button').forEach(function (control) {
            const text = (control.textContent || '').trim().toLowerCase();
            if (mutationWords.some(function (word) { return text.includes(word); })) {
                control.style.display = 'none';
            }
        });
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'ufsc_readonly_access_hardening_admin_footer', 10020 );
