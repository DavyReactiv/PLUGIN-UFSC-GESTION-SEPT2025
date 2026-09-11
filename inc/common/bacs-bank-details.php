<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Official UFSC bank details used for BACS payments.
 *
 * The values come from the association's Crédit Mutuel RIB. They are exposed
 * through a filter so a future banking change can be applied centrally without
 * touching the checkout presentation code.
 *
 * @return array<string,string>
 */
function ufsc_bacs_bank_account_details() {
    $details = array(
        'holder' => 'UNION FRANCAISE DES SPORTS DE COMBAT',
        'bank'   => 'Crédit Mutuel — CCM ST REMY DE PROVENCE',
        'iban'   => 'FR76 1027 8090 6100 0204 6460 166',
        'bic'    => 'CMCIFR2A',
    );

    return (array) apply_filters( 'ufsc_bacs_bank_account_details', $details );
}

/**
 * Build the bank-details block shown to a club selecting a bank transfer.
 *
 * @param WC_Order|null $order Optional order when it already exists.
 * @return string
 */
function ufsc_bacs_bank_details_html( $order = null ) {
    $details = ufsc_bacs_bank_account_details();
    $reference = __( 'Indiquez le numéro de commande et le nom du club dans le libellé du virement.', 'ufsc-clubs' );

    if ( $order && is_callable( array( $order, 'get_order_number' ) ) ) {
        $reference = sprintf(
            __( 'Référence du virement : commande #%s + nom du club.', 'ufsc-clubs' ),
            $order->get_order_number()
        );
    }

    return '<div class="ufsc-bacs-bank-details" aria-label="' . esc_attr__( 'Coordonnées bancaires UFSC', 'ufsc-clubs' ) . '">'
        . '<p><strong>' . esc_html__( 'Coordonnées bancaires UFSC', 'ufsc-clubs' ) . '</strong></p>'
        . '<p><strong>' . esc_html__( 'Titulaire :', 'ufsc-clubs' ) . '</strong> ' . esc_html( (string) ( $details['holder'] ?? '' ) ) . '<br>'
        . '<strong>' . esc_html__( 'Banque :', 'ufsc-clubs' ) . '</strong> ' . esc_html( (string) ( $details['bank'] ?? '' ) ) . '<br>'
        . '<strong>' . esc_html__( 'IBAN :', 'ufsc-clubs' ) . '</strong> <code>' . esc_html( (string) ( $details['iban'] ?? '' ) ) . '</code><br>'
        . '<strong>' . esc_html__( 'BIC :', 'ufsc-clubs' ) . '</strong> <code>' . esc_html( (string) ( $details['bic'] ?? '' ) ) . '</code></p>'
        . '<p><strong>' . esc_html( $reference ) . '</strong></p>'
        . '</div>';
}

/**
 * Plain-text equivalent for transactional e-mails.
 *
 * @param WC_Order|null $order Optional order when it already exists.
 * @return string
 */
function ufsc_bacs_bank_details_text( $order = null ) {
    $details = ufsc_bacs_bank_account_details();
    $lines = array(
        __( 'Coordonnées bancaires UFSC', 'ufsc-clubs' ),
        sprintf( __( 'Titulaire : %s', 'ufsc-clubs' ), (string) ( $details['holder'] ?? '' ) ),
        sprintf( __( 'Banque : %s', 'ufsc-clubs' ), (string) ( $details['bank'] ?? '' ) ),
        sprintf( __( 'IBAN : %s', 'ufsc-clubs' ), (string) ( $details['iban'] ?? '' ) ),
        sprintf( __( 'BIC : %s', 'ufsc-clubs' ), (string) ( $details['bic'] ?? '' ) ),
    );

    if ( $order && is_callable( array( $order, 'get_order_number' ) ) ) {
        $lines[] = sprintf(
            __( 'Référence du virement : commande #%s + nom du club.', 'ufsc-clubs' ),
            $order->get_order_number()
        );
    } else {
        $lines[] = __( 'Indiquez le numéro de commande et le nom du club dans le libellé du virement.', 'ufsc-clubs' );
    }

    return implode( "\n", $lines );
}

/** Append the official account details to the BACS method on checkout. */
function ufsc_bacs_append_bank_details_to_gateway_description( $description, $gateway_id ) {
    if ( 'bacs' !== $gateway_id || false !== strpos( (string) $description, 'ufsc-bacs-bank-details' ) ) {
        return $description;
    }

    return (string) $description . ufsc_bacs_bank_details_html();
}
add_filter( 'woocommerce_gateway_description', 'ufsc_bacs_append_bank_details_to_gateway_description', 30, 2 );

/** Repeat the account details on the BACS confirmation page with order reference. */
function ufsc_bacs_render_bank_details_on_thankyou( $order_id ) {
    $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
    if ( ! $order || 'bacs' !== $order->get_payment_method() ) {
        return;
    }

    echo wp_kses_post( ufsc_bacs_bank_details_html( $order ) );
}
add_action( 'woocommerce_thankyou_bacs', 'ufsc_bacs_render_bank_details_on_thankyou', 30 );

/** Include the account details in customer e-mails for BACS orders. */
function ufsc_bacs_email_bank_details( $order, $sent_to_admin, $plain_text, $email ) {
    unset( $email );
    if ( $sent_to_admin || ! $order || 'bacs' !== $order->get_payment_method() ) {
        return;
    }

    if ( $plain_text ) {
        echo esc_html( ufsc_bacs_bank_details_text( $order ) ) . "\n";
        return;
    }

    echo wp_kses_post( ufsc_bacs_bank_details_html( $order ) );
}
add_action( 'woocommerce_email_before_order_table', 'ufsc_bacs_email_bank_details', 30, 4 );
