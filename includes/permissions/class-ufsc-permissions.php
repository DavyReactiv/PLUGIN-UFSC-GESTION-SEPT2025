<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Central UFSC permissions, roles and regional access registry.
 */
class UFSC_Permissions {
    const META_ALLOWED_REGIONS = '_ufsc_allowed_regions';
    const META_ALL_REGIONS     = '_ufsc_all_regions_access';

    const CAP_GESTION_READ          = 'ufsc_gestion_read';
    const CAP_GESTION_MANAGE        = 'ufsc_gestion_manage';
    const CAP_LICENCES_READ         = 'ufsc_licences_read';
    const CAP_LICENCES_MANAGE       = 'ufsc_licences_manage';
    const CAP_COMPETITIONS_READ     = 'ufsc_competitions_read';
    const CAP_COMPETITIONS_MANAGE   = 'ufsc_competitions_manage';
    const CAP_SETTINGS_MANAGE       = 'ufsc_settings_manage';
    const CAP_REGIONS_MANAGE        = 'ufsc_regions_manage';
    const CAP_ALL_REGIONS_ACCESS    = 'ufsc_all_regions_access';

    /**
     * Bootstrap hooks.
     */
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'maybe_register_roles_and_caps' ) );
        add_action( 'admin_menu', array( __CLASS__, 'register_permissions_page' ), 30 );
    }

    /**
     * Idempotent role/capability registration.
     */
    public static function maybe_register_roles_and_caps() {
        $version = '2026-05-29-1';
        if ( get_option( 'ufsc_permissions_caps_version' ) === $version ) {
            return;
        }

        self::register_roles_and_caps();
        update_option( 'ufsc_permissions_caps_version', $version, false );
    }

    /**
     * Register roles and capabilities without removing existing custom grants.
     */
    public static function register_roles_and_caps() {
        $role_caps = self::get_role_capabilities();

        foreach ( $role_caps as $role_key => $config ) {
            $role = get_role( $role_key );
            if ( ! $role ) {
                add_role( $role_key, $config['label'], $config['caps'] );
                $role = get_role( $role_key );
            }

            if ( $role ) {
                foreach ( $config['caps'] as $cap => $grant ) {
                    if ( $grant ) {
                        $role->add_cap( $cap );
                    }
                }
            }
        }

        $administrator = get_role( 'administrator' );
        if ( $administrator ) {
            foreach ( self::get_capabilities() as $cap ) {
                $administrator->add_cap( $cap );
            }
        }
    }

    /**
     * @return string[]
     */
    public static function get_capabilities() {
        return array(
            self::CAP_GESTION_READ,
            self::CAP_GESTION_MANAGE,
            self::CAP_LICENCES_READ,
            self::CAP_LICENCES_MANAGE,
            self::CAP_COMPETITIONS_READ,
            self::CAP_COMPETITIONS_MANAGE,
            self::CAP_SETTINGS_MANAGE,
            self::CAP_REGIONS_MANAGE,
            self::CAP_ALL_REGIONS_ACCESS,
        );
    }

    /**
     * @return array<string,array{label:string,caps:array<string,bool>}>
     */
    public static function get_role_capabilities() {
        return array(
            'ufsc_region_viewer' => array(
                'label' => __( 'Lecture régionale', 'ufsc-clubs' ),
                'caps'  => array(
                    'read' => true,
                    self::CAP_GESTION_READ  => true,
                    self::CAP_LICENCES_READ => true,
                ),
            ),
            'ufsc_region_manager' => array(
                'label' => __( 'Gestion régionale', 'ufsc-clubs' ),
                'caps'  => array(
                    'read' => true,
                    self::CAP_GESTION_READ    => true,
                    self::CAP_GESTION_MANAGE  => true,
                    self::CAP_LICENCES_READ   => true,
                    self::CAP_LICENCES_MANAGE => true,
                ),
            ),
            'ufsc_competition_manager' => array(
                'label' => __( 'Gestion compétitions', 'ufsc-clubs' ),
                'caps'  => array(
                    'read' => true,
                    self::CAP_GESTION_READ          => true,
                    self::CAP_LICENCES_READ         => true,
                    self::CAP_COMPETITIONS_READ     => true,
                    self::CAP_COMPETITIONS_MANAGE   => true,
                ),
            ),
            'ufsc_admin_limited' => array(
                'label' => __( 'Admin UFSC limité', 'ufsc-clubs' ),
                'caps'  => array(
                    'read' => true,
                    self::CAP_GESTION_READ          => true,
                    self::CAP_LICENCES_READ         => true,
                    self::CAP_COMPETITIONS_READ     => true,
                    self::CAP_COMPETITIONS_MANAGE   => true,
                ),
            ),
        );
    }

    /**
     * Check the immutable WordPress administrator role/capability.
     */
    public static function is_wordpress_administrator( $user_id = null ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        $user = get_userdata( $user_id );
        return ( $user && in_array( 'administrator', (array) $user->roles, true ) ) || user_can( $user_id, 'manage_options' );
    }

    /**
     * Whether the current user may open the permissions page.
     */
    public static function current_user_can_manage_permissions( $user_id = null ) {
        unset( $user_id );
        return current_user_can( 'manage_options' );
    }

    /**
     * Add admin submenu.
     */
    public static function register_permissions_page() {
        add_submenu_page(
            'ufsc-dashboard',
            __( 'Droits & accès', 'ufsc-clubs' ),
            __( 'Droits & accès', 'ufsc-clubs' ),
            'manage_options',
            'ufsc-permissions',
            array( __CLASS__, 'render_permissions_page' )
        );
    }

    /**
     * Render and handle the permissions page.
     */
    public static function render_permissions_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé : seuls les administrateurs WordPress peuvent gérer les droits UFSC.', 'ufsc-clubs' ) );
        }

        $notice = self::maybe_handle_permissions_save();
        $selected_user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
        if ( ! $selected_user_id ) {
            $selected_user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
        }

        echo '<div class="wrap ufsc-permissions-page">';
        echo '<h1>' . esc_html__( 'Droits & accès UFSC', 'ufsc-clubs' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Les restrictions UFSC sont vérifiées côté serveur. Le masquage des boutons n’est qu’un confort visuel.', 'ufsc-clubs' ) . '</p>';

        $simplified_notice = self::maybe_handle_simplified_admin_save();
        if ( $simplified_notice ) {
            $notice = $simplified_notice;
        }

        if ( $notice ) {
            printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notice['type'] ), esc_html( $notice['message'] ) );
        }

        self::render_simplified_admin_option();
        self::render_users_table( $selected_user_id );

        if ( $selected_user_id ) {
            self::render_user_permissions_form( $selected_user_id );
        }

        echo '</div>';
    }

    /**
     * Save global simplified admin interface option.
     */
    private static function maybe_handle_simplified_admin_save() {
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['ufsc_simplified_admin_action'] ) ) {
            return null;
        }

        check_admin_referer( 'ufsc_save_simplified_admin', 'ufsc_simplified_admin_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé : seuls les administrateurs WordPress peuvent modifier cette option.', 'ufsc-clubs' ) );
        }

        update_option( 'ufsc_enable_simplified_admin', ! empty( $_POST['ufsc_enable_simplified_admin'] ) ? '1' : '0', false );

        return array( 'type' => 'success', 'message' => __( 'Option d’interface admin simplifiée sauvegardée.', 'ufsc-clubs' ) );
    }

    /**
     * Render the global simplified admin interface option for WordPress administrators.
     */
    private static function render_simplified_admin_option() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $enabled = '0' !== (string) get_option( 'ufsc_enable_simplified_admin', '1' );

        echo '<h2>' . esc_html__( 'Interface admin simplifiée', 'ufsc-clubs' ) . '</h2>';
        echo '<form method="post" class="ufsc-simplified-admin-settings" style="max-width:900px;margin-bottom:24px;padding:16px;background:#fff;border:1px solid #dcdcde;">';
        wp_nonce_field( 'ufsc_save_simplified_admin', 'ufsc_simplified_admin_nonce' );
        echo '<input type="hidden" name="ufsc_simplified_admin_action" value="save" />';
        echo '<label><input type="checkbox" name="ufsc_enable_simplified_admin" value="1" ' . checked( $enabled, true, false ) . '> ' . esc_html__( 'Activer l’interface admin simplifiée pour les utilisateurs UFSC limités', 'ufsc-clubs' ) . '</label>';
        echo '<p class="description">' . esc_html__( 'Activée par défaut. Cette option masque les menus inutiles et redirige les utilisateurs UFSC limités, sans remplacer les contrôles serveur.', 'ufsc-clubs' ) . '</p>';
        submit_button( __( 'Enregistrer cette option', 'ufsc-clubs' ), 'secondary', 'submit', false );
        echo '</form>';
    }

    /**
     * Save submitted permissions.
     */
    private static function maybe_handle_permissions_save() {
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['ufsc_permissions_action'] ) ) {
            return null;
        }

        check_admin_referer( 'ufsc_save_user_permissions', 'ufsc_permissions_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé : seuls les administrateurs WordPress peuvent gérer les droits UFSC.', 'ufsc-clubs' ) );
        }

        $target_user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
        $target_user    = $target_user_id ? get_userdata( $target_user_id ) : false;
        if ( ! $target_user ) {
            return array( 'type' => 'error', 'message' => __( 'Utilisateur introuvable.', 'ufsc-clubs' ) );
        }

        $current_user_id = get_current_user_id();
        $is_admin        = current_user_can( 'manage_options' );
        $target_is_admin = user_can( $target_user_id, 'manage_options' ) || in_array( 'administrator', (array) $target_user->roles, true );

        if ( ! $is_admin && $target_is_admin ) {
            return array( 'type' => 'error', 'message' => __( 'Seul un administrateur WordPress peut modifier un compte administrateur.', 'ufsc-clubs' ) );
        }

        $submitted_caps = isset( $_POST['ufsc_caps'] ) && is_array( $_POST['ufsc_caps'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['ufsc_caps'] ) ) : array();
        $submitted_caps = array_values( array_intersect( $submitted_caps, self::get_capabilities() ) );
        $sensitive_caps = array( self::CAP_SETTINGS_MANAGE, self::CAP_REGIONS_MANAGE, self::CAP_ALL_REGIONS_ACCESS );

        if ( ! $is_admin && $target_user_id === $current_user_id && array_intersect( $submitted_caps, $sensitive_caps ) ) {
            return array( 'type' => 'error', 'message' => __( 'Vous ne pouvez pas vous attribuer vous-même des droits UFSC sensibles.', 'ufsc-clubs' ) );
        }

        if ( ! $is_admin && $target_user_id === $current_user_id ) {
            $existing_sensitive = array_filter( $sensitive_caps, static function( $cap ) use ( $target_user_id ) {
                return user_can( $target_user_id, $cap );
            } );
            $submitted_caps = array_values( array_unique( array_merge( $submitted_caps, $existing_sensitive ) ) );
        }

        foreach ( self::get_capabilities() as $cap ) {
            if ( in_array( $cap, $submitted_caps, true ) ) {
                $target_user->add_cap( $cap );
            } else {
                $target_user->remove_cap( $cap );
            }
        }

        $regions = isset( $_POST['ufsc_allowed_regions'] ) && is_array( $_POST['ufsc_allowed_regions'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ufsc_allowed_regions'] ) ) : array();
        ufsc_set_user_regions( $target_user_id, $regions );

        $all_regions = ! empty( $_POST['ufsc_all_regions_access'] ) ? '1' : '0';
        update_user_meta( $target_user_id, self::META_ALL_REGIONS, $all_regions );

        return array( 'type' => 'success', 'message' => __( 'Droits UFSC sauvegardés.', 'ufsc-clubs' ) );
    }

    /**
     * Render UFSC users table.
     */
    private static function render_users_table( $selected_user_id ) {
        $users = self::get_ufsc_users();

        echo '<h2>' . esc_html__( 'Utilisateurs UFSC', 'ufsc-clubs' ) . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__( 'Utilisateur', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Rôles', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Droits UFSC', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Régions autorisées', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Action', 'ufsc-clubs' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $users ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'Aucun utilisateur UFSC trouvé.', 'ufsc-clubs' ) . '</td></tr>';
        }

        foreach ( $users as $user ) {
            $caps    = self::get_user_ufsc_caps( $user->ID );
            $regions = ufsc_user_has_all_regions_access( $user->ID ) ? array( __( 'Toutes les régions', 'ufsc-clubs' ) ) : ufsc_get_user_regions( $user->ID );
            $url     = add_query_arg( array( 'page' => 'ufsc-permissions', 'user_id' => (int) $user->ID ), admin_url( 'admin.php' ) );
            echo '<tr' . ( (int) $selected_user_id === (int) $user->ID ? ' class="active"' : '' ) . '>';
            echo '<td><strong>' . esc_html( $user->display_name ) . '</strong><br><code>' . esc_html( $user->user_login ) . '</code></td>';
            echo '<td>' . esc_html( implode( ', ', (array) $user->roles ) ) . '</td>';
            echo '<td>' . wp_kses_post( self::render_cap_badges( $caps ) ) . '</td>';
            echo '<td>' . esc_html( implode( ', ', $regions ) ) . '</td>';
            echo '<td><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Modifier les droits', 'ufsc-clubs' ) . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Render form for one user.
     */
    private static function render_user_permissions_form( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $user_caps = self::get_user_ufsc_caps( $user_id );
        $regions   = ufsc_get_regions();
        $allowed   = ufsc_get_user_regions( $user_id );
        $all_access = ufsc_user_has_all_regions_access( $user_id );

        echo '<hr><h2>' . esc_html( sprintf( __( 'Modifier les droits de %s', 'ufsc-clubs' ), $user->display_name ) ) . '</h2>';
        echo '<form method="post" class="ufsc-permissions-form">';
        wp_nonce_field( 'ufsc_save_user_permissions', 'ufsc_permissions_nonce' );
        echo '<input type="hidden" name="ufsc_permissions_action" value="save" />';
        echo '<input type="hidden" name="user_id" value="' . esc_attr( (string) $user_id ) . '" />';

        echo '<h3>' . esc_html__( 'Capabilities UFSC', 'ufsc-clubs' ) . '</h3><div style="columns:2;max-width:900px;">';
        foreach ( self::get_capabilities() as $cap ) {
            echo '<label style="display:block;margin:6px 0;"><input type="checkbox" name="ufsc_caps[]" value="' . esc_attr( $cap ) . '" ' . checked( in_array( $cap, $user_caps, true ), true, false ) . '> <code>' . esc_html( $cap ) . '</code></label>';
        }
        echo '</div>';

        echo '<h3>' . esc_html__( 'Accès régional', 'ufsc-clubs' ) . '</h3>';
        echo '<label><input type="checkbox" name="ufsc_all_regions_access" value="1" ' . checked( $all_access, true, false ) . '> ' . esc_html__( 'Accès à toutes les régions', 'ufsc-clubs' ) . '</label>';
        echo '<div style="columns:2;max-width:900px;margin-top:10px;">';
        foreach ( $regions as $region ) {
            echo '<label style="display:block;margin:6px 0;"><input type="checkbox" name="ufsc_allowed_regions[]" value="' . esc_attr( $region ) . '" ' . checked( in_array( $region, $allowed, true ), true, false ) . '> ' . esc_html( $region ) . '</label>';
        }
        echo '</div>';

        submit_button( __( 'Enregistrer les droits', 'ufsc-clubs' ) );
        echo '</form>';
    }

    /**
     * @return WP_User[]
     */
    private static function get_ufsc_users() {
        $users      = get_users( array( 'fields' => 'all_with_meta' ) );
        $ufsc_roles = array_keys( self::get_role_capabilities() );

        return array_values( array_filter( $users, static function( $user ) use ( $ufsc_roles ) {
            if ( array_intersect( $ufsc_roles, (array) $user->roles ) ) {
                return true;
            }
            foreach ( self::get_capabilities() as $cap ) {
                if ( user_can( $user->ID, $cap ) ) {
                    return true;
                }
            }
            return false;
        } ) );
    }

    /**
     * @return string[]
     */
    private static function get_user_ufsc_caps( $user_id ) {
        return array_values( array_filter( self::get_capabilities(), static function( $cap ) use ( $user_id ) {
            return user_can( $user_id, $cap );
        } ) );
    }

    /**
     * @param string[] $caps
     * @return string
     */
    private static function render_cap_badges( array $caps ) {
        if ( empty( $caps ) ) {
            return '<span class="description">' . esc_html__( 'Aucun droit UFSC direct', 'ufsc-clubs' ) . '</span>';
        }

        $html = '';
        foreach ( $caps as $cap ) {
            $html .= '<span class="ufsc-badge" style="display:inline-block;margin:2px;padding:2px 6px;border-radius:10px;background:#eef5ff;color:#0a4b78;"><code>' . esc_html( $cap ) . '</code></span> ';
        }
        return $html;
    }
}

