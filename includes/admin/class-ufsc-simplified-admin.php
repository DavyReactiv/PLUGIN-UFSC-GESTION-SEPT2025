<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Simplified WordPress admin interface for limited UFSC users.
 *
 * This class only hides/redirects admin UI entry points. It does not grant any
 * WordPress capability and must not replace the existing server-side checks.
 */
class UFSC_Simplified_Admin {
    const OPTION_ENABLED = 'ufsc_enable_simplified_admin';

    /**
     * Capabilities that identify a limited UFSC user for this interface.
     *
     * @return string[]
     */
    private static function limited_user_caps() {
        return array(
            UFSC_Permissions::CAP_GESTION_READ,
            UFSC_Permissions::CAP_GESTION_MANAGE,
            UFSC_Permissions::CAP_LICENCES_READ,
            UFSC_Permissions::CAP_LICENCES_MANAGE,
            UFSC_Permissions::CAP_COMPETITIONS_READ,
            UFSC_Permissions::CAP_COMPETITIONS_MANAGE,
        );
    }

    /**
     * Bootstrap admin hooks.
     */
    public static function init() {
        $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
        if ( ! is_admin() && 'wp-login.php' !== $pagenow ) {
            return;
        }

        add_filter( 'login_redirect', array( __CLASS__, 'filter_login_redirect' ), 20, 3 );
        add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_from_dashboard' ), 1 );
        add_action( 'admin_init', array( __CLASS__, 'maybe_block_direct_admin_access' ), 20 );
        add_action( 'admin_menu', array( __CLASS__, 'register_licences_bridge_menu' ), 19 );
        add_action( 'admin_menu', array( __CLASS__, 'register_welcome_page' ), 20 );
        add_action( 'admin_menu', array( __CLASS__, 'filter_admin_menu' ), 9999 );
        add_action( 'admin_bar_menu', array( __CLASS__, 'simplify_admin_bar' ), 9999 );
    }

    /**
     * Whether the simplified admin mode is enabled globally.
     */
    public static function is_enabled() {
        return '0' !== (string) get_option( self::OPTION_ENABLED, '1' );
    }

    /**
     * Detect non-administrator users with at least one limited UFSC capability.
     */
    public static function is_limited_ufsc_user() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        if ( current_user_can( 'manage_options' ) || UFSC_Permissions::is_wordpress_administrator() ) {
            return false;
        }

        foreach ( self::limited_user_caps() as $cap ) {
            if ( current_user_can( $cap ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether simplified UI rules should currently apply.
     */
    private static function should_apply() {
        return self::is_enabled() && self::is_limited_ufsc_user();
    }

    /**
     * Does current user have a UFSC Gestion capability?
     */
    private static function can_access_gestion() {
        return current_user_can( UFSC_Permissions::CAP_GESTION_READ ) || current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    /**
     * Does current user have a UFSC Licences capability?
     */
    private static function can_access_licences() {
        return current_user_can( UFSC_Permissions::CAP_LICENCES_READ ) || current_user_can( UFSC_Permissions::CAP_LICENCES_MANAGE );
    }

    /**
     * Does current user have a UFSC Compétitions capability?
     */
    private static function can_access_competitions() {
        return current_user_can( UFSC_Permissions::CAP_COMPETITIONS_READ ) || current_user_can( UFSC_Permissions::CAP_COMPETITIONS_MANAGE );
    }

    /**
     * Register a dedicated UFSC Licences top-level menu for users who only have licence rights.
     */
    public static function register_licences_bridge_menu() {
        if ( ! self::should_apply() || ! self::can_access_licences() || ! class_exists( 'UFSC_SQL_Admin' ) ) {
            return;
        }

        add_menu_page(
            __( 'UFSC Licences', 'ufsc-clubs' ),
            __( 'UFSC Licences', 'ufsc-clubs' ),
            UFSC_Permissions::CAP_LICENCES_READ,
            'ufsc-licences',
            array( 'UFSC_SQL_Admin', 'render_licences' ),
            'dashicons-id',
            59
        );
    }

    /**
     * Register a lightweight UFSC home page for limited users who can open UFSC Gestion.
     */
    public static function register_welcome_page() {
        if ( ! self::should_apply() || ! self::can_access_gestion() ) {
            return;
        }

        add_submenu_page(
            'ufsc-dashboard',
            __( 'Accueil UFSC', 'ufsc-clubs' ),
            __( 'Accueil UFSC', 'ufsc-clubs' ),
            UFSC_Permissions::CAP_GESTION_READ,
            'ufsc-home',
            array( __CLASS__, 'render_welcome_page' ),
            0
        );
    }

    /**
     * Render simplified home page.
     */
    public static function render_welcome_page() {
        if ( ! self::can_access_gestion() ) {
            wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ), esc_html__( 'Accès refusé', 'ufsc-clubs' ), array( 'response' => 403 ) );
        }

        $is_read_only = ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE )
            && ! current_user_can( UFSC_Permissions::CAP_LICENCES_MANAGE )
            && ! current_user_can( UFSC_Permissions::CAP_COMPETITIONS_MANAGE );
        $regions = function_exists( 'ufsc_current_user_allowed_regions' ) ? ufsc_current_user_allowed_regions() : array();

        echo '<div class="wrap ufsc-simplified-home">';
        echo '<h1>' . esc_html__( 'Bienvenue dans votre espace UFSC', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Votre interface est limitée aux outils nécessaires à votre mission.', 'ufsc-clubs' ) . '</p>';

        if ( $is_read_only ) {
            echo '<p><span class="ufsc-badge" style="display:inline-block;padding:4px 10px;border-radius:12px;background:#eef5ff;color:#0a4b78;font-weight:600;">' . esc_html__( 'Lecture seule', 'ufsc-clubs' ) . '</span></p>';
        }

        echo '<div class="ufsc-dashboard-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:20px;">';
        if ( self::can_access_licences() ) {
            echo '<div class="ufsc-dashboard-card"><h2>' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</h2><p><a class="button button-primary" href="' . esc_url( self::get_licences_url() ) . '">' . esc_html__( 'Accéder aux licences', 'ufsc-clubs' ) . '</a></p></div>';
        }
        if ( self::can_access_competitions() ) {
            echo '<div class="ufsc-dashboard-card"><h2>' . esc_html__( 'Compétitions', 'ufsc-clubs' ) . '</h2><p><a class="button button-primary" href="' . esc_url( self::get_competitions_url() ) . '">' . esc_html__( 'Accéder aux compétitions', 'ufsc-clubs' ) . '</a></p></div>';
        }
        if ( self::can_access_gestion() ) {
            echo '<div class="ufsc-dashboard-card"><h2>' . esc_html__( 'UFSC Gestion', 'ufsc-clubs' ) . '</h2><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=ufsc-dashboard' ) ) . '">' . esc_html__( 'Accéder à UFSC Gestion', 'ufsc-clubs' ) . '</a></p></div>';
        }
        echo '</div>';

        if ( ! empty( $regions ) ) {
            echo '<h2>' . esc_html__( 'Régions autorisées', 'ufsc-clubs' ) . '</h2>';
            echo '<p>' . esc_html( implode( ', ', $regions ) ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Redirect limited users to the first authorized UFSC screen after login.
     */
    public static function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( ! self::is_enabled() || ! ( $user instanceof WP_User ) ) {
            return $redirect_to;
        }

        if ( user_can( $user, 'manage_options' ) || UFSC_Permissions::is_wordpress_administrator( $user->ID ) ) {
            return $redirect_to;
        }

        foreach ( self::limited_user_caps() as $cap ) {
            if ( user_can( $user, $cap ) ) {
                return self::get_first_authorized_url_for_user( $user );
            }
        }

        return $redirect_to;
    }

    /**
     * Redirect /wp-admin/ dashboard visits to the first authorized UFSC screen.
     */
    public static function maybe_redirect_from_dashboard() {
        if ( ! self::should_apply() || wp_doing_ajax() ) {
            return;
        }

        $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
        if ( 'index.php' === $pagenow ) {
            wp_safe_redirect( self::get_first_authorized_url() );
            exit;
        }
    }

    /**
     * Block or redirect direct access to non-authorized admin pages.
     */
    public static function maybe_block_direct_admin_access() {
        if ( ! self::should_apply() || wp_doing_ajax() ) {
            return;
        }

        $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
        if ( in_array( $pagenow, self::always_allowed_admin_files(), true ) || self::is_authorized_admin_file_request( $pagenow ) ) {
            return;
        }

        if ( 'admin.php' === $pagenow ) {
            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
            if ( '' !== $page && self::is_authorized_page_slug( $page ) ) {
                return;
            }
            self::redirect_away_from_blocked_page();
        }

        if ( ! in_array( $pagenow, self::allowed_non_admin_php_files(), true ) ) {
            self::redirect_away_from_blocked_page();
        }
    }

    /**
     * Keep only explicitly authorized top-level menus and safe profile items.
     */
    public static function filter_admin_menu() {
        if ( ! self::should_apply() ) {
            return;
        }

        global $menu, $submenu;

        foreach ( (array) $menu as $index => $item ) {
            $slug  = isset( $item[2] ) ? (string) $item[2] : '';
            $title = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';

            if ( ! self::is_authorized_top_level_menu( $slug, $title ) ) {
                unset( $menu[ $index ] );
            }
        }

        foreach ( (array) $submenu as $parent_slug => $items ) {
            if ( ! self::is_authorized_parent_slug( (string) $parent_slug ) && 'profile.php' !== $parent_slug ) {
                unset( $submenu[ $parent_slug ] );
                continue;
            }

            foreach ( (array) $items as $index => $item ) {
                $slug = isset( $item[2] ) ? (string) $item[2] : '';
                if ( 'profile.php' === $parent_slug ) {
                    if ( 'profile.php' !== $slug ) {
                        unset( $submenu[ $parent_slug ][ $index ] );
                    }
                    continue;
                }

                if ( ! self::is_authorized_page_slug( $slug ) ) {
                    unset( $submenu[ $parent_slug ][ $index ] );
                }
            }
        }
    }

    /**
     * Simplify admin bar for limited UFSC users.
     */
    public static function simplify_admin_bar( $wp_admin_bar ) {
        if ( ! self::should_apply() || ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'get_nodes' ) ) {
            return;
        }

        $allowed_nodes = array( 'top-secondary', 'my-account', 'user-actions', 'user-info', 'edit-profile', 'logout' );
        foreach ( (array) $wp_admin_bar->get_nodes() as $node ) {
            if ( ! in_array( $node->id, $allowed_nodes, true ) ) {
                $wp_admin_bar->remove_node( $node->id );
            }
        }
    }

    /**
     * Allow non-admin.php requests that belong to an authorized UFSC module.
     */
    private static function is_authorized_admin_file_request( $pagenow ) {
        if ( ! self::can_access_competitions() ) {
            return false;
        }

        if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) ) {
            $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
            return self::is_competitions_slug( $post_type );
        }

