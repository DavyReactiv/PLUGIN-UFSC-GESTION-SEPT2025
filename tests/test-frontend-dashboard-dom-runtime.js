#!/usr/bin/env node
'use strict';

// Execute the shipped browser bundle against a small DOM/jQuery runtime. This
// catches the former global early-return without reducing the check to strings.
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(__dirname + '/../assets/js/frontend-dashboard.js', 'utf8');
const registered = [];

// Use a stable proxy implementation so chained jQuery calls behave like an
// empty, rendered page rather than throwing before initialization completes.
function emptyCollection(length = 0) {
    let proxy;
    const object = { length };
    proxy = new Proxy(object, { get(target, property) {
        if (property in target) return target[property];
        if (property === 'ready') return callback => { callback(); return proxy; };
        if (property === 'on') return (events, selector) => { registered.push({ events, selector: typeof selector === 'string' ? selector : '' }); return proxy; };
        if (property === 'data') return () => undefined;
        if (property === 'val') return () => '';
        if (property === 'is') return () => false;
        if (property === 'each') return () => proxy;
        if (['find','filter','first','not','closest','siblings','children'].includes(property)) return () => emptyCollection(0);
        return () => proxy;
    }});
    return proxy;
}

const document = { hidden: false, title: 'Portail', getElementById: () => null };
const warnings = [];
const window = { location: { search: '', hash: '', pathname: '/portail/' }, history: { replaceState() {} }, ufsc_frontend_vars: {} };
function jQuery() { return emptyCollection(0); }
jQuery.extend = Object.assign;
jQuery.each = (values, callback) => Object.keys(values || {}).forEach(key => callback(key, values[key]));

vm.runInNewContext(source, {
    window, document, jQuery, $: jQuery, URLSearchParams, URL: { revokeObjectURL() {}, createObjectURL() { return 'blob:test'; } },
    location: window.location, history: window.history, console: { warn: message => warnings.push(message), error() {}, log() {} },
    setTimeout() {}, clearTimeout() {}, setInterval() {}, confirm: () => true, Number, String, Object, Array, JSON, Date, Math
}, { filename: 'frontend-dashboard.js' });

if (warnings.some(message => String(message).includes('No club ID provided'))) throw new Error('club-id warning still emitted');
if (!window.UfscDashboard || !window.UfscDashboard.initialized) throw new Error('dashboard initializer did not run');
if (!registered.some(item => String(item.events).includes('submit') && item.selector === '.ufsc-delete-licence-form')) throw new Error('club-independent handlers were not initialized');

// A repeated call must be idempotent and must not bind a second handler set.
const bindings = registered.length;
window.UfscDashboard.init();
if (registered.length !== bindings) throw new Error('dashboard initialized twice');
console.log('Frontend dashboard DOM runtime initialization OK');
