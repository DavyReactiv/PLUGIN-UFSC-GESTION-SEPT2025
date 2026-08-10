#!/usr/bin/env node
'use strict';
// Minimal cascade runtime for the exact rendered portal components. It parses
// the shipped stylesheet in source order and applies matching class/tag rules.
const fs=require('fs'); const css=fs.readFileSync(__dirname+'/../assets/css/ufsc-front.css','utf8').replace(/\/\*[\s\S]*?\*\//g,'');
const rules=[]; for(const match of css.matchAll(/([^{}@]+)\{([^{}]+)\}/g)){for(const selector of match[1].split(',')){const declarations={};for(const pair of match[2].split(';')){const i=pair.indexOf(':');if(i>0)declarations[pair.slice(0,i).trim()]=pair.slice(i+1).trim();}rules.push({selector:selector.trim(),declarations,order:rules.length,specificity:(selector.match(/\./g)||[]).length*10+(selector.match(/\b(?:button|input|strong|small|a)\b/g)||[]).length});}}
function computed(element){const winners={};for(const rule of rules){if(!element.matches(rule.selector))continue;for(const [key,value] of Object.entries(rule.declarations)){const old=winners[key];if(!old||rule.specificity>old.specificity||(rule.specificity===old.specificity&&rule.order>old.order))winners[key]={value,specificity:rule.specificity,order:rule.order};}}return Object.fromEntries(Object.entries(winners).map(([k,v])=>[k,v.value]));}
function portalElement(classes,tag='button'){const own=new Set(classes.split(/\s+/));return {matches(selector){if(selector.includes(':')||selector.includes('['))return false; if(!selector.includes('.ufsc-club-portal')||!selector.includes('.ufsc-club-account'))return false; const required=[...selector.matchAll(/\.([\w-]+)/g)].map(m=>m[1]).filter(c=>!['ufsc-club-portal','ufsc-club-account'].includes(c)); if(!required.every(c=>own.has(c)))return false; const last=selector.trim().split(/\s+/).pop(); return !/^(button|a|input)/.test(last)||last.startsWith(tag);}};}
const primary=computed(portalElement('ufsc-btn ufsc-btn-primary'));
if(primary.color!=='#fff'||primary.background!=='#0b4f86'||primary['-webkit-text-fill-color']!=='#fff'||primary.visibility!=='visible'||primary.opacity!=='1')throw new Error('primary action is not visibly styled at rest: '+JSON.stringify(primary));
const secondary=computed(portalElement('ufsc-btn ufsc-btn-secondary','a'));
if(secondary.color!=='#173b67'||secondary.background!=='#f8fafc'||secondary['-webkit-text-fill-color']!=='#173b67')throw new Error('secondary action is not visibly styled at rest: '+JSON.stringify(secondary));
if(!css.includes('background: #e5e7eb; border-color: #6b7280; color: #374151; -webkit-text-fill-color: #374151; cursor: not-allowed; opacity: 1'))throw new Error('disabled action is illegible');
const source=fs.readFileSync(__dirname+'/../includes/frontend/class-frontend-shortcodes.php','utf8');
for(const text of ['Attestation UFSC','Génération en cours','Le document est en préparation. Aucune action n’est nécessaire.'])if(!source.includes(text))throw new Error('missing attestation content: '+text);
console.log('Portal computed resting button and attestation DOM/style contract OK');
