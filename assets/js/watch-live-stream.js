(() => {
    "use strict";

    const twitchScript = "https://player.twitch.tv/js/embed/v1.js";
    let twitchPromise;

    function loadTwitch() {
        if (window.Twitch && window.Twitch.Player) {
            return Promise.resolve(window.Twitch);
        }
        if (twitchPromise) {
            return twitchPromise;
        }
        twitchPromise = new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = twitchScript;
            script.async = true;
            script.onload = () => resolve(window.Twitch);
            script.onerror = reject;
            document.head.appendChild(script);
        });
        return twitchPromise;
    }

    function setState(block, state) {
        block.querySelectorAll("[data-stream-state]").forEach((element) => {
            element.hidden = element.getAttribute("data-stream-state") !== state;
        });
        block.dataset.streamStatus = state;
        const replayIntro = block.querySelector("[data-replay-intro]");
        if (replayIntro) replayIntro.hidden = state !== "replay";

        const video = block.querySelector(".surfside-watch-live__offline video");
        if (video) {
            if (state === "offline") {
                const play = video.play();
                if (play && typeof play.catch === "function") {
                    play.catch(() => {});
                }
            } else {
                video.pause();
            }
        }

        const label = block.querySelector("[data-stream-label]");
        if (label) {
            label.textContent = state === "live" ? "Live now" : state === "replay" && !block.dataset.nextService ? "Watch last service" : "Next livestream";
        }

        block.querySelectorAll(".surfside-watch-live__next, [data-stream-countdown]").forEach((element) => {
            element.hidden = state === "live";
        });
    }

    function startCountdown(block) {
        const targetValue = block.dataset.nextService;
        const output = block.querySelector("[data-stream-countdown]");
        if (!targetValue || !output) {
            return;
        }
        const target = new Date(targetValue);

        const update = () => {
            const difference = target.getTime() - Date.now();
            if (difference <= 0) {
                output.textContent = "The service is starting now.";
                return;
            }
            const minutes = Math.floor(difference / 60000);
            const days = Math.floor(minutes / 1440);
            const hours = Math.floor((minutes % 1440) / 60);
            const remainingMinutes = minutes % 60;
            const parts = [];
            if (days) parts.push(days + (days === 1 ? " day" : " days"));
            if (hours || days) parts.push(hours + (hours === 1 ? " hour" : " hours"));
            if (!days) parts.push(remainingMinutes + (remainingMinutes === 1 ? " minute" : " minutes"));
            output.textContent = "Starts in " + parts.join(", ");
        };

        update();
        window.setInterval(update, 60000);
    }

    function cloudflareEmbed(hlsUrl) {
        if (typeof hlsUrl !== "string") return null;
        const match = hlsUrl.match(/^https:\/\/(customer-[a-z0-9-]+\.cloudflarestream\.com)\/([a-f0-9]{32})\/manifest\/video\.m3u8$/i);
        if (!match) return null;
        return "https://" + match[1] + "/" + match[2] + "/iframe?preload=auto";
    }

    function initialize(block) {
        const channel = block.dataset.channel;
        const playerId = block.dataset.playerId;
        const endpoint = block.dataset.streamEndpoint;
        const liveHost = document.getElementById(playerId);
        const replayHost = block.querySelector("[data-cloudflare-replay]");
        let mode = "";
        let twitchPlayer = null;
        let twitchStarting = false;
        let activeFrame = null;

        function clearCloudflare() {
            if (activeFrame) activeFrame.remove();
            activeFrame = null;
        }

        function showCloudflare(state, url) {
            const embed = cloudflareEmbed(url);
            if (!embed) { showFallback(); return; }
            if (mode === state && activeFrame && activeFrame.src === embed) return;
            clearCloudflare();
            liveHost.replaceChildren();
            const host = state === "live" ? liveHost : replayHost;
            const frame = document.createElement("iframe");
            frame.src = embed;
            frame.title = state === "live" ? "Surfside live service" : "Surfside last service";
            frame.allow = "autoplay; fullscreen; picture-in-picture; encrypted-media";
            frame.allowFullscreen = true;
            frame.loading = "eager";
            frame.referrerPolicy = "strict-origin-when-cross-origin";
            frame.style.border = "0";
            host.replaceChildren(frame);
            activeFrame = frame;
            mode = state;
            setState(block, state);
        }

        function showFallback() {
            if (mode === "twitch" || twitchStarting) return;
            clearCloudflare();
            replayHost.replaceChildren();
            mode = "twitch";
            twitchStarting = true;
            setState(block, "loading");
            const timeout = window.setTimeout(() => {
                if (mode === "twitch") setState(block, "offline");
            }, 8000);
            loadTwitch().then(() => {
                if (mode !== "twitch") return;
                twitchPlayer = new window.Twitch.Player(playerId, {
                    channel, width: "100%", height: "100%",
                    autoplay: true, muted: false, parent: [window.location.hostname]
                });
                twitchPlayer.addEventListener(window.Twitch.Player.ONLINE, () => {
                    if (mode === "twitch") { window.clearTimeout(timeout); setState(block, "live"); }
                });
                twitchPlayer.addEventListener(window.Twitch.Player.OFFLINE, () => {
                    if (mode === "twitch") { window.clearTimeout(timeout); setState(block, "offline"); }
                });
            }).catch(() => {
                window.clearTimeout(timeout);
                if (mode === "twitch") setState(block, "offline");
            }).finally(() => { twitchStarting = false; });
        }

        async function refresh() {
            try {
                const response = await fetch(endpoint, {cache: "no-store", credentials: "same-origin"});
                if (!response.ok) throw new Error("Stream status unavailable");
                const data = await response.json();
                if (data.live === true && cloudflareEmbed(data.live_hls_url)) {
                    showCloudflare("live", data.live_hls_url);
                } else if (data.last_service && cloudflareEmbed(data.last_service.hls_url)) {
                    showCloudflare("replay", data.last_service.hls_url);
                } else if (data.live === null) {
                    showFallback();
                } else {
                    clearCloudflare();
                    replayHost.replaceChildren();
                    mode = "offline";
                    setState(block, "offline");
                }
            } catch (error) {
                showFallback();
            }
        }

        startCountdown(block);
        refresh();
        window.setInterval(refresh, 60000);
        document.addEventListener("visibilitychange", () => {
            if (!document.hidden) refresh();
        });
    }

    function initializeLiveSlides() {
        const item = document.querySelector(".wp-block-accordion-item[data-surfside-live-slides]");
        if (!item) return;

        const toggle = item.querySelector(".wp-block-accordion-heading__toggle");
        const frame = item.querySelector("iframe[data-live-slides-src]");
        if (!toggle || !frame) return;

        // Core Accordion controls the panel; only connect the Cloudflare
        // viewer while this item is expanded.
        function syncViewer() {
            const open = item.classList.contains("is-open") ||
                toggle.getAttribute("aria-expanded") === "true";

            if (open && !frame.hasAttribute("src")) {
                frame.src = frame.dataset.liveSlidesSrc;
            } else if (!open && frame.hasAttribute("src")) {
                frame.removeAttribute("src");
            }
        }

        const observer = new MutationObserver(syncViewer);
        observer.observe(item, {
            attributes: true,
            attributeFilter: ["class", "aria-expanded"],
            subtree: true
        });
        toggle.addEventListener("click", () => window.requestAnimationFrame(syncViewer));
        syncViewer();
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll("[data-surfside-watch-live]").forEach(initialize);
        initializeLiveSlides();
    });
})();