if ( ! function_exists( 'ufsc_user_can' ) ) {
    function ufsc_user_can( $capability, $user_id = null ) {
        $capability = sanitize_key( (string) $capability );
        $user_id    = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id || '' === $capability ) {
            return false;
        }
        if ( UFSC_Permissions::is_wordpress_administrator( $user_id ) ) {
            return true;
        }
        return user_can( $user_id, $capability );
    }
}

if ( ! function_exists( 'ufsc_get_regions' ) ) {
    function ufsc_get_regions() {
        $regions = array(
            'Auvergne-Rhône-Alpes',
            'Bourgogne-Franche-Comté',
            'Bretagne',
            'Centre-Val de Loire',
            'Corse',
            'Grand Est',
            'Hauts-de-France',
            'Île-de-France',
            'Normandie',
            'Nouvelle-Aquitaine',
            'Occitanie',
            'Pays de la Loire',
            'Provence-Alpes-Côte d’Azur',
            'Guadeloupe',
            'Martinique',
            'Guyane',
            'La Réunion',
            'Mayotte',
        );
        return apply_filters( 'ufsc_regions_list', $regions );
    }
}

if ( ! function_exists( 'ufsc_get_user_regions' ) ) {
    function ufsc_get_user_regions( $user_id = null ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id ) {
            return array();
        }
        $regions = get_user_meta( $user_id, UFSC_Permissions::META_ALLOWED_REGIONS, true );
        if ( ! is_array( $regions ) ) {
            $regions = array();
        }
        $valid = ufsc_get_regions();
        $clean = array_map( 'sanitize_text_field', $regions );
        return array_values( array_unique( array_intersect( $clean, $valid ) ) );
    }
}

