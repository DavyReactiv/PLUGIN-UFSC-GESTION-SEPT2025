<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read-only multi-region UFSC administration.
 *
 * This module is intentionally additive. It reuses the existing
 * `ufsc_region_viewer` role and regional scope helpers without changing any
 * licence, affiliation, quota, WooCommerce or season business workflow.
 */

const UFSC_READONLY_ACCESS_PROFILE_META = '_ufsc_readonly_access_profile';
const UFSC_READONLY_ACCESS_REGIONAL     = 'regional_readonly';
const UFSC_READONLY_ACCESS_NATIONAL     = 'national_readonly';

/**
 * Return the profiles managed by the simplified access screen.
 *
 * @return array<string,string>
 */
function ufsc_readonly_access_profiles() {
    return array(
        ''                            => __( 'Aucun accès consultation géré ici', 'ufsc-clubs' ),
        UFSC_READONLY_ACCESS_REGIONAL => __( 'Responsable de ligue – Consultation', 'ufsc-clubs' ),
        UFSC_READONLY_ACCESS_NATIONAL => __( 'Responsable national – Consultation', 'ufsc-clubs' ),
    );
}

/**
 * Determine whether a user is managed by the read-only access layer.
 *
 * WordPress administrators are always excluded, even if stale metadata exists.
 *
 * @param int|null $user_id User ID, defaults to current user.
 * @return bool
 */
function ufsc_readonly_access_is_user( $user_id = null ) {
    $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
    if ( ! $user_id ) {
        return false;
    }

    if ( class_exists( 'UFSC_Permissions' ) && UFSC_Permissions::is_wordpress_administrator( $user_id ) ) {
        return false;
    }

    $profile = sanitize_key( (string) get_user_meta( $user_id, UFSC_READONLY_ACCESS_PROFILE_META, true ) );
    if ( ! in_array( $profile, array( UFSC_READONLY_ACCESS_REGIONAL, UFSC_READONLY_ACCESS_NATIONAL ), true ) ) {
        return false;
    }

    $user = get_userdata( $user_id );
    return $user && in_array( 'ufsc_region_viewer', (array) $user->roles, true );
}

/**
 * Check for legacy/elevated UFSC rights that must not be silently downgraded by
 * the simplified read-only screen.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function ufsc_readonly_access_has_conflicting_rights( $user_id ) {
    $user = get_userdata( absint( $user_id ) );
    if ( ! $user ) {
        return true;
    }

    $conflicting_roles = array( 'ufsc_region_manager', 'ufsc_competition_manager', 'ufsc_admin_limited' );
    if ( array_intersect( $conflicting_roles, (array) $user->roles ) ) {
        return true;
    }

    if ( ! class_exists( 'UFSC_Permissions' ) ) {
        return false;
    }

    foreach ( array(
        UFSC_Permissions::CAP_GESTION_MANAGE,
        UFSC_Permissions::CAP_LICENCES_MANAGE,
        UFSC_Permissions::CAP_COMPETITIONS_MANAGE,
        UFSC_Permissions::CAP_SETTINGS_MANAGE,
        UFSC_Permissions::CAP_REGIONS_MANAGE,
    ) as $capability ) {
        if ( user_can( $user_id, $capability ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Register the administrator-only assignment screen.
 */
function ufsc_readonly_access_register_admin_page() {
    add_submenu_page(
        'ufsc-dashboard',
        __( 'Accès responsables', 'ufsc-clubs' ),
        __( 'Accès responsables', 'ufsc-clubs' ),
        'manage_options',
        'ufsc-readonly-access',
        'ufsc_readonly_access_render_admin_page'
    );
}
add_action( 'admin_menu', 'ufsc_readonly_access_register_admin_page', 31 );

/**
 * Apply one read-only access profile to an existing WordPress user.
 *
 * The function never creates users and never touches business data.
 *
 * @param int      $user_id User ID.
 * @param string   $profile Profile key.
 * @param string[] $regions Allowed regions for regional profile.
 * @return true|WP_Error
 */
