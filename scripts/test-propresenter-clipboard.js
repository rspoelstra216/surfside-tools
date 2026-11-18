/**
 * Test the browser-side clipboard paths without a browser or external APIs.
 * Run: node scripts/test-propresenter-clipboard.js
 */
'use strict';
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(
    path.join(__dirname, '..', 'assets/js/propresenter-announcements.js'), 'utf8');

function setup(withHtmlSupport) {
    let listener;
    const events = { html: [], plain: [], notices: [] };
    const article = {
        querySelector(selector) {
            if (selector === '[data-propresenter-title]') return { value: 'Christmas <Caroling> & Friends' };
            if (selector === '[data-propresenter-lines]') return { value: 'Monday, December 7\nLenox Assisted Living\nSign up at Information Table' };
            throw new Error('Unexpected input ' + selector);
        }
    };
    const slideContainer = { children: [article] };
    const status = {
        textContent: '',
        classList: { toggle() {} }
    };
    const controls = [
        { dataset: { propresenterAction: 'copy-formatted' }, disabled: false },
        { dataset: { propresenterAction: 'copy-one' }, disabled: false },
        { dataset: { propresenterAction: 'copy-all' }, disabled: false }
    ];
    const root = {
        dataset: { nonce: 'fake', ajaxUrl: '/wp-admin/admin-ajax.php', sourceHash: 'fixture' },
        contains() { return true; },
        querySelector(selector) {
            if (selector === '#surfside-propresenter-status') return status;
            if (selector === '#surfside-propresenter-slides') return slideContainer;
            if (selector === '.surfside-propresenter-actions') return { querySelectorAll() { return controls; } };
            if (selector === '[data-propresenter-slide]') return article;
            return null;
        },
        querySelectorAll(selector) {
            if (selector === '[data-propresenter-slide]') return [article];
            if (selector === '[data-propresenter-action]') return controls;
            throw new Error('Unexpected selector ' + selector);
        },
        addEventListener(name, callback) {
            assert.equal(name, 'click');
            listener = callback;
        }
    };
    const window = {
        isSecureContext: true,
        ClipboardItem: withHtmlSupport
            ? class FakeClipboardItem { constructor(data) { this.data = data; } }
            : undefined
    };
    const navigator = { clipboard: {
        async write(items) { events.html.push(...items); },
        async writeText(text) { events.plain.push(text); }
    } };
    vm.runInNewContext(source, {
        document: { getElementById() { return root; } },
        window, navigator, Blob
    }, { filename: 'propresenter-announcements.js' });

    async function click(action) {
        const button = controls.find(item => item.dataset.propresenterAction === action);
        button.disabled = false;
        await listener({
            target: {
                closest() {
                    return Object.assign(button, {
                        closest(selector) {
                            assert.equal(selector, '[data-propresenter-slide]');
                            return article;
                        }
                    });
                }
            }
        });
        events.notices.push(status.textContent);
    }
    return { click, events };
}

(async function () {
    const first = setup(true);
    await first.click('copy-formatted');
    assert.equal(first.events.plain.length, 0,
        'Rich clipboard must not silently downgrade to plain text');
    assert.equal(first.events.html.length, 1);
    const item = first.events.html[0].data;
    assert.ok(item['text/html']);
    assert.ok(item['text/plain']);
    const html = await item['text/html'].text();
    const text = await item['text/plain'].text();
    assert.match(html, /font-weight:700[^>]*>Christmas &lt;Caroling&gt; &amp; Friends<\/span>/);
    assert.match(html, /font-weight:400[^>]*>Monday, December 7<\/span>/);
    assert.match(html, /font-weight:400[^>]*>Sign up at Information Table<\/span>/);
    assert.equal((html.match(/font-weight:700/g) || []).length, 1,
        'Only the title is bold');
    assert.doesNotMatch(html, /---|font-size|background|<script|<style/i,
        'Experimental rich copy must not include separators or overriding slide styles');
    assert.equal(text, 'Christmas <Caroling> & Friends\nMonday, December 7\nLenox Assisted Living\nSign up at Information Table');
    assert.match(first.events.notices[0], /regular Paste/);

    await first.click('copy-one');
    assert.equal(first.events.plain.at(-1), text, 'Old plain Copy Slide still works');

    await first.click('copy-all');
    assert.equal(first.events.plain.at(-1), text,
        'A one-slide Copy All contains no additional separator');

    const unavailable = setup(false);
    await unavailable.click('copy-formatted');
    assert.equal(unavailable.events.plain.length, 0,
        'Rich clipboard failure must not fall back without warning');
    assert.equal(unavailable.events.html.length, 0);
    assert.match(unavailable.events.notices[0], /unavailable/);

    console.log('ProPresenter rich clipboard tests passed.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
