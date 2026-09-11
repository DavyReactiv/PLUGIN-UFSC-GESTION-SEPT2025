const { test, expect } = require('@playwright/test');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const jquery = require.resolve('jquery/dist/jquery.min.js');
const legacyDashboard = path.join(root, 'assets/js/frontend-dashboard.js');
const renewalController = path.join(root, 'assets/js/ufsc-renewal-production-flow.js');

test('paid renewal real 1-2-3 journey performs one native admin-post POST', async ({ page }) => {
  const posts = [];
  let statusSeen = false;

  await page.exposeFunction('ufscMarkSubmitStatus', (text) => {
    if (String(text || '').includes('Traitement du renouvellement en cours')) statusSeen = true;
  });

  await page.route('https://ufsc.test/wp-admin/admin-post.php', async (route) => {
    const request = route.request();
    posts.push({ method: request.method(), body: request.postData() || '' });
    await new Promise((resolve) => setTimeout(resolve, 150));
    await route.fulfill({
      status: 200,
      contentType: 'text/html',
      body: '<!doctype html><html><body>submitted</body></html>',
    });
  });

  await page.setContent(`<!doctype html>
  <html><body>
    <div id="ufsc-renewal-wizard" class="ufsc-renewal-wizard">
      <ol class="ufsc-renewal-steps">
        <li data-ufsc-step-indicator="1"><strong>1</strong> Sélectionner</li>
        <li data-ufsc-step-indicator="2"><strong>2</strong> Vérifier</li>
        <li data-ufsc-step-indicator="3"><strong>3</strong> Finaliser</li>
      </ol>
      <div class="ufsc-journey-renewal-quota">10 / 10 utilisées — 0 restante</div>
      <form id="ufsc-renewal-assistant-form" method="post" action="https://ufsc.test/wp-admin/admin-post.php" data-current-step="1" data-initial-step="1">
        <input type="hidden" name="action" value="ufsc_bulk_renew_licences">
        <input type="hidden" name="ufsc_club_id" value="1">
        <input type="hidden" name="target_season" value="2026-2027">

        <div data-ufsc-selection-count></div>
        <div class="ufsc-front-table-scroll">
          <table class="ufsc-renewal-table">
            <thead><tr><th>Sélection</th><th>Identité</th></tr></thead>
            <tbody>
              <tr class="ufsc-renewal-source-row" data-source-id="1326" data-complete="1" data-cart-eligible="1" data-blocked="0">
                <td><input class="ufsc-renewal-checkbox" type="checkbox" name="ufsc_renew_ids[]" value="1326" checked></td>
                <td data-label="Identité">Licence test</td>
              </tr>
            </tbody>
          </table>
        </div>

        <section class="ufsc-renewal-profile-row" data-profile-id="1326" hidden>
          <div data-ufsc-completeness><strong>Dossier complet</strong></div>
          <input required name="profiles[1326][nom]" value="TEST">
          <input required name="profiles[1326][prenom]" value="Licence">
          <input required type="email" name="profiles[1326][email]" value="licence@example.test">
          <input required type="date" name="profiles[1326][date_naissance]" value="1990-01-01">
          <select required name="profiles[1326][sexe]"><option value="M" selected>Homme</option></select>
          <input required name="profiles[1326][adresse]" value="1 rue du Test">
          <input required name="profiles[1326][ville]" value="Montluçon">
          <input required name="profiles[1326][code_postal]" value="03100">
          <select required name="profiles[1326][fighter_level]"><option value="classe_c" selected>Classe C</option></select>
          <input required type="number" min="20" max="300" name="profiles[1326][poids]" value="70">
        </section>

        <div class="ufsc-renewal-actions" data-ufsc-step-actions="1">
          <button type="button" data-ufsc-select-all>Tout sélectionner</button>
          <button type="button" data-ufsc-select-none>Tout désélectionner</button>
          <button type="button" data-ufsc-next-step="2">Vérifier</button>
        </div>
        <div class="ufsc-renewal-actions" data-ufsc-step-actions="2" hidden>
          <button type="button" data-ufsc-next-step="3">Continuer</button>
        </div>
        <div data-ufsc-step-review="3" hidden>
          <strong data-ufsc-review-title></strong>
          <span data-ufsc-review-status></span>
          <ul></ul>
        </div>
        <span id="ufsc-cart-readiness"></span>
        <div class="ufsc-renewal-actions" data-ufsc-step-actions="3" hidden>
          <button type="submit" name="ufsc_renew_intent" value="add_to_cart" data-ufsc-product-ready="1">Confirmer</button>
        </div>
      </form>
    </div>
  </body></html>`, { waitUntil: 'domcontentloaded' });

  await page.addScriptTag({ path: jquery });
  await page.evaluate(() => {
    window.ufsc_frontend_vars = { ajax_url: '/wp-admin/admin-ajax.php', club_id: 1 };
    window.ufsc_dashboard_vars = {};
  });
  await page.addScriptTag({ path: legacyDashboard });
  await page.addScriptTag({ path: renewalController });

  await page.evaluate(() => {
    const form = document.getElementById('ufsc-renewal-assistant-form');
    const report = () => {
      const node = form && form.querySelector('[data-ufsc-final-submit-status="1"]');
      if (node) window.ufscMarkSubmitStatus(node.textContent || '');
    };
    const observer = new MutationObserver(report);
    observer.observe(form, { childList: true, subtree: true, attributes: true });
    report();
  });

  const form = page.locator('#ufsc-renewal-assistant-form');
  await expect(form).toHaveAttribute('data-current-step', '1');

  await form.locator('[data-ufsc-next-step="2"]').click();
  await expect(form).toHaveAttribute('data-current-step', '2');

  await form.locator('[data-ufsc-next-step="3"]').click();
  await expect(form).toHaveAttribute('data-current-step', '3');

  const submit = form.locator('button[name="ufsc_renew_intent"][value="add_to_cart"]');
  await expect(submit).toBeVisible();
  await expect(submit).toBeEnabled();
  await submit.click({ noWaitAfter: true });

  await expect.poll(() => statusSeen, { timeout: 5000 }).toBe(true);
  await expect.poll(() => posts.length, { timeout: 5000 }).toBe(1);
  expect(posts[0].method).toBe('POST');

  const params = new URLSearchParams(posts[0].body);
  expect(params.get('action')).toBe('ufsc_bulk_renew_licences');
  expect(params.get('ufsc_renew_intent')).toBe('add_to_cart');
  expect(params.get('ufsc_renew_intent_fallback')).toBe('add_to_cart');
  expect(params.getAll('ufsc_renew_ids[]')).toEqual(['1326']);
});
