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

        add_action( 'wp_login_failed', array( __CLASS__, 'handle_login_failed' ) );

        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    /**
     * Enqueue assets for authentication shortcodes conditionally
     */
    public static function enqueue_assets() {
        $post = get_post();
        if ( ! $post ) {
            return;
        }

        $content = $post->post_content;
        $has_login  = has_shortcode( $content, 'ufsc_login_form' );
        $has_status = has_shortcode( $content, 'ufsc_user_status' );

        if ( $has_login || $has_status ) {
            wp_enqueue_style( 'ufsc-front', UFSC_CL_URL . 'assets/css/ufsc-front.css', array(), UFSC_CL_VERSION );
        }

        // Login form styles are included in ufsc-front.css
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


        $error_message = '';
        if ( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) {
            $error_message = __( 'Identifiant ou mot de passe incorrect.', 'ufsc-clubs' );
        }

        $username_error = '';
        $password_error = '';
        if ( isset( $_GET['login_error'] ) ) {
            $error = sanitize_text_field( wp_unslash( $_GET['login_error'] ) );
            if ( 'empty_username' === $error ) {
                $username_error = __( 'Veuillez saisir votre identifiant.', 'ufsc-clubs' );
            } elseif ( 'empty_password' === $error ) {
                $password_error = __( 'Veuillez saisir votre mot de passe.', 'ufsc-clubs' );
            } elseif ( 'invalid' === $error ) {
                $username_error = __( 'Identifiants invalides.', 'ufsc-clubs' );
                $password_error = __( 'Identifiants invalides.', 'ufsc-clubs' );
            }

        }

        ob_start();
        ?>
        <div class="ufsc-front ufsc-full">
        <div class="<?php echo esc_attr( $atts['class'] ); ?>">

            <div class="ufsc-card ufsc-col-span-2 ufsc-login-card">
                <?php if ( ! empty( $atts['title'] ) ): ?>
                    <h3 class="ufsc-login-title"><?php echo esc_html( $atts['title'] ); ?></h3>
                <?php endif; ?>

                <div class="ufsc-notices" aria-live="polite">
                    <?php if ( $error_message ) : ?>
                        <div class="ufsc-alert ufsc-alert-error"><?php echo esc_html( $error_message ); ?></div>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" class="ufsc-form ufsc-login-form">
                    <?php wp_nonce_field( 'ufsc_login', 'ufsc_login_nonce' ); ?>

                    <input type="hidden" name="redirect_to" value="<?php echo esc_url( self::get_dynamic_redirect_url( $atts ) ); ?>" />

                    <div class="ufsc-field">
                        <label for="user_login"><?php echo esc_html__( 'Nom d\'utilisateur ou email', 'ufsc-clubs' ); ?></label>
                        <input type="text" name="log" id="user_login" required autocomplete="username" aria-describedby="user_login-help user_login-error" />
                        <p class="ufsc-field-help" id="user_login-help"><?php echo esc_html__( 'Entrez votre identifiant ou votre adresse email.', 'ufsc-clubs' ); ?></p>
                        <p class="ufsc-field-error" id="user_login-error"><?php echo esc_html( $error_message ); ?></p>
                    </div>

                    <div class="ufsc-field">
                        <label for="user_pass"><?php echo esc_html__( 'Mot de passe', 'ufsc-clubs' ); ?></label>
                        <input type="password" name="pwd" id="user_pass" required autocomplete="current-password" aria-describedby="user_pass-help user_pass-error" />
                        <p class="ufsc-field-help" id="user_pass-help"><?php echo esc_html__( 'Entrez votre mot de passe.', 'ufsc-clubs' ); ?></p>
                        <p class="ufsc-field-error" id="user_pass-error"><?php echo esc_html( $error_message ); ?></p>
                    </div>

                    <div class="ufsc-field ufsc-remember">
                        <input type="checkbox" name="rememberme" id="rememberme" value="forever" />
                        <label for="rememberme"><?php echo esc_html__( 'Se souvenir de moi', 'ufsc-clubs' ); ?></label>
                    </div>

                    <div class="ufsc-login-actions">
                        <button type="submit" class="ufsc-btn ufsc-btn-primary">
                            <?php echo esc_html__( 'Se connecter', 'ufsc-clubs' ); ?>
                        </button>
                    </div>

                    <?php if ( $atts['show_lost_password'] === 'true' ): ?>
                        <div class="ufsc-login-links">
                            <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="ufsc-link-lost-password">
                                <?php echo esc_html__( 'Mot de passe oublié ?', 'ufsc-clubs' ); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ( $atts['show_register'] === 'true' && get_option( 'users_can_register' ) ): ?>
                        <div class="ufsc-login-links">
                            <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="ufsc-link-register">
                                <?php echo esc_html__( 'Créer un compte', 'ufsc-clubs' ); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        </div>


        <?php
        return ob_get_clean();
    }

    /**
     * Redirect back to form on failed login
     */
    public static function handle_login_failed() {
        $referrer = wp_get_referer();
        if ( $referrer && false === strpos( (string) ( $referrer ?? '' ), 'wp-login.php' ) && false === strpos( (string) ( $referrer ?? '' ), 'wp-admin' ) ) {
            wp_safe_redirect( add_query_arg( 'login', 'failed', $referrer ) );
            exit;
        }
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
            '<div class="ufsc-front ufsc-full"><a href="%s" class="%s" onclick="%s">%s</a></div>',
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
            return '<div class="ufsc-front ufsc-full"><div class="ufsc-user-status ufsc-not-logged-in">' .
                   esc_html__( 'Non connecté', 'ufsc-clubs' ) .
                   '</div></div>';
        }

        $user = wp_get_current_user();
        $club = ufsc_get_user_club( $user->ID );

        ob_start();
        ?>
        <div class="ufsc-front ufsc-full">
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
        </div>
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
            '<div class="ufsc-front ufsc-full"><div class="ufsc-already-logged-in">
                <p>%s <strong>%s</strong></p>
                <p><a href="%s" class="ufsc-btn ufsc-btn-primary">%s</a></p>
            </div></div>',
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


/**
 * Redirect newly registered users to the club creation page.
 *
 * @param string $redirect_to Default redirect URL.
 * @return string Modified redirect URL.
 */
function ufsc_handle_registration_form( $redirect_to ) {
    return home_url( '/creation-du-club/' );
}
add_filter( 'registration_redirect', 'ufsc_handle_registration_form' );

