/* Regression test for Watch Live state and read-only slides.
 * Run: node scripts/test-watch-live-slides.js
 */
"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const path = require("node:path");

const callbackMap = new Map();
const timers = [];
const sockets = [];
let expanded = false;
let response = {
    live: true,
    live_hls_url: "https://customer-example.cloudflarestream.com/d8fe3de81677a407a70b62f0bb470769/manifest/video.m3u8",
    last_service: null
};
const lastService = {
    hls_url: "https://customer-example.cloudflarestream.com/d8fe3de81677a407a70b62f0bb470769/manifest/video.m3u8"
};

function makeTarget() {
    return {
        listeners: {},
        addEventListener(event, handler) {
            (this.listeners[event] ||= []).push(handler);
        },
        emit(event, argument) {
            for (const handler of (this.listeners[event] || [])) handler(argument);
        },
        textContent: "",
        hidden: false
    };
}

const item = makeTarget();
item.hidden = true;
item.classList = { contains: name => name === "is-open" && expanded };
const toggle = makeTarget();
toggle.getAttribute = key => key === "aria-expanded" ? String(expanded) : null;
const feed = makeTarget();
feed.dataset = { slidesSocket: "wss://example.workers.dev/api/live" };
const slideText = makeTarget();
const slideTitle = makeTarget();
const slideStatus = makeTarget();
const selectors = {
    ".wp-block-accordion-heading__toggle": toggle,
    "[data-surfside-slides-feed]": feed,
    "[data-slides-text]": slideText,
    "[data-slides-presentation]": slideTitle,
    "[data-slides-status]": slideStatus
};
item.querySelector = selector => selectors[selector] || null;

const replayBanner = makeTarget();
const streamLabel = makeTarget();
const replayHost = { replaceChildren() {} };
const liveHost = { replaceChildren() {} };
const player = {
    dataset: {
        channel: "surfsidecf",
        playerId: "player",
        streamEndpoint: "/wp-json/surfside/v1/stream",
        nextService: ""
    },
    querySelectorAll(selector) {
        return selector === "[data-stream-state]" ? [] : [];
    },
    querySelector(selector) {
        return {
            "[data-cloudflare-replay]": replayHost,
            "[data-replay-intro]": replayBanner,
            "[data-stream-label]": streamLabel
        }[selector] || null;
    }
};

class FakeSocket extends (class {}) {
    static OPEN = 1;
    constructor(url) {
        this.url = url;
        this.readyState = 0;
        this.listeners = {};
        this.closed = false;
        sockets.push(this);
    }
    addEventListener(name, cb) { (this.listeners[name] ||= []).push(cb); }
    emit(name, data) {
        if (name === "open") this.readyState = FakeSocket.OPEN;
        for (const fn of (this.listeners[name] || [])) fn(data);
    }
    close() {
        this.closed = true;
        this.readyState = 3;
        this.emit("close");
    }
}

class FakeObserver {
    constructor(cb) { this.callback = cb; }
    observe() {}
}

const domEvents = {};
const document = {
    hidden: false,
    querySelector(selector) {
        return selector === ".wp-block-accordion-item[data-surfside-live-slides]" ? item : null;
    },
    querySelectorAll(selector) {
        return selector === "[data-surfside-watch-live]" ? [player] : [];
    },
    getElementById() { return liveHost; },
    createElement(tag) {
        return { tagName: tag, remove() {}, style: {} };
    },
    addEventListener(name, cb) { (domEvents[name] ||= []).push(cb); }
};

const window = {
    setInterval(cb, interval) { timers.push({ cb, interval }); return timers.length; },
    clearInterval() {},
    setTimeout() { return 1; },
    clearTimeout() {},
    requestAnimationFrame(cb) { cb(); },
    addEventListener() {}
};

const context = {
    document, window, WebSocket: FakeSocket, MutationObserver: FakeObserver,
    fetch: async () => ({ ok: true, json: async () => response }),
    Promise, console, Date, JSON
};
const file = path.join(__dirname, "../assets/js/watch-live-stream.js");
vm.runInNewContext(fs.readFileSync(file, "utf8"), context, { filename: file });

async function settle() {
    for (let i = 0; i < 8; i++) await Promise.resolve();
}

async function run() {
    for (const fn of domEvents.DOMContentLoaded || []) fn();
    assert.equal(item.hidden, true, "Slides must initially be hidden");
    assert.equal(sockets.length, 0, "No socket before live stream confirmed");

    await settle();
    assert.equal(item.hidden, false, "Live stream should reveal Live Slides accordion");
    assert.equal(replayBanner.hidden, true, "Replay disclaimer must disappear during live");
    assert.equal(sockets.length, 0, "Do not connect slides while accordion closed");

    expanded = true;
    toggle.emit("click");
    assert.equal(sockets.length, 1, "Opening Live Slides should connect once");
    assert.equal(sockets[0].url, "wss://example.workers.dev/api/live");
    sockets[0].emit("open");
    sockets[0].emit("message", {
        data: JSON.stringify({
            type: "snapshot",
            online: true,
            lastSeenAt: new Date().toISOString(),
            staleAfterMs: 60000,
            slide: { text: "Amazing grace", presentation: "Opening Hymn" }
        })
    });
    assert.equal(slideText.textContent, "Amazing grace");
    assert.equal(slideTitle.textContent, "Opening Hymn");

    response = { live: false, last_service: lastService };
    const refresh = timers.find(x => x.interval === 60000);
    assert.ok(refresh, "Stream status should be periodically refreshed");
    refresh.cb();
    await settle();
    assert.equal(item.hidden, true, "Slides must hide during offline replay");
    assert.equal(sockets[0].closed, true, "Socket closes when no longer live");
    assert.equal(replayBanner.hidden, false, "Replay disclaimer appears during replay");

    response = {
        live: true,
        live_hls_url: lastService.hls_url,
        last_service: null
    };
    refresh.cb();
    await settle();
    assert.equal(item.hidden, false, "Slides return during the next live service");
    assert.equal(replayBanner.hidden, true, "Replay disclaimer hides again");

    console.log("Watch Live WebSocket and live-only visibility checks passed.");
}
run().catch(error => { console.error(error); process.exitCode = 1; });
