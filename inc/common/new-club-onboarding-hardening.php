<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Production-safe hardening for the first affiliation journey.
 *
 * This module deliberately reuses the canonical annual affiliation state,
 * WooCommerce order bridge and honorability rules already present in the plugin.
 * It does not introduce a second affiliation, payment or honorability model.
 */
final class UFSC_New_Club_Onboarding_Hardening {
	private const DASHBOARD_SLUG = 'tableau-de-bord-club';
	private const AFFILIATION_SLUG = 'affiliation-club';

	public static function init() {
		add_filter( 'registration_redirect', array( __CLASS__, 'registration_redirect' ), 99 );
		add_filter( 'login_message', array( __CLASS__, 'registration_login_message' ), 30 );
		add_filter( 'wp_new_user_notification_email', array( __CLASS__, 'registration_email' ), 30, 3 );

		add_filter( 'wp_redirect', array( __CLASS__, 'prevent_legacy_checkout_loop' ), 99, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_single_affiliation_checkout' ), 99, 2 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_checkout_journey_notice' ), 5 );
		add_filter( 'gettext', array( __CLASS__, 'translate_checkout_labels' ), 90, 3 );

		add_filter( 'do_shortcode_tag', array( __CLASS__, 'enhance_login_without_club' ), 25, 4 );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'enhance_club_form' ), 30, 4 );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'enhance_dashboard' ), 35, 4 );
		add_action( 'admin_post_ufsc_save_club', array( __CLASS__, 'validate_officer_addresses' ), 1 );
		add_action( 'admin_post_nopriv_ufsc_save_club', array( __CLASS__, 'validate_officer_addresses' ), 1 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 30 );
	}

	private static function current_season() {
		return class_exists( 'UFSC_Season_Service' )
			? (string) UFSC_Season_Service::get_current_season()
			: ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
	}

	private static function dashboard_url() {
		if ( class_exists( 'UFSC_Frontend_Shortcodes' ) && method_exists( 'UFSC_Frontend_Shortcodes', 'get_club_portal_url' ) ) {
			return UFSC_Frontend_Shortcodes::get_club_portal_url( 'overview' );
		}
		$page = get_page_by_path( self::DASHBOARD_SLUG );
		return $page ? get_permalink( $page ) : home_url( '/' . self::DASHBOARD_SLUG . '/' );
	}

	private static function affiliation_url() {
		$page = get_page_by_path( self::AFFILIATION_SLUG );
		return $page ? get_permalink( $page ) : home_url( '/' . self::AFFILIATION_SLUG . '/' );
	}

	private static function user_club_id() {
		return is_user_logged_in() && function_exists( 'ufsc_get_user_club_id' )
			? absint( ufsc_get_user_club_id( get_current_user_id() ) )
			: 0;
	}

	private static function annual_affiliation( $club_id = 0 ) {
		$club_id = $club_id ? absint( $club_id ) : self::user_club_id();
		$season  = self::current_season();
		if ( $club_id < 1 || ! $season || ! class_exists( 'UFSC_Season_Archive_Manager' ) ) { return null; }
		return UFSC_Season_Archive_Manager::get_affiliation( $club_id, $season );
	}

	private static function annual_status( $club_id = 0 ) {
		$annual = self::annual_affiliation( $club_id );
		if ( ! $annual ) { return ''; }
		$raw = (string) ( $annual->status ?? $annual->statut ?? '' );
		return method_exists( 'UFSC_Season_Archive_Manager', 'normalize_status' )
			? sanitize_key( (string) UFSC_Season_Archive_Manager::normalize_status( $raw ) )
			: sanitize_key( $raw );
	}

	private static function is_existing_request_status( $status ) {
		return in_array(
			sanitize_key( (string) $status ),
			array( 'pending_payment', 'pending_validation', 'pending', 'en_attente', 'active', 'validated' ),
			true
		);
	}

	public static function registration_redirect( $redirect_to ) {
		unset( $redirect_to );
		$login_url = wp_login_url( self::affiliation_url() );
		return add_query_arg( 'checkemail', 'registered', $login_url );
	}

	public static function registration_login_message( $message ) {
		$action = isset( $_REQUEST['action'] ) && ! is_array( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$checkemail = isset( $_GET['checkemail'] ) && ! is_array( $_GET['checkemail'] ) ? sanitize_key( wp_unslash( $_GET['checkemail'] ) ) : '';
		if ( 'register' !== $action && 'registered' !== $checkemail ) { return $message; }
		$guide = '<div class="message ufsc-registration-guide"><strong>' . esc_html__( 'Création de votre accès Club UFSC', 'ufsc-clubs' ) . '</strong><br>' . esc_html__( 'Après l’inscription, un email vous est envoyé pour définir votre mot de passe. Consultez aussi vos courriers indésirables. Une fois le mot de passe créé, connectez-vous : vous serez dirigé vers le dossier d’affiliation de votre club.', 'ufsc-clubs' ) . '</div>';
		return $guide . $message;
	}

	public static function registration_email( $email, $user, $blogname ) {
		if ( ! is_array( $email ) || ! $user instanceof WP_User ) { return $email; }
		$email['subject'] = sprintf( '[%s] %s', wp_specialchars_decode( $blogname, ENT_QUOTES ), __( 'Activez votre accès Club UFSC', 'ufsc-clubs' ) );
		$email['message'] .= "\n\n" . __( 'Votre compte UFSC est créé. Utilisez le lien ci-dessus pour définir votre mot de passe.', 'ufsc-clubs' );
		$email['message'] .= "\n" . __( 'Ensuite, reconnectez-vous au site UFSC pour compléter les informations de votre association, les membres du bureau, les documents et l’affiliation annuelle.', 'ufsc-clubs' );
		$email['message'] .= "\n" . __( 'Si vous ne voyez pas cet email, vérifiez le dossier Courriers indésirables / Spam.', 'ufsc-clubs' );
		return $email;
	}

	private static function is_checkout_target( $location ) {
		if ( ! function_exists( 'wc_get_checkout_url' ) ) { return false; }
		$checkout_path = untrailingslashit( (string) wp_parse_url( wc_get_checkout_url(), PHP_URL_PATH ) );
		$target_path   = untrailingslashit( (string) wp_parse_url( $location, PHP_URL_PATH ) );
		return '' !== $checkout_path && $checkout_path === $target_path;
	}

	public static function prevent_legacy_checkout_loop( $location, $status ) {
		unset( $status );
		if ( ! is_user_logged_in() || ! self::is_checkout_target( $location ) ) { return $location; }
		if ( false !== strpos( $location, 'order-pay' ) || false !== strpos( $location, 'order-received' ) ) { return $location; }

		$club_id = self::user_club_id();
		if ( $club_id < 1 || ! self::is_existing_request_status( self::annual_status( $club_id ) ) ) { return $location; }

		// The first checkout remains untouched because no annual request exists yet.
		// Once WooCommerce has created the order, the canonical state bridge creates
		// the annual request and every legacy "club en_attente => checkout" redirect
		// is routed back to the club dashboard instead of charging twice.
		return self::dashboard_url();
	}

	private static function cart_contains_affiliation() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) { return false; }
		$expected = function_exists( 'ufsc_get_affiliation_product_id' ) ? absint( ufsc_get_affiliation_product_id() ) : 0;
		if ( $expected < 1 ) { return false; }
		foreach ( WC()->cart->get_cart() as $item ) {
			$product_id   = absint( $item['product_id'] ?? 0 );
			$variation_id = absint( $item['variation_id'] ?? 0 );
			if ( $expected === $product_id || $expected === $variation_id ) { return true; }
		}
		return false;
	}

	public static function validate_single_affiliation_checkout( $data, $errors ) {
		unset( $data );
		if ( ! self::cart_contains_affiliation() || ! is_wp_error( $errors ) ) { return; }
		$club_id = self::user_club_id();
		$season  = self::current_season();
		if ( $club_id < 1 || ! $season ) { return; }

		$status = self::annual_status( $club_id );
		$existing_order = function_exists( 'ufsc_wc_has_pending_renewal_order' )
			? (bool) ufsc_wc_has_pending_renewal_order( 'renew_affiliation', $club_id, $season )
			: false;

		if ( self::is_existing_request_status( $status ) || $existing_order ) {
			$errors->add(
				'ufsc_affiliation_already_requested',
				__( 'Une affiliation est déjà enregistrée pour votre club pour cette saison. Aucun second règlement n’est nécessaire. Consultez le tableau de bord pour suivre la validation.', 'ufsc-clubs' )
			);
		}
	}

	public static function render_checkout_journey_notice() {
		if ( ! self::cart_contains_affiliation() ) { return; }
		echo '<section class="ufsc-checkout-journey" aria-label="' . esc_attr__( 'Étape de règlement de l’affiliation', 'ufsc-clubs' ) . '">';
		echo '<strong>' . esc_html__( 'Étape 4 sur 4 — Finaliser l’affiliation', 'ufsc-clubs' ) . '</strong>';
		echo '<p>' . esc_html__( 'Vérifiez les coordonnées puis choisissez votre moyen de règlement. Avec le virement bancaire, la commande est enregistrée immédiatement en attente de vérification du règlement : vous pourrez revenir au tableau de bord sans effectuer un second paiement.', 'ufsc-clubs' ) . '</p>';
		echo '</section>';
	}

	public static function translate_checkout_labels( $translated, $text, $domain ) {
		unset( $domain );
		if ( is_admin() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return $translated; }
		$map = array(
			'Checkout'             => __( 'Finaliser mon affiliation', 'ufsc-clubs' ),
			'Customer information' => __( 'Informations du responsable', 'ufsc-clubs' ),
			'Your order'           => __( 'Votre affiliation', 'ufsc-clubs' ),
			'Payment'              => __( 'Paiement', 'ufsc-clubs' ),
			'Billing details'      => __( 'Coordonnées de facturation', 'ufsc-clubs' ),
			'Place order'          => __( 'Valider mon affiliation', 'ufsc-clubs' ),
		);
		return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
	}

	public static function enhance_login_without_club( $output, $tag, $attr, $m ) {
		unset( $attr, $m );
		if ( 'ufsc_login_form' !== $tag || ! is_user_logged_in() || self::user_club_id() > 0 ) { return $output; }

		$user = wp_get_current_user();
		$display_name = trim( (string) $user->display_name );
		$heading = $display_name
			? sprintf( __( 'Bienvenue %s', 'ufsc-clubs' ), $display_name )
			: __( 'Votre compte Club UFSC est prêt', 'ufsc-clubs' );
		$affiliation_url = self::affiliation_url();
		$logout_url = wp_logout_url( home_url( '/' ) );

		$html  = '<div class="ufsc-already-logged-in ufsc-card ufsc-no-club-onboarding">';
		$html .= self::stepper_html( 2 );
		$html .= '<h3>' . esc_html( $heading ) . '</h3>';
		$html .= '<p>' . esc_html__( 'Votre compte utilisateur est bien créé, mais aucun club n’y est encore rattaché. Vous pouvez maintenant créer votre club et démarrer sa demande d’affiliation UFSC.', 'ufsc-clubs' ) . '</p>';
		$html .= '<div class="ufsc-onboarding-intro"><strong>' . esc_html__( 'Étape suivante : créer et affilier votre club', 'ufsc-clubs' ) . '</strong><p>' . esc_html__( 'Préparez les informations de l’association, les coordonnées du bureau et les documents demandés. Le règlement de l’affiliation interviendra à la dernière étape.', 'ufsc-clubs' ) . '</p></div>';
		$html .= '<div class="ufsc-login-actions"><a href="' . esc_url( $affiliation_url ) . '" class="ufsc-btn ufsc-btn-primary">' . esc_html__( 'Créer / affilier mon club', 'ufsc-clubs' ) . '</a><a href="' . esc_url( $logout_url ) . '" class="ufsc-btn ufsc-btn-secondary">' . esc_html__( 'Se déconnecter', 'ufsc-clubs' ) . '</a></div>';
		$html .= '</div>';
		return $html;
	}

	public static function validate_officer_addresses() {
		if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) { return; }
		if ( current_user_can( 'manage_options' ) ) { return; }
		$club_id = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
		if ( $club_id > 0 ) { return; }
		$required = array(
			'president_adresse'  => __( 'Président', 'ufsc-clubs' ),
			'secretaire_adresse' => __( 'Secrétaire', 'ufsc-clubs' ),
			'tresorier_adresse'  => __( 'Trésorier', 'ufsc-clubs' ),
		);
		$missing = array();
		foreach ( $required as $field => $label ) {
			$value = isset( $_POST[ $field ] ) && ! is_array( $_POST[ $field ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ) : '';
			if ( '' === $value ) { $missing[] = $label; }
		}
		if ( ! $missing ) { return; }

		$redirect = wp_get_referer() ?: self::affiliation_url();
		$message = sprintf( __( 'Adresse postale complète obligatoire pour : %s.', 'ufsc-clubs' ), implode( ', ', $missing ) );
		wp_safe_redirect( add_query_arg( 'ufsc_error', rawurlencode( $message ), $redirect ) );
		exit;
	}

	private static function stepper_html( $active = 2 ) {
		$steps = array(
			1 => __( 'Compte', 'ufsc-clubs' ),
			2 => __( 'Club & bureau', 'ufsc-clubs' ),
			3 => __( 'Documents & honorabilité', 'ufsc-clubs' ),
			4 => __( 'Paiement', 'ufsc-clubs' ),
		);
		$html = '<nav class="ufsc-onboarding-steps" aria-label="' . esc_attr__( 'Étapes de création du club', 'ufsc-clubs' ) . '"><ol>';
		foreach ( $steps as $number => $label ) {
			$class = $number < $active ? 'is-done' : ( $number === $active ? 'is-active' : '' );
			$html .= '<li class="' . esc_attr( $class ) . '"><span>' . esc_html( $number ) . '</span><strong>' . esc_html( $label ) . '</strong></li>';
		}
		return $html . '</ol></nav>';
	}

	public static function enhance_club_form( $output, $tag, $attr, $m ) {
		unset( $attr, $m );
		if ( 'ufsc_club_form' !== $tag || ! is_string( $output ) || '' === $output ) { return $output; }

		foreach ( array( 'president_adresse', 'secretaire_adresse', 'tresorier_adresse' ) as $field ) {
			$output = preg_replace( '/(<input\b(?=[^>]*\bname=["\']' . preg_quote( $field, '/' ) . '["\'])(?![^>]*\brequired\b)[^>]*)(>)/i', '$1 required autocomplete="street-address"$2', $output, 1 );
		}

		$intro = self::stepper_html( 2 ) . '<div class="ufsc-onboarding-intro"><strong>' . esc_html__( 'Création de votre club UFSC', 'ufsc-clubs' ) . '</strong><p>' . esc_html__( 'Renseignez les informations du club et du bureau. Pour le président, le secrétaire et le trésorier, renseignez une adresse postale complète (numéro et voie, code postal et ville).', 'ufsc-clubs' ) . '</p></div>';
		$honorability = '<div class="ufsc-onboarding-honorability"><strong>' . esc_html__( 'Honorabilité obligatoire pour le bureau', 'ufsc-clubs' ) . '</strong><p>' . esc_html__( 'Le président, le secrétaire, le trésorier et tout dirigeant soumis au contrôle d’honorabilité devront disposer d’une attestation dans leur dossier de licence. La validation utilise le dispositif d’honorabilité déjà présent dans le plugin : aucun dossier parallèle n’est créé.', 'ufsc-clubs' ) . '</p></div>';
		return $intro . $output . $honorability;
	}

	public static function enhance_dashboard( $output, $tag, $attr, $m ) {
		unset( $attr, $m );
		if ( 'ufsc_club_dashboard' !== $tag || ! is_string( $output ) || '' === $output ) { return $output; }
		$status = self::annual_status();
		if ( ! in_array( $status, array( 'pending_payment', 'pending_validation', 'pending', 'en_attente' ), true ) ) { return $output; }
		$notice = '<div class="ufsc-affiliation-pending-banner" role="status"><strong>' . esc_html__( 'Votre affiliation est enregistrée', 'ufsc-clubs' ) . '</strong><p>' . esc_html__( 'Votre dossier est en attente de vérification par l’UFSC. Vous pouvez utiliser votre tableau de bord et compléter vos informations. Les actions nécessitant une affiliation validée restent bloquées jusqu’à la validation administrative.', 'ufsc-clubs' ) . '</p></div>';
		return $notice . $output;
	}

	public static function enqueue_assets() {
		if ( is_admin() ) { return; }
		wp_enqueue_style(
			'ufsc-new-club-onboarding',
			UFSC_CL_URL . 'assets/css/ufsc-new-club-onboarding.css',
			array(),
			function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/css/ufsc-new-club-onboarding.css' ) : UFSC_CL_VERSION
		);
	}
}

UFSC_New_Club_Onboarding_Hardening::init();