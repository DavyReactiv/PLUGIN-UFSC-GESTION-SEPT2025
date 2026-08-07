<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce settings module for UFSC Gestion
 * Handles WooCommerce product IDs, quota, and season settings (quota season only)
 */

/**
 * Get default WooCommerce settings
 *
 * @return array Default WooCommerce settings
 */
function ufsc_get_default_woocommerce_settings() {
    return array(
        'product_affiliation_id'  => 4823,
        'product_license_id'      => 0,
        'included_licenses'       => 10,
        // "season" ici = saison de quota Woo (historique). La "source de vérité" de saison globale est gérée ailleurs (inc/settings.php + inc/common/season.php).
        'season'                  => function_exists( 'ufsc_get_season_for_date' ) ? ufsc_get_season_for_date( current_time( 'timestamp' ) ) : '',
        'max_profile_photo_size'  => 2,
        'auto_consume_included'   => 1,
        // Fallback historique: ouverture renouvellement au 30/07 (jour/mois)
        'renewal_window_day'      => 30,
        'renewal_window_month'    => 7,
    );
}

/**
 * Get current WooCommerce settings
 *
 * @return array Current WooCommerce settings merged with defaults
 */
function ufsc_get_woocommerce_settings() {
    $defaults = ufsc_get_default_woocommerce_settings();
    $saved    = get_option( 'ufsc_woocommerce_settings', array() );

    return wp_parse_args( $saved, $defaults );
}

/**
 * Save WooCommerce settings
 *
 * @param array $settings WooCommerce settings to save
 * @return bool True on success
 */
function ufsc_save_woocommerce_settings( $settings ) {
    $sanitized = array();

    if ( isset( $settings['product_affiliation_id'] ) ) {
        $sanitized['product_affiliation_id'] = absint( $settings['product_affiliation_id'] );
    }

    if ( isset( $settings['product_license_id'] ) ) {
        $sanitized['product_license_id'] = absint( $settings['product_license_id'] );
    }

    if ( isset( $settings['included_licenses'] ) ) {
        $sanitized['included_licenses'] = absint( $settings['included_licenses'] );
    }

    if ( isset( $settings['season'] ) ) {
        $sanitized['season'] = sanitize_text_field( $settings['season'] );
    }

    if ( isset( $settings['max_profile_photo_size'] ) ) {
        $sanitized['max_profile_photo_size'] = absint( $settings['max_profile_photo_size'] );
    }

    if ( isset( $settings['auto_consume_included'] ) ) {
        $sanitized['auto_consume_included'] = ! empty( $settings['auto_consume_included'] ) ? 1 : 0;
    }

    if ( isset( $settings['renewal_window_day'] ) ) {
        $sanitized['renewal_window_day'] = max( 1, min( 31, absint( $settings['renewal_window_day'] ) ) );
    }

    if ( isset( $settings['renewal_window_month'] ) ) {
        $sanitized['renewal_window_month'] = max( 1, min( 12, absint( $settings['renewal_window_month'] ) ) );
    }

    return update_option( 'ufsc_woocommerce_settings', $sanitized );
}

/**
 * Get configured affiliation renewal product ID.
 *
 * @return int
 */
function ufsc_get_affiliation_product_id() {
    $settings = ufsc_get_woocommerce_settings();
    $configured_id = isset( $settings['product_affiliation_id'] ) ? absint( $settings['product_affiliation_id'] ) : 0;

    // Keep 4823 as the default, but never discard an explicit administrator setting.
    return $configured_id > 0 ? $configured_id : 4823;
}

/** Return the canonical affiliation WooCommerce product when available. */
function ufsc_get_affiliation_product() {
    $product_id = ufsc_get_affiliation_product_id();
    if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
        return false;
    }

    $product = wc_get_product( $product_id );
    return ( $product && is_callable( array( $product, 'exists' ) ) && $product->exists() ) ? $product : false;
}

/** Return the canonical affiliation product permalink, or an empty string. */
function ufsc_get_affiliation_product_url() {
    $product = ufsc_get_affiliation_product();
    if ( ! $product || ! is_callable( array( $product, 'get_permalink' ) ) ) {
        return '';
    }

    $url = $product->get_permalink();
    return is_string( $url ) ? $url : '';
}

