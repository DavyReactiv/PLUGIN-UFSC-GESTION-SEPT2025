<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce cart integration for UFSC Gestion
 * Handles secure add-to-cart and meta transfer to orders
 *
 * NOTE: This file was normalized to remove merge-conflict markers and to be fail-closed.
 * - Keeps existing public metas/keys as-is (ufsc_club_id, ufsc_license_ids, ufsc_licence_ids, etc.)
 * - Fixes ufsc_validate_licence_ids_for_cart(): fail-closed (was unsafe in some branches).
 * - Adds defensive guards (Woo not loaded, WC()->cart null, etc.)
 * - Adds filter ufsc_cart_max_licence_ids (default 50, bounded 1..500)
 */

/**
 * Initialize cart integration hooks
 */
function ufsc_init_cart_integration() {
	if ( ! function_exists( 'ufsc_is_woocommerce_active' ) || ! ufsc_is_woocommerce_active() ) {
		return;
	}

	// Handle secure add to cart requests
	add_action( 'admin_post_ufsc_add_to_cart', 'ufsc_handle_add_to_cart_secure' );
	add_action( 'admin_post_nopriv_ufsc_add_to_cart', 'ufsc_handle_add_to_cart_secure' );

	// Transfer meta data from cart to order
	add_action( 'woocommerce_checkout_create_order_line_item', 'ufsc_transfer_cart_meta_to_order', 10, 4 );
}

/**
 * Handle secure add to cart requests posted via admin-post.php
 */
