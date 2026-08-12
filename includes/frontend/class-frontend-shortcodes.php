<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Frontend shortcodes for UFSC Gestion
 * Provides secure, nonce-protected shortcodes for club management
 */
class UFSC_Frontend_Shortcodes {

    const SPORTS_RULES_URL = 'https://ufsc-france.fr/ufsc-reglements-sportifs-techniques-interieur/';

    /**
     * Return the canonical URL of a club portal section.
     *
     * Page lookup deliberately uses WordPress permalinks instead of a hard-coded
     * host. The slug fallback also remains installation-aware through home_url().
     *
     * @param string $section Portal section name.
     * @return string
     */
    public static function get_club_portal_url( $section = '' ) {
        $account_sections = array( 'club-information', 'club-officers', 'club-documents' );
        $is_account       = in_array( $section, $account_sections, true );
        $slug             = $is_account ? 'compte-club' : 'tableau-de-bord-club';
        $page             = get_page_by_path( $slug );
        $url              = $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
        $anchors          = array(
            'overview'          => 'ufsc-overview',
            'licences'          => 'ufsc-club-licences',
            'club-licences'     => 'ufsc-club-licences',
            'licences-archives' => 'ufsc-licences-archives',
            'club-information'  => 'ufsc-club-information',
            'club-officers'     => 'ufsc-club-officers',
            'club-documents'    => 'ufsc-club-documents',
        );

        if ( 'licences-archives' === $section ) {
            $url = add_query_arg( 'ufsc_section', 'licences-archives', $url ) . '#ufsc-licences-archives';
        } elseif ( 'licences-renouvellement' === $section ) {
            $url = add_query_arg( array( 'ufsc_section' => 'licences-renouvellement' ), $url ) . '#ufsc-renouvellement';
        } elseif ( isset( $anchors[ $section ] ) ) {
            $url .= '#' . $anchors[ $section ];
        }

        return $url;
    }

    /**
     * Validate a possible return URL against this site's club portal pages.
     *
     * @param string $candidate Candidate URL.
     * @return string Empty when the URL is not a local portal URL.
     */
    private static function validate_club_portal_return_url( $candidate ) {
        $candidate = wp_validate_redirect( (string) $candidate, '' );
        if ( '' === $candidate ) {
            return '';
        }

        $candidate_host = wp_parse_url( $candidate, PHP_URL_HOST );
        $site_host      = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
        $candidate_path = untrailingslashit( (string) wp_parse_url( $candidate, PHP_URL_PATH ) );
        $portal_paths   = array(
            untrailingslashit( (string) wp_parse_url( self::get_club_portal_url(), PHP_URL_PATH ) ),
            untrailingslashit( (string) wp_parse_url( self::get_club_portal_url( 'club-information' ), PHP_URL_PATH ) ),
        );

        if ( $candidate_host !== $site_host || ! in_array( $candidate_path, $portal_paths, true ) ) {
            return '';
        }

        return $candidate;
    }

    /**
     * Resolve the licence list URL, preserving its filters when explicitly sent.
     *
     * @return string
     */
    private static function get_licence_return_url() {
        if ( isset( $_GET['ufsc_return'] ) && ! is_array( $_GET['ufsc_return'] ) ) {
            $explicit = self::validate_club_portal_return_url( wp_unslash( $_GET['ufsc_return'] ) );
            if ( '' !== $explicit ) {
                return $explicit;
            }
        }

        $referer = wp_get_referer();
        if ( $referer ) {
            $referer = self::validate_club_portal_return_url( $referer );
            if ( '' !== $referer ) {
                return $referer;
            }
        }

        return self::get_club_portal_url( 'club-licences' );
    }

    /**
     * Build a detail URL with a validated, filter-preserving list URL.
     *
     * @param int $licence_id Licence identifier.
     * @return string
     */
    private static function get_licence_detail_url( $licence_id ) {
        $list_url = remove_query_arg(
            array( 'view_licence', 'edit_licence', 'ufsc_return', 'ufsc_message', 'ufsc_error' )
        );
        $list_url = self::validate_club_portal_return_url( $list_url );
        if ( '' === $list_url ) {
            $list_url = self::get_club_portal_url( 'club-licences' );
        }

        return add_query_arg(
            array(
                'view_licence' => absint( $licence_id ),
                'ufsc_return'  => $list_url,
            )
        );
    }

    /**
     * Register all frontend shortcodes
     */
    public static function register() {
        add_shortcode( 'ufsc_club_dashboard', array( __CLASS__, 'render_club_dashboard' ) );
        add_shortcode( 'ufsc_club_licences', array( __CLASS__, 'render_club_licences' ) );
        add_shortcode( 'ufsc_club_stats', array( __CLASS__, 'render_club_stats' ) );
        add_shortcode( 'ufsc_club_profile', array( __CLASS__, 'render_club_profile' ) );
        add_shortcode( 'ufsc_add_licence', array( __CLASS__, 'render_add_licence' ) );
        add_shortcode( 'ufsc_licences', array( __CLASS__, 'render_licences' ) );
    }

    private static function get_status_badge_front($status, $label = '')
    {
		if ( 'a_renouveler' === sanitize_key( (string) $status ) ) {
			$label = __( 'À renouveler', 'ufsc-clubs' );
		}
        if (empty($label)) {
            if ( function_exists( 'ufsc_get_licence_status_label_fr' ) ) {
                $label = ufsc_get_licence_status_label_fr( $status );
            } else {
                $label = UFSC_SQL::statuses()[$status] ?? $status;
            }
        }

        // Map status to CSS class
        $normalized = function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $status ) : $status;
        $status_map = array(
            'brouillon'  => 'pending',
            'non_payee'  => 'pending',
            'en_attente' => 'pending',
            'valide'     => 'valid',
            'refuse'     => 'rejected',
			'a_renouveler' => 'pending',
        );

        $css_class = isset($status_map[$normalized]) ? $status_map[$normalized] : 'inactive';

