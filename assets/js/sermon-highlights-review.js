/* Staff-only, video-first sermon highlight reviews. Nothing here publishes video. */
(() => {
  "use strict";
  const root = document.getElementById("surfside-highlights-review");
  const cfg = window.SurfsideHighlightsReview;
  if (!root || !cfg || !cfg.endpoint || !cfg.nonce) return;

  const sermon = root.querySelector("#surfside-highlights-sermon");
  const analyze = root.querySelector("#surfside-highlights-analyze");
  const render = root.querySelector("#surfside-highlights-render");
  const findMore = root.querySelector("#surfside-highlights-find-more");
  const moreMessage = root.querySelector("#surfside-highlights-more-message");
  const message = root.querySelector("#surfside-highlights-message");
  const results = root.querySelector("#surfside-highlights-results");
  const exportPanel = root.querySelector("#surfside-highlight-export-panel");
  const exportButton = root.querySelector("#surfside-highlight-export-button");
  const exportMessage = root.querySelector("#surfside-highlight-export-message");
  const exportDownloads = root.querySelector("#surfside-highlight-export-downloads");
  const verticalPanel = root.querySelector("#surfside-highlight-vertical-panel");
  const verticalSelect = root.querySelector("#surfside-vertical-number");
  const verticalAnchor = root.querySelector("#surfside-vertical-anchor");
  const verticalAnchorValue = root.querySelector("#surfside-vertical-anchor-value");
  const verticalGenerate = root.querySelector("#surfside-vertical-generate");
  const verticalMessage = root.querySelector("#surfside-vertical-message");
  const verticalWrap = root.querySelector("#surfside-vertical-video-wrap");
  const verticalFocusArea = root.querySelector("#surfside-vertical-focus-area");
  const verticalFocusVideo = root.querySelector("#surfside-vertical-focus-video");
  const verticalFocusPicker = root.querySelector("#surfside-vertical-focus-picker");
  const verticalFocusMarker = root.querySelector("#surfside-vertical-focus-marker");
  let activeId = "", analysis = null, previews = null, exportsState = null, token = 0, timer = null;
  let exportTimer = null, exportBusy = false, busy = false;
  let verticalTimer = null, verticalBusy = false, verticalState = null;
  let focusPreviewUrl = "", focusPreviewNumber = 0, focusSeek = 0;

  const fmt = (seconds) => {
    const s = Math.max(0, Math.round(seconds * 100) / 100);
    return Math.floor(s / 60) + ":" +
      String(Math.floor(s % 60)).padStart(2, "0") +
      "." + String(Math.round((s % 1) * 100)).padStart(2, "0");
  };
  const round = (n) => Math.round(n * 100) / 100;
  const note = (text, isError = false) => {
    message.textContent = text;
    message.classList.toggle("surfside-highlights-error", isError);
  };
  const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };
  const action = (label, callback, className) => {
    const button = el("button", className || "", label);
    button.type = "button";
    button.addEventListener("click", callback);
    return button;
  };
  const stopPolling = () => { if (timer) clearTimeout(timer); timer = null; };
  const poll = (callback, wait = 5500) => {
    stopPolling();
    timer = setTimeout(callback, wait);
  };
  const staffEndpoint = () => {
    // Use the already-loaded, browser-canonical same-origin staff URL for
    // POSTs, not a possibly different www/URL-scheme/site-settings URL.
    // The WordPress dashboard handler checks the exact page and view.
    const current = new URL(window.location.href);
    const configured = new URL(cfg.endpoint, current);
    if (["/dashboard/site-management/", "/dashboard/site-management"]
          .includes(current.pathname) &&
        current.searchParams.get("view") === "sermon-highlights") {
      current.hash = "";
      return current.toString();
    }
    if (configured.origin !== current.origin) {
      throw Error("The dashboard action URL differs from this site's address. Reload the Surfside staff page.", {
        cause: "staff-origin-mismatch",
      });
    }
    return configured.toString();
  };
  const json = async (mode, body = null) => {
    // Surfside staff sessions are scoped to the dashboard and expire.
    // Do not follow redirects with a signed, authenticated POST: it could
    // silently become a login-page GET or cross an origin boundary.
    const fields = new URLSearchParams({
      action: "surfside_highlights_browser_review",
      highlight_nonce: cfg.nonce,
      mode,
    });
    if (body) {
      for (const [key, value] of Object.entries(body)) {
        if (value !== undefined && value !== null) fields.set(key, String(value));
      }
    }
    const target = staffEndpoint();
    let response;
    try {
      response = await fetch(target, {
        method: "POST",
        credentials: "same-origin",
        redirect: "manual",
        cache: "no-store",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "Accept": "application/json" },
        body: fields.toString(),
      });
    } catch {
      throw Error("Could not reach the staff dashboard. Check your connection, then reload the page and retry.");
    }
    if (response.type === "opaqueredirect" ||
        (response.status >= 300 && response.status < 400)) {
      throw Error("Your staff session may have expired or the request was redirected. Reload the page, sign in again if prompted, and retry.");
    }
    const contentType = response.headers.get("content-type") || "";
    if (!contentType.toLowerCase().includes("application/json")) {
      throw Error("The staff dashboard returned a page instead of an action response. Reload the page and sign in again if needed.");
    }
    const payload = await response.json().catch(() => null);
    if (!payload || typeof payload !== "object") {
      throw Error("The staff dashboard sent an unreadable response. Reload the page before retrying.");
    }
    if (!response.ok || payload.success !== true) {
      throw Error(payload.data?.message ||
        (response.status === 401 || response.status === 403
          ? "The staff session is no longer authorized. Reload and sign in again."
          : "The staff request failed (HTTP " + response.status + ")."));
    }
    return payload.data;
  };
  const controls = () => {
    analyze.disabled = !activeId || busy;
    render.disabled = !activeId || busy ||
      analysis?.status !== "ready" || !(analysis?.result?.highlights?.length);
    if (findMore) {
      const more = analysis?.more;
      findMore.disabled = !activeId || busy || analysis?.status !== "ready" ||
        (analysis?.result?.highlights?.length || 0) >= 10 ||
        ["queued", "processing"].includes(more?.status) ||
        Number(more?.round || 0) >= 4;
    }
    if (verticalGenerate) {
      verticalGenerate.disabled = !activeId || busy || verticalBusy ||
        !approvedReviews().length || !verticalSelect?.value ||
        ["queued", "processing"].includes(verticalState?.status);
    }
    if (exportButton) {
      exportButton.disabled = !activeId || busy || exportBusy ||
        !approvedReviews().length ||
        ["queued", "processing"].includes(exportsState?.status);
    }
  };
  const readReviews = () => analysis?.saved_reviews || {};
  const approvedReviews = () => Object.values(readReviews())
    .filter((r) => r?.decision === "approved" && Number.isInteger(r.number));

  function makeTrimControl(card, label, original, initial, update) {
    const group = el("div", "surfside-highlight-trim");
    const heading = el("div", "surfside-highlight-trim-heading");
    heading.append(el("strong", "", label));
    const time = el("output", "surfside-highlight-time", fmt(initial));
    heading.append(time);
    group.append(heading);
    const row = el("div", "surfside-highlight-trim-row");
    const slider = el("input");
    slider.type = "range"; slider.min = "-5"; slider.max = "5"; slider.step = "0.5";
    slider.value = String(Math.max(-5, Math.min(5, round(initial - original))));
    slider.setAttribute("aria-label", label + " adjustment in seconds");
    const minus = action("−", () => change(-0.5), "surfside-highlight-nudge");
    const plus = action("+", () => change(0.5), "surfside-highlight-nudge");
    minus.setAttribute("aria-label", "Move " + label.toLowerCase() + " half a second earlier");
    plus.setAttribute("aria-label", "Move " + label.toLowerCase() + " half a second later");
    const change = (amount) => {
      slider.value = String(Math.max(-5, Math.min(5, round(Number(slider.value) + amount))));
      slider.dispatchEvent(new Event("input"));
    };
    slider.addEventListener("input", () => {
      const value = round(original + Number(slider.value));
      time.textContent = fmt(value);
      update(value);
    });
    row.append(minus, slider, plus);
    group.append(row);
    card.append(group);
    return { slider, time };
  }

  function showMoreStatus() {
    if (!moreMessage) return;
    const more = analysis?.more;
    if (!activeId || analysis?.status !== "ready") {
      moreMessage.textContent = ""; return;
    }
    if (["queued", "processing"].includes(more?.status)) {
      moreMessage.textContent = "Finding different sermon moments from the saved transcript (" +
        more.status + "). Existing clips and approvals are unchanged.";
    } else if (more?.status === "ready") {
      moreMessage.textContent = more.added > 0
        ? "Found " + more.added + " additional suggestion(s). Their private previews will prepare automatically."
        : "No additional strong moments found in that pass. Existing suggestions remain available.";
    } else if (more?.status === "failed") {
      moreMessage.textContent = "Additional suggestion search failed. Existing clips are safe; you can retry.";
    } else {
      moreMessage.textContent = "Didn't like the AI choices? Find more uses the saved transcript without rerunning transcription.";
    }
  }

  function renderResults() {
    results.replaceChildren();
    const highlights = analysis?.result?.highlights;
    if (!Array.isArray(highlights) || !highlights.length) return;
    const intro = el("div", "surfside-highlights-intro");
    intro.append(el("h2", "", "Suggested clips"));
    intro.append(el("p", "", "All timestamps refer to the original MP4. Adjust each edge up to five seconds. Additional AI suggestions appear after the original clips, without replacing saved reviews. Use Save, Approve, or Reject to record your decision."));
    if (previews?.status === "ready") {
      intro.append(action("Refresh preview links", () => refreshPreviews(token),
        "surfside-highlight-secondary"));
    } else {
      intro.append(el("p", "surfside-highlight-help",
        previews?.status === "processing" || previews?.status === "queued"
          ? "Preparing private video previews…"
          : "Use Generate video previews to watch these excerpts."));
    }
    results.append(intro);
    const reviews = readReviews();
    const section = el("div", "surfside-highlights-grid");

    for (const suggestion of highlights.slice(0, 10)) {
      const number = suggestion.number;
      const saved = reviews[String(number)] || {};
      const baselineStart = Number(suggestion.start), baselineEnd = Number(suggestion.end);
      const state = {
        start: Number.isFinite(Number(saved.start)) && saved.start !== undefined
          ? Number(saved.start) : baselineStart,
        end: Number.isFinite(Number(saved.end)) && saved.end !== undefined
          ? Number(saved.end) : baselineEnd,
        decision: saved.decision || "pending",
      };
      const card = el("article", "surfside-highlight-card");
      const top = el("div", "surfside-highlight-top");
      const title = el("h3", "", "#" + number + " " + (suggestion.title || "Highlight"));
      const badge = el("span", "surfside-highlight-decision", state.decision === "pending" ? "Not reviewed" : state.decision);
      top.append(title, badge);
      card.append(top);
      card.append(el("p", "surfside-highlight-reason", suggestion.reason || "AI suggested moment"));
      card.append(el("p", "surfside-highlight-meta",
        "AI clip: " + fmt(baselineStart) + "–" + fmt(baselineEnd) +
        " · Score " + (suggestion.score ?? "—") + "/5"));

      const clip = Array.isArray(previews?.clips) ? previews.clips.find(c => c.number === number &&
        Math.abs(Number(c.start) - Math.max(0, round(baselineStart - 5))) < 0.06 &&
        Math.abs(Number(c.end) - Math.min(4320, round(baselineEnd + 5))) < 0.06) : null;
      const playerWrap = el("div", "surfside-highlight-player");
      let video = null;
      if (clip?.preview_url) {
        video = el("video");
        video.controls = true; video.preload = "metadata"; video.playsInline = true;
        video.src = clip.preview_url;
        video.setAttribute("aria-label", "Video preview for highlight " + number);
        const seekStart = () => Math.max(0, state.start - clip.start);
        const seekEnd = () => state.end - clip.start;
        video.addEventListener("loadedmetadata", () => { video.currentTime = seekStart(); });
        video.addEventListener("play", () => {
          if (video.currentTime < seekStart() - 0.12 || video.currentTime >= seekEnd() - 0.12)
            video.currentTime = seekStart();
        });
        video.addEventListener("timeupdate", () => {
          if (video.currentTime >= seekEnd()) {
            video.pause();
            video.currentTime = Math.min(seekEnd(), video.duration || seekEnd());
          }
        });
        video.addEventListener("error", () => note(
          "A preview failed to load. Refresh the private preview links and try again.", true));
        playerWrap.append(video);
      } else {
        playerWrap.append(el("p", "", "Video preview not generated yet."));
      }
      card.append(playerWrap);

      const trims = el("div", "surfside-highlight-trims");
      const updateStart = (newStart) => {
        state.start = newStart;
        badge.textContent = "Unsaved changes";
        if (video) { video.pause(); video.currentTime = Math.max(0, state.start - clip.start); }
      };
      const updateEnd = (newEnd) => {
        state.end = newEnd;
        badge.textContent = "Unsaved changes";
        if (video && video.currentTime > state.end - clip.start) {
          video.pause(); video.currentTime = Math.max(0, state.start - clip.start);
        }
      };
      makeTrimControl(trims, "Start", baselineStart, state.start, updateStart);
      makeTrimControl(trims, "End", baselineEnd, state.end, updateEnd);
      const cue = el("p", "surfside-highlight-help",
        "Each edge adjusts independently in 0.5-second steps (±5 seconds).");
      card.append(trims, cue);
      if (suggestion.excerpt) {
        const details = el("details", "surfside-highlight-transcript");
        details.append(el("summary", "", "View AI transcript excerpt"));
        details.append(el("p", "", suggestion.excerpt));
        card.append(details);
      }
      const choices = el("div", "surfside-highlight-choices");
      async function save(decision) {
        if (state.end - state.start < 15 || state.end - state.start > 120) {
          note("Clip duration must be between 15 and 120 seconds.", true);
          return;
        }
        if (busy) return;
        busy = true; controls();
        const buttons = choices.querySelectorAll("button");
        buttons.forEach(b => { b.disabled = true; });
        try {
          const response = await json("review", {
            video_id: activeId, number, decision, start: state.start, end: state.end,
          });
          if (!analysis.saved_reviews) analysis.saved_reviews = {};
          analysis.saved_reviews[String(number)] = response.review;
          state.decision = decision;
          badge.textContent = decision === "saved" ? "Saved draft" : decision;
          note("Highlight #" + number + " " +
            (decision === "saved" ? "saved" : decision) + " privately. Nothing has been published.");
          exportsState = null;
          renderExportPanel();
          renderVerticalPanel();
          refreshExports(token);
          refreshVertical(token);
        } catch (error) {
          note(error.message, true);
        } finally {
          busy = false; controls();
          buttons.forEach(b => { b.disabled = false; });
        }
      }
      const saveButton = action("Save changes", () => save("saved"), "surfside-highlight-secondary");
      const approveButton = action("Approve", () => save("approved"), "surfside-highlight-primary");
      const rejectButton = action("Reject", () => save("rejected"), "surfside-highlight-reject");
      choices.append(saveButton, approveButton, rejectButton);
      card.append(choices);
      section.append(card);
    }
    results.append(section);
  }

  const focusLabel = (value) => {
    const percent = Math.max(0, Math.min(100, Number(value) || 0));
    if (percent === 50) return "Center";
    if (percent < 50) return percent + "% from left";
    return percent + "% from left";
  };
  const updateFocusMarker = () => {
    const percent = Math.max(0, Math.min(100, Number(verticalAnchor?.value) || 0));
    if (verticalAnchorValue) verticalAnchorValue.textContent = focusLabel(percent);
    if (verticalFocusMarker) verticalFocusMarker.style.left = percent + "%";
    if (verticalFocusPicker) verticalFocusPicker.setAttribute("aria-label",
      "Pastor starting position: " + focusLabel(percent) +
      ". Tap the video to choose a different position.");
  };

  function syncVerticalFocusFrame() {
    if (!verticalFocusArea || !verticalFocusVideo || !verticalSelect) return;
    const number = Number(verticalSelect.value);
    const approved = approvedReviews().find((clip) => clip.number === number);
    const clip = Array.isArray(previews?.clips) ?
      previews.clips.find((item) => item.number === number &&
        Number(item.start) <= Number(approved?.start) + 0.02 &&
        Number(item.end) >= Number(approved?.end) - 0.02 &&
        item.preview_url) : null;
    const ready = !!clip && !!approved && analysis?.status === "ready";
    verticalFocusArea.hidden = !ready;
    if (!ready) {
      focusPreviewUrl = ""; focusPreviewNumber = 0;
      verticalFocusVideo.removeAttribute("src");
      verticalFocusVideo.load();
      updateFocusMarker();
      return;
    }
    focusSeek = Math.max(0.1, Number(approved.start) - Number(clip.start));
    if (clip.preview_url !== focusPreviewUrl || number !== focusPreviewNumber) {
      focusPreviewUrl = clip.preview_url;
      focusPreviewNumber = number;
      verticalFocusVideo.src = clip.preview_url;
      verticalFocusVideo.load();
    } else if (verticalFocusVideo.readyState >= 1 &&
               Math.abs(verticalFocusVideo.currentTime - focusSeek) > 0.1) {
      verticalFocusVideo.currentTime = focusSeek;
    }
    updateFocusMarker();
  }

  if (verticalFocusVideo) {
    verticalFocusVideo.addEventListener("loadedmetadata", () => {
      if (verticalFocusVideo.duration > 0) {
        verticalFocusVideo.currentTime =
          Math.min(focusSeek, Math.max(0, verticalFocusVideo.duration - 0.1));
      }
    });
    verticalFocusVideo.addEventListener("seeked", () => verticalFocusVideo.pause());
    verticalFocusVideo.addEventListener("error", () => {
      if (verticalMessage && verticalFocusArea && !verticalFocusArea.hidden)
        verticalMessage.textContent =
          "Could not load the starting frame. Refresh the private video previews, then try again.";
    });
  }
  if (verticalFocusPicker && verticalAnchor) {
    verticalFocusPicker.addEventListener("click", (event) => {
      if (!verticalFocusArea || verticalFocusArea.hidden) return;
      const bounds = verticalFocusPicker.getBoundingClientRect();
      if (!bounds.width) return;
      const percent = Math.round(Math.max(0, Math.min(100,
        (event.clientX - bounds.left) / bounds.width * 100)));
      verticalAnchor.value = String(percent);
      updateFocusMarker();
      // Mobile browsers can keep a muted, seeked video black until the first
      // user gesture. Use this tap to unlock the paused frame if necessary.
      if (verticalFocusVideo?.paused && verticalFocusVideo.readyState >= 1) {
        verticalFocusVideo.play().then(() => {
          verticalFocusVideo.pause();
          verticalFocusVideo.currentTime = focusSeek;
        }).catch(() => {});
      }
    });
    verticalFocusPicker.addEventListener("keydown", (event) => {
      if (!["ArrowLeft", "ArrowRight"].includes(event.key)) return;
      event.preventDefault();
      const change = event.key === "ArrowLeft" ? -5 : 5;
      verticalAnchor.value = String(Math.max(0, Math.min(100,
        Number(verticalAnchor.value) + change)));
      updateFocusMarker();
    });
  }

  function renderVerticalPanel() {
    if (!verticalPanel || !verticalSelect || !verticalMessage || !verticalWrap) return;
    verticalPanel.hidden = analysis?.status !== "ready";
    if (verticalPanel.hidden) return;
    const approved = approvedReviews().sort((a, b) => a.number - b.number);
    const selected = Number(verticalSelect.value);
    const optionsChanged = approved.length !== verticalSelect.options.length ||
      approved.some((clip, i) => Number(verticalSelect.options[i]?.value) !== clip.number);
    if (optionsChanged) {
      verticalSelect.replaceChildren();
      for (const clip of approved) {
        const option = el("option", "", "#" + clip.number + " — " +
          (analysis?.result?.highlights?.find((h) => h.number === clip.number)?.title || "Approved"));
        option.value = String(clip.number);
        verticalSelect.append(option);
      }
      const preferred = approved.find((c) => c.number === selected) ||
        approved.find((c) => c.number === 4) || approved[0];
      if (preferred) verticalSelect.value = String(preferred.number);
    }
    syncVerticalFocusFrame();
    verticalWrap.replaceChildren();
    if (!approved.length) {
      verticalMessage.textContent = "Approve a clip above to test moving-speaker tracking.";
      controls(); return;
    }
    const state = verticalState;
    if (state?.status === "ready") {
      const clip = state.clip;
      verticalMessage.textContent = "Vertical result for #" + clip.number +
        " (" + fmt(clip.start) + "–" + fmt(clip.end) + "), starting focus " +
        state.anchor + "%. " + (state.mode === "tracked"
          ? "Moving face tracking used. Check that it follows the pastor throughout."
          : state.fallback_reason === "tracking_unavailable"
            ? "Speaker tracking could not complete. A safe wide-shot portrait was created instead; Cloudflare logs record the tracker error."
            : "Face tracking found too few reliable detections. The full wide shot is retained in the phone-format frame.");
      if (state.preview_url) {
        const player = el("video", "surfside-vertical-video");
        player.controls = true;
        player.playsInline = true;
        player.preload = "metadata";
        player.src = state.preview_url;
        player.setAttribute("aria-label", "Experimental vertical phone clip");
        verticalWrap.append(player);
      }
      if (state.download_url) {
        const link = el("a", "surfside-highlight-download", "Download vertical MP4");
        link.href = state.download_url;
        link.download = "surfside-" + activeId + "-vertical-" + clip.number + ".mp4";
        link.rel = "noreferrer noopener";
        verticalWrap.append(link);
        verticalWrap.append(action("Refresh vertical links",
          () => refreshVertical(token), "surfside-highlight-secondary"));
      }
    } else if (["queued", "processing"].includes(state?.status)) {
      verticalMessage.textContent = "Experimental vertical video " + state.status +
        ". Face tracking and source-quality encoding run in the background.";
    } else if (state?.status === "stalled") {
      verticalMessage.textContent = "The previous vertical job stalled in the queue. Click Generate vertical test to retry.";
    } else if (state?.status === "outdated") {
      verticalMessage.textContent = "The approved clip has changed. Generate a new vertical test.";
    } else if (state?.status === "failed" || state?.status === "incomplete") {
      const stage = {
        source: "downloading the original sermon",
        extraction: "extracting the approved video",
        tracking: "detecting and following the speaker",
        encoding: "rendering the vertical MP4",
        upload: "saving the video to R2",
      }[state?.detail] || "processing the vertical video";
      verticalMessage.textContent = "Vertical generation failed while " + stage +
        ". Your original clips are safe. See Cloudflare logs for the detailed error before retrying.";
    } else {
      verticalMessage.textContent = "Tap the pastor in the still above to choose his starting position, " +
        "then generate the private phone-format video. If the still is missing, refresh the preview links.";
    }
    controls();
  }

  async function refreshVertical(currentToken) {
    if (!activeId || currentToken !== token || analysis?.status !== "ready") return;
    try {
      const data = await json("vertical-status", { video_id: activeId });
      if (currentToken !== token) return;
      verticalState = data;
      renderVerticalPanel();
      if (verticalTimer) clearTimeout(verticalTimer);
      verticalTimer = null;
      if (["queued", "processing"].includes(data.status))
        verticalTimer = setTimeout(() => refreshVertical(currentToken), 5500);
    } catch (error) {
      if (currentToken === token && verticalMessage)
        verticalMessage.textContent = error.message || "Could not retrieve vertical test status.";
    }
  }

  function renderExportPanel() {
    if (!exportPanel || !exportButton || !exportDownloads || !exportMessage) return;
    const enabled = analysis?.status === "ready";
    exportPanel.hidden = !enabled;
    exportDownloads.replaceChildren();
    if (!enabled) return;
    const approved = approvedReviews();
    const state = exportsState;
    if (!approved.length) {
      exportMessage.textContent = "No clips approved yet. Saved drafts are not exported; use Approve on clips you want to download.";
    } else if (state?.status === "ready" && state.matches_current_approvals === true) {
      exportMessage.textContent = approved.length +
        " approved clip(s) exported as private MP4s. Links expire after 10 minutes; refresh them below if needed.";
      for (const clip of state.clips || []) {
        const row = el("div", "surfside-highlight-download-row");
        const title = el("span", "", "#" + clip.number + " — " +
          (clip.title || "Approved highlight") + " (" + fmt(clip.start) + "–" + fmt(clip.end) + ")");
        const link = el("a", "surfside-highlight-download", "Download MP4");
        link.href = clip.download_url;
        link.download = "surfside-" + activeId + "-highlight-" + clip.number + ".mp4";
        link.rel = "noreferrer noopener";
        row.append(title, link);
        exportDownloads.append(row);
      }
      exportDownloads.append(action("Refresh download links", () => refreshExports(token),
        "surfside-highlight-secondary"));
    } else if (["queued", "processing"].includes(state?.status)) {
      exportMessage.textContent = "Rendering approved MP4 files (" + state.status +
        "). Files will appear here when the job finishes.";
    } else if (state?.status === "outdated" ||
               (state?.status === "ready" && state.matches_current_approvals === false)) {
      exportMessage.textContent = "Approvals or trim points changed. Export the current approved clips to generate updated files.";
    } else if (state?.status === "stalled") {
      exportMessage.textContent = "The previous export attempt stalled. You can click Export approved clips again to retry the saved approvals.";
    } else if (state?.status === "failed" || state?.status === "incomplete") {
      exportMessage.textContent = "The export did not finish. Check Cloudflare logs before submitting another export.";
    } else {
      exportMessage.textContent = approved.length +
        " approved clip(s) ready for final MP4 export. Saved drafts and rejected suggestions stay out.";
    }
    controls();
  }

  async function refreshExports(currentToken) {
    if (!activeId || currentToken !== token || analysis?.status !== "ready") return;
    try {
      const data = await json("export-status", { video_id: activeId });
      if (currentToken !== token) return;
      exportsState = data;
      renderExportPanel();
      if (exportTimer) clearTimeout(exportTimer);
      exportTimer = null;
      if (["queued", "processing"].includes(data.status)) {
        exportTimer = setTimeout(() => refreshExports(currentToken), 5500);
      }
    } catch (error) {
      if (currentToken === token && exportMessage)
        exportMessage.textContent = error.message || "Cannot retrieve private exports.";
    }
  }

  async function refreshPreviews(currentToken) {
    if (!activeId || currentToken !== token) return;
    try {
      const data = await json("preview-status", { video_id: activeId });
      if (currentToken !== token) return;
      previews = data;
      renderResults();
      renderVerticalPanel();
      if (data.status === "queued" || data.status === "processing") {
        note("Generating MP4 previews from the original sermon. No publishing occurs.");
        poll(() => refreshPreviews(currentToken));
      } else if (data.status === "failed") {
        note("Video preview rendering failed. See Cloudflare logs before retrying.", true);
      } else if (data.status === "ready") {
        note("Previews ready. Play the videos, adjust boundaries, then save your decisions.");
      }
    } catch (error) { if (currentToken === token) note(error.message, true); }
  }

  async function refreshAnalysis(currentToken) {
    if (!activeId || currentToken !== token) return;
    try {
      const status = await json("status", { video_id: activeId });
      if (currentToken !== token) return;
      analysis = status;
      renderResults();
      showMoreStatus();
      renderExportPanel();
      renderVerticalPanel();
      controls();
      if (status.status === "queued" || status.status === "processing") {
        note("AI analysis " + status.status + ". Checking for suggestions…");
        poll(() => refreshAnalysis(currentToken));
      } else if (status.status === "ready") {
        note("AI suggestions ready. Loading private video preview status…");
        await refreshPreviews(currentToken);
        await refreshExports(currentToken);
        await refreshVertical(currentToken);
        // Keep checking while a second-pass Llama search is running. It does
        // not block playback of the original sermon highlights.
        if (["queued", "processing"].includes(status.more?.status))
          poll(() => refreshAnalysis(currentToken));
      } else if (status.status === "failed") {
        note("Analysis failed (" + (status.detail || "unknown error") +
          "). Review Cloudflare logs before retrying.", true);
      } else {
        note("No analysis found. New weekly uploads start automatically; use Analyze / Prepare now for older sermons or testing.");
      }
    } catch (error) { if (currentToken === token) note(error.message, true); }
  }

  async function chooseSermon() {
    token += 1; stopPolling();
    if (exportTimer) clearTimeout(exportTimer);
    if (verticalTimer) clearTimeout(verticalTimer);
    exportTimer = null; verticalTimer = null;
    activeId = sermon.value; analysis = null; previews = null; exportsState = null;
    verticalState = null;
    results.replaceChildren(); showMoreStatus(); renderExportPanel(); renderVerticalPanel(); controls();
    if (!activeId) { note("Choose a verified uploaded sermon."); return; }
    note("Checking AI results for the selected sermon…");
    await refreshAnalysis(token);
  }

  analyze.addEventListener("click", async () => {
    if (!activeId || busy) return;
    busy = true; controls();
    try {
      const data = await json("analyze", { video_id: activeId });
      note(data.already_analyzed
        ? "Existing AI analysis reused. Video previews: " + (data.previews || "checking") + "."
        : "AI analysis " + data.status + ". Private video previews will queue automatically afterward.");
      await refreshAnalysis(token);
    } catch (error) { note(error.message, true); }
    finally { busy = false; controls(); }
  });
  if (findMore) findMore.addEventListener("click", async () => {
    if (!activeId || busy || analysis?.status !== "ready") return;
    busy = true; controls();
    try {
      const data = await json("find-more", { video_id: activeId });
      if (moreMessage) moreMessage.textContent =
        "Additional AI suggestions " + data.status + ". Original clips and saved decisions are preserved.";
      await refreshAnalysis(token);
    } catch (error) {
      if (moreMessage) moreMessage.textContent = error.message ||
        "Could not start the additional suggestion search.";
    } finally { busy = false; controls(); }
  });
  render.addEventListener("click", async () => {
    if (!activeId || busy) return;
    busy = true; controls();
    try {
      const data = await json("previews", { video_id: activeId });
      note("Private video previews " + data.status + ".");
      await refreshPreviews(token);
    } catch (error) { note(error.message, true); }
    finally { busy = false; controls(); }
  });
  if (exportButton) exportButton.addEventListener("click", async () => {
    if (!activeId || exportBusy || !approvedReviews().length) return;
    exportBusy = true; controls();
    if (exportMessage) exportMessage.textContent = "Queuing exact-trim MP4 exports…";
    try {
      const currentToken = token;
      await json("export", { video_id: activeId });
      await refreshExports(currentToken);
    } catch (error) {
      if (exportMessage) exportMessage.textContent = error.message || "Export request failed.";
    } finally {
      exportBusy = false; controls();
    }
  });
  if (verticalAnchor) verticalAnchor.addEventListener("input", updateFocusMarker);
  if (verticalSelect) verticalSelect.addEventListener("change", () => {
    syncVerticalFocusFrame();
    controls();
  });
  if (verticalGenerate) verticalGenerate.addEventListener("click", async () => {
    if (!activeId || busy || verticalBusy || !verticalSelect.value) return;
    verticalBusy = true; controls();
    if (verticalMessage) verticalMessage.textContent = "Queuing experimental moving-speaker portrait…";
    try {
      const currentToken = token;
      await json("vertical", {
        video_id: activeId, number: verticalSelect.value, anchor: verticalAnchor.value,
      });
      await refreshVertical(currentToken);
    } catch (error) {
      if (verticalMessage) verticalMessage.textContent =
        error.message || "Could not start vertical test.";
    } finally { verticalBusy = false; controls(); }
  });
  sermon.addEventListener("change", chooseSermon);

  (async () => {
    try {
      // Use the same authenticated PHP response that rendered this page.
      // The prior second "sermons" POST produced an inconsistent response
      // envelope even while page-render checks were all valid.
      if (!Array.isArray(cfg.sermons) || typeof cfg.configured !== "boolean") {
        note("Sermon Highlights startup data is missing. Confirm the full WordPress plugin update and reload the page.", true);
        analyze.disabled = true; render.disabled = true;
        return;
      }
      sermon.replaceChildren();
      const blank = el("option", "", "Choose an uploaded sermon");
      blank.value = ""; sermon.append(blank);
      for (const item of cfg.sermons) {
        const option = el("option", "",
          (item.sermon_date || "No date") + " — " + (item.title || item.video_id));
        option.value = item.video_id;
        sermon.append(option);
      }
      if (!cfg.configured) {
        note("WordPress could not validate the private Cloudflare highlights settings on this page.", true);
        const report = el("section", "surfside-highlights-diagnostics");
        report.append(el("h2", "", "Configuration checks"));
        report.append(el("p", "", "Only safe Yes/No checks are shown; no credentials or URLs."));
        const checks = [
          ["Worker URL defined", "worker_url_defined"],
          ["Worker URL is text", "worker_url_string"],
          ["Worker HTTPS URL accepted", "worker_url_valid"],
          ["Shared secret defined", "secret_defined"],
          ["Shared secret is text", "secret_string"],
          ["Shared secret meets minimum length", "secret_length_valid"],
        ];
        const list = el("ul");
        for (const [label, key] of checks) {
          const status = typeof cfg.pageChecks?.[key] === "boolean"
            ? (cfg.pageChecks[key] ? "Yes" : "No") : "Not reported";
          const item = el("li", "", label + ": " + status);
          item.classList.add(status === "Yes" ? "surfside-highlight-check-pass"
            : status === "No" ? "surfside-highlight-check-fail"
            : "surfside-highlight-check-unknown");
          list.append(item);
        }
        report.append(list);
        results.replaceChildren(report);
        analyze.disabled = true; render.disabled = true;
        return;
      }
      if (!cfg.sermons.length) {
        note("No verified sermon MP4s were found in the Messages playlist.", true);
        analyze.disabled = true; render.disabled = true;
        return;
      }
      if (cfg.initialId && cfg.sermons.some(item => item.video_id === cfg.initialId)) {
        sermon.value = cfg.initialId;
        await chooseSermon();
      } else {
        note("Choose a verified uploaded sermon to inspect its highlights.");
      }
      controls();
    } catch (error) { note(error.message, true); }
  })();
})();
