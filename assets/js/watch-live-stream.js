(() => {
    "use strict";

    const twitchScript = "https://player.twitch.tv/js/embed/v1.js";
    let twitchPromise;
    let liveSlidesController = null;

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
        if (liveSlidesController) liveSlidesController.setStreamLive(state === "live");

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
        if (!item) return null;

        const toggle = item.querySelector(".wp-block-accordion-heading__toggle");
        const feed = item.querySelector("[data-surfside-slides-feed]");
        const text = item.querySelector("[data-slides-text]");
        const title = item.querySelector("[data-slides-presentation]");
        const status = item.querySelector("[data-slides-status]");
        if (!toggle || !feed || !text || !title || !status) return null;

        const socketUrl = feed.dataset.slidesSocket;
        let streamLive = false;
        let connection = null;
        let reconnectTimer = null;
        let reconnectDelay = 1000;
        let publisherOnline = false;
        let lastSeenAt = 0;
        let staleAfterMs = 60000;
        let latestSlide = null;

        function expanded() {
            return item.classList.contains("is-open") ||
                toggle.getAttribute("aria-expanded") === "true";
        }

        function shouldConnect() {
            return streamLive && expanded() && !document.hidden;
        }

        function updateSlide() {
            const fresh = publisherOnline && lastSeenAt > 0 &&
                Date.now() - lastSeenAt < staleAfterMs;

            if (!fresh) {
                status.textContent = "Waiting for the live presentation";
                text.textContent = "Slides will appear here when the presentation begins.";
                title.textContent = "";
                title.hidden = true;
                return;
            }

            status.textContent = "Presentation connected";
            if (!latestSlide || typeof latestSlide.text !== "string") {
                text.textContent = "Waiting for the next slide…";
                title.textContent = "";
                title.hidden = true;
                return;
            }

            // Never use innerHTML for user-created presentation material.
            text.textContent = latestSlide.text || "(Blank slide)";
            const presentation = typeof latestSlide.presentation === "string"
                ? latestSlide.presentation : "";
            title.textContent = presentation;
            title.hidden = !presentation;
        }

        function stop() {
            if (reconnectTimer !== null) {
                window.clearTimeout(reconnectTimer);
                reconnectTimer = null;
            }
            if (connection) {
                const old = connection;
                connection = null;
                old.close();
            }
            publisherOnline = false;
            lastSeenAt = 0;
            latestSlide = null;
            reconnectDelay = 1000;
        }

        function retry() {
            if (!shouldConnect() || reconnectTimer !== null) return;
            reconnectTimer = window.setTimeout(() => {
                reconnectTimer = null;
                connect();
            }, reconnectDelay);
            reconnectDelay = Math.min(reconnectDelay * 2, 20000);
        }

        function connect() {
            if (!shouldConnect() || connection) return;
            status.textContent = "Connecting to presentation…";
            let ws;
            try {
                ws = new WebSocket(socketUrl);
            } catch (error) {
                status.textContent = "Connection unavailable — retrying";
                retry();
                return;
            }
            connection = ws;
            ws.addEventListener("open", () => {
                if (connection !== ws) return;
                reconnectDelay = 1000;
            });
            ws.addEventListener("message", (event) => {
                if (connection !== ws) return;
                let packet;
                try {
                    packet = JSON.parse(event.data);
                } catch (error) {
                    return;
                }
                if (!packet || typeof packet !== "object") return;
                if (packet.type === "snapshot") {
                    publisherOnline = packet.online === true;
                    latestSlide = packet.slide;
                } else if (packet.type === "heartbeat") {
                    publisherOnline = true;
                } else {
                    return;
                }
                lastSeenAt = packet.lastSeenAt ? Date.parse(packet.lastSeenAt) : 0;
                if (!Number.isFinite(lastSeenAt)) lastSeenAt = 0;
                const ttl = Number(packet.staleAfterMs);
                staleAfterMs = Number.isFinite(ttl) && ttl > 0 ? ttl : 60000;
                updateSlide();
            });
            ws.addEventListener("close", () => {
                if (connection !== ws) return;
                connection = null;
                status.textContent = "Connection interrupted — retrying";
                retry();
            });
            ws.addEventListener("error", () => {
                if (connection === ws) ws.close();
            });
        }

        function sync() {
            if (shouldConnect()) {
                connect();
            } else {
                stop();
                updateSlide();
            }
        }

        const observer = new MutationObserver(sync);
        observer.observe(toggle, { attributes: true, attributeFilter: ["aria-expanded"] });
        observer.observe(item, { attributes: true, attributeFilter: ["class"] });
        toggle.addEventListener("click", () => window.requestAnimationFrame(sync));
        document.addEventListener("visibilitychange", sync);
        window.addEventListener("pagehide", stop);
        window.setInterval(() => {
            if (shouldConnect() && connection) updateSlide();
        }, 5000);

        // No flash of Live Slides while checking stream status.
        item.hidden = true;
        sync();
        return {
            setStreamLive(live) {
                const next = live === true;
                if (streamLive === next) return;
                streamLive = next;
                item.hidden = !streamLive;
                sync();
            }
        };
    }

    document.addEventListener("DOMContentLoaded", () => {
        liveSlidesController = initializeLiveSlides();
        document.querySelectorAll("[data-surfside-watch-live]").forEach(initialize);
    });
})();
