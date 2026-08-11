#!/usr/bin/env node
'use strict';

// Run the shipped bundle in the DOM/AJAX simulator. The underlying scenario
// invokes init twice, coalesces identical requests and injects 403, 500 and 0.
const { spawnSync } = require('child_process');
const path = require('path');
const scenario = path.join(__dirname, 'test-frontend-dashboard-dom-runtime.js');
const result = spawnSync(process.execPath, [scenario], { encoding: 'utf8' });
if (result.status !== 0) {
    process.stderr.write(result.stderr || result.stdout);
    process.exit(result.status || 1);
}
if (!result.stdout.includes('Frontend dashboard DOM runtime initialization OK')) {
    throw new Error('dashboard network scenario did not complete');
}
console.log('Dashboard duplicate-init, 403, 500 and AJAX-0 runtime safeguards OK');
