/* Website-only offline announcement playback.
 * The playlist comes from the same published option used by /surfside/v1/app.
 * Live and replay decisions remain owned by watch-live-stream.js.
 */
(() => {
    "use strict";

    function validSlides(candidate) {
        if (!Array.isArray(candidate)) return [];
        const seen = new Set();
        const result = [];
        candidate.slice(0, 50).forEach((slide) => {
            if (!slide || typeof slide.image_url !== "string") return;
            let uri;
            try { uri = new URL(slide.image_url, window.location.href); } catch (_) { return; }
            if (uri.protocol !== "https:" && uri.protocol !== "http:") return;
            const id = Number(slide.id);
            if (!Number.isInteger(id) || id <= 0 || seen.has(id)) return;
            seen.add(id);
            const seconds = Number(slide.duration_seconds);
            result.push({
                id,
                image_url: uri.href,
                duration_seconds: Number.isFinite(seconds) ? Math.max(3, Math.min(60, seconds)) : 8
            });
        });
        return result;
    }

    function create(block) {
        const stage = block.querySelector("[data-announcement-slideshow]");
        const image = stage && stage.querySelector("[data-announcement-image]");
        if (!stage || !image) return null;

        const video = block.querySelector(".surfside-watch-live__offline video");
        const offlineMessage = block.querySelector("[data-announcement-fallback-message]");
        const endpoint = block.dataset.announcementEndpoint || "";
        let slides = [];
        try { slides = validSlides(JSON.parse(block.dataset.announcementSlides || "[]")); }
        catch (_) { slides = []; }

        let playlistKey = JSON.stringify(slides);
        let state = "loading";
        let currentIndex = 0;
        let slideTimeout = null;
        let requestInFlight = false;
        const failed = new Set();

        function clearSlideTimeout() {
            if (slideTimeout !== null) {
                window.clearTimeout(slideTimeout);
                slideTimeout = null;
            }
        }

        function playVideo() {
            if (!video) return;
            const attempt = video.play();
            if (attempt && typeof attempt.catch === "function") attempt.catch(() => {});
        }

        function fallback() {
            clearSlideTimeout();
            stage.hidden = true;
            if (video) {
                video.hidden = false;
                if (offlineMessage) offlineMessage.hidden = true;
                if (state === "offline" && !document.hidden) playVideo();
            } else if (offlineMessage) {
                offlineMessage.hidden = false;
            }
        }

        function nextAvailable(start) {
            for (let step = 0; step < slides.length; step++) {
                const index = (start + step) % slides.length;
                if (!failed.has(slides[index].id)) return index;
            }
            return -1;
        }

        function showSlide() {
            clearSlideTimeout();
            if (state !== "offline") return;
            const available = nextAvailable(currentIndex);
            if (available < 0 || !slides.length) {
                fallback();
                return;
            }
            currentIndex = available;
            const slide = slides[currentIndex];
            stage.hidden = false;
            if (offlineMessage) offlineMessage.hidden = true;
            if (video) {
                video.hidden = true;
                video.pause();
            }
            image.alt = "Surfside announcement " + (currentIndex + 1) + " of " + slides.length;
            image.src = slide.image_url;
            if (!document.hidden) {
                slideTimeout = window.setTimeout(() => {
                    currentIndex = (currentIndex + 1) % slides.length;
                    showSlide();
                }, slide.duration_seconds * 1000);
            }
        }

        image.addEventListener("error", () => {
            if (state !== "offline" || !slides.length) return;
            const slide = slides[currentIndex];
            if (!slide || image.src !== slide.image_url) return;
            failed.add(slide.id);
            currentIndex = (currentIndex + 1) % slides.length;
            showSlide();
        });

        function updatePlaylist(value) {
            const incoming = validSlides(value && value.slides);
            const key = JSON.stringify(incoming);
            if (key === playlistKey) return;
            playlistKey = key;
            slides = incoming;
            failed.clear();
            currentIndex = 0;
            if (state === "offline") {
                if (slides.length) showSlide();
                else fallback();
            }
        }

        async function refresh() {
            if (!endpoint || requestInFlight) return;
            requestInFlight = true;
            try {
                const response = await fetch(endpoint, {cache: "no-store", credentials: "same-origin"});
                if (!response.ok) throw new Error("Slideshow unavailable");
                updatePlaylist(await response.json());
            } catch (_) {
                // Keep the most recently loaded playlist, or the legacy MP4.
            } finally {
                requestInFlight = false;
            }
        }

        function setStreamState(next) {
            const wasOffline = state === "offline";
            state = next;
            if (next !== "offline") {
                clearSlideTimeout();
                stage.hidden = true;
                if (video) {
                    video.hidden = false;
                    video.pause();
                }
                if (offlineMessage) offlineMessage.hidden = !!video;
                return;
            }
            if (!wasOffline) {
                currentIndex = 0;
                if (slides.length) showSlide();
                else fallback();
                refresh();
            }
        }

        function syncVisibility() {
            if (state !== "offline") return;
            if (document.hidden) {
                clearSlideTimeout();
                if (video) video.pause();
            } else {
                if (slides.length && nextAvailable(currentIndex) >= 0) showSlide();
                else fallback();
                refresh();
            }
        }

        const poll = window.setInterval(() => {
            if (state === "offline" && !document.hidden) refresh();
        }, 60000);

        return {
            setStreamState,
            syncVisibility,
            stop() {
                clearSlideTimeout();
                window.clearInterval(poll);
                if (video) video.pause();
            }
        };
    }

    window.SurfsideOfflineAnnouncements = { create };
})();
