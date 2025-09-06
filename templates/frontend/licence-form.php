<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
include UFSC_CL_DIR . 'templates/partials/notice.php';
$wc_settings    = ufsc_get_woocommerce_settings();
$included_limit = isset( $wc_settings['included_licenses'] ) ? (int) $wc_settings['included_licenses'] : 10;
$included_count = UFSC_Licence_Form::get_included_count();
$club_id       = ufsc_get_user_club_id( get_current_user_id() );
?>
<div class="ufsc-front ufsc-full">
<form method="post" class="ufsc-licence-form cart">
    <?php wp_nonce_field( 'ufsc_save_licence' ); ?>
    <input type="hidden" name="licence_id" value="<?php echo isset( $licence->id ) ? intval( $licence->id ) : 0; ?>" />
    <input type="hidden" name="ufsc_club_id" value="<?php echo esc_attr( $club_id ); ?>" />
    <input type="hidden" id="included_count" value="<?php echo esc_attr( $included_count ); ?>" />
    <input type="hidden" id="included_limit" value="<?php echo esc_attr( $included_limit ); ?>" />

    <div class="ufsc-grid">
        <div class="ufsc-field">
            <label for="prenom"><?php esc_html_e( 'Pr\u00e9nom', 'ufsc-clubs' ); ?></label>
            <input type="text" id="prenom" name="prenom" value="<?php echo esc_attr( $licence->prenom ?? '' ); ?>" />
        </div>
        <div class="ufsc-field">
            <label for="nom"><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></label>
            <input type="text" id="nom" name="nom" value="<?php echo esc_attr( $licence->nom ?? '' ); ?>" />
        </div>
        <div class="ufsc-field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo esc_attr( $licence->email ?? '' ); ?>" />
        </div>
        <div class="ufsc-field">
            <label for="date_naissance"><?php esc_html_e( 'Date de naissance', 'ufsc-clubs' ); ?></label>
            <input type="date" id="date_naissance" name="date_naissance" value="<?php echo esc_attr( $licence->date_naissance ?? '' ); ?>" />
        </div>
        <div class="ufsc-field">
            <label for="role"><?php esc_html_e( 'R\u00f4le', 'ufsc-clubs' ); ?></label>
            <select id="role" name="role">
                <option value=""<?php selected( $licence->role ?? '', '' ); ?>><?php esc_html_e( 'S\u00e9lectionner', 'ufsc-clubs' ); ?></option>
                <option value="president"<?php selected( $licence->role ?? '', 'president' ); ?>><?php esc_html_e( 'Pr\u00e9sident', 'ufsc-clubs' ); ?></option>
                <option value="secretaire"<?php selected( $licence->role ?? '', 'secretaire' ); ?>><?php esc_html_e( 'Secr\u00e9taire', 'ufsc-clubs' ); ?></option>
                <option value="tresorier"<?php selected( $licence->role ?? '', 'tresorier' ); ?>><?php esc_html_e( 'Tr\u00e9sorier', 'ufsc-clubs' ); ?></option>
                <option value="entraineur"<?php selected( $licence->role ?? '', 'entraineur' ); ?>><?php esc_html_e( 'Entra\u00eeneur', 'ufsc-clubs' ); ?></option>
                <option value="adherent"<?php selected( $licence->role ?? '', 'adherent' ); ?>><?php esc_html_e( 'Adh\u00e9rent', 'ufsc-clubs' ); ?></option>
            </select>
        </div>
        <div class="ufsc-field">
            <label class="ufsc-checkbox"><input type="checkbox" id="reduction_postier" name="reduction_postier" value="1" <?php checked( $licence->reduction_postier ?? 0, 1 ); ?> /> <?php esc_html_e( 'R\u00e9duction postier', 'ufsc-clubs' ); ?></label>
        </div>
        <div class="ufsc-field ufsc-field-identifiant-laposte" style="display:none;">
            <label for="identifiant_laposte"><?php esc_html_e( 'Identifiant La Poste', 'ufsc-clubs' ); ?></label>
            <input type="text" id="identifiant_laposte" name="identifiant_laposte" value="<?php echo esc_attr( $licence->identifiant_laposte ?? '' ); ?>" />
        </div>
        <div class="ufsc-field">
            <label class="ufsc-checkbox"><input type="checkbox" id="reduction_benevole" name="reduction_benevole" value="1" <?php checked( $licence->reduction_benevole ?? 0, 1 ); ?> /> <?php esc_html_e( 'R\u00e9duction b\u00e9n\u00e9vole', 'ufsc-clubs' ); ?></label>
        </div>
        <div class="ufsc-field">
            <label class="ufsc-checkbox"><input type="checkbox" id="licence_delegataire" name="licence_delegataire" value="1" <?php checked( $licence->licence_delegataire ?? 0, 1 ); ?> /> <?php esc_html_e( 'Licence d\u00e9l\u00e9gataire', 'ufsc-clubs' ); ?></label>
        </div>
        <div class="ufsc-field ufsc-field-numero-delegataire" style="display:none;">
            <label for="numero_licence_delegataire"><?php esc_html_e( 'Num\u00e9ro de licence d\u00e9l\u00e9gataire', 'ufsc-clubs' ); ?></label>
            <input type="text" id="numero_licence_delegataire" name="numero_licence_delegataire" value="<?php echo esc_attr( $licence->numero_licence_delegataire ?? '' ); ?>" />
        </div>
        <div class="ufsc-field">
            <label for="note"><?php esc_html_e( 'Note', 'ufsc-clubs' ); ?></label>
            <textarea id="note" name="note" rows="3"><?php echo esc_textarea( $licence->note ?? '' ); ?></textarea>
        </div>
        <div class="ufsc-field">
            <label class="ufsc-checkbox"><input type="checkbox" id="is_included" name="is_included" value="1" <?php checked( $licence->is_included ?? 0, 1 ); ?> /> <?php esc_html_e( 'Inclure dans quota', 'ufsc-clubs' ); ?></label>
        </div>
    </div>
    <div class="ufsc-form-actions">
        <button type="submit" class="ufsc-btn ufsc-btn-primary" formaction="<?php echo esc_url( admin_url( 'admin-post.php?action=ufsc_save_licence' ) ); ?>">
            <?php esc_html_e( 'Enregistrer', 'ufsc-clubs' ); ?>
        </button>
        <button type="submit" class="button add_to_cart_button" data-product_id="<?php echo esc_attr( $wc_settings['product_license_id'] ); ?>" name="add-to-cart" value="<?php echo esc_attr( $wc_settings['product_license_id'] ); ?>">
            <?php esc_html_e( 'Ajouter au panier', 'ufsc-clubs' ); ?>
        </button>
    </div>
</form>
</div>
