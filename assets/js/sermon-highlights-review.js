/* Staff-only, video-first sermon highlight reviews. Nothing here publishes video. */
(() => {
  "use strict";
  const root = document.getElementById("surfside-highlights-review");
  const cfg = window.SurfsideHighlightsReview;
  if (!root || !cfg || !cfg.endpoint || !cfg.nonce) return;

  const sermon = root.querySelector("#surfside-highlights-sermon");
  const analyze = root.querySelector("#surfside-highlights-analyze");
  const render = root.querySelector("#surfside-highlights-render");
  const message = root.querySelector("#surfside-highlights-message");
  const results = root.querySelector("#surfside-highlights-results");
  let activeId = "", analysis = null, previews = null, token = 0, timer = null;
  let busy = false;

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
  const json = async (mode, body = null) => {
    // The Surfside Tools staff session is scoped to /dashboard/, not to
    // generic WordPress REST cookie authentication. Like Weekly Media,
    // authorize via an own-nonce POST to the current signed-in staff page.
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
    let response;
    try {
      response = await fetch(cfg.endpoint, {
        method: "POST",
        credentials: "same-origin",
        redirect: "error",
        cache: "no-store",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: fields.toString(),
      });
    } catch {
      throw Error("The staff authorization request failed. Check your login and connection.");
    }
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.success !== true) {
      throw Error(payload.data?.message || "Staff authorization failed (HTTP " + response.status + ").");
    }
    return payload.data;
  };
  const controls = () => {
    analyze.disabled = !activeId || busy;
    render.disabled = !activeId || busy ||
      analysis?.status !== "ready" || !(analysis?.result?.highlights?.length);
  };
  const readReviews = () => analysis?.saved_reviews || {};

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

  function renderResults() {
    results.replaceChildren();
    const highlights = analysis?.result?.highlights;
    if (!Array.isArray(highlights) || !highlights.length) return;
    const intro = el("div", "surfside-highlights-intro");
    intro.append(el("h2", "", "Suggested clips"));
    intro.append(el("p", "", "All timestamps refer to the original MP4. Adjust each edge up to five seconds. Changes apply instantly within the padded preview; use Save, Approve, or Reject to record your decision."));
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

    for (const suggestion of highlights.slice(0, 5)) {
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

  async function refreshPreviews(currentToken) {
    if (!activeId || currentToken !== token) return;
    try {
      const data = await json("preview-status", { video_id: activeId });
      if (currentToken !== token) return;
      previews = data;
      renderResults();
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
      controls();
      if (status.status === "queued" || status.status === "processing") {
        note("AI analysis " + status.status + ". Checking for suggestions…");
        poll(() => refreshAnalysis(currentToken));
      } else if (status.status === "ready") {
        note("AI suggestions ready. Generate video previews or review existing ones.");
        await refreshPreviews(currentToken);
      } else if (status.status === "failed") {
        note("Analysis failed (" + (status.detail || "unknown error") +
          "). Review Cloudflare logs before retrying.", true);
      } else {
        note("No AI analysis for this sermon yet. Select Analyze sermon to begin.");
      }
    } catch (error) { if (currentToken === token) note(error.message, true); }
  }

  async function chooseSermon() {
    token += 1; stopPolling();
    activeId = sermon.value; analysis = null; previews = null;
    results.replaceChildren(); controls();
    if (!activeId) { note("Choose a verified uploaded sermon."); return; }
    note("Checking AI results for the selected sermon…");
    await refreshAnalysis(token);
  }

  analyze.addEventListener("click", async () => {
    if (!activeId || busy) return;
    busy = true; controls();
    try {
      const data = await json("analyze", { video_id: activeId });
      note("Analysis " + data.status + ". The original sermon and podcast are unchanged.");
      await refreshAnalysis(token);
    } catch (error) { note(error.message, true); }
    finally { busy = false; controls(); }
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
  sermon.addEventListener("change", chooseSermon);

  (async () => {
    try {
      const data = await json("sermons");
      sermon.replaceChildren();
      const blank = el("option", "", "Choose an uploaded sermon");
      blank.value = ""; sermon.append(blank);
      for (const item of data.sermons || []) {
        const option = el("option", "",
          (item.sermon_date || "No date") + " — " + (item.title || item.video_id));
        option.value = item.video_id;
        sermon.append(option);
      }
      if (!data.configured) {
        note("The private Cloudflare highlights service is not configured on WordPress.", true);
        analyze.disabled = true; render.disabled = true;
        return;
      }
      if (!data.sermons?.length) {
        note("No verified sermon MP4s were found in the Messages playlist.", true);
        return;
      }
      if (cfg.initialId && (data.sermons || []).some(item => item.video_id === cfg.initialId)) {
        sermon.value = cfg.initialId;
        await chooseSermon();
      } else {
        note("Choose a verified uploaded sermon to inspect its highlights.");
      }
      controls();
    } catch (error) { note(error.message, true); }
  })();
})();
