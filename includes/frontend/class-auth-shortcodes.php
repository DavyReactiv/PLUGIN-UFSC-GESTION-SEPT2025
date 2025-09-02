<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Authentication Shortcodes
 * Provides login/logout functionality with role-aware redirects
 */
class UFSC_Auth_Shortcodes {

    /**
     * Register authentication shortcodes
     */
    public static function register() {
        add_shortcode( 'ufsc_login_form', array( __CLASS__, 'render_login_form' ) );
        add_shortcode( 'ufsc_logout_button', array( __CLASS__, 'render_logout_button' ) );
        add_shortcode( 'ufsc_user_status', array( __CLASS__, 'render_user_status' ) );
    }

    /**
     * Render login form with role-aware redirects
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_login_form( $atts = array() ) {
        $atts = shortcode_atts( array(
            'redirect_admin' => admin_url( 'admin.php?page=ufsc-gestion' ),
            'redirect_club' => home_url( '/club-dashboard/' ),
            'redirect_default' => home_url(),
            'show_register' => 'true',
            'show_lost_password' => 'true',
            'title' => __( 'Connexion', 'ufsc-clubs' ),
            'class' => 'ufsc-login-form'
        ), $atts, 'ufsc_login_form' );

        if ( is_user_logged_in() ) {
            return self::render_already_logged_in();
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr( $atts['class'] ); ?>">
            <?php if ( ! empty( $atts['title'] ) ): ?>
                <h3 class="ufsc-login-title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" class="ufsc-login-form-inner">
                <?php wp_nonce_field( 'ufsc_login', 'ufsc_login_nonce' ); ?>
                
                <input type="hidden" name="redirect_to" value="<?php echo esc_url( self::get_dynamic_redirect_url( $atts ) ); ?>" />
                
                <div class="ufsc-form-group">
                    <label for="user_login"><?php echo esc_html__( 'Nom d\'utilisateur ou email', 'ufsc-clubs' ); ?></label>
                    <input type="text" name="log" id="user_login" class="ufsc-form-control" required autocomplete="username" />
                </div>

                <div class="ufsc-form-group">
                    <label for="user_pass"><?php echo esc_html__( 'Mot de passe', 'ufsc-clubs' ); ?></label>
                    <input type="password" name="pwd" id="user_pass" class="ufsc-form-control" required autocomplete="current-password" />
                </div>

                <div class="ufsc-form-group ufsc-form-checkbox">
                    <label>
                        <input type="checkbox" name="rememberme" value="forever" />
                        <?php echo esc_html__( 'Se souvenir de moi', 'ufsc-clubs' ); ?>
                    </label>
                </div>

                <div class="ufsc-form-group ufsc-form-submit">
                    <button type="submit" class="ufsc-btn ufsc-btn-primary">
                        <?php echo esc_html__( 'Se connecter', 'ufsc-clubs' ); ?>
                    </button>
                </div>

                <?php if ( $atts['show_lost_password'] === 'true' ): ?>
                    <div class="ufsc-form-links">
                        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="ufsc-link-lost-password">
                            <?php echo esc_html__( 'Mot de passe oublié ?', 'ufsc-clubs' ); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ( $atts['show_register'] === 'true' && get_option( 'users_can_register' ) ): ?>
                    <div class="ufsc-form-links">
                        <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="ufsc-link-register">
                            <?php echo esc_html__( 'Créer un compte', 'ufsc-clubs' ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <style>
        .ufsc-login-form {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
        }
        .ufsc-login-title {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        .ufsc-form-group {
            margin-bottom: 15px;
        }
        .ufsc-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        .ufsc-form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .ufsc-form-control:focus {
            border-color: #0073aa;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.2);
        }
        .ufsc-form-checkbox label {
            display: inline;
            font-weight: normal;
            margin-left: 5px;
        }
        .ufsc-btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .ufsc-btn-primary {
            background-color: #0073aa;
            color: #fff;
            width: 100%;
        }
        .ufsc-btn-primary:hover {
            background-color: #005a87;
        }
        .ufsc-form-links {
            text-align: center;
            margin-top: 10px;
        }
        .ufsc-form-links a {
            color: #0073aa;
            text-decoration: none;
            font-size: 13px;
        }
        .ufsc-form-links a:hover {
            text-decoration: underline;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Render logout button
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_logout_button( $atts = array() ) {
        $atts = shortcode_atts( array(
            'text' => __( 'Se déconnecter', 'ufsc-clubs' ),
            'redirect' => home_url(),
            'class' => 'ufsc-logout-button',
            'confirm' => 'true'
        ), $atts, 'ufsc_logout_button' );

        if ( ! is_user_logged_in() ) {
            return '';
        }

        $logout_url = wp_logout_url( $atts['redirect'] );
        $onclick = $atts['confirm'] === 'true' ? 
            'return confirm(\'' . esc_js( __( 'Êtes-vous sûr de vouloir vous déconnecter ?', 'ufsc-clubs' ) ) . '\')' : 
            '';

        return sprintf(
            '<a href="%s" class="%s" onclick="%s">%s</a>',
            esc_url( $logout_url ),
            esc_attr( $atts['class'] ),
            $onclick,
            esc_html( $atts['text'] )
        );
    }

    /**
     * Render user status information
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_user_status( $atts = array() ) {
        $atts = shortcode_atts( array(
            'show_avatar' => 'true',
            'show_role' => 'true',
            'show_club' => 'true',
            'show_logout' => 'true',
            'avatar_size' => '32'
        ), $atts, 'ufsc_user_status' );

        if ( ! is_user_logged_in() ) {
            return '<div class="ufsc-user-status ufsc-not-logged-in">' . 
                   esc_html__( 'Non connecté', 'ufsc-clubs' ) . 
                   '</div>';
        }

        $user = wp_get_current_user();
        $club = ufsc_get_user_club( $user->ID );

        ob_start();
        ?>
        <div class="ufsc-user-status ufsc-logged-in">
            <?php if ( $atts['show_avatar'] === 'true' ): ?>
                <div class="ufsc-user-avatar">
                    <?php echo get_avatar( $user->ID, $atts['avatar_size'] ); ?>
                </div>
            <?php endif; ?>

            <div class="ufsc-user-info">
                <div class="ufsc-user-name">
                    <strong><?php echo esc_html( $user->display_name ); ?></strong>
                </div>

                <?php if ( $atts['show_role'] === 'true' ): ?>
                    <div class="ufsc-user-role">
                        <?php echo esc_html( ucfirst( implode( ', ', $user->roles ) ) ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( $atts['show_club'] === 'true' && $club ): ?>
                    <div class="ufsc-user-club">
                        <small><?php echo esc_html( $club->nom ); ?></small>
                        <?php echo UFSC_Badge_Helper::render_region_badge( $club->region ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( $atts['show_logout'] === 'true' ): ?>
                    <div class="ufsc-user-actions">
                        <?php echo self::render_logout_button( array( 'text' => __( 'Déconnexion', 'ufsc-clubs' ) ) ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
        .ufsc-user-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
        .ufsc-user-status.ufsc-not-logged-in {
            text-align: center;
            color: #6c757d;
        }
        .ufsc-user-avatar img {
            border-radius: 50%;
        }
        .ufsc-user-info {
            flex: 1;
        }
        .ufsc-user-name {
            margin-bottom: 2px;
        }
        .ufsc-user-role, .ufsc-user-club {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 2px;
        }
        .ufsc-user-actions {
            margin-top: 5px;
        }
        .ufsc-logout-button {
            font-size: 12px;
            color: #dc3545;
            text-decoration: none;
        }
        .ufsc-logout-button:hover {
            text-decoration: underline;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Get already logged in message
     */
    private static function render_already_logged_in() {
        $user = wp_get_current_user();
        $dashboard_url = self::get_user_dashboard_url( $user );

        return sprintf(
            '<div class="ufsc-already-logged-in">
                <p>%s <strong>%s</strong></p>
                <p><a href="%s" class="ufsc-btn ufsc-btn-primary">%s</a></p>
            </div>',
            esc_html__( 'Vous êtes déjà connecté en tant que', 'ufsc-clubs' ),
            esc_html( $user->display_name ),
            esc_url( $dashboard_url ),
            esc_html__( 'Accéder au tableau de bord', 'ufsc-clubs' )
        );
    }

