<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ufsc_get_club_profile_value' ) ) {
    /** Read club profile values with historical aliases, without database writes. */
    function ufsc_get_club_profile_value( $club, $field ) {
        $aliases = array(
            'name' => array( 'nom', 'name', 'club_name' ),
            'region' => array( 'region', 'region_club' ),
            'address' => array( 'adresse', 'adresse_siege', 'adresse_postale', 'address' ),
            'postal_code' => array( 'code_postal', 'cp', 'postal_code' ),
            'city' => array( 'ville', 'city' ),
            'phone' => array( 'telephone', 'tel', 'phone', 'tel_mobile', 'telephone_contact' ),
            'email' => array( 'email', 'email_contact', 'contact_email' ),
            'website' => array( 'url_site', 'site_web', 'website', 'site_internet' ),
            'affiliation_number' => array( 'num_affiliation', 'numero_affiliation', 'num_asptt' ),
            'logo' => array( 'profile_photo_url', 'logo_url', 'logo' ),
        );
        foreach ( $aliases[ $field ] ?? array( $field ) as $key ) {
            if ( is_object( $club ) && isset( $club->$key ) && '' !== trim( (string) $club->$key ) ) { return $club->$key; }
            if ( is_array( $club ) && isset( $club[ $key ] ) && '' !== trim( (string) $club[ $key ] ) ) { return $club[ $key ]; }
        }
        return '';
    }
}

/** Official UFSC honorability documents. */
if ( ! function_exists( 'ufsc_get_honorability_note_url' ) ) {
    function ufsc_get_honorability_note_url() {
        return (string) apply_filters(
            'ufsc_honorability_note_url',
            'https://ufsc-france.fr/wp-content/uploads/2026/08/2021-06-02-2-ANNEXE-1-NOTE-SUR-LE-CONTROLE-DE-LHONORABILITE.pdf'
        );
    }
}

function ufsc_honorability_default_template_url( $url ) {
    if ( '' !== trim( (string) $url ) ) { return $url; }
    return 'https://ufsc-france.fr/wp-content/uploads/2026/08/Attestation_Honorabilite_UFSC_2026_remplissable.pdf';
}
add_filter( 'ufsc_honorability_template_url', 'ufsc_honorability_default_template_url', 5 );

