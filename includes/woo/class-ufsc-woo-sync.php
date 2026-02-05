<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce synchronization for UFSC Gestion.
 *
 * Attaches licence/club identifiers to cart items and syncs order
 * transitions with UFSC SQL tables.
 */
class UFSC_Woo_Sync {
    /** @var int Licence product ID */
    protected static $license_product_id = 2934;

    /** @var int Affiliation product ID */
    protected static $affiliation_product_id = 4823;

    /**
     * Initialize hooks.
     */
    public static function init() {
        if ( function_exists( 'ufsc_get_woocommerce_settings' ) ) {
            $settings = ufsc_get_woocommerce_settings();
            if ( ! empty( $settings['product_license_id'] ) ) {
                self::$license_product_id = (int) $settings['product_license_id'];
            }
            if ( ! empty( $settings['product_affiliation_id'] ) ) {
                self::$affiliation_product_id = (int) $settings['product_affiliation_id'];
            }
        }

        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 3 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'handle_order_activation' ) );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_order_activation' ) );
        add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_order_refund' ) );
    }

    /**
     * Attach UFSC identifiers to cart items.
     *
     * @param array $cart_item_data Existing cart item data.
     * @param int   $product_id     Product ID being added.
     * @return array
     */
    public static function add_cart_item_data( $cart_item_data, $product_id ) {
        if ( $product_id == self::$license_product_id && isset( $_REQUEST['ufsc_licence_id'] ) ) {
            $cart_item_data['ufsc_licence_id'] = absint( $_REQUEST['ufsc_licence_id'] );
        }

        if ( $product_id == self::$affiliation_product_id && isset( $_REQUEST['ufsc_club_id'] ) ) {
            $cart_item_data['ufsc_club_id'] = absint( $_REQUEST['ufsc_club_id'] );
        }

        return $cart_item_data;
    }

    /**
     * Save UFSC meta to order items.
     *
     * @param WC_Order_Item_Product $item       Line item object.
     * @param string                $cart_key   Cart item key.
     * @param array                 $values     Cart item values.
     */
    public static function add_order_item_meta( $item, $cart_key, $values ) {
        if ( isset( $values['ufsc_licence_id'] ) ) {
            $item->add_meta_data( '_ufsc_licence_id', (int) $values['ufsc_licence_id'], true );
        }

        if ( isset( $values['ufsc_club_id'] ) ) {
            $item->add_meta_data( '_ufsc_club_id', (int) $values['ufsc_club_id'], true );
        }
    }

    /**
     * Handle order activation when reaching processing/completed.
     *
     * @param int $order_id Order ID.
     */
    public static function handle_order_activation( $order_id ) {
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
        if ( ! $order ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();

            if ( $product_id == self::$license_product_id ) {
                $licence_id = $item->get_meta( '_ufsc_licence_id', true );
                if ( $licence_id ) {
                    self::activate_licence( $licence_id );
                }
            }

            if ( $product_id == self::$affiliation_product_id ) {
                $club_id = $item->get_meta( '_ufsc_club_id', true );
                if ( ! $club_id && function_exists( 'ufsc_get_user_club_id' ) ) {
                    $club_id = ufsc_get_user_club_id( $order->get_user_id() );
                }
                if ( $club_id ) {
                    self::activate_club( $club_id );
                }
            }
        }
    }

    /**
     * Handle order refunds.
     *
     * @param int $order_id Order ID.
     */
    public static function handle_order_refund( $order_id ) {
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
        if ( ! $order ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();

            if ( $product_id == self::$license_product_id ) {
                $licence_id = $item->get_meta( '_ufsc_licence_id', true );
                if ( $licence_id ) {
                    self::rollback_licence( $licence_id );
                }
            }

            if ( $product_id == self::$affiliation_product_id ) {
                $club_id = $item->get_meta( '_ufsc_club_id', true );
                if ( $club_id ) {
                    self::rollback_club( $club_id );
                }
            }
        }
    }

    /**
     * Activate a licence and decrement club quota.
     *
     * @param int $licence_id Licence ID.
     */
    protected static function activate_licence( $licence_id ) {
        if ( ! function_exists( 'ufsc_get_licences_table' ) || ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return;
        }

        global $wpdb;
        $licences_table = ufsc_get_licences_table();

        $wpdb->update(
            $licences_table,
            array(
                'statut'    => 'valide',
                'paid_date' => current_time( 'mysql' ),
            ),
            array( 'id' => $licence_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        $club_id = $wpdb->get_var( $wpdb->prepare( "SELECT club_id FROM {$licences_table} WHERE id = %d", $licence_id ) );
        if ( $club_id ) {
            if ( ! function_exists( 'ufsc_quotas_enabled' ) || ufsc_quotas_enabled() ) {
                $clubs_table = ufsc_get_clubs_table();
                $wpdb->query( $wpdb->prepare( "UPDATE {$clubs_table} SET quota_licences = GREATEST(COALESCE(quota_licences,0)-1,0) WHERE id = %d", $club_id ) );
            }
        }
    }

    /**
     * Rollback a licence and restore club quota.
     *
     * @param int $licence_id Licence ID.
     */
    protected static function rollback_licence( $licence_id ) {
        if ( ! function_exists( 'ufsc_get_licences_table' ) || ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return;
        }

        global $wpdb;
        $licences_table = ufsc_get_licences_table();

        $wpdb->update(
            $licences_table,
            array( 'statut' => 'en_attente' ),
            array( 'id' => $licence_id ),
            array( '%s' ),
            array( '%d' )
        );

        $club_id = $wpdb->get_var( $wpdb->prepare( "SELECT club_id FROM {$licences_table} WHERE id = %d", $licence_id ) );
        if ( $club_id ) {
            if ( ! function_exists( 'ufsc_quotas_enabled' ) || ufsc_quotas_enabled() ) {
                $clubs_table = ufsc_get_clubs_table();
                $wpdb->query( $wpdb->prepare( "UPDATE {$clubs_table} SET quota_licences = COALESCE(quota_licences,0) + 1 WHERE id = %d", $club_id ) );
            }
        }
    }

    /**
     * Activate a club affiliation.
     *
     * @param int $club_id Club ID.
     */
    protected static function activate_club( $club_id ) {
        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return;
        }

        global $wpdb;
        $clubs_table = ufsc_get_clubs_table();

        $wpdb->update(
            $clubs_table,
            array(
                'statut'           => 'valide',
                'date_affiliation' => current_time( 'mysql' ),
            ),
            array( 'id' => $club_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Rollback a club affiliation.
     *
     * @param int $club_id Club ID.
     */
    protected static function rollback_club( $club_id ) {
        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return;
        }

        global $wpdb;
        $clubs_table = ufsc_get_clubs_table();

        $wpdb->update(
            $clubs_table,
            array( 'statut' => 'en_attente' ),
            array( 'id' => $club_id ),
            array( '%s' ),
            array( '%d' )
        );
    }
}