    /**
     * Get dynamic redirect URL based on user role
     */
    private static function get_dynamic_redirect_url( $atts ) {
        // For login form, we need to determine redirect after login
        // We'll use a custom login redirect hook
        add_filter( 'login_redirect', array( __CLASS__, 'handle_login_redirect' ), 10, 3 );
        
        return $atts['redirect_default'];
    }

    /**
     * Handle login redirect based on user role
     */
    public static function handle_login_redirect( $redirect_to, $request, $user ) {
        if ( ! is_wp_error( $user ) ) {
            // Admin users go to admin dashboard
            if ( user_can( $user, 'manage_options' ) ) {
                return admin_url( 'admin.php?page=ufsc-gestion' );
            }
            
            // Club managers go to club dashboard
            $club_id = ufsc_get_user_club_id( $user->ID );
            if ( $club_id ) {
                return home_url( '/club-dashboard/' );
            }
            
            // Regular users go to homepage
            return home_url();
        }
        
        return $redirect_to;
    }

    /**
     * Get appropriate dashboard URL for user
     */
    private static function get_user_dashboard_url( $user ) {
        if ( user_can( $user, 'manage_options' ) ) {
            return admin_url( 'admin.php?page=ufsc-gestion' );
        }
        
        $club_id = ufsc_get_user_club_id( $user->ID );
        if ( $club_id ) {
            return home_url( '/club-dashboard/' );
        }
        
        return home_url();
    }
}