function ufsc_readonly_access_apply_profile( $user_id, $profile, array $regions = array() ) {
    $user_id = absint( $user_id );
    $profile = sanitize_key( (string) $profile );
    $user    = $user_id ? get_userdata( $user_id ) : false;

    if ( ! $user ) {
        return new WP_Error( 'ufsc_readonly_user_missing', __( 'Utilisateur WordPress introuvable.', 'ufsc-clubs' ) );
    }

    if ( class_exists( 'UFSC_Permissions' ) && UFSC_Permissions::is_wordpress_administrator( $user_id ) ) {
        return new WP_Error( 'ufsc_readonly_admin_forbidden', __( 'Un administrateur WordPress ne peut pas être converti en compte UFSC lecture seule.', 'ufsc-clubs' ) );
    }

    $managed_profile = sanitize_key( (string) get_user_meta( $user_id, UFSC_READONLY_ACCESS_PROFILE_META, true ) );
    if ( '' !== $profile && ! in_array( $profile, array( UFSC_READONLY_ACCESS_REGIONAL, UFSC_READONLY_ACCESS_NATIONAL ), true ) ) {
        return new WP_Error( 'ufsc_readonly_profile_invalid', __( 'Profil de consultation invalide.', 'ufsc-clubs' ) );
    }

    if ( '' !== $profile && ufsc_readonly_access_has_conflicting_rights( $user_id ) && ! in_array( $managed_profile, array( UFSC_READONLY_ACCESS_REGIONAL, UFSC_READONLY_ACCESS_NATIONAL ), true ) ) {
        return new WP_Error(
            'ufsc_readonly_conflicting_rights',
            __( 'Ce compte possède déjà des droits UFSC de gestion. Utilisez la page Droits & accès avancée pour éviter une régression de permissions.', 'ufsc-clubs' )
        );
    }

    if ( '' === $profile ) {
        $user->remove_role( 'ufsc_region_viewer' );
        delete_user_meta( $user_id, UFSC_READONLY_ACCESS_PROFILE_META );
        delete_user_meta( $user_id, UFSC_Permissions::META_ALLOWED_REGIONS );
        delete_user_meta( $user_id, UFSC_Permissions::META_ALL_REGIONS );
        delete_user_meta( $user_id, UFSC_Scope::USER_META_KEY );
        return true;
    }

    if ( UFSC_READONLY_ACCESS_REGIONAL === $profile ) {
        $regions = array_values( array_unique( array_intersect( array_map( 'sanitize_text_field', $regions ), ufsc_get_regions() ) ) );
        if ( empty( $regions ) ) {
            return new WP_Error( 'ufsc_readonly_regions_required', __( 'Sélectionnez au moins une région pour un responsable de ligue.', 'ufsc-clubs' ) );
        }
    } else {
        $regions = array();
    }

    // Reuse the existing role. add_role preserves the user's unrelated WP role.
    $user->add_role( 'ufsc_region_viewer' );

    // Defensive removal of direct UFSC elevated grants. The normal viewer role
    // only contains read capabilities, but direct grants may exist on old users.
    foreach ( array(
        UFSC_Permissions::CAP_GESTION_MANAGE,
        UFSC_Permissions::CAP_LICENCES_MANAGE,
        UFSC_Permissions::CAP_COMPETITIONS_MANAGE,
        UFSC_Permissions::CAP_SETTINGS_MANAGE,
        UFSC_Permissions::CAP_REGIONS_MANAGE,
        UFSC_Permissions::CAP_ALL_REGIONS_ACCESS,
    ) as $capability ) {
        $user->remove_cap( $capability );
    }

    // Explicit read grants are additive and match the existing viewer role.
    $user->add_cap( UFSC_Permissions::CAP_GESTION_READ );
    $user->add_cap( UFSC_Permissions::CAP_LICENCES_READ );

    update_user_meta( $user_id, UFSC_READONLY_ACCESS_PROFILE_META, $profile );

    if ( UFSC_READONLY_ACCESS_NATIONAL === $profile ) {
        ufsc_set_user_regions( $user_id, array() );
        update_user_meta( $user_id, UFSC_Permissions::META_ALL_REGIONS, '1' );
    } else {
        ufsc_set_user_regions( $user_id, $regions );
        update_user_meta( $user_id, UFSC_Permissions::META_ALL_REGIONS, '0' );
    }

    // The legacy single-region scope is intentionally cleared. UFSC_Scope then
    // consumes the canonical multi-region list through ufsc_get_user_regions().
    delete_user_meta( $user_id, UFSC_Scope::USER_META_KEY );

    return true;
}

