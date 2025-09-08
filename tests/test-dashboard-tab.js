const assert = require('assert');
function getSection(url, hash) {
  const u = new URL(url);
  return u.searchParams.get('tab') || new URLSearchParams(hash.replace(/^#/, '')).get('tab');
}
assert.strictEqual(getSection('https://example.com/dashboard?tab=stats', '#tab=club'), 'stats');
assert.strictEqual(getSection('https://example.com/dashboard', '#tab=licences'), 'licences');
console.log('Dashboard tab fallback OK');