/**
 * Get configured licence renewal product ID.
 *
 * @return int
 */
function ufsc_get_licence_product_id() {
    $settings = ufsc_get_woocommerce_settings();
    $configured_id = isset( $settings['product_license_id'] ) ? absint( $settings['product_license_id'] ) : 0;

    /**
     * Single, explicit extension point for installations that provision their
     * product outside this settings screen. A filter may validate/migrate a
     * known ID, but the resolver deliberately never guesses from the catalogue.
     */
    return function_exists( 'apply_filters' ) ? absint( apply_filters( 'ufsc_licence_product_id', $configured_id, $settings ) ) : $configured_id;
}

/** Return the canonical licence product diagnostic used by admin, UI and cart. */
function ufsc_get_licence_product_resolution() {
    $product_id = ufsc_get_licence_product_id();
    $diagnostic = ufsc_get_woocommerce_product_diagnostic( $product_id );
    $diagnostic['configured_id'] = $product_id;
    $diagnostic['valid'] = ufsc_is_woocommerce_product_available( $product_id );
    return $diagnostic;
}

/** Stable cache-busting version for a plugin asset. */
function ufsc_asset_version( $relative_path ) {
    $path = defined( 'UFSC_CL_DIR' ) ? UFSC_CL_DIR . ltrim( $relative_path, '/' ) : '';
    return $path && is_file( $path ) ? (string) filemtime( $path ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : '1' );
}


/**
 * Build a safe diagnostic for a configured WooCommerce product.
 *
 * @param int $product_id Product ID to inspect.
 * @return array<string,mixed>
 */
function ufsc_get_woocommerce_product_diagnostic( $product_id ) {
    $product_id = absint( $product_id );
    $diagnostic = array(
        'woocommerce_active'       => function_exists( 'ufsc_is_woocommerce_active' ) ? ufsc_is_woocommerce_active() : class_exists( 'WooCommerce' ),
        'wc_get_product_available' => function_exists( 'wc_get_product' ),
        'product_id'               => $product_id,
        'product_found'            => false,
		'product_name'             => '',
        'product_status'           => '',
        'product_purchasable'      => false,
		'product_visibility'       => '',
        'product_type'             => '',
		'product_price'            => '',
		'product_permalink'        => '',
		'unavailable_reason'       => '',
    );

    if ( ! $diagnostic['woocommerce_active'] || ! $diagnostic['wc_get_product_available'] || $product_id <= 0 ) {
		$diagnostic['unavailable_reason'] = ! $diagnostic['woocommerce_active'] ? 'woocommerce_inactive' : ( $product_id <= 0 ? 'missing_product_id' : 'product_api_unavailable' );
        return $diagnostic;
    }

    $product = wc_get_product( $product_id );
    if ( ! $product || ! $product->exists() ) {
		$diagnostic['unavailable_reason'] = 'product_not_found';
        return $diagnostic;
    }

    $diagnostic['product_found']       = true;
	$diagnostic['product_name']        = is_callable( array( $product, 'get_name' ) ) ? (string) $product->get_name() : '';
    $diagnostic['product_status']      = is_callable( array( $product, 'get_status' ) ) ? (string) $product->get_status() : '';
    $diagnostic['product_purchasable'] = is_callable( array( $product, 'is_purchasable' ) ) ? (bool) $product->is_purchasable() : false;
	$diagnostic['product_visibility']  = is_callable( array( $product, 'get_catalog_visibility' ) ) ? (string) $product->get_catalog_visibility() : '';
	$diagnostic['product_type']        = is_callable( array( $product, 'get_type' ) ) ? (string) $product->get_type() : '';
	$diagnostic['product_price']       = is_callable( array( $product, 'get_price' ) ) ? (string) $product->get_price() : '';
	$diagnostic['product_permalink']   = is_callable( array( $product, 'get_permalink' ) ) ? (string) $product->get_permalink() : '';
	if ( 'publish' !== $diagnostic['product_status'] ) {
		$diagnostic['unavailable_reason'] = 'product_not_published';
	} elseif ( '' === $diagnostic['product_permalink'] ) {
		$diagnostic['unavailable_reason'] = 'permalink_unavailable';
	} elseif ( '' === $diagnostic['product_price'] ) {
		$diagnostic['unavailable_reason'] = 'product_without_price';
	} elseif ( ! $diagnostic['product_purchasable'] ) {
		$diagnostic['unavailable_reason'] = 'product_not_purchasable';
	}

    return $diagnostic;
}