/**
 * Handle administrator assignment submissions.
 */
function ufsc_readonly_access_handle_save() {
    if ( ! is_admin() || 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return;
    }

    $page = isset( $_POST['ufsc_readonly_access_page'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_readonly_access_page'] ) ) : '';
    if ( 'save' !== $page ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    check_admin_referer( 'ufsc_readonly_access_save', 'ufsc_readonly_access_nonce' );

    $user_id = isset( $_POST['ufsc_readonly_user_id'] ) ? absint( wp_unslash( $_POST['ufsc_readonly_user_id'] ) ) : 0;
    $profile = isset( $_POST['ufsc_readonly_profile'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_readonly_profile'] ) ) : '';
    $regions = isset( $_POST['ufsc_readonly_regions'] ) && is_array( $_POST['ufsc_readonly_regions'] )
        ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ufsc_readonly_regions'] ) )
        : array();

    $result = ufsc_readonly_access_apply_profile( $user_id, $profile, $regions );
    $args   = array( 'page' => 'ufsc-readonly-access', 'user_id' => $user_id );

    if ( is_wp_error( $result ) ) {
        $args['ufsc_access_error'] = rawurlencode( $result->get_error_message() );
    } else {
        $args['ufsc_access_saved'] = '1';
    }

    wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_init', 'ufsc_readonly_access_handle_save', -50 );

/**
 * Render the administrator assignment page.
 */
function ufsc_readonly_access_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    $selected_user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
    $selected_user    = $selected_user_id ? get_userdata( $selected_user_id ) : false;
    $profile          = $selected_user ? sanitize_key( (string) get_user_meta( $selected_user_id, UFSC_READONLY_ACCESS_PROFILE_META, true ) ) : '';
    $allowed_regions  = $selected_user ? ufsc_get_user_regions( $selected_user_id ) : array();
    $users            = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => 'all_with_meta' ) );

    echo '<div class="wrap ufsc-readonly-access-admin">';
    echo '<h1>' . esc_html__( 'Accès responsables – Consultation', 'ufsc-clubs' ) . '</h1>';
    echo '<p>' . esc_html__( 'Créez d’abord le compte dans Utilisateurs WordPress, puis attribuez ici un accès UFSC strictement en lecture seule, limité à une ou plusieurs régions ou à l’ensemble du territoire.', 'ufsc-clubs' ) . '</p>';
    echo '<p><a class="button" href="' . esc_url( admin_url( 'user-new.php' ) ) . '">' . esc_html__( 'Créer un utilisateur WordPress', 'ufsc-clubs' ) . '</a> ';
    echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=ufsc-permissions' ) ) . '">' . esc_html__( 'Droits & accès avancés', 'ufsc-clubs' ) . '</a></p>';

    if ( isset( $_GET['ufsc_access_saved'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Accès de consultation enregistré.', 'ufsc-clubs' ) . '</p></div>';
    }
    if ( isset( $_GET['ufsc_access_error'] ) ) {
        $error_message = sanitize_text_field( rawurldecode( wp_unslash( $_GET['ufsc_access_error'] ) ) );
        echo '<div class="notice notice-error"><p>' . esc_html( $error_message ) . '</p></div>';
    }

    echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="max-width:900px;background:#fff;border:1px solid #dcdcde;padding:18px;margin:20px 0;">';
    echo '<input type="hidden" name="page" value="ufsc-readonly-access">';
    echo '<label for="ufsc-readonly-user"><strong>' . esc_html__( 'Utilisateur WordPress', 'ufsc-clubs' ) . '</strong></label><br>';
    echo '<select id="ufsc-readonly-user" name="user_id" style="min-width:420px;max-width:100%;margin-top:8px;">';
    echo '<option value="">' . esc_html__( '— Choisir un utilisateur —', 'ufsc-clubs' ) . '</option>';
    foreach ( $users as $user ) {
        if ( class_exists( 'UFSC_Permissions' ) && UFSC_Permissions::is_wordpress_administrator( $user->ID ) ) {
            continue;
        }
        $roles = implode( ', ', array_map( 'sanitize_text_field', (array) $user->roles ) );
        printf(
            '<option value="%1$d" %2$s>%3$s — %4$s (%5$s)</option>',
            (int) $user->ID,
            selected( $selected_user_id, $user->ID, false ),
            esc_html( $user->display_name ),
            esc_html( $user->user_email ),
            esc_html( $roles ?: __( 'aucun rôle', 'ufsc-clubs' ) )
        );
    }
    echo '</select> ';
    submit_button( __( 'Configurer', 'ufsc-clubs' ), 'secondary', 'submit', false );
    echo '</form>';

    if ( $selected_user ) {
        $conflict = ufsc_readonly_access_has_conflicting_rights( $selected_user_id ) && ! ufsc_readonly_access_is_user( $selected_user_id );
        echo '<form method="post" style="max-width:900px;background:#fff;border:1px solid #dcdcde;padding:20px;">';
        wp_nonce_field( 'ufsc_readonly_access_save', 'ufsc_readonly_access_nonce' );
        echo '<input type="hidden" name="ufsc_readonly_access_page" value="save">';
        echo '<input type="hidden" name="ufsc_readonly_user_id" value="' . esc_attr( (string) $selected_user_id ) . '">';
        echo '<h2>' . esc_html( $selected_user->display_name ) . '</h2>';

        if ( $conflict ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Ce compte possède déjà des droits UFSC de gestion. Pour sa sécurité, ce formulaire ne les modifiera pas automatiquement : utilisez Droits & accès avancés.', 'ufsc-clubs' ) . '</p></div>';
        }

        echo '<p><label for="ufsc-readonly-profile"><strong>' . esc_html__( 'Type d’accès', 'ufsc-clubs' ) . '</strong></label><br>';
        echo '<select id="ufsc-readonly-profile" name="ufsc_readonly_profile" style="min-width:420px;max-width:100%;margin-top:8px;">';
        foreach ( ufsc_readonly_access_profiles() as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $profile, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></p>';

        echo '<div id="ufsc-readonly-regions"><h3>' . esc_html__( 'Régions autorisées', 'ufsc-clubs' ) . '</h3>';
        echo '<p class="description">' . esc_html__( 'Vous pouvez attribuer plusieurs régions au même responsable. Le profil national ignore cette sélection et donne accès à toutes les régions en lecture seule.', 'ufsc-clubs' ) . '</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px 18px;max-width:820px;">';
        foreach ( ufsc_get_regions() as $region ) {
            echo '<label><input type="checkbox" name="ufsc_readonly_regions[]" value="' . esc_attr( $region ) . '" ' . checked( in_array( $region, $allowed_regions, true ), true, false ) . '> ' . esc_html( $region ) . '</label>';
        }
        echo '</div></div>';

        echo '<hr style="margin:22px 0;">';
        echo '<p><strong>' . esc_html__( 'Ce profil ne donne jamais accès :', 'ufsc-clubs' ) . '</strong> ' . esc_html__( 'aux modifications de clubs/licences, validations, imports, réglages, commandes, paiements, données comptables ou administration WooCommerce.', 'ufsc-clubs' ) . '</p>';
        submit_button( __( 'Enregistrer l’accès de consultation', 'ufsc-clubs' ), 'primary', 'submit', false, $conflict ? array( 'disabled' => 'disabled' ) : array() );
        echo '</form>';
    }

    echo '</div>';
}

/**
 * Force sensitive capabilities off for managed read-only users.
 *
 * This is a server-side safety net in addition to the role configuration.
 *
 * @param array   $allcaps All capabilities.
 * @param string[] $caps   Required capabilities.
 * @param array   $args    Capability arguments.
 * @param WP_User $user    User object.
 * @return array
 */
function ufsc_readonly_access_deny_sensitive_caps( $allcaps, $caps, $args, $user ) {
    unset( $caps, $args );
    if ( ! $user instanceof WP_User || ! ufsc_readonly_access_is_user( $user->ID ) ) {
        return $allcaps;
    }

    $deny = array(
        UFSC_Permissions::CAP_GESTION_MANAGE,
        UFSC_Permissions::CAP_LICENCES_MANAGE,
        UFSC_Permissions::CAP_COMPETITIONS_MANAGE,
        UFSC_Permissions::CAP_SETTINGS_MANAGE,
        UFSC_Permissions::CAP_REGIONS_MANAGE,
        UFSC_Permissions::CAP_ALL_REGIONS_ACCESS,
        'manage_woocommerce',
        'view_woocommerce_reports',
        'edit_shop_orders',
        'edit_others_shop_orders',
        'publish_shop_orders',
        'delete_shop_orders',
        'read_private_shop_orders',
    );

    foreach ( $deny as $capability ) {
        $allcaps[ $capability ] = false;
    }

    return $allcaps;
}
add_filter( 'user_has_cap', 'ufsc_readonly_access_deny_sensitive_caps', 999, 4 );

/**
 * Block every UFSC write request and sensitive direct screen for read-only users.
 */
function ufsc_readonly_access_guard_requests() {
    if ( ! is_admin() || ! ufsc_readonly_access_is_user() ) {
        return;
    }

    $method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
    $page   = isset( $_REQUEST['page'] ) && ! is_array( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
    $action = isset( $_REQUEST['action'] ) && ! is_array( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

    if ( 'POST' === $method && ( 0 === strpos( $page, 'ufsc' ) || 0 === strpos( $action, 'ufsc' ) ) ) {
        wp_die( esc_html__( 'Compte en consultation : aucune modification UFSC n’est autorisée.', 'ufsc-clubs' ), esc_html__( 'Accès lecture seule', 'ufsc-clubs' ), array( 'response' => 403 ) );
    }

    $blocked_pages = array(
        'ufsc-exports',
        'ufsc-import',
        'ufsc-settings',
        'ufsc-woocommerce',
        'ufsc-permissions',
        'ufsc-readonly-access',
        'ufsc-diagnostics',
    );
    if ( in_array( $page, $blocked_pages, true ) || false !== strpos( $page, 'communication' ) || false !== strpos( $page, 'mail' ) ) {
        wp_die( esc_html__( 'Cette rubrique n’est pas disponible avec un compte de consultation.', 'ufsc-clubs' ), esc_html__( 'Accès lecture seule', 'ufsc-clubs' ), array( 'response' => 403 ) );
    }

    $post_type = isset( $_REQUEST['post_type'] ) && ! is_array( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '';
    if ( 'shop_order' === $post_type || 'woocommerce' === $page || 0 === strpos( $page, 'wc-' ) ) {
        wp_die( esc_html__( 'Les commandes et données WooCommerce ne sont pas accessibles aux responsables en consultation.', 'ufsc-clubs' ), esc_html__( 'Accès lecture seule', 'ufsc-clubs' ), array( 'response' => 403 ) );
    }

    $blocked_get_actions = array( 'new', 'delete', 'trash', 'restore', 'force-delete', 'renew', 'validate', 'approve', 'reject', 'import', 'export', 'save' );
    if ( 'GET' === $method && 0 === strpos( $page, 'ufsc' ) && in_array( $action, $blocked_get_actions, true ) ) {
        wp_die( esc_html__( 'Compte en consultation : cette action est désactivée.', 'ufsc-clubs' ), esc_html__( 'Accès lecture seule', 'ufsc-clubs' ), array( 'response' => 403 ) );
    }
}
add_action( 'admin_init', 'ufsc_readonly_access_guard_requests', 5 );

/**
 * Replace only the dashboard callback for a read-only user. Full administrators
 * keep the existing dashboard unchanged.
 */
function ufsc_readonly_access_replace_dashboard_callback() {
    if ( ! ufsc_readonly_access_is_user() ) {
        return;
    }

    remove_action( 'toplevel_page_ufsc-dashboard', array( 'UFSC_CL_Admin_Menu', 'render_dashboard' ) );
    add_action( 'toplevel_page_ufsc-dashboard', 'ufsc_readonly_access_render_dashboard', 1 );
}
add_action( 'admin_menu', 'ufsc_readonly_access_replace_dashboard_callback', 9996 );

/**
 * Hide mutation, settings and accounting menus for read-only users.
 */
function ufsc_readonly_access_cleanup_menus() {
    if ( ! ufsc_readonly_access_is_user() ) {
        return;
    }

    foreach ( array( 'ufsc-exports', 'ufsc-import', 'ufsc-settings', 'ufsc-woocommerce', 'ufsc-permissions', 'ufsc-readonly-access', 'ufsc-diagnostics' ) as $slug ) {
        remove_submenu_page( 'ufsc-dashboard', $slug );
    }

    remove_menu_page( 'woocommerce' );
    remove_menu_page( 'wc-admin' );
    remove_menu_page( 'edit.php?post_type=shop_order' );
}
add_action( 'admin_menu', 'ufsc_readonly_access_cleanup_menus', 10000 );

/**
 * Add a body marker used for presentation-only read-only refinements.
 *
 * @param string $classes Existing body classes.
 * @return string
 */
function ufsc_readonly_access_admin_body_class( $classes ) {
    return ufsc_readonly_access_is_user() ? trim( $classes . ' ufsc-readonly-access' ) : $classes;
}
add_filter( 'admin_body_class', 'ufsc_readonly_access_admin_body_class' );

/**
 * Present existing edit/detail screens as consultation screens without changing
 * their server-side data source. POST is blocked independently by the guard.
 */
function ufsc_readonly_access_admin_footer() {
    if ( ! ufsc_readonly_access_is_user() ) {
        return;
    }
    ?>
    <style>
        body.ufsc-readonly-access .page-title-action,
        body.ufsc-readonly-access .bulkactions,
        body.ufsc-readonly-access .row-actions .delete,
        body.ufsc-readonly-access .row-actions .trash,
        body.ufsc-readonly-access .row-actions .renew,
        body.ufsc-readonly-access a[href*="action=new"],
        body.ufsc-readonly-access a[href*="action=delete"],
        body.ufsc-readonly-access a[href*="action=trash"],
        body.ufsc-readonly-access a[href*="action=renew"] {
            display: none !important;
        }
        body.ufsc-readonly-access .ufsc-readonly-banner {
            margin: 14px 0;
            padding: 12px 16px;
            border-left: 4px solid #2271b1;
            background: #f0f6fc;
            color: #1d2327;
            font-weight: 600;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || '';
        const action = params.get('action') || '';
        if (!page.startsWith('ufsc')) return;

        document.querySelectorAll('a[href*="action=edit"]').forEach(function (link) {
            const text = (link.textContent || '').trim().toLowerCase();
            if (text === 'modifier' || text === 'edit') link.textContent = 'Consulter';
        });

        if (action === 'edit' || action === 'view') {
            const wrap = document.querySelector('.wrap');
            if (wrap && !wrap.querySelector('.ufsc-readonly-banner')) {
                const banner = document.createElement('div');
                banner.className = 'ufsc-readonly-banner';
                banner.textContent = 'Mode consultation : les informations sont visibles mais aucune modification ne peut être enregistrée.';
                wrap.insertBefore(banner, wrap.firstChild);
            }
            document.querySelectorAll('form input:not([type="hidden"]), form select, form textarea, form button[type="submit"], form input[type="submit"]').forEach(function (field) {
                field.disabled = true;
            });
        }
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'ufsc_readonly_access_admin_footer', 9999 );

/**
 * Render a dedicated non-accounting dashboard in place of the normal dashboard
 * for read-only users. Queries reuse the canonical regional SQL scope.
 */
function ufsc_readonly_access_render_dashboard() {
    if ( ! ufsc_readonly_access_is_user() ) {
        return;
    }

    global $wpdb;

    $clubs_table    = function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs';
    $licences_table = function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences';
    $season         = function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '';
    $regions        = ufsc_user_has_all_regions_access() ? ufsc_get_regions() : ufsc_get_user_regions();

    $club_scope = class_exists( 'UFSC_Scope' ) ? UFSC_Scope::build_scope_condition( 'region' ) : '';
    $lic_scope  = class_exists( 'UFSC_Scope' ) ? UFSC_Scope::build_scope_condition( 'region' ) : '';
    $club_where = $club_scope ? 'WHERE ' . $club_scope : '';

    $lic_conditions = array();
    if ( $lic_scope ) {
        $lic_conditions[] = $lic_scope;
    }
    $season_column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $licences_table ) : '';
    if ( $season && $season_column ) {
        if ( 'season_end_year' === $season_column && preg_match( '/^\d{4}-(\d{4})$/', $season, $match ) ) {
            $lic_conditions[] = $wpdb->prepare( '`season_end_year` = %d', (int) $match[1] );
        } else {
            $lic_conditions[] = $wpdb->prepare( "REPLACE(`{$season_column}`, '/', '-') = %s", $season );
        }
    }
    $lic_where = $lic_conditions ? 'WHERE ' . implode( ' AND ', $lic_conditions ) : 'WHERE 0=1';

    $clubs_total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$club_where}" );
    $licences_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$licences_table}` {$lic_where}" );
    $licences_valid = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$licences_table}` {$lic_where} AND statut IN ('valide','validee','active')" );
    $licences_wait  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$licences_table}` {$lic_where} AND statut IN ('en_attente','attente','pending','a_regler')" );

    $affiliations_active  = 0;
    $affiliations_pending = 0;
    if ( class_exists( 'UFSC_Season_Archive_Manager' ) && $season ) {
        $aff_table = UFSC_Season_Archive_Manager::get_affiliations_table();
        if ( ! function_exists( 'ufsc_table_exists' ) || ufsc_table_exists( $aff_table ) ) {
            $join_scope = class_exists( 'UFSC_Scope' ) ? UFSC_Scope::build_scope_condition( 'region', 'c' ) : '';
            $join_scope = $join_scope ? ' AND ' . $join_scope : '';
            $affiliations_active = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT a.club_id) FROM `{$aff_table}` a INNER JOIN `{$clubs_table}` c ON c.id=a.club_id WHERE a.season=%s AND LOWER(a.status) IN ('validated','valide','active','actif'){$join_scope}",
                    $season
                )
            );
            $affiliations_pending = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT a.club_id) FROM `{$aff_table}` a INNER JOIN `{$clubs_table}` c ON c.id=a.club_id WHERE a.season=%s AND a.status IN ('pending_payment','pending_validation','correction_required'){$join_scope}",
                    $season
                )
            );
        }
    }

    echo '<div class="wrap ufsc-readonly-dashboard">';
    echo '<h1>' . esc_html__( 'UFSC – Tableau de bord consultation', 'ufsc-clubs' ) . '</h1>';
    echo '<p class="description">' . esc_html__( 'Vue strictement en lecture seule. Aucune commande, aucun paiement et aucune donnée comptable ne sont affichés.', 'ufsc-clubs' ) . '</p>';
    echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Périmètre autorisé : ', 'ufsc-clubs' ) . '</strong>' . esc_html( implode( ' · ', $regions ) ) . '</p></div>';

    $cards = array(
        __( 'Saison', 'ufsc-clubs' )                 => $season,
        __( 'Clubs', 'ufsc-clubs' )                  => $clubs_total,
        __( 'Affiliations actives', 'ufsc-clubs' )   => $affiliations_active,
        __( 'Affiliations en attente', 'ufsc-clubs' ) => $affiliations_pending,
        __( 'Licences', 'ufsc-clubs' )               => $licences_total,
        __( 'Licences validées', 'ufsc-clubs' )      => $licences_valid,
        __( 'Licences en attente', 'ufsc-clubs' )    => $licences_wait,
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