if ( ! function_exists( 'ufsc_set_user_regions' ) ) {
    function ufsc_set_user_regions( $user_id, array $regions ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || ! get_userdata( $user_id ) ) {
            return false;
        }
        $valid = ufsc_get_regions();
        $clean = array_map( 'sanitize_text_field', $regions );
        $clean = array_values( array_unique( array_intersect( $clean, $valid ) ) );
        return false !== update_user_meta( $user_id, UFSC_Permissions::META_ALLOWED_REGIONS, $clean );
    }
}

if ( ! function_exists( 'ufsc_user_has_all_regions_access' ) ) {
    function ufsc_user_has_all_regions_access( $user_id = null ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        if ( UFSC_Permissions::is_wordpress_administrator( $user_id ) ) {
            return true;
        }
        if ( user_can( $user_id, UFSC_Permissions::CAP_ALL_REGIONS_ACCESS ) ) {
            return true;
        }
        return '1' === (string) get_user_meta( $user_id, UFSC_Permissions::META_ALL_REGIONS, true );
    }
}

if ( ! function_exists( 'ufsc_user_can_access_region' ) ) {
    function ufsc_user_can_access_region( $region, $user_id = null ) {
        $region = sanitize_text_field( (string) $region );
        if ( '' === $region || ! in_array( $region, ufsc_get_regions(), true ) ) {
            return false;
        }
        if ( ufsc_user_has_all_regions_access( $user_id ) ) {
            return true;
        }
        return in_array( $region, ufsc_get_user_regions( $user_id ), true );
    }
}