/**
 * Check whether a configured WooCommerce product can be offered for renewal.
 *
 * @param int $product_id Product ID to inspect.
 * @return bool
 */
function ufsc_is_woocommerce_product_available( $product_id ) {
    $diagnostic = ufsc_get_woocommerce_product_diagnostic( $product_id );

    return ! empty( $diagnostic['woocommerce_active'] )
        && ! empty( $diagnostic['wc_get_product_available'] )
        && ! empty( $diagnostic['product_id'] )
        && ! empty( $diagnostic['product_found'] )
        && 'publish' === $diagnostic['product_status']
		&& '' !== $diagnostic['product_price']
		&& '' !== $diagnostic['product_permalink']
        && ! empty( $diagnostic['product_purchasable'] );
}

/** Human-readable diagnostic reserved for administrators. */
function ufsc_get_woocommerce_product_diagnostic_message( $product_id ) {
	$d = ufsc_get_woocommerce_product_diagnostic( $product_id );
	return sprintf(
		__( 'ID configuré : %1$d · produit : %2$s · trouvé : %3$s · statut : %4$s · visibilité : %5$s · type : %6$s · prix : %7$s · achetable : %8$s · permalink : %9$s · cause : %10$s', 'ufsc-clubs' ),
		absint( $product_id ),
		$d['product_name'] ?: '—',
		! empty( $d['product_found'] ) ? __( 'oui', 'ufsc-clubs' ) : __( 'non', 'ufsc-clubs' ),
		$d['product_status'] ?: '—',
		$d['product_visibility'] ?: '—',
		$d['product_type'] ?: '—',
		'' !== $d['product_price'] ? $d['product_price'] : '—',
		! empty( $d['product_purchasable'] ) ? __( 'oui', 'ufsc-clubs' ) : __( 'non', 'ufsc-clubs' ),
		$d['product_permalink'] ?: '—',
		ufsc_get_affiliation_product_unavailable_message( $d['unavailable_reason'] ?: '' )
	);
}

/** Front-safe affiliation product unavailability message. */
function ufsc_get_affiliation_product_unavailable_message( $reason ) {
    $messages = array(
        'woocommerce_inactive'     => __( 'WooCommerce est inactif.', 'ufsc-clubs' ),
        'missing_product_id'       => __( 'ID produit absent.', 'ufsc-clubs' ),
        'product_not_found'        => __( 'Produit d’affiliation introuvable.', 'ufsc-clubs' ),
        'product_not_published'    => __( 'Produit d’affiliation non publié.', 'ufsc-clubs' ),
        'product_without_price'    => __( 'Prix du produit d’affiliation absent.', 'ufsc-clubs' ),
        'product_not_purchasable'  => __( 'Produit d’affiliation non achetable.', 'ufsc-clubs' ),
        'product_api_unavailable'  => __( 'API produit WooCommerce indisponible.', 'ufsc-clubs' ),
        'permalink_unavailable'    => __( 'Permalink du produit d’affiliation indisponible.', 'ufsc-clubs' ),
    );

    return isset( $messages[ $reason ] ) ? $messages[ $reason ] : __( 'Le renouvellement en ligne est temporairement indisponible. Veuillez contacter l’UFSC.', 'ufsc-clubs' );
}

