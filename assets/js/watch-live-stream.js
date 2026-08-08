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

        const video = block.querySelector("video");
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
            label.textContent = state === "live" ? "Live now" : "Next livestream";
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

    function initialize(block) {
        const channel = block.dataset.channel;
        const playerId = block.dataset.playerId;
        let resolved = false;
        let timeout;
        let player;

        const resolveState = (state) => {
            resolved = true;
            window.clearTimeout(timeout);
            setState(block, state);

            if (state === "live" && player) {
                window.requestAnimationFrame(() => {
                    try {
                        player.setMuted(false);
                        player.play();
                    } catch (error) {
                        // The visible Twitch controls remain available if a browser blocks autoplay.
                    }
                });
            }
        };

        startCountdown(block);

        timeout = window.setTimeout(() => {
            if (!resolved) resolveState("offline");
        }, 8000);

        loadTwitch()
            .then(() => {
                player = new window.Twitch.Player(playerId, {
                    channel,
                    width: "100%",
                    height: "100%",
                    autoplay: true,
                    muted: false,
                    parent: [window.location.hostname]
                });

                player.addEventListener(window.Twitch.Player.ONLINE, () => resolveState("live"));
                player.addEventListener(window.Twitch.Player.OFFLINE, () => resolveState("offline"));
            })
            .catch(() => resolveState("offline"));
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll("[data-surfside-watch-live]").forEach(initialize);
    });
})();