/** Add concise, non-blocking honorability guidance to the existing front forms. */
function ufsc_render_honorability_onboarding_assets() {
    if ( is_admin() || ! is_user_logged_in() ) { return; }
    $template_url = function_exists( 'ufsc_get_honorability_template_url' ) ? ufsc_get_honorability_template_url() : ufsc_honorability_default_template_url( '' );
    $note_url = ufsc_get_honorability_note_url();
    ?>
    <style id="ufsc-honorability-onboarding-css">
    .ufsc-honorability-onboarding{margin:14px 0;padding:14px 16px;border:1px solid #dfe5ef;border-left:4px solid #b48755;border-radius:10px;background:#fff;color:#2f3440}.ufsc-honorability-onboarding__title{margin:0 0 5px;font-size:15px;font-weight:800;color:#292668}.ufsc-honorability-onboarding__text{margin:0 0 10px;font-size:13px;line-height:1.45}.ufsc-honorability-onboarding__actions{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 9px}.ufsc-honorability-onboarding__btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:7px 12px;border:1px solid #0b6194;border-radius:999px;background:#0b6194;color:#fff!important;text-decoration:none!important;font-size:12px;font-weight:800}.ufsc-honorability-onboarding__btn--secondary{background:#fff;color:#0b6194!important}.ufsc-honorability-onboarding__steps{margin:0;padding-left:19px;font-size:12px;line-height:1.5;color:#525967}.ufsc-honorability-onboarding__later{margin:8px 0 0;padding:8px 10px;border-radius:7px;background:#fff6dc;color:#6c5013;font-size:12px;font-weight:650}@media(max-width:640px){.ufsc-honorability-onboarding__actions{display:grid}.ufsc-honorability-onboarding__btn{width:100%}}
    </style>
    <script id="ufsc-honorability-onboarding-js">
    (function(){'use strict';var templateUrl=<?php echo wp_json_encode( esc_url_raw( $template_url ) ); ?>;var noteUrl=<?php echo wp_json_encode( esc_url_raw( $note_url ) ); ?>;function norm(value){value=(value||'').toString().toLowerCase();return typeof value.normalize==='function'?value.normalize('NFD').replace(/[\u0300-\u036f]/g,''):value;}function hasWords(node,words){var text=norm(node&&node.textContent);return words.some(function(word){return text.indexOf(norm(word))!==-1;});}function makeBox(context){var box=document.createElement('div');box.className='ufsc-honorability-onboarding';box.setAttribute('data-ufsc-honorability-onboarding',context);var later=context==='club'?'Le dépôt ne bloque pas la création du club. Après enregistrement des dirigeants, chaque personne concernée retrouvera son attestation à déposer dans le Compte Club.':'Si cette personne n’est pas encore enregistrée, enregistrez d’abord la licence. Le dépôt du document signé sera ensuite disponible sur son profil dans le Compte Club.';box.innerHTML='<p class="ufsc-honorability-onboarding__title">Honorabilité : document à préparer</p><p class="ufsc-honorability-onboarding__text">Les dirigeants, éducateurs, entraîneurs, coaches et encadrants concernés doivent disposer d’une attestation d’honorabilité pour la saison.</p><div class="ufsc-honorability-onboarding__actions"><a class="ufsc-honorability-onboarding__btn" href="'+templateUrl+'" target="_blank" rel="noopener">Télécharger l’attestation UFSC remplissable</a><a class="ufsc-honorability-onboarding__btn ufsc-honorability-onboarding__btn--secondary" href="'+noteUrl+'" target="_blank" rel="noopener">Lire la note sur le contrôle de l’honorabilité</a></div><ol class="ufsc-honorability-onboarding__steps"><li>Télécharger et remplir l’attestation.</li><li>La signer sur ordinateur ou à la main.</li><li>Enregistrer le document signé en PDF, JPG ou PNG.</li><li>Le déposer sur le profil de la personne concernée.</li></ol><p class="ufsc-honorability-onboarding__later">'+later+'</p>';return box;}function enhanceLicenceHonorability(){var checkbox=document.querySelector('input[name="honorability_confirmed"]');if(!checkbox)return;var section=checkbox.closest('.ufsc-form-section,.ufsc-step-panel,.ufsc-step,.ufsc-card,.form-section,fieldset,section,.ufsc-compliance-section')||checkbox.parentElement;if(!section||section.querySelector('[data-ufsc-honorability-onboarding="licence"]'))return;var existingNoteButton=Array.prototype.slice.call(section.querySelectorAll('a,button')).find(function(el){return hasWords(el,['note sur le controle de l’honorabilite','note sur le contrôle de l’honorabilité']);});var box=makeBox('licence');if(existingNoteButton){var actionRow=existingNoteButton.closest('p,div');if(actionRow&&actionRow.parentNode)actionRow.parentNode.insertBefore(box,actionRow.nextSibling);else section.insertBefore(box,checkbox.closest('label')||checkbox);}else{section.insertBefore(box,checkbox.closest('label')||checkbox);}var label=checkbox.closest('label');if(label){Array.prototype.slice.call(label.childNodes).forEach(function(node){if(node.nodeType===3&&norm(node.nodeValue).indexOf('je confirme')!==-1){node.nodeValue=' Je reconnais avoir pris connaissance de l’obligation d’honorabilité applicable à cette fonction et du document à fournir.';}});}}function findClubForm(){var action=document.querySelector('form input[name="action"][value="ufsc_save_club"]');return action?action.closest('form'):document.querySelector('.ufsc-club-form-container form,.ufsc-club-form');}function directClubTarget(form){var nodes=Array.prototype.slice.call(form.querySelectorAll('fieldset,section,.ufsc-form-section,.ufsc-step-panel,.ufsc-step,.ufsc-card'));return nodes.find(function(node){return hasWords(node,['membres du bureau','bureau','president','président','secretaire','secrétaire','tresorier','trésorier']);})||null;}function enhanceClubCreation(){var form=findClubForm();if(!form||form.querySelector('[data-ufsc-honorability-onboarding="club"]'))return;var target=directClubTarget(form);var box=makeBox('club');if(target){target.insertBefore(box,target.firstChild);return;}var submit=form.querySelector('button[type="submit"],input[type="submit"]');if(submit&&submit.parentNode)submit.parentNode.insertBefore(box,submit);else form.appendChild(box);}function init(){enhanceLicenceHonorability();enhanceClubCreation();}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();window.setTimeout(init,400);})();
    </script>
    <?php
}
add_action( 'wp_footer', 'ufsc_render_honorability_onboarding_assets', 35 );

require_once UFSC_CL_DIR . 'inc/common/honorability-admin.php';
