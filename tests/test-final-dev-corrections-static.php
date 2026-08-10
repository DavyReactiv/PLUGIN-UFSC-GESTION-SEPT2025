<?php
$root=dirname(__DIR__);$front=file_get_contents($root.'/includes/frontend/class-frontend-shortcodes.php');$css=file_get_contents($root.'/assets/css/ufsc-front.css');$js=file_get_contents($root.'/assets/js/frontend-dashboard.js');$settings=file_get_contents($root.'/inc/woocommerce/settings-woocommerce.php');
$a=function($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}};
$a(strpos($settings,'array_merge( $existing, $sanitized )')!==false,'partial settings save preserves product');
foreach(array('opacity: 1','visibility: visible','white-space: nowrap','min-width: max-content','padding-inline: 18px') as $n)$a(strpos($css,$n)!==false,"button contract $n");
$a(strpos($css,'.ufsc-club-portal .ufsc-renewal-pagination a.ufsc-btn')!==false && strpos($css,'[aria-disabled="true"]')!==false,'visible pagination states');
$a(strpos($front,'Saison archivée')!==false && strpos($front,'Consulter les archives')!==false && strpos($front,'disabled( empty( $archive_seasons )')!==false,'archive controls always rendered');
$a(strpos($front,'ufsc_renew_source_season')!==false && strpos($front,"'ufsc_renew_source_season' => " . '$source')!==false,'season preserved in pagination');
$a(strpos($js,"source.attr('data-complete') === '1'")!==false && strpos($js,"ready === renewalForm.find('.ufsc-renewal-checkbox:checked').length")!==false,'only complete dossiers reach cart review');
$a(strpos($js,"ufsc-cart-submitting")!==false,'double submit blocked');
$a(strpos($front,'Génération en cours')!==false && strpos($front,'Aucune action n’est nécessaire')!==false,'attestation status is explanatory');
$a(strpos($css,'.ufsc-dashboard-actions--primary { display: grid')!==false && strpos($css,'grid-template-columns: repeat(2,minmax(0,1fr))')!==false,'dashboard actions grid');
$a(substr_count($front,'id="ufsc-club-logo-file"')===1 && strpos($css,'.ufsc-logo-editor__file')!==false,'single accessible logo picker');
$a(strpos($front,'<noscript>')!==false && strpos($front,'data-ufsc-renew-one')!==false,'server fallback retained');
echo "Final DEV corrective static safeguards OK\n";
