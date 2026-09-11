const { test, expect } = require('@playwright/test');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const jquery = require.resolve('jquery/dist/jquery.min.js');
const licenceFormJs = path.join(root, 'assets/js/ufsc-license-form.js');

test('new paid licence keeps add_to_cart submitter and performs one admin-post POST', async ({ page }) => {
  const posts = [];

  await page.route('https://ufsc.test/wp-admin/admin-post.php', async (route) => {
    const request = route.request();
    posts.push({ method: request.method(), body: request.postData() || '' });
    await route.fulfill({
      status: 200,
      contentType: 'text/html',
      body: '<!doctype html><html><body>submitted</body></html>',
    });
  });

  await page.setContent(`<!doctype html>
  <html><body>
    <h1>Ajouter une licence</h1>
    <form class="ufsc-licence-form" method="post" action="https://ufsc.test/wp-admin/admin-post.php">
      <input type="hidden" name="action" value="ufsc_add_licence">
      <input type="hidden" name="_wpnonce" value="test-nonce">
      <input type="hidden" name="licence_id" value="0">
      <input type="hidden" name="club_id" value="1">
      <input type="hidden" name="ufsc_wizard_step" value="6">
      <input name="prenom" value="Test">
      <input name="nom" value="Licence">
      <input name="email" type="email" value="licence@example.test">
      <input name="date_naissance" type="date" value="1990-01-01">
      <input name="sexe" value="M">
      <input name="adresse" value="1 rue du Test">
      <input name="ville" value="Montluçon">
      <input name="code_postal" value="03100">
      <input name="pays" value="France">
      <input name="telephone" value="0600000000">
      <input name="role" value="pratiquant">
      <input name="health_questionnaire_confirmed" value="1">
      <div class="ufsc-final-buttons">
        <button type="submit" name="ufsc_submit_action" value="save_draft">Enregistrer en brouillon</button>
        <button type="submit" name="ufsc_submit_action" value="add_to_cart">Ajouter au panier — licence payante</button>
      </div>
    </form>
  </body></html>`, { waitUntil: 'domcontentloaded' });

  await page.addScriptTag({ path: jquery });
  await page.addScriptTag({ path: licenceFormJs });

  const form = page.locator('.ufsc-licence-form');
  const submit = form.locator('button[name="ufsc_submit_action"][value="add_to_cart"]');
  await expect(submit).toBeVisible();
  await expect(submit).toBeEnabled();
  await submit.click({ noWaitAfter: true });

  await expect.poll(() => posts.length, { timeout: 5000 }).toBe(1);
  expect(posts[0].method).toBe('POST');

  const params = new URLSearchParams(posts[0].body);
  expect(params.get('action')).toBe('ufsc_add_licence');
  expect(params.get('ufsc_submit_action')).toBe('add_to_cart');
  expect(params.get('licence_id')).toBe('0');
  expect(params.get('club_id')).toBe('1');
});
