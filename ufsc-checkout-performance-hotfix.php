<?php
/**
 * Plugin Name: UFSC Checkout Performance Hotfix
 * Description: Hotfix temporaire pour sécuriser le checkout UFSC et corriger l'affichage du mini-panier.
 * Version: 1.0.0
 * Author: UFSC / Studio REACTIV
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Empêche les migrations lourdes du plugin UFSC de s'exécuter sur les requêtes front.
 * Elles restent disponibles en admin, en cron et via WP-CLI.
 */
add_action( 'plugins_loaded', function() {
    if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return;
    }

    if ( class_exists( 'UFSC_DB_Migrations' ) ) {
        remove_action( 'plugins_loaded', array( 'UFSC_DB_Migrations', 'run_migrations' ) );
    }
}, 0 );

/**
 * Évite la double validation UFSC pendant le traitement final du checkout.
 * La validation panier woocommerce_check_cart_items reste active.
 */
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ufsc_validate_licence_affiliation_checkout' ) ) {
        remove_action( 'woocommerce_checkout_process', 'ufsc_validate_licence_affiliation_checkout' );
    }
}, 20 );

/**
 * Ajustements visuels du mini-panier WooCommerce/Astra.
 */
add_action( 'wp_head', function() {
    if ( is_admin() ) {
        return;
    }
    ?>
    <style id="ufsc-mini-cart-hotfix">
        .ast-site-header-cart .widget_shopping_cart,
        .ast-site-header-cart .ast-site-header-cart-data,
        .woocommerce-mini-cart__empty-message {
            box-sizing: border-box;
        }

        .ast-site-header-cart .widget_shopping_cart {
            width: min(380px, calc(100vw - 24px)) !important;
            min-width: 320px;
            padding: 0 !important;
            overflow: hidden;
        }

        .ast-site-header-cart .woocommerce-mini-cart {
            margin: 0 !important;
            padding: 12px 16px 4px !important;
            max-height: 420px;
            overflow-y: auto;
        }

        .ast-site-header-cart .woocommerce-mini-cart-item {
            display: grid !important;
            grid-template-columns: 64px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            min-height: 82px;
            padding: 10px 0 !important;
        }

        .ast-site-header-cart .woocommerce-mini-cart-item a:not(.remove) {
            min-width: 0;
            white-space: normal;
            line-height: 1.35;
        }

        .ast-site-header-cart .woocommerce-mini-cart-item img {
            width: 56px !important;
            height: 56px !important;
            object-fit: contain;
            margin: 0 !important;
        }

        .ast-site-header-cart .woocommerce-mini-cart-item .remove,
        .ast-site-header-cart .woocommerce-mini-cart-item a.remove {
            position: static !important;
            width: 28px;
            height: 28px;
            line-height: 26px;
            text-align: center;
        }

        .ast-site-header-cart .woocommerce-mini-cart__total {
            margin: 0 !important;
            padding: 14px 16px !important;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .ast-site-header-cart .woocommerce-mini-cart__buttons {
            margin: 0 !important;
            padding: 0 16px 16px !important;
            display: grid !important;
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .ast-site-header-cart .woocommerce-mini-cart__buttons .button {
            width: 100% !important;
            margin: 0 !important;
            min-height: 44px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .ast-site-header-cart .quantity,
        .ast-site-header-cart .woocommerce-Price-amount {
            white-space: nowrap;
        }

        @media (max-width: 480px) {
            .ast-site-header-cart .widget_shopping_cart {
                min-width: 0;
                width: calc(100vw - 16px) !important;
            }
        }
    </style>
    <?php
}, 99 );
