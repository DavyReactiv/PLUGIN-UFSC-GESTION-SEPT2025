#!/usr/bin/env node
'use strict';

// Execute the shipped browser bundle against a small DOM/jQuery runtime. This
// catches the former global early-return without reducing the check to strings.
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(__dirname + '/../assets/js/frontend-dashboard.js', 'utf8');
const registered = [];
const ajaxRequests = [];
const intervalCallbacks = [];
const ui = { selected: 0, count: '', verifyDisabled: true, renewed: false };
let dashboardPresent = false;

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

const document = { hidden: false, title: 'Portail', getElementById: id => id === 'ufsc-dashboard' && dashboardPresent ? {} : null };
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
    setTimeout() {}, clearTimeout() {}, setInterval(callback) { intervalCallbacks.push(callback); return intervalCallbacks.length; }, confirm: () => true, Number, String, Object, Array, JSON, Date, Math
}, { filename: 'frontend-dashboard.js' });

if (warnings.some(message => String(message).includes('No club ID provided'))) throw new Error('club-id warning still emitted');
if (!window.UfscDashboard || !window.UfscDashboard.initialized) throw new Error('dashboard initializer did not run');
if (!registered.some(item => String(item.events).includes('submit') && item.selector === '.ufsc-delete-licence-form')) throw new Error('club-independent handlers were not initialized');

// Returning to a visible tab must not trigger REST refreshes on pages where
// the dashboard component is absent.
const visibilityHandler = registered.find(item => item.events === 'visibilitychange').callback;
visibilityHandler();
if (ajaxRequests.length !== 0) throw new Error('visibility refresh ran without a dashboard component');

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

// A duplicated request is coalesced while in flight. Definitive 403/500 and
// the historical admin-ajax bare "0" response never schedule another cycle.
const beforeDuplicate = ajaxRequests.length;
window.UfscDashboard.restRequest('same-endpoint', { page: 1 });
window.UfscDashboard.restRequest('same-endpoint', { page: 1 });
if (ajaxRequests.length !== beforeDuplicate + 1) throw new Error('identical in-flight REST request was duplicated');
ajaxRequests[ajaxRequests.length - 1].error({ status: 403, responseJSON: { message: 'refusé' } });
window.UfscDashboard.restRequest('server-error', {});
ajaxRequests[ajaxRequests.length - 1].error({ status: 500, responseJSON: { message: 'erreur serveur' } });
const beforeZero = ajaxRequests.length;
window.UfscDashboard.apiRequest('missing-action', {});
ajaxRequests[ajaxRequests.length - 1].success('0');
if (ajaxRequests.length !== beforeZero + 1) throw new Error('bare AJAX 0 triggered another request');
if (source.includes('setInterval(function()') || source.includes("on('visibilitychange'")) throw new Error('automatic dashboard request loop remains');

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

// The periodic refresh is unique and pauses both in a hidden tab and after the
// dashboard component has left the DOM.
let refreshes = 0;
dashboardPresent = true;
window.UfscDashboard.refresh_timer = null;
window.UfscDashboard.refreshData = () => { refreshes += 1; };
window.UfscDashboard.startRefreshTimer();
window.UfscDashboard.startRefreshTimer();
if (intervalCallbacks.length !== 1) throw new Error('dashboard refresh interval initialized twice');
document.hidden = true;
intervalCallbacks[0]();
if (refreshes !== 0) throw new Error('dashboard refreshed while page was hidden');
document.hidden = false;
intervalCallbacks[0]();
if (refreshes !== 1) throw new Error('visible dashboard did not refresh');
dashboardPresent = false;
intervalCallbacks[0]();
if (refreshes !== 1) throw new Error('detached dashboard continued to refresh');
console.log('Frontend dashboard DOM runtime initialization OK');
