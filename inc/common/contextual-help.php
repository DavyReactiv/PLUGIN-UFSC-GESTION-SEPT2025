<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Lightweight contextual guidance for UFSC club/admin screens.
 * Presentation only: no business state, quota, payment or data mutation.
 */

/** Return concise contextual help by visible panel title. */
function ufsc_contextual_help_map() {
    return array(
        'Compte Club' => __( 'Vérifiez ici l’identité et les coordonnées officielles du club. Ces informations servent aux dossiers UFSC et aux documents générés.', 'ufsc-clubs' ),
        'Mon Club' => __( 'Mettez à jour les informations officielles du club et contrôlez qu’elles sont exactes avant une affiliation.', 'ufsc-clubs' ),
        'Informations du club' => __( 'Renseignez les coordonnées officielles, le siège et les contacts du club. Enregistrez après chaque modification.', 'ufsc-clubs' ),
        'Dirigeants' => __( 'Renseignez au minimum le président, le secrétaire et le trésorier. Certains rôles peuvent nécessiter une attestation d’honorabilité.', 'ufsc-clubs' ),
        'Bureau' => __( 'Vérifiez les membres du bureau et leurs coordonnées. Les rôles obligatoires doivent être complets pour le dossier d’affiliation.', 'ufsc-clubs' ),
        'Documents' => __( 'Déposez les pièces demandées dans un format lisible. Un document à corriger reste visible tant qu’une nouvelle version n’a pas été enregistrée.', 'ufsc-clubs' ),
        'Affiliation' => __( 'Suivez ici l’état du dossier annuel. Le paiement et la validation UFSC sont deux étapes distinctes.', 'ufsc-clubs' ),
        'Mes licences UFSC' => __( 'Créez, complétez et suivez les licences de la saison. Pour modifier un poids, ouvrez la fiche de la licence concernée puis enregistrez la nouvelle valeur.', 'ufsc-clubs' ),
        'Licences' => __( 'Les brouillons peuvent être repris plus tard. Une licence n’entre dans le quota qu’au moment de sa finalisation.', 'ufsc-clubs' ),
        'Ajouter une licence' => __( 'Vous pouvez enregistrer un brouillon sans consommer de place. À l’envoi, une place incluse est utilisée si le quota le permet ; sinon la licence passe au panier.', 'ufsc-clubs' ),
        'Modifier une licence' => __( 'Enregistrez les changements avant de quitter. Une licence déjà envoyée peut être limitée à certaines corrections selon son statut.', 'ufsc-clubs' ),
        'Renouveler des licences' => __( 'Le renouvellement crée le dossier de la nouvelle saison sans réécrire l’ancienne licence. Vérifiez les informations avant l’envoi.', 'ufsc-clubs' ),
        'Statistiques' => __( 'Les indicateurs portent sur la saison affichée. Les dossiers saisis, les licences incluses, les paiements individuels et les validations UFSC sont comptés séparément.', 'ufsc-clubs' ),
        'Profil des licenciés' => __( 'La répartition doit refléter les dossiers saisis de la saison, même lorsqu’ils sont encore en brouillon ou en attente de validation.', 'ufsc-clubs' ),
        'Licences incluses dans votre affiliation' => __( 'Les 10 premières licences finalisées sont incluses dans l’affiliation. Un brouillon ne consomme pas de place.', 'ufsc-clubs' ),
        'Santé' => __( 'Vérifiez les informations demandées avant l’envoi. Les réponses personnelles au questionnaire de santé restent sous la responsabilité de l’adhérent.', 'ufsc-clubs' ),
        'Récapitulatif' => __( 'Relisez les informations avant l’envoi. Après finalisation, le dossier suit le circuit de validation UFSC.', 'ufsc-clubs' ),
    );
}

