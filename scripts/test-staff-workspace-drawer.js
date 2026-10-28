/* Regression tests for the cross-device Surfside Tools navigation drawer. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '..');
const css = fs.readFileSync(path.join(root, 'assets/css/staff-workspace.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/staff-workspace.js'), 'utf8');

assert.match(css, /Use the same on-demand Tools & Search drawer on desktop/);
assert.match(css, /\.surfside-workspace\{display:block;width:min\(1440px/);
assert.match(css, /\.surfside-workspace-sidebar\{[\s\S]*?visibility:hidden;transform:translateX\(-105%\)/);
assert.match(css, /\.surfside-workspace\.is-open \.surfside-workspace-sidebar\{transform:translateX\(0\);visibility:visible\}/);
assert.match(css, /\.surfside-workspace-menu\{[\s\S]*?display:inline-flex/);
assert.doesNotMatch(js, /if \(!mobile\.matches\) open = false/);

const handlers = Object.create(null);
const classes = new Set();
const contextDocument = {
  readyState: 'complete',
  activeElement: null,
  addEventListener: (event, fn) => { handlers[event] = fn; },
  querySelector: selector => selector === '[data-surfside-workspace]' ? workspace : null,
  querySelectorAll: () => [],
};
function element() {
  const events = Object.create(null);
  const attrs = Object.create(null);
  return {
    events,
    attrs,
    hidden: true,
    addEventListener: (name, fn) => { events[name] = fn; },
    setAttribute: (name, value) => { attrs[name] = value; },
    getAttribute: name => attrs[name] || null,
    getClientRects: () => [1],
    closest: () => null,
    focus() { contextDocument.activeElement = this; },
  };
}
const openButton = element();
const closeButton = element();
const search = element();
const overlay = element();
const empty = element();
const categoryButton = element();
categoryButton.setAttribute('data-ss-breadcrumb-group', 'media');
const categoryHeading = element();
categoryHeading.textContent = 'Messages & Media';
const groupLink = element();
groupLink.textContent = 'Streaming';
groupLink.parentElement = { hidden: false };
const categoryGroup = element();
categoryGroup.setAttribute('data-ss-group-key', 'media');
categoryGroup.querySelector = selector => selector === 'h2' ? categoryHeading : null;
categoryGroup.querySelectorAll = selector => selector === '[data-ss-link]' ? [groupLink] : [];
let scrolledToCategory = false;
categoryGroup.scrollIntoView = () => { scrolledToCategory = true; };
const sidebar = element();
sidebar.querySelectorAll = () => [closeButton, search];
sidebar.contains = node => node === closeButton || node === search;
const elems = {
  '#surfside-workspace-sidebar': sidebar,
  '[data-ss-search]': search,
  '[data-ss-open]': openButton,
  '[data-ss-breadcrumb-group]': categoryButton,
  '[data-ss-empty]': empty,
  '.surfside-workspace-scrim': overlay,
};
const workspace = {
  classList: {
    contains: name => classes.has(name),
    toggle(name, present) { if (present) classes.add(name); else classes.delete(name); },
  },
  querySelector: selector => elems[selector] || null,
  querySelectorAll: selector => selector === '[data-ss-close]' ? [closeButton] : selector === '[data-ss-group]' ? [categoryGroup] : [],
};
const win = {
  location: { hash: '' },
  addEventListener: (event, fn) => { handlers['window:' + event] = fn; },
};
vm.runInNewContext(js, { document: contextDocument, window: win, console });

assert.equal(classes.has('is-open'), false, 'drawer starts collapsed by default');
assert.equal(openButton.attrs['aria-expanded'], 'false');
assert.equal(sidebar.attrs['aria-hidden'], 'true', 'closed menu is removed from screen readers');
assert.equal(overlay.hidden, true);
openButton.events.click();
assert.equal(classes.has('is-open'), true, 'toolbar opens menu');
assert.equal(openButton.attrs['aria-expanded'], 'true');
assert.equal(sidebar.attrs['aria-hidden'], 'false');
assert.equal(overlay.hidden, false);
assert.equal(contextDocument.activeElement, search, 'opening menu focuses search');
closeButton.events.click();
assert.equal(classes.has('is-open'), false, 'close control hides menu');
assert.equal(contextDocument.activeElement, openButton, 'closing restores button focus');

search.value = 'hidden from previous search';
categoryButton.events.click();
assert.equal(classes.has('is-open'), true, 'category breadcrumb opens drawer');
assert.equal(openButton.attrs['aria-expanded'], 'true', 'category opening updates menu state');
assert.equal(search.value, '', 'category breadcrumb clears the old search filter');
assert.equal(categoryGroup.hidden, false, 'selected group items are visible');
assert.equal(contextDocument.activeElement, categoryHeading, 'keyboard focus moves to category heading');
assert.equal(categoryHeading.attrs.tabindex, '-1', 'category heading is programmatically focusable');
assert.equal(scrolledToCategory, true, 'drawer scrolls directly to selected category');
closeButton.events.click();

let prevented = false;
handlers.keydown({
  key: 'k', ctrlKey: true, metaKey: false, altKey: false,
  preventDefault() { prevented = true; },
});
assert.equal(prevented, true);
assert.equal(classes.has('is-open'), true, 'Ctrl+K opens search without a viewport check');
assert.equal(contextDocument.activeElement, search);

contextDocument.activeElement = search;
let wrapped = false;
handlers.keydown({
  key: 'Tab', shiftKey: false,
  preventDefault() { wrapped = true; },
});
assert.equal(wrapped, true, 'Tab wraps focus within open menu');
assert.equal(contextDocument.activeElement, closeButton);
contextDocument.activeElement = closeButton;
let backwards = false;
handlers.keydown({
  key: 'Tab', shiftKey: true,
  preventDefault() { backwards = true; },
});
assert.equal(backwards, true, 'Shift+Tab wraps focus backwards');
assert.equal(contextDocument.activeElement, search);

handlers.keydown({key: 'Escape', preventDefault() {}});
assert.equal(classes.has('is-open'), false, 'Escape closes overlay');

console.log('PASS: Collapsible navigation default, desktop shortcuts, focus and overlay behavior');
