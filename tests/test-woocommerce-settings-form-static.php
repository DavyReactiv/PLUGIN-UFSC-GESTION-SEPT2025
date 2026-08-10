<?php
$source=file_get_contents(dirname(__DIR__).'/inc/woocommerce/settings-woocommerce.php');
$assert=function($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}};
$assert(strpos($source,'<form method="post" action="">')!==false,'settings parent form is POST');
$assert(strpos($source,"wp_nonce_field( 'ufsc_woocommerce_settings' )")!==false,'settings nonce rendered');
$assert(strpos($source,'id="product_license_id"')!==false && strpos($source,'name="ufsc_woocommerce_settings[product_license_id]"')!==false,'select posts canonical nested field');
$assert(strpos($source,'value="<?php echo esc_attr( $candidate->get_id() ); ?>"')!==false,'each option value is numeric product ID');
$assert(strpos($source,"submit_button( __( 'Enregistrer les paramètres WooCommerce'")!==false,'submit button is in canonical form');
$assert(strpos($source,'ufsc_process_woocommerce_settings_submission( $_POST )')!==false,'renderer uses tested handler');
$assert(strpos($source,"get_option( 'ufsc_woocommerce_settings', array() )")!==false,'handler rereads canonical option');
echo "WooCommerce settings form contract safeguards OK\n";
