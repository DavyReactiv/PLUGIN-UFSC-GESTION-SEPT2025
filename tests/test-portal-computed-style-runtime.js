#!/usr/bin/env node
'use strict';

// Minimal cascade runtime for the exact rendered portal components. It parses
// the shipped stylesheet in source order, including bounded :is(...) selectors,
// and resolves the custom properties used by the canonical color contract.
const fs = require('fs');
const css = fs
    .readFileSync(__dirname + '/../assets/css/ufsc-front.css', 'utf8')
    .replace(/\/\*[\s\S]*?\*\//g, '');

function splitSelectorList(value) {
    const selectors = [];
    let current = '';
    let parentheses = 0;
    let brackets = 0;

    for (const character of value) {
        if (character === '(') parentheses++;
        if (character === ')') parentheses--;
        if (character === '[') brackets++;
        if (character === ']') brackets--;

        if (character === ',' && parentheses === 0 && brackets === 0) {
            selectors.push(current.trim());
            current = '';
        } else {
            current += character;
        }
    }

    if (current.trim()) selectors.push(current.trim());
    return selectors;
}

function functionalPseudos(selector) {
    const groups = [];
    const pattern = /:(is|not)\(/g;
    let match;

    while ((match = pattern.exec(selector))) {
        let depth = 1;
        let end = pattern.lastIndex;
        while (end < selector.length && depth > 0) {
            if (selector[end] === '(') depth++;
            if (selector[end] === ')') depth--;
            end++;
        }
        groups.push({
            type: match[1],
            start: match.index,
            end,
            value: selector.slice(pattern.lastIndex, end - 1),
        });
        pattern.lastIndex = end;
    }

    return groups;
}

function selectorSpecificity(selector) {
    let remainder = selector;
    let functional = 0;
    const groups = functionalPseudos(selector).reverse();

    for (const group of groups) {
        const optionSpecificities = splitSelectorList(group.value).map(selectorSpecificity);
        functional += optionSpecificities.length ? Math.max(...optionSpecificities) : 0;
        remainder = remainder.slice(0, group.start) + remainder.slice(group.end);
    }

    const ids = (remainder.match(/#[\w-]+/g) || []).length;
    const classes = (remainder.match(/\.[\w-]+/g) || []).length;
    const attributes = (remainder.match(/\[[^\]]+\]/g) || []).length;
    const pseudos = (remainder.match(/:(?!:)[\w-]+/g) || []).length;
    const types = (remainder.match(/(^|[\s>+~])(?:a|button|input|span|strong|small)(?=[.#:\[\s>+~]|$)/g) || []).length;

    return functional + ids * 100 + (classes + attributes + pseudos) * 10 + types;
}

const rules = [];
for (const match of css.matchAll(/([^{}@]+)\{([^{}]+)\}/g)) {
    for (const selector of splitSelectorList(match[1])) {
        const declarations = {};
        for (const pair of match[2].split(';')) {
            const separator = pair.indexOf(':');
            if (separator > 0) {
                const rawValue = pair.slice(separator + 1).trim();
                declarations[pair.slice(0, separator).trim()] = {
                    value: rawValue.replace(/\s*!important\s*$/i, '').trim(),
                    important: /\s*!important\s*$/i.test(rawValue),
                };
            }
        }
        rules.push({
            selector,
            declarations,
            order: rules.length,
            specificity: selectorSpecificity(selector),
        });
    }
}

function attributeMatches(element, expression) {
    const match = expression.match(/^([\w-]+)(?:([*]?=)["']?([^"']*)["']?)?$/);
    if (!match) return false;
    const [, name, operator, expected] = match;
    if (!Object.prototype.hasOwnProperty.call(element.attributes, name)) return false;
    if (!operator) return true;
    const actual = String(element.attributes[name]);
    return operator === '*=' ? actual.includes(expected) : actual === expected;
}

function simpleSelectorMatches(element, selector) {
    const requiredIds = [...selector.matchAll(/#([\w-]+)/g)].map(match => match[1]);
    if (!requiredIds.every(id => id === element.id)) return false;

    const requiredClasses = [...selector.matchAll(/\.([\w-]+)/g)].map(match => match[1]);
    if (!requiredClasses.every(className => element.classes.has(className) || element.ancestors.has(className))) {
        return false;
    }

    for (const match of selector.matchAll(/\[([^\]]+)\]/g)) {
        if (!attributeMatches(element, match[1])) return false;
    }

    for (const state of ['hover', 'active', 'focus-visible', 'disabled']) {
        if (selector.includes(':' + state) && !element.states.has(state)) return false;
    }

    const lastCompound = selector.trim().split(/\s+|>|\+|~/).filter(Boolean).pop() || '';
    const tag = lastCompound.match(/^(a|button|input|span|strong|small)(?=[.#:\[]|$)/);
    return !tag || tag[1] === element.tag;
}

function selectorMatches(element, selector) {
    let remainder = selector;
    const groups = functionalPseudos(selector).reverse();

    for (const group of groups) {
        const matches = splitSelectorList(group.value).some(option => simpleSelectorMatches(element, option));
        if ((group.type === 'is' && !matches) || (group.type === 'not' && matches)) return false;
        remainder = remainder.slice(0, group.start) + remainder.slice(group.end);
    }

    return simpleSelectorMatches(element, remainder);
}

function resolveValue(value, properties, seen = new Set()) {
    return value.replace(/var\(\s*(--[\w-]+)(?:\s*,\s*([^\)]+))?\s*\)/g, (match, name, fallback) => {
        if (seen.has(name)) return fallback ? fallback.trim() : match;
        const resolved = properties[name];
        if (typeof resolved === 'undefined') return fallback ? fallback.trim() : match;
        const nextSeen = new Set(seen);
        nextSeen.add(name);
        return resolveValue(resolved, properties, nextSeen);
    });
}

function computed(element) {
    const winners = {};
    for (const rule of rules) {
        if (!selectorMatches(element, rule.selector)) continue;
        for (const [key, declaration] of Object.entries(rule.declarations)) {
            const previous = winners[key];
            if (
                !previous ||
                (declaration.important && !previous.important) ||
                (declaration.important === previous.important && rule.specificity > previous.specificity) ||
                (
                    declaration.important === previous.important &&
                    rule.specificity === previous.specificity &&
                    rule.order > previous.order
                )
            ) {
                winners[key] = {
                    value: declaration.value,
                    important: declaration.important,
                    specificity: rule.specificity,
                    order: rule.order,
                };
            }
        }
    }

    const properties = Object.fromEntries(Object.entries(winners).map(([key, winner]) => [key, winner.value]));
    return Object.fromEntries(
        Object.entries(properties).map(([key, value]) => [key, resolveValue(value, properties)])
    );
}

function portalElement(classes, tag = 'button', options = {}) {
    return {
        id: options.id || '',
        tag,
        classes: new Set(classes.split(/\s+/).filter(Boolean)),
        ancestors: new Set(options.ancestors || ['ufsc-club-portal', 'ufsc-club-account']),
        attributes: { ...(options.attributes || {}) },
        states: new Set(options.states || []),
    };
}

function assertStyles(actual, expected, message) {
    for (const [property, value] of Object.entries(expected)) {
        if (actual[property] !== value) {
            throw new Error(message + ': expected ' + property + '=' + value + ', received ' + JSON.stringify(actual));
        }
    }
}

const primaryRest = computed(portalElement('ufsc-btn ufsc-btn-primary'));
assertStyles(primaryRest, {
    background: '#0b4f86',
    color: '#fff',
    '-webkit-text-fill-color': '#fff',
    'min-height': '44px',
    opacity: '1',
    visibility: 'visible',
}, 'primary action is not visibly styled at rest');

const secondaryRest = computed(portalElement('ufsc-btn ufsc-btn-secondary', 'a'));
assertStyles(secondaryRest, {
    background: '#f8fafc',
    color: '#0b4f86',
    '-webkit-text-fill-color': '#0b4f86',
    'min-height': '44px',
    opacity: '1',
    visibility: 'visible',
}, 'secondary action is not visibly styled at rest');

const primaryHover = computed(portalElement('ufsc-btn ufsc-btn-primary', 'button', { states: ['hover'] }));
assertStyles(primaryHover, {
    background: '#073b66',
    'border-color': '#073b66',
    color: '#fff',
    '-webkit-text-fill-color': '#fff',
    opacity: '1',
    visibility: 'visible',
}, 'primary hover state is not visibly styled');

const primaryFocus = computed(portalElement('ufsc-btn ufsc-btn-primary', 'button', { states: ['focus-visible'] }));
assertStyles(primaryFocus, {
    background: '#0b4f86',
    color: '#fff',
    '-webkit-text-fill-color': '#fff',
    outline: '3px solid #073b66',
    'outline-offset': '3px',
    'box-shadow': '0 0 0 3px #fff',
    opacity: '1',
    visibility: 'visible',
}, 'primary keyboard-focus state is not visibly styled');

const primaryDisabled = computed(portalElement('ufsc-btn ufsc-btn-primary', 'button', {
    attributes: { disabled: '' },
    states: ['disabled'],
}));
assertStyles(primaryDisabled, {
    background: '#e5e7eb',
    'border-color': '#6b7280',
    color: '#374151',
    '-webkit-text-fill-color': '#374151',
    cursor: 'not-allowed',
    opacity: '1',
    visibility: 'visible',
}, 'disabled primary action is not legible');

const source = fs.readFileSync(__dirname + '/../includes/frontend/class-frontend-shortcodes.php', 'utf8');
for (const text of ['Attestation UFSC', 'Génération en cours', 'Le document est en préparation. Aucune action n’est nécessaire.']) {
    if (!source.includes(text)) throw new Error('missing attestation content: ' + text);
}

console.log('Portal computed rest, hover, focus, disabled and attestation style contract OK');