function ufsc_handle_add_to_cart_secure() {
	$can_manage_all = false;
	if ( class_exists( 'UFSC_Capabilities' ) && method_exists( 'UFSC_Capabilities', 'user_can' ) ) {
		$can_manage_all = UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ );
	}
	if ( ! $can_manage_all ) {
		$can_manage_all = current_user_can( 'manage_options' );
	}

	// Woo required.
	if ( ! function_exists( 'wc_add_notice' ) || ! function_exists( 'wc_get_product' ) ) {
		if ( function_exists( 'ufsc_wc_log' ) ) {
			ufsc_wc_log( 'ufsc_add_to_cart_woo_missing', array(), 'warning' );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	// Verify nonce
	$nonce = isset( $_POST['_ufsc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ufsc_nonce'] ) ) : '';
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ufsc_add_to_cart_action' ) ) {
		wc_add_notice( __( 'Action non autorisée', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
		wc_add_notice( __( 'Vous devez être connecté pour effectuer cette action', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	if ( ! $product_id ) {
		wc_add_notice( __( 'Produit non trouvé', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	$product = wc_get_product( $product_id );
	if ( ! $product || ! $product->exists() ) {
		wc_add_notice( __( 'Produit non trouvé', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	$quantity       = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
	$cart_item_data = array();

	$current_user_id = get_current_user_id();
	$user_club_id    = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( $current_user_id ) ) : 0;

	$club_id = isset( $_POST['ufsc_club_id'] ) ? absint( $_POST['ufsc_club_id'] ) : 0;
	if ( $club_id <= 0 ) {
		$club_id = $user_club_id;
	}

	if ( $club_id <= 0 ) {
		wc_add_notice( __( 'Club invalide pour cet utilisateur.', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	if ( ! $can_manage_all && ( $user_club_id <= 0 || $club_id !== $user_club_id ) ) {
		wc_add_notice( __( 'Club invalide pour cet utilisateur.', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	$cart_item_data['ufsc_club_id'] = $club_id;

	// Add license IDs if provided (lot)
	if ( isset( $_POST['ufsc_license_ids'] ) ) {
		$license_ids_string = sanitize_text_field( wp_unslash( $_POST['ufsc_license_ids'] ) );
		$license_ids        = array_filter( array_map( 'absint', explode( ',', $license_ids_string ) ) );

		if ( ! empty( $license_ids ) ) {
			if ( ! ufsc_validate_licence_ids_for_cart( $license_ids, $club_id ) ) {
				wc_add_notice( __( 'Une ou plusieurs licences ne peuvent pas être ajoutées au panier.', 'ufsc-clubs' ), 'error' );
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}

			$cart_item_data['ufsc_license_ids'] = array_values( array_unique( $license_ids ) );
			$quantity                           = count( $cart_item_data['ufsc_license_ids'] ); // Override quantity to match license count
		}
	}

	// Ensure cart is ready
	if ( function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}
	if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
		wc_add_notice( __( 'Panier indisponible, veuillez réessayer.', 'ufsc-clubs' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	$cart_item_key = WC()->cart->add_to_cart( $product_id, max( 1, $quantity ), 0, array(), $cart_item_data );

	if ( $cart_item_key ) {
		wc_add_notice(
			sprintf( __( '%s ajouté au panier', 'ufsc-clubs' ), $product->get_name() ),
			'success'
		);
	} else {
		wc_add_notice( __( 'Erreur lors de l\'ajout au panier', 'ufsc-clubs' ), 'error' );
	}

	// Redirect back to the referring page
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : ( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url() ) );
	exit;
}

/**
 * Validate club ownership and lock/payment state before cart add.
 *
 * FAIL-CLOSED: any doubt => false.
 *
 * @param array $licence_ids Licence IDs.
 * @param int   $club_id     Current user club ID.
 * @return bool
 */
function ufsc_validate_licence_ids_for_cart( $licence_ids, $club_id ) {
	global $wpdb;
	$can_manage_all = false;
	if ( class_exists( 'UFSC_Capabilities' ) && method_exists( 'UFSC_Capabilities', 'user_can' ) ) {
		$can_manage_all = UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ );
	}
	if ( ! $can_manage_all ) {
		$can_manage_all = current_user_can( 'manage_options' );
	}

	if ( ! is_array( $licence_ids ) ) {
		return false;
	}

	$licence_ids = array_values( array_unique( array_filter( array_map( 'absint', $licence_ids ) ) ) );
	if ( empty( $licence_ids ) ) {
		return false;
	}

	$max_ids = (int) apply_filters( 'ufsc_cart_max_licence_ids', 50 );
	$max_ids = max( 1, min( $max_ids, 500 ) );

	if ( count( $licence_ids ) > $max_ids ) {
		return false;
	}

	if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
		return false;
	}

	// Non-admins must have a club.
	if ( ! $can_manage_all && $club_id <= 0 ) {
		return false;
	}

	$table = ufsc_get_licences_table();
	$clubs = array();

	foreach ( $licence_ids as $licence_id ) {
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", absint( $licence_id ) )
		);

		if ( ! $row ) {
			return false;
		}

		$row_club_id = absint( $row->club_id ?? 0 );
		if ( $row_club_id <= 0 ) {
			return false;
		}

		$clubs[ $row_club_id ] = true;

		// Ownership check (non-admin).
		if ( ! $can_manage_all && $row_club_id !== absint( $club_id ) ) {
			return false;
		}

		// Lock check: paid/en_attente/valide/etc.
		if ( function_exists( 'ufsc_is_licence_locked_for_club' ) && ufsc_is_licence_locked_for_club( $row ) ) {
			return false;
		}
	}

	// All licences must belong to one club (prevents mixing).
	if ( count( $clubs ) !== 1 ) {
		return false;
	}

	$clubs_ids = array_map( 'absint', array_keys( $clubs ) );
	if ( empty( $clubs_ids ) ) {
		return false;
	}

	if ( $club_id > 0 && (int) $clubs_ids[0] !== (int) absint( $club_id ) ) {
		return false;
	}

	return true;
}

/**
 * Transfer custom meta from cart to order line items
 *
 * @param WC_Order_Item_Product $item          Order line item
 * @param string               $cart_item_key  Cart item key
 * @param array                $values         Cart item values
 * @param WC_Order             $order          Order object
 */
function ufsc_transfer_cart_meta_to_order( $item, $cart_item_key, $values, $order ) {
	// Transfer club ID (store both underscored + public for compatibility)
	if ( isset( $values['ufsc_club_id'] ) ) {
		$item->add_meta_data( '_ufsc_club_id', absint( $values['ufsc_club_id'] ) );
		$item->add_meta_data( 'ufsc_club_id', absint( $values['ufsc_club_id'] ) );
	}

	// Transfer single licence ID (legacy compatibility)
	if ( isset( $values['ufsc_licence_id'] ) ) {
		$item->add_meta_data( '_ufsc_licence_id', absint( $values['ufsc_licence_id'] ) );
		$item->add_meta_data( 'ufsc_licence_id', absint( $values['ufsc_licence_id'] ) );
	}

	/**
	 * Transfer licence IDs (lot)
	 * NOTE: cart uses 'ufsc_license_ids' (EN spelling).
	 * Order metas stored as both _ufsc_licence_ids and ufsc_licence_ids for compatibility.
	 */
	if ( isset( $values['ufsc_license_ids'] ) && is_array( $values['ufsc_license_ids'] ) ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $values['ufsc_license_ids'] ) ) ) );
		if ( ! empty( $ids ) ) {
			$item->add_meta_data( '_ufsc_licence_id', (int) $ids[0], true );
			$item->add_meta_data( 'ufsc_licence_id', (int) $ids[0], true );
			$item->add_meta_data( '_ufsc_licence_ids', $ids );
			$item->add_meta_data( 'ufsc_licence_ids', $ids );
		}
	}

	// Transfer personal data
	if ( isset( $values['ufsc_nom'] ) ) {
		$item->add_meta_data( '_ufsc_nom', sanitize_text_field( $values['ufsc_nom'] ) );
	}
	if ( isset( $values['ufsc_prenom'] ) ) {
		$item->add_meta_data( '_ufsc_prenom', sanitize_text_field( $values['ufsc_prenom'] ) );
	}
	if ( isset( $values['ufsc_date_naissance'] ) ) {
		$item->add_meta_data( '_ufsc_date_naissance', sanitize_text_field( $values['ufsc_date_naissance'] ) );
	}
}

/**
 * Display custom cart item data
 *
 * @param array $item_data Item data
 * @param array $cart_item Cart item
 * @return array Modified item data
 */
function ufsc_display_cart_item_data( $item_data, $cart_item ) {
	// Display club info
	if ( isset( $cart_item['ufsc_club_id'] ) ) {
		$club_id   = absint( $cart_item['ufsc_club_id'] );
		$club_name = ufsc_get_club_name( $club_id );

		if ( $club_name ) {
			$item_data[] = array(
				'key'   => __( 'Club', 'ufsc-clubs' ),
				'value' => $club_name,
			);
		}
	}

	// Display license IDs (lot)
	if ( isset( $cart_item['ufsc_license_ids'] ) && is_array( $cart_item['ufsc_license_ids'] ) ) {
		$license_count = count( $cart_item['ufsc_license_ids'] );
		$item_data[]   = array(
			'key'   => __( 'Licences', 'ufsc-clubs' ),
			'value' => sprintf( __( '%d licence(s) spécifique(s)', 'ufsc-clubs' ), $license_count ),
		);
	}

	// Display single licence ID
	if ( isset( $cart_item['ufsc_licence_id'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Licence', 'ufsc-clubs' ),
			'value' => '#' . intval( $cart_item['ufsc_licence_id'] ),
		);
	}

	// Display personal data
	if ( isset( $cart_item['ufsc_nom'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Nom', 'ufsc-clubs' ),
			'value' => sanitize_text_field( $cart_item['ufsc_nom'] ),
		);
	}
	if ( isset( $cart_item['ufsc_prenom'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Prénom', 'ufsc-clubs' ),
			'value' => sanitize_text_field( $cart_item['ufsc_prenom'] ),
		);
	}
	if ( isset( $cart_item['ufsc_date_naissance'] ) && $cart_item['ufsc_date_naissance'] ) {
		$item_data[] = array(
			'key'   => __( 'Date de naissance', 'ufsc-clubs' ),
			'value' => sanitize_text_field( $cart_item['ufsc_date_naissance'] ),
		);
	}

	return $item_data;
}

/**
 * Get club name by ID.
 *
 * Looks up the `nom` column in the configured clubs table. Returns the name
 * or `false` when no matching club exists.
 *
 * @param int $club_id Club ID.
 * @return string|false Club name or false if not found.
 */
function ufsc_get_club_name( $club_id ) {
	global $wpdb;

	$club_id = absint( $club_id );
	if ( $club_id <= 0 ) {
		return false;
	}

	if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
		return false;
	}

	$clubs_table = ufsc_get_clubs_table();

	$club_name = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT nom FROM {$clubs_table} WHERE id = %d",
			$club_id
		)
	);

	return ! empty( $club_name ) ? $club_name : false;
}

/**
 * Add affiliation pack to cart for a club.
 * This function can be called from other parts of the plugin.
 *
 * @param int $club_id Club ID
 * @return bool True on success
 */
function ufsc_add_affiliation_to_cart( $club_id ) {
	if ( ! function_exists( 'ufsc_is_woocommerce_active' ) || ! ufsc_is_woocommerce_active() ) {
		return false;
	}

	if ( ! function_exists( 'WC' ) ) {
		return false;
	}

	if ( is_null( WC()->cart ) ) {
		function_exists( 'wc_load_cart' ) && wc_load_cart();
	}

	$wc_settings = function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings() : array();
	$product_id  = isset( $wc_settings['product_affiliation_id'] ) ? absint( $wc_settings['product_affiliation_id'] ) : 0;

	// Fallback: preserve previous behavior if settings missing.
	if ( $product_id <= 0 ) {
		return false;
	}

	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
	if ( ! $product || ! $product->exists() ) {
		return false;
	}

	$cart_item_data = array(
		'ufsc_club_id' => absint( $club_id ),
	);

	$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

	return $cart_item_key !== false;
}

/**
 * Apply included licence quota to cart items.
 *
 * Sets licence product price to zero when the club still has included
 * licences available. Flagged items receive the ufsc_consumes_included
 * marker so the quota can be updated on successful order.
 *
 * @param WC_Cart $cart Cart object.
 */
function ufsc_apply_included_quota_to_cart( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	if ( ! function_exists( 'ufsc_is_woocommerce_active' ) || ! ufsc_is_woocommerce_active() ) {
		return;
	}

	if ( function_exists( 'ufsc_quotas_enabled' ) && ! ufsc_quotas_enabled() ) {
		return;
	}

	$settings           = function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings() : array();
	$licence_product_id = isset( $settings['product_license_id'] ) ? (int) $settings['product_license_id'] : 0;
	if ( $licence_product_id <= 0 ) {
		return;
	}

	$club_remaining = array();

	foreach ( $cart->get_cart() as $key => $item ) {
		if ( (int) ( $item['product_id'] ?? 0 ) !== $licence_product_id ) {
			continue;
		}

		$club_id = isset( $item['ufsc_club_id'] )
			? absint( $item['ufsc_club_id'] )
			: ( function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0 );

		if ( ! $club_id ) {
			continue;
		}

		if ( ! isset( $club_remaining[ $club_id ] ) ) {
			$club_remaining[ $club_id ] = 0;

			if ( function_exists( 'ufsc_get_clubs_table' ) ) {
				global $wpdb;

				$clubs_table = ufsc_get_clubs_table();

				$quota = (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT included_quota FROM {$clubs_table} WHERE id = %d", $club_id )
				);

				$used = (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT included_quota_used FROM {$clubs_table} WHERE id = %d", $club_id )
				);

				$club_remaining[ $club_id ] = max( 0, $quota - $used );
			}
		}

		if ( $club_remaining[ $club_id ] > 0 && isset( $item['data'] ) && is_object( $item['data'] ) ) {
			$item['data']->set_price( 0 );
			$cart->cart_contents[ $key ]['ufsc_consumes_included'] = 1;
			$club_remaining[ $club_id ]--;
		}
	}
}

add_action( 'woocommerce_before_calculate_totals', 'ufsc_apply_included_quota_to_cart', 10, 1 );

// Initialize cart integration if WooCommerce is active
add_action( 'plugins_loaded', 'ufsc_init_cart_integration' );

// Hook to display cart item data
add_filter( 'woocommerce_get_item_data', 'ufsc_display_cart_item_data', 10, 2 );

if ( ! function_exists( 'ufsc_redirect_with_notice' ) ) {
	/**
	 * Redirect helper that appends notice query args.
	 *
	 * @param string $message      Message to show.
	 * @param string $type         Notice type: success|error.
	 * @param string $redirect_url Optional redirect URL.
	 */
	function ufsc_redirect_with_notice( $message, $type = 'success', $redirect_url = '' ) {
		$redirect_url = $redirect_url ?: ( wp_get_referer() ?: home_url() );
		$key          = ( 'error' === $type ) ? 'ufsc_error' : 'ufsc_message';
		$redirect_url = add_query_arg( $key, rawurlencode( $message ), $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit;
	}
}

/**
 * Redirect to checkout if club is "en_attente" and cart empty; add affiliation product if needed.
 *
 * @param int $existing_club_id Club ID.
 */
function ufsc_club_redirect_cart( $existing_club_id ) {
	global $wpdb;

	if ( ! class_exists( 'UFSC_SQL' ) || ! method_exists( 'UFSC_SQL', 'get_settings' ) ) {
		return;
	}

	$existing_club_id = absint( $existing_club_id );
	if ( $existing_club_id <= 0 ) {
		return;
	}

	$settings = UFSC_SQL::get_settings();
	$table    = $settings['table_clubs'] ?? '';
	$pk       = $settings['pk_club'] ?? 'id';

	if ( ! $table ) {
		return;
	}

	$club_data = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT statut FROM `{$table}` WHERE `{$pk}` = %d",
			$existing_club_id
		),
		ARRAY_A
	);

	if ( $club_data && strtolower( (string) ( $club_data['statut'] ?? '' ) ) === 'en_attente' ) {
		if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
			$cart = WC()->cart;
			if ( empty( $cart->cart_contents ) ) {
				ufsc_add_affiliation_to_cart( $existing_club_id );
			}
		}

		wp_safe_redirect( site_url( '/checkout' ) );
		exit;
	}
}

/**
 * Handle club affiliation form submission.
 *
 * Processes required documents and adds the affiliation product to cart.
 */
function ufsc_club_affiliation_submit() {
	check_admin_referer( 'ufsc_club_affiliation_submit' );

	if ( ! current_user_can( 'read' ) ) {
		ufsc_redirect_with_notice( __( 'Vous devez être connecté', 'ufsc-clubs' ), 'error' );
	}

	$club_id = isset( $_POST['club_id'] ) ? absint( $_POST['club_id'] ) : 0;
	if ( $club_id <= 0 ) {
		ufsc_redirect_with_notice( __( 'Club invalide.', 'ufsc-clubs' ), 'error' );
	}

	if ( ! class_exists( 'UFSC_Uploads' ) || ! method_exists( 'UFSC_Uploads', 'handle_required_docs' ) ) {
		ufsc_redirect_with_notice( __( 'Système de documents indisponible.', 'ufsc-clubs' ), 'error' );
	}

	$uploads = UFSC_Uploads::handle_required_docs( $_FILES );
	if ( is_wp_error( $uploads ) ) {
		ufsc_redirect_with_notice( $uploads->get_error_message(), 'error' );
	}

	$added = false;
	if ( function_exists( 'WC' ) ) {
		function_exists( 'wc_load_cart' ) && wc_load_cart();

		// Preserve existing behavior: hardcoded affiliation product id 4823.
		$added = WC()->cart->add_to_cart( 4823, 1, 0, array(), array( 'ufsc_club_id' => $club_id ) );
	}

	if ( ! $added ) {
		ufsc_redirect_with_notice( __( 'Impossible d\'ajouter le produit au panier.', 'ufsc-clubs' ), 'error' );
	}

	$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url();
	ufsc_redirect_with_notice( __( 'Produit d\'affiliation ajouté au panier.', 'ufsc-clubs' ), 'success', $cart_url );
}

add_action( 'admin_post_ufsc_club_affiliation_submit', 'ufsc_club_affiliation_submit' );
add_action( 'admin_post_nopriv_ufsc_club_affiliation_submit', 'ufsc_club_affiliation_submit' );