/** Centralized annual affiliation dashboard state. */
function ufsc_get_affiliation_renewal_state( $club_id, $season ) {
    $affiliation = class_exists( 'UFSC_Season_Archive_Manager' ) ? UFSC_Season_Archive_Manager::get_affiliation( $club_id, $season ) : null;
    $status = $affiliation && isset( $affiliation->status ) ? sanitize_key( (string) $affiliation->status ) : '';
    $payment_status = $affiliation && isset( $affiliation->payment_status ) ? sanitize_key( (string) $affiliation->payment_status ) : '';

    if ( in_array( $status, array( 'active', 'validated' ), true ) ) {
        return array( 'status' => 'active', 'label' => __( 'Affilié', 'ufsc-clubs' ), 'action' => 'none', 'affiliation' => $affiliation );
    }
    if ( 'pending_payment' === $status || 'pending' === $payment_status ) {
        return array( 'status' => 'pending_payment', 'label' => __( 'Paiement en attente', 'ufsc-clubs' ), 'action' => 'finalize_payment', 'affiliation' => $affiliation );
    }
    if ( in_array( $status, array( 'pending', 'pending_validation', 'en_attente' ), true ) ) {
        return array( 'status' => 'pending_validation', 'label' => __( 'En attente de validation', 'ufsc-clubs' ), 'action' => 'wait', 'affiliation' => $affiliation );
    }
    if ( 'correction_required' === $status ) {
        return array( 'status' => 'correction_required', 'label' => __( 'À corriger', 'ufsc-clubs' ), 'action' => 'correct', 'affiliation' => $affiliation );
    }
    if ( in_array( $status, array( 'rejected', 'suspended' ), true ) ) {
        return array( 'status' => $status, 'label' => 'rejected' === $status ? __( 'Refusée', 'ufsc-clubs' ) : __( 'Suspendue', 'ufsc-clubs' ), 'action' => 'contact', 'affiliation' => $affiliation );
    }

    return array( 'status' => 'renewal_required', 'label' => __( 'À renouveler', 'ufsc-clubs' ), 'action' => 'renew', 'affiliation' => $affiliation );
}

/** Build the configured affiliation product-page URL with renewal context. */
function ufsc_get_affiliation_renewal_url( $club_id, $season, $previous_affiliation_id = 0 ) {
    // Keep the public signature for compatibility, but the configured current
    // season is the sole renewal target. Never shift a caller-provided season.
    $season = class_exists( 'UFSC_Season_Service' )
        ? UFSC_Season_Service::get_current_season()
        : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
    $season = sanitize_text_field( (string) $season );
    if ( ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
        return '';
    }

    $product_id = ufsc_get_affiliation_product_id();
    if ( ! ufsc_is_woocommerce_product_available( $product_id ) ) {
        return '';
    }

    $url = ufsc_get_affiliation_product_url();
    if ( ! is_string( $url ) || '' === $url ) {
        return '';
    }

    return add_query_arg(
        array(
			'ufsc_action'                  => 'renewal',
            'ufsc_club_id'                 => absint( $club_id ),
            'ufsc_target_season'           => sanitize_text_field( (string) $season ),
			'ufsc_previous_affiliation_id' => absint( $previous_affiliation_id ),
			'ufsc_product_id'              => absint( $product_id ),
        ),
        $url
    );
}

/**
 * Check if WooCommerce is active
 *
 * @return bool True if WooCommerce is active
 */
function ufsc_is_woocommerce_active() {
    return class_exists( 'WooCommerce' ) || function_exists( 'wc_get_product' );
}

/**
 * Validate WooCommerce product ID
 *
 * @param int $product_id Product ID to validate
 * @return bool True if product exists
 */
function ufsc_validate_woocommerce_product( $product_id ) {
    if ( ! ufsc_is_woocommerce_active() ) {
        return false;
    }

    $product = wc_get_product( $product_id );
    return $product && $product->exists();
}

/**
 * Render WooCommerce settings page
 */
