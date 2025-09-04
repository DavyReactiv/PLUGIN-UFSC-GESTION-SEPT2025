<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce hooks for UFSC Gestion
 * Handles order processing and integrations
 */

/**
 * Helper to log WooCommerce events with audit trail fallback.
 *
 * @param string $action  Action performed.
 * @param array  $context Context information.
 * @param string $level   Log level (info|error).
 */
function ufsc_wc_log( $action, $context = array(), $level = 'info' ) {
    if ( class_exists( 'UFSC_Audit_Logger' ) ) {
        UFSC_Audit_Logger::log( $action, $context );
    } elseif ( function_exists( 'wc_get_logger' ) ) {
        $logger  = wc_get_logger();
        $context = array_merge( array( 'source' => 'ufsc-gestion' ), $context );
        if ( 'error' === $level ) {
            $logger->error( $action, $context );
        } else {
            $logger->info( $action, $context );
        }
    }
}

/**
 * Initialize WooCommerce hooks
 */
function ufsc_init_woocommerce_hooks() {
    if ( ! ufsc_is_woocommerce_active() ) {
        return;
    }

    add_action(
        'woocommerce_checkout_create_order_line_item',
        function ( $item, $cart_key, $values ) {
            foreach ( $values as $k => $v ) {
                if ( strpos( $k, 'ufsc_' ) === 0 ) {
                    $item->add_meta_data( $k, $v, true );
                }
            }
        },
        10,
        3
    );

    // Hook into order processing
    add_action( 'woocommerce_order_status_processing', 'ufsc_handle_order_processing' );
    add_action( 'woocommerce_order_status_completed', 'ufsc_handle_order_completed' );

    // Validate paid items when order is processed or completed
    add_action( 'woocommerce_order_status_processing', 'ufsc_wc_validate_paid_items' );
    add_action( 'woocommerce_order_status_completed', 'ufsc_wc_validate_paid_items' );

    // Track consumption of included licences
    add_action( 'woocommerce_order_status_processing', 'ufsc_wc_increment_included_quota' );
    add_action( 'woocommerce_order_status_completed', 'ufsc_wc_increment_included_quota' );
}

/**
 * Handle order when it reaches processing status
 * 
 * @param int $order_id Order ID
 */
function ufsc_handle_order_processing( $order_id ) {
    ufsc_process_order_items( $order_id );
}

/**
 * Handle order when it reaches completed status
 * 
 * @param int $order_id Order ID
 */
function ufsc_handle_order_completed( $order_id ) {
    ufsc_process_order_items( $order_id );
}

/**
 * Validate paid items for an order once payment is confirmed.
 *
 * @param int $order_id Order ID.
 */
function ufsc_wc_validate_paid_items( $order_id ) {
    if ( ! ufsc_is_woocommerce_active() ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $settings               = ufsc_get_woocommerce_settings();
    $season                 = $settings['season'];
    $affiliation_product_id = $settings['product_affiliation_id'];
    $license_product_id     = $settings['product_license_id'];

    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();

        if ( $product_id == $license_product_id ) {
            $license_ids = $item->get_meta( '_ufsc_licence_ids' );
            if ( empty( $license_ids ) ) {
                $license_ids = $item->get_meta( 'ufsc_licence_ids' );
            }
            if ( ! empty( $license_ids ) && is_array( $license_ids ) ) {
                foreach ( $license_ids as $license_id ) {
                    if ( class_exists( 'UFSC_SQL' ) ) {
                        UFSC_SQL::mark_licence_as_paid_and_validated( $license_id, $season );
                    }
                }
            }
        }

        if ( $product_id == $affiliation_product_id ) {
            $club_id = $item->get_meta( '_ufsc_club_id' );
            if ( empty( $club_id ) ) {
                $club_id = $item->get_meta( 'ufsc_club_id' );
            }
            if ( ! $club_id ) {
                $club_id = ufsc_get_user_club_id( $order->get_user_id() );
            }
            if ( $club_id && class_exists( 'UFSC_SQL' ) ) {
                UFSC_SQL::mark_club_affiliation_active( $club_id, $season );
            }
        }
    }
}

