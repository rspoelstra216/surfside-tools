/**
 * Regression: Copy All is plain text for manual slide breaks in Reflow.
 * Run: node scripts/test-propresenter-clipboard.js
 */
'use strict';
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(
    path.join(__dirname, '..', 'assets/js/propresenter-announcements.js'), 'utf8');
const phpSource = fs.readFileSync(
    path.join(__dirname, '..', 'includes/propresenter-announcements.php'), 'utf8');

assert.doesNotMatch(source, /copy-formatted|copyFormattedSlide|formattedSlideHtml|ClipboardItem/,
    'The experimental rich clipboard feature was removed');
assert.doesNotMatch(phpSource, /copy-formatted|Copy formatted \(test\)/,
    'The rendered staff page no longer displays a formatted-copy control');

const announcements = [
    {
        title: 'CHRISTMAS CAROLING',
        lines: ['Monday, December 7 • 5:30 PM', 'Lenox Assisted Living • Merritt Island', 'Sign up at the Information Table']
    },
    {
        title: 'SENIOR LUNCHEON',
        lines: ['Friday, November 20 • 11:30 AM', 'Fellowship Hall']
    }
];
const articles = announcements.map(function (slide) {
    return {
        querySelector(selector) {
            if (selector === '[data-propresenter-title]') return { value: slide.title };
            if (selector === '[data-propresenter-lines]') return { value: slide.lines.join('\n') };
            throw new Error('Unexpected field: ' + selector);
        }
    };
});
const controls = ['copy-one', 'copy-all'].map(function (action) {
    return { dataset: { propresenterAction: action }, disabled: false };
});
const events = { plain: [], messages: [] };
let onClick;
const status = { textContent: '', classList: { toggle() {} } };
const container = { children: articles };
const root = {
    dataset: { nonce: 'fake', ajaxUrl: '/wp-admin/admin-ajax.php', sourceHash: 'fixture' },
    contains() { return true; },
    querySelector(selector) {
        if (selector === '#surfside-propresenter-status') return status;
        if (selector === '#surfside-propresenter-slides') return container;
        if (selector === '.surfside-propresenter-actions') {
            return { querySelectorAll() { return controls; } };
        }
        if (selector === '[data-propresenter-slide]') return articles[0];
        return null;
    },
    querySelectorAll(selector) {
        if (selector === '[data-propresenter-slide]') return articles;
        if (selector === '[data-propresenter-action]') return controls;
        throw new Error('Unexpected selector: ' + selector);
    },
    addEventListener(event, callback) {
        assert.equal(event, 'click');
        onClick = callback;
    }
};
vm.runInNewContext(source, {
    document: { getElementById() { return root; } },
    window: { isSecureContext: true },
    navigator: {
        clipboard: {
            async writeText(value) { events.plain.push(value); },
            async write() { throw Error('Rich clipboard must never be used'); }
        }
    }
});

async function click(action, article = articles[0]) {
    const button = controls.find(control => control.dataset.propresenterAction === action);
    assert.ok(button, 'Expected control exists: ' + action);
    button.disabled = false;
    await onClick({
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
    events.messages.push(status.textContent);
}

(async function () {
    await click('copy-all');
    const actual = events.plain.at(-1);
    const expected = [
        'CHRISTMAS CAROLING',
        'Monday, December 7 • 5:30 PM',
        'Lenox Assisted Living • Merritt Island',
        'Sign up at the Information Table',
        'SENIOR LUNCHEON',
        'Friday, November 20 • 11:30 AM',
        'Fellowship Hall'
    ].join('\n');

    assert.equal(actual, expected,
        'Copy All retains title/body lines and uses only one newline between announcements');
    assert.doesNotMatch(actual, /---|\n\n/,
        'Copy All adds no horizontal dividers or blank lines');
    assert.ok(!actual.endsWith('\n'),
        'Copy All adds no trailing line break');
    assert.match(events.messages.at(-1), /Reflow/,
        'Success message explains the manual Reflow slide breaks');

    await click('copy-one', articles[1]);
    assert.equal(events.plain.at(-1), [
        'SENIOR LUNCHEON',
        'Friday, November 20 • 11:30 AM',
        'Fellowship Hall'
    ].join('\n'), 'Single slide Copy keeps its existing behavior');

    console.log('ProPresenter Reflow clipboard tests passed.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