        return '<span class="ufsc-status-badge ufsc-status-' . esc_attr($css_class) . '">' .
               '<span class="ufsc-status-dot"></span>' .
               esc_html($label) .
               '</span>';
    }

    /**
     * Render the main club dashboard with 4 sections
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    /** Render the shared square, non-cropping club logo component. */
    private static function render_club_logo( $url, $club_name, $extra_class = '' ) {
        $label = trim( (string) $club_name );
        $initial = '' !== $label ? function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 1 ) : substr( $label, 0, 1 ) : 'UFSC';
        $class = trim( 'ufsc-club-logo ' . sanitize_html_class( $extra_class ) );
        if ( '' !== trim( (string) $url ) ) {
            return '<figure class="' . esc_attr( $class ) . '"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( sprintf( __( 'Logo du club %s', 'ufsc-clubs' ), $label ) ) . '" loading="lazy" decoding="async"></figure>';
        }
        return '<figure class="' . esc_attr( $class . ' ufsc-club-logo--fallback' ) . '" role="img" aria-label="' . esc_attr__( 'Aucun logo de club enregistré', 'ufsc-clubs' ) . '"><span aria-hidden="true">' . esc_html( strtoupper( $initial ) ) . '</span></figure>';
    }

    public static function render_club_dashboard( $atts = array() ) {
        wp_enqueue_style( 'ufsc-front', UFSC_CL_URL . 'assets/css/ufsc-front.css', array(), function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/css/ufsc-front.css' ) : UFSC_CL_VERSION );
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );
        $atts = shortcode_atts( array(
            'show_sections' => 'licences,stats,profile,add_licence'
        ), $atts );

        if ( ! is_user_logged_in() ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Vous devez être connecté pour accéder au tableau de bord.', 'ufsc-clubs' ) .
                   '</div>';
        }

        $user_id = get_current_user_id();
        $club_id = self::get_user_club_id( $user_id );

        if ( ! $club_id ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Aucun club associé à votre compte.', 'ufsc-clubs' ) .
                   '</div>';
        }

        $wc_settings = ufsc_get_woocommerce_settings();
        $season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
        $stats = self::get_club_stats( $club_id, $season );
        $status_counts = (array) ( $stats['by_status'] ?? array() );
        $draft_licences = (int) ( $stats['draft_licences'] ?? array_sum( array_intersect_key( $status_counts, array_flip( array( 'brouillon', 'draft', 'a_completer' ) ) ) ) );
        $payable_licences = array_sum( array_intersect_key( $status_counts, array_flip( array( 'a_regler', 'pending_payment', 'non_payee' ) ) ) );
        $season_start_year = (int) substr( $season, 0, 4 );
        $previous_season = $season_start_year ? ( $season_start_year - 1 ) . '-' . $season_start_year : '';
        $renewable_licences = $previous_season ? self::get_club_licences_count( $club_id, array( 'season' => $previous_season ) ) : 0;
		$pack_usage = function_exists( 'ufsc_get_pack_usage' ) ? ufsc_get_pack_usage( $club_id, $season ) : array( 'total' => 0, 'bureau' => 0, 'libres' => 0, 'payantes' => 0, 'roles' => array() );
        $licence_stats_labels = array(
            esc_html__( 'Total', 'ufsc-clubs' ),
            esc_html__( 'Payées', 'ufsc-clubs' ),
            esc_html__( 'Validées', 'ufsc-clubs' ),
            esc_html__( 'Homme', 'ufsc-clubs' ),
            esc_html__( 'Femme', 'ufsc-clubs' ),
            esc_html__( 'Loisir', 'ufsc-clubs' ),
            esc_html__( 'Compétition', 'ufsc-clubs' ),
        );
        $licence_stats_data = array(
            (int) $stats['total_licences'],
            (int) $stats['paid_licences'],
            (int) $stats['validated_licences'],
            (int) ( $stats['by_gender']['M'] ?? 0 ),
            (int) ( $stats['by_gender']['F'] ?? 0 ),
            (int) ( $stats['by_practice']['leisure'] ?? ( $stats['by_practice'][0] ?? 0 ) ),
            (int) ( $stats['by_practice']['competition'] ?? ( $stats['by_practice'][1] ?? 0 ) ),
        );

        wp_localize_script(
            'chart-js',
            'ufscLicenceStats',
            array(
                'labels' => $licence_stats_labels,
                'data'   => $licence_stats_data,
                'datasetLabel' => esc_html__( 'Licences', 'ufsc-clubs' ),
            )
        );

        wp_localize_script(
            'chart-js',
            'ufscLicenceStatsYear',
            array(
                'data'   => $stats['by_birth_year'],
                'datasetLabel' => esc_html__( 'Nombre de licence par année de naissance', 'ufsc-clubs' ),
            )
        );

        $sections        = explode( ',', $atts['show_sections'] );
        $requested_dashboard_section = isset( $_GET['ufsc_tab'] ) && ! is_array( $_GET['ufsc_tab'] ) ? sanitize_key( wp_unslash( $_GET['ufsc_tab'] ) ) : 'licences';
        if ( ! in_array( $requested_dashboard_section, $sections, true ) ) { $requested_dashboard_section = 'licences'; }
        $club            = self::get_club_data( $club_id );
        // Never expose the permanent club status as the annual affiliation status.
        $club_status     = 'a_renouveler';
        $bureau_data     = self::get_bureau_coverage_data( (int) $club_id );
        $missing_roles   = ! empty( $bureau_data['missing_labels'] ) ? count( $bureau_data['missing_labels'] ) : 0;
        $mandatory_docs  = array( 'doc_statuts', 'doc_recepisse', 'doc_jo', 'doc_pv_ag', 'doc_cer', 'doc_attestation_cer' );
        $missing_docs    = 0;
        $attestation_dashboard = function_exists( 'ufsc_get_affiliation_attestation_data' )
            ? ufsc_get_affiliation_attestation_data( $club_id, $club )
            : array( 'url' => '', 'status' => 'pending', 'can_view' => false );
        foreach ( $mandatory_docs as $doc_key ) {
            if ( empty( $club->$doc_key ) || ! wp_get_attachment_url( $club->$doc_key ) ) {
                $missing_docs++;
            }
        }

        $wc_settings    = function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings() : array();
        $current_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : $season );
        $renewal_affiliation_season = $current_season;
        $season_start   = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_season_start_date( $current_season ) : '';
        $season_end     = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_season_end_date( $current_season ) : '';
        $licence_affiliation_gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id, $current_season ) : array( 'allowed' => false, 'code' => 'affiliation_resolution_error', 'message' => __( 'L’état de votre affiliation n’a pas pu être déterminé. Veuillez contacter l’UFSC.', 'ufsc-clubs' ) );
        $affiliation_season = ! empty( $licence_affiliation_gate['allowed'] ) ? $current_season : '';
        $annual_affiliation = class_exists( 'UFSC_Season_Archive_Manager' ) ? UFSC_Season_Archive_Manager::get_affiliation( $club_id, $renewal_affiliation_season ) : null;
        $affiliation_state = function_exists( 'ufsc_get_affiliation_renewal_state' ) ? ufsc_get_affiliation_renewal_state( $club_id, $renewal_affiliation_season ) : array( 'status' => 'renewal_required', 'label' => __( 'À renouveler', 'ufsc-clubs' ), 'action' => 'renew', 'affiliation' => $annual_affiliation );
        $renewal_affiliation_done = ! empty( $licence_affiliation_gate['allowed'] );
        $affiliation_pending = in_array( $affiliation_state['status'], array( 'pending_payment', 'pending_validation' ), true );
        $annual_presentation = function_exists( 'ufsc_get_annual_affiliation_status' ) ? ufsc_get_annual_affiliation_status( $annual_affiliation ) : array( 'key' => $affiliation_state['status'], 'label' => $affiliation_state['label'] );
        $club_status = ! empty( $annual_presentation['key'] ) ? $annual_presentation['key'] : $affiliation_state['status'];
		$honorability_kpis = array( 'required' => 0, 'validated' => 0, 'pending' => 0, 'rejected' => 0, 'correction_required' => 0, 'missing' => 0, 'complete' => 0, 'incomplete' => 0 );
		if ( function_exists( 'ufsc_get_honorability_document_kpis' ) ) {
			$current_licences = self::get_club_licences( $club_id, array( 'season' => $current_season, 'page' => 1, 'per_page' => 2000 ) );
			$honorability_kpis = ufsc_get_honorability_document_kpis( $current_licences, $current_season );
			$missing_docs += $honorability_kpis['incomplete'];
		}
        $pending_order = function_exists( 'ufsc_wc_has_pending_renewal_order' ) ? ufsc_wc_has_pending_renewal_order( 'renew_affiliation', $club_id, $renewal_affiliation_season ) : false;
        $pending_payment_url = function_exists( 'ufsc_get_pending_affiliation_payment_url' ) ? ufsc_get_pending_affiliation_payment_url( $club_id, $renewal_affiliation_season ) : '';
        $renewal_url = function_exists( 'ufsc_get_affiliation_renewal_url' ) ? ufsc_get_affiliation_renewal_url( $club_id, $renewal_affiliation_season, $annual_affiliation->id ?? 0 ) : '';
        $affiliation_product_id = function_exists( 'ufsc_get_affiliation_product_id' ) ? ufsc_get_affiliation_product_id() : 4823;
        $affiliation_product_diagnostic = function_exists( 'ufsc_get_woocommerce_product_diagnostic' ) ? ufsc_get_woocommerce_product_diagnostic( $affiliation_product_id ) : array();
        $affiliation_product_available = function_exists( 'ufsc_is_woocommerce_product_available' ) ? ufsc_is_woocommerce_product_available( $affiliation_product_id ) : false;
        $can_manage_current_club = true;
        if ( class_exists( 'UFSC_CL_Permissions' ) && method_exists( 'UFSC_CL_Permissions', 'ufsc_user_can_edit_club' ) ) {
            $can_manage_current_club = UFSC_CL_Permissions::ufsc_user_can_edit_club( $club_id );
        }
        $can_renew_affiliation = $can_manage_current_club && 'renew' === $affiliation_state['action'] && ! $pending_order && $affiliation_product_available && '' !== $renewal_url;
        $renew_window_open = function_exists( 'ufsc_is_renewal_window_open' ) ? ufsc_is_renewal_window_open() : true;
        $renew_start_ts = function_exists( 'ufsc_get_renewal_window_start_ts' ) ? (int) ufsc_get_renewal_window_start_ts() : 0;
        $renew_open_label = $renew_start_ts > 0 ? wp_date( 'd/m/Y', $renew_start_ts ) : __( '30/07', 'ufsc-clubs' );
        $profile_name    = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'name' ) : ( $club->nom ?? '' );
        $profile_region  = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'region' ) : ( $club->region ?? '' );
        $profile_address = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'address' ) : ( $club->adresse ?? '' );
        $profile_cp      = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'postal_code' ) : ( $club->code_postal ?? '' );
        $profile_city    = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'city' ) : ( $club->ville ?? '' );
        $profile_phone   = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'phone' ) : ( $club->telephone ?? '' );
        $profile_email   = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'email' ) : ( $club->email ?? '' );
        $profile_site    = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'website' ) : ( $club->url_site ?? '' );
        $profile_affnum  = $annual_affiliation->num_affiliation ?? ( function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'affiliation_number' ) : ( $club->num_affiliation ?? '' ) );
        $profile_address_line = trim( trim( (string) $profile_address ) . ' ' . trim( (string) $profile_cp ) . ' ' . trim( (string) $profile_city ) );
        $profile_logo = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'logo' ) : ( $club->profile_photo_url ?? '' );

        ob_start();
        ?>
        <div class="ufsc-club-portal ufsc-club-account ufsc-club-dashboard ufsc-premium-v3" id="ufsc-dashboard" data-ufsc-build="<?php echo esc_attr( function_exists( 'ufsc_get_build_id' ) ? ufsc_get_build_id() : UFSC_CL_VERSION ); ?>" data-club-id="<?php echo esc_attr( absint( $club_id ) ); ?>">
            <div class="ufsc-dashboard-shell">
                <div class="ufsc-dashboard-header ufsc-dashboard-header--premium ufsc-club-account__header" id="ufsc-overview">
                    <div class="ufsc-dashboard-hero-layout">
                    <div class="ufsc-hero-left">
                        <div class="ufsc-dashboard-brand">
                            <?php echo self::render_club_logo( $profile_logo, $profile_name, 'ufsc-dashboard-logo' ); ?>
                            <div class="ufsc-dashboard-title">
                                <h2><?php esc_html_e( 'Tableau de bord Club', 'ufsc-clubs' ); ?></h2>
                                <p class="ufsc-dashboard-subtitle">
                                    <?php
                                    $club_name = self::get_club_name( $club_id );
                                    if ( $club_name ) {
                                        echo sprintf( esc_html__( 'Pilotage de %s', 'ufsc-clubs' ), esc_html( $club_name ) );
                                    }
                                    ?>
                                </p>
                                <div class="ufsc-dashboard-status-line">
                                    <span class="ufsc-badge ufsc-badge-info"><?php esc_html_e( 'État du club', 'ufsc-clubs' ); ?></span>
                                    <?php echo self::get_status_badge_front( $club_status, $annual_presentation['label'] ); ?>
                                    <?php if ( ! empty( $club->num_affiliation ) ) : ?>
                                        <span class="ufsc-badge ufsc-badge-region"><?php echo esc_html( sprintf( __( 'Affiliation %s', 'ufsc-clubs' ), $club->num_affiliation ) ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <dl class="ufsc-club-account__identity" aria-label="<?php esc_attr_e( 'Coordonnées principales du club', 'ufsc-clubs' ); ?>">
                                    <?php if ( '' !== $profile_region ) : ?><div><dt><?php esc_html_e( 'Région', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $profile_region ); ?></dd></div><?php endif; ?>
                                    <?php if ( '' !== $profile_address_line ) : ?><div><dt><?php esc_html_e( 'Adresse', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $profile_address_line ); ?></dd></div><?php endif; ?>
                                    <?php if ( '' !== $profile_phone ) : ?><div><dt><?php esc_html_e( 'Téléphone', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $profile_phone ); ?></dd></div><?php endif; ?>
                                    <?php if ( '' !== $profile_email ) : ?><div><dt><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></dt><dd><a href="mailto:<?php echo esc_attr( $profile_email ); ?>"><?php echo esc_html( $profile_email ); ?></a></dd></div><?php endif; ?>
                                    <?php if ( '' !== $profile_site ) : ?><div><dt><?php esc_html_e( 'Site', 'ufsc-clubs' ); ?></dt><dd><a href="<?php echo esc_url( $profile_site ); ?>" target="_blank" rel="noopener"><?php echo esc_html( preg_replace( '#^https?://#', '', (string) $profile_site ) ); ?></a></dd></div><?php endif; ?>
                                    <?php if ( '' !== $profile_affnum ) : ?><div><dt><?php esc_html_e( 'N° affiliation', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $profile_affnum ); ?></dd></div><?php endif; ?>
                                    <?php if ( '' !== $current_season ) : ?><div><dt><?php esc_html_e( 'Saison', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $current_season ); ?></dd></div><?php endif; ?>
                                </dl>
						<?php if ( $honorability_kpis['required'] ) : ?>
						<div class="ufsc-message <?php echo $honorability_kpis['incomplete'] ? 'ufsc-warning' : 'ufsc-success'; ?>">
							<strong><?php esc_html_e( 'Attestations d’honorabilité :', 'ufsc-clubs' ); ?></strong>
							<?php echo esc_html( sprintf( __( '%1$d validée(s), %2$d en attente, %3$d à corriger, %4$d manquante(s).', 'ufsc-clubs' ), $honorability_kpis['validated'], $honorability_kpis['pending'], $honorability_kpis['correction_required'], $honorability_kpis['missing'] + $honorability_kpis['rejected'] ) ); ?><small><?php esc_html_e( 'L’honorabilité concerne uniquement les dirigeants et encadrants soumis à cette obligation.', 'ufsc-clubs' ); ?></small>
						</div>
						<?php endif; ?>
                            </div>
                        </div>
                        <div class="ufsc-dashboard-actions ufsc-dashboard-actions--primary">
                            <?php if ( in_array( 'add_licence', $sections, true ) ): ?>
                                <a href="<?php echo esc_url( add_query_arg( 'ufsc_tab', 'add_licence', self::get_club_portal_url( 'licences' ) ) . '#ufsc-section-add_licence' ); ?>" class="ufsc-btn ufsc-btn-primary">
                                    <?php esc_html_e( 'Ajouter une licence', 'ufsc-clubs' ); ?>
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url( self::get_club_portal_url( 'licences-renouvellement' ) ); ?>" class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Renouveler des licences', 'ufsc-clubs' ); ?></a>
                            <a href="<?php echo esc_url( self::get_club_portal_url( 'club-documents' ) ); ?>" class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Consulter les documents', 'ufsc-clubs' ); ?></a>
                            <?php if ( in_array( 'profile', $sections, true ) ): ?>
                                <a href="<?php echo esc_url( self::get_club_portal_url( 'club-information' ) ); ?>" class="ufsc-btn ufsc-btn-secondary">
                                    <?php esc_html_e( 'Mettre à jour le club', 'ufsc-clubs' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <aside class="ufsc-attestation-card ufsc-card" aria-label="<?php esc_attr_e( 'Attestation UFSC', 'ufsc-clubs' ); ?>"><strong><?php esc_html_e( 'Attestation UFSC', 'ufsc-clubs' ); ?></strong><span class="ufsc-badge <?php echo ! empty( $attestation_dashboard['url'] ) ? 'ufsc-badge-success' : 'ufsc-badge-warning'; ?>"><?php echo ! empty( $attestation_dashboard['url'] ) ? esc_html__( 'Disponible', 'ufsc-clubs' ) : ( ! empty( $attestation_dashboard['can_view'] ) ? esc_html__( 'Génération en cours', 'ufsc-clubs' ) : esc_html__( 'Informations manquantes', 'ufsc-clubs' ) ); ?></span><small><?php echo ! empty( $attestation_dashboard['url'] ) ? esc_html__( 'Votre attestation est prête à être consultée et téléchargée.', 'ufsc-clubs' ) : ( ! empty( $attestation_dashboard['can_view'] ) ? esc_html__( 'Le document est en préparation. Aucune action n’est nécessaire.', 'ufsc-clubs' ) : esc_html__( 'Complétez les informations du club pour permettre sa génération.', 'ufsc-clubs' ) ); ?></small><?php if ( ! empty( $attestation_dashboard['url'] ) ) : ?><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( $attestation_dashboard['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Consulter / Télécharger', 'ufsc-clubs' ); ?></a><?php endif; ?></aside>
                    </div>

                    <div class="ufsc-hero-right"><div class="ufsc-hero-kpi-grid" aria-label="<?php esc_attr_e( 'Indicateurs de la saison active', 'ufsc-clubs' ); ?>">
                        <?php $kpis = array(
                            array( sprintf( __( 'Licences %s', 'ufsc-clubs' ), $season ), (int) $stats['total_licences'], add_query_arg( 'ufsc_season', $season, self::get_club_portal_url( 'licences' ) ), sprintf( __( 'Dossiers du club appartenant à la saison active %s.', 'ufsc-clubs' ), $season ) ),
                            array( __( 'Licences validées', 'ufsc-clubs' ), (int) $stats['validated_licences'], add_query_arg( array( 'ufsc_status' => 'valide', 'ufsc_season' => $season ), self::get_club_portal_url( 'licences' ) ), __( 'Licences validées du club pour la saison active.', 'ufsc-clubs' ) ),
                            array( __( 'Brouillons / à compléter', 'ufsc-clubs' ), (int) $draft_licences, add_query_arg( array( 'ufsc_status' => 'brouillon', 'ufsc_season' => $season ), self::get_club_portal_url( 'licences' ) ), __( 'Brouillons explicitement enregistrés pour la saison active.', 'ufsc-clubs' ) ),
                            array( __( 'Licences à renouveler', 'ufsc-clubs' ), (int) $renewable_licences, self::get_club_portal_url( 'licences-renouvellement' ), __( 'Licences de la saison précédente encore éligibles au renouvellement.', 'ufsc-clubs' ) ),
                            array( __( 'Paiements à finaliser', 'ufsc-clubs' ), (int) $payable_licences, add_query_arg( 'ufsc_renew_state', 'payable', self::get_club_portal_url( 'licences-renouvellement' ) ), __( 'Demandes du club dont le règlement peut encore être finalisé.', 'ufsc-clubs' ) ),
                            array( sprintf( __( 'Documents manquants %s', 'ufsc-clubs' ), $season ), (int) $honorability_kpis['incomplete'], add_query_arg( 'ufsc_renew_state', 'incomplete', self::get_club_portal_url( 'licences-renouvellement' ) ), sprintf( __( 'Documents manquants uniquement sur les dossiers du club rattachés à %s.', 'ufsc-clubs' ), $season ) ),
                        ); foreach ( $kpis as $kpi ) : ?><a class="ufsc-card ufsc-kpi-tile ufsc-hero-kpi-card" href="<?php echo esc_url( $kpi[2] ); ?>" title="<?php echo esc_attr( $kpi[3] ); ?>" aria-label="<?php echo esc_attr( $kpi[0] . ' — ' . $kpi[1] . '. ' . $kpi[3] ); ?>"><span class="ufsc-kpi-tile-label"><?php echo esc_html( $kpi[0] ); ?></span><strong class="ufsc-kpi-tile-value"><?php echo esc_html( $kpi[1] ); ?></strong></a><?php endforeach; ?>
                    </div></div>
					<section class="ufsc-demographic-summary" aria-labelledby="ufsc-demographic-title">
						<h3 id="ufsc-demographic-title"><?php esc_html_e( 'Profil des licenciés', 'ufsc-clubs' ); ?></h3>
						<div class="ufsc-demographic-grid">
						<?php
						$demographic_kpis = array(
							array( __( 'Femmes', 'ufsc-clubs' ), (int) ( $stats['by_gender']['F'] ?? 0 ), array( 'ufsc_gender' => 'F' ) ),
							array( __( 'Hommes', 'ufsc-clubs' ), (int) ( $stats['by_gender']['M'] ?? 0 ), array( 'ufsc_gender' => 'M' ) ),
							array( __( 'Mineurs', 'ufsc-clubs' ), (int) ( $stats['by_age']['minor'] ?? 0 ), array( 'ufsc_age' => 'minor' ) ),
							array( __( 'Majeurs', 'ufsc-clubs' ), (int) ( $stats['by_age']['adult'] ?? 0 ), array( 'ufsc_age' => 'adult' ) ),
							array( __( 'Loisirs', 'ufsc-clubs' ), (int) ( $stats['by_practice']['leisure'] ?? 0 ), array( 'ufsc_practice' => 'leisure' ) ),
							array( __( 'Compétiteurs', 'ufsc-clubs' ), (int) ( $stats['by_practice']['competition'] ?? 0 ), array( 'ufsc_practice' => 'competition' ) ),
							array( __( 'Non renseigné', 'ufsc-clubs' ), (int) ( $stats['unknown_profiles'] ?? 0 ), array( 'ufsc_missing_profile' => '1' ) ),
						);
						foreach ( $demographic_kpis as $demographic ) :
							$url = add_query_arg( array_merge( array( 'ufsc_season' => $season ), $demographic[2] ), self::get_club_portal_url( 'licences' ) );
						?><a class="ufsc-card ufsc-demographic-card" href="<?php echo esc_url( $url ); ?>"><span><?php echo esc_html( $demographic[0] ); ?></span><strong><?php echo esc_html( $demographic[1] ); ?></strong></a><?php endforeach; ?>
						</div>
					</section>
					<section class="ufsc-pack-summary" aria-labelledby="ufsc-pack-title">
						<div class="ufsc-pack-summary__heading">
							<h3 id="ufsc-pack-title"><?php esc_html_e( 'Pack d’affiliation', 'ufsc-clubs' ); ?></h3>
							<span><?php echo esc_html( sprintf( __( '%d licence(s) utilisée(s) sur 10', 'ufsc-clubs' ), $pack_usage['total'] ) ); ?></span>
						</div>
						<a class="ufsc-pack-card" href="<?php echo esc_url( add_query_arg( 'ufsc_pack', 'bureau', self::get_club_portal_url( 'licences' ) ) ); ?>">
							<span class="ufsc-pack-card__label"><?php esc_html_e( 'Bureau', 'ufsc-clubs' ); ?></span>
							<strong class="ufsc-pack-card__value"><?php echo esc_html( sprintf( __( '%d/3', 'ufsc-clubs' ), $pack_usage['bureau'] ) ); ?></strong>
							<span class="ufsc-pack-card__detail"><?php foreach ( array( 'president' => __( 'Président', 'ufsc-clubs' ), 'secretaire' => __( 'Secrétaire', 'ufsc-clubs' ), 'tresorier' => __( 'Trésorier', 'ufsc-clubs' ) ) as $role_key => $role_label ) { echo esc_html( $role_label . ' : ' . ( ! empty( $pack_usage['roles'][ $role_key ] ) ? __( 'renseigné', 'ufsc-clubs' ) : __( 'manquant', 'ufsc-clubs' ) ) . '. ' ); } ?></span>
						</a>
						<a class="ufsc-pack-card" href="<?php echo esc_url( add_query_arg( 'ufsc_pack', 'libre', self::get_club_portal_url( 'licences' ) ) ); ?>">
							<span class="ufsc-pack-card__label"><?php esc_html_e( 'Licences libres', 'ufsc-clubs' ); ?></span>
							<strong class="ufsc-pack-card__value"><?php echo esc_html( sprintf( __( '%d/7', 'ufsc-clubs' ), $pack_usage['libres'] ) ); ?></strong>
							<span class="ufsc-pack-card__detail"><?php esc_html_e( 'Incluses dans le pack après les trois licences du Bureau.', 'ufsc-clubs' ); ?></span>
						</a>
						<a class="ufsc-pack-card" href="<?php echo esc_url( add_query_arg( 'ufsc_pack', 'payante', self::get_club_portal_url( 'licences' ) ) ); ?>">
							<span class="ufsc-pack-card__label"><?php esc_html_e( 'Licences supplémentaires', 'ufsc-clubs' ); ?></span>
							<strong class="ufsc-pack-card__value"><?php echo esc_html( (int) $pack_usage['payantes'] ); ?></strong>
							<span class="ufsc-pack-card__detail"><?php esc_html_e( 'Licences payantes au-delà des dix licences du pack.', 'ufsc-clubs' ); ?></span>
						</a>
					</section>


                    </div>
                </div>

                <div class="ufsc-dashboard-mainpane">
                    <nav class="ufsc-club-account__nav ufsc-club-portal__nav" aria-label="<?php esc_attr_e( 'Navigation Compte Club', 'ufsc-clubs' ); ?>">
                        <a aria-current="page" href="<?php echo esc_url( self::get_club_portal_url( 'overview' ) ); ?>"><?php esc_html_e( 'Vue d’ensemble', 'ufsc-clubs' ); ?></a>
                        <a href="<?php echo esc_url( self::get_club_portal_url( 'club-information' ) ); ?>"><?php esc_html_e( 'Informations du club', 'ufsc-clubs' ); ?></a>
                        <a href="<?php echo esc_url( self::get_club_portal_url( 'club-officers' ) ); ?>"><?php esc_html_e( 'Dirigeants', 'ufsc-clubs' ); ?></a>
                        <a href="<?php echo esc_url( self::get_club_portal_url( 'club-documents' ) ); ?>"><?php esc_html_e( 'Documents', 'ufsc-clubs' ); ?></a>
                        <a href="<?php echo esc_url( self::get_club_portal_url( 'licences-archives' ) ); ?>"><?php esc_html_e( 'Archives licences', 'ufsc-clubs' ); ?></a>
                        <a href="<?php echo esc_url( self::SPORTS_RULES_URL ); ?>"><?php esc_html_e( 'Règlements sportifs', 'ufsc-clubs' ); ?></a>
                    </nav>
                    <div class="ufsc-season-card ufsc-card">
                        <div>
                            <span class="ufsc-kpi-tile-label"><?php esc_html_e( 'Saison UFSC', 'ufsc-clubs' ); ?></span>
                            <strong><?php echo esc_html( $current_season ); ?></strong>
                            <?php if ( $season_start && $season_end ) : ?>
                                <p><?php echo esc_html( sprintf( __( 'Validité : %s au %s', 'ufsc-clubs' ), mysql2date( 'd/m/Y', $season_start ), mysql2date( 'd/m/Y', $season_end ) ) ); ?></p>
                            <?php endif; ?>
                            <p><?php echo esc_html( sprintf( __( 'Affiliation %1$s : %2$s', 'ufsc-clubs' ), $current_season, ! empty( $licence_affiliation_gate['allowed'] ) ? __( 'Active', 'ufsc-clubs' ) : ( 'affiliation_missing' === ( $licence_affiliation_gate['code'] ?? '' ) ? __( 'Non souscrite', 'ufsc-clubs' ) : ( $licence_affiliation_gate['message'] ?? __( 'Indéterminée', 'ufsc-clubs' ) ) ) ) ); ?></p>
                            <p class="ufsc-admin-help"><?php esc_html_e( 'L’état administratif permanent du club et son affiliation annuelle sont deux informations distinctes.', 'ufsc-clubs' ); ?></p>
                        </div>
                        <?php
                        $affiliation_view = array(
                            'state'        => 'renewal_unavailable',
                            'badge_class'  => 'ufsc-badge-warning',
                            'message'      => function_exists( 'ufsc_get_affiliation_product_unavailable_message' ) ? ufsc_get_affiliation_product_unavailable_message( $affiliation_product_diagnostic['unavailable_reason'] ?? '' ) : __( 'Le renouvellement en ligne est temporairement indisponible. Veuillez contacter l’UFSC.', 'ufsc-clubs' ),
                            'url'          => '',
                            'button_label' => '',
                            'show_product' => false,
                            'show_admin_diagnostic' => true,
                        );

                        switch ( true ) {
                            case $renewal_affiliation_done:
                                $affiliation_view = array(
                                    'state'        => 'active',
                                    'badge_class'  => 'ufsc-badge-success',
                                    'message'      => sprintf( __( 'Affiliation %s active', 'ufsc-clubs' ), $renewal_affiliation_season ),
                                    'url'          => '',
                                    'button_label' => '',
                                    'show_product' => false,
                                    'show_admin_diagnostic' => false,
                                );
                                break;
                            case $affiliation_pending && 'paid' === sanitize_key( (string) $annual_affiliation->payment_status ):
                                $affiliation_view = array(
                                    'state'        => 'pending_validation',
                                    'badge_class'  => 'ufsc-badge-warning',
                                    'message'      => __( 'Affiliation en attente de validation', 'ufsc-clubs' ),
                                    'url'          => '',
                                    'button_label' => '',
                                    'show_product' => false,
                                    'show_admin_diagnostic' => false,
                                );
                                break;
                            case $affiliation_pending || $pending_order:
                                $affiliation_view = array(
                                    'state'        => 'pending_payment',
                                    'badge_class'  => 'ufsc-badge-warning',
                                    'message'      => __( 'Renouvellement en cours', 'ufsc-clubs' ),
                                    'url'          => $pending_payment_url ? $pending_payment_url : '',
                                    'button_label' => $pending_payment_url ? __( 'Finaliser mon paiement', 'ufsc-clubs' ) : '',
                                    'show_product' => false,
                                    'show_admin_diagnostic' => false,
                                );
                                break;
                            case $can_renew_affiliation && $renewal_url:
                                $affiliation_view = array(
                                    'state'        => 'renewal_available',
                                    'badge_class'  => 'ufsc-badge-warning',
                                    'message'      => sprintf( __( 'Votre club n’est pas encore affilié pour la saison %s. Vérifiez vos informations puis procédez au renouvellement de votre affiliation.', 'ufsc-clubs' ), $renewal_affiliation_season ),
                                    'url'          => $renewal_url,
                                    'button_label' => sprintf( __( 'Renouveler mon affiliation %s', 'ufsc-clubs' ), $renewal_affiliation_season ),
                                    'show_product' => true,
                                    'show_admin_diagnostic' => false,
                                );
                                break;
                            case in_array( $affiliation_state['action'], array( 'wait', 'contact', 'correct' ), true ):
                                $affiliation_view['message'] = $affiliation_state['label'] . ' — ' . __( 'suivez les consignes UFSC pour votre dossier annuel.', 'ufsc-clubs' );
                                $affiliation_view['show_admin_diagnostic'] = false;
                                break;
                            case ! $renew_window_open:
                                $affiliation_view['message'] = __( 'La période de renouvellement d’affiliation n’est pas encore ouverte.', 'ufsc-clubs' );
                                $affiliation_view['show_admin_diagnostic'] = false;
                                break;
                        }
                        ?>
                        <div class="ufsc-affiliation-renewal-alert" data-affiliation-cta-state="<?php echo esc_attr( $affiliation_view['state'] ); ?>">
                            <?php if ( in_array( $affiliation_view['state'], array( 'renewal_available', 'renewal_unavailable' ), true ) ) : ?>
                                <h3><?php echo esc_html( sprintf( __( 'Affiliation %s à renouveler', 'ufsc-clubs' ), $renewal_affiliation_season ) ); ?></h3>
                            <?php endif; ?>
                            <span class="ufsc-badge <?php echo esc_attr( $affiliation_view['badge_class'] ); ?>"><?php echo esc_html( $affiliation_view['message'] ); ?></span>
                            <?php if ( ! empty( $affiliation_view['url'] ) && ! empty( $affiliation_view['button_label'] ) ) : ?>
                                <a class="ufsc-btn ufsc-btn-primary ufsc-btn-xl" href="<?php echo esc_url( $affiliation_view['url'] ); ?>"><?php echo esc_html( $affiliation_view['button_label'] ); ?></a>
                            <?php endif; ?>
                            <?php if ( ! empty( $affiliation_view['show_product'] ) ) : ?>
                                <span class="ufsc-kpi-tile-label"><?php esc_html_e( 'Produit WooCommerce : Pack Affiliation UFSC / FSASPTT', 'ufsc-clubs' ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $affiliation_view['show_admin_diagnostic'] ) && current_user_can( 'manage_options' ) ) : ?>
                                <p class="ufsc-admin-help"><?php echo esc_html( function_exists( 'ufsc_get_woocommerce_product_diagnostic_message' ) ? ufsc_get_woocommerce_product_diagnostic_message( $affiliation_product_id ) : '' ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ufsc-dashboard-nav">
                        <?php if ( in_array( 'stats', $sections ) ): ?>
                            <button type="button" class="ufsc-nav-btn<?php echo 'stats' === $requested_dashboard_section ? ' active' : ''; ?>" data-section="stats"<?php echo 'stats' === $requested_dashboard_section ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Statistiques', 'ufsc-clubs' ); ?></button>
                        <?php endif; ?>
                        <?php if ( in_array( 'profile', $sections ) ): ?>
                            <button type="button" class="ufsc-nav-btn<?php echo 'profile' === $requested_dashboard_section ? ' active' : ''; ?>" data-section="profile"<?php echo 'profile' === $requested_dashboard_section ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Mon Club', 'ufsc-clubs' ); ?></button>
                        <?php endif; ?>
                        <?php if ( in_array( 'add_licence', $sections ) ): ?>
                            <button type="button" class="ufsc-nav-btn<?php echo 'add_licence' === $requested_dashboard_section ? ' active' : ''; ?>" data-section="add_licence"<?php echo 'add_licence' === $requested_dashboard_section ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Ajouter une Licence', 'ufsc-clubs' ); ?></button>
                        <?php endif; ?>
                        <?php if ( in_array( 'licences', $sections ) ): ?>
                            <button type="button" class="ufsc-nav-btn<?php echo 'licences' === $requested_dashboard_section ? ' active' : ''; ?>" data-section="licences"<?php echo 'licences' === $requested_dashboard_section ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Mes licences UFSC', 'ufsc-clubs' ); ?></button>
                        <?php endif; ?>
                    </div>
                    <div class="ufsc-dashboard-content">
                        <?php if ( in_array( 'licences', $sections ) ): ?>
                            <div id="ufsc-club-licences" class="ufsc-dashboard-section<?php echo 'licences' === $requested_dashboard_section ? ' active' : ''; ?>">
                                <?php echo self::render_club_licences( array( 'club_id' => $club_id, 'readonly' => false ) ); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( in_array( 'stats', $sections ) ): ?>
                            <div id="ufsc-section-stats" class="ufsc-dashboard-section<?php echo 'stats' === $requested_dashboard_section ? ' active' : ''; ?>">
                                <?php echo self::render_club_stats( array( 'club_id' => $club_id ) ); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( in_array( 'profile', $sections ) ): ?>
                            <div id="ufsc-section-profile" class="ufsc-dashboard-section<?php echo 'profile' === $requested_dashboard_section ? ' active' : ''; ?>">
                                <?php echo self::render_club_profile( array( 'club_id' => $club_id ) ); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( in_array( 'add_licence', $sections ) ): ?>
                            <div id="ufsc-section-add_licence" class="ufsc-dashboard-section<?php echo 'add_licence' === $requested_dashboard_section ? ' active' : ''; ?>">
                                <?php echo self::render_add_licence( array( 'club_id' => $club_id ) ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var dashboard = $('#ufsc-dashboard');
            if (dashboard.data('ufscDashboardInitialized')) return;
            dashboard.data('ufscDashboardInitialized', true);
            dashboard.find('.ufsc-nav-btn').on('click.ufscDashboard', function() {
                var section = $(this).data('section');

                // Update nav
                dashboard.find('.ufsc-nav-btn').removeClass('active').removeAttr('aria-current');
                dashboard.find('.ufsc-nav-btn[data-section="' + section + '"]').addClass('active').attr('aria-current', 'page');

                // Show section
                dashboard.find('.ufsc-dashboard-section').removeClass('active');
                dashboard.find(section === 'licences' ? '#ufsc-club-licences' : '#ufsc-section-' + section).addClass('active');
            });

            var ctx = document.getElementById('ufsc-licence-chart');
            if (ctx && typeof ufscLicenceStats !== 'undefined' && typeof Chart !== 'undefined' && !(Chart.getChart && Chart.getChart(ctx))) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ufscLicenceStats.labels,
                        datasets: [{
                            label: ufscLicenceStats.datasetLabel,
                            data: ufscLicenceStats.data,
                            backgroundColor: ['#36a2eb', '#4caf50', '#fff756ff', '#36dbf4ff', '#f436e4ff', '#b136f4ff', '#36f4b5ff', '#f44936ff']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }

            var ctx = document.getElementById('ufsc-licence-year-chart');
            if (ctx && typeof ufscLicenceStatsYear !== 'undefined' && typeof Chart !== 'undefined' && !(Chart.getChart && Chart.getChart(ctx))) {

                const dataObj = ufscLicenceStatsYear.data;

                // Trier les clés (années) numériquement
                const sortedYears = Object.keys(dataObj)
                    .map(year => parseInt(year))
                    .sort((a, b) => a - b);

                const labels = sortedYears.map(year => year.toString());
                const data = sortedYears.map(year => dataObj[year]);

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: ufscLicenceStatsYear.datasetLabel,
                            data: data,
                            backgroundColor: '#36a2eb',
                            borderColor: '#2a7bbd',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                title: { display: true, text: 'Année de naissance' }
                            },
                            y: {
                                title: { display: true, text: 'Nombre de licences' },
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        },
                        animation: { duration: 0 },
                        hover: { animationDuration: 0 }
                    }
                });
            }

        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render club licences section
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_club_licences( $atts = array() ) {
        wp_enqueue_style( 'ufsc-front', UFSC_CL_URL . 'assets/css/ufsc-front.css', array(), function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/css/ufsc-front.css' ) : UFSC_CL_VERSION );
        $atts = shortcode_atts( array(
            'club_id' => 0,
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => '',
            'sort' => 'created_desc',
            'readonly' => false,
        ), $atts );

        $readonly = filter_var( $atts['readonly'], FILTER_VALIDATE_BOOLEAN );

        if ( ! $atts['club_id'] && is_user_logged_in() ) {
            $atts['club_id'] = self::get_user_club_id( get_current_user_id() );
        }

        if ( ! $atts['club_id'] ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Club non trouvé.', 'ufsc-clubs' ) .
                   '</div>';
        }
        // Handle pagination and filters from URL
        if ( isset( $_GET['ufsc_page'] ) ) {
            $atts['page'] = max( 1, intval( $_GET['ufsc_page'] ) );
        }
        if ( isset( $_GET['ufsc_status'] ) ) {
            $atts['status'] = sanitize_text_field( $_GET['ufsc_status'] );
        }
        if ( isset( $_GET['ufsc_search'] ) ) {
            $atts['search'] = sanitize_text_field( $_GET['ufsc_search'] );
        }
        if ( isset( $_GET['ufsc_sort'] ) ) {
            $atts['sort'] = sanitize_text_field( $_GET['ufsc_sort'] );
        }
        if ( isset( $_GET['ufsc_per_page'] ) && ! is_array( $_GET['ufsc_per_page'] ) ) {
            $requested_per_page = absint( $_GET['ufsc_per_page'] );
            $atts['per_page'] = in_array( $requested_per_page, array( 10, 20 ), true ) ? $requested_per_page : 20;
        }

        $is_delete_success = isset( $_GET['ufsc_message'] ) && false !== strpos( strtolower( sanitize_text_field( wp_unslash( $_GET['ufsc_message'] ) ) ), 'supprim' );

        if ( ! $is_delete_success && isset( $_GET['view_licence'] ) ) {
            $licence_id = intval( $_GET['view_licence'] );
            return self::render_single_licence( $licence_id, $readonly );
        }

        if ( ! $readonly && ! $is_delete_success && isset( $_GET['edit_licence'] ) ) {
            $licence_id = intval( $_GET['edit_licence'] );
            return self::render_add_licence( array(
                'club_id'    => $atts['club_id'],
                'licence_id' => $licence_id,
            ) );
        }

        $active_season = class_exists( 'UFSC_Season_Service' )
            ? UFSC_Season_Service::get_current_season()
            : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
        $requested_section = isset( $_GET['ufsc_section'] ) && ! is_array( $_GET['ufsc_section'] ) ? sanitize_key( wp_unslash( $_GET['ufsc_section'] ) ) : 'club-licences';
        $archive_filter = '';
        $show_archives = isset( $_GET['ufsc_show_archives'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['ufsc_show_archives'] ) );
        if ( isset( $_GET['ufsc_archive_season'] ) && ! is_array( $_GET['ufsc_archive_season'] ) ) {
            $archive_filter = sanitize_text_field( wp_unslash( $_GET['ufsc_archive_season'] ) );
        }

        // Query each visible collection independently. Archives are never loaded
        // until a canonical season has been selected by the club user.
        $archive_seasons         = self::get_club_archive_seasons( $atts['club_id'], $active_season );
        $available_seasons       = array_values( array_unique( array_filter( array_merge( array( $active_season ), $archive_seasons ) ) ) );
        $selected_season         = isset( $_GET['ufsc_season'] ) && ! is_array( $_GET['ufsc_season'] ) ? sanitize_text_field( wp_unslash( $_GET['ufsc_season'] ) ) : $active_season;
        if ( ! in_array( $selected_season, $available_seasons, true ) ) {
            $selected_season = $active_season;
        }
        $active_args             = $atts;
        $active_args['season']   = $selected_season;
        foreach ( array( 'ufsc_gender' => 'gender', 'ufsc_practice' => 'practice', 'ufsc_age' => 'age', 'ufsc_missing_profile' => 'missing_profile', 'ufsc_birth_from' => 'birth_from', 'ufsc_birth_to' => 'birth_to' ) as $request_key => $arg_key ) {
            $active_args[ $arg_key ] = isset( $_GET[ $request_key ] ) && ! is_array( $_GET[ $request_key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $request_key ] ) ) : '';
        }
        if ( ! in_array( $active_args['gender'], array( '', 'F', 'M' ), true ) ) { $active_args['gender'] = ''; }
        if ( ! in_array( $active_args['practice'], array( '', 'leisure', 'competition' ), true ) ) { $active_args['practice'] = ''; }
        if ( ! in_array( $active_args['age'], array( '', 'minor', 'adult' ), true ) ) { $active_args['age'] = ''; }
        $active_args['missing_profile'] = '1' === $active_args['missing_profile'] ? '1' : '';
        $active_args['per_page'] = max( 1, (int) $atts['per_page'] );
        $active_licences         = self::get_club_licences( $atts['club_id'], $active_args );
        $total_count             = self::get_club_licences_count( $atts['club_id'], $active_args );
        $total_pages             = max( 1, (int) ceil( $total_count / $active_args['per_page'] ) );
        $atts['page']            = min( max( 1, (int) $atts['page'] ), $total_pages );
        if ( (int) $active_args['page'] !== (int) $atts['page'] ) {
            $active_args['page'] = $atts['page'];
            $active_licences     = self::get_club_licences( $atts['club_id'], $active_args );
        }
        $licences       = $active_licences;
        $future_licences = array();

        $archive_per_page = isset( $_GET['ufsc_archive_per_page'] ) ? absint( $_GET['ufsc_archive_per_page'] ) : 10;
        $archive_per_page = in_array( $archive_per_page, array( 10, 20 ), true ) ? $archive_per_page : 10;
        $archive_page     = isset( $_GET['ufsc_archive_page'] ) ? max( 1, absint( $_GET['ufsc_archive_page'] ) ) : 1;
        $archive_total    = 0;
        $archive_licences = array();
        if ( '' !== $archive_filter && in_array( $archive_filter, $archive_seasons, true ) ) {
            $archive_args = array_merge( $atts, array(
                'season'   => $archive_filter,
                'page'     => $archive_page,
                'per_page' => $archive_per_page,
            ) );
            $archive_total = self::get_club_licences_count( $atts['club_id'], $archive_args );
            $archive_pages = max( 1, (int) ceil( $archive_total / $archive_per_page ) );
            $archive_page  = min( $archive_page, $archive_pages );
            $archive_args['page'] = $archive_page;
            $archive_licences = self::get_club_licences( $atts['club_id'], $archive_args );
        } else {
            $archive_filter = '';
        }

        // The renewal assistant only needs the immediately preceding season.
        $active_start = (int) substr( $active_season, 0, 4 );
        $renewal_source_season = $active_start ? ( $active_start - 1 ) . '-' . $active_start : '';
        $renew_filters = self::get_renewal_filters_from_request();
        $renew_page = isset( $_GET['ufsc_renew_page'] ) ? max( 1, absint( $_GET['ufsc_renew_page'] ) ) : 1;
        $renew_per_page = isset( $_GET['ufsc_renew_per_page'] ) && 20 === absint( $_GET['ufsc_renew_per_page'] ) ? 20 : 10;
        $renew_args = array_merge( $atts, $renew_filters, array( 'season' => $renewal_source_season, 'page' => $renew_page, 'per_page' => $renew_per_page ) );
        $renew_total = $renewal_source_season ? self::get_club_licences_count( $atts['club_id'], $renew_args ) : 0;
        $renew_pages = max( 1, (int) ceil( $renew_total / $renew_per_page ) );
        $renew_page = min( $renew_page, $renew_pages ); $renew_args['page'] = $renew_page;
        $renewal_archives = $renewal_source_season ? self::get_club_licences( $atts['club_id'], $renew_args ) : array();
        $direct_source_id = isset( $_GET['renew_source_id'] ) ? absint( $_GET['renew_source_id'] ) : 0;
        if ( $direct_source_id ) {
            $direct_source = self::get_licence( $atts['club_id'], $direct_source_id );
            if ( $direct_source && self::get_licence_display_season( $direct_source ) === $renewal_source_season ) { $renewal_archives = array( $direct_source ); $renew_total = 1; $renew_page = 1; $renew_per_page = 10; }
        }
        $bureau_data = self::get_bureau_coverage_data( (int) $atts['club_id'] );

        $club_name  = self::get_club_name( $atts['club_id'] );
        $wc_settings = ufsc_get_woocommerce_settings();

        ob_start();
        if ( current_user_can( 'manage_options' ) && function_exists( 'ufsc_get_table_diagnostic' ) ) {
            $table_diagnostic = ufsc_get_table_diagnostic();
            echo "<!-- UFSC table diagnostic: source=" . esc_html( $table_diagnostic['source'] ) . "; clubs=" . esc_html( $table_diagnostic['clubs_table'] ) . "; licences=" . esc_html( $table_diagnostic['licences_table'] ) . " -->\n";
        }
        ?>
        <div class="ufsc-licences-section">
            <div class="ufsc-feedback" id="ufsc-feedback" aria-live="polite">
                <?php if ( isset( $_GET['ufsc_message'] ) ) : ?>
                    <div class="ufsc-message ufsc-success"><?php echo esc_html( $_GET['ufsc_message'] ); ?></div>
                <?php elseif ( isset( $_GET['ufsc_error'] ) ) : ?>
                    <div class="ufsc-message ufsc-error"><?php echo esc_html( $_GET['ufsc_error'] ); ?></div>
                    <?php $clean_url = esc_url( remove_query_arg( 'ufsc_error' ) ); ?>
                    <script>
                        if ( window.history.replaceState ) {
                            window.history.replaceState( {}, document.title, '<?php echo $clean_url; ?>' );
                        }
                    </script>
                <?php endif; ?>
            </div>
            <div class="ufsc-licences-workspace">
            <?php if ( 'licences-renouvellement' === $requested_section ) : ?>
                <?php echo self::render_renewal_assistant( $renewal_archives, $atts, $renew_total, $renew_page, $renew_per_page, $renew_filters ); ?>
            <?php elseif ( 'licences-archives' === $requested_section ) : ?>
                <?php echo self::render_archived_licences_section( $archive_licences, $archive_seasons, $archive_filter, $atts, true, $archive_total, $archive_page, $archive_per_page ); ?>
            <?php else : ?>
                <?php echo self::render_current_licences_section( $active_licences, $available_seasons, $selected_season, $atts, $total_count, $total_pages ); ?>
            <?php endif; ?>
            <?php echo self::render_future_licences_section( $future_licences ); ?>
            </div>
        </div>

        <!-- Import Modal -->
        <?php if ( ! $readonly ) : ?>
            <?php echo self::render_import_modal( $atts['club_id'] ); ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /** Sanitize the server-side renewal filters shared by SQL and pagination links. */
    private static function get_renewal_filters_from_request() {
        $filters = array();
        foreach ( array( 'ufsc_renew_search' => 'search', 'ufsc_renew_sex' => 'gender', 'ufsc_renew_practice' => 'practice', 'ufsc_renew_birth_from' => 'birth_from', 'ufsc_renew_birth_to' => 'birth_to', 'ufsc_renew_state' => 'renewal_state' ) as $query => $key ) {
            $value = isset( $_GET[ $query ] ) && ! is_array( $_GET[ $query ] ) ? sanitize_text_field( wp_unslash( $_GET[ $query ] ) ) : '';
            if ( 'gender' === $key && ! in_array( $value, array( 'M', 'F' ), true ) ) { $value = ''; }
            if ( 'practice' === $key && ! in_array( $value, array( 'leisure', 'competition' ), true ) ) { $value = ''; }
            if ( 'renewal_state' === $key && ! in_array( $value, array( 'renewable', 'draft', 'incomplete', 'renewed', 'payable', 'blocked' ), true ) ) { $value = ''; }
            if ( in_array( $key, array( 'birth_from', 'birth_to' ), true ) && '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) { $value = ''; }
            $filters[ $key ] = $value;
        }
        return $filters;
    }

    /** Render complete accessible renewal pagination above and below the table. */
    private static function render_renewal_pagination( $page, $total_pages, $args, $base_url ) {
        $total_pages = max( 1, absint( $total_pages ) ); $page = min( $total_pages, max( 1, absint( $page ) ) );
        if ( $total_pages <= 1 ) { return ''; }
        $link = static function ( $target, $label, $rel = '' ) use ( $args, $base_url ) { $url = add_query_arg( array_merge( $args, array( 'ufsc_renew_page' => $target ) ), $base_url ); return '<a class="ufsc-btn ufsc-btn-secondary"' . ( $rel ? ' rel="' . esc_attr( $rel ) . '"' : '' ) . ' href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>'; };
        $html = '<nav class="ufsc-renewal-pagination" aria-label="' . esc_attr__( 'Pagination des licences à renouveler', 'ufsc-clubs' ) . '">';
        if ( $page > 1 ) { $html .= $link( 1, __( 'Première page', 'ufsc-clubs' ) ) . $link( $page - 1, __( 'Page précédente', 'ufsc-clubs' ), 'prev' ); } else { $html .= '<span class="ufsc-btn ufsc-btn-disabled" aria-disabled="true">' . esc_html__( 'Première page', 'ufsc-clubs' ) . '</span><span class="ufsc-btn ufsc-btn-disabled" aria-disabled="true">' . esc_html__( 'Page précédente', 'ufsc-clubs' ) . '</span>'; }
        for ( $number = 1; $number <= $total_pages; $number++ ) { $html .= $number === $page ? '<span class="ufsc-btn" aria-current="page">' . $number . '</span>' : $link( $number, (string) $number ); }
        if ( $page < $total_pages ) { $html .= $link( $page + 1, __( 'Page suivante', 'ufsc-clubs' ), 'next' ) . $link( $total_pages, __( 'Dernière page', 'ufsc-clubs' ) ); } else { $html .= '<span class="ufsc-btn ufsc-btn-disabled" aria-disabled="true">' . esc_html__( 'Page suivante', 'ufsc-clubs' ) . '</span><span class="ufsc-btn ufsc-btn-disabled" aria-disabled="true">' . esc_html__( 'Dernière page', 'ufsc-clubs' ) . '</span>'; }
        return $html . '</nav>';
    }

    /** Three-step, non-destructive renewal assistant for the immediately previous season. */
    private static function render_renewal_assistant( $archives, $atts, $total_rows = 0, $page = 1, $per_page = 10, $filters = array() ) {
        $target  = UFSC_Season_Service::get_current_season();
        $start   = (int) substr( $target, 0, 4 );
        $source  = $start ? ( $start - 1 ) . '-' . $start : '';
        $club_id = absint( $atts['club_id'] ?? 0 );
        $rows    = array_values( array_filter( (array) $archives, static function ( $row ) use ( $source ) { return self::get_licence_display_season( $row ) === $source; } ) );
        $total_rows = max( 0, absint( $total_rows ) );
        $per_page = in_array( absint( $per_page ), array( 10, 20 ), true ) ? absint( $per_page ) : 10;
        $page = max( 1, absint( $page ) );
        $product_resolution = function_exists( 'ufsc_get_licence_product_resolution' ) ? ufsc_get_licence_product_resolution() : array( 'configured_id' => 0, 'valid' => false );
        $product_id = absint( $product_resolution['configured_id'] ?? 0 );
        $product_ready = ! empty( $product_resolution['valid'] );
        $counts = array( 'renewable' => 0, 'renewed' => 0, 'pending' => 0, 'payable' => 0, 'blocked' => 0 );
        $saved = is_user_logged_in() ? get_transient( 'ufsc_renewal_front_' . get_current_user_id() . '_' . $club_id ) : array();
        $saved = is_array( $saved ) ? $saved : array(); $saved_profiles = (array) ( $saved['profiles'] ?? array() ); $saved_ids = array_map( 'absint', (array) ( $saved['ids'] ?? array() ) );
        $requested_source = isset( $_GET['renew_source_id'] ) ? absint( $_GET['renew_source_id'] ) : 0;
        $requested_step   = isset( $_GET['ufsc_renew_step'] ) ? absint( $_GET['ufsc_renew_step'] ) : 0;
        $requested_target = isset( $_GET['target_season'] ) && ! is_array( $_GET['target_season'] ) ? sanitize_text_field( wp_unslash( $_GET['target_season'] ) ) : '';
        $requested_row    = null;
        if ( $requested_source && ( '' === $requested_target || $requested_target === $target ) ) {
            foreach ( $rows as $candidate ) {
                if ( absint( $candidate->id ?? 0 ) === $requested_source ) {
                    $requested_row = $candidate;
                    break;
                }
            }
        }
        // The fallback URL only preselects a source after the same ownership,
        // season and business checks as its rendered checkbox. It never writes.
        if ( $requested_row ) {
            $requested_context = ufsc_get_licence_season_context_status( $requested_row, $target );
            if ( ! empty( $requested_context['renewal_allowed'] ) ) {
                $saved_ids[] = $requested_source;
                $saved_ids   = array_values( array_unique( $saved_ids ) );
            } else {
                $requested_source = 0;
            }
        } else {
            $requested_source = 0;
        }
        $total_pages = max( 1, (int) ceil( $total_rows / $per_page ) );
        $page = min( $page, $total_pages );
        $pagination_args = array( 'ufsc_section' => 'licences-renouvellement', 'ufsc_renew_source_season' => $source, 'ufsc_renew_per_page' => $per_page, 'ufsc_renew_search' => $filters['search'] ?? '', 'ufsc_renew_sex' => $filters['gender'] ?? '', 'ufsc_renew_practice' => $filters['practice'] ?? '', 'ufsc_renew_birth_from' => $filters['birth_from'] ?? '', 'ufsc_renew_birth_to' => $filters['birth_to'] ?? '', 'ufsc_renew_state' => $filters['renewal_state'] ?? '' );
        $pagination_url = self::get_club_portal_url( 'licences-renouvellement' );
        ob_start(); ?>
        <section class="ufsc-renewal-wizard ufsc-card" data-ufsc-build="<?php echo esc_attr( function_exists( 'ufsc_get_build_id' ) ? ufsc_get_build_id() : UFSC_CL_VERSION ); ?>" aria-labelledby="ufsc-renewal-title">
            <h4 id="ufsc-renewal-title"><?php echo esc_html( sprintf( __( 'Licences à renouveler pour %s', 'ufsc-clubs' ), $target ) ); ?></h4>
            <p class="ufsc-renewal-season-context"><strong><?php esc_html_e( 'Saison d’origine :', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $source ); ?> <span aria-hidden="true">→</span> <strong><?php esc_html_e( 'Saison cible :', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $target ); ?></p>
            <ol class="ufsc-renewal-steps" aria-label="<?php esc_attr_e( 'Étapes du renouvellement', 'ufsc-clubs' ); ?>"><li data-ufsc-step-indicator="1" aria-current="step"><strong>1</strong> <?php esc_html_e( 'Sélectionner', 'ufsc-clubs' ); ?></li><li data-ufsc-step-indicator="2"><strong>2</strong> <?php esc_html_e( 'Vérifier les informations', 'ufsc-clubs' ); ?></li><li data-ufsc-step-indicator="3"><strong>3</strong> <?php esc_html_e( 'Ajouter au panier', 'ufsc-clubs' ); ?></li></ol>
            <form method="get" action="<?php echo esc_url( $pagination_url ); ?>" class="ufsc-renewal-filters" aria-label="<?php esc_attr_e( 'Filtrer les licences à renouveler', 'ufsc-clubs' ); ?>">
                <input type="hidden" name="ufsc_section" value="licences-renouvellement"><input type="hidden" name="ufsc_renew_page" value="1"><input type="hidden" name="ufsc_renew_per_page" value="<?php echo esc_attr( $per_page ); ?>">
                <label><?php esc_html_e( 'Saison', 'ufsc-clubs' ); ?><select name="ufsc_renew_source_season"><option value="<?php echo esc_attr( $source ); ?>" selected><?php echo esc_html( $source ); ?></option></select></label>
                <label><?php esc_html_e( 'Nom ou prénom', 'ufsc-clubs' ); ?><input type="search" name="ufsc_renew_search" value="<?php echo esc_attr( $filters['search'] ?? '' ); ?>"></label>
                <label><?php esc_html_e( 'Sexe', 'ufsc-clubs' ); ?><select name="ufsc_renew_sex"><option value=""><?php esc_html_e( 'Tous', 'ufsc-clubs' ); ?></option><option value="F" <?php selected( $filters['gender'] ?? '', 'F' ); ?>><?php esc_html_e( 'Femme', 'ufsc-clubs' ); ?></option><option value="M" <?php selected( $filters['gender'] ?? '', 'M' ); ?>><?php esc_html_e( 'Homme', 'ufsc-clubs' ); ?></option></select></label>
                <label><?php esc_html_e( 'Pratique', 'ufsc-clubs' ); ?><select name="ufsc_renew_practice"><option value=""><?php esc_html_e( 'Toutes', 'ufsc-clubs' ); ?></option><option value="leisure" <?php selected( $filters['practice'] ?? '', 'leisure' ); ?>><?php esc_html_e( 'Loisir', 'ufsc-clubs' ); ?></option><option value="competition" <?php selected( $filters['practice'] ?? '', 'competition' ); ?>><?php esc_html_e( 'Compétiteur', 'ufsc-clubs' ); ?></option></select></label>
                <label><?php esc_html_e( 'Naissance du', 'ufsc-clubs' ); ?><input type="date" name="ufsc_renew_birth_from" value="<?php echo esc_attr( $filters['birth_from'] ?? '' ); ?>"></label><label><?php esc_html_e( 'au', 'ufsc-clubs' ); ?><input type="date" name="ufsc_renew_birth_to" value="<?php echo esc_attr( $filters['birth_to'] ?? '' ); ?>"></label>
                <label><?php esc_html_e( 'État', 'ufsc-clubs' ); ?><select name="ufsc_renew_state"><option value=""><?php esc_html_e( 'Tous', 'ufsc-clubs' ); ?></option><?php foreach ( array( 'renewable' => __( 'À renouveler', 'ufsc-clubs' ), 'draft' => __( 'Brouillon', 'ufsc-clubs' ), 'incomplete' => __( 'À compléter', 'ufsc-clubs' ), 'renewed' => __( 'Déjà renouvelée', 'ufsc-clubs' ), 'payable' => __( 'Paiement à finaliser', 'ufsc-clubs' ), 'blocked' => __( 'Bloquée', 'ufsc-clubs' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['renewal_state'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <button class="ufsc-btn ufsc-btn-primary" type="submit"><?php esc_html_e( 'Rechercher', 'ufsc-clubs' ); ?></button><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( $pagination_url ); ?>"><?php esc_html_e( 'Réinitialiser les filtres', 'ufsc-clubs' ); ?></a>
            </form>
            <?php if ( empty( $rows ) ) : ?><div class="ufsc-message ufsc-info" role="status"><strong><?php esc_html_e( 'Aucune licence trouvée', 'ufsc-clubs' ); ?></strong><p><?php esc_html_e( 'Modifiez vos critères ou revenez à la liste complète.', 'ufsc-clubs' ); ?></p><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( $pagination_url ); ?>"><?php esc_html_e( 'Réinitialiser les filtres', 'ufsc-clubs' ); ?></a></div><?php else : ?>
            <?php if ( $total_pages > 6 && $total_rows > 60 ) { echo self::render_renewal_pagination( $page, $total_pages, $pagination_args, $pagination_url ); } ?>
            <div class="ufsc-renewal-list-tools">
                <form method="get" action="<?php echo esc_url( $pagination_url ); ?>" class="ufsc-renewal-page-size">
                    <input type="hidden" name="ufsc_section" value="licences-renouvellement">
                    <input type="hidden" name="ufsc_renew_page" value="1">
                    <?php foreach ( array( 'ufsc_renew_search', 'ufsc_renew_sex', 'ufsc_renew_practice', 'ufsc_renew_birth_from', 'ufsc_renew_birth_to', 'ufsc_renew_state' ) as $filter_name ) : if ( ! empty( $pagination_args[ $filter_name ] ) ) : ?><input type="hidden" name="<?php echo esc_attr( $filter_name ); ?>" value="<?php echo esc_attr( $pagination_args[ $filter_name ] ); ?>"><?php endif; endforeach; ?>
                    <label for="ufsc-renew-per-page"><?php esc_html_e( 'Licences par page', 'ufsc-clubs' ); ?></label>
                    <select id="ufsc-renew-per-page" name="ufsc_renew_per_page"><option value="10" <?php selected( $per_page, 10 ); ?>>10</option><option value="20" <?php selected( $per_page, 20 ); ?>>20</option></select>
                    <button type="submit" class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Appliquer', 'ufsc-clubs' ); ?></button>
                </form>
                <p><?php echo esc_html( sprintf( __( 'Page %1$d sur %2$d — %3$d licences. La sélection est limitée à la page courante.', 'ufsc-clubs' ), $page, $total_pages, $total_rows ) ); ?></p>
            </div>
            <?php if ( 'save_draft' === ( $saved['state'] ?? '' ) && $saved_ids ) : ?><aside class="ufsc-message ufsc-info ufsc-renewal-draft-panel" aria-labelledby="ufsc-renewal-draft-title"><strong id="ufsc-renewal-draft-title"><?php esc_html_e( 'Un brouillon de renouvellement existe.', 'ufsc-clubs' ); ?></strong><p><a class="ufsc-btn ufsc-btn-primary" href="<?php echo esc_url( add_query_arg( 'ufsc_renew_step', 2, $pagination_url ) ); ?>"><?php esc_html_e( 'Reprendre le brouillon', 'ufsc-clubs' ); ?></a> <button form="ufsc-renewal-assistant-form" type="submit" name="ufsc_renew_intent" value="cancel" class="ufsc-btn ufsc-btn-secondary" onclick="return window.confirm('<?php echo esc_js( __( 'Supprimer ce brouillon ?', 'ufsc-clubs' ) ); ?>');"><?php esc_html_e( 'Supprimer le brouillon', 'ufsc-clubs' ); ?></button> <a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( $pagination_url ); ?>"><?php esc_html_e( 'Retourner à la liste', 'ufsc-clubs' ); ?></a></p></aside><?php endif; ?>
            <form id="ufsc-renewal-assistant-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-initial-step="<?php echo 2 === $requested_step || $requested_source ? '2' : '1'; ?>">
                <?php wp_nonce_field( 'ufsc_bulk_renew_licences_' . $club_id ); ?><input type="hidden" name="action" value="ufsc_bulk_renew_licences"><input type="hidden" name="ufsc_club_id" value="<?php echo esc_attr( $club_id ); ?>"><input type="hidden" name="ufsc_target_season" value="<?php echo esc_attr( $target ); ?>">
                <div class="ufsc-front-table-scroll<?php echo $requested_source ? ' ufsc-is-hidden' : ''; ?>" <?php echo $requested_source ? 'hidden' : ''; ?> tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Licences renouvelables', 'ufsc-clubs' ); ?>"><table class="ufsc-licence-table ufsc-renewal-table"><thead><tr><th><?php esc_html_e( 'Sélection', 'ufsc-clubs' ); ?></th><th><?php esc_html_e( 'Identité', 'ufsc-clubs' ); ?></th><th><?php esc_html_e( 'N° UFSC', 'ufsc-clubs' ); ?></th><th><?php esc_html_e( 'Niveau sportif', 'ufsc-clubs' ); ?></th><th><?php esc_html_e( 'Poids', 'ufsc-clubs' ); ?></th><th><?php esc_html_e( 'État et action', 'ufsc-clubs' ); ?></th></tr></thead><tbody>
                <?php foreach ( $rows as $row ) :
                    $context = ufsc_get_licence_season_context_status( $row, $target );
                    $source_row = $row; $row = clone $row;
                    foreach ( (array) ( $saved_profiles[absint( $row->id )] ?? array() ) as $saved_field => $saved_value ) { if ( in_array( $saved_field, UFSC_Renewal_Service::editable_renewal_fields(), true ) && ! is_array( $saved_value ) ) { $row->{$saved_field} = $saved_value; } }
                    $state = $context['renewal_state'] ?? 'blocked';
                    $counts[ isset( $counts[ $state ] ) ? $state : 'blocked' ]++;
                    $level = function_exists( 'ufsc_normalize_fighter_level' ) ? ufsc_normalize_fighter_level( $row->fighter_level ?? '' ) : sanitize_key( (string) ( $row->fighter_level ?? '' ) );
                    $weight = trim( (string) ( $row->poids ?? '' ) );
                    $selectable = ! empty( $context['renewal_allowed'] );
                    $required_values = array( 'nom' => $row->nom ?? '', 'prenom' => $row->prenom ?? '', 'email' => $row->email ?? '', 'date_naissance' => $row->date_naissance ?? '', 'sexe' => $row->sexe ?? '', 'adresse' => $row->adresse ?? '', 'ville' => $row->ville ?? '', 'code_postal' => $row->code_postal ?? '', 'fighter_level' => $level, 'poids' => $weight );
                    $missing_fields = array_keys( array_filter( $required_values, static function ( $value ) { return '' === trim( (string) $value ); } ) );
                    $complete = empty( $missing_fields );
                    // Incompleteness is a verification concern, never a selection ban.
                    $decision = array(
                        'selectable' => ! empty( $context['renewal_allowed'] ),
                        'complete' => (bool) $complete,
                        'cart_eligible' => ! empty( $context['renewal_allowed'] ) && $product_ready && (bool) $complete,
                        'blocked' => empty( $context['renewal_allowed'] ),
                        'block_code' => empty( $context['renewal_allowed'] ) ? sanitize_key( (string) ( $context['renewal_state'] ?? 'blocked' ) ) : '',
                        'block_message' => empty( $context['renewal_allowed'] ) ? (string) ( $context['renewal_reason'] ?? __( 'Renouvellement indisponible.', 'ufsc-clubs' ) ) : '',
                    );
                    $reason_id = 'ufsc-renewal-reason-' . absint( $row->id ); ?>
                    <?php $checkbox_id = 'ufsc-renew-' . absint( $row->id ); $fallback_url = add_query_arg( array( 'ufsc_section' => 'licences-renouvellement', 'renew_source_id' => absint( $row->id ), 'target_season' => $target ), self::get_club_portal_url( 'licences-renouvellement' ) ); ?>
                    <tr class="ufsc-renewal-source-row ufsc-renewal-card" data-source-id="<?php echo esc_attr( $row->id ); ?>" data-selectable="<?php echo $decision['selectable'] ? '1' : '0'; ?>" data-complete="<?php echo $decision['complete'] ? '1' : '0'; ?>" data-cart-eligible="<?php echo $decision['cart_eligible'] ? '1' : '0'; ?>" data-blocked="<?php echo $decision['blocked'] ? '1' : '0'; ?>" data-block-code="<?php echo esc_attr( $decision['block_code'] ); ?>"><td data-label="<?php esc_attr_e( 'Sélection', 'ufsc-clubs' ); ?>"><label class="ufsc-renewal-selection-control" for="<?php echo esc_attr( $checkbox_id ); ?>"><input id="<?php echo esc_attr( $checkbox_id ); ?>" class="ufsc-renew-checkbox ufsc-renewal-checkbox" type="checkbox" name="ufsc_renew_ids[]" value="<?php echo esc_attr( $row->id ); ?>" <?php disabled( ! $decision['selectable'] ); ?> <?php checked( in_array( absint( $row->id ), $saved_ids, true ) ); ?> aria-describedby="<?php echo esc_attr( $reason_id ); ?>"><span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Sélectionner %1$s %2$s', 'ufsc-clubs' ), $row->prenom ?? '', $row->nom ?? '' ) ); ?></span></label></td><td data-label="<?php esc_attr_e( 'Identité', 'ufsc-clubs' ); ?>"><?php echo esc_html( trim( ( $row->prenom ?? '' ) . ' ' . ( $row->nom ?? '' ) ) ); ?></td><td data-label="<?php esc_attr_e( 'N° UFSC', 'ufsc-clubs' ); ?>"><?php echo esc_html( UFSC_Identifier_Resolver::read( $source_row, 'licence_ufsc' ) ?: '—' ); ?></td><td data-label="<?php esc_attr_e( 'Niveau', 'ufsc-clubs' ); ?>"><?php echo esc_html( ufsc_fighter_level_label( $level ) ); ?></td><td data-label="<?php esc_attr_e( 'Poids', 'ufsc-clubs' ); ?>"><?php echo esc_html( '' !== $weight ? $weight . ' kg' : __( 'Poids manquant', 'ufsc-clubs' ) ); ?></td><td data-label="<?php esc_attr_e( 'État et action', 'ufsc-clubs' ); ?>"><?php if ( $selectable && ! $complete ) : ?><span class="ufsc-badge ufsc-badge-warning"><?php esc_html_e( 'Dossier incomplet', 'ufsc-clubs' ); ?></span><?php else : ?><span class="ufsc-badge <?php echo esc_attr( $context['badge_class'] ?? 'ufsc-badge-neutral' ); ?>"><?php echo esc_html( $context['action_label'] ?? $context['label'] ?? '' ); ?></span><?php endif; ?><div id="<?php echo esc_attr( $reason_id ); ?>" class="ufsc-renewal-reason"><?php echo ! $selectable ? esc_html( $context['renewal_reason'] ?? __( 'Renouvellement indisponible.', 'ufsc-clubs' ) ) : esc_html( $complete ? __( 'Dossier prêt à vérifier.', 'ufsc-clubs' ) : __( 'À compléter à l’étape suivante', 'ufsc-clubs' ) ); ?><?php if ( $selectable && $missing_fields ) : ?><small class="ufsc-renewal-missing"><?php echo esc_html( sprintf( __( 'Champs manquants : %s', 'ufsc-clubs' ), implode( ', ', $missing_fields ) ) ); ?></small><?php endif; ?></div><?php if ( $selectable ) : ?><a href="<?php echo esc_url( $fallback_url ); ?>" class="ufsc-renewal-button ufsc-btn ufsc-btn-primary ufsc-btn-small" data-ufsc-renew-one="<?php echo esc_attr( $row->id ); ?>"><strong><?php esc_html_e( 'Renouveler', 'ufsc-clubs' ); ?></strong><small><?php echo esc_html( $target ); ?></small></a><?php endif; ?></td></tr>
                    <?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( sprintf( 'UFSC renewal checkbox licence_id=%d selectable=%s complete=%s eligible_for_cart=%s blocked=%s reason=%s disabled=%s', absint( $row->id ), $decision['selectable'] ? 'true' : 'false', $decision['complete'] ? 'true' : 'false', $decision['cart_eligible'] ? 'true' : 'false', $decision['blocked'] ? 'true' : 'false', (string) $decision['block_message'], $decision['selectable'] ? 'false' : 'true' ) ); } ?>
                    <?php if ( $selectable ) : $prefix = 'renewal_profiles[' . absint( $row->id ) . ']'; ?>
                    <?php $show_profile = $requested_source === absint( $row->id ) || ( 2 === $requested_step && in_array( absint( $row->id ), $saved_ids, true ) ); ?><tr class="ufsc-renewal-profile-row<?php echo $show_profile ? '' : ' ufsc-is-hidden'; ?>" <?php echo $show_profile ? '' : 'hidden'; ?> data-profile-id="<?php echo esc_attr( $row->id ); ?>"><td colspan="6"><details class="ufsc-renewal-profile"><summary id="ufsc-renewal-profile-title-<?php echo esc_attr( $row->id ); ?>" tabindex="-1"><?php echo esc_html( sprintf( __( 'Vérifier les informations de %1$s %2$s', 'ufsc-clubs' ), $row->prenom ?? '', $row->nom ?? '' ) ); ?></summary>
                        <p class="ufsc-message ufsc-info"><?php esc_html_e( 'Ces nouvelles valeurs seront utilisées uniquement pour la nouvelle licence annuelle. La licence archivée reste intacte.', 'ufsc-clubs' ); ?></p><div class="ufsc-renewal-completeness <?php echo $complete ? 'ufsc-success' : 'ufsc-warning'; ?>" role="status" data-ufsc-completeness><strong><?php echo $complete ? esc_html__( 'Dossier complet', 'ufsc-clubs' ) : esc_html__( 'Dossier incomplet', 'ufsc-clubs' ); ?></strong><?php if ( $missing_fields ) : ?><span><?php echo esc_html( sprintf( __( 'À renseigner : %s', 'ufsc-clubs' ), implode( ', ', $missing_fields ) ) ); ?></span><?php endif; ?></div><div class="ufsc-renewal-profile-grid"><label><?php esc_html_e( 'Numéro de licence antérieur', 'ufsc-clubs' ); ?><input type="text" value="<?php echo esc_attr( UFSC_Identifier_Resolver::read( $source_row, 'licence_ufsc' ) ); ?>" readonly></label></div>
                        <div class="ufsc-renewal-profile-grid">
                        <?php foreach ( array( 'adresse' => __( 'Adresse', 'ufsc-clubs' ), 'suite_adresse' => __( 'Complément d’adresse', 'ufsc-clubs' ), 'code_postal' => __( 'Code postal', 'ufsc-clubs' ), 'ville' => __( 'Ville', 'ufsc-clubs' ), 'pays' => __( 'Pays', 'ufsc-clubs' ), 'profession' => __( 'Profession', 'ufsc-clubs' ), 'contact_urgence' => __( 'Contact d’urgence', 'ufsc-clubs' ), 'legal_representative_name' => __( 'Représentant légal', 'ufsc-clubs' ) ) as $field => $label ) : ?><label><?php echo esc_html( $label ); ?><input type="text" name="<?php echo esc_attr( $prefix . '[' . $field . ']' ); ?>" value="<?php echo esc_attr( $row->{$field} ?? '' ); ?>"><small><?php echo esc_html( sprintf( __( 'Ancienne valeur : %s', 'ufsc-clubs' ), $row->{$field} ?? '—' ) ); ?></small></label><?php endforeach; ?>
                        <label><?php esc_html_e( 'Adresse e-mail', 'ufsc-clubs' ); ?><input type="email" name="<?php echo esc_attr( $prefix . '[email]' ); ?>" value="<?php echo esc_attr( $row->email ?? '' ); ?>"></label>
                        <label><?php esc_html_e( 'Téléphone fixe', 'ufsc-clubs' ); ?><input type="tel" name="<?php echo esc_attr( $prefix . '[tel_fixe]' ); ?>" value="<?php echo esc_attr( $row->tel_fixe ?? '' ); ?>"></label>
                        <label><?php esc_html_e( 'Téléphone mobile', 'ufsc-clubs' ); ?><input type="tel" name="<?php echo esc_attr( $prefix . '[tel_mobile]' ); ?>" value="<?php echo esc_attr( $row->tel_mobile ?? '' ); ?>"></label>
                        <label><?php esc_html_e( 'Téléphone principal', 'ufsc-clubs' ); ?><input type="tel" name="<?php echo esc_attr( $prefix . '[telephone]' ); ?>" value="<?php echo esc_attr( $row->telephone ?? '' ); ?>"></label>
                        <label><?php esc_html_e( 'Rôle dans le club', 'ufsc-clubs' ); ?><input type="text" name="<?php echo esc_attr( $prefix . '[role]' ); ?>" value="<?php echo esc_attr( $row->role ?? '' ); ?>"></label>
                        <label><?php esc_html_e( 'Type de pratique / discipline', 'ufsc-clubs' ); ?><input type="text" name="<?php echo esc_attr( $prefix . '[pratique]' ); ?>" value="<?php echo esc_attr( $row->pratique ?? $row->discipline ?? '' ); ?>"></label>
                        <label><?php esc_html_e( 'Niveau sportif', 'ufsc-clubs' ); ?><select name="<?php echo esc_attr( $prefix . '[fighter_level]' ); ?>"><option value=""><?php esc_html_e( 'Non renseigné', 'ufsc-clubs' ); ?></option><?php foreach ( ufsc_get_sport_level_options() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $level, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><small><?php echo esc_html( ufsc_get_sport_level_help() ); ?></small></label>
                        <label><?php esc_html_e( 'Poids déclaratif courant (kg)', 'ufsc-clubs' ); ?><input type="text" inputmode="decimal" pattern="[0-9]+([,.][0-9]+)?" name="<?php echo esc_attr( $prefix . '[poids]' ); ?>" value="<?php echo esc_attr( $weight ); ?>"><small><?php esc_html_e( 'La pesée officielle historique n’est jamais modifiée.', 'ufsc-clubs' ); ?></small></label>
                        <label><input type="checkbox" name="<?php echo esc_attr( $prefix . '[competition]' ); ?>" value="1" <?php checked( ! empty( $row->competition ) ); ?>> <?php esc_html_e( 'Pratique en compétition', 'ufsc-clubs' ); ?></label>
                        </div>
                        <fieldset><legend><?php esc_html_e( 'Réductions et identifiants', 'ufsc-clubs' ); ?></legend><div class="ufsc-renewal-profile-grid">
                        <?php foreach ( array( 'reduction_benevole' => __( 'Réduction bénévole', 'ufsc-clubs' ), 'reduction_postier' => __( 'Réduction postier', 'ufsc-clubs' ), 'identifiant_laposte_flag' => __( 'Identifiant La Poste', 'ufsc-clubs' ), 'fonction_publique' => __( 'Fonction publique', 'ufsc-clubs' ), 'licence_delegataire' => __( 'Licence délégataire', 'ufsc-clubs' ) ) as $field => $label ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $prefix . '[' . $field . ']' ); ?>" value="1" <?php checked( ! empty( $row->{$field} ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?>
                        <label><?php esc_html_e( 'Numéro bénévole', 'ufsc-clubs' ); ?><input type="text" name="<?php echo esc_attr( $prefix . '[reduction_benevole_num]' ); ?>" value="<?php echo esc_attr( $row->reduction_benevole_num ?? '' ); ?>"></label><label><?php esc_html_e( 'Matricule postier', 'ufsc-clubs' ); ?><input type="text" name="<?php echo esc_attr( $prefix . '[reduction_postier_num]' ); ?>" value="<?php echo esc_attr( $row->reduction_postier_num ?? '' ); ?>"></label><label><?php esc_html_e( 'Identifiant La Poste', 'ufsc-clubs' ); ?><input type="text" name="<?php echo esc_attr( $prefix . '[identifiant_laposte]' ); ?>" value="<?php echo esc_attr( $row->identifiant_laposte ?? '' ); ?>"></label></div></fieldset>
                        <fieldset><legend><?php esc_html_e( 'Consentements, conformité et assurances', 'ufsc-clubs' ); ?></legend><div class="ufsc-renewal-profile-grid"><?php foreach ( array( 'diffusion_image' => __( 'Autoriser la diffusion d’image', 'ufsc-clubs' ), 'infos_fsasptt' => __( 'Recevoir les informations FSASPTT', 'ufsc-clubs' ), 'infos_asptt' => __( 'Recevoir les informations ASPTT', 'ufsc-clubs' ), 'infos_cr' => __( 'Recevoir les informations du CR', 'ufsc-clubs' ), 'infos_partenaires' => __( 'Recevoir les informations partenaires', 'ufsc-clubs' ), 'honorabilite' => __( 'Je certifie mon honorabilité', 'ufsc-clubs' ), 'honorability_confirmed' => __( 'Honorabilité confirmée', 'ufsc-clubs' ), 'assurance_dommage_corporel' => __( 'Assurance dommage corporel', 'ufsc-clubs' ), 'assurance_assistance' => __( 'Assurance assistance', 'ufsc-clubs' ), 'health_questionnaire_confirmed' => __( 'Questionnaire de santé consulté', 'ufsc-clubs' ) ) as $field => $label ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $prefix . '[' . $field . ']' ); ?>" value="1" <?php checked( ! empty( $row->{$field} ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?><label><?php esc_html_e( 'Note', 'ufsc-clubs' ); ?><textarea name="<?php echo esc_attr( $prefix . '[note]' ); ?>" rows="3"><?php echo esc_textarea( $row->note ?? '' ); ?></textarea></label></div></fieldset>
                        <div class="ufsc-renewal-change-summary" aria-live="polite"><strong><?php echo esc_html( sprintf( __( 'Informations mises à jour pour la saison %s', 'ufsc-clubs' ), $target ) ); ?></strong><ul></ul></div>
                        <fieldset class="ufsc-sensitive-identity"><legend><?php esc_html_e( 'Identité principale — modification sensible', 'ufsc-clubs' ); ?></legend><p><?php esc_html_e( 'Toute modification déclenche une correction administrative. Le numéro UFSC et l’identifiant de personne restent inchangés.', 'ufsc-clubs' ); ?></p><div class="ufsc-renewal-profile-grid">
                        <?php foreach ( array( 'nom' => __( 'Nom', 'ufsc-clubs' ), 'prenom' => __( 'Prénom', 'ufsc-clubs' ), 'date_naissance' => __( 'Date de naissance', 'ufsc-clubs' ), 'sexe' => __( 'Sexe', 'ufsc-clubs' ) ) as $field => $label ) : ?><label><?php echo esc_html( $label ); ?><input type="<?php echo 'date_naissance' === $field ? 'date' : 'text'; ?>" name="<?php echo esc_attr( $prefix . '[' . $field . ']' ); ?>" value="<?php echo esc_attr( $row->{$field} ?? '' ); ?>"></label><?php endforeach; ?></div><label><input type="checkbox" name="<?php echo esc_attr( $prefix . '[confirm_identity_change]' ); ?>" value="1"> <?php esc_html_e( 'Je confirme avoir vérifié toute modification d’identité.', 'ufsc-clubs' ); ?></label></fieldset>
                    </details></td></tr><?php endif; ?>
                <?php endforeach; ?></tbody></table></div>
                <div class="ufsc-renewal-summary" aria-live="polite"><strong><?php esc_html_e( 'Résumé', 'ufsc-clubs' ); ?></strong> — <?php echo esc_html( sprintf( __( '%1$d à renouveler, %2$d déjà renouvelées, %3$d demandes en cours, %4$d paiements à finaliser, %5$d bloquées.', 'ufsc-clubs' ), $counts['renewable'], $counts['renewed'], $counts['pending'], $counts['payable'], $counts['blocked'] ) ); ?> <span data-ufsc-selection-count><?php esc_html_e( '0 sélectionnée', 'ufsc-clubs' ); ?></span></div>
                <noscript><p class="ufsc-message ufsc-info"><?php esc_html_e( 'Sans JavaScript, sélectionnez les licences puis soumettez la sélection pour ouvrir la vérification serveur.', 'ufsc-clubs' ); ?></p></noscript><div class="ufsc-renewal-actions<?php echo $requested_source ? ' ufsc-is-hidden' : ''; ?>" <?php echo $requested_source ? 'hidden' : ''; ?> data-ufsc-step-actions="1"><button type="button" class="ufsc-btn ufsc-btn-secondary" data-ufsc-select-all><?php esc_html_e( 'Tout sélectionner', 'ufsc-clubs' ); ?></button><button type="button" class="ufsc-btn ufsc-btn-secondary" data-ufsc-select-none><?php esc_html_e( 'Tout désélectionner', 'ufsc-clubs' ); ?></button><button type="submit" name="ufsc_renew_intent" value="save_draft" formnovalidate class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Enregistrer en brouillon', 'ufsc-clubs' ); ?></button><button type="submit" name="ufsc_renew_intent" value="cancel" formnovalidate class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Annuler et revenir à la liste', 'ufsc-clubs' ); ?></button><button type="submit" name="ufsc_renew_intent" value="verify" class="ufsc-btn ufsc-btn-primary" data-ufsc-next-step="2"><?php esc_html_e( 'Continuer vers la vérification', 'ufsc-clubs' ); ?></button></div>
                <div class="ufsc-renewal-actions<?php echo ( $requested_source || 2 === $requested_step ) ? '' : ' ufsc-is-hidden'; ?>" <?php echo ( $requested_source || 2 === $requested_step ) ? '' : 'hidden'; ?> data-ufsc-step-actions="2"><button type="button" class="ufsc-btn ufsc-btn-secondary" data-ufsc-next-step="1"><?php esc_html_e( 'Précédent', 'ufsc-clubs' ); ?></button><button type="submit" name="ufsc_renew_intent" value="save_draft" formnovalidate class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Enregistrer en brouillon', 'ufsc-clubs' ); ?></button><button type="submit" name="ufsc_renew_intent" value="cancel" formnovalidate class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Annuler', 'ufsc-clubs' ); ?></button><button type="button" class="ufsc-btn ufsc-btn-primary" data-ufsc-next-step="3"><?php esc_html_e( 'Continuer', 'ufsc-clubs' ); ?></button></div>
                <div class="ufsc-renewal-final-review ufsc-is-hidden" hidden data-ufsc-step-review="3" aria-live="polite"><h5 data-ufsc-review-title><?php esc_html_e( 'Vérification finale', 'ufsc-clubs' ); ?></h5><p data-ufsc-review-status></p><ul></ul></div>
                <div class="ufsc-renewal-actions ufsc-is-hidden" hidden data-ufsc-step-actions="3"><button type="button" class="ufsc-btn ufsc-btn-secondary" data-ufsc-next-step="2"><?php esc_html_e( 'Précédent', 'ufsc-clubs' ); ?></button><button type="submit" name="ufsc_renew_intent" value="save_draft" formnovalidate class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Enregistrer en brouillon', 'ufsc-clubs' ); ?></button><button type="submit" name="ufsc_renew_intent" value="add_to_cart" class="ufsc-btn ufsc-btn-primary" <?php disabled( ! $product_ready ); ?> data-ufsc-product-ready="<?php echo $product_ready ? '1' : '0'; ?>" aria-describedby="ufsc-cart-readiness"><?php esc_html_e( 'Ajouter au panier', 'ufsc-clubs' ); ?></button><span id="ufsc-cart-readiness" class="ufsc-cart-readiness" role="status"><?php echo $product_ready ? esc_html__( 'Complétez puis vérifiez tous les dossiers pour activer le panier.', 'ufsc-clubs' ) : esc_html( function_exists( 'ufsc_get_licence_product_message' ) ? ufsc_get_licence_product_message( $product_resolution ) : __( 'Le produit Licence UFSC est indisponible.', 'ufsc-clubs' ) ); ?></span><button type="submit" name="ufsc_renew_intent" value="cancel" formnovalidate class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Annuler et revenir à la liste', 'ufsc-clubs' ); ?></button><?php if ( ! $product_ready ) : ?><p class="ufsc-message ufsc-warning" role="status"><?php echo esc_html( function_exists( 'ufsc_get_licence_product_message' ) ? ufsc_get_licence_product_message( $product_resolution ) : __( 'Le produit Licence UFSC est indisponible. Contactez l’administration.', 'ufsc-clubs' ) ); ?></p><?php endif; ?></div>
            </form>
            <?php echo self::render_renewal_pagination( $page, $total_pages, $pagination_args, $pagination_url ); ?>
            <?php endif; ?>
        </section><?php return ob_get_clean();
    }

    /**
     * Split club licences between the active season table and read-only archives.
     *
     * Archives are display-only: no licence row is edited or deleted here.
     *
     * @param array  $licences      Licence rows.
     * @param string $active_season Current active season label.
     * @return array{active:array,archives:array,archive_seasons:array}
     */
    private static function split_licences_by_active_season( $licences, $active_season ) {
        $active_season   = trim( (string) $active_season );
        $active          = array();
        $archives        = array();
        $future          = array();
        $archive_seasons = array();

        foreach ( (array) $licences as $licence ) {
            if ( ! is_object( $licence ) ) {
                continue;
            }

            $season = self::get_licence_display_season( $licence );
            $licence->season_label = $season;

            $comparison = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::compare_seasons( $season, $active_season ) : null;
            if ( 0 === $comparison ) {
                $active[] = $licence;
                continue;
            }

            if ( null === $comparison ) {
                $archives[] = $licence;
                continue;
            }

            if ( $comparison < 0 ) {
                $archives[] = $licence;
                if ( '' !== $season ) {
                    $archive_seasons[] = $season;
                }
                continue;
            }

            $future[] = $licence;
        }

        $archive_seasons = array_values( array_unique( $archive_seasons ) );
        rsort( $archive_seasons, SORT_NATURAL );

        return array(
            'active'          => $active,
            'archives'        => $archives,
            'future'          => $future,
            'archive_seasons' => $archive_seasons,
        );
    }

    /**
     * Resolve the season label used by licence tables.
     *
     * @param object $licence Licence row.
     * @return string
     */
    private static function get_licence_display_season( $licence ) {
        $season = '';
        if ( isset( $licence->season_label ) && '' !== trim( (string) $licence->season_label ) ) {
            $season = (string) $licence->season_label;
        } elseif ( function_exists( 'ufsc_get_licence_season_label' ) ) {
            $season = (string) ufsc_get_licence_season_label( $licence );
        } elseif ( function_exists( 'ufsc_get_licence_season' ) ) {
            $season = (string) ufsc_get_licence_season( $licence );
        }

        return trim( str_replace( '/', '-', sanitize_text_field( $season ) ) );
    }

    /**
     * Render the club's canonical licence list for the selected season.
     *
     * This deliberately remains separate from renewal candidates and archives.
     */
    private static function render_current_licences_section( $licences, $seasons, $season, $atts, $total, $total_pages ) {
        $page     = max( 1, absint( $atts['page'] ?? 1 ) );
        $per_page = in_array( absint( $atts['per_page'] ?? 20 ), array( 10, 20 ), true ) ? absint( $atts['per_page'] ) : 20;
        $reset_url = self::get_club_portal_url( 'club-licences' );
        ob_start();
        ?>
        <section id="ufsc-current-licences" class="ufsc-current-licences" aria-labelledby="ufsc-current-licences-title">
            <div class="ufsc-section-header">
                <div><h3 id="ufsc-current-licences-title"><?php esc_html_e( 'Mes licences UFSC', 'ufsc-clubs' ); ?></h3><p><?php echo esc_html( sprintf( __( 'Saison affichée : %s', 'ufsc-clubs' ), $season ) ); ?></p></div>
                <a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( self::get_club_portal_url() ); ?>#ufsc-overview"><?php esc_html_e( 'Retour au tableau de bord', 'ufsc-clubs' ); ?></a>
            </div>
            <form method="get" class="ufsc-renewal-filters ufsc-current-licence-filters">
                <input type="hidden" name="ufsc_section" value="club-licences">
                <label for="ufsc-season-filter"><?php esc_html_e( 'Saison', 'ufsc-clubs' ); ?><select id="ufsc-season-filter" name="ufsc_season"><?php foreach ( $seasons as $available ) : ?><option value="<?php echo esc_attr( $available ); ?>" <?php selected( $season, $available ); ?>><?php echo esc_html( $available ); ?></option><?php endforeach; ?></select></label>
                <label for="ufsc-search-filter"><?php esc_html_e( 'Nom ou prénom', 'ufsc-clubs' ); ?><input id="ufsc-search-filter" type="search" name="ufsc_search" value="<?php echo esc_attr( $atts['search'] ?? '' ); ?>" autocomplete="off"></label>
                <label for="ufsc-status-filter"><?php esc_html_e( 'État', 'ufsc-clubs' ); ?><select id="ufsc-status-filter" name="ufsc_status"><option value=""><?php esc_html_e( 'Tous', 'ufsc-clubs' ); ?></option><?php foreach ( array( 'brouillon' => __( 'Brouillon', 'ufsc-clubs' ), 'validee' => __( 'Validée', 'ufsc-clubs' ), 'a_regler' => __( 'À régler', 'ufsc-clubs' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $atts['status'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label for="ufsc-per-page-filter"><?php esc_html_e( 'Lignes', 'ufsc-clubs' ); ?><select id="ufsc-per-page-filter" name="ufsc_per_page"><option value="10" <?php selected( $per_page, 10 ); ?>>10</option><option value="20" <?php selected( $per_page, 20 ); ?>>20</option></select></label>
                <div class="ufsc-filter-actions"><button type="submit" class="ufsc-btn ufsc-btn-primary"><?php esc_html_e( 'Rechercher', 'ufsc-clubs' ); ?></button><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Réinitialiser les filtres', 'ufsc-clubs' ); ?></a></div>
            </form>
            <p class="ufsc-results-count" aria-live="polite"><?php echo esc_html( sprintf( _n( '%d licence', '%d licences', absint( $total ), 'ufsc-clubs' ), absint( $total ) ) ); ?></p>
            <?php if ( empty( $licences ) ) : ?>
                <div class="ufsc-message ufsc-info"><strong><?php esc_html_e( 'Aucune licence trouvée', 'ufsc-clubs' ); ?></strong><p><?php esc_html_e( 'Modifiez vos critères ou réinitialisez les filtres.', 'ufsc-clubs' ); ?></p><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Réinitialiser les filtres', 'ufsc-clubs' ); ?></a></div>
            <?php else : ?>
                <div class="ufsc-front-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Licences UFSC du club', 'ufsc-clubs' ); ?>"><table class="ufsc-licence-table ufsc-licence-table--current"><thead><tr><th><?php esc_html_e( 'Identité', 'ufsc-clubs' ); ?></th><th><?php esc_html_e( 'N° UFSC', 'ufsc-clubs' ); ?></th><th><?php esc_html_e( 'Saison', 'ufsc-clubs' ); ?></th><th><?php esc_html_e( 'Statut', 'ufsc-clubs' ); ?></th><th><?php esc_html_e( 'Paiement', 'ufsc-clubs' ); ?></th><th><?php esc_html_e( 'Actions', 'ufsc-clubs' ); ?></th></tr></thead><tbody>
                <?php foreach ( $licences as $licence ) : $id = absint( $licence->id ?? 0 ); $status = $licence->licence_statut ?? ( $licence->statut ?? '' ); $payment = self::get_first_licence_field( $licence, array( 'payment_status', 'statut_paiement', 'paid' ) ); ?>
                    <tr><td data-label="<?php esc_attr_e( 'Identité', 'ufsc-clubs' ); ?>"><?php echo esc_html( trim( ( $licence->prenom ?? '' ) . ' ' . ( $licence->nom ?? '' ) ) ); ?></td><td data-label="<?php esc_attr_e( 'N° UFSC', 'ufsc-clubs' ); ?>"><?php echo esc_html( class_exists( 'UFSC_Identifier_Resolver' ) ? ( UFSC_Identifier_Resolver::read( $licence, 'licence_ufsc' ) ?: '—' ) : '—' ); ?></td><td data-label="<?php esc_attr_e( 'Saison', 'ufsc-clubs' ); ?>"><?php echo esc_html( self::get_licence_display_season( $licence ) ?: $season ); ?></td><td data-label="<?php esc_attr_e( 'Statut', 'ufsc-clubs' ); ?>"><?php echo self::get_status_badge_front( $status ); ?></td><td data-label="<?php esc_attr_e( 'Paiement', 'ufsc-clubs' ); ?>"><?php echo esc_html( '' !== $payment ? $payment : __( 'Non renseigné', 'ufsc-clubs' ) ); ?></td><td data-label="<?php esc_attr_e( 'Actions', 'ufsc-clubs' ); ?>"><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( self::get_licence_detail_url( $id ) ); ?>"><?php esc_html_e( 'Consulter', 'ufsc-clubs' ); ?></a><a class="ufsc-btn ufsc-btn-primary" href="<?php echo esc_url( add_query_arg( array( 'edit_licence' => $id, 'ufsc_return' => remove_query_arg( array( 'edit_licence', 'view_licence' ) ) ) ) ); ?>"><?php esc_html_e( 'Modifier / Compléter', 'ufsc-clubs' ); ?></a></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <?php echo self::render_pagination( $page, $total_pages, array( 'ufsc_section' => 'club-licences', 'ufsc_season' => $season, 'ufsc_search' => $atts['search'] ?? '', 'ufsc_status' => $atts['status'] ?? '', 'ufsc_per_page' => $per_page ) ); ?>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the previous-season licences archive block for club users.
     *
     * @param array  $archive_licences Previous-season licence rows.
     * @param array  $archive_seasons  Available archive season labels.
     * @param string $archive_filter   Selected archive season label.
     * @param array  $atts             Current shortcode attributes.
     * @param bool   $readonly         Whether the parent shortcode is read-only.
     * @return string
     */
    private static function render_archived_licences_section( $archive_licences, $archive_seasons, $archive_filter, $atts, $readonly, $archive_total = 0, $archive_page = 1, $archive_per_page = 10 ) {
        $target_renewal_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
        $club_id = isset( $atts['club_id'] ) ? absint( $atts['club_id'] ) : 0;
        $affiliation_gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id, $target_renewal_season ) : array( 'allowed' => false, 'message' => __( 'L’état de votre affiliation n’a pas pu être déterminé. Veuillez contacter l’UFSC.', 'ufsc-clubs' ) );
        $can_renew_licences = ! empty( $affiliation_gate['allowed'] );
        $licence_product_id = function_exists( 'ufsc_get_licence_product_id' ) ? ufsc_get_licence_product_id() : ( function_exists( 'ufsc_get_woocommerce_settings' ) ? (int) ( ufsc_get_woocommerce_settings()['product_license_id'] ?? 0 ) : 0 );

        ob_start();
        ?>
        <section id="ufsc-licences-archives" class="ufsc-licences-archives" aria-labelledby="ufsc-licences-archives-title">
            <div class="ufsc-section-header ufsc-section-header--compact">
				<h4 id="ufsc-licences-archives-title"><?php esc_html_e( 'Licences des saisons précédentes', 'ufsc-clubs' ); ?></h4>
            </div>
            <p class="ufsc-admin-help"><?php esc_html_e( 'Les licences des saisons précédentes restent consultables ici. Elles ne sont pas modifiées par l’affichage des archives.', 'ufsc-clubs' ); ?></p>
            <?php if ( ! $can_renew_licences ) : ?>
                <div class="ufsc-message ufsc-info"><?php echo esc_html( $affiliation_gate['message'] ); ?></div>
            <?php endif; ?>

            <?php if ( $archive_seasons ) : ?><form method="get" class="ufsc-archive-filter-form">
                    <?php foreach ( array( 'ufsc_status', 'ufsc_search', 'ufsc_sort' ) as $param ) : ?>
                        <?php if ( isset( $_GET[ $param ] ) && ! is_array( $_GET[ $param ] ) ) : ?>
                            <input type="hidden" name="<?php echo esc_attr( $param ); ?>" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) ); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label for="ufsc_archive_season"><?php esc_html_e( 'Saison archivée', 'ufsc-clubs' ); ?></label>
                    <select id="ufsc_archive_season" name="ufsc_archive_season" <?php disabled( empty( $archive_seasons ) ); ?>>
                        <option value=""><?php esc_html_e( 'Choisir une saison', 'ufsc-clubs' ); ?></option>
                        <?php foreach ( $archive_seasons as $season ) : ?>
                            <option value="<?php echo esc_attr( $season ); ?>" <?php selected( $archive_filter, $season ); ?>><?php echo esc_html( $season ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Consulter les archives', 'ufsc-clubs' ); ?></button>
                    <?php if ( '' !== $archive_filter ) : ?><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( remove_query_arg( array( 'ufsc_archive_season', 'ufsc_archive_page', 'ufsc_archive_per_page' ) ) ); ?>"><?php esc_html_e( 'Réinitialiser', 'ufsc-clubs' ); ?></a><?php endif; ?>
                </form><?php else : ?><div class="ufsc-message ufsc-info"><strong><?php esc_html_e( 'Aucune archive disponible', 'ufsc-clubs' ); ?></strong><p><?php esc_html_e( 'Aucune licence d’une saison antérieure n’est enregistrée pour ce club.', 'ufsc-clubs' ); ?></p></div><?php endif; ?>

            <?php if ( $archive_seasons && '' === $archive_filter ) : ?>
                <div class="ufsc-message ufsc-info"><?php esc_html_e( 'Sélectionnez une saison pour consulter les licences archivées.', 'ufsc-clubs' ); ?></div>
            <?php elseif ( empty( $archive_licences ) ) : ?>
                <div class="ufsc-message ufsc-info"><?php esc_html_e( 'Aucune licence archivée pour le filtre sélectionné.', 'ufsc-clubs' ); ?></div>
            <?php else :
                $archive_total = max( 0, absint( $archive_total ) );
                $archive_per_page = in_array( absint( $archive_per_page ), array( 10, 20 ), true ) ? absint( $archive_per_page ) : 10;
                $archive_pages = max( 1, (int) ceil( $archive_total / $archive_per_page ) );
                $archive_page = min( max( 1, absint( $archive_page ) ), $archive_pages ); ?>
                <p class="ufsc-archive-results" aria-live="polite"><?php echo esc_html( sprintf( _n( '%d licence archivée', '%d licences archivées', $archive_total, 'ufsc-clubs' ), $archive_total ) ); ?></p>
				<?php if ( ! $readonly && $can_renew_licences && $licence_product_id ) : ?>
				<form id="ufsc-bulk-renew-archives" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ufsc_bulk_renew_licences_' . $club_id ); ?><input type="hidden" name="action" value="ufsc_bulk_renew_licences"><input type="hidden" name="ufsc_club_id" value="<?php echo esc_attr( $club_id ); ?>">
					<label><input type="checkbox" id="ufsc-select-all-renewals"> <?php esc_html_e( 'Tout sélectionner', 'ufsc-clubs' ); ?></label>
					<button class="ufsc-btn ufsc-btn-primary"><?php esc_html_e( 'Renouveler les licences sélectionnées', 'ufsc-clubs' ); ?></button>
				</form>
				<script>document.getElementById('ufsc-select-all-renewals').addEventListener('change',function(){document.querySelectorAll('.ufsc-renewal-checkbox').forEach(function(box){box.checked=this.checked;},this);});</script>
				<?php endif; ?>
                <p class="ufsc-front-table-hint"><?php esc_html_e( 'Faites glisser le tableau horizontalement pour consulter les archives.', 'ufsc-clubs' ); ?></p>
                <div class="ufsc-front-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Tableau des archives des licences UFSC du club', 'ufsc-clubs' ); ?>">
                    <table class="ufsc-licence-table ufsc-licence-table--archives">
                        <thead>
                            <tr>
								<th><?php esc_html_e( 'Sélection', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Saison', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Prénom', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Date de naissance', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Sexe', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Ancien statut', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Ancien poids', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Ancienne catégorie d’âge', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Ancienne catégorie de poids', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Date de création', 'ufsc-clubs' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'ufsc-clubs' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $archive_licences as $licence ) :
                                $season = self::get_licence_display_season( $licence );
                                $status_raw = $licence->licence_statut ?? ( $licence->statut ?? '' );
                                $status = class_exists( 'UFSC_Licence_Status' ) ? UFSC_Licence_Status::display_status( $status_raw ) : ( function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $status_raw ) : $status_raw );
                                $category_summary = class_exists( 'UFSC_Category_Repository' )
                                    ? UFSC_Category_Repository::detect_for_athlete( $licence, UFSC_Category_Repository::DEFAULT_DISCIPLINE, $season )
                                    : array( 'age_category_label' => '', 'weight_category_label' => '', 'status' => 'age_not_found' );
                                $age_category_label = $category_summary['age_category_label'] ?: self::get_first_licence_field( $licence, array( 'categorie_age_detectee', 'categorie', 'category', 'type_licence', 'cat' ) );
                                $weight_category_label = $category_summary['weight_category_label'] ?: self::get_first_licence_field( $licence, array( 'categorie_poids_detectee' ) );
                                $weight_value = self::get_first_licence_field( $licence, array( 'poids', 'weight' ) );
                                $renewed_licence_id = ( $target_renewal_season && function_exists( 'ufsc_get_renewed_licence_marker' ) ) ? ufsc_get_renewed_licence_marker( (int) ( $licence->id ?? 0 ), $target_renewal_season ) : 0;
                                if ( ! $renewed_licence_id && $target_renewal_season && function_exists( 'ufsc_wc_find_equivalent_renewed_licence_id' ) ) {
                                    $renewed_licence_id = ufsc_wc_find_equivalent_renewed_licence_id( $licence, $club_id, $target_renewal_season );
                                }
								$season_context = function_exists( 'ufsc_get_licence_season_context_status' ) ? ufsc_get_licence_season_context_status( $licence, $target_renewal_season ) : array();
								if ( ! empty( $season_context['renewed_licence_id'] ) ) { $renewed_licence_id = absint( $season_context['renewed_licence_id'] ); }
								if ( $renewed_licence_id && empty( $season_context['action_url'] ) ) { $season_context['action_url'] = self::get_licence_detail_url( $renewed_licence_id ); }
                            ?>
                                <tr>
									<td><?php if ( ! empty( $season_context['renewal_allowed'] ) && ! $readonly && $licence_product_id ) : ?><input form="ufsc-bulk-renew-archives" class="ufsc-renewal-checkbox" type="checkbox" name="renew_licence_ids[]" value="<?php echo esc_attr( $licence->id ?? 0 ); ?>" aria-label="<?php esc_attr_e( 'Sélectionner cette licence', 'ufsc-clubs' ); ?>"><?php else : ?>—<?php endif; ?></td>
                                    <td><?php echo esc_html( $season ? $season : '—' ); ?></td>
                                    <td><?php echo esc_html( $licence->nom ?? '' ); ?></td>
                                    <td><?php echo esc_html( $licence->prenom ?? '' ); ?></td>
                                    <td><?php echo esc_html( $licence->date_naissance ?? '' ); ?></td>
                                    <td><?php echo esc_html( $licence->sexe ?? '' ); ?></td>
                                    <td><?php echo self::get_status_badge_front( $status ); ?></td>
                                    <td><?php echo esc_html( '' !== $weight_value ? $weight_value . ' kg' : '—' ); ?></td>
                                    <td><?php echo self::render_category_badge( $age_category_label, $age_category_label ? 'ok' : $category_summary['status'] ); ?></td>
                                    <td><?php echo self::render_category_badge( $weight_category_label, $category_summary['status'] ); ?></td>
                                    <td><?php echo esc_html( self::get_first_licence_field( $licence, array( 'date_creation', 'created_at', 'date_inscription' ) ) ); ?></td>
                                    <td>
                                        <a class="ufsc-action" href="<?php echo esc_url( self::get_licence_detail_url( $licence->id ?? 0 ) ); ?>"><?php esc_html_e( 'Consulter', 'ufsc-clubs' ); ?></a>
                                        <?php if ( $target_renewal_season ) : ?>
                                            <?php if ( in_array( $season_context['renewal_state'] ?? '', array( 'renewed', 'pending', 'payable' ), true ) ) : ?>
											<?php if ( ! empty( $season_context['action_url'] ) ) : ?><a class="ufsc-badge ufsc-badge-info" href="<?php echo esc_url( $season_context['action_url'] ); ?>"><?php echo esc_html( $season_context['action_label'] ); ?></a><?php else : ?><span class="ufsc-badge ufsc-badge-info"><?php echo esc_html( $season_context['action_label'] ); ?></span><?php endif; ?>
                                            <?php elseif ( ! $readonly && ! empty( $season_context['renewal_allowed'] ) && $licence_product_id ) : ?>
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-inline-renew-form" style="display:inline">
                                                    <?php wp_nonce_field( 'ufsc_add_to_cart_action', '_ufsc_nonce' ); ?>
                                                    <input type="hidden" name="action" value="ufsc_add_to_cart">
                                                    <input type="hidden" name="product_id" value="<?php echo esc_attr( $licence_product_id ); ?>">
                                                    <input type="hidden" name="ufsc_club_id" value="<?php echo esc_attr( $club_id ); ?>">
                                                    <input type="hidden" name="ufsc_action" value="renew_licence">
                                                    <input type="hidden" name="ufsc_target_season" value="<?php echo esc_attr( $target_renewal_season ); ?>">
                                                    <input type="hidden" name="ufsc_renew_from_licence_id" value="<?php echo esc_attr( $licence->id ?? 0 ); ?>">
                                                    <button type="submit" class="ufsc-action"><?php echo esc_html( $season_context['action_label'] ); ?></button>
                                                </form>
										<?php elseif ( 'blocked' === ( $season_context['renewal_state'] ?? '' ) ) : ?>
											<span class="ufsc-badge ufsc-badge-warning"><?php echo esc_html( $season_context['action_label'] ); ?></span><br><small><?php echo esc_html( $season_context['renewal_reason'] ); ?></small>
                                            <?php elseif ( ! $readonly && ! $licence_product_id ) : ?>
                                                <span class="ufsc-badge ufsc-badge-warning"><?php esc_html_e( 'Produit licence non configuré', 'ufsc-clubs' ); ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ( $archive_pages > 1 ) : ?><nav class="ufsc-renewal-pagination" aria-label="<?php esc_attr_e( 'Pagination des archives', 'ufsc-clubs' ); ?>">
                    <?php if ( $archive_page > 1 ) : ?><a class="ufsc-btn ufsc-btn-secondary" rel="prev" href="<?php echo esc_url( add_query_arg( array( 'ufsc_archive_season' => $archive_filter, 'ufsc_archive_per_page' => $archive_per_page, 'ufsc_archive_page' => $archive_page - 1 ) ) ); ?>"><?php esc_html_e( 'Page précédente', 'ufsc-clubs' ); ?></a><?php endif; ?>
                    <span aria-current="page"><?php echo esc_html( sprintf( __( 'Page %1$d sur %2$d', 'ufsc-clubs' ), $archive_page, $archive_pages ) ); ?></span>
                    <?php if ( $archive_page < $archive_pages ) : ?><a class="ufsc-btn ufsc-btn-secondary" rel="next" href="<?php echo esc_url( add_query_arg( array( 'ufsc_archive_season' => $archive_filter, 'ufsc_archive_per_page' => $archive_per_page, 'ufsc_archive_page' => $archive_page + 1 ) ) ); ?>"><?php esc_html_e( 'Page suivante', 'ufsc-clubs' ); ?></a><?php endif; ?>
                </nav><?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Render licences already prepared for a future season, outside archives.
     *
     * @param array $future_licences Future-season licence rows.
     * @return string
     */
    private static function render_future_licences_section( $future_licences ) {
        if ( empty( $future_licences ) ) {
            return '';
        }

        ob_start();
        ?>
        <section class="ufsc-licences-future" aria-labelledby="ufsc-licences-future-title">
            <div class="ufsc-section-header ufsc-section-header--compact">
                <h4 id="ufsc-licences-future-title"><?php esc_html_e( 'Licences préparées pour une saison future', 'ufsc-clubs' ); ?></h4>
            </div>
            <p class="ufsc-admin-help"><?php esc_html_e( 'Ces licences sont déjà rattachées à une saison postérieure à la saison active. Elles ne sont pas affichées dans les archives.', 'ufsc-clubs' ); ?></p>
            <div class="ufsc-front-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Tableau des licences futures UFSC du club', 'ufsc-clubs' ); ?>">
                <table class="ufsc-licence-table ufsc-licence-table--future">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Saison', 'ufsc-clubs' ); ?></th>
                            <th><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></th>
                            <th><?php esc_html_e( 'Prénom', 'ufsc-clubs' ); ?></th>
                            <th><?php esc_html_e( 'Statut', 'ufsc-clubs' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'ufsc-clubs' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $future_licences as $licence ) :
                            $season = self::get_licence_display_season( $licence );
                            $status_raw = $licence->licence_statut ?? ( $licence->statut ?? '' );
                            $status = class_exists( 'UFSC_Licence_Status' ) ? UFSC_Licence_Status::display_status( $status_raw ) : ( function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $status_raw ) : $status_raw );
                        ?>
                            <tr>
                                <td><?php echo esc_html( $season ? $season : '—' ); ?></td>
                                <td><?php echo esc_html( $licence->nom ?? '' ); ?></td>
                                <td><?php echo esc_html( $licence->prenom ?? '' ); ?></td>
                                <td><?php echo self::get_status_badge_front( $status ); ?></td>
                                <td><a class="ufsc-action" href="<?php echo esc_url( self::get_licence_detail_url( $licence->id ?? 0 ) ); ?>"><?php esc_html_e( 'Consulter', 'ufsc-clubs' ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Display a single licence details
     *
     * @param int $licence_id Licence ID
     * @return string
     */
    public static function render_single_licence( $licence_id, $readonly = false ) {
        $readonly = filter_var( $readonly, FILTER_VALIDATE_BOOLEAN );
        wp_enqueue_style( 'ufsc-front', UFSC_CL_URL . 'assets/css/ufsc-front.css', array(), function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/css/ufsc-front.css' ) : UFSC_CL_VERSION );

        $club_id = self::get_user_club_id( get_current_user_id() );
        if ( ! $club_id ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Club non trouvé.', 'ufsc-clubs' ) .
                   '</div>';
        }

        $licence = self::get_licence( $club_id, $licence_id );
        if ( ! $licence ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Licence non trouvée.', 'ufsc-clubs' ) .
                   '</div>';
        }

        $wc_settings = ufsc_get_woocommerce_settings();
        $return_url  = self::get_licence_return_url();
        $active_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : '';
        $source_season = self::get_licence_display_season( $licence );
        $season_context = function_exists( 'ufsc_get_licence_season_context_status' ) ? ufsc_get_licence_season_context_status( $licence, $active_season ) : array();
        $renewal_edit_url = ! empty( $season_context['renewal_allowed'] ) ? add_query_arg( array( 'ufsc_section' => 'licences-renouvellement', 'renew_source_id' => absint( $licence_id ), 'target_season' => $active_season ), self::get_club_portal_url( 'licences-renouvellement' ) ) : '';
        $renewed_url = ! empty( $season_context['renewed_licence_id'] ) ? self::get_licence_detail_url( absint( $season_context['renewed_licence_id'] ) ) : '';

        ob_start();
        ?>
        <div class="ufsc-licence-detail">
            <nav class="ufsc-club-portal__nav ufsc-club-portal__actions" aria-label="<?php esc_attr_e( 'Navigation de la fiche licence', 'ufsc-clubs' ); ?>">
                <a href="<?php echo esc_url( $return_url ); ?>" class="ufsc-btn ufsc-btn-primary"><?php esc_html_e( '← Retour à mes licences', 'ufsc-clubs' ); ?></a>
                <a href="<?php echo esc_url( self::get_club_portal_url( 'overview' ) ); ?>" class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Tableau de bord', 'ufsc-clubs' ); ?></a>
                <a href="<?php echo esc_url( self::get_club_portal_url( 'licences-archives' ) ); ?>" class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Archives licences', 'ufsc-clubs' ); ?></a>
                <a href="<?php echo esc_url( self::SPORTS_RULES_URL ); ?>" class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( 'Règlements sportifs UFSC', 'ufsc-clubs' ); ?></a>
                <?php if ( $renewed_url ) : ?><a href="<?php echo esc_url( $renewed_url ); ?>" class="ufsc-btn ufsc-btn-primary"><?php echo esc_html( sprintf( __( 'Consulter la licence %s', 'ufsc-clubs' ), $active_season ) ); ?></a><?php elseif ( $renewal_edit_url ) : ?><a href="<?php echo esc_url( $renewal_edit_url ); ?>" class="ufsc-btn ufsc-btn-primary"><?php esc_html_e( 'Modifier pour renouveler', 'ufsc-clubs' ); ?></a><a href="<?php echo esc_url( $renewal_edit_url ); ?>" class="ufsc-btn ufsc-btn-secondary"><?php echo esc_html( sprintf( __( 'Renouveler pour %s', 'ufsc-clubs' ), $active_season ) ); ?></a><?php endif; ?>
            </nav>
            <div class="ufsc-section-header">
                <h3><?php esc_html_e( 'Détails de la licence', 'ufsc-clubs' ); ?></h3>
            </div>
            <table class="ufsc-table ufsc-licence-info">
                <tbody>
                    <?php
                    $fields = UFSC_SQL::get_licence_fields();
                    if ( property_exists( $licence, 'payment_status' ) && ! isset( $fields['payment_status'] ) ) {
                        $fields['payment_status'] = array( __( 'Statut de paiement', 'ufsc-clubs' ), 'payment_status' );
                    }

                    $exclude = array( 'club_id', 'responsable_id', 'is_included' );
                    foreach ( $fields as $field_key => $field_info ) {
                        if ( in_array( $field_key, $exclude, true ) ) {
                            continue;
                        }

                        list( $label, $type ) = $field_info;

                        if ( ! property_exists( $licence, $field_key ) ) {
                            continue;
                        }

                        $value = $licence->{$field_key};

                        if ( $value === null || $value === '' ) {
                            if ( 'bool' !== $type && 'licence_status' !== $type && 'payment_status' !== $type ) {
                                continue;
                            }
                        }

                        switch ( $type ) {
                            case 'bool':
                                $formatted = $value ? esc_html__( 'Oui', 'ufsc-clubs' ) : esc_html__( 'Non', 'ufsc-clubs' );
                                break;
                            case 'date':
                                $formatted = $value ? esc_html( date_i18n( 'd/m/Y', strtotime( $value ) ) ) : '';
                                break;
                            case 'licence_status':
                                $label_value = self::get_licence_status_label( $value );
                                $class = self::get_licence_status_badge_class( $value );
                                $formatted = '<span class="ufsc-badge ' . esc_attr( $class ) . '">' . esc_html( $label_value ) . '</span>';
                                break;
                            case 'payment_status':
                                $formatted = self::render_payment_status_badge( $value );
                                break;
                            default:
                                $formatted = esc_html( $value );
                                break;
                        }

                        if ( '' === $formatted ) {
                            continue;
                        }
                        echo '<tr><th>' . esc_html( $label ) . '</th><td>' . $formatted . '</td></tr>';
                    }
                    $season_label = function_exists( 'ufsc_get_licence_season_label' ) ? ufsc_get_licence_season_label( $licence ) : ( function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $licence ) : '' );
                    if ( $season_label ) {
                        echo '<tr><th>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</th><td>' . esc_html( $season_label ) . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
            <?php
            $licence_doc = function_exists( 'ufsc_get_licence_document_data' ) ? ufsc_get_licence_document_data( $licence ) : array( 'url' => '', 'attachment_id' => 0, 'status' => 'missing' );
            $can_manage_doc = function_exists( 'ufsc_can_manage_licence_document' ) ? ufsc_can_manage_licence_document( $licence->id ?? 0, $club_id ) : false;
            ?>
            <div class="ufsc-licence-documents">
                <h4><?php esc_html_e( 'Certificat médical', 'ufsc-clubs' ); ?></h4>
                <?php if ( ! empty( $licence_doc['url'] ) ) : ?>
                    <p class="ufsc-document-status"><?php esc_html_e( 'Disponible', 'ufsc-clubs' ); ?></p>
                    <p>
                        <a href="<?php echo esc_url( $licence_doc['url'] ); ?>" target="_blank" rel="noopener" class="ufsc-btn ufsc-btn-small">
                            <?php esc_html_e( 'Télécharger', 'ufsc-clubs' ); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <p class="ufsc-document-status"><?php esc_html_e( 'Non transmis', 'ufsc-clubs' ); ?></p>
                <?php endif; ?>

                <?php if ( ! $readonly && $can_manage_doc ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ufsc-licence-doc-form">
                        <?php wp_nonce_field( 'ufsc_upload_licence_document_' . ( $licence->id ?? 0 ) ); ?>
                        <input type="hidden" name="action" value="ufsc_upload_licence_document">
                        <input type="hidden" name="licence_id" value="<?php echo esc_attr( $licence->id ?? 0 ); ?>">
                        <input type="file" name="licence_document" accept=".pdf" />
                        <button type="submit" class="ufsc-btn ufsc-btn-small ufsc-btn-secondary">
                            <?php echo ! empty( $licence_doc['url'] ) ? esc_html__( 'Remplacer le PDF', 'ufsc-clubs' ) : esc_html__( 'Ajouter le PDF', 'ufsc-clubs' ); ?>
                        </button>
                    </form>
                    <?php if ( ! empty( $licence_doc['url'] ) ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-licence-doc-remove-form">
                            <?php wp_nonce_field( 'ufsc_remove_licence_document_' . ( $licence->id ?? 0 ) ); ?>
                            <input type="hidden" name="action" value="ufsc_remove_licence_document">
                            <input type="hidden" name="licence_id" value="<?php echo esc_attr( $licence->id ?? 0 ); ?>">
                            <label>
                                <input type="checkbox" name="delete_attachment" value="1">
                                <?php esc_html_e( 'Supprimer le fichier (si non utilisé ailleurs)', 'ufsc-clubs' ); ?>
                            </label>
                            <button type="submit" class="ufsc-btn ufsc-btn-small ufsc-btn-danger">
                                <?php esc_html_e( 'Supprimer', 'ufsc-clubs' ); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if ( ! $readonly ) : ?>
            <div class="ufsc-row-actions">
                <?php
                $licence_status_raw = $licence->licence_statut ?? ( $licence->statut ?? '' );
                $licence_status     = class_exists( 'UFSC_Licence_Status' ) ? UFSC_Licence_Status::display_status( $licence_status_raw ) : ( function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $licence_status_raw ) : $licence_status_raw );
                $is_locked          = function_exists( 'ufsc_is_licence_locked_for_club' ) ? ufsc_is_licence_locked_for_club( $licence ) : ! ( function_exists( 'ufsc_is_editable_licence_status' ) ? ufsc_is_editable_licence_status( $licence_status ) : false );
                $lock_reason        = '';
                if ( 'valide' === $licence_status ) {
                    $lock_reason = __( 'Validée', 'ufsc-clubs' );
                } elseif ( function_exists( 'ufsc_is_licence_paid' ) && ufsc_is_licence_paid( $licence ) ) {
                    $lock_reason = __( 'Paiement / Commande', 'ufsc-clubs' );
                } elseif ( $is_locked ) {
                    $lock_reason = __( 'Verrouillage', 'ufsc-clubs' );
                }
                $can_retry_payment  = function_exists( 'ufsc_can_retry_licence_payment' ) ? ufsc_can_retry_licence_payment( $licence->id ?? 0 ) : false;
                $is_in_cart         = self::is_licence_in_cart( (int) ( $licence->id ?? 0 ) );

                if ( ! $is_locked ) {
                    echo self::render_pre_payment_warning_block();
                }

                if ( ! $is_locked || $can_retry_payment ) :
                    ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                        <?php wp_nonce_field( 'ufsc_add_to_cart_action', '_ufsc_nonce' ); ?>
                        <input type="hidden" name="action" value="ufsc_add_to_cart">
                        <input type="hidden" name="product_id" value="<?php echo esc_attr( $wc_settings['product_license_id'] ); ?>">
                        <input type="hidden" name="ufsc_club_id" value="<?php echo esc_attr( (int) ( $licence->club_id ?? $club_id ?? 0 ) ); ?>">
                        <input type="hidden" name="ufsc_license_ids" value="<?php echo esc_attr( $licence->id ?? 0 ); ?>">
                        <button type="submit" class="ufsc-btn ufsc-btn-small">
                            <?php echo $is_in_cart ? esc_html__( 'Payer maintenant / Voir panier', 'ufsc-clubs' ) : esc_html__( 'Ajouter au panier', 'ufsc-clubs' ); ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ( $is_locked ) : ?>
                    <span class="ufsc-text-muted"><?php echo esc_html( '🔒 ' . sprintf( __( 'Verrouillée (%s)', 'ufsc-clubs' ), $lock_reason ) ); ?></span>
                <?php endif; ?>

                <?php if ( ! $is_locked ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'edit_licence', $licence->id ?? 0 ) ); ?>" class="ufsc-btn ufsc-btn-small">
                        <?php esc_html_e( 'Modifier', 'ufsc-clubs' ); ?>
                    </a>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-delete-licence-form" style="display:inline">
                        <?php wp_nonce_field( 'ufsc_delete_licence' ); ?>
                        <input type="hidden" name="action" value="ufsc_delete_licence">
                        <input type="hidden" name="licence_id" value="<?php echo esc_attr( $licence->id ?? 0 ); ?>">
                        <button type="submit" class="ufsc-btn ufsc-btn-small ufsc-btn-danger">
                            <?php esc_html_e( 'Supprimer', 'ufsc-clubs' ); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <p class="ufsc-club-portal__actions">
                <a href="<?php echo esc_url( $return_url ); ?>" class="ufsc-btn ufsc-btn-secondary"><?php esc_html_e( '← Retour à mes licences', 'ufsc-clubs' ); ?></a>
                <?php if ( $renewed_url ) : ?><a href="<?php echo esc_url( $renewed_url ); ?>" class="ufsc-btn ufsc-btn-primary"><?php echo esc_html( sprintf( __( 'Consulter la licence %s', 'ufsc-clubs' ), $active_season ) ); ?></a><?php elseif ( $renewal_edit_url ) : ?><a href="<?php echo esc_url( $renewal_edit_url ); ?>" class="ufsc-btn ufsc-btn-primary"><?php esc_html_e( 'Modifier pour renouveler', 'ufsc-clubs' ); ?></a><a href="<?php echo esc_url( $renewal_edit_url ); ?>" class="ufsc-btn ufsc-btn-secondary"><?php echo esc_html( sprintf( __( 'Renouveler pour %s', 'ufsc-clubs' ), $active_season ) ); ?></a><?php endif; ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render club statistics section
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_club_stats( $atts = array() ) {
        $atts = shortcode_atts( array(
            'club_id' => 0,
            'season' => ''
        ), $atts );

        if ( ! $atts['club_id'] && is_user_logged_in() ) {
            $atts['club_id'] = self::get_user_club_id( get_current_user_id() );
        }

        if ( ! $atts['club_id'] ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Club non trouvé.', 'ufsc-clubs' ) .
                   '</div>';
        }

        if ( empty( $atts['season'] ) ) {
            $atts['season'] = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
        }

        $stats = self::get_club_stats( $atts['club_id'], $atts['season'] );

        ob_start();
        ?>
        <div class="ufsc-stats-section">
            <div class="ufsc-section-header">
                <h3><?php esc_html_e( 'Statistiques', 'ufsc-clubs' ); ?></h3>
                <p class="ufsc-season-info">
                    <?php echo sprintf( esc_html__( 'Saison: %s', 'ufsc-clubs' ), esc_html( $atts['season'] ) ); ?>
                </p>
            </div>

            <div class="ufsc-stats-kpi">
                <div class="ufsc-kpi-card">
                    <div class="ufsc-kpi-value"><?php echo esc_html( $stats['total_licences'] ); ?></div>
                    <div class="ufsc-kpi-label"><?php esc_html_e( 'Total Licences', 'ufsc-clubs' ); ?></div>
                </div>

                <div class="ufsc-kpi-card">
                    <div class="ufsc-kpi-value"><?php echo esc_html( $stats['paid_licences'] ); ?></div>
                    <div class="ufsc-kpi-label"><?php esc_html_e( 'Licences Payées', 'ufsc-clubs' ); ?></div>
                </div>

                <div class="ufsc-kpi-card">
                    <div class="ufsc-kpi-value"><?php echo esc_html( $stats['validated_licences'] ); ?></div>
                    <div class="ufsc-kpi-label"><?php esc_html_e( 'Licences Validées', 'ufsc-clubs' ); ?></div>
                </div>

            </div>
			<?php if ( empty( $stats['total_licences'] ) ) : ?>
				<div class="ufsc-message ufsc-info"><?php esc_html_e( 'Aucune licence enregistrée pour cette saison.', 'ufsc-clubs' ); ?></div>
			<?php endif; ?>
			<?php $previous_stats_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_previous_season() : ''; ?>
			<?php if ( $previous_stats_season ) : ?>
				<p><a href="<?php echo esc_url( add_query_arg( 'ufsc_archive_season', $previous_stats_season ) ); ?>"><?php echo esc_html( sprintf( __( 'Consulter les statistiques %s', 'ufsc-clubs' ), $previous_stats_season ) ); ?></a></p>
			<?php endif; ?>

			<?php if ( ! empty( $stats['total_licences'] ) ) : ?>
			<div class="ufsc-stats-chart">
                <h4><?php esc_html_e( 'Évolution des licences', 'ufsc-clubs' ); ?></h4>
                <canvas id="ufsc-licence-chart" height="200"></canvas>
            </div>

            <div class="ufsc-stats-chart">
                <h4><?php esc_html_e( 'Évolution des licences selon les année de naissance', 'ufsc-clubs' ); ?></h4>
                <canvas id="ufsc-licence-year-chart" height="200"></canvas>
            </div>
			<?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render club profile section with all required fields organized in sections
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_club_profile( $atts = array() ) {
        wp_enqueue_style( 'ufsc-front', UFSC_CL_URL . 'assets/css/ufsc-front.css', array(), function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/css/ufsc-front.css' ) : UFSC_CL_VERSION );
        $atts = shortcode_atts( array(
            'club_id'    => 0,
            'licence_id' => 0,
        ), $atts );

        if ( ! $atts['club_id'] && is_user_logged_in() ) {
            $atts['club_id'] = self::get_user_club_id( get_current_user_id() );
        }
        ;
        if ( ! $atts['club_id'] ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Club non trouvé.', 'ufsc-clubs' ) .
                   '</div>';
        }

        $club = self::get_club_data( $atts['club_id'] );
        $is_validated = self::is_validated_club( $atts['club_id'] );
        $is_admin = current_user_can( 'manage_options' );

        if ( ! $club ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Données du club non trouvées.', 'ufsc-clubs' ) .
                   '</div>';
        }

        $is_admin = current_user_can( 'manage_options' );
        $can_edit = UFSC_CL_Permissions::ufsc_user_can_edit_club( $atts['club_id'] );
		$current_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
		$annual_affiliation = class_exists( 'UFSC_Season_Archive_Manager' ) ? UFSC_Season_Archive_Manager::get_affiliation( $atts['club_id'], $current_season ) : null;
		$annual_status = $annual_affiliation ? sanitize_key( (string) $annual_affiliation->status ) : 'a_renouveler';
        $annual_presentation = function_exists( 'ufsc_get_annual_affiliation_status' ) ? ufsc_get_annual_affiliation_status( $annual_affiliation ) : array( 'key' => $annual_status, 'label' => __( 'À renouveler', 'ufsc-clubs' ) );
        $club_status = ! empty( $annual_presentation['key'] ) ? $annual_presentation['key'] : $annual_status;

        if ( ! $can_edit ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Vous n\'avez pas les permissions pour voir ce club.', 'ufsc-clubs' ) .
                   '</div>';
        }

        // Handle form submission
        if (
            isset( $_POST['action'] ) &&
            'ufsc_save_club' === $_POST['action'] &&
            isset( $_POST['ufsc_club_nonce'] ) &&
            wp_verify_nonce( $_POST['ufsc_club_nonce'], 'ufsc_save_club' )
        ) {
            $result = self::handle_club_update( $atts['club_id'], $_POST );
            if ( $result['success'] ) {
                echo '<div class="ufsc-message ufsc-success">' . esc_html( $result['message'] ) . '</div>';
                $club = self::get_club_data( $atts['club_id'] ); // Refresh data
            } else {
                echo '<div class="ufsc-message ufsc-error">' . esc_html( $result['message'] ) . '</div>';
            }
        }

        $profile_name    = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'name' ) : ( $club->nom ?? '' );
        $profile_region  = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'region' ) : ( $club->region ?? '' );
        $profile_address = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'address' ) : ( $club->adresse ?? '' );
        $profile_cp      = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'postal_code' ) : ( $club->code_postal ?? '' );
        $profile_city    = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'city' ) : ( $club->ville ?? '' );
        $profile_phone   = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'phone' ) : ( $club->telephone ?? '' );
        $profile_email   = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'email' ) : ( $club->email ?? '' );
        $profile_site    = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'website' ) : ( $club->url_site ?? '' );
        $profile_logo    = function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'logo' ) : ( $club->profile_photo_url ?? '' );
        $profile_affnum  = $annual_affiliation->num_affiliation ?? ( function_exists( 'ufsc_get_club_profile_value' ) ? ufsc_get_club_profile_value( $club, 'affiliation_number' ) : ( $club->num_affiliation ?? '' ) );
        $profile_address_line = trim( trim( (string) $profile_address ) . ' ' . trim( (string) $profile_cp ) . ' ' . trim( (string) $profile_city ) );

        ob_start();
        UFSC_CL_Club_Form_Handler::display_save_club_results();
        $regions = UFSC_CL_Utils::regions();
        ?>

        <div class="ufsc-club-portal ufsc-club-account ufsc-club-profile ufsc-premium-v3" data-ufsc-build="<?php echo esc_attr( function_exists( 'ufsc_get_build_id' ) ? ufsc_get_build_id() : UFSC_CL_VERSION ); ?>">
            <div class="ufsc-club-profile-shell">
                <div class="ufsc-section-header ufsc-profile-header">
                    <div>
                        <h3><?php esc_html_e( 'Compte Club', 'ufsc-clubs' ); ?></h3>
                        <p class="ufsc-dashboard-subtitle"><?php esc_html_e( 'Consultez et mettez à jour les informations officielles de votre club.', 'ufsc-clubs' ); ?></p>
                    </div>
                    <?php if ( ! $is_admin ): ?>
                        <p class="ufsc-permission-notice">
                            <?php esc_html_e( 'Coordonnées du club modifiables ici. Les poids des licenciés se mettent à jour depuis l’onglet Mes licences UFSC.', 'ufsc-clubs' ); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <nav class="ufsc-club-account__nav ufsc-club-portal__nav" aria-label="<?php esc_attr_e( 'Navigation Compte Club', 'ufsc-clubs' ); ?>">
                    <a href="<?php echo esc_url( self::get_club_portal_url( 'overview' ) ); ?>"><?php esc_html_e( 'Vue d’ensemble', 'ufsc-clubs' ); ?></a>
                    <a aria-current="page" href="<?php echo esc_url( self::get_club_portal_url( 'club-information' ) ); ?>"><?php esc_html_e( 'Informations du club', 'ufsc-clubs' ); ?></a>
                    <a href="<?php echo esc_url( self::get_club_portal_url( 'club-officers' ) ); ?>"><?php esc_html_e( 'Dirigeants', 'ufsc-clubs' ); ?></a>
                    <a href="<?php echo esc_url( self::get_club_portal_url( 'club-documents' ) ); ?>"><?php esc_html_e( 'Documents', 'ufsc-clubs' ); ?></a>
                    <a href="<?php echo esc_url( self::get_club_portal_url( 'licences-archives' ) ); ?>"><?php esc_html_e( 'Archives licences', 'ufsc-clubs' ); ?></a>
                    <a href="<?php echo esc_url( self::SPORTS_RULES_URL ); ?>"><?php esc_html_e( 'Règlements sportifs', 'ufsc-clubs' ); ?></a>
                </nav>

            <?php
                // UFSC PATCH: Attestation UFSC section (stable + legacy fallback).
                $attestation = function_exists( 'ufsc_get_affiliation_attestation_data' )
                    ? ufsc_get_affiliation_attestation_data( $club->id, $club )
                    : array( 'url' => '', 'status' => 'pending', 'can_view' => false );
            ?>
                <div class="ufsc-card ufsc-club-hero">
                    <div class="ufsc-club-hero-media">
                        <div class="ufsc-logo-editor" data-ufsc-logo-editor>
                            <h5 class="ufsc-logo-editor__title"><?php esc_html_e( 'Logo du club', 'ufsc-clubs' ); ?></h5>
                            <div class="ufsc-logo-editor__preview" data-ufsc-logo-preview><?php echo self::render_club_logo( $profile_logo, $profile_name, 'photo-club-front' ); ?></div>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ufsc-logo-editor__upload">
                                <?php wp_nonce_field( 'ufsc_upload_profile_photo', 'ufsc_upload_profile_photo_nonce' ); ?>
                                <input type="hidden" name="action" value="ufsc_upload_profile_photo"><input type="hidden" name="club_id" value="<?php echo esc_attr( $club->id ); ?>">
                                <label class="ufsc-btn ufsc-btn-primary" for="ufsc-club-logo-file"><?php esc_html_e( 'Remplacer le logo', 'ufsc-clubs' ); ?></label>
                                <input id="ufsc-club-logo-file" class="ufsc-logo-editor__file" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-max-bytes="5242880">
                                <p class="ufsc-logo-editor__help"><?php esc_html_e( 'JPEG, PNG ou WebP — 5 Mo maximum.', 'ufsc-clubs' ); ?></p><p class="ufsc-logo-editor__filename" data-ufsc-logo-filename aria-live="polite"></p>
                                <div class="ufsc-logo-editor__pending" hidden><button type="submit" class="ufsc-btn ufsc-btn-primary"><?php esc_html_e( 'Enregistrer le logo', 'ufsc-clubs' ); ?></button><button type="button" class="ufsc-btn ufsc-btn-secondary" data-ufsc-logo-cancel><?php esc_html_e( 'Annuler', 'ufsc-clubs' ); ?></button></div>
                            </form>
                            <?php if ( '' !== $profile_logo ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-logo-editor__remove" onsubmit="return window.confirm('<?php echo esc_js( __( 'Supprimer définitivement le logo du club ?', 'ufsc-clubs' ) ); ?>');"><?php wp_nonce_field( 'ufsc_remove_profile_photo', 'ufsc_remove_profile_photo_nonce' ); ?><input type="hidden" name="action" value="ufsc_remove_profile_photo"><input type="hidden" name="club_id" value="<?php echo esc_attr( $club->id ); ?>"><button type="submit" class="ufsc-btn ufsc-btn-link"><?php esc_html_e( 'Supprimer', 'ufsc-clubs' ); ?></button></form><?php endif; ?>
                        </div>
                    </div>
                    <div class="ufsc-club-hero-content">
                        <h4><?php echo esc_html( $profile_name ); ?></h4>
                        <div class="ufsc-dashboard-status-line">
							<?php echo self::get_status_badge_front( $club_status, $annual_presentation['label'] ?? '' ); ?>
                            <?php if ( $annual_affiliation && ! empty( $annual_affiliation->num_affiliation ) ) : ?>
								<span class="ufsc-badge ufsc-badge-region"><?php echo esc_html( sprintf( __( 'Affiliation %s', 'ufsc-clubs' ), $annual_affiliation->num_affiliation ) ); ?></span>
                            <?php endif; ?>
                        </div>
                        <dl class="ufsc-club-account__identity" aria-label="<?php esc_attr_e( 'Coordonnées principales du club', 'ufsc-clubs' ); ?>">
                            <?php if ( '' !== $profile_region ) : ?><div><dt><?php esc_html_e( 'Région', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $profile_region ); ?></dd></div><?php endif; ?>
                            <?php if ( '' !== $profile_address_line ) : ?><div><dt><?php esc_html_e( 'Adresse', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $profile_address_line ); ?></dd></div><?php endif; ?>
                            <?php if ( '' !== $profile_phone ) : ?><div><dt><?php esc_html_e( 'Téléphone', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $profile_phone ); ?></dd></div><?php endif; ?>
                            <?php if ( '' !== $profile_email ) : ?><div><dt><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></dt><dd><a href="mailto:<?php echo esc_attr( $profile_email ); ?>"><?php echo esc_html( $profile_email ); ?></a></dd></div><?php endif; ?>
                            <?php if ( '' !== $profile_site ) : ?><div><dt><?php esc_html_e( 'Site', 'ufsc-clubs' ); ?></dt><dd><a href="<?php echo esc_url( $profile_site ); ?>" target="_blank" rel="noopener"><?php echo esc_html( preg_replace( '#^https?://#', '', (string) $profile_site ) ); ?></a></dd></div><?php endif; ?>
                            <?php if ( '' !== $profile_affnum ) : ?><div><dt><?php esc_html_e( 'N° affiliation', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $profile_affnum ); ?></dd></div><?php endif; ?>
                            <?php if ( '' !== $current_season ) : ?><div><dt><?php esc_html_e( 'Saison', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $current_season ); ?></dd></div><?php endif; ?>
                        </dl>
                        <?php if ( $attestation['can_view'] ) : ?>
                            <div class="div-attestation">
                                <h3 class="title-attestation club front"><?php esc_html_e( 'Attestation UFSC', 'ufsc-clubs' ); ?></h3>
                                <?php if ( $attestation['url'] ) : ?>
                                    <div class="ufsc-current-file">
                                        <p class="ufsc-document-status"><?php esc_html_e( 'Disponible', 'ufsc-clubs' ); ?></p>
                                        <div class="ufsc-document-actions">
                                            <a href="<?php echo esc_url( $attestation['url'] ); ?>" target="_blank" rel="noopener" class="button">
                                                <?php esc_html_e( 'Voir', 'ufsc-clubs' ); ?>
                                            </a>
                                            <a href="<?php echo esc_url( $attestation['url'] ); ?>" download class="button" id="btn-telechrager-attestation">
                                                <?php esc_html_e( 'Télécharger', 'ufsc-clubs' ); ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php else : ?>
                                    <p class="ufsc-document-status"><?php esc_html_e( 'En cours de génération', 'ufsc-clubs' ); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
                $profile_bureau = self::get_bureau_coverage_data( (int) $club->id );
                $profile_missing_roles = ! empty( $profile_bureau['missing_labels'] ) ? count( $profile_bureau['missing_labels'] ) : 0;
                $profile_docs_fields = array( 'doc_statuts', 'doc_recepisse', 'doc_jo', 'doc_pv_ag', 'doc_cer', 'doc_attestation_cer' );
                $profile_missing_docs = 0;
                foreach ( $profile_docs_fields as $profile_doc_key ) {
                    $profile_doc_value = isset( $club->$profile_doc_key ) ? $club->$profile_doc_key : '';
                    if ( empty( $profile_doc_value ) || ! wp_get_attachment_url( $profile_doc_value ) ) {
                        $profile_missing_docs++;
                    }
                }
            ?>
            <div class="ufsc-profile-insight-band">
                <div class="ufsc-card ufsc-profile-insight">
                    <span><?php esc_html_e( 'Statut global', 'ufsc-clubs' ); ?></span>
					<?php echo self::get_status_badge_front( $club_status, $annual_presentation['label'] ?? '' ); ?>
                </div>
                <div class="ufsc-card ufsc-profile-insight">
                    <strong class="ufsc-profile-insight__value"><?php echo esc_html( (int) $profile_missing_roles ); ?></strong>
                    <span class="ufsc-profile-insight__label"><?php esc_html_e( 'Rôles manquants', 'ufsc-clubs' ); ?></span>
                </div>
                <div class="ufsc-card ufsc-profile-insight">
                    <strong class="ufsc-profile-insight__value"><?php echo esc_html( (int) $profile_missing_docs ); ?></strong>
                    <span class="ufsc-profile-insight__label"><?php esc_html_e( 'Documents manquants', 'ufsc-clubs' ); ?></span>
                </div>
                <div class="ufsc-card ufsc-profile-insight">
                    <span><?php esc_html_e( 'Attestation UFSC', 'ufsc-clubs' ); ?></span>
                    <strong><?php echo ! empty( $attestation['url'] ) ? esc_html__( 'Disponible', 'ufsc-clubs' ) : esc_html__( 'En attente', 'ufsc-clubs' ); ?></strong>
                </div>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ufsc-club-form ufsc-club-profile">
                <div class="ufsc-notices" aria-live="polite"></div>
                <input type="hidden" name="action" value="ufsc_save_club">
                <input type="hidden" name="club_id" value="<?= (int) $club->id ?>" />
                <?php wp_nonce_field( 'ufsc_save_club', 'ufsc_club_nonce' ); ?>
                <div class="ufsc-club-profile-layout">
                    <div class="ufsc-club-profile-main ufsc-profile-cards">
                <div class="ufsc-card ufsc-section" id="ufsc-club-information">
                    <h4><?php esc_html_e( 'Identité du club', 'ufsc-clubs' ); ?></h4>

                    <div class="ufsc-grid">
                        <?php self::render_field( 'nom', $club, __( 'Nom du club', 'ufsc-clubs' ), 'text', true, $is_admin ); ?>
                        <div class="ufsc-field">
                            <label for="region" class="ufsc-label required"><?php esc_html_e( 'Région', 'ufsc-clubs' ); ?></label>
                            <select id="region" name="region" required>
                                <option value=""><?php esc_html_e( 'Sélectionner une région', 'ufsc-clubs' ); ?></option>
                                <?php foreach ( $regions as $region ): ?>
                                    <option value="<?php echo esc_attr( $region ); ?>" <?php selected( $club->region ?? '', $region ); ?>>
                                        <?php echo esc_html( $region ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="ufsc-field-error" aria-live="polite"></div>
                        </div>

                        <?php self::render_field( 'num_affiliation', $club, __( 'N° d\'affiliation', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'statut', $club, __( 'Statut', 'ufsc-clubs' ), 'text', true, false ); ?>
                    </div>
                </div>

                <!-- // UFSC: Coordonnées -->
                <div class="ufsc-card ufsc-section">
                    <h4><?php esc_html_e( 'Coordonnées', 'ufsc-clubs' ); ?></h4>

                    <div class="ufsc-grid">
                        <?php self::render_field( 'adresse', $club, __( 'Adresse', 'ufsc-clubs' ), 'textarea', false, $is_admin ); ?>
                        <?php self::render_field( 'code_postal', $club, __( 'Code postal', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'ville', $club, __( 'Ville', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'email', $club, __( 'Email', 'ufsc-clubs' ), 'email', false, true ); ?>
                        <?php self::render_field( 'telephone', $club, __( 'Téléphone', 'ufsc-clubs' ), 'tel', false, true ); ?>
                    </div>
                </div>

                <div class="ufsc-card ufsc-form-section">
                    <h4><?php esc_html_e( 'Informations légales', 'ufsc-clubs' ); ?></h4>

                    <div class="ufsc-grid">
                        <?php self::render_field( 'siren', $club, __( 'SIREN', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'ape', $club, __( 'APE', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'ccn', $club, __( 'CCN', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'ancv', $club, __( 'ANCV', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'rna_number', $club, __( 'Numéro RNA', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'num_declaration', $club, __( 'N° déclaration', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                        <?php self::render_field( 'date_declaration', $club, __( 'Date déclaration', 'ufsc-clubs' ), 'date', false, $is_admin ); ?>
                    </div>
                </div>

                <div class="ufsc-card ufsc-form-section ufsc-section-board ufsc-club-portal__section--full" id="ufsc-club-officers">
                    <h4><?php esc_html_e( 'Dirigeants', 'ufsc-clubs' ); ?></h4>
                    <p class="ufsc-admin-help"><?php esc_html_e( 'La licence individuelle de la saison est la source de référence. Les anciennes coordonnées du club ne sont pas utilisées pour attribuer une fonction.', 'ufsc-clubs' ); ?></p>
                    <div class="ufsc-board-columns">
                        <?php self::render_officer_licence_card( 'president', $profile_bureau, $current_season ); ?>
                        <?php self::render_officer_licence_card( 'secretaire', $profile_bureau, $current_season ); ?>
                        <?php self::render_officer_licence_card( 'tresorier', $profile_bureau, $current_season ); ?>
                    </div>

                    <div class="ufsc-board-role-card ufsc-board-role-card--coach">
                        <h5><?php esc_html_e( 'Entraîneur', 'ufsc-clubs' ); ?></h5>
                        <div class="ufsc-grid">
                            <?php self::render_field( 'entraineur_prenom', $club, __( 'Prénom', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                            <?php self::render_field( 'entraineur_nom', $club, __( 'Nom', 'ufsc-clubs' ), 'text', false, $is_admin ); ?>
                            <?php self::render_field( 'entraineur_tel', $club, __( 'Téléphone', 'ufsc-clubs' ), 'tel', false, $is_admin ); ?>
                            <?php self::render_field( 'entraineur_email', $club, __( 'Email', 'ufsc-clubs' ), 'email', false, $is_admin ); ?>
                        </div>
                    </div>
                </div>

                <div class="ufsc-card ufsc-form-section">
                    <h4><?php esc_html_e( 'Réseaux sociaux', 'ufsc-clubs' ); ?></h4>

                    <div class="ufsc-grid">
                        <?php self::render_field( 'url_site', $club, __( 'Site web', 'ufsc-clubs' ), 'url', false, $is_admin ); ?>
                        <?php self::render_field( 'url_facebook', $club, __( 'Facebook', 'ufsc-clubs' ), 'url', false, $is_admin ); ?>
                        <?php self::render_field( 'url_instagram', $club, __( 'Instagram', 'ufsc-clubs' ), 'url', false, $is_admin ); ?>
                    </div>
                </div>

                <div class="ufsc-card ufsc-form-section">
                    <h4><?php esc_html_e( 'Chiffres et dates', 'ufsc-clubs' ); ?></h4>

                    <div class="ufsc-grid">
                        <?php self::render_field( 'date_creation', $club, __( 'Date de création', 'ufsc-clubs' ), 'date', false, $is_admin ); ?>
                        <?php self::render_field( 'date_affiliation', $club, __( 'Date d\'affiliation', 'ufsc-clubs' ), 'date', false, $is_admin ); ?>
                        <?php self::render_field( 'responsable_id', $club, __( 'ID responsable', 'ufsc-clubs' ), 'number', true, false ); ?>
                    </div>
                </div>

                <div class="ufsc-card ufsc-form-section ufsc-club-portal__section--full">
                    <h4><?php esc_html_e( 'Distribution', 'ufsc-clubs' ); ?></h4>

                    <div class="ufsc-grid">
                        <?php self::render_field( 'precision_distribution', $club, __( 'Précision distribution', 'ufsc-clubs' ), 'textarea', false, $is_admin ); ?>
                    </div>
                </div>
                    </div>

                </div>

                <!-- // UFSC: Submit section -->
                <div class="ufsc-form-actions ufsc-club-account__savebar">
                    <?php if ( $can_edit ): ?>
                        <button type="button" class="ufsc-btn ufsc-btn-secondary" data-ufsc-cancel><?php esc_html_e( 'Annuler', 'ufsc-clubs' ); ?></button>
                        <button type="submit" name="ufsc_save_club" class="ufsc-btn ufsc-btn-primary"><?php esc_html_e( 'Enregistrer les modifications', 'ufsc-clubs' ); ?></button>
                    <?php endif; ?>
                </div>

                <!-- // UFSC: Documents Section - 6 mandatory documents -->
                <div class="ufsc-club-profile-documents ufsc-club-account__documents ufsc-club-portal__section--full" id="ufsc-club-documents">
                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Mes documents', 'ufsc-clubs' ); ?></h4>

                        <?php
                        // // UFSC: 6 mandatory documents as per requirements
                        $mandatory_documents = array(
                            'doc_statuts' => __( 'Statuts', 'ufsc-clubs' ),
                            'doc_recepisse' => __( 'Récépissé', 'ufsc-clubs' ),
                            'doc_jo' => __( 'Journal Officiel', 'ufsc-clubs' ),
                            'doc_pv_ag' => __( 'PV Assemblée Générale', 'ufsc-clubs' ),
                            'doc_cer' => __( 'CER', 'ufsc-clubs' ),
                            'doc_attestation_cer' => __( 'Attestation CER', 'ufsc-clubs' )
                        );

                        $documents_state = array();
                        $missing_docs    = array();

                        foreach ( $mandatory_documents as $doc_key => $doc_label ) {
                            $doc_value = isset( $club->$doc_key ) ? $club->$doc_key : '';
                            $doc_url   = $doc_value ? wp_get_attachment_url( $doc_value ) : '';
                            $is_missing = empty( $doc_value ) || empty( $doc_url );

                            if ( $is_missing ) {
                                $missing_docs[ $doc_key ] = $doc_label;
                            }

                            $documents_state[ $doc_key ] = array(
                                'label' => $doc_label,
                                'value' => $doc_value,
                                'url'   => $doc_url,
                                'missing' => $is_missing,
                            );
                        }

                        $total_docs   = count( $mandatory_documents );
                        $received_docs = $total_docs - count( $missing_docs );
                        ?>

                        <div class="ufsc-document-summary">
                            <p class="ufsc-document-summary-count">
                                <?php echo esc_html( sprintf( __( 'Documents: %d / %d reçus', 'ufsc-clubs' ), $received_docs, $total_docs ) ); ?>
                            </p>
                            <?php if ( ! empty( $missing_docs ) ) : ?>
                                <p class="ufsc-document-summary-missing-label"><?php esc_html_e( 'Documents manquants :', 'ufsc-clubs' ); ?></p>
                                <ul class="ufsc-document-missing-list">
                                    <?php foreach ( $missing_docs as $missing_label ) : ?>
                                        <li><?php echo esc_html( $missing_label ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <div class="ufsc-grid ufsc-documents-grid">
                            <?php foreach ( $documents_state as $doc_key => $doc_state ) :
                                $upload_key = str_replace( 'doc_', '', $doc_key ) . '_upload';
                                ?>
                                <div class="ufsc-card ufsc-document-card">
                                    <div class="ufsc-document-header">
                                        <h5><?php echo esc_html( $doc_state['label'] ); ?></h5>
                                        <span class="ufsc-document-status">
                                            <?php if ( ! $doc_state['missing'] ) : ?>
                                                <span class="ufsc-badge ufsc-badge-success" aria-label="<?php esc_attr_e( 'Transmis', 'ufsc-clubs' ); ?>">✅</span>
                                            <?php else : ?>
                                                <span class="ufsc-badge ufsc-badge-pending" aria-label="<?php esc_attr_e( 'En attente', 'ufsc-clubs' ); ?>">⏳</span>
                                                <span class="ufsc-badge ufsc-badge-warning" aria-label="<?php esc_attr_e( 'Document manquant', 'ufsc-clubs' ); ?>">⚠ <?php esc_html_e( 'Document manquant', 'ufsc-clubs' ); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <div class="ufsc-document-content">
                                        <?php if ( ! empty( $doc_state['url'] ) ) : ?>
                                            <div class="ufsc-document-current">
                                                <p class="ufsc-document-name"><?php echo esc_html( basename( $doc_state['url'] ) ); ?></p>
                                                <div class="ufsc-document-actions">
                                                    <a href="<?php echo esc_url( $doc_state['url'] ); ?>" target="_blank" class="ufsc-btn-small">
                                                        <?php esc_html_e( 'Voir', 'ufsc-clubs' ); ?>
                                                    </a>
                                                    <a href="<?php echo esc_url( $doc_state['url'] ); ?>" download class="ufsc-btn-small">
                                                        <?php esc_html_e( 'Télécharger', 'ufsc-clubs' ); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ( $can_edit ): ?>
                                            <div class="ufsc-document-upload">
                                                <input type="file"
                                                       id="<?php echo esc_attr( $upload_key ); ?>"
                                                       name="<?php echo esc_attr( $upload_key ); ?>"
                                                       accept=".pdf,.jpg,.jpeg,.png"
                                                       class="ufsc-file-input">
                                                <label for="<?php echo esc_attr( $upload_key ); ?>" class="ufsc-upload-label">
                                                    <?php if ( ! empty( $doc_state['url'] ) ): ?>
                                                        <?php esc_html_e( 'Remplacer le document', 'ufsc-clubs' ); ?>
                                                    <?php else: ?>
                                                        <?php esc_html_e( 'Choisir un fichier', 'ufsc-clubs' ); ?>
                                                    <?php endif; ?>
                                                </label>
                                                <p class="ufsc-help-text">
                                                    <?php esc_html_e( 'Formats: PDF, JPG, PNG - Max 5MB', 'ufsc-clubs' ); ?>
                                                </p>
                                                <div class="ufsc-upload-feedback" role="status" aria-live="polite"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                </div>

            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render add licence section
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_add_licence( $atts = array() ) {
		wp_enqueue_style( 'ufsc-licence-form', UFSC_CL_URL . 'assets/css/ufsc-frontend.css', array(), UFSC_CL_VERSION );
		$layout_path    = UFSC_CL_DIR . 'assets/css/ufsc-licence-form.css';
		$layout_version = file_exists( $layout_path ) ? (string) filemtime( $layout_path ) : UFSC_CL_VERSION;
		wp_enqueue_style( 'ufsc-licence-form-layout', UFSC_CL_URL . 'assets/css/ufsc-licence-form.css', array( 'ufsc-licence-form' ), $layout_version );
		$script_path = UFSC_CL_DIR . 'assets/js/ufsc-license-form.js';
		wp_enqueue_script( 'ufsc-license-form', UFSC_CL_URL . 'assets/js/ufsc-license-form.js', array( 'jquery' ), file_exists( $script_path ) ? (string) filemtime( $script_path ) : UFSC_CL_VERSION, true );

        $atts = shortcode_atts( array(
            'club_id'    => 0,
            'licence_id' => 0,
        ), $atts );

        if ( ! $atts['club_id'] && is_user_logged_in() ) {
            $atts['club_id'] = self::get_user_club_id( get_current_user_id() );
        }

        if ( ! $atts['club_id'] ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Club non trouvé.', 'ufsc-clubs' ) .
                   '</div>';
        }

        $edit_licence_id = absint( $atts['licence_id'] );
        if ( $edit_licence_id <= 0 && isset( $_GET['edit_licence'] ) ) {
            $edit_licence_id = absint( $_GET['edit_licence'] );
        }

        $edit_licence = null;
        $is_edit_mode = $edit_licence_id > 0;
        $is_locked_licence = false;
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_clubs'];
        $pk       = $settings['pk_club'];

        $club_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT statut, nom FROM `{$table}` WHERE `{$pk}` = %d",
                $atts['club_id']
            ),
            ARRAY_A
        );

        if ($club_data && strtolower($club_data['statut']) === 'en_attente') {
            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice(__( '⚠ Vous devez régler les frais du club pour continuer.', 'ufsc-clubs' ),'error');
            }
            $cart = ( function_exists( 'WC' ) && WC() ) ? WC()->cart : null;
            if ( empty( $cart ) || empty( $cart->cart_contents ) ) {
                ufsc_add_affiliation_to_cart($atts['club_id']);
            }
            wp_safe_redirect(site_url('/checkout'));
            exit;
        }

        if ( $is_edit_mode ) {
            $edit_licence = self::get_licence( $atts['club_id'], $edit_licence_id );
            if ( ! $edit_licence ) {
                return '<div class="ufsc-message ufsc-error">' . esc_html__( 'Licence non trouvée.', 'ufsc-clubs' ) . '</div>';
            }
            $is_locked_licence = function_exists( 'ufsc_is_licence_locked_for_club' ) ? ufsc_is_licence_locked_for_club( $edit_licence ) : false;
        }

        $form_data   = array();
        $form_errors = array();

        if ( is_user_logged_in() ) {
            $form_key = 'ufsc_licence_form_' . get_current_user_id();
            $stored   = get_transient( $form_key );
            if ( $stored ) {
                $form_data   = $stored['data'] ?? array();
                $form_errors = $stored['errors'] ?? array();
                delete_transient( $form_key );
            }
        }

        if ( empty( $form_data ) && $edit_licence ) {
            $form_data = (array) $edit_licence;
        }

        if ( empty( $form_data['telephone'] ) ) {
            $form_data['telephone'] = self::resolve_licence_phone( $form_data );
        }
        if ( isset( $form_data['role'] ) ) {
            $form_data['role'] = sanitize_key( (string) $form_data['role'] );
        }
        if ( ! $is_edit_mode && empty( $form_data['role'] ) && isset( $_GET['ufsc_prefill_role'] ) && ! is_array( $_GET['ufsc_prefill_role'] ) ) {
            $prefill_role = sanitize_key( wp_unslash( $_GET['ufsc_prefill_role'] ) );
            if ( in_array( $prefill_role, array( 'president', 'secretaire', 'tresorier' ), true ) ) { $form_data['role'] = $prefill_role; }
        }

        // UFSC: default checked (stable + no regression)
        $form_data = is_array( $form_data ) ? $form_data : array();

        $assurance_dommage_checked = array_key_exists( 'assurance_dommage_corporel', $form_data )
            ? ! empty( $form_data['assurance_dommage_corporel'] )
            : true;

        $assurance_assistance_checked = array_key_exists( 'assurance_assistance', $form_data )
            ? ! empty( $form_data['assurance_assistance'] )
            : true;

        // UFSC: default note club name
        $club_name = isset( $club_data['nom'] ) ? sanitize_text_field( $club_data['nom'] ) : '';
        $default_note_prefix = $club_name
            ? sprintf( __( 'Club : %s - ', 'ufsc-clubs' ), $club_name )
            : __( 'Club : ', 'ufsc-clubs' );
        $note_value = array_key_exists( 'note', $form_data )
            ? $form_data['note']
            : $default_note_prefix;

        // Handle form submission
        if ( isset( $_POST['ufsc_add_licence'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_add_licence' ) ) {
            $result = self::handle_licence_creation( $atts['club_id'], $_POST );
            if ( $result['success'] ) {
                echo '<div class="ufsc-message ufsc-success">' . esc_html( $result['message'] ) . '</div>';
                if ( isset( $result['payment_url'] ) ) {
                    echo '<div class="ufsc-message ufsc-info">';
                    echo '<p>' . esc_html__( 'Paiement requis :', 'ufsc-clubs' ) . '</p>';
                    echo '<a href="' . esc_url( $result['payment_url'] ) . '" class="ufsc-btn ufsc-btn-primary">';
                    echo esc_html__( 'Procéder au paiement', 'ufsc-clubs' );
                    echo '</a>';
                    echo '<span class="ufsc-field-error" aria-live="polite"></span></div>';
                }
            } else {
                echo '<div class="ufsc-message ufsc-error">' . esc_html( $result['message'] ) . '</div>';
            }
        }

        ob_start();
        ?>
        <div class="ufsc-add-licence-section">
            <div class="ufsc-section-header">
                <h3><?php echo $is_edit_mode ? esc_html__( 'Modifier une licence', 'ufsc-clubs' ) : esc_html__( 'Ajouter une Licence', 'ufsc-clubs' ); ?></h3>
            </div>

            <?php if ( isset( $_GET['draft_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['draft_saved'] ) ) ) : ?><div class="ufsc-message ufsc-success" role="status"><?php esc_html_e( 'Brouillon enregistré', 'ufsc-clubs' ); ?> — <a href="<?php echo esc_url( add_query_arg( array( 'edit_licence' => $edit_licence_id ) ) ); ?>"><?php esc_html_e( 'Reprendre ce dossier', 'ufsc-clubs' ); ?></a></div><?php endif; ?>
            <?php if ( ! empty( $form_errors ) ) : ?>
                <div class="ufsc-message ufsc-error ufsc-form-error-summary" role="alert" tabindex="-1" data-ufsc-server-errors>
                    <strong><?php esc_html_e( 'Ce dossier ne peut pas être finalisé. Complétez les champs suivants :', 'ufsc-clubs' ); ?></strong>
                    <ul>
                    <?php foreach ( $form_errors as $error ) : ?>
                        <?php if ( is_array( $error ) ) : ?><li><a href="#<?php echo esc_attr( $error['field'] ?? '' ); ?>" data-ufsc-error-field="<?php echo esc_attr( $error['field'] ?? '' ); ?>" data-ufsc-error-step="<?php echo esc_attr( $error['step'] ?? 1 ); ?>"><?php echo esc_html( $error['message'] ?? '' ); ?></a></li><?php else : ?><li><?php echo esc_html( $error ); ?></li><?php endif; ?>
                    <?php endforeach; ?>
                    </ul>
                    <?php $first_error = reset( $form_errors ); if ( is_array( $first_error ) ) : ?><a class="ufsc-btn ufsc-btn-primary" href="#<?php echo esc_attr( $first_error['field'] ?? '' ); ?>" data-ufsc-error-field="<?php echo esc_attr( $first_error['field'] ?? '' ); ?>" data-ufsc-error-step="<?php echo esc_attr( $first_error['step'] ?? 1 ); ?>"><?php esc_html_e( 'Compléter ce dossier', 'ufsc-clubs' ); ?></a><?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-licence-form">

                <input type="hidden" name="action" value="ufsc_save_licence">
                <?php wp_nonce_field( 'ufsc_save_licence' ); ?>
                <!-- The clicked submit button is the authoritative business intent.  The
                     previous hidden field depended on inline JavaScript and therefore
                     posted "continue" when CSP blocked onclick handlers. -->
                <input type="hidden" id="ufsc_submit_action" value="continue"><input type="hidden" name="ufsc_wizard_step" id="ufsc_wizard_step" value="<?php echo isset( $_GET['ufsc_wizard_step'] ) ? esc_attr( min( 6, max( 1, absint( $_GET['ufsc_wizard_step'] ) ) ) ) : '1'; ?>">
                <input type="hidden" name="licence_id" value="<?php echo esc_attr( $edit_licence_id ); ?>">

                <div class="ufsc-notices" aria-live="polite"></div>
                <nav class="ufsc-licence-wizard-progress" aria-label="<?php esc_attr_e( 'Progression du dossier de licence', 'ufsc-clubs' ); ?>">
                    <ol><li data-wizard-indicator="1" aria-current="step">1. <?php esc_html_e( 'Identité', 'ufsc-clubs' ); ?></li><li data-wizard-indicator="2">2. <?php esc_html_e( 'Coordonnées', 'ufsc-clubs' ); ?></li><li data-wizard-indicator="3">3. <?php esc_html_e( 'Sport', 'ufsc-clubs' ); ?></li><li data-wizard-indicator="4">4. <?php esc_html_e( 'Réductions', 'ufsc-clubs' ); ?></li><li data-wizard-indicator="5">5. <?php esc_html_e( 'Santé', 'ufsc-clubs' ); ?></li><li data-wizard-indicator="6">6. <?php esc_html_e( 'Récapitulatif', 'ufsc-clubs' ); ?></li></ol>
                </nav>
                <div class="ufsc-licence-wizard-errors ufsc-message ufsc-error" role="alert" tabindex="-1" hidden></div>

                <!-- // UFSC: Enhanced form structure with conditional fields -->
                <div class="ufsc-grid">
                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Informations personnelles', 'ufsc-clubs' ); ?></h4>

                        <div class="ufsc-field">
                            <label for="nom"><?php esc_html_e( 'Nom *', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="nom" name="nom" value="<?php echo esc_attr( $form_data['nom'] ?? '' ); ?>" required>
                        </div>

                        <div class="ufsc-field">
                            <label for="prenom"><?php esc_html_e( 'Prénom *', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="prenom" name="prenom" value="<?php echo esc_attr( $form_data['prenom'] ?? '' ); ?>" required>
                        </div>

                        <div class="ufsc-field">
                            <label for="email"><?php esc_html_e( 'Email *', 'ufsc-clubs' ); ?></label>
                            <input type="email" id="email" name="email" value="<?php echo esc_attr( $form_data['email'] ?? '' ); ?>" required>
                        </div>

                        <div class="ufsc-field">
                            <label for="telephone"><?php esc_html_e( 'Téléphone *', 'ufsc-clubs' ); ?></label>
                            <input type="tel" id="telephone" name="telephone" value="<?php echo esc_attr( $form_data['telephone'] ?? '' ); ?>" required>
                        </div>

                        <div class="ufsc-field">
                            <label for="date_naissance"><?php esc_html_e( 'Date de naissance *', 'ufsc-clubs' ); ?></label>
                            <input type="date" id="date_naissance" name="date_naissance" value="<?php echo esc_attr( $form_data['date_naissance'] ?? '' ); ?>" required>
                        </div>

                        <div class="ufsc-field">
                            <label for="sexe"><?php esc_html_e( 'Sexe *', 'ufsc-clubs' ); ?></label>
                            <select id="sexe" name="sexe" required>
                                <option value=""><?php esc_html_e( 'Sélectionner', 'ufsc-clubs' ); ?></option>
                                <option value="M" <?php selected( $form_data['sexe'] ?? '', 'M' ); ?>><?php esc_html_e( 'Homme', 'ufsc-clubs' ); ?></option>
                                <option value="F" <?php selected( $form_data['sexe'] ?? '', 'F' ); ?>><?php esc_html_e( 'Femme', 'ufsc-clubs' ); ?></option>
                                <option value="Autre" <?php selected( $form_data['sexe'] ?? '', 'Autre' ); ?>><?php esc_html_e( 'Autre', 'ufsc-clubs' ); ?></option>
                            </select>
                        </div>

                        <div class="ufsc-field">
                            <label for="poids"><?php esc_html_e( 'Poids (kg)', 'ufsc-clubs' ); ?></label>
                            <input type="number" id="poids" name="poids" min="10" max="250" step="0.1" value="<?php echo esc_attr( $form_data['poids'] ?? '' ); ?>">
							<small><?php echo esc_html( sprintf( __( 'Utilisé pour détecter automatiquement la catégorie Kickboxing / Tatami / Assaut pour la saison %s.', 'ufsc-clubs' ), class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' ) ) ); ?></small>
                        </div>
                        <div class="ufsc-field ufsc-field--full">
                            <label for="fighter_level"><?php esc_html_e( 'Niveau sportif', 'ufsc-clubs' ); ?></label>
                            <select id="fighter_level" name="fighter_level" data-ufsc-fighter-level data-veteran-min-age="<?php echo esc_attr( ufsc_get_veteran_min_age() ); ?>">
                                <option value=""><?php esc_html_e( 'Non renseigné', 'ufsc-clubs' ); ?></option>
                                <?php foreach ( ufsc_get_fighter_levels() as $level_key => $level_label ) : ?>
                                    <option value="<?php echo esc_attr( $level_key ); ?>" <?php selected( $form_data['fighter_level'] ?? '', $level_key ); ?>><?php echo esc_html( $level_label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small data-ufsc-level-help><?php echo esc_html( sprintf( __( 'Mineur : Assaut. Majeur : Classe C, Classe B ou Classe A. Vétéran à partir de %d ans.', 'ufsc-clubs' ), ufsc_get_veteran_min_age() ) ); ?></small>
                        </div>
                    </div>

                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Adresse', 'ufsc-clubs' ); ?></h4>

                        <div class="ufsc-field">
                            <label for="adresse"><?php esc_html_e( 'Adresse complète *', 'ufsc-clubs' ); ?></label>
                            <textarea id="adresse" name="adresse" rows="3" required><?php echo esc_textarea( $form_data['adresse'] ?? '' );  ?></textarea>
                        </div>

                        <div class="ufsc-field">
                            <label for="ville"><?php esc_html_e( 'Ville *', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="ville" name="ville" value="<?php echo esc_attr( $form_data['ville'] ?? '' ); ?>" required>
                        </div>

                        <div class="ufsc-field">
                            <label for="code_postal"><?php esc_html_e( 'Code postal *', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="code_postal" name="code_postal" value="<?php echo esc_attr( $form_data['code_postal'] ?? '' ); ?>" pattern="[0-9]{5}" maxlength="5" required>
                        </div>
                        <div class="ufsc-field">
                            <label for="pays"><?php esc_html_e( 'Pays *', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="pays" name="pays" value="<?php echo esc_attr( $form_data['pays'] ?? 'France' ); ?>" autocomplete="country-name" required>
                        </div>
                    </div>
                </div>

                <div class="ufsc-grid">
                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Rôle et activité', 'ufsc-clubs' ); ?></h4>

                        <div class="ufsc-field">
                            <label for="role"><?php esc_html_e( 'Rôle dans le club', 'ufsc-clubs' ); ?></label>
                            <select id="role" name="role" required>
                                <option value=""><?php esc_html_e( 'Sélectionner', 'ufsc-clubs' ); ?></option>
                                <option value="president" <?php selected( $form_data['role'] ?? '', 'president' ); ?>><?php esc_html_e( 'Président', 'ufsc-clubs' ); ?></option>
                                <option value="secretaire" <?php selected( $form_data['role'] ?? '', 'secretaire' ); ?>><?php esc_html_e( 'Secrétaire', 'ufsc-clubs' ); ?></option>
                                <option value="tresorier" <?php selected( $form_data['role'] ?? '', 'tresorier' ); ?>><?php esc_html_e( 'Trésorier', 'ufsc-clubs' ); ?></option>
								<option value="dirigeant" <?php selected( $form_data['role'] ?? '', 'dirigeant' ); ?>><?php esc_html_e( 'Dirigeant', 'ufsc-clubs' ); ?></option>
								<option value="educateur" <?php selected( $form_data['role'] ?? '', 'educateur' ); ?>><?php esc_html_e( 'Éducateur', 'ufsc-clubs' ); ?></option>
                                <option value="entraineur" <?php selected( $form_data['role'] ?? '', 'entraineur' ); ?>><?php esc_html_e( 'Entraîneur', 'ufsc-clubs' ); ?></option>
								<option value="coach" <?php selected( $form_data['role'] ?? '', 'coach' ); ?>><?php esc_html_e( 'Coach', 'ufsc-clubs' ); ?></option>
								<option value="encadrant" <?php selected( $form_data['role'] ?? '', 'encadrant' ); ?>><?php esc_html_e( 'Encadrant', 'ufsc-clubs' ); ?></option>
								<option value="responsable_technique" <?php selected( $form_data['role'] ?? '', 'responsable_technique' ); ?>><?php esc_html_e( 'Responsable technique', 'ufsc-clubs' ); ?></option>
                                <option value="adherent" <?php selected( $form_data['role'] ?? '', 'adherent' ); ?>><?php esc_html_e( 'Adhérent', 'ufsc-clubs' ); ?></option>
								<option value="arbitre" <?php selected( $form_data['role'] ?? '', 'arbitre' ); ?>><?php esc_html_e( 'Arbitre / officiel', 'ufsc-clubs' ); ?></option>
								<option value="autre" <?php selected( $form_data['role'] ?? '', 'autre' ); ?>><?php esc_html_e( 'Autre rôle concerné', 'ufsc-clubs' ); ?></option>
                            </select>
                        </div>

                        <div class="ufsc-field">
                            <label for="competition"><?php esc_html_e( 'Type de pratique', 'ufsc-clubs' ); ?></label>
                            <select id="competition" name="competition">
                                <option value="0" <?php selected( $form_data['competition'] ?? '', 0 ); ?>><?php esc_html_e( 'Loisir', 'ufsc-clubs' ); ?></option>
                                <option value="1" <?php selected( $form_data['competition'] ?? '', 1 ); ?>><?php esc_html_e( 'Compétition', 'ufsc-clubs' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Licence antérieure', 'ufsc-clubs' ); ?></h4>
                        <p class="ufsc-help-text"><?php esc_html_e( 'Si le licencié possède déjà un numéro de licence', 'ufsc-clubs' ); ?></p>

                        <div class="ufsc-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="has_license_number" name="has_license_number" value="1" class="ufsc-toggle" <?php checked( ! empty( $form_data['has_license_number'] ) ); ?> >
                                <?php esc_html_e( 'Possède un numéro de licence antérieur', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-field ufsc-conditional-field" data-depends="has_license_number">
                            <label for="numero_licence"><?php esc_html_e( 'Numéro de licence antérieur', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="numero_licence" name="numero_licence" maxlength="10" pattern="[A-Za-z0-9]{1,10}" inputmode="text" value="<?php echo esc_attr( $form_data['numero_licence'] ?? '' ); ?>" aria-describedby="numero_licence_help"><small id="numero_licence_help"><?php esc_html_e( '1 à 10 lettres ou chiffres, sans espace.', 'ufsc-clubs' ); ?></small>
                        </div>
                    </div>
                </div>

                <div class="ufsc-grid">
                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Réductions et identifiants', 'ufsc-clubs' ); ?></h4>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="reduction_benevole" name="reduction_benevole" value="1" class="ufsc-toggle">
                                <?php esc_html_e( 'Réduction bénévole', 'ufsc-clubs' ); ?>
                            </label>
                        </div>
                        <div class="ufsc-form-field ufsc-conditional-field" data-depends="reduction_benevole">
                            <label for="reduction_benevole_num"><?php esc_html_e( 'Numéro bénévole', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="reduction_benevole_num" name="reduction_benevole_num">
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="reduction_postier" name="reduction_postier" value="1" class="ufsc-toggle">
                                <?php esc_html_e( 'Réduction postier', 'ufsc-clubs' ); ?>
                            </label>
                        </div>
                        <div class="ufsc-form-field ufsc-conditional-field" data-depends="reduction_postier">
                            <label for="reduction_postier_num"><?php esc_html_e( 'Matricule postier', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="reduction_postier_num" name="reduction_postier_num">
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="identifiant_laposte_flag" name="identifiant_laposte_flag" value="1" class="ufsc-toggle">
                                <?php esc_html_e( 'Identifiant La Poste', 'ufsc-clubs' ); ?>
                            </label>
                        </div>
                        <div class="ufsc-form-field ufsc-conditional-field" data-depends="identifiant_laposte_flag">
                            <label for="identifiant_laposte"><?php esc_html_e( 'Identifiant La Poste', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="identifiant_laposte" name="identifiant_laposte">
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="fonction_publique" name="fonction_publique" value="1">
                                <?php esc_html_e( 'Fonction publique', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="licence_delegataire" name="licence_delegataire" value="1" class="ufsc-toggle">
                                <?php esc_html_e( 'Licence délégataire', 'ufsc-clubs' ); ?>
                            </label>
                        </div>
                        <div class="ufsc-form-field ufsc-conditional-field" data-depends="licence_delegataire">
                            <label for="numero_licence_delegataire"><?php esc_html_e( 'Numéro de licence délégataire', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="numero_licence_delegataire" name="numero_licence_delegataire">
                        </div>
                    </div>

                    <div class="ufsc-card ufsc-form-section">
                        <h4><?php esc_html_e( 'Consents et assurances', 'ufsc-clubs' ); ?></h4>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="diffusion_image" name="diffusion_image" value="1">
                                <?php esc_html_e( 'Autoriser la diffusion d\'image', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="infos_fsasptt" name="infos_fsasptt" value="1">
                                <?php esc_html_e( 'Recevoir les informations FSASPTT', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="infos_asptt" name="infos_asptt" value="1">
                                <?php esc_html_e( 'Recevoir les informations ASPTT', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="infos_cr" name="infos_cr" value="1">
                                <?php esc_html_e( 'Recevoir les informations du CR', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="infos_partenaires" name="infos_partenaires" value="1">
                                <?php esc_html_e( 'Recevoir les informations partenaires', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="assurance_dommage_corporel" name="assurance_dommage_corporel" value="1" <?php checked( $assurance_dommage_checked ); ?>>
                                <?php esc_html_e( 'Assurance dommage corporel', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <div class="ufsc-form-field">
                            <label class="ufsc-checkbox-label">
                                <input type="checkbox" id="assurance_assistance" name="assurance_assistance" value="1" <?php checked( $assurance_assistance_checked ); ?>>
                                <?php esc_html_e( 'Assurance assistance', 'ufsc-clubs' ); ?>
                            </label>
                        </div>

                        <p class="ufsc-form-hint"><?php esc_html_e( 'Ces assurances sont incluses dans votre licence.', 'ufsc-clubs' ); ?></p>

                        <div class="ufsc-form-field">
                            <label for="note"><?php esc_html_e( 'Note', 'ufsc-clubs' ); ?></label>
                            <textarea id="note" name="note" rows="3"><?php echo esc_textarea( $note_value ); ?></textarea>
                        </div>
                    </div>
                </div>

				<section class="ufsc-card ufsc-form-section ufsc-compliance-section" aria-labelledby="ufsc-health-title">
					<h4 id="ufsc-health-title"><?php esc_html_e( 'Santé et conformité', 'ufsc-clubs' ); ?></h4>
					<div class="ufsc-compliance-document-links">
						<a data-ufsc-health-document="adult" class="ufsc-btn ufsc-btn-secondary ufsc-health-document-link ufsc-document-button" href="https://ufsc-france.fr/wp-content/uploads/2026/08/2024-08-28-QUESTIONNAIRE-SANTE-MAJEUR.pdf" target="_blank" rel="noopener"><?php esc_html_e( 'Consulter / télécharger le questionnaire majeur', 'ufsc-clubs' ); ?></a>
						<a data-ufsc-health-document="minor" hidden class="ufsc-btn ufsc-btn-secondary ufsc-health-document-link ufsc-document-button" href="https://ufsc-france.fr/wp-content/uploads/2026/08/2021-06-02-5-ANNEXE-4-QUESTIONNAIRE-SANTE-MINEUR.pdf" target="_blank" rel="noopener"><?php esc_html_e( 'Consulter / télécharger le questionnaire mineur', 'ufsc-clubs' ); ?></a>
					</div>
					<div id="ufsc-health-adult" class="ufsc-compliance-panel">
						<h5><?php esc_html_e( 'Questionnaire de santé majeur', 'ufsc-clubs' ); ?></h5>
						<label class="ufsc-checkbox-label" for="ufsc-health-confirm-adult"><input id="ufsc-health-confirm-adult" type="checkbox" name="health_questionnaire_confirmed" value="1" required <?php checked( ! empty( $form_data['health_questionnaire_confirmed'] ) ); ?>> <?php esc_html_e( 'Je confirme avoir pris connaissance du questionnaire de santé majeur.', 'ufsc-clubs' ); ?></label>
					</div>
					<div id="ufsc-health-minor" class="ufsc-compliance-panel" hidden>
						<h5><?php esc_html_e( 'Questionnaire de santé mineur', 'ufsc-clubs' ); ?></h5>
						<label for="legal_representative_name"><?php esc_html_e( 'Identité du représentant légal', 'ufsc-clubs' ); ?></label>
						<input type="text" id="legal_representative_name" name="legal_representative_name" value="<?php echo esc_attr( $form_data['legal_representative_name'] ?? '' ); ?>">
						<label class="ufsc-checkbox-label" for="ufsc-health-confirm-minor"><input id="ufsc-health-confirm-minor" type="checkbox" name="health_questionnaire_confirmed" value="1" required disabled <?php checked( ! empty( $form_data['health_questionnaire_confirmed'] ) ); ?>> <?php esc_html_e( 'Le représentant légal confirme avoir pris connaissance du questionnaire de santé mineur.', 'ufsc-clubs' ); ?></label>
					</div>
					<div id="ufsc-honorability" class="ufsc-compliance-panel" hidden>
						<h5><?php esc_html_e( 'Contrôle de l’honorabilité', 'ufsc-clubs' ); ?></h5>
						<p><?php esc_html_e( 'Les dirigeants, éducateurs, entraîneurs, coachs, encadrants et responsables du club sont soumis aux obligations de contrôle de l’honorabilité applicables à leur fonction.', 'ufsc-clubs' ); ?></p>
						<a class="ufsc-btn ufsc-btn-secondary ufsc-document-button" href="https://ufsc-france.fr/wp-content/uploads/2026/08/2021-06-02-2-ANNEXE-1-NOTE-SUR-LE-CONTROLE-DE-LHONORABILITE.pdf" target="_blank" rel="noopener"><?php esc_html_e( 'Lire la note sur le contrôle de l’honorabilité', 'ufsc-clubs' ); ?></a>
						<label class="ufsc-checkbox-label" for="ufsc-honorability-confirmed"><input id="ufsc-honorability-confirmed" type="checkbox" name="honorability_confirmed" value="1" <?php checked( ! empty( $form_data['honorability_confirmed'] ) ); ?>> <?php esc_html_e( 'Je confirme avoir transmis ou complété l’attestation d’honorabilité requise pour cette fonction.', 'ufsc-clubs' ); ?></label>
						<div class="ufsc-message ufsc-warning"><strong><?php esc_html_e( 'Attestation d’honorabilité — Document obligatoire à transmettre pour finaliser le dossier.', 'ufsc-clubs' ); ?></strong><br><?php esc_html_e( 'Le dépôt reste recommandé avant finalisation et ne bloque ni le brouillon, ni le panier, ni le paiement.', 'ufsc-clubs' ); ?></div>
					</div>
				</section>

                <div class="ufsc-licence-wizard-review" data-wizard-review hidden aria-live="polite"><h4><?php esc_html_e( 'Récapitulatif avant panier', 'ufsc-clubs' ); ?></h4><dl></dl></div>
                <div class="ufsc-licence-wizard-navigation"><button type="button" class="ufsc-btn ufsc-btn-secondary" data-wizard-previous><?php esc_html_e( 'Précédent', 'ufsc-clubs' ); ?></button><button type="submit" name="ufsc_submit_action" value="save_draft" class="ufsc-btn ufsc-btn-secondary" formnovalidate data-wizard-save-draft><?php esc_html_e( 'Enregistrer en brouillon', 'ufsc-clubs' ); ?></button><button type="button" class="ufsc-btn ufsc-btn-primary" data-wizard-next><?php esc_html_e( 'Continuer', 'ufsc-clubs' ); ?></button></div>

				<div class="ufsc-form-actions ufsc-licence-final-actions">
                    <?php if ( ! $is_locked_licence ) : ?>
                        <?php echo self::render_pre_payment_warning_block(); ?>
						<p class="ufsc-final-help"><?php esc_html_e( 'Enregistrez un brouillon pour compléter la licence plus tard. Ajoutez au panier uniquement lorsque toutes les informations ont été vérifiées.', 'ufsc-clubs' ); ?></p>
						<p class="ufsc-cart-confirmation"><?php esc_html_e( 'Le club confirme que les informations saisies sont exactes et que l’adhérent ou son représentant légal a accompli les démarches nécessaires relatives au questionnaire de santé.', 'ufsc-clubs' ); ?></p>
						<div class="ufsc-final-buttons">
                        <button type="submit" name="ufsc_submit_action" value="save_draft" formnovalidate class="ufsc-btn ufsc-btn-secondary">
							<?php esc_html_e( 'Enregistrer comme brouillon', 'ufsc-clubs' ); ?>
                        </button>
                        <button type="submit" name="ufsc_submit_action" value="add_to_cart" class="ufsc-btn ufsc-btn-primary">
							<?php esc_html_e( 'Ajouter au panier', 'ufsc-clubs' ); ?>
                        </button>
                        <?php if ( $is_edit_mode ) : ?>
                            <button type="submit" form="ufsc-delete-licence-from-edit" class="ufsc-btn ufsc-btn-danger">
                                <?php esc_html_e( 'Supprimer', 'ufsc-clubs' ); ?>
                            </button>
                        <?php endif; ?>
						</div>
                    <?php else : ?>
                        <div class="ufsc-message ufsc-info">
                            <?php esc_html_e( 'Licence en traitement/validée : modification et suppression désactivées.', 'ufsc-clubs' ); ?>
                        </div>
                        <a href="<?php echo esc_url( remove_query_arg( 'edit_licence' ) ); ?>" class="ufsc-btn ufsc-btn-secondary">
                            <?php esc_html_e( 'Retour aux licences', 'ufsc-clubs' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ( $is_edit_mode && function_exists( 'ufsc_role_requires_honorability' ) ) :
				$edit_role = $edit_licence->role ?? ( $edit_licence->fonction ?? 'pratiquant' );
				$edit_season = function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $edit_licence_id ) : ( class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : '' );
				$honorability_doc = ufsc_get_honorability_document( $edit_licence_id, $edit_season );
				$doc_labels = array( 'missing' => __( 'Attestation d’honorabilité manquante', 'ufsc-clubs' ), 'pending' => __( 'En attente de validation', 'ufsc-clubs' ), 'validated' => __( 'Validée', 'ufsc-clubs' ), 'rejected' => __( 'Refusée', 'ufsc-clubs' ), 'correction_required' => __( 'À corriger', 'ufsc-clubs' ), 'expired' => __( 'Expirée', 'ufsc-clubs' ) );
			?>
				<?php if ( ufsc_role_requires_honorability( $edit_role ) ) : ?>
				<section class="ufsc-card ufsc-honorability-document-card">
					<h4><?php esc_html_e( 'Attestation d’honorabilité', 'ufsc-clubs' ); ?></h4>
					<p><strong><?php echo esc_html( $doc_labels[ $honorability_doc['status'] ] ?? $honorability_doc['status'] ); ?></strong> — <?php echo esc_html( $honorability_doc['uploaded_at'] ?: __( 'Aucun dépôt', 'ufsc-clubs' ) ); ?></p>
					<?php if ( $honorability_doc['reason'] ) : ?><div class="ufsc-message ufsc-warning"><?php echo esc_html( $honorability_doc['reason'] ); ?></div><?php endif; ?>
					<?php if ( $honorability_doc['attachment_id'] ) : $doc_url = wp_get_attachment_url( $honorability_doc['attachment_id'] ); ?><p><a href="<?php echo esc_url( $doc_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Voir', 'ufsc-clubs' ); ?></a> · <a href="<?php echo esc_url( $doc_url ); ?>" download><?php esc_html_e( 'Télécharger', 'ufsc-clubs' ); ?></a></p><?php endif; ?>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'ufsc_honorability_attestation_' . $edit_licence_id ); ?>
						<input type="hidden" name="action" value="ufsc_upload_honorability_attestation"><input type="hidden" name="licence_id" value="<?php echo esc_attr( $edit_licence_id ); ?>"><input type="hidden" name="club_id" value="<?php echo esc_attr( $atts['club_id'] ); ?>">
						<label for="honorability_attestation"><?php echo esc_html( $honorability_doc['attachment_id'] ? __( 'Remplacer l’attestation', 'ufsc-clubs' ) : __( 'Déposer l’attestation', 'ufsc-clubs' ) ); ?></label>
						<input required type="file" id="honorability_attestation" name="honorability_attestation" accept=".pdf,.jpg,.jpeg,.png"><button class="ufsc-btn ufsc-btn-secondary"><?php echo esc_html( $honorability_doc['attachment_id'] ? __( 'Remplacer', 'ufsc-clubs' ) : __( 'Déposer', 'ufsc-clubs' ) ); ?></button>
					</form>
				</section>
				<?php endif; ?>
			<?php endif; ?>
            <?php if ( $is_edit_mode && ! $is_locked_licence ) : ?>
                <form id="ufsc-delete-licence-from-edit" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
                    <?php wp_nonce_field( 'ufsc_delete_licence' ); ?>
                    <input type="hidden" name="action" value="ufsc_delete_licence">
                    <input type="hidden" name="licence_id" value="<?php echo esc_attr( $edit_licence_id ); ?>">
                </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render licences list or form based on action
     */
    public static function render_licences( $atts = array() ) {
        $atts = shortcode_atts( array(
            'club_id' => 0,
        ), $atts );

        if ( ! $atts['club_id'] && is_user_logged_in() ) {
            $atts['club_id'] = self::get_user_club_id( get_current_user_id() );
        }

        if ( ! $atts['club_id'] ) {
            return '<div class="ufsc-message ufsc-error">' .
                   esc_html__( 'Club non trouv\u00e9.', 'ufsc-clubs' ) .
                   '</div>';
        }

        $action     = isset( $_GET['ufsc_action'] ) ? sanitize_key( $_GET['ufsc_action'] ) : '';
        $licence_id = isset( $_GET['licence_id'] ) ? intval( $_GET['licence_id'] ) : 0;

        wp_enqueue_style( 'ufsc-front', UFSC_CL_URL . 'assets/css/ufsc-front.css', array(), function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/css/ufsc-front.css' ) : UFSC_CL_VERSION );
        wp_enqueue_script( 'ufsc-licences', UFSC_CL_URL . 'assets/js/ufsc-licences.js', array( 'jquery' ), function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/js/ufsc-licences.js' ) : UFSC_CL_VERSION, true );

        ob_start();
        if ( in_array( $action, array( 'edit', 'new' ), true ) ) {
            $licence = null;
            if ( 'edit' === $action && $licence_id ) {
                $licence = self::get_licence( $atts['club_id'], $licence_id );
            }
            echo self::render_add_licence( array( 'club_id' => $atts['club_id'], 'licence_id' => $licence_id ) );
        } else {
            $licences     = self::get_club_licences( $atts['club_id'], array( 'per_page' => 100 ) );
            $wc_settings  = function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings() : array();
            include UFSC_CL_DIR . 'templates/frontend/licences-list.php';
        }
        return ob_get_clean();
    }

    /**
     * Get single licence
     */
    private static function get_licence( $club_id, $licence_id ) {
        global $wpdb;
        if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
            return null;
        }
        $table = ufsc_get_licences_table();
        $columns = function_exists( 'ufsc_table_columns' )
            ? ufsc_table_columns( $table )
            : $wpdb->get_col( "DESCRIBE `{$table}`" );

        $select_fields = '*';
        if ( in_array( 'statut', $columns, true ) ) {
            $select_fields = "*, `statut` AS licence_statut";
        }

        $where = "id = %d AND club_id = %d";
        if ( in_array( 'deleted_at', $columns, true ) ) {
            $where .= " AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        }

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT {$select_fields} FROM `{$table}` WHERE {$where}", $licence_id, $club_id ) );

        return self::normalize_licence_row_for_display( $row );
    }

    // Helper methods

    /**
     * Get user club ID
     *
     * @param int $user_id User ID
     * @return int|false   Club ID or false if none
     */
    private static function get_user_club_id( $user_id ) {
        if ( function_exists( 'ufsc_get_user_club_id' ) ) {
            return ufsc_get_user_club_id( $user_id );
        }

        global $wpdb;

        if ( ! class_exists( 'UFSC_SQL' ) ) {
            return false;
        }

        $settings        = UFSC_SQL::get_settings();
        $clubs_table     = $settings['table_clubs'];
        $pk_col          = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id';
        $responsable_col = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'responsable_id' ) : 'responsable_id';

        $club_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `{$pk_col}` FROM `{$clubs_table}` WHERE `{$responsable_col}` = %d LIMIT 1",
                $user_id
            )
        );

        return $club_id ? (int) $club_id : false;
    }

    /**
     * Get club name
     */
    private static function get_club_name( $club_id ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) { return "Club #{$club_id}"; }
        $clubs_table = ufsc_get_clubs_table();
        $name = $wpdb->get_var( $wpdb->prepare(
            "SELECT nom FROM `{$clubs_table}` WHERE id = %d LIMIT 1",
            $club_id
        ) );
        return $name ? $name : "Club #{$club_id}";
    }

    /**
     * Render pre-payment warning (Option B).
     *
     * @return string
     */
    private static function render_pre_payment_warning_block() {
        return '<div class="ufsc-message ufsc-info" style="margin:10px 0;">'
            . '<strong>' . esc_html__( 'Vérification obligatoire avant paiement', 'ufsc-clubs' ) . '</strong><br>'
            . esc_html__( 'Merci de contrôler les informations saisies (nom/prénom, date de naissance, catégorie, coordonnées).', 'ufsc-clubs' ) . '<br>'
            . esc_html__( 'Une fois le paiement effectué, la licence passe en traitement et ne peut plus être modifiée en autonomie.', 'ufsc-clubs' ) . '<br>'
            . esc_html__( 'Toute correction demandée après paiement est soumise à des frais de traitement administratif de 5 €.', 'ufsc-clubs' )
            . '</div>';
    }

    /**
     * Check if a specific licence is already present in current cart.
     *
     * @param int $licence_id Licence ID.
     * @return bool
     */
    private static function is_licence_in_cart( $licence_id ) {
        if ( $licence_id <= 0 || ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }

        foreach ( WC()->cart->get_cart() as $item ) {
            $ids = array();

            if ( isset( $item['ufsc_license_ids'] ) && is_array( $item['ufsc_license_ids'] ) ) {
                $ids = array_merge( $ids, $item['ufsc_license_ids'] );
            }
            if ( isset( $item['ufsc_licence_id'] ) ) {
                $ids[] = $item['ufsc_licence_id'];
            }

            $ids = array_map( 'absint', $ids );
            if ( in_array( $licence_id, $ids, true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get club licences with pagination and filters
     */
    private static function append_strict_season_clause( &$clauses, &$values, $table, $columns, $season ) {
        $season = str_replace( '/', '-', sanitize_text_field( (string) $season ) );
        if ( '' === $season ) {
            return;
        }

        $column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $table ) : '';
        if ( '' === $column || ! in_array( $column, (array) $columns, true ) ) {
            // A requested season must never degrade to an unfiltered query.
            $clauses[] = '1 = 0';
            return;
        }

        if ( 'season_end_year' === $column ) {
            $end_year = preg_match( '/^\d{4}-(\d{4})$/', $season, $matches ) ? absint( $matches[1] ) : 0;
            $clauses[] = $end_year ? "`{$column}` = %d" : '1 = 0';
            if ( $end_year ) {
                $values[] = $end_year;
            }
            return;
        }

        $clauses[] = "REPLACE(TRIM(`{$column}`), '/', '-') = %s";
        $values[]  = $season;
    }

    /** Apply the same normalized demographic definition used by UFSC_Stats. */
    private static function append_demographic_clauses( &$clauses, &$values, $columns, $args ) {
        if ( ! empty( $args['gender'] ) && in_array( 'sexe', $columns, true ) ) {
            $clauses[] = 'F' === $args['gender']
                ? "UPPER(TRIM(`sexe`)) IN ('F','FEMME')"
                : "UPPER(TRIM(`sexe`)) IN ('M','H','HOMME')";
        }
        if ( ! empty( $args['practice'] ) && in_array( 'competition', $columns, true ) ) {
            $clauses[] = '`competition` = %d';
            $values[] = 'competition' === $args['practice'] ? 1 : 0;
        }
        if ( ! empty( $args['age'] ) && in_array( 'date_naissance', $columns, true ) ) {
            $clauses[] = 'minor' === $args['age']
                ? '`date_naissance` > DATE_SUB(CURDATE(), INTERVAL 18 YEAR) AND `date_naissance` <= CURDATE()'
                : '`date_naissance` <= DATE_SUB(CURDATE(), INTERVAL 18 YEAR) AND `date_naissance` >= \'1900-01-01\'';
        }
        if ( ! empty( $args['missing_profile'] ) ) {
            $missing = array();
            if ( in_array( 'sexe', $columns, true ) ) { $missing[] = "(`sexe` IS NULL OR UPPER(TRIM(`sexe`)) NOT IN ('F','FEMME','M','H','HOMME'))"; }
            if ( in_array( 'date_naissance', $columns, true ) ) { $missing[] = "(`date_naissance` IS NULL OR `date_naissance` = '' OR `date_naissance` = '0000-00-00' OR `date_naissance` > CURDATE() OR STR_TO_DATE(`date_naissance`, '%Y-%m-%d') IS NULL)"; }
            if ( in_array( 'competition', $columns, true ) ) { $missing[] = "(`competition` IS NULL OR CAST(`competition` AS CHAR) = '')"; }
            if ( $missing ) { $clauses[] = '(' . implode( ' OR ', $missing ) . ')'; }
        }
        if ( ! empty( $args['birth_from'] ) && in_array( 'date_naissance', $columns, true ) ) { $clauses[] = '`date_naissance` >= %s'; $values[] = $args['birth_from']; }
        if ( ! empty( $args['birth_to'] ) && in_array( 'date_naissance', $columns, true ) ) { $clauses[] = '`date_naissance` <= %s'; $values[] = $args['birth_to']; }
    }

    private static function get_club_licences( $club_id, $args ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
            return array();
        }
        $licences_table = ufsc_get_licences_table();

        $defaults = array(
            'search'   => '',
            'status'   => '',
            'season'   => '',
            'page'     => 1,
            'per_page' => 20,
            'sort'     => 'created_desc',
        );
        $args = wp_parse_args( $args, $defaults );

        // Colonnes disponibles
        $columns = function_exists( 'ufsc_table_columns' )
            ? ufsc_table_columns( $licences_table )
            : $wpdb->get_col( "DESCRIBE `{$licences_table}`" );

        // Clauses et valeurs de préparation
        $clauses = array( 'club_id = %d' );
        $values  = array( (int) $club_id );
        if ( in_array( 'deleted_at', $columns, true ) ) {
            $clauses[] = "(deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        }

        // Recherche
        if ( ! empty( $args['search'] ) ) {
            $search_fields = array();
            $search_values = array();
            foreach ( array( 'nom', 'nom_licence', 'prenom', 'email' ) as $field ) {
                if ( in_array( $field, $columns, true ) ) {
                    $search_fields[] = "`{$field}` LIKE %s";
                    $search_values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                }
            }
            if ( $search_fields ) {
                $clauses[] = '(' . implode( ' OR ', $search_fields ) . ')';
                $values    = array_merge( $values, $search_values );
            }
        }

        // Statut
        if ( ! empty( $args['status'] ) && in_array( 'statut', $columns, true ) ) {
            $raw_values = function_exists( 'ufsc_get_licence_status_raw_values_for_norm' )
                ? ufsc_get_licence_status_raw_values_for_norm( $args['status'] )
                : array( $args['status'] );
            if ( empty( $raw_values ) ) {
                $clauses[] = '1 = 0';
            } else {
                $placeholders = implode( ', ', array_fill( 0, count( $raw_values ), '%s' ) );
                $clauses[]    = "`statut` IN ({$placeholders})";
                $values       = array_merge( $values, $raw_values );
            }
        }

        // Saison
        self::append_strict_season_clause( $clauses, $values, $licences_table, $columns, $args['season'] );


        // Demographic filters are applied before LIMIT/OFFSET and share KPI normalization.
        self::append_demographic_clauses( $clauses, $values, $columns, $args );
        if ( ! empty( $args['renewal_state'] ) ) {
            $state = $args['renewal_state'];
            if ( 'incomplete' === $state ) {
                $missing = array();
                if ( in_array( 'fighter_level', $columns, true ) ) { $missing[] = "(`fighter_level` IS NULL OR `fighter_level` = '')"; }
                if ( in_array( 'poids', $columns, true ) ) { $missing[] = "(`poids` IS NULL OR `poids` = '')"; }
                if ( $missing ) { $clauses[] = '(' . implode( ' OR ', $missing ) . ')'; }
            } elseif ( in_array( 'statut', $columns, true ) ) {
                $state_statuses = array( 'draft' => array( 'brouillon', 'draft' ), 'payable' => array( 'a_regler', 'pending_payment', 'non_payee' ), 'renewed' => array( 'renouvelee', 'renewed' ), 'blocked' => array( 'bloquee', 'refusee', 'suspendue' ) );
                if ( isset( $state_statuses[ $state ] ) ) { $placeholders = implode( ',', array_fill( 0, count( $state_statuses[ $state ] ), '%s' ) ); $clauses[] = "`statut` IN ({$placeholders})"; $values = array_merge( $values, $state_statuses[ $state ] ); }
            }
        }

        // Tri
        $order_by = 'id DESC';
        switch ( $args['sort'] ) {
            case 'created_asc':
                $order_by = 'id ASC';
                break;
            case 'name_asc':
                if ( in_array( 'nom', $columns, true ) ) { $order_by = 'nom ASC'; }
                break;
            case 'name_desc':
                if ( in_array( 'nom', $columns, true ) ) { $order_by = 'nom DESC'; }
                break;
        }

        // Pagination
        $per_page = max( 1, (int) $args['per_page'] );
        $page     = isset( $args['page'] ) ? (int) $args['page'] : ( isset( $args['paged'] ) ? (int) $args['paged'] : 1 );
        $page     = max( 1, $page );
        $offset   = ( $page - 1 ) * $per_page;

        $where_sql = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';
        $select_fields = '*';
        if ( in_array( 'statut', $columns, true ) ) {
            $select_fields = "*, `statut` AS licence_statut";
        }

        $sql       = "SELECT {$select_fields} FROM `{$licences_table}` {$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d";
        $values[]  = $per_page;
        $values[]  = $offset;

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $idx => $row ) {
                $rows[ $idx ] = self::normalize_licence_row_for_display( $row );
            }
        }
        if ( ! empty( $rows ) && function_exists( 'ufsc_get_licence_season_label' ) ) {
            foreach ( $rows as $row ) {
                $row->season_label = ufsc_get_licence_season_label( $row );
                $row->saison       = $row->season_label;
            }
        }

        return $rows;
    }

    /**
     * Render a neutral/success category badge for front-office tables.
     *
     * @param string $label Category label.
     * @param string $status Detection status.
     * @return string
     */
    private static function render_category_badge( $label, $status ) {
        $label = trim( (string) $label );
        if ( '' === $label ) {
            if ( 'missing_weight' === $status ) {
                $label = __( 'Poids manquant', 'ufsc-clubs' );
            } elseif ( 'invalid_birthdate' === $status ) {
                $label = __( 'Date de naissance invalide', 'ufsc-clubs' );
            } else {
                $label = __( 'À compléter', 'ufsc-clubs' );
            }
        }

        $class = 'ok' === $status ? 'ufsc-badge ufsc-badge-success' : 'ufsc-badge ufsc-badge-neutral';
        return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
    }

    /**
     * Return the first non-empty display field from a licence row.
     *
     * @param object|array|null $licence Licence row.
     * @param string[]          $fields  Candidate fields.
     * @return string
     */
    private static function get_first_licence_field( $licence, $fields ) {
        if ( ! is_object( $licence ) && ! is_array( $licence ) ) {
            return '';
        }

        foreach ( (array) $fields as $field ) {
            $value = is_array( $licence ) ? ( $licence[ $field ] ?? '' ) : ( $licence->{$field} ?? '' );
            $value = trim( (string) $value );
            if ( '' !== $value ) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Resolve the best phone value from a licence row.
     *
     * @param object|array|null $licence Licence row.
     * @return string
     */
    private static function resolve_licence_phone( $licence ) {
        if ( ! is_object( $licence ) && ! is_array( $licence ) ) {
            return '';
        }

        $candidates = array( 'tel_mobile', 'telephone', 'mobile', 'tel_fixe' );
        foreach ( $candidates as $field ) {
            $value = is_array( $licence ) ? ( $licence[ $field ] ?? '' ) : ( $licence->{$field} ?? '' );
            $value = trim( (string) $value );
            if ( '' !== $value ) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Normalize legacy licence fields for consistent UI rendering.
     *
     * @param object|null $row Raw DB row.
     * @return object|null
     */
    private static function normalize_licence_row_for_display( $row ) {
        if ( ! is_object( $row ) ) {
            return $row;
        }

        $phone = self::resolve_licence_phone( $row );
        $row->telephone = $phone;
        if ( empty( $row->tel_mobile ) ) {
            $row->tel_mobile = $phone;
        }

        if ( isset( $row->role ) ) {
            $row->role = sanitize_key( (string) $row->role );
        }

        if ( ! empty( $row->date_inscription ) && is_string( $row->date_inscription ) ) {
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}\s+/', $row->date_inscription ) ) {
                $row->date_inscription = substr( $row->date_inscription, 0, 10 );
            } elseif ( '0000-00-00' === $row->date_inscription || '0000-00-00 00:00:00' === $row->date_inscription ) {
                $row->date_inscription = '';
            }
        }

        return $row;
    }

    /**
     * Get club licences count
     */
    /** Return only historical season labels without loading licence rows. */
    private static function get_club_archive_seasons( $club_id, $active_season ) {
        global $wpdb;
        if ( ! function_exists( 'ufsc_get_licences_table' ) ) { return array(); }
        $table = ufsc_get_licences_table();
        $columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $table ) : $wpdb->get_col( "DESCRIBE `{$table}`" );
        $season_col = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $table ) : '';
        if ( '' === $season_col ) { return array(); }
        $season_expression = 'season_end_year' === $season_col
            ? "CONCAT(CAST(`{$season_col}` AS UNSIGNED) - 1, '-', CAST(`{$season_col}` AS UNSIGNED))"
            : "REPLACE(TRIM(`{$season_col}`), '/', '-')";
        $where = array( 'club_id = %d', "`{$season_col}` IS NOT NULL", "TRIM(CAST(`{$season_col}` AS CHAR)) <> ''", "{$season_expression} <> %s" );
        $values = array( absint( $club_id ), str_replace( '/', '-', (string) $active_season ) );
        if ( in_array( 'deleted_at', $columns, true ) ) { $where[] = "(deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')"; }
        $sql = "SELECT DISTINCT {$season_expression} FROM `{$table}` WHERE " . implode( ' AND ', $where ) . " ORDER BY {$season_expression} DESC";
        $seasons = $wpdb->get_col( $wpdb->prepare( $sql, $values ) );
        return array_values( array_filter( array_map( static function ( $season ) {
            $season = str_replace( '/', '-', sanitize_text_field( (string) $season ) );
            return preg_match( '/^\d{4}-\d{4}$/', $season ) ? $season : '';
        }, (array) $seasons ) ) );
    }

    private static function get_club_licences_count( $club_id, $args ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
            return 0;
        }
        $licences_table = ufsc_get_licences_table();

        $defaults = array(
            'search' => '',
            'status' => '',
            'season' => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $columns = function_exists( 'ufsc_table_columns' )
            ? ufsc_table_columns( $licences_table )
            : $wpdb->get_col( "DESCRIBE `{$licences_table}`" );

        $clauses = array( 'club_id = %d' );
        $values  = array( (int) $club_id );
        if ( in_array( 'deleted_at', $columns, true ) ) {
            $clauses[] = "(deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        }

        // Recherche
        if ( ! empty( $args['search'] ) ) {
            $search_fields = array();
            $search_values = array();
            foreach ( array( 'nom', 'nom_licence', 'prenom', 'email' ) as $field ) {
                if ( in_array( $field, $columns, true ) ) {
                    $search_fields[] = "`{$field}` LIKE %s";
                    $search_values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                }
            }
            if ( $search_fields ) {
                $clauses[] = '(' . implode( ' OR ', $search_fields ) . ')';
                $values    = array_merge( $values, $search_values );
            }
        }

        // Statut
        if ( ! empty( $args['status'] ) && in_array( 'statut', $columns, true ) ) {
            $raw_values = function_exists( 'ufsc_get_licence_status_raw_values_for_norm' )
                ? ufsc_get_licence_status_raw_values_for_norm( $args['status'] )
                : array( $args['status'] );
            if ( empty( $raw_values ) ) {
                $clauses[] = '1 = 0';
            } else {
                $placeholders = implode( ', ', array_fill( 0, count( $raw_values ), '%s' ) );
                $clauses[]    = "`statut` IN ({$placeholders})";
                $values       = array_merge( $values, $raw_values );
            }
        }

        // Saison
        self::append_strict_season_clause( $clauses, $values, $licences_table, $columns, $args['season'] );


        self::append_demographic_clauses( $clauses, $values, $columns, $args );
        if ( ! empty( $args['renewal_state'] ) ) {
            $state = $args['renewal_state'];
            if ( 'incomplete' === $state ) {
                $missing = array();
                if ( in_array( 'fighter_level', $columns, true ) ) { $missing[] = "(`fighter_level` IS NULL OR `fighter_level` = '')"; }
                if ( in_array( 'poids', $columns, true ) ) { $missing[] = "(`poids` IS NULL OR `poids` = '')"; }
                if ( $missing ) { $clauses[] = '(' . implode( ' OR ', $missing ) . ')'; }
            } elseif ( in_array( 'statut', $columns, true ) ) {
                $state_statuses = array( 'draft' => array( 'brouillon', 'draft' ), 'payable' => array( 'a_regler', 'pending_payment', 'non_payee' ), 'renewed' => array( 'renouvelee', 'renewed' ), 'blocked' => array( 'bloquee', 'refusee', 'suspendue' ) );
                if ( isset( $state_statuses[ $state ] ) ) { $placeholders = implode( ',', array_fill( 0, count( $state_statuses[ $state ] ), '%s' ) ); $clauses[] = "`statut` IN ({$placeholders})"; $values = array_merge( $values, $state_statuses[ $state ] ); }
            }
        }

        $where_sql = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';
        $sql       = "SELECT COUNT(*) FROM `{$licences_table}` {$where_sql}";

        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Get bureau assignments and missing roles from licences.
     *
     * @param int $club_id Club identifier.
     * @return array{assignments: array<string,array<int>>, missing_labels: array<int,string>}
     */
    private static function get_bureau_coverage_data( $club_id, $season = '' ) {
        global $wpdb;

        $club_id = (int) $club_id;
        $data = array(
            'assignments' => array(
                'president' => array(),
                'secretaire' => array(),
                'tresorier' => array(),
                'adherent' => array(),
            ),
            'licences' => array( 'president' => array(), 'secretaire' => array(), 'tresorier' => array() ),
            'missing_labels' => array(),
            'status_code'    => 'non_conforme',
            'status_label'   => __( 'Bureau non conforme', 'ufsc-clubs' ),
        );

        if ( $club_id <= 0 || ! function_exists( 'ufsc_get_licences_table' ) ) {
            return $data;
        }

        $licences_table = ufsc_get_licences_table();
        $columns = function_exists( 'ufsc_table_columns' )
            ? ufsc_table_columns( $licences_table )
            : $wpdb->get_col( "DESCRIBE `{$licences_table}`" );

        if ( ! in_array( 'role', (array) $columns, true ) ) {
            return $data;
        }

        $season = $season ?: ( class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' ) );
        $where = "club_id = %d AND role IN ('president','secretaire','tresorier','adherent')";
        $values = array( $club_id );
        if ( in_array( 'deleted_at', (array) $columns, true ) ) {
            $where .= " AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        }
        $season_column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $licences_table ) : '';
        if ( $season_column && $season ) {
            if ( 'season_end_year' === $season_column && preg_match( '/^\d{4}-(\d{4})$/', $season, $matches ) ) {
                $where .= ' AND season_end_year = %d'; $values[] = (int) $matches[1];
            } else {
                $where .= " AND REPLACE(TRIM(`{$season_column}`), '/', '-') = %s"; $values[] = str_replace( '/', '-', $season );
            }
        } elseif ( $season ) {
            $where .= ' AND 0 = 1';
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$licences_table}` WHERE {$where} ORDER BY id DESC",
                $values
            )
        );

        foreach ( (array) $rows as $row ) {
            $role = sanitize_key( (string) ( $row->role ?? '' ) );
            if ( isset( $data['assignments'][ $role ] ) ) {
                $data['assignments'][ $role ][] = (int) ( $row->id ?? 0 );
            }
            if ( isset( $data['licences'][ $role ] ) ) { $data['licences'][ $role ][] = $row; }
        }

        $role_labels = array(
            'president' => __( 'Président', 'ufsc-clubs' ),
            'secretaire' => __( 'Secrétaire', 'ufsc-clubs' ),
            'tresorier' => __( 'Trésorier', 'ufsc-clubs' ),
        );
        foreach ( $role_labels as $role => $label ) {
            if ( empty( $data['assignments'][ $role ] ) ) {
                $data['missing_labels'][] = sprintf( __( '%s non licencié', 'ufsc-clubs' ), $label );
            }
        }

        if ( empty( $data['missing_labels'] ) ) {
            $data['status_code']  = 'a_jour';
            $data['status_label'] = __( 'Bureau à jour', 'ufsc-clubs' );
        } else {
            $data['status_code']  = 'incomplet';
            $data['status_label'] = __( 'Bureau incomplet', 'ufsc-clubs' );
        }

        return $data;
    }

    /**
     * Render bureau badges for one licence line.
     *
     * @param int   $licence_id Licence identifier.
     * @param array $assignments Role assignments.
     * @return string
     */
    private static function render_bureau_badges_for_front_licence( $licence_id, $assignments ) {
        $role_labels = array(
            'president' => __( 'Président', 'ufsc-clubs' ),
            'secretaire' => __( 'Secrétaire', 'ufsc-clubs' ),
            'tresorier' => __( 'Trésorier', 'ufsc-clubs' ),
            'adherent'  => __( 'Adhérent', 'ufsc-clubs' ),
        );

        $badges = array();
        foreach ( $role_labels as $role => $label ) {
            $ids = isset( $assignments[ $role ] ) ? array_map( 'intval', (array) $assignments[ $role ] ) : array();
            if ( in_array( (int) $licence_id, $ids, true ) ) {
                $badges[] = '<span class="ufsc-badge badge-info" style="margin-right:4px;">' . esc_html( $label ) . '</span>';
            }
        }

        return $badges ? implode( ' ', $badges ) : '—';
    }

    /** Render the canonical season licence attached to one statutory office. */
    private static function render_officer_licence_card( $role, $bureau, $season ) {
        $labels = array( 'president' => __( 'Président', 'ufsc-clubs' ), 'secretaire' => __( 'Secrétaire', 'ufsc-clubs' ), 'tresorier' => __( 'Trésorier', 'ufsc-clubs' ) );
        if ( ! isset( $labels[ $role ] ) ) { return; }
        $rows = array_values( (array) ( $bureau['licences'][ $role ] ?? array() ) );
        $licence = $rows ? $rows[0] : null;
        $create_url = add_query_arg( array( 'ufsc_tab' => 'add_licence', 'ufsc_prefill_role' => $role ), self::get_club_portal_url( 'licences' ) ) . '#ufsc-section-add_licence';
        ?>
        <article class="ufsc-board-role-card" aria-labelledby="ufsc-officer-<?php echo esc_attr( $role ); ?>">
            <div class="ufsc-board-role-card__heading"><h5 id="ufsc-officer-<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $labels[ $role ] ); ?></h5><span class="ufsc-badge <?php echo $licence ? 'ufsc-badge-success' : 'ufsc-badge-warning'; ?>"><?php echo $licence ? esc_html__( 'Licence liée', 'ufsc-clubs' ) : esc_html__( 'À créer', 'ufsc-clubs' ); ?></span></div>
            <?php if ( count( $rows ) > 1 ) : ?><div class="ufsc-message ufsc-error" role="alert"><?php echo esc_html( sprintf( __( 'Doublon détecté : %d licences portent déjà cette fonction pour la saison.', 'ufsc-clubs' ), count( $rows ) ) ); ?></div><?php endif; ?>
            <?php if ( $licence ) :
                $licence_id = absint( $licence->id ?? 0 );
                $status_raw = $licence->statut ?? ( $licence->status ?? '' );
                $detail_url = self::get_licence_detail_url( $licence_id );
                $edit_url = add_query_arg( array( 'ufsc_tab' => 'add_licence', 'edit_licence' => $licence_id ), self::get_club_portal_url( 'licences' ) ) . '#ufsc-section-add_licence';
                $start = (int) substr( (string) $season, 0, 4 );
                $next_season = $start ? ( $start + 1 ) . '-' . ( $start + 2 ) : '';
                $renew_url = add_query_arg( array( 'ufsc_section' => 'licences-renouvellement', 'renew_source_id' => $licence_id, 'target_season' => $next_season ), self::get_club_portal_url( 'licences-renouvellement' ) );
            ?>
                <dl class="ufsc-officer-summary"><div><dt><?php esc_html_e( 'Identité', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( trim( (string) ( $licence->prenom ?? '' ) . ' ' . (string) ( $licence->nom ?? '' ) ) ); ?></dd></div><div><dt><?php esc_html_e( 'Saison', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $season ); ?></dd></div><div><dt><?php esc_html_e( 'Statut', 'ufsc-clubs' ); ?></dt><dd><?php echo self::get_status_badge_front( $status_raw ); ?></dd></div><div><dt><?php esc_html_e( 'N° de licence', 'ufsc-clubs' ); ?></dt><dd><?php echo esc_html( $licence->numero_licence_ufsc ?? ( $licence->numero_licence ?? '—' ) ); ?></dd></div></dl>
                <div class="ufsc-board-role-card__actions"><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'Consulter la licence', 'ufsc-clubs' ); ?></a><a class="ufsc-btn ufsc-btn-primary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifier / compléter', 'ufsc-clubs' ); ?></a><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( $renew_url ); ?>"><?php esc_html_e( 'Renouveler', 'ufsc-clubs' ); ?></a></div>
            <?php else : ?>
                <p><?php echo esc_html( sprintf( __( 'Aucune licence %1$s liée à cette fonction pour %2$s.', 'ufsc-clubs' ), $labels[ $role ], $season ) ); ?></p>
                <a class="ufsc-btn ufsc-btn-primary" href="<?php echo esc_url( $create_url ); ?>"><?php esc_html_e( 'Créer la licence du dirigeant', 'ufsc-clubs' ); ?></a>
            <?php endif; ?>
        </article>
        <?php
    }

    /**
     * Render bureau role selector for one licence with current assignment pre-selected.
     *
     * @param int   $licence_id Licence ID.
     * @param array $assignments Current assignments by role.
     * @return string
     */
    private static function render_bureau_role_selector( $licence_id, $assignments ) {
        $current_role = '';
        foreach ( array( 'president', 'secretaire', 'tresorier', 'adherent' ) as $role ) {
            $assigned_ids = isset( $assignments[ $role ] ) ? array_map( 'intval', (array) $assignments[ $role ] ) : array();
            if ( in_array( (int) $licence_id, $assigned_ids, true ) ) {
                $current_role = $role;
                break;
            }
        }

        $labels = array(
            ''           => __( 'Aucun rôle', 'ufsc-clubs' ),
            'president'  => __( 'Président', 'ufsc-clubs' ),
            'secretaire' => __( 'Secrétaire', 'ufsc-clubs' ),
            'tresorier'  => __( 'Trésorier', 'ufsc-clubs' ),
            'adherent'   => __( 'Adhérent', 'ufsc-clubs' ),
        );

        ob_start();
        ?>
        <select id="ufsc-bureau-role-<?php echo esc_attr( (int) $licence_id ); ?>" name="bureau_role" class="ufsc-bureau-role-select">
            <?php foreach ( $labels as $role_key => $role_label ) : ?>
                <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $current_role, $role_key ); ?>>
                    <?php echo esc_html( $role_label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Get club statistics
     */
    private static function get_club_stats( $club_id, $season ) {
        $cache_key = "ufsc_stats_{$club_id}_{$season}";
        $stats     = get_transient( $cache_key );

        if ( class_exists( 'UFSC_Stats' ) ) {
            $stats = UFSC_Stats::get_club_stats( $club_id, $season );
        } else {
            $stats = array( 'total_licences' => 0, 'paid_licences' => 0, 'validated_licences' => 0, 'quota_remaining' => 10 );
        }

        if ( function_exists( 'ufsc_quotas_enabled' ) && ! ufsc_quotas_enabled() ) {
            $stats['quota_remaining'] = 0;
        }

        return $stats;
    }

    /**
     * Get club data
     */
    private static function get_club_data( $club_id ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return (object) array(
                'id'        => (int) $club_id,
                'nom'       => 'Club',
                'email'     => '',
                'telephone' => '',
            );
        }

        $clubs_table = ufsc_get_clubs_table();

        $club = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$clubs_table}` WHERE id = %d LIMIT 1",
                (int) $club_id
            )
        );

        return $club ?: (object) array(
            'id'        => (int) $club_id,
            'nom'       => 'Club',
            'email'     => '',
            'telephone' => '',
        );
    }

    /**
     * Check if club is validated
     */
    private static function is_validated_club( $club_id ) {
        if ( function_exists( 'ufsc_is_club_validated' ) ) {
            return ufsc_is_club_validated( $club_id );
        }

        return false;
    }

    /**
     * Check if a licence has been validated.
     *
     * @param int $licence_id Licence ID
     * @return bool
     */
    private static function is_validated_licence( $licence_id ) {
        return ufsc_is_validated_licence( $licence_id );
    }

    /**
     * Get licence status label
     */
    private static function get_licence_status_label( $status ) {
        if ( function_exists( 'ufsc_get_licence_status_label_fr' ) ) {
            return ufsc_get_licence_status_label_fr( $status );
        }

        return $status;
    }

    /**
     * Map licence status to badge class
     */
    private static function get_licence_status_badge_class( $status ) {
        $normalized = function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $status ) : $status;
        $classes = array(
            'brouillon'  => '-draft',
            'non_payee'  => '-pending',
            'valide'     => '-ok',
            'en_attente' => '-pending',
            'refuse'     => '-rejected',
        );

        return $classes[ $normalized ] ?? '-draft';
    }

    /**
     * Render payment status badge
     */
    private static function render_payment_status_badge( $status ) {
        $badge_classes = array(
            'paid'              => 'badge-success',
            'pending'           => 'badge-warning',
            'awaiting_transfer' => 'badge-info',
            'failed'            => 'badge-danger',
            'refunded'          => 'badge-secondary',
        );

        $class = isset( $badge_classes[ $status ] ) ? $badge_classes[ $status ] : 'badge-secondary';
        return '<span class="ufsc-badge ' . esc_attr( $class ) . '">' . esc_html( $status ) . '</span>';
    }

    /**
     * Get club quota information
     */
    private static function get_club_quota_info( $club_id ) {
        global $wpdb;

        if ( function_exists( 'ufsc_quotas_enabled' ) && ! ufsc_quotas_enabled() ) {
            return array( 'total' => 0, 'used' => 0, 'remaining' => 0 );
        }

        if ( ! class_exists( 'UFSC_SQL' ) ) {
            return array( 'total' => 0, 'used' => 0, 'remaining' => 0 );
        }

        $settings        = UFSC_SQL::get_settings();
        $clubs_table     = $settings['table_clubs'];
        $licences_table  = $settings['table_licences'];
        $quota_col       = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'quota_licences' ) : 'quota_licences';

        $quota_total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT `{$quota_col}` FROM `{$clubs_table}` WHERE id = %d",
            $club_id
        ) );

        $licence_conditions = array( 'club_id = %d' );
        $licence_args       = array( $club_id );
        $licence_columns    = function_exists( 'ufsc_table_columns' )
            ? ufsc_table_columns( $licences_table )
            : $wpdb->get_col( "DESCRIBE `{$licences_table}`" );
        if ( in_array( 'deleted_at', $licence_columns, true ) ) {
            $licence_conditions[] = "(deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        }
        $used = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$licences_table}` WHERE " . implode( ' AND ', $licence_conditions ),
                $licence_args
            )
        );

        return array(
            'total'     => $quota_total,
            'used'      => $used,
            'remaining' => max( 0, $quota_total - $used )
        );
    }

    /**
     * Render club documents list for frontend display
     */
    private static function render_club_documents_list( $club_id ) {
        // Get default document types and allow filtering
        $doc_types = apply_filters( 'ufsc_club_documents_types', array(
            'statuts' => __( 'Statuts', 'ufsc-clubs' ),
            'assurance' => __( 'Attestation d\'assurance', 'ufsc-clubs' ),
            'rib' => __( 'RIB', 'ufsc-clubs' ),
            'attestation_ufsc' => __( 'Attestation UFSC', 'ufsc-clubs' )
        ) );

        echo '<div class="ufsc-documents-list">';

        $has_documents = false;
        foreach ( $doc_types as $slug => $label ) {
            $attachment_id = (int) get_option( 'ufsc_club_doc_' . $slug . '_' . $club_id );

            if ( $attachment_id ) {
                $attachment_url = wp_get_attachment_url( $attachment_id );
                if ( $attachment_url ) {
                    $has_documents = true;
                    echo '<div class="ufsc-document-item">';
                    echo '<span class="ufsc-document-label">' . esc_html( $label ) . ':</span>';
					$status = sanitize_key( (string) get_option( 'ufsc_club_doc_' . $slug . '_status_' . $club_id, 'pending' ) );
					$reason = (string) get_option( 'ufsc_club_doc_' . $slug . '_reason_' . $club_id, '' );
					echo '<span class="ufsc-document-status">' . esc_html( $status ) . '</span>';
					if ( in_array( $status, array( 'rejected', 'correction_required' ), true ) && '' !== $reason ) {
						echo '<p class="ufsc-document-reason"><strong>' . esc_html__( 'Motif :', 'ufsc-clubs' ) . '</strong> ' . esc_html( $reason ) . '</p>';
						echo '<a class="ufsc-btn ufsc-btn-small" href="' . esc_url( add_query_arg( 'upload_documents', '1' ) ) . '">' . esc_html__( 'Remplacer le document', 'ufsc-clubs' ) . '</a>';
					}
                    echo '<div class="ufsc-document-actions">';
                    echo '<a href="' . esc_url( $attachment_url ) . '" target="_blank" rel="noopener" class="ufsc-btn ufsc-btn-small">' . esc_html__( 'Voir', 'ufsc-clubs' ) . '</a> ';
                    echo '<a href="' . esc_url( $attachment_url ) . '" download class="ufsc-btn ufsc-btn-small">' . esc_html__( 'Télécharger', 'ufsc-clubs' ) . '</a>';
                    echo '<span class="ufsc-field-error" aria-live="polite"></span></div>';
                    echo '<span class="ufsc-field-error" aria-live="polite"></span></div>';
                }
            }
        }

        if ( ! $has_documents ) {
            echo '<p class="ufsc-no-documents">' . esc_html__( 'Aucun document disponible.', 'ufsc-clubs' ) . '</p>';
        }

        echo '<span class="ufsc-field-error" aria-live="polite"></span></div>';
    }

    /**
     * Handle club update with validation restrictions
     */
    private static function handle_club_update( $club_id, $data ) {

        global $wpdb;
        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return array( 'success' => false, 'message' => __( 'Erreur de configuration.', 'ufsc-clubs' ) );
        }

        $clubs_table = ufsc_get_clubs_table();
        $is_admin = current_user_can( 'manage_options' );

        // Verify club exists and user has permission
        $club = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$clubs_table}` WHERE id = %d", $club_id ) );
        if ( ! $club ) {
            return array( 'success' => false, 'message' => __( 'Club non trouvé.', 'ufsc-clubs' ) );
        }

        if ( ! $is_admin ) {
            $user_club_id = function_exists('ufsc_get_user_club_id') ? ufsc_get_user_club_id( get_current_user_id() ) : 0;
            if ( (int) $user_club_id !== (int) $club_id ) {
                return array( 'success' => false, 'message' => __( 'Permission refusée.', 'ufsc-clubs' ) );
            }
        }

        $update_data = array();

        if ( $is_admin ) {
            // Admin can update all fields present in the data
            $allowed_fields = array( 'nom', 'sigle', 'email', 'telephone', 'adresse', 'code_postal', 'ville', 'region' );
            foreach ( $allowed_fields as $field ) {
                if ( isset( $data[$field] ) ) {
                    $update_data[$field] = sanitize_text_field( $data[$field] );
                }
            }

            // Handle logo upload for admin (option storage)
            if ( ! empty( $_FILES['club_logo']['name'] ) ) {
                $upload_result = wp_handle_upload( $_FILES['club_logo'], array( 'test_form' => false ) );
                if ( ! isset( $upload_result['error'] ) ) {
                    $attachment_id = wp_insert_attachment( array(
                        'post_title'     => sanitize_file_name( $_FILES['club_logo']['name'] ),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                        'post_mime_type' => $upload_result['type']
                    ), $upload_result['file'] );

                    if ( $attachment_id ) {
                        update_option( 'ufsc_club_logo_' . $club_id, $attachment_id );
                    }
                }
            }

        } else {
            // Non-admin can only update email and telephone
            $allowed_fields = array( 'email', 'telephone' );
            foreach ( $allowed_fields as $field ) {
                if ( isset( $data[$field] ) ) {
                    $update_data[$field] = sanitize_text_field( $data[$field] );
                }
            }
        }

        if ( empty( $update_data ) ) {
            return array( 'success' => false, 'message' => __( 'Aucune donnée à mettre à jour.', 'ufsc-clubs' ) );
        }

        // Validate email if present
        if ( isset( $update_data['email'] ) && ! empty( $update_data['email'] ) && ! is_email( $update_data['email'] ) ) {
            return array( 'success' => false, 'message' => __( 'Adresse email invalide.', 'ufsc-clubs' ) );
        }

        // Keep only real columns if helper exists
        if ( function_exists( 'ufsc_table_columns' ) ) {
            $columns = (array) ufsc_table_columns( $clubs_table );
            if ( ! empty( $columns ) ) {
                $update_data = array_intersect_key( $update_data, array_flip( $columns ) );
            }
        }

        if ( empty( $update_data ) ) {
            return array( 'success' => false, 'message' => __( 'Aucune donnée à mettre à jour.', 'ufsc-clubs' ) );
        }

        $data_formats = array_fill( 0, count( $update_data ), '%s' );
        $result       = $wpdb->update( $clubs_table, $update_data, array( 'id' => (int) $club_id ), $data_formats, array( '%d' ) );

        if ( $result !== false ) {
            return array( 'success' => true, 'message' => __( 'Club mis à jour avec succès.', 'ufsc-clubs' ) );
        }

        return array( 'success' => false, 'message' => __( 'Erreur lors de la mise à jour.', 'ufsc-clubs' ) );
    }

    /**
     * Handle licence creation
     */
    private static function handle_licence_creation( $club_id, $data ) {

        if ( ! class_exists( 'UFSC_SQL' ) ) {
            return array( 'success' => false, 'message' => __( 'Base UFSC non disponible.', 'ufsc-clubs' ) );
        }

        $fields = array( 'nom', 'prenom', 'email', 'telephone', 'date_naissance', 'sexe', 'adresse', 'ville', 'code_postal' );
        $sanitized = array();
        foreach ( $fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $value = 'email' === $field ? sanitize_email( $data[ $field ] ) : sanitize_text_field( $data[ $field ] );
                $sanitized[ $field ] = $value;
            }
        }

        if ( empty( $sanitized['nom'] ) || empty( $sanitized['prenom'] ) || empty( $sanitized['email'] ) ) {
            return array( 'success' => false, 'message' => __( 'Champs obligatoires manquants.', 'ufsc-clubs' ) );
        }
        if ( ! is_email( $sanitized['email'] ) ) {
            return array( 'success' => false, 'message' => __( 'Adresse email invalide.', 'ufsc-clubs' ) );
        }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        $pk = $settings['pk_club'];

        $club_data = $wpdb->get_row( $wpdb->prepare(
            "SELECT statut FROM `{$clubs_table}` WHERE `{$pk}` = %d",
            $club_id
        ), ARRAY_A );

        if ( isset( $club_data['statut'] ) && 'en_attente' === $club_data['statut'] ) {
            return array(
                'success' => false,
                'message' => __( 'Le club est encore en attente de validation. Veuillez compléter le paiement pour activer le club.', 'ufsc-clubs' ),
                'redirect_url' => wc_get_checkout_url()
            );
        }

        if ( function_exists( 'ufsc_quotas_enabled' ) && ! ufsc_quotas_enabled() ) {
            $needs_payment = false;
        } else {
            $quota_info    = self::get_club_quota_info( $club_id );
            $needs_payment = $quota_info['remaining'] <= 0;
        }

        $settings       = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];

        $insert_data = array(
            'club_id'          => $club_id,
            'nom'              => $sanitized['nom'],
            'prenom'           => $sanitized['prenom'],
            'email'            => $sanitized['email'],
            'tel_mobile'       => $sanitized['telephone'] ?? '',
            'date_naissance'   => $sanitized['date_naissance'] ?? '',
            'sexe'             => isset( $sanitized['sexe'] ) ? strtoupper( $sanitized['sexe'] ) : '',
            'adresse'          => $sanitized['adresse'] ?? '',
            'ville'            => $sanitized['ville'] ?? '',
            'code_postal'      => $sanitized['code_postal'] ?? '',
            'statut'           => 'brouillon',
            'date_inscription' => current_time( 'mysql' )
        );

        $result = $wpdb->insert( $licences_table, $insert_data );

        if ( false === $result ) {
            return array( 'success' => false, 'message' => __( 'Échec de création de la licence.', 'ufsc-clubs' ) );
        }

        $licence_id = $wpdb->insert_id;
        if ( function_exists( 'ufsc_get_licence_season' ) && function_exists( 'ufsc_set_licence_season' ) ) {
            $stored_season = ufsc_get_licence_season( $licence_id );
            if ( ! is_string( $stored_season ) || '' === trim( $stored_season ) ) {
                ufsc_set_licence_season( $licence_id, ufsc_get_current_season() );
            }
        }
        $response   = array(
            'success' => true,
            'message' => __( 'Licence créée avec succès.', 'ufsc-clubs' )
        );

        if ( $needs_payment ) {
            $order_id = ufsc_create_additional_license_order( $club_id, array( $licence_id ), get_current_user_id() );
            if ( $order_id ) {
                $order                   = wc_get_order( $order_id );
                $response['payment_url'] = $order ? $order->get_checkout_payment_url() : '';
            }
            do_action( 'ufsc_quota_exceeded', $club_id, array( 'licence_id' => $licence_id ) );
        }

        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log( 'licence_created', array(
                'licence_id'    => $licence_id,
                'club_id'       => $club_id,
                'user_id'       => get_current_user_id(),
                'needs_payment' => $needs_payment
            ) );
        }

        return $response;
    }

    /**
     * Render pagination
     */
    private static function render_pagination( $current_page, $total_pages, $query_args = array() ) {
        if ( $total_pages <= 1 ) {
            return '';
        }
        $current_page = min( max( 1, absint( $current_page ) ), absint( $total_pages ) );
        $output = '<nav class="ufsc-pagination-wrapper" aria-label="' . esc_attr__( 'Pagination des licences', 'ufsc-clubs' ) . '">';

        foreach ( array( array( 1, __( 'Première', 'ufsc-clubs' ) ), array( max( 1, $current_page - 1 ), __( 'Précédente', 'ufsc-clubs' ) ) ) as $control ) {
            list( $target, $label ) = $control;
            $output .= 1 === $current_page ? '<span class="ufsc-page-link disabled" aria-disabled="true">' . esc_html( $label ) . '</span>' : '<a class="ufsc-page-link" href="' . esc_url( add_query_arg( array_merge( $query_args, array( 'ufsc_page' => $target ) ) ) ) . '">' . esc_html( $label ) . '</a>';
        }

        for ( $page = 1; $page <= $total_pages; $page++ ) {
            $args = array_merge( $query_args, array( 'ufsc_page' => $page ) );
            $url = add_query_arg( $args );
            $class = $page === $current_page ? 'current' : '';

            $output .= $page === $current_page
                ? sprintf( '<span class="ufsc-page-link current" aria-current="page">%d</span>', $page )
                : sprintf( '<a href="%s" class="ufsc-page-link">%d</a>', esc_url( $url ), $page );
        }

        foreach ( array( array( min( $total_pages, $current_page + 1 ), __( 'Suivante', 'ufsc-clubs' ) ), array( $total_pages, __( 'Dernière', 'ufsc-clubs' ) ) ) as $control ) {
            list( $target, $label ) = $control;
            $output .= $current_page === $total_pages ? '<span class="ufsc-page-link disabled" aria-disabled="true">' . esc_html( $label ) . '</span>' : '<a class="ufsc-page-link" href="' . esc_url( add_query_arg( array_merge( $query_args, array( 'ufsc_page' => $target ) ) ) ) . '">' . esc_html( $label ) . '</a>';
        }
        $output .= '</nav>';
        return $output;
    }

    /**
     * Render import modal
     */
    private static function render_import_modal( $club_id ) {
        ob_start();
        ?>
        <div id="ufsc-import-modal" class="ufsc-modal" style="display:none;">
            <div class="ufsc-modal-content">
                <span class="ufsc-modal-close" onclick="document.getElementById('ufsc-import-modal').style.display='none'">&times;</span>
                <h3><?php esc_html_e( 'Importer des licences CSV', 'ufsc-clubs' ); ?></h3>
                <form method="post" enctype="multipart/form-data" class="ufsc-import-form">
                    <div class="ufsc-notices" aria-live="polite"></div>
                    <?php wp_nonce_field( 'ufsc_import', 'ufsc_nonce' ); ?>
                    <input type="hidden" name="club_id" value="<?php echo esc_attr( $club_id ); ?>">
                    <input type="hidden" name="ufsc_import_preview" id="action" value="1">
                    <div class="ufsc-field">
                        <label for="csv_file"><?php esc_html_e( 'Fichier CSV', 'ufsc-clubs' ); ?></label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                        <p class="ufsc-help-text">
                            <?php esc_html_e( 'Format attendu: nom,prenom,email,telephone,date_naissance,sexe,adresse,ville,code_postal,suite_adresse,tel_fixe,region', 'ufsc-clubs' ); ?>
                        </p>
                    </div>

                    <div class="ufsc-form-actions">
                        <button type="submit" id="btn-import-csv" name="ufsc_import_preview" class="ufsc-btn ufsc-btn-primary" disabled>
                            <?php esc_html_e( 'Prévisualiser', 'ufsc-clubs' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a form field with proper permissions and validation
     */
    private static function render_field( $field_key, $club, $label, $type = 'text', $readonly = false, $editable = false ) {
        $value = isset( $club->{$field_key} ) ? $club->{$field_key} : '';
        $field_readonly = $readonly || ! $editable;

        echo '<div class="ufsc-field">';
        echo '<label for="' . esc_attr( $field_key ) . '">' . esc_html( $label ) . '</label>';

        if ( $type === 'textarea' ) {
            echo '<textarea id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '"';
            if ( $field_readonly ) {
                echo ' readonly';
            }
            echo '>' . esc_textarea( $value ) . '</textarea>';
        } else {
            echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '"';
            echo ' value="' . esc_attr( $value ) . '"';
            if ( 'email' === $type ) { echo ' autocomplete="email"'; }
            if ( 'tel' === $type ) { echo ' autocomplete="tel"'; }
            if ( false !== strpos( $field_key, 'prenom' ) ) { echo ' autocomplete="given-name"'; }
            if ( false !== strpos( $field_key, '_nom' ) ) { echo ' autocomplete="family-name"'; }
            if ( $field_readonly ) {
                echo ' readonly';
            }
            echo '>';
        }

        echo '<span class="ufsc-field-error" aria-live="polite"></span></div>';
    }
}

// STUB FUNCTIONS - To be implemented according to existing database schema

if ( ! function_exists( 'ufsc_is_validated_club' ) ) {
    function ufsc_is_validated_club( $club_id ) {
        global $wpdb;

        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $pk = ufsc_club_col( 'id' );
        $statut_col = ufsc_club_col( 'statut' );

        $statut = $wpdb->get_var( $wpdb->prepare(
            "SELECT `{$statut_col}` FROM `{$table}` WHERE `{$pk}` = %d LIMIT 1",
            $club_id
        ) );

        if ( ! $statut ) {
            return false;
        }

        // Consider various forms of active/validated status
        $valid_statuses = array( 'actif', 'active', 'valide', 'validé', 'validée', 'approved' );
        return in_array( strtolower( $statut ), $valid_statuses );
    }
}

if ( ! function_exists( 'ufsc_is_validated_licence' ) ) {
    /**
     * Check if a licence has been validated.
     *
     * @param int $licence_id Licence ID
     * @return bool True if licence is validated
     */
    function ufsc_is_validated_licence( $licence_id ) {
        global $wpdb;

        if ( ! class_exists( 'UFSC_SQL' ) ) {
            return false;
        }

        $settings      = UFSC_SQL::get_settings();
        $table         = $settings['table_licences'];
        $pk            = function_exists( 'ufsc_lic_col' ) ? ufsc_lic_col( 'id' ) : 'id';
        $status_column = function_exists( 'ufsc_lic_col' ) ? ufsc_lic_col( 'statut' ) : 'statut';

        $status = $wpdb->get_var( $wpdb->prepare(
            "SELECT `{$status_column}` FROM `{$table}` WHERE `{$pk}` = %d LIMIT 1",
            $licence_id
        ) );

        if ( ! $status ) {
            return false;
        }

        if ( function_exists( 'ufsc_get_licence_status_norm' ) ) {
            return 'valide' === ufsc_get_licence_status_norm( $status );
        }

        $valid_statuses = array( 'valide', 'validé', 'validée', 'validated', 'applied', 'approved' );
        return in_array( strtolower( $status ), $valid_statuses, true );
    }
}
