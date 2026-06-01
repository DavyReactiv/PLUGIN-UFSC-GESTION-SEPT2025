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
     * UFSC roles considered limited WordPress administrators for the simplified UI.
     *
     * @return string[]
     */
    private static function limited_user_roles() {
        return array(
            'ufsc_region_viewer',
            'ufsc_region_manager',
            'ufsc_competition_manager',
            'ufsc_admin_limited',
        );
    }

    /**
     * Bootstrap admin hooks.
     */
    public static function init() {
        add_filter( 'login_redirect', array( __CLASS__, 'filter_login_redirect' ), 999999, 3 );
        add_filter( 'wp_redirect', array( __CLASS__, 'prevent_front_office_redirect' ), 999999, 2 );

        if ( ! is_admin() ) {
            return;
        }

        add_action( 'admin_init', array( __CLASS__, 'force_allow_wp_admin_for_limited_ufsc_users' ), 0 );
        add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_from_dashboard' ), 1 );
        add_action( 'admin_init', array( __CLASS__, 'maybe_block_direct_admin_access' ), 20 );
        add_action( 'admin_menu', array( __CLASS__, 'register_compatibility_parent_pages' ), 1 );
        add_action( 'admin_menu', array( __CLASS__, 'register_authorized_alias_pages' ), 18 );
        add_action( 'admin_menu', array( __CLASS__, 'register_licences_bridge_menu' ), 19 );
        add_action( 'admin_menu', array( __CLASS__, 'register_welcome_page' ), 20 );
        add_action( 'admin_menu', array( __CLASS__, 'cleanup_compatibility_parent_pages_for_full_admins' ), 9997 );
        add_action( 'admin_menu', array( __CLASS__, 'normalize_ufsc_menu_capabilities' ), 9998 );
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
    public static function is_limited_ufsc_user( $user_id = null ) {
        if ( null === $user_id ) {
            if ( ! is_user_logged_in() ) {
                return false;
            }
            $user_id = get_current_user_id();
        }

        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return false;
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        if ( user_can( $user_id, 'manage_options' ) || UFSC_Permissions::is_wordpress_administrator( $user_id ) ) {
            return false;
        }

        $has_limited_role = (bool) array_intersect( self::limited_user_roles(), (array) $user->roles );
        foreach ( self::limited_user_caps() as $cap ) {
            if ( user_can( $user_id, $cap ) ) {
                return true;
            }
        }

        return $has_limited_role;
    }

    /**
     * Whether simplified UI rules should currently apply.
     */
    private static function should_apply() {
        return self::is_enabled() && self::is_limited_ufsc_user();
    }

    /**
     * Whether current limited user may use WordPress posts.
     */
    private static function can_access_articles() {
        return current_user_can( 'edit_posts' );
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
     * Register stable compatibility parents before companion plugins add their subpages.
     *
     * Some UFSC LC installations attach settings pages to historical parents such as
     * ufsc_lc or ufsc-licence-competition. Registering hidden parents prevents the
     * WordPress "parent slug missing" admin notice without exposing sensitive pages.
     */
    public static function register_compatibility_parent_pages() {
        if ( ! self::is_enabled() ) {
            return;
        }

        // These are real parents because companion plugins may call
        // add_submenu_page( 'ufsc_lc', ... ). Hidden subpages alone do not
        // satisfy WordPress' parent_slug validation.
        foreach ( self::compatibility_parent_definitions() as $definition ) {
            if ( self::menu_slug_exists( $definition['slug'] ) ) {
                continue;
            }
            add_menu_page(
                $definition['page_title'],
                $definition['menu_title'],
                $definition['capability'],
                $definition['slug'],
                $definition['callback'],
                $definition['icon'],
                $definition['position']
            );
        }
    }

    /**
     * Parent menu definitions used only to satisfy legacy companion parent_slug values.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function compatibility_parent_definitions() {
        return array(
            array(
                'slug'       => 'ufsc_lc',
                'page_title' => __( 'UFSC Licences', 'ufsc-clubs' ),
                'menu_title' => __( 'UFSC Licences', 'ufsc-clubs' ),
                'capability' => UFSC_Permissions::CAP_LICENCES_READ,
                'callback'   => array( 'UFSC_SQL_Admin', 'render_licences' ),
                'icon'       => 'dashicons-id',
                'position'   => 59,
            ),
            array(
                'slug'       => 'ufsc-lc',
                'page_title' => __( 'UFSC Licences', 'ufsc-clubs' ),
                'menu_title' => __( 'UFSC Licences', 'ufsc-clubs' ),
                'capability' => UFSC_Permissions::CAP_LICENCES_READ,
                'callback'   => array( 'UFSC_SQL_Admin', 'render_licences' ),
                'icon'       => 'dashicons-id',
                'position'   => 60,
            ),
            array(
                'slug'       => 'ufsc_lc_competitions',
                'page_title' => __( 'Compétitions', 'ufsc-clubs' ),
                'menu_title' => __( 'Compétitions', 'ufsc-clubs' ),
                'capability' => UFSC_Permissions::CAP_COMPETITIONS_READ,
                'callback'   => array( __CLASS__, 'render_competitions_bridge_page' ),
                'icon'       => 'dashicons-awards',
                'position'   => 61,
            ),
            array(
                'slug'       => 'ufsc-licence-competition',
                'page_title' => __( 'Compétitions', 'ufsc-clubs' ),
                'menu_title' => __( 'Compétitions', 'ufsc-clubs' ),
                'capability' => UFSC_Permissions::CAP_COMPETITIONS_READ,
                'callback'   => array( __CLASS__, 'render_competitions_bridge_page' ),
                'icon'       => 'dashicons-awards',
                'position'   => 62,
            ),
        );
    }

    /**
     * Remove compatibility-only parents from full WordPress administrators' visible menu.
     */
    public static function cleanup_compatibility_parent_pages_for_full_admins() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        foreach ( self::compatibility_parent_definitions() as $definition ) {
            remove_menu_page( $definition['slug'] );
        }
    }

    /**
     * Register hidden aliases for UFSC Gestion/Licences slugs used by existing installs.
     */
    public static function register_authorized_alias_pages() {
        if ( ! self::is_enabled() ) {
            return;
        }

        if ( self::can_access_gestion() ) {
            add_submenu_page( null, __( 'UFSC Gestion', 'ufsc-clubs' ), __( 'UFSC Gestion', 'ufsc-clubs' ), UFSC_Permissions::CAP_GESTION_READ, 'ufsc-gestion', array( 'UFSC_CL_Admin_Menu', 'render_dashboard' ) );
            add_submenu_page( null, __( 'UFSC Gestion', 'ufsc-clubs' ), __( 'UFSC Gestion', 'ufsc-clubs' ), UFSC_Permissions::CAP_GESTION_READ, 'ufsc_gestion', array( 'UFSC_CL_Admin_Menu', 'render_dashboard' ) );
            if ( class_exists( 'UFSC_SQL_Admin' ) ) {
                add_submenu_page( null, __( 'Clubs UFSC', 'ufsc-clubs' ), __( 'Clubs UFSC', 'ufsc-clubs' ), UFSC_Permissions::CAP_GESTION_READ, 'ufsc_clubs', array( 'UFSC_SQL_Admin', 'render_clubs' ) );
            }
        }

        if ( self::can_access_licences() && class_exists( 'UFSC_SQL_Admin' ) ) {
            foreach ( array( 'ufsc_licences', 'ufsc-licence', 'ufsc_licence', 'ufsc-licences-dashboard', 'ufsc_lc_licences' ) as $slug ) {
                if ( ! self::menu_slug_exists( $slug ) ) {
                    add_submenu_page( null, __( 'UFSC Licences', 'ufsc-clubs' ), __( 'UFSC Licences', 'ufsc-clubs' ), UFSC_Permissions::CAP_LICENCES_READ, $slug, array( 'UFSC_SQL_Admin', 'render_licences' ) );
                }
            }
        }

        if ( self::can_access_competitions() ) {
            foreach ( array( 'ufsc-licence-competition', 'ufsc_licence_competition', 'ufsc-competitions', 'ufsc_competitions', 'ufsc-competition', 'ufsc_competition', 'ufsc-competition-dashboard', 'ufsc_competition_dashboard' ) as $slug ) {
                if ( ! self::menu_slug_exists( $slug ) ) {
                    add_submenu_page( null, __( 'Compétitions', 'ufsc-clubs' ), __( 'Compétitions', 'ufsc-clubs' ), UFSC_Permissions::CAP_COMPETITIONS_READ, $slug, array( __CLASS__, 'render_competitions_bridge_page' ) );
                }
            }
        }
    }

    /**
     * Render a safe bridge for competition aliases when the companion plugin uses another slug.
     */
    public static function render_competitions_bridge_page() {
        if ( ! self::can_access_competitions() ) {
            wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ), esc_html__( 'Accès refusé', 'ufsc-clubs' ), array( 'response' => 403 ) );
        }

        $detected = self::get_detected_ufsc_menus( 'competitions' );
        $current  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $bridge_aliases = array( 'ufsc-licence-competition', 'ufsc_licence_competition', 'ufsc-competitions', 'ufsc_competitions', 'ufsc-competition', 'ufsc_competition', 'ufsc-competition-dashboard', 'ufsc_competition_dashboard' );
        foreach ( $detected as $menu ) {
            if ( empty( $menu['slug'] ) || $menu['slug'] === $current || in_array( $menu['slug'], $bridge_aliases, true ) ) {
                continue;
            }
            wp_safe_redirect( admin_url( 'admin.php?page=' . rawurlencode( $menu['slug'] ) ) );
            exit;
        }

        echo '<div class="wrap"><h1>' . esc_html__( 'Compétitions', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Votre accès Compétitions est autorisé, mais aucun écran Compétitions actif n’a été détecté. Vérifiez que le plugin UFSC Compétitions est actif et que son menu utilise ufsc_competitions_read.', 'ufsc-clubs' ) . '</p></div>';
    }

    /**
     * Register a dedicated UFSC Licences top-level menu for users who only have licence rights.
     */
    public static function register_licences_bridge_menu() {
        if ( ! self::should_apply() || ! self::can_access_licences() || ! class_exists( 'UFSC_SQL_Admin' ) ) {
            return;
        }

        if ( self::top_level_menu_slug_exists( 'ufsc-sql-licences' ) ) {
            return;
        }

        add_menu_page(
            __( 'UFSC Licences', 'ufsc-clubs' ),
            __( 'UFSC Licences', 'ufsc-clubs' ),
            UFSC_Permissions::CAP_LICENCES_READ,
            'ufsc-sql-licences',
            array( 'UFSC_SQL_Admin', 'render_licences' ),
            'dashicons-id',
            59
        );

        add_submenu_page(
            'ufsc-sql-licences',
            __( 'Tableau licences', 'ufsc-clubs' ),
            __( 'Tableau licences', 'ufsc-clubs' ),
            UFSC_Permissions::CAP_LICENCES_READ,
            'ufsc-sql-licences',
            array( 'UFSC_SQL_Admin', 'render_licences' )
        );
    }

    /**
     * Register a lightweight UFSC home page for limited users who can open UFSC Gestion.
     */
    public static function register_welcome_page() {
        if ( ! self::should_apply() ) {
            return;
        }

        add_menu_page(
            __( 'Espace UFSC', 'ufsc-clubs' ),
            __( 'Espace UFSC', 'ufsc-clubs' ),
            'read',
            'ufsc-limited-dashboard',
            array( __CLASS__, 'render_welcome_page' ),
            'dashicons-admin-home',
            57
        );

        add_submenu_page(
            null,
            __( 'Espace UFSC', 'ufsc-clubs' ),
            __( 'Espace UFSC', 'ufsc-clubs' ),
            'read',
            'ufsc-home',
            array( __CLASS__, 'render_welcome_page' )
        );
    }

    /**
     * Render simplified home page.
     */
    public static function render_welcome_page() {
        if ( ! self::should_apply() ) {
            wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ), esc_html__( 'Accès refusé', 'ufsc-clubs' ), array( 'response' => 403 ) );
        }

        $urls      = self::get_module_urls();
        $user      = wp_get_current_user();
        $role_name = ! empty( $user->roles ) ? implode( ', ', array_map( 'sanitize_key', (array) $user->roles ) ) : __( 'Aucun rôle', 'ufsc-clubs' );
        $regions   = self::get_current_user_region_summary();
        $cards     = self::get_dashboard_cards( $urls );

        echo '<div class="wrap ufsc-simplified-home">';
        echo '<style>.ufsc-simplified-home .ufsc-hero{background:linear-gradient(135deg,#0a4b78,#12385c);color:#fff;border-radius:16px;padding:28px 32px;margin:18px 0 24px;box-shadow:0 14px 35px rgba(10,75,120,.18)}.ufsc-simplified-home .ufsc-hero h1{color:#fff;margin:0 0 8px;font-size:30px}.ufsc-simplified-home .ufsc-hero p{font-size:15px;max-width:780px;margin:0;opacity:.92}.ufsc-module-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;margin:22px 0}.ufsc-module-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:20px;box-shadow:0 6px 18px rgba(0,0,0,.06);display:flex;flex-direction:column;min-height:190px}.ufsc-module-card h2{margin:0 0 8px;font-size:19px}.ufsc-module-card p{margin:0 0 16px;color:#50575e}.ufsc-module-card .ufsc-card-footer{margin-top:auto;display:flex;gap:10px;align-items:center;justify-content:space-between}.ufsc-state{display:inline-flex;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700;background:#f0f6fc;color:#0a4b78}.ufsc-state--manage{background:#edfaef;color:#006b2f}.ufsc-state--denied{background:#fcf0f1;color:#b32d2e}.ufsc-region-box,.ufsc-summary-box{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px 18px;margin-top:18px}.ufsc-region-box.warning{border-left:4px solid #dba617}.ufsc-summary-list{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 0}.ufsc-summary-list span{background:#f6f7f7;border-radius:999px;padding:5px 10px;font-size:12px}</style>';

        echo '<section class="ufsc-hero"><h1>' . esc_html__( 'Bienvenue dans votre espace UFSC', 'ufsc-clubs' ) . '</h1><p>' . esc_html__( 'Votre interface est limitée aux outils nécessaires à votre mission.', 'ufsc-clubs' ) . '</p></section>';

        echo '<div class="ufsc-module-grid">';
        foreach ( $cards as $card ) {
            $state_class = 'denied' === $card['state_key'] ? 'ufsc-state--denied' : ( 'manage' === $card['state_key'] ? 'ufsc-state--manage' : '' );
            echo '<article class="ufsc-module-card">';
            echo '<h2>' . esc_html( $card['title'] ) . '</h2>';
            echo '<p>' . esc_html( $card['description'] ) . '</p>';
            echo '<div class="ufsc-card-footer"><span class="ufsc-state ' . esc_attr( $state_class ) . '">' . esc_html( $card['state'] ) . '</span>';
            if ( $card['allowed'] ) {
                echo '<a class="button button-primary" href="' . esc_url( $card['url'] ) . '">' . esc_html( $card['button'] ) . '</a>';
            } else {
                echo '<button class="button" disabled="disabled">' . esc_html__( 'Non autorisé', 'ufsc-clubs' ) . '</button>';
            }
            echo '</div></article>';
        }
        echo '</div>';

        echo '<section class="ufsc-region-box ' . ( $regions['warning'] ? 'warning' : '' ) . '"><h2>' . esc_html__( 'Régions autorisées', 'ufsc-clubs' ) . '</h2><p>' . esc_html( $regions['label'] ) . '</p></section>';

        echo '<section class="ufsc-summary-box"><h2>' . esc_html__( 'Résumé de vos accès', 'ufsc-clubs' ) . '</h2>';
        echo '<p><strong>' . esc_html__( 'Rôle :', 'ufsc-clubs' ) . '</strong> ' . esc_html( $role_name ) . '</p>';
        echo '<div class="ufsc-summary-list">';
        foreach ( self::get_limited_user_rights_summary() as $summary ) {
            echo '<span>' . esc_html( $summary ) . '</span>';
        }
        echo '</div></section>';

        echo '</div>';
    }

    /**
     * Cards shown on the simplified dashboard.
     *
     * @param array<string,string> $urls Module URLs.
     * @return array<int,array<string,mixed>>
     */
    private static function get_dashboard_cards( array $urls ) {
        return array(
            array(
                'title'       => __( 'Articles', 'ufsc-clubs' ),
                'description' => __( 'Créer et gérer les actualités autorisées.', 'ufsc-clubs' ),
                'url'         => $urls['articles'],
                'button'      => __( 'Voir les articles', 'ufsc-clubs' ),
                'allowed'     => self::can_access_articles(),
                'state'       => self::can_access_articles() ? __( 'Gestion', 'ufsc-clubs' ) : __( 'Non autorisé', 'ufsc-clubs' ),
                'state_key'   => self::can_access_articles() ? 'manage' : 'denied',
            ),
            array(
                'title'       => __( 'UFSC Gestion', 'ufsc-clubs' ),
                'description' => __( 'Consulter les clubs et informations administratives.', 'ufsc-clubs' ),
                'url'         => $urls['gestion'],
                'button'      => __( 'Ouvrir UFSC Gestion', 'ufsc-clubs' ),
                'allowed'     => self::can_access_gestion(),
                'state'       => ! self::can_access_gestion() ? __( 'Non autorisé', 'ufsc-clubs' ) : ( current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ? __( 'Gestion', 'ufsc-clubs' ) : __( 'Lecture', 'ufsc-clubs' ) ),
                'state_key'   => ! self::can_access_gestion() ? 'denied' : ( current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ? 'manage' : 'read' ),
            ),
            array(
                'title'       => __( 'UFSC Licences', 'ufsc-clubs' ),
                'description' => __( 'Consulter les licences et informations associées.', 'ufsc-clubs' ),
                'url'         => $urls['licences'],
                'button'      => __( 'Ouvrir les licences', 'ufsc-clubs' ),
                'allowed'     => self::can_access_licences(),
                'state'       => ! self::can_access_licences() ? __( 'Non autorisé', 'ufsc-clubs' ) : ( current_user_can( UFSC_Permissions::CAP_LICENCES_MANAGE ) ? __( 'Gestion', 'ufsc-clubs' ) : __( 'Lecture', 'ufsc-clubs' ) ),
                'state_key'   => ! self::can_access_licences() ? 'denied' : ( current_user_can( UFSC_Permissions::CAP_LICENCES_MANAGE ) ? 'manage' : 'read' ),
            ),
            array(
                'title'       => __( 'Compétitions', 'ufsc-clubs' ),
                'description' => __( 'Gérer les compétitions, inscriptions, combats et résultats.', 'ufsc-clubs' ),
                'url'         => $urls['competitions'],
                'button'      => __( 'Ouvrir les compétitions', 'ufsc-clubs' ),
                'allowed'     => self::can_access_competitions(),
                'state'       => ! self::can_access_competitions() ? __( 'Non autorisé', 'ufsc-clubs' ) : ( current_user_can( UFSC_Permissions::CAP_COMPETITIONS_MANAGE ) ? __( 'Gestion', 'ufsc-clubs' ) : __( 'Lecture', 'ufsc-clubs' ) ),
                'state_key'   => ! self::can_access_competitions() ? 'denied' : ( current_user_can( UFSC_Permissions::CAP_COMPETITIONS_MANAGE ) ? 'manage' : 'read' ),
            ),
            array(
                'title'       => __( 'Profil', 'ufsc-clubs' ),
                'description' => __( 'Mettre à jour vos informations de compte WordPress.', 'ufsc-clubs' ),
                'url'         => $urls['profile'],
                'button'      => __( 'Modifier mon profil', 'ufsc-clubs' ),
                'allowed'     => true,
                'state'       => __( 'Autorisé', 'ufsc-clubs' ),
                'state_key'   => 'read',
            ),
        );
    }

    /**
     * Lightweight rights summary for limited users.
     *
     * @return string[]
     */
    private static function get_limited_user_rights_summary() {
        $rights = array();
        $rights[] = self::can_access_articles() ? __( 'Articles : gestion', 'ufsc-clubs' ) : __( 'Articles : non autorisé', 'ufsc-clubs' );
        $rights[] = self::can_access_gestion() ? __( 'UFSC Gestion : lecture', 'ufsc-clubs' ) : __( 'UFSC Gestion : non autorisé', 'ufsc-clubs' );
        $rights[] = self::can_access_licences() ? __( 'UFSC Licences : lecture', 'ufsc-clubs' ) : __( 'UFSC Licences : non autorisé', 'ufsc-clubs' );
        $rights[] = self::can_access_competitions() ? __( 'Compétitions : autorisé', 'ufsc-clubs' ) : __( 'Compétitions : non autorisé', 'ufsc-clubs' );
        return $rights;
    }

    /**
     * Current user's regional summary without expensive SQL.
     *
     * @return array{label:string,warning:bool}
     */
    private static function get_current_user_region_summary() {
        if ( function_exists( 'ufsc_user_has_all_regions_access' ) && ufsc_user_has_all_regions_access() ) {
            return array( 'label' => __( 'Toutes les régions', 'ufsc-clubs' ), 'warning' => false );
        }

        $regions = function_exists( 'ufsc_current_user_allowed_regions' ) ? ufsc_current_user_allowed_regions() : array();
        if ( empty( $regions ) ) {
            return array( 'label' => __( 'Aucune région n’est associée à votre compte. Contactez un administrateur UFSC.', 'ufsc-clubs' ), 'warning' => true );
        }

        return array( 'label' => implode( ', ', array_map( 'sanitize_text_field', $regions ) ), 'warning' => false );
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

        if ( self::is_limited_ufsc_user( $user->ID ) ) {
            return self::get_first_authorized_url_for_user( $user );
        }

        return $redirect_to;
    }

    /**
     * Keep limited UFSC users inside wp-admin even when a third-party plugin tries to send non-admins to the front-office.
     */
    public static function force_allow_wp_admin_for_limited_ufsc_users() {
        if ( ! self::should_apply() || wp_doing_ajax() ) {
            return;
        }

        $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
        if ( in_array( $pagenow, self::always_allowed_admin_files(), true ) ) {
            return;
        }

        if ( 'admin.php' === $pagenow ) {
            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
            if ( '' === $page || self::is_authorized_page_slug( $page ) ) {
                return;
            }
        }
    }

    /**
     * Rewrite front-office redirects triggered during wp-admin requests for limited UFSC users.
     */
    public static function prevent_front_office_redirect( $location, $status = 302 ) {
        unset( $status );

        if ( ! self::is_enabled() || ! self::is_limited_ufsc_user() ) {
            return $location;
        }

        $location = (string) $location;
        if ( '' === $location || self::is_admin_url( $location ) ) {
            return $location;
        }

        if ( self::is_front_office_url( $location ) ) {
            $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
            if ( 'profile.php' === $pagenow ) {
                return admin_url( 'profile.php' );
            }

            if ( 'admin.php' === $pagenow ) {
                $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
                if ( '' !== $page && self::is_authorized_page_slug( $page ) ) {
                    return admin_url( 'admin.php?page=' . rawurlencode( $page ) );
                }
            }

            return self::get_first_authorized_url();
        }

        return $location;
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
     * Normalize capabilities requested by UFSC menus registered by this or companion plugins.
     */
    public static function normalize_ufsc_menu_capabilities() {
        if ( ! self::is_enabled() ) {
            return;
        }

        global $menu, $submenu;

        foreach ( (array) $menu as $index => $item ) {
            $slug  = isset( $item[2] ) ? (string) $item[2] : '';
            $title = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
            $cap   = self::capability_for_menu_item( $slug, $title );
            if ( $cap && isset( $menu[ $index ][1] ) ) {
                $menu[ $index ][1] = $cap;
                self::clear_menu_nopriv( $slug );
            }
        }

        foreach ( (array) $submenu as $parent_slug => $items ) {
            foreach ( (array) $items as $index => $item ) {
                $slug  = isset( $item[2] ) ? (string) $item[2] : '';
                $title = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
                $cap   = self::capability_for_menu_item( $slug, $title );
                if ( $cap && isset( $submenu[ $parent_slug ][ $index ][1] ) ) {
                    $submenu[ $parent_slug ][ $index ][1] = $cap;
                    self::clear_submenu_nopriv( (string) $parent_slug, $slug );
                }
            }
        }
    }

    /**
     * Capability that a UFSC menu item should require, or null for unrelated/sensitive menus.
     */
    private static function capability_for_menu_item( $slug, $title = '' ) {
        if ( self::is_sensitive_ufsc_slug( $slug ) ) {
            return null;
        }

        if ( self::is_competitions_slug( $slug, $title ) ) {
            return UFSC_Permissions::CAP_COMPETITIONS_READ;
        }

        if ( self::is_licences_slug( $slug, $title ) ) {
            return UFSC_Permissions::CAP_LICENCES_READ;
        }

        if ( self::is_gestion_slug( $slug, $title ) ) {
            return UFSC_Permissions::CAP_GESTION_READ;
        }

        return null;
    }

    /**
     * Sensitive UFSC pages stay reserved to their existing server-side capabilities.
     */
    private static function is_sensitive_ufsc_slug( $slug ) {
        $normalized = self::normalize_page_slug( $slug );
        if ( self::slug_matches( $slug, array( 'ufsc-permissions', 'ufsc-settings', 'ufsc-woocommerce', 'ufsc-sql-settings' ) ) ) {
            return true;
        }

        foreach ( array( 'settings', 'parametres', 'wpcode', 'members' ) as $needle ) {
            if ( false !== strpos( $normalized, $needle ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove a top-level menu nopriv marker after normalizing its capability.
     */
    private static function clear_menu_nopriv( $slug ) {
        global $_wp_menu_nopriv;
        if ( is_array( $_wp_menu_nopriv ) ) {
            unset( $_wp_menu_nopriv[ self::normalize_page_slug( $slug ) ] );
            unset( $_wp_menu_nopriv[ $slug ] );
        }
    }

    /**
     * Remove a submenu nopriv marker after normalizing its capability.
     */
    private static function clear_submenu_nopriv( $parent_slug, $slug ) {
        global $_wp_submenu_nopriv;
        if ( is_array( $_wp_submenu_nopriv ) ) {
            unset( $_wp_submenu_nopriv[ $parent_slug ][ $slug ] );
            unset( $_wp_submenu_nopriv[ $parent_slug ][ self::normalize_page_slug( $slug ) ] );
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

                if ( 'edit.php' === $parent_slug ) {
                    if ( ! in_array( $slug, array( 'edit.php', 'post-new.php' ), true ) ) {
                        unset( $submenu[ $parent_slug ][ $index ] );
                    }
                    continue;
                }

                $title = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
                if ( self::is_sensitive_limited_submenu_item( $slug, $title ) || ! self::is_authorized_page_slug( $slug ) ) {
                    unset( $submenu[ $parent_slug ][ $index ] );
                }
            }
        }

        self::deduplicate_limited_top_level_menus();
    }

    /**
     * Ensure only one visible top-level entry remains for each simplified UFSC module.
     */
    private static function deduplicate_limited_top_level_menus() {
        global $menu;
        $best = array();
        foreach ( (array) $menu as $index => $item ) {
            $slug   = isset( $item[2] ) ? (string) $item[2] : '';
            $title  = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
            $module = 'profile.php' === $slug ? 'profile' : self::module_for_slug( $slug, $title );
            if ( 'edit.php' === $slug ) {
                $module = 'articles';
            }
            if ( ! $module ) {
                continue;
            }

            $score = self::preferred_top_level_menu_score( $module, $slug );
            if ( ! isset( $best[ $module ] ) || $score < $best[ $module ]['score'] ) {
                $best[ $module ] = array( 'index' => $index, 'score' => $score );
            }
        }

        foreach ( (array) $menu as $index => $item ) {
            $slug   = isset( $item[2] ) ? (string) $item[2] : '';
            $title  = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
            $module = 'profile.php' === $slug ? 'profile' : self::module_for_slug( $slug, $title );
            if ( 'edit.php' === $slug ) {
                $module = 'articles';
            }
            if ( $module && isset( $best[ $module ] ) && $best[ $module ]['index'] !== $index ) {
                unset( $menu[ $index ] );
            }
        }
    }

    /**
     * Lower score wins when duplicate top-level module menus exist.
     */
    private static function preferred_top_level_menu_score( $module, $slug ) {
        $raw_slug = (string) $slug;
        $slug     = self::normalize_page_slug( $raw_slug );
        $preferred = array(
            'espace_ufsc'  => array( 'ufsc-limited-dashboard', 'ufsc-home' ),
            'articles'     => array( 'edit.php' ),
            'gestion'      => array( 'ufsc-dashboard', 'ufsc-clubs', 'ufsc-sql-clubs', 'ufsc-gestion', 'ufsc_gestion' ),
            'licences'     => array( 'ufsc-sql-licences', 'ufsc-licences', 'ufsc_lc_licences', 'ufsc-sql-licenses', 'ufsc_lc', 'ufsc-lc' ),
            'competitions' => array( 'ufsc-competitions', 'ufsc_competitions', 'ufsc-licence-competition', 'ufsc_lc_competitions' ),
            'profile'      => array( 'profile.php' ),
        );

        if ( empty( $preferred[ $module ] ) ) {
            return 100;
        }

        $position = array_search( $raw_slug, $preferred[ $module ], true );
        if ( false === $position ) {
            $position = array_search( $slug, $preferred[ $module ], true );
        }
        return false === $position ? 100 : (int) $position;
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
        if ( self::can_access_articles() && in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) ) {
            $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
            return '' === $post_type || 'post' === $post_type;
        }

        if ( self::can_access_articles() && 'post.php' === $pagenow && ! empty( $_GET['post'] ) ) {
            $post = get_post( absint( $_GET['post'] ) );
            return $post && 'post' === $post->post_type;
        }

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

        if ( 'edit.php' === $slug ) {
            return self::can_access_articles();
        }

        if ( self::slug_matches( $slug, array( 'ufsc-limited-dashboard', 'ufsc-home' ) ) ) {
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
        if ( 'edit.php' === $parent_slug ) {
            return self::can_access_articles();
        }

        return self::is_authorized_page_slug( $parent_slug );
    }

    /**
     * Is an admin.php?page= slug authorized for the current limited user?
     */
    private static function is_authorized_page_slug( $slug ) {
        $slug = sanitize_key( (string) $slug );

        if ( self::slug_matches( $slug, array( 'ufsc-limited-dashboard', 'ufsc-home' ) ) ) {
            return true;
        }

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
        if ( self::slug_matches( $slug, array( 'ufsc-gestion', 'ufsc_gestion', 'ufsc-clubs', 'ufsc_clubs', 'ufsc-dashboard', 'ufsc-home', 'ufsc-exports' ) ) ) {
            return true;
        }

        return self::title_contains( $title, 'ufsc gestion' );
    }

    /**
     * Identify UFSC Licences menu/page slugs.
     */
    private static function is_licences_slug( $slug, $title = '' ) {
        if ( self::slug_matches( $slug, array( 'ufsc-licences', 'ufsc_licences', 'ufsc-licence', 'ufsc_licence', 'ufsc-licences-dashboard', 'ufsc_lc_licences', 'ufsc_lc', 'ufsc-lc', 'ufsc-sql-licences', 'ufsc-sql-licenses' ) ) ) {
            return true;
        }

        $normalized = self::normalize_page_slug( $slug );
        return ( 0 === strpos( $normalized, 'ufsc-licence' ) || 0 === strpos( $normalized, 'ufsc_licence' ) || self::title_contains( $title, 'ufsc licences' ) || self::title_contains( $title, 'ufsc lc' ) );
    }

    /**
     * Identify competition menu/page slugs from the dedicated competition plugin.
     */
    private static function is_competitions_slug( $slug, $title = '' ) {
        $raw = strtolower( (string) $slug );
        if ( false !== strpos( $raw, 'post_type=ufsc_competition' ) || false !== strpos( $raw, 'post_type=ufsc-competition' ) ) {
            return true;
        }

        if ( self::slug_matches( $slug, array( 'ufsc-competitions', 'ufsc_competitions', 'ufsc-competition', 'ufsc_competition', 'ufsc-competition-dashboard', 'ufsc_competition_dashboard', 'ufsc-licence-competition', 'ufsc_licence_competition', 'competitions' ) ) ) {
            return true;
        }

        $normalized = self::normalize_page_slug( $slug );
        return ( false !== strpos( $normalized, 'competition' ) || false !== strpos( $normalized, 'competitions' ) || self::title_contains( $title, 'compétitions' ) || self::title_contains( $title, 'competitions' ) );
    }

    /**
     * Extract and normalize a menu/page slug, including admin.php?page=... values.
     */
    private static function normalize_page_slug( $slug ) {
        $slug = html_entity_decode( (string) $slug );
        if ( false !== strpos( $slug, 'page=' ) ) {
            $query = wp_parse_url( $slug, PHP_URL_QUERY );
            if ( $query ) {
                $args = array();
                wp_parse_str( $query, $args );
                if ( ! empty( $args['page'] ) ) {
                    $slug = (string) $args['page'];
                }
            }
        }

        return sanitize_key( $slug );
    }

    /**
     * Whether a raw or normalized slug is in an allowed list.
     */
    private static function slug_matches( $slug, array $allowed_slugs ) {
        $normalized = self::normalize_page_slug( $slug );
        return in_array( $normalized, array_map( 'sanitize_key', $allowed_slugs ), true );
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
     * Whether a menu/submenu slug is already registered in WordPress menu globals.
     */
    private static function menu_slug_exists( $slug ) {
        $slug = self::normalize_page_slug( $slug );
        $map  = self::get_registered_menu_slug_map();
        return isset( $map[ $slug ] );
    }

    /**
     * Request-local map of registered admin slugs to avoid repeated menu scans.
     *
     * @return array<string,bool>
     */
    private static function get_registered_menu_slug_map() {
        global $menu, $submenu;
        static $cache = null;
        static $signature = '';

        $submenu_items = 0;
        foreach ( (array) $submenu as $items ) {
            $submenu_items += count( (array) $items );
        }
        $current_signature = count( (array) $menu ) . ':' . count( (array) $submenu ) . ':' . $submenu_items;
        if ( null !== $cache && $signature === $current_signature ) {
            return $cache;
        }

        $cache = array();
        foreach ( (array) $menu as $item ) {
            if ( isset( $item[2] ) ) {
                $cache[ self::normalize_page_slug( (string) $item[2] ) ] = true;
            }
        }

        foreach ( (array) $submenu as $items ) {
            foreach ( (array) $items as $item ) {
                if ( isset( $item[2] ) ) {
                    $cache[ self::normalize_page_slug( (string) $item[2] ) ] = true;
                }
            }
        }

        $signature = $current_signature;
        return $cache;
    }

    /**
     * Return UFSC menus currently registered in WordPress globals for diagnostics and redirects.
     *
     * @return array<int,array{module:string,parent:string,slug:string,capability:string,title:string,type:string}>
     */
    public static function get_detected_ufsc_menus( $module = null ) {
        global $menu, $submenu;

        $module = $module ? sanitize_key( (string) $module ) : null;
        $items  = array();

        foreach ( (array) $menu as $item ) {
            $slug  = isset( $item[2] ) ? (string) $item[2] : '';
            $title = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
            $found = self::module_for_slug( $slug, $title );
            if ( $found && ( null === $module || $module === $found ) ) {
                $items[] = array(
                    'module'     => $found,
                    'parent'     => '',
                    'slug'       => self::normalize_page_slug( $slug ),
                    'capability' => isset( $item[1] ) ? (string) $item[1] : '',
                    'title'      => $title,
                    'type'       => 'menu',
                );
            }
        }

        foreach ( (array) $submenu as $parent_slug => $children ) {
            foreach ( (array) $children as $item ) {
                $slug  = isset( $item[2] ) ? (string) $item[2] : '';
                $title = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
                $found = self::module_for_slug( $slug, $title );
                if ( $found && ( null === $module || $module === $found ) ) {
                    $items[] = array(
                        'module'     => $found,
                        'parent'     => self::normalize_page_slug( (string) $parent_slug ),
                        'slug'       => self::normalize_page_slug( $slug ),
                        'capability' => isset( $item[1] ) ? (string) $item[1] : '',
                        'title'      => $title,
                        'type'       => 'submenu',
                    );
                }
            }
        }

        return $items;
    }

    /**
     * Determine the UFSC module for a menu slug/title.
     */
    private static function module_for_slug( $slug, $title = '' ) {
        if ( self::is_sensitive_ufsc_slug( $slug ) ) {
            return null;
        }
        if ( self::is_competitions_slug( $slug, $title ) ) {
            return 'competitions';
        }
        if ( self::is_licences_slug( $slug, $title ) ) {
            return 'licences';
        }
        if ( self::slug_matches( $slug, array( 'ufsc-limited-dashboard', 'ufsc-home' ) ) ) {
            return 'espace_ufsc';
        }
        if ( self::is_gestion_slug( $slug, $title ) ) {
            return 'gestion';
        }
        return null;
    }

    /**
     * Whether a URL points to the current WordPress admin.
     */
    private static function is_admin_url( $url ) {
        return 0 === strpos( wp_sanitize_redirect( (string) $url ), admin_url() );
    }

    /**
     * Whether a URL points to the front office of the current site.
     */
    private static function is_front_office_url( $url ) {
        $url  = wp_sanitize_redirect( (string) $url );
        $home = home_url( '/' );
        return 0 === strpos( $url, $home ) && 0 !== strpos( $url, admin_url() );
    }

    /**
     * First authorized URL for current user, with requested priority.
     */
    public static function get_first_authorized_url( $user = null ) {
        if ( null === $user ) {
            $user = wp_get_current_user();
        }
        return self::get_first_authorized_url_for_user( $user );
    }

    /**
     * First authorized URL for a user, with requested priority.
     *
     * @param WP_User|int $user User object or ID.
     */
    public static function get_first_authorized_url_for_user( $user ) {
        $user = $user instanceof WP_User ? $user : get_userdata( absint( $user ) );
        if ( ! $user ) {
            return admin_url( 'profile.php' );
        }

        if ( self::is_limited_ufsc_user( $user->ID ) ) {
            return admin_url( 'admin.php?page=ufsc-limited-dashboard' );
        }

        return admin_url( 'profile.php' );
    }


    /**
     * Central source of truth for simplified UFSC admin URLs.
     *
     * @return array<string,string>
     */
    public static function get_module_urls() {
        static $urls = null;
        if ( null !== $urls ) {
            return $urls;
        }

        $urls = array(
            'dashboard'   => admin_url( 'admin.php?page=ufsc-limited-dashboard' ),
            'espace_ufsc' => admin_url( 'admin.php?page=ufsc-limited-dashboard' ),
            'articles'    => admin_url( 'edit.php' ),
            'gestion'     => self::get_first_candidate_admin_url( 'gestion' ),
            'clubs'       => self::get_first_existing_slug_url( array( 'ufsc-clubs', 'ufsc-sql-clubs', 'ufsc_clubs' ), 'admin.php?page=ufsc-clubs' ),
            'licences'    => self::get_licences_admin_url(),
            'competitions'=> self::get_first_candidate_admin_url( 'competitions' ),
            'profile'     => admin_url( 'profile.php' ),
            'profil'      => admin_url( 'profile.php' ),
        );

        return $urls;
    }


    /**
     * The canonical admin licences table URL, never a front-office/club dashboard URL.
     */
    private static function get_licences_admin_url() {
        return self::get_first_existing_slug_url(
            array( 'ufsc-sql-licences', 'ufsc-licences', 'ufsc_lc_licences', 'ufsc-sql-licenses' ),
            'admin.php?page=ufsc-sql-licences'
        );
    }

    /**
     * Technical diagnostics for administrators and support tooling.
     *
     * @return array<string,array<string,string>>
     */
    public static function get_module_url_diagnostics() {
        $urls = self::get_module_urls();
        return array(
            'licences' => array(
                'slug'       => self::extract_page_from_admin_url( $urls['licences'] ),
                'url'        => $urls['licences'],
                'capability' => UFSC_Permissions::CAP_LICENCES_READ,
                'reason'     => __( 'Priorité au tableau admin SQL des licences (ufsc-sql-licences), puis aux aliases admin connus.', 'ufsc-clubs' ),
            ),
            'gestion' => array(
                'slug'       => self::extract_page_from_admin_url( $urls['gestion'] ),
                'url'        => $urls['gestion'],
                'capability' => UFSC_Permissions::CAP_GESTION_READ,
                'reason'     => __( 'Menu UFSC Gestion détecté ou fallback dashboard.', 'ufsc-clubs' ),
            ),
            'competitions' => array(
                'slug'       => self::extract_page_from_admin_url( $urls['competitions'] ),
                'url'        => $urls['competitions'],
                'capability' => UFSC_Permissions::CAP_COMPETITIONS_READ,
                'reason'     => __( 'Menu Compétitions détecté ou alias admin de compatibilité.', 'ufsc-clubs' ),
            ),
        );
    }

    /**
     * Extract the page query arg from an admin URL for diagnostics.
     */
    private static function extract_page_from_admin_url( $url ) {
        $query = wp_parse_url( (string) $url, PHP_URL_QUERY );
        if ( ! $query ) {
            return '';
        }
        $args = array();
        wp_parse_str( $query, $args );
        return isset( $args['page'] ) ? sanitize_key( (string) $args['page'] ) : '';
    }

    /**
     * First URL for an already registered slug in a preferred list.
     */
    private static function get_first_existing_slug_url( array $slugs, $fallback_path ) {
        foreach ( $slugs as $slug ) {
            if ( self::menu_slug_exists( $slug ) ) {
                return admin_url( 'admin.php?page=' . rawurlencode( self::normalize_page_slug( $slug ) ) );
            }
        }

        return admin_url( $fallback_path );
    }

    /**
     * Detect whether a visible top-level menu for a module already exists.
     */
    private static function has_visible_module_menu( $module ) {
        global $menu;
        $module = sanitize_key( (string) $module );
        foreach ( (array) $menu as $item ) {
            $slug  = isset( $item[2] ) ? (string) $item[2] : '';
            $title = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
            if ( $module === self::module_for_slug( $slug, $title ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect whether a top-level menu slug already exists.
     */
    private static function top_level_menu_slug_exists( $slug ) {
        global $menu;
        $slug = self::normalize_page_slug( $slug );
        foreach ( (array) $menu as $item ) {
            if ( isset( $item[2] ) && $slug === self::normalize_page_slug( (string) $item[2] ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sensitive submenu entries are hidden from limited users unless a manage capability explicitly allows them.
     */
    private static function is_sensitive_limited_submenu_item( $slug, $title = '' ) {
        if ( current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( self::is_sensitive_ufsc_slug( $slug ) ) {
            return true;
        }

        $title = strtolower( remove_accents( wp_strip_all_tags( (string) $title ) ) );
        foreach ( array( 'parametre', 'reglage', 'droits', 'acces' ) as $needle ) {
            if ( false !== strpos( $title, $needle ) ) {
                return true;
            }
        }

        $normalized_slug = self::normalize_page_slug( $slug );
        if ( false !== strpos( $title, 'export' ) || false !== strpos( $normalized_slug, 'export' ) ) {
            return ! ( current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) || current_user_can( UFSC_Permissions::CAP_LICENCES_MANAGE ) );
        }

        if ( false !== strpos( $title, 'import' ) || false !== strpos( $title, 'asptt' ) ) {
            if ( self::is_competitions_slug( $slug, $title ) && current_user_can( UFSC_Permissions::CAP_COMPETITIONS_MANAGE ) ) {
                return false;
            }

            return ! current_user_can( UFSC_Permissions::CAP_LICENCES_MANAGE );
        }

        return false;
    }

    /**
     * Candidate admin URLs by UFSC module, ordered by preferred slug.
     *
     * @return string[]
     */
    public static function get_candidate_admin_urls( $module ) {
        $module = sanitize_key( (string) $module );
        $paths  = array(
            'competitions' => array(
                'admin.php?page=ufsc-licence-competition',
                'admin.php?page=ufsc-competitions',
                'admin.php?page=ufsc_competitions',
                'admin.php?page=ufsc-competition',
                'admin.php?page=ufsc_competition',
                'admin.php?page=ufsc-competition-dashboard',
                'admin.php?page=ufsc_competition_dashboard',
            ),
            'licences' => array(
                'admin.php?page=ufsc-sql-licences',
                'admin.php?page=ufsc-licences',
                'admin.php?page=ufsc_lc_licences',
                'admin.php?page=ufsc-sql-licenses',
                'admin.php?page=ufsc_licences',
                'admin.php?page=ufsc-licence',
                'admin.php?page=ufsc_licence',
                'admin.php?page=ufsc-licences-dashboard',
                'admin.php?page=ufsc_lc',
            ),
            'gestion' => array(
                'admin.php?page=ufsc-gestion',
                'admin.php?page=ufsc_gestion',
                'admin.php?page=ufsc-clubs',
                'admin.php?page=ufsc_clubs',
                'admin.php?page=ufsc-dashboard',
            ),
        );

        $paths = isset( $paths[ $module ] ) ? $paths[ $module ] : array( 'profile.php' );
        $paths = apply_filters( 'ufsc_simplified_admin_candidate_paths', $paths, $module );

        return array_map( 'admin_url', array_values( array_filter( array_map( 'strval', (array) $paths ) ) ) );
    }

    /**
     * First candidate admin URL for a module.
     */
    private static function get_first_candidate_admin_url( $module ) {
        $detected = self::get_detected_ufsc_menus( $module );
        if ( ! empty( $detected ) ) {
            foreach ( $detected as $item ) {
                if ( 'menu' === $item['type'] && ! empty( $item['slug'] ) ) {
                    return admin_url( 'admin.php?page=' . rawurlencode( $item['slug'] ) );
                }
            }
            foreach ( $detected as $item ) {
                if ( 'submenu' === $item['type'] && ! empty( $item['parent'] ) && ! empty( $item['slug'] ) ) {
                    return admin_url( 'admin.php?page=' . rawurlencode( $item['slug'] ) );
                }
            }
        }

        $urls = self::get_candidate_admin_urls( $module );
        return $urls ? reset( $urls ) : admin_url( 'profile.php' );
    }

    /**
     * Best-known UFSC Licences URL.
     */
    private static function get_licences_url() {
        return self::get_first_candidate_admin_url( 'licences' );
    }

    /**
     * Best-known UFSC Compétitions URL.
     */
    private static function get_competitions_url() {
        return self::get_first_candidate_admin_url( 'competitions' );
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