        if ( 'post.php' === $pagenow && ! empty( $_GET['post'] ) ) {
            $post = get_post( absint( $_GET['post'] ) );
            return $post && self::is_competitions_slug( $post->post_type );
        }

        return false;
    }

    /**
     * @return string[]
     */
    private static function always_allowed_admin_files() {
        return array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php', 'profile.php' );
    }

    /**
     * @return string[]
     */
    private static function allowed_non_admin_php_files() {
        return array_merge( self::always_allowed_admin_files(), array( 'index.php' ) );
    }

    /**
     * Is the top-level menu authorized for the current limited user?
     */
    private static function is_authorized_top_level_menu( $slug, $title = '' ) {
        if ( 'profile.php' === $slug ) {
            return true;
        }

        if ( self::is_gestion_slug( $slug, $title ) ) {
            return self::can_access_gestion();
        }

        if ( self::is_licences_slug( $slug, $title ) ) {
            return self::can_access_licences();
        }

        if ( self::is_competitions_slug( $slug, $title ) ) {
            return self::can_access_competitions();
        }

        return false;
    }

    /**
     * Is a submenu parent authorized?
     */
    private static function is_authorized_parent_slug( $parent_slug ) {
        return self::is_authorized_page_slug( $parent_slug );
    }

    /**
     * Is an admin.php?page= slug authorized for the current limited user?
     */
    private static function is_authorized_page_slug( $slug ) {
        $slug = sanitize_key( (string) $slug );

        if ( self::is_gestion_slug( $slug ) ) {
            return self::can_access_gestion();
        }

        if ( self::is_licences_slug( $slug ) ) {
            return self::can_access_licences();
        }

        if ( self::is_competitions_slug( $slug ) ) {
            return self::can_access_competitions();
        }

        return false;
    }

    /**
     * Identify UFSC Gestion menu/page slugs without matching unrelated plugins.
     */
    private static function is_gestion_slug( $slug, $title = '' ) {
        $slug = sanitize_key( (string) $slug );
        if ( in_array( $slug, array( 'ufsc-dashboard', 'ufsc-home', 'ufsc-clubs', 'ufsc-exports' ), true ) ) {
            return true;
        }

        return self::title_contains( $title, 'ufsc gestion' );
    }

    /**
     * Identify UFSC Licences menu/page slugs.
     */
    private static function is_licences_slug( $slug, $title = '' ) {
        $slug = sanitize_key( (string) $slug );
        if ( in_array( $slug, array( 'ufsc-licences', 'ufsc-sql-licences', 'ufsc-licences-dashboard' ), true ) ) {
            return true;
        }

        return ( 0 === strpos( $slug, 'ufsc-licence' ) || 0 === strpos( $slug, 'ufsc-licences' ) || self::title_contains( $title, 'ufsc licences' ) );
    }

    /**
     * Identify competition menu/page slugs from the dedicated competition plugin.
     */
    private static function is_competitions_slug( $slug, $title = '' ) {
        $slug = sanitize_key( (string) $slug );
        if ( in_array( $slug, array( 'ufsc-competitions', 'competitions', 'edit.php?post_type=ufsc_competition' ), true ) ) {
            return true;
        }

        return ( false !== strpos( $slug, 'competition' ) || false !== strpos( $slug, 'competitions' ) || self::title_contains( $title, 'compétitions' ) || self::title_contains( $title, 'competitions' ) );
    }

    /**
     * Accent-insensitive-ish lowercase containment for admin menu labels.
     */
    private static function title_contains( $title, $needle ) {
        $title  = strtolower( remove_accents( wp_strip_all_tags( (string) $title ) ) );
        $needle = strtolower( remove_accents( (string) $needle ) );
        return '' !== $needle && false !== strpos( $title, $needle );
    }

    /**
     * First authorized URL for current user, with requested priority.
     */
    private static function get_first_authorized_url() {
        return self::get_first_authorized_url_for_user( wp_get_current_user() );
    }

    /**
     * First authorized URL for a user, with requested priority.
     */
    private static function get_first_authorized_url_for_user( WP_User $user ) {
        if ( user_can( $user, UFSC_Permissions::CAP_COMPETITIONS_READ ) || user_can( $user, UFSC_Permissions::CAP_COMPETITIONS_MANAGE ) ) {
            return self::get_competitions_url();
        }

        if ( user_can( $user, UFSC_Permissions::CAP_LICENCES_READ ) || user_can( $user, UFSC_Permissions::CAP_LICENCES_MANAGE ) ) {
            return self::get_licences_url();
        }

        if ( user_can( $user, UFSC_Permissions::CAP_GESTION_READ ) || user_can( $user, UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            return admin_url( 'admin.php?page=ufsc-home' );
        }

        return admin_url( 'profile.php' );
    }

    /**
     * Best-known UFSC Licences URL.
     */
    private static function get_licences_url() {
        return admin_url( 'admin.php?page=ufsc-licences' );
    }

    /**
     * Best-known UFSC Compétitions URL.
     */
    private static function get_competitions_url() {
        return admin_url( 'admin.php?page=ufsc-competitions' );
    }

    /**
     * Redirect blocked requests to the authorized UFSC entry point.
     */
    private static function redirect_away_from_blocked_page() {
        $target = self::get_first_authorized_url();
        $current = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

        if ( $current && false !== strpos( $target, $current ) ) {
            wp_die( esc_html__( 'Accès refusé : cette page ne fait pas partie de votre espace UFSC.', 'ufsc-clubs' ), esc_html__( 'Accès refusé', 'ufsc-clubs' ), array( 'response' => 403 ) );
        }

        wp_safe_redirect( $target );
        exit;
    }
}
