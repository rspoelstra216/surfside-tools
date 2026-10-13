/* Regression coverage for live-only embedded slides and replay message.
 * Run: node scripts/test-watch-live-slides.js
 */
"use strict";
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const listeners = {};
const timers = [];
let currentStream = null;
let isExpanded = false;

function target() {
    return {
        hidden: false,
        textContent: "",
        style: {
            values: {},
            setProperty(name, value, priority) {
                this.values[name] = { value, priority };
            }
        },
        handlers: {},
        addEventListener(type, fn) { (this.handlers[type] ||= []).push(fn); },
        trigger(type, arg) { for (const fn of this.handlers[type] || []) fn(arg); }
    };
}

const frame = {
    dataset: { liveSlidesSrc: "https://example.workers.dev/viewer" },
    attributes: {},
    hasAttribute(name) { return Object.hasOwn(this.attributes, name); },
    removeAttribute(name) { delete this.attributes[name]; },
    set src(value) { this.attributes.src = value; },
    get src() { return this.attributes.src; }
};
const item = target();
item.hidden = true;
item.classList = { contains: key => key === "is-open" && isExpanded };
const toggle = target();
toggle.getAttribute = key => key === "aria-expanded" ? String(isExpanded) : null;
const heading = target();
const title = target();
const typographySelectors = [
    ".wp-block-accordion-heading",
    ".wp-block-accordion-heading__toggle",
    ".wp-block-accordion-heading__toggle-title"
];
const referenceNodes = Object.fromEntries(typographySelectors.map(selector => {
    const element = target();
    element.computedTypography = {
        "font-family": "Manrope",
        "font-size": "16px",
        "font-weight": "600",
        "line-height": "24px",
        "letter-spacing": "normal",
        "text-transform": "none",
        "color": "rgb(6, 27, 51)"
    };
    return [selector, element];
}));
item.nextElementSibling = {
    querySelector(selector) { return referenceNodes[selector] || null; }
};
item.querySelector = selector => ({
    ".wp-block-accordion-heading": heading,
    ".wp-block-accordion-heading__toggle": toggle,
    ".wp-block-accordion-heading__toggle-title": title,
    "iframe[data-live-slides-src]": frame
})[selector] || null;

const statusText = target();
const replayMessage = target();
const replayHost = { replaceChildren() {} };
const liveHost = { replaceChildren() {} };
const stream = {
    dataset: {
        channel: "surfsidecf",
        playerId: "video",
        streamEndpoint: "/wp-json/surfside/v1/stream",
        nextService: ""
    },
    querySelectorAll() { return []; },
    querySelector(selector) {
        return ({
            "[data-cloudflare-replay]": replayHost,
            "[data-replay-intro]": replayMessage,
            "[data-stream-label]": statusText
        })[selector] || null;
    }
};
const document = {
    hidden: false,
    querySelector(selector) {
        return selector === ".wp-block-accordion-item[data-surfside-live-slides]" ? item : null;
    },
    querySelectorAll(selector) {
        return selector === "[data-surfside-watch-live]" ? [stream] : [];
    },
    getElementById() { return liveHost; },
    createElement(tag) {
        return { tag, style: {}, remove() {}, set src(value) { this._src = value; }, get src() { return this._src; } };
    },
    addEventListener(name, cb) { (listeners[name] ||= []).push(cb); }
};
class Observer {
    constructor(callback) { this.callback = callback; }
    observe() {}
}
const window = {
    setInterval(cb, ms) { timers.push({cb, ms}); return timers.length; },
    setTimeout() { return 1; },
    clearTimeout() {},
    requestAnimationFrame(cb) { cb(); },
    getComputedStyle(element) {
        return {
            getPropertyValue(prop) {
                return element.computedTypography[prop] || "";
            }
        };
    },
    addEventListener() {}
};
const url = "https://customer-8je7me9ta294mn10.cloudflarestream.com/d8fe3de81677a407a70b62f0bb470769/manifest/video.m3u8";
const replay = { live: false, last_service: { hls_url: url } };
const live = { live: true, live_hls_url: url, last_service: null };
currentStream = replay;
const context = {
    document, window, MutationObserver: Observer, fetch: async () => ({
        ok: true, json: async () => currentStream
    }), console, Promise, Date
};
const js = fs.readFileSync(path.join(__dirname, "../assets/js/watch-live-stream.js"), "utf8");
vm.runInNewContext(js, context, {filename: "watch-live-stream.js"});

async function settle() {
    for (let i = 0; i < 10; i++) await Promise.resolve();
}
async function run() {
    for (const listener of listeners.DOMContentLoaded || []) listener();

    for (const element of [heading, toggle, title]) {
        assert.equal(element.style.values["font-size"].value, "16px",
            "Live Slides heading should copy the native accordion font size");
        assert.equal(element.style.values["font-family"].value, "Manrope",
            "Live Slides heading should copy the native accordion font family");
        assert.equal(element.style.values["font-weight"].value, "600",
            "Live Slides heading should copy the native accordion weight");
        assert.equal(element.style.values["font-size"].priority, "important",
            "Theme heading styles must not override the matched size");
    }

    assert.equal(item.hidden, true, "Slides start hidden");
    assert.equal(frame.hasAttribute("src"), false, "Do not embed before live state");
    await settle();
    assert.equal(item.hidden, true, "Replay keeps slides hidden");
    assert.equal(replayMessage.hidden, false, "Replay invitation shown");

    const refresh = timers.find(t => t.ms === 60000);
    assert.ok(refresh, "Livestream poll should run every 60 seconds");

    currentStream = live;
    refresh.cb();
    await settle();
    assert.equal(item.hidden, false, "Going live reveals slides");
    assert.equal(replayMessage.hidden, true, "Going live hides replay invitation");
    assert.equal(frame.hasAttribute("src"), false, "Collapsed accordion must not embed");

    isExpanded = true;
    toggle.trigger("click");
    assert.equal(frame.src, frame.dataset.liveSlidesSrc, "Opening live slides starts iframe");

    document.hidden = true;
    for (const listener of listeners.visibilitychange || []) listener();
    assert.equal(frame.hasAttribute("src"), false, "Hidden tab releases iframe");
    document.hidden = false;
    for (const listener of listeners.visibilitychange || []) listener();
    assert.equal(frame.src, frame.dataset.liveSlidesSrc, "Returning restores expanded viewer");

    currentStream = replay;
    refresh.cb();
    await settle();
    assert.equal(item.hidden, true, "Slides disappear after broadcast");
    assert.equal(frame.hasAttribute("src"), false, "Offline closes embedded viewer");
    assert.equal(replayMessage.hidden, false, "Replay banner returns after broadcast");

    console.log("Watch Live embedded viewer lifecycle checks passed.");
}
run().catch(err => { console.error(err); process.exitCode = 1; });
