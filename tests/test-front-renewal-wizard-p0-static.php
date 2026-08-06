<?php
$root=dirname(__DIR__);$front=file_get_contents($root.'/includes/frontend/class-frontend-shortcodes.php');$js=file_get_contents($root.'/assets/js/frontend-dashboard.js');$css=file_get_contents($root.'/assets/css/ufsc-front.css');$cart=file_get_contents($root.'/inc/woocommerce/cart-integration.php');$levels=file_get_contents($root.'/inc/common/fighter-level.php');
$checks=array(
 'selection independent from completeness'=>strpos($front,"\$selectable = ! empty( \$context['renewal_allowed'] ) && \$product_id")!==false&&strpos($front,'$complete = $level')!==false,
 'selectable profile rendered'=>strpos($front,'if ( $selectable ) : $prefix')!==false&&strpos($front,'if ( $eligible ) : $prefix')===false,
 'incomplete badge'=>strpos($front,'Informations à compléter')!==false,
 'individual real button'=>strpos($front,'<button type="button" class="ufsc-btn ufsc-btn-primary ufsc-btn-small" data-ufsc-renew-one=')!==false,
 'three functional controls'=>strpos($front,'data-ufsc-next-step="2"')!==false&&strpos($front,'data-ufsc-next-step="3"')!==false&&strpos($front,'type="submit"')!==false,
 'server fallback'=>strpos($front,'admin-post.php')!==false&&strpos($front,'ufsc_bulk_renew_licences')!==false,
 'js wizard wiring'=>strpos($js,'showRenewalStep')!==false&&strpos($js,'data-ufsc-renew-one')!==false&&strpos($js,'ufsc-renewal-checkbox:checked')!==false,
 'accessible state'=>strpos($front,'aria-current="step"')!==false&&strpos($front,'aria-describedby')!==false,
 'official levels'=>strpos($levels,"'debutant'")!==false&&strpos($levels,"'pro'")!==false,
 'full form fields retained'=>!array_filter(array('reduction_benevole','identifiant_laposte','diffusion_image','infos_fsasptt','assurance_dommage_corporel','health_questionnaire_confirmed','note'),function($field)use($front){return strpos($front,$field)===false;}),
 'cart nominative metadata'=>strpos($cart,'ufsc_previous_licence_id')!==false&&strpos($cart,'ufsc_numero_licence_ufsc')!==false&&strpos($cart,'ufsc_category')!==false,
 'post and season security'=>strpos($cart,"'POST' !== strtoupper")!==false&&strpos($cart,'Saison cible invalide')!==false,
 'responsive cards'=>strpos($css,'@media(max-width:768px)')!==false&&strpos($css,'.ufsc-renewal-source-row td:before')!==false,
);
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}}echo "Front renewal wizard P0 static safeguards passed.\n";