/**
 * Increment included quota usage for licences marked as consuming it.
 *
 * @param int $order_id Order identifier.
 */
function ufsc_wc_increment_included_quota( $order_id ) {
    if ( ! ufsc_is_woocommerce_active() ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    foreach ( $order->get_items() as $item ) {
        if ( ! $item->get_meta( 'ufsc_consumes_included' ) ) {
            continue;
        }

        $club_id = $item->get_meta( '_ufsc_club_id' );
        if ( ! $club_id ) {
            $club_id = ufsc_get_user_club_id( $order->get_user_id() );
        }

        if ( ! $club_id ) {
            continue;
        }

        $qty = max( 1, $item->get_quantity() );

        if ( function_exists( 'ufsc_get_clubs_table' ) ) {
            global $wpdb;
            $clubs_table = ufsc_get_clubs_table();
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$clubs_table} SET included_quota_used = COALESCE(included_quota_used,0) + %d WHERE id = %d",
                    $qty,
                    $club_id
                )
            );
        }
    }
}

/**
 * Process order items for UFSC products
 * 
 * @param int $order_id Order ID
 */
function ufsc_process_order_items( $order_id ) {
    if ( ! ufsc_is_woocommerce_active() ) {
        return;
    }
    
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    
    $wc_settings = ufsc_get_woocommerce_settings();
    $affiliation_product_id = $wc_settings['product_affiliation_id'];
    $license_product_id = $wc_settings['product_license_id'];
    
    foreach ( $order->get_items() as $item_id => $item ) {
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();
        
        if ( $product_id == $affiliation_product_id ) {
            // Handle affiliation pack
            ufsc_handle_affiliation_pack_payment( $order, $item, $quantity );
        } elseif ( $product_id == $license_product_id ) {
            // Handle additional license
            ufsc_handle_additional_license_payment( $order, $item, $quantity );
        }
    }
}

/**
 * Handle affiliation pack payment
 * 
 * @param WC_Order $order Order object
 * @param WC_Order_Item_Product $item Item object
 * @param int $quantity Quantity purchased
 */
function ufsc_handle_affiliation_pack_payment( $order, $item, $quantity ) {
    $user_id = $order->get_user_id();
    $season = ufsc_get_woocommerce_settings()['season'];
    
    // Get club ID for this user
    $club_id = ufsc_get_user_club_id( $user_id );
    
    if ( $club_id ) {
        // Mark affiliation as paid for the season
        ufsc_mark_affiliation_paid( $club_id, $season );
        
        // Credit included licenses quota
        $included_licenses = ufsc_get_woocommerce_settings()['included_licenses'];
        $total_licenses = $included_licenses * $quantity;
        ufsc_quota_add_included( $club_id, $total_licenses, $season );
        
        // Log the action
        ufsc_wc_log(
            'Affiliation pack processed',
            array(
                'order_id'          => $order->get_id(),
                'club_id'           => $club_id,
                'season'            => $season,
                'licenses_credited' => $total_licenses,
            )
        );
    }
}

/**
 * Handle additional license payment
 * 
 * @param WC_Order $order Order object
 * @param WC_Order_Item_Product $item Item object
 * @param int $quantity Quantity purchased
 */
function ufsc_handle_additional_license_payment( $order, $item, $quantity ) {
    $user_id = $order->get_user_id();
    $season = ufsc_get_woocommerce_settings()['season'];
    $club_id = ufsc_get_user_club_id( $user_id );

    // Check if specific license IDs are attached to this line item
    $license_ids = $item->get_meta( '_ufsc_licence_ids' );

    if ( empty( $license_ids ) ) {
        $single_id = $item->get_meta( '_ufsc_licence_id' );
        if ( $single_id ) {
            $license_ids = array( $single_id );
        }
    }

    if ( ! empty( $license_ids ) && is_array( $license_ids ) ) {
        // Mark specific licenses as paid
        foreach ( $license_ids as $license_id ) {
            ufsc_mark_licence_paid( $license_id, $season );
        }
        ufsc_wc_log(
            'Specific licenses marked as paid',
            array(
                'order_id'    => $order->get_id(),
                'club_id'     => $club_id,
                'license_ids' => implode( ', ', $license_ids ),
                'season'      => $season,
            )
        );
    } else {
        // Credit prepaid licenses for future use
        if ( $club_id ) {
            ufsc_quota_add_paid( $club_id, $quantity, $season );

            ufsc_wc_log(
                'Prepaid licenses credited',
                array(
                    'order_id' => $order->get_id(),
                    'club_id'  => $club_id,
                    'quantity' => $quantity,
                    'season'   => $season,
                )
            );
        }
    }
}

