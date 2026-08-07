#!/usr/bin/env node
'use strict';

// Execute the shipped browser bundle against a small DOM/jQuery runtime. This
// catches the former global early-return without reducing the check to strings.
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(__dirname + '/../assets/js/frontend-dashboard.js', 'utf8');
const registered = [];
const ajaxRequests = [];
const ui = { selected: 0, count: '', verifyDisabled: true, renewed: false };

// Use a stable proxy implementation so chained jQuery calls behave like an
// empty, rendered page rather than throwing before initialization completes.
function emptyCollection(length = 0, selector = '') {
    let proxy;
    const object = { length };
    proxy = new Proxy(object, { get(target, property) {
        if (property in target) return target[property];
        if (typeof property === 'string' && /^\d+$/.test(property)) return undefined;
        if (property === 'ready') return callback => { callback(); return proxy; };
        if (property === 'on') return (events, delegatedSelector, callback) => { registered.push({ events, selector: typeof delegatedSelector === 'string' ? delegatedSelector : '', callback: typeof callback === 'function' ? callback : delegatedSelector }); return proxy; };
        if (property === 'data') return key => target.__data ? target.__data[key] : undefined;
        if (property === 'attr') return (...args) => args.length === 1 ? '' : proxy;
        if (property === 'prop') return (name, value) => { if (selector.includes('.ufsc-renewal-checkbox') && name === 'checked' && value === true) { ui.selected = 1; ui.renewed = true; } if (selector.includes('[data-ufsc-next-step="2"]') && name === 'disabled') ui.verifyDisabled = value; return proxy; };
        if (property === 'text') return value => { if (selector.includes('[data-ufsc-selection-count]')) ui.count = String(value); return proxy; };
        if (property === 'val') return () => '';
        if (property === 'is') return () => false;
        if (property === 'each') return () => proxy;
        if (property === 'find') return child => emptyCollection(child.includes(':checked') ? ui.selected : 0, child);
        if (['filter','first','not','closest','siblings','children'].includes(property)) return () => emptyCollection(0);
        return () => proxy;
    }});
    return proxy;
}

const document = { hidden: false, title: 'Portail', getElementById: () => null };
const warnings = [];
const window = { location: { search: '', hash: '', pathname: '/portail/' }, history: { replaceState() {} }, ufsc_frontend_vars: {} };
function jQuery(selector) {
    if (selector && typeof selector === 'object' && selector.__data) {
        const result = emptyCollection(1, 'event-target'); result.__data = selector.__data; return result;
    }
    return emptyCollection(selector === '#ufsc-renewal-assistant-form' ? 1 : 0, String(selector || ''));
}
jQuery.extend = Object.assign;
jQuery.each = (values, callback) => Object.keys(values || {}).forEach(key => callback(key, values[key]));
jQuery.param = () => '';
jQuery.ajax = options => { ajaxRequests.push(options); return {}; };

vm.runInNewContext(source, {
    window, document, jQuery, $: jQuery, URLSearchParams, URL: { revokeObjectURL() {}, createObjectURL() { return 'blob:test'; } },
    location: window.location, history: window.history, console: { warn: message => warnings.push(message), error() {}, log() {} },
    setTimeout() {}, clearTimeout() {}, setInterval() {}, confirm: () => true, Number, String, Object, Array, JSON, Date, Math
}, { filename: 'frontend-dashboard.js' });

if (warnings.some(message => String(message).includes('No club ID provided'))) throw new Error('club-id warning still emitted');
if (!window.UfscDashboard || !window.UfscDashboard.initialized) throw new Error('dashboard initializer did not run');
if (!registered.some(item => String(item.events).includes('submit') && item.selector === '.ufsc-delete-licence-form')) throw new Error('club-independent handlers were not initialized');

// Exercise the real AJAX contract, including all optional/invalid callback
// combinations which previously raised "errorCallback is not a function".
let internalErrors = 0;
let validError = '';
window.UfscDashboard.showError = () => { internalErrors += 1; };
window.UfscDashboard.config.rest_url = '/wp-json/ufsc/v1/';
window.UfscDashboard.restRequest('success-without-callbacks');
ajaxRequests.pop().success({ ok: true });
window.UfscDashboard.restRequest('failure-without-error-callback');
ajaxRequests.pop().error({ responseJSON: { message: 'indisponible' } });
window.UfscDashboard.restRequest('failure-with-valid-callback', {}, null, message => { validError = message; });
ajaxRequests.pop().error({ responseJSON: { message: 'refusée' } });
window.UfscDashboard.restRequest('failure-with-invalid-callback', {}, {}, 'not-a-function');
ajaxRequests.pop().error({ responseJSON: { message: 'invalide' } });
if (internalErrors !== 2) throw new Error('missing internal fallback for absent/invalid error callbacks');
if (validError !== 'refusée') throw new Error('valid error callback was not called');

// A rejected secondary request must not remove the independently registered
// selection and direct-renewal handlers from the rendered-page runtime.
if (!registered.some(item => item.events === 'change' && item.selector === '.ufsc-renewal-checkbox')) throw new Error('selection handler unavailable after AJAX failure');
if (!registered.some(item => item.events === 'click' && item.selector === '[data-ufsc-renew-one]')) throw new Error('renew action unavailable after AJAX failure');
const changeHandler = registered.find(item => item.events === 'change' && item.selector === '.ufsc-renewal-checkbox').callback;
ui.selected = 1;
changeHandler();
if (ui.count !== '1 sélectionnée(s), 0 à compléter, 0 bloquée(s)' || ui.verifyDisabled) throw new Error('checkbox did not update count and enable verification');
const renewHandler = registered.find(item => item.events === 'click' && item.selector === '[data-ufsc-renew-one]').callback;
renewHandler.call({ __data: { 'ufsc-renew-one': 42 } }, { preventDefault() {} });
if (!ui.renewed) throw new Error('direct renew action did not select its licence');

// A repeated call must be idempotent and must not bind a second handler set.
const bindings = registered.length;
window.UfscDashboard.init();
if (registered.length !== bindings) throw new Error('dashboard initialized twice');
console.log('Frontend dashboard DOM runtime initialization OK');
