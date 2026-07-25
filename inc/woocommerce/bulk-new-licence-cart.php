<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Add a progressive-enhancement bulk selector for unpaid new licence drafts.
 *
 * The existing secure `ufsc_add_to_cart` handler remains authoritative: this
 * UI only submits an explicit comma-separated list of licence dossier IDs.
 * That handler validates ownership and adds one WooCommerce cart line per ID.
 */
function ufsc_init_bulk_new_licence_cart() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    add_action( 'wp_footer', 'ufsc_render_bulk_new_licence_cart_enhancement', 40 );
}

/**
 * Render the client-side selector only when the front licence cards exist.
 * Individual payment forms remain fully functional when JavaScript is absent.
 */
function ufsc_render_bulk_new_licence_cart_enhancement() {
    if ( is_admin() || ! function_exists( 'ufsc_get_licence_product_id' ) || absint( ufsc_get_licence_product_id() ) <= 0 ) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const grid = document.querySelector('.ufsc-licence-grid');
        if (!grid || document.getElementById('ufsc-bulk-new-licence-form')) return;

        const candidates = Array.from(grid.querySelectorAll('form.ufsc-licence-action-form')).filter(function (form) {
            const ids = form.querySelector('input[name="ufsc_license_ids"]');
            const renewal = form.querySelector('input[name="ufsc_action"][value="renew_licence"]');
            return !!ids && !renewal;
        });
        if (candidates.length < 2) return;

        const first = candidates[0];
        const product = first.querySelector('input[name="product_id"]');
        const club = first.querySelector('input[name="ufsc_club_id"]');
        const nonce = first.querySelector('input[name="_ufsc_nonce"]');
        if (!product || !club || !nonce) return;

        const bulk = document.createElement('form');
        bulk.id = 'ufsc-bulk-new-licence-form';
        bulk.method = 'post';
        bulk.action = first.action;
        bulk.className = 'ufsc-bulk-new-licence-form';
        bulk.style.cssText = 'margin:0 0 18px;padding:14px;border:1px solid #dcdcde;background:#fff;display:flex;gap:12px;align-items:center;flex-wrap:wrap';
        bulk.innerHTML =
            '<input type="hidden" name="action" value="ufsc_add_to_cart">' +
            '<input type="hidden" name="product_id" value="' + product.value.replace(/"/g, '&quot;') + '">' +
            '<input type="hidden" name="ufsc_club_id" value="' + club.value.replace(/"/g, '&quot;') + '">' +
            '<input type="hidden" name="_ufsc_nonce" value="' + nonce.value.replace(/"/g, '&quot;') + '">' +
            '<input type="hidden" name="ufsc_license_ids" id="ufsc-new-licence-ids" value="">' +
            '<strong>Paiement groupé des nouvelles licences</strong>' +
            '<span id="ufsc-new-selection-count" aria-live="polite">0 licence sélectionnée</span>' +
            '<button type="submit" id="ufsc-bulk-new-submit" class="button button-primary" disabled>Ajouter les dossiers sélectionnés au panier</button>' +
            '<small style="flex-basis:100%">Chaque dossier restera sur une ligne nominative distincte dans le panier et la commande WooCommerce.</small>';

        grid.parentNode.insertBefore(bulk, grid);

        const idsField = bulk.querySelector('#ufsc-new-licence-ids');
        const count = bulk.querySelector('#ufsc-new-selection-count');
        const submit = bulk.querySelector('#ufsc-bulk-new-submit');
        const checkboxes = [];

        candidates.forEach(function (form) {
            const idInput = form.querySelector('input[name="ufsc_license_ids"]');
            const card = form.closest('.ufsc-licence-card');
            if (!idInput || !card || !/^\d+$/.test(idInput.value)) return;

            const label = document.createElement('label');
            label.style.cssText = 'display:flex;gap:8px;align-items:center;margin:8px 0;font-weight:600';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'ufsc-new-licence-checkbox';
            checkbox.value = idInput.value;
            label.appendChild(checkbox);
            label.appendChild(document.createTextNode(' Sélectionner pour le paiement groupé'));

            const actions = card.querySelector('.ufsc-licence-actions');
            card.insertBefore(label, actions || card.firstChild);
            checkboxes.push(checkbox);
        });

        function refresh() {
            const selected = checkboxes.filter(function (checkbox) { return checkbox.checked; });
            idsField.value = selected.map(function (checkbox) { return checkbox.value; }).join(',');
            count.textContent = selected.length + (selected.length > 1 ? ' licences sélectionnées' : ' licence sélectionnée');
            submit.disabled = selected.length === 0;
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', refresh);
        });

        bulk.addEventListener('submit', function (event) {
            refresh();
            if (!idsField.value) event.preventDefault();
        });
    });
    </script>
    <?php
}
