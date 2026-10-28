/* Deterministic browser-side tests for image rotation and MP4 fallback. */
"use strict";
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const callbacks = {timeouts: new Map(), intervals: new Map(), next: 0};
function timer(cb, ms, type) {
    const id = ++callbacks.next;
    callbacks[type].set(id, {cb, ms});
    return id;
}
const window = {
    location: {href: "https://surfsidefellowship.org/watch-live/"},
    setTimeout: (cb, ms) => timer(cb, ms, "timeouts"),
    clearTimeout: id => callbacks.timeouts.delete(id),
    setInterval: (cb, ms) => timer(cb, ms, "intervals"),
    clearInterval: id => callbacks.intervals.delete(id)
};
const document = {hidden: false};
const handlers = {};
const image = {
    src: "",
    alt: "",
    addEventListener: (event, fn) => { handlers[event] = fn; }
};
const stage = {hidden: true, querySelector: q => q === "[data-announcement-image]" ? image : null};
const message = {hidden: false};
const badge = {hidden: true};
const video = {
    hidden: false, plays: 0, pauses: 0,
    play() { this.plays++; return Promise.resolve(); },
    pause() { this.pauses++; }
};
const urlA = "https://surfsidefellowship.org/uploads/one.jpg";
const urlB = "https://surfsidefellowship.org/uploads/two.jpg";
let published = {slides: [
    {id: 1, image_url: urlA, duration_seconds: 3},
    {id: 2, image_url: urlB, duration_seconds: 5}
]};
let failingFetch = false;
let pollCount = 0;
async function fetchApi() {
    pollCount++;
    if (failingFetch) throw new Error("offline");
    return {ok: true, json: async () => published};
}
const block = {
    dataset: {
        announcementEndpoint: "/wp-json/surfside/v1/announcement-slideshow",
        announcementSlides: JSON.stringify(published.slides)
    },
    querySelector(selector) {
        return ({
            "[data-announcement-slideshow]": stage,
            ".surfside-watch-live__offline video": video,
            "[data-announcement-fallback-message]": message,
            "[data-announcement-badge]": badge
        })[selector] || null;
    }
};
const source = fs.readFileSync(path.join(__dirname, "../assets/js/watch-live-announcements.js"), "utf8");
vm.runInNewContext(source, {window, document, URL, fetch: fetchApi, console, Set, JSON, Number, Promise});

function pendingTimeout() { return [...callbacks.timeouts.values()][0]; }
async function settle() { for (let i = 0; i < 12; i++) await Promise.resolve(); }
async function tick() {
    const [id, entry] = [...callbacks.timeouts.entries()][0] || [];
    assert.ok(entry, "A slide change must be scheduled");
    callbacks.timeouts.delete(id);
    entry.cb();
    await settle();
}
async function run() {
    const controller = window.SurfsideOfflineAnnouncements.create(block);
    assert.ok(controller, "Website initializes a standalone announcement controller");
    assert.equal(callbacks.intervals.size, 1, "One periodic refresh registered");
    assert.equal([...callbacks.intervals.values()][0].ms, 60000, "Refresh once per minute");
    controller.setStreamState("live");
    assert.equal(stage.hidden, true, "Announcements cannot cover a live service");
    assert.equal(video.plays, 0, "MP4 cannot autoplay behind live service");

    controller.setStreamState("offline");
    await settle();
    assert.equal(stage.hidden, false, "Offline state displays published slideshow");
    assert.equal(image.src, urlA, "First slide starts immediately");
    assert.equal(pendingTimeout().ms, 3000, "First slide respects its duration");
    assert.equal(video.hidden, true, "The legacy MP4 is hidden when images are available");
    assert.equal(badge.hidden, false, "Announcement label shows with slideshow");
    assert.equal(video.plays, 0, "Legacy video isn't played behind slideshow");

    controller.setStreamState("offline");
    assert.equal(callbacks.timeouts.size, 1, "Repeated status polls do not duplicate playback timers");
    await tick();
    assert.equal(image.src, urlB, "Slides rotate in published order");
    assert.equal(pendingTimeout().ms, 5000, "Second slide respects its own duration");
    await tick();
    assert.equal(image.src, urlA, "Playlist loops continuously");

    published = {slides: [{id: 3, image_url: "https://surfsidefellowship.org/uploads/updated.jpg", duration_seconds: 8}]};
    const refresh = [...callbacks.intervals.values()][0];
    refresh.cb();
    await settle();
    assert.equal(image.src, published.slides[0].image_url, "New published images replace old slideshow on refresh");
    assert.equal(pendingTimeout().ms, 8000, "Updated duration used without page reload");

    failingFetch = true;
    refresh.cb();
    await settle();
    assert.equal(image.src, published.slides[0].image_url, "Network failure keeps the last known playlist");
    failingFetch = false;

    document.hidden = true;
    controller.syncVisibility();
    assert.equal(callbacks.timeouts.size, 0, "Hidden browser tab stops rotation");
    document.hidden = false;
    controller.syncVisibility();
    await settle();
    assert.ok(pendingTimeout(), "Returning to page resumes rotation");

    // If images cannot load, the existing video is available.
    handlers.error();
    assert.equal(stage.hidden, true, "Broken-only image playlist is hidden");
    assert.equal(video.hidden, false, "Broken images reveal the MP4 fallback");
    assert.ok(video.plays > 0, "MP4 fallback starts on broken images");

    published = {slides: [{id: 4, image_url: urlA, duration_seconds: 6}]};
    refresh.cb();
    await settle();
    assert.equal(stage.hidden, false, "A fresh valid playlist recovers from image failure");
    assert.equal(video.hidden, true, "MP4 stops after slideshow recovery");

    controller.setStreamState("replay");
    assert.equal(stage.hidden, true, "Sermon replay takes priority over announcements");
    assert.equal(callbacks.timeouts.size, 0, "Replay cancels offline slideshow timer");
    assert.ok(video.pauses > 0, "MP4 paused outside offline state");

    controller.setStreamState("offline");
    await settle();
    published = {slides: []};
    refresh.cb();
    await settle();
    assert.equal(stage.hidden, true, "An empty published list deactivates slideshow");
    assert.equal(video.hidden, false, "An empty list activates the MP4 fallback");

    controller.stop();
    assert.equal(callbacks.intervals.size, 0, "Navigation away stops polling");
    console.log("PASS: Website announcement rotation, live/replay priority, refresh, broken images and MP4 fallback");
}
run().catch(error => { console.error(error); process.exitCode = 1; });