function ufsc_render_woocommerce_settings_page() {
    if ( ! ufsc_user_can( UFSC_Permissions::CAP_SETTINGS_MANAGE ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    // Handle form submission
    if ( isset( $_POST['ufsc_save_woocommerce_settings'] ) && check_admin_referer( 'ufsc_woocommerce_settings' ) ) {
        $settings = array();

        if ( isset( $_POST['product_affiliation_id'] ) ) {
            $settings['product_affiliation_id'] = absint( $_POST['product_affiliation_id'] );
        }

        if ( isset( $_POST['product_license_id'] ) ) {
            $settings['product_license_id'] = absint( $_POST['product_license_id'] );
        }

        if ( isset( $_POST['included_licenses'] ) ) {
            $settings['included_licenses'] = absint( $_POST['included_licenses'] );
        }

        if ( isset( $_POST['season'] ) ) {
            $settings['season'] = sanitize_text_field( wp_unslash( $_POST['season'] ) );
        }

        if ( isset( $_POST['renewal_window_day'] ) ) {
            $settings['renewal_window_day'] = absint( $_POST['renewal_window_day'] );
        }

        if ( isset( $_POST['renewal_window_month'] ) ) {
            $settings['renewal_window_month'] = absint( $_POST['renewal_window_month'] );
        }

        if ( ufsc_save_woocommerce_settings( $settings ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Paramètres WooCommerce enregistrés avec succès.', 'ufsc-clubs' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Erreur lors de l\'enregistrement des paramètres WooCommerce.', 'ufsc-clubs' ) . '</p></div>';
        }
    }

    $current_settings   = ufsc_get_woocommerce_settings();
    $woocommerce_active = ufsc_is_woocommerce_active();
    $licence_resolution = ufsc_get_licence_product_resolution();
    ?>
    <div class="wrap">
        <?php if ( class_exists( 'UFSC_SQL_Admin' ) ) { UFSC_SQL_Admin::render_admin_quick_nav(); } ?>
        <h1><?php esc_html_e( 'Paramètres WooCommerce - UFSC Gestion', 'ufsc-clubs' ); ?></h1>

        <?php if ( ! $woocommerce_active ) : ?>
            <div class="notice notice-warning">
                <p><?php esc_html_e( 'WooCommerce n\'est pas activé. Certaines fonctionnalités ne seront pas disponibles.', 'ufsc-clubs' ); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'ufsc_woocommerce_settings' ); ?>

            <h2><?php esc_html_e( 'Produits WooCommerce', 'ufsc-clubs' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="product_affiliation_id"><?php esc_html_e( 'ID du produit pack affiliation', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="product_affiliation_id"
                            name="product_affiliation_id"
                            value="<?php echo esc_attr( $current_settings['product_affiliation_id'] ); ?>"
                            class="regular-text"
                            min="1"
                        />
                        <p class="description">
                            <?php esc_html_e( 'Produit canonique : Pack Affiliation UFSC / FSASPTT (ID 4823).', 'ufsc-clubs' ); ?>
                            <?php if ( $woocommerce_active && ufsc_validate_woocommerce_product( $current_settings['product_affiliation_id'] ) ) : ?>
                                <span style="color: green;">✓ <?php esc_html_e( 'Produit trouvé', 'ufsc-clubs' ); ?></span>
                            <?php elseif ( $woocommerce_active ) : ?>
                                <span style="color: red;">✗ <?php esc_html_e( 'Produit non trouvé', 'ufsc-clubs' ); ?></span>
                            <?php endif; ?>
                        </p>
						<p><strong><?php echo esc_html( ufsc_get_woocommerce_product_diagnostic_message( $current_settings['product_affiliation_id'] ) ); ?></strong></p>
						<p><a class="button" href="#product_affiliation_id"><?php esc_html_e( 'Vérifier le produit d’affiliation', 'ufsc-clubs' ); ?></a> <?php if ( ! empty( ufsc_get_woocommerce_product_diagnostic( $current_settings['product_affiliation_id'] )['product_permalink'] ) ) : ?><a class="button" href="<?php echo esc_url( ufsc_get_woocommerce_product_diagnostic( $current_settings['product_affiliation_id'] )['product_permalink'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ouvrir le produit WooCommerce', 'ufsc-clubs' ); ?></a><?php endif; ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="product_license_id"><?php esc_html_e( 'ID du produit licence additionnelle', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <select
                            id="product_license_id"
                            name="product_license_id"
                            class="regular-text"
                        >
                            <option value="0"><?php esc_html_e( '— Aucun produit configuré —', 'ufsc-clubs' ); ?></option>
                            <?php if ( function_exists( 'wc_get_products' ) ) : foreach ( wc_get_products( array( 'status' => array( 'publish', 'draft', 'private' ), 'limit' => -1, 'orderby' => 'name', 'order' => 'ASC' ) ) as $candidate ) : ?>
                                <option value="<?php echo esc_attr( $candidate->get_id() ); ?>" <?php selected( $current_settings['product_license_id'], $candidate->get_id() ); ?>><?php echo esc_html( $candidate->get_name() . ' (#' . $candidate->get_id() . ', ' . $candidate->get_status() . ')' ); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'ID du produit "Licence UFSC/ASPTT" dans WooCommerce (par défaut: 2934)', 'ufsc-clubs' ); ?>
                            <?php if ( ! empty( $licence_resolution['valid'] ) ) : ?>
                                <span style="color: green;">✓ <?php esc_html_e( 'Produit publié et achetable', 'ufsc-clubs' ); ?></span>
                            <?php elseif ( $woocommerce_active ) : ?>
                                <span style="color: #b32d2e;">✗ <?php esc_html_e( 'Le produit Licence UFSC est absent, non publié ou non achetable.', 'ufsc-clubs' ); ?></span>
                            <?php endif; ?>
                        </p>
                        <p><strong><?php echo esc_html( ufsc_get_woocommerce_product_diagnostic_message( $current_settings['product_license_id'] ) ); ?></strong></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Configuration des quotas', 'ufsc-clubs' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="included_licenses"><?php esc_html_e( 'Licences incluses par pack', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="included_licenses"
                            name="included_licenses"
                            value="<?php echo esc_attr( $current_settings['included_licenses'] ); ?>"
                            class="regular-text"
                            min="0"
                        />
                        <p class="description">
                            <?php esc_html_e( 'Nombre de licences incluses dans chaque pack d\'affiliation (par défaut: 10)', 'ufsc-clubs' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="season"><?php esc_html_e( 'Saison courante (quota)', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="season"
                            name="season"
                            value="<?php echo esc_attr( $current_settings['season'] ); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            <?php esc_html_e( 'Saison utilisée pour la gestion des quotas côté WooCommerce (héritage). La saison globale UFSC se gère dans UFSC Gestion → Paramètres.', 'ufsc-clubs' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="renewal_window_day"><?php esc_html_e( 'Ouverture renouvellement (fallback)', 'ufsc-clubs' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="renewal_window_day"
                            name="renewal_window_day"
                            value="<?php echo esc_attr( $current_settings['renewal_window_day'] ); ?>"
                            min="1"
                            max="31"
                            class="small-text"
                        />
                        /
                        <input
                            type="number"
                            id="renewal_window_month"
                            name="renewal_window_month"
                            value="<?php echo esc_attr( $current_settings['renewal_window_month'] ); ?>"
                            min="1"
                            max="12"
                            class="small-text"
                        />
                        <p class="description">
                            <?php esc_html_e( 'Date (jour/mois) utilisée uniquement en fallback si aucune date d’ouverture n’est définie dans UFSC Gestion → Paramètres (par défaut 30/07).', 'ufsc-clubs' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Enregistrer les paramètres WooCommerce', 'ufsc-clubs' ), 'primary', 'ufsc_save_woocommerce_settings' ); ?>
        </form>

        <h2><?php esc_html_e( 'Informations sur l\'intégration WooCommerce', 'ufsc-clubs' ); ?></h2>
        <div class="notice notice-info">
            <p><strong><?php esc_html_e( 'Fonctionnement du système de quotas:', 'ufsc-clubs' ); ?></strong></p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><?php esc_html_e( 'Chaque pack d\'affiliation payé crédite le quota de licences incluses pour le club', 'ufsc-clubs' ); ?></li>
                <li><?php esc_html_e( 'Au-delà du quota inclus, les licences supplémentaires sont payantes', 'ufsc-clubs' ); ?></li>
                <li><?php esc_html_e( 'Le système génère automatiquement des commandes pour les licences additionnelles', 'ufsc-clubs' ); ?></li>
            </ul>
        </div>
    </div>
    <?php
}