/** Clean the outdated Compte Club note and attach compact, collapsed help. */
function ufsc_contextual_help_filter_front( $output, $tag, $attr, $m ) {
    unset( $attr, $m );
    $supported = array( 'ufsc_club_dashboard', 'ufsc_club_licences', 'ufsc_add_licence', 'ufsc_licences', 'ufsc_club_profile', 'ufsc_club_stats' );
    if ( ! in_array( $tag, $supported, true ) || ! is_user_logged_in() ) {
        return $output;
    }

    $output = str_replace(
        'Coordonnées du club modifiables ici. Les poids des licenciés se mettent à jour depuis l’onglet Mes licences UFSC.',
        'Coordonnées du club modifiables ici.',
        $output
    );

    $hide_legacy_profile = ( false !== strpos( $output, 'ufsc-stats-v2' ) )
        ? '.ufsc-demographic-summary{display:none!important}'
        : '';

    $map = wp_json_encode( ufsc_contextual_help_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( false === $map ) {
        return $output;
    }

    $assets = '<style id="ufsc-context-help-css">'
        . $hide_legacy_profile
        . '.ufsc-context-help{margin:8px 0 12px;max-width:760px}.ufsc-context-help summary{display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;font-weight:700;color:#0b4f7c;background:#f4f8fc;border:1px solid #cbdbea;border-radius:999px;padding:5px 10px;list-style:none}.ufsc-context-help summary::-webkit-details-marker{display:none}.ufsc-context-help summary:before{content:"i";display:inline-grid;place-items:center;width:16px;height:16px;border-radius:50%;background:#0b4f7c;color:#fff;font-size:10px}.ufsc-context-help p{margin:7px 0 0;padding:9px 11px;border-left:3px solid #0b4f7c;background:#f8fafc;border-radius:6px;color:#334155;font-size:13px;line-height:1.45}@media(max-width:700px){.ufsc-context-help{max-width:100%}}'
        . '</style>';

    $assets .= '<script id="ufsc-context-help-js">(function(){var map=' . $map . ';function norm(s){return (s||"").replace(/\s+/g," ").trim();}function run(){var root=document.querySelector(".ufsc-dashboard,.ufsc-club-dashboard,.ufsc-container,.ufsc-account-page")||document;var heads=root.querySelectorAll("h1,h2,h3,h4");var added=0;heads.forEach(function(h){if(added>=12||h.dataset.ufscHelpDone){return;}var t=norm(h.textContent);var key=Object.keys(map).find(function(k){return t===k||t.indexOf(k)===0;});if(!key){return;}h.dataset.ufscHelpDone="1";var d=document.createElement("details");d.className="ufsc-context-help";var s=document.createElement("summary");s.textContent="Aide";var p=document.createElement("p");p.textContent=map[key];d.appendChild(s);d.appendChild(p);h.insertAdjacentElement("afterend",d);added++;});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run);}else{run();}})();</script>';

    if ( false === strpos( $output, 'ufsc-context-help-css' ) ) {
        $output .= $assets;
    }
    return $output;
}
add_filter( 'do_shortcode_tag', 'ufsc_contextual_help_filter_front', 220, 4 );

/** Return a concise admin help message for known UFSC pages. */
function ufsc_contextual_admin_help_message( $page ) {
    $page = sanitize_key( (string) $page );
    $map = array(
        'honor' => __( 'Contrôlez les attestations déposées, filtrez par saison/statut et indiquez un motif en cas de correction ou de refus.', 'ufsc-clubs' ),
        'ffst' => __( 'Sélectionnez la saison puis le club. Le dossier FFST peut être généré avec les informations disponibles ; les éléments manquants restent signalés.', 'ufsc-clubs' ),
        'bank' => __( 'Confirmez uniquement un virement réellement visible sur le compte bancaire. La confirmation déclenche ensuite le traitement WooCommerce/UFSC normal.', 'ufsc-clubs' ),
        'virement' => __( 'Confirmez uniquement un virement réellement reçu. Utilisez la référence bancaire lorsqu’elle est disponible afin de conserver une trace claire.', 'ufsc-clubs' ),
        'licence' => __( 'Utilisez les filtres de saison et de statut avant d’agir. Une validation UFSC, un paiement et une place incluse sont des états distincts.', 'ufsc-clubs' ),
        'club' => __( 'Vérifiez l’identité du club, son rattachement régional, son dossier annuel et ses dirigeants avant toute validation.', 'ufsc-clubs' ),
        'export' => __( 'Vérifiez la saison et le périmètre avant l’export. Les exports ne doivent pas modifier les fiches clubs ou licences.', 'ufsc-clubs' ),
        'import' => __( 'Contrôlez le fichier et la saison avant import. En cas de doute, testez d’abord sur l’environnement DEV.', 'ufsc-clubs' ),
        'woocommerce' => __( 'Ces réglages pilotent le raccordement aux produits et commandes WooCommerce. Ne changez un identifiant produit qu’après vérification.', 'ufsc-clubs' ),
        'droit' => __( 'Attribuez uniquement les droits nécessaires. Les comptes régionaux ne doivent voir que leur périmètre autorisé.', 'ufsc-clubs' ),
        'access' => __( 'Attribuez uniquement les droits nécessaires. Les comptes régionaux ne doivent voir que leur périmètre autorisé.', 'ufsc-clubs' ),
        'param' => __( 'Ces réglages peuvent modifier le comportement global du plugin. Vérifiez chaque changement en DEV avant production.', 'ufsc-clubs' ),
        'setting' => __( 'Ces réglages peuvent modifier le comportement global du plugin. Vérifiez chaque changement en DEV avant production.', 'ufsc-clubs' ),
    );
    foreach ( $map as $needle => $message ) {
        if ( false !== strpos( $page, $needle ) ) {
            return $message;
        }
    }
    return '';
}

/** Display one discreet admin help notice on UFSC Gestion pages only. */
function ufsc_contextual_admin_help_notice() {
    if ( ! is_admin() || ! current_user_can( 'read' ) ) {
        return;
    }
    $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page context.
    if ( '' === $page || false === strpos( $page, 'ufsc' ) ) {
        return;
    }
    $message = ufsc_contextual_admin_help_message( $page );
    if ( '' === $message ) {
        return;
    }
    echo '<div class="notice notice-info inline ufsc-admin-context-help"><p><strong>' . esc_html__( 'Aide :', 'ufsc-clubs' ) . '</strong> ' . esc_html( $message ) . '</p></div>';
}
add_action( 'admin_notices', 'ufsc_contextual_admin_help_notice', 30 );