// Database helper functions

/**
 * Get club ID for a user.
 *
 * Tries to resolve the club via the optional UFSC_User_Club_Mapping class
 * if available. If not, the function falls back to a direct lookup on the
 * configured clubs table using the `responsable_id` column.
 *
 * @param int $user_id User ID
 * @return int|false Club ID or false if not found
 */
if ( ! function_exists( 'ufsc_get_user_club_id' ) ) {
    function ufsc_get_user_club_id( $user_id ) {
        // Delegate to the mapping class when present
        if ( class_exists( 'UFSC_User_Club_Mapping' ) ) {
            return UFSC_User_Club_Mapping::get_user_club_id( $user_id );
        }

        global $wpdb;
        $clubs_table = ufsc_get_clubs_table();

        $club_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$clubs_table} WHERE responsable_id = %d",
                $user_id
            )
        );

        return $club_id ? (int) $club_id : false;
    }
}

/**
 * Mark affiliation as paid for a season
 *
 * @param int $club_id Club ID
 * @param string $season Season identifier
 */
if ( ! function_exists( 'ufsc_mark_affiliation_paid' ) ) {
    function ufsc_mark_affiliation_paid( $club_id, $season ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return false;
        }

        $clubs_table = ufsc_get_clubs_table();

        // Update affiliation date to mark payment
        $updated = $wpdb->update(
            $clubs_table,
            array( 'date_affiliation' => current_time( 'mysql' ) ),
            array( 'id' => $club_id ),
            array( '%s' ),
            array( '%d' )
        );

        return false !== $updated;
    }
}

/**
 * Mark a specific license as paid
 *
 * @param int $license_id License ID
 * @param string $season Season identifier
 */
if ( ! function_exists( 'ufsc_mark_licence_paid' ) ) {
    function ufsc_mark_licence_paid( $license_id, $season ) {
        global $wpdb;

        if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
            return false;
        }

        $licences_table = ufsc_get_licences_table();
        $updated        = $wpdb->update(
            $licences_table,
            array(
                'statut'      => 'en_attente',
                'is_included' => 0,
                'paid_season' => $season,
                'paid_date'   => current_time( 'mysql' ),
            ),
            array( 'id' => $license_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        return false !== $updated;
    }
}

/**
 * Add included licenses to club quota
 *
 * @param int $club_id Club ID
 * @param int $quantity Number of licenses to add
 * @param string $season Season identifier
 */
function ufsc_quota_add_included( $club_id, $quantity, $season ) {
    global $wpdb;

    if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
        return false;
    }

    $clubs_table = ufsc_get_clubs_table();
    $updated     = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$clubs_table} SET quota_licences = COALESCE(quota_licences,0) + %d WHERE id = %d",
            $quantity,
            $club_id
        )
    );

    return false !== $updated;
}

/**
 * Add paid licenses to club quota
 *
 * @param int $club_id Club ID
 * @param int $quantity Number of licenses to add
 * @param string $season Season identifier
 */
function ufsc_quota_add_paid( $club_id, $quantity, $season ) {
    global $wpdb;

    if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
        return false;
    }

    $clubs_table = ufsc_get_clubs_table();
    $updated     = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$clubs_table} SET quota_licences = COALESCE(quota_licences,0) + %d WHERE id = %d",
            $quantity,
            $club_id
        )
    );

    return false !== $updated;
}
