<?php

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = null ) { unset( $domain ); return $text; }
function esc_html__( $text, $domain = null ) { unset( $domain ); return $text; }
function esc_attr__( $text, $domain = null ) { unset( $domain ); return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function apply_filters( $tag, $value ) { unset( $tag ); return $value; }
function add_filter() { return true; }
function add_action() { return true; }
function wp_kses_post( $html ) { return $html; }

require dirname( __DIR__ ) . '/inc/common/bacs-bank-details.php';

final class UFSC_Bacs_Test_Order {
    public function get_order_number() { return '1234'; }
    public function get_payment_method() { return 'bacs'; }
}

$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { $failures[] = $message; }
};

$details = ufsc_bacs_bank_account_details();
$assert( 'FR76 1027 8090 6100 0204 6460 166' === $details['iban'], 'IBAN UFSC officiel incorrect' );
$assert( 'CMCIFR2A' === $details['bic'], 'BIC UFSC officiel incorrect' );
$assert( 'UNION FRANCAISE DES SPORTS DE COMBAT' === $details['holder'], 'titulaire UFSC incorrect' );

$html = ufsc_bacs_bank_details_html();
foreach ( array( 'Coordonnées bancaires UFSC', 'FR76 1027 8090 6100 0204 6460 166', 'CMCIFR2A', 'numéro de commande', 'nom du club' ) as $expected ) {
    $assert( false !== strpos( $html, $expected ), 'checkout BACS incomplet : ' . $expected );
}

$order_html = ufsc_bacs_bank_details_html( new UFSC_Bacs_Test_Order() );
$assert( false !== strpos( $order_html, 'commande #1234 + nom du club' ), 'référence de virement avec commande absente' );

$original = '<p>Carte bancaire</p>';
$assert( $original === ufsc_bacs_append_bank_details_to_gateway_description( $original, 'mypos' ), 'les autres moyens de paiement ne doivent pas être modifiés' );
$bacs = ufsc_bacs_append_bank_details_to_gateway_description( '<p>Virement bancaire</p>', 'bacs' );
$assert( false !== strpos( $bacs, 'ufsc-bacs-bank-details' ), 'le checkout BACS doit afficher le bloc bancaire' );

if ( $failures ) {
    foreach ( $failures as $failure ) { fwrite( STDERR, "FAIL: {$failure}\n" ); }
    exit( 1 );
}

echo "BACS bank details runtime safeguards OK\n";