if ( ! function_exists( 'ufsc_current_user_allowed_regions' ) ) {
    function ufsc_current_user_allowed_regions() {
        if ( ufsc_user_has_all_regions_access() ) {
            return ufsc_get_regions();
        }
        return ufsc_get_user_regions();
    }
}

if ( ! function_exists( 'ufsc_is_region_allowed_for_current_user' ) ) {
    function ufsc_is_region_allowed_for_current_user( $region ) {
        return ufsc_user_can_access_region( $region, get_current_user_id() );
    }
}

if ( ! function_exists( 'ufsc_filter_query_by_allowed_regions' ) ) {
    function ufsc_filter_query_by_allowed_regions( $args, $region_field = 'region' ) {
        if ( ! is_array( $args ) ) {
            $args = array();
        }
        $region_field = sanitize_key( (string) $region_field );
        if ( '' === $region_field || ufsc_user_has_all_regions_access() ) {
            return $args;
        }

        $regions = ufsc_current_user_allowed_regions();
        if ( empty( $regions ) ) {
            $regions = array( '__ufsc_no_region_allowed__' );
        }

        if ( isset( $args['post_type'] ) || isset( $args['meta_query'] ) ) {
            if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
                $args['meta_query'] = array();
            }
            $args['meta_query'][] = array(
                'key'     => $region_field,
                'value'   => $regions,
                'compare' => 'IN',
            );
            return $args;
        }

        $args['ufsc_allowed_regions'] = $regions;
        $args['ufsc_region_field']    = $region_field;
        return $args;
    }
}
