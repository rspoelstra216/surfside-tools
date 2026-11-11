/* Direct-to-R2 staff upload. No MP4 bytes touch the WordPress server. */
(() => {
  "use strict";
  const root = document.getElementById("surfside-r2-sermons");
  const config = window.SurfsideR2Upload;
  if (!root || !config) return;
  const form = root.querySelector("#surfside-r2-upload-form");
  if (!form) return;
  const idField = root.querySelector("#surfside-r2-id");
  const fileField = root.querySelector("#surfside-r2-file");
  const status = root.querySelector("#surfside-r2-status");
  const progress = root.querySelector("#surfside-r2-progress");
  const cancel = root.querySelector("#surfside-r2-cancel");
  const submit = form.querySelector('button[type="submit"]');
  const podcastSelect = root.querySelector("#surfside-podcast-id");
  const podcastEmpty = root.querySelector("#surfside-podcast-empty");
  const podcastDetails = root.querySelector("#surfside-podcast-details");
  const podcastTitle = root.querySelector("#surfside-podcast-title");
  const podcastDescription = root.querySelector("#surfside-podcast-description");
  const podcastReview = root.querySelector("#surfside-podcast-review");
  const podcastNotesStatus = root.querySelector("#surfside-podcast-notes-status");
  const podcastRecover = root.querySelector("#surfside-podcast-recover-spreaker");
  const podcastRecoverStatus = root.querySelector("#surfside-podcast-recovery-status");
  const podcastAudioRetry = root.querySelector("#surfside-podcast-retry-audio");
  const podcastAudioRetryStatus = root.querySelector("#surfside-podcast-audio-retry-status");
  let active = null;

  const showPodcastPreview = () => {
    if (!podcastSelect) return;
    const option = podcastSelect.selectedOptions[0];
    const selected = Boolean(option && option.value);
    podcastEmpty.hidden = selected;
    podcastDetails.hidden = !selected;
    if (podcastAudioRetry) {
      podcastAudioRetry.disabled = !selected || podcastAudioRetry.dataset.busy === "1";
    }
    if (podcastRecover) {
      podcastRecover.disabled = !selected || option.dataset.podcastReview === "1" ||
        podcastRecover.dataset.busy === "1";
    }
    if (!selected) return;
    podcastTitle.textContent = option.dataset.podcastTitle || "";
    podcastDescription.textContent = option.dataset.podcastDescription || "";
    podcastReview.hidden = option.dataset.podcastReview !== "1";
    if (podcastNotesStatus) {
      const descriptions = {
        added: "Matching archived sermon notes are included in this description.",
        truncated: "Matching sermon notes are included, but shortened to fit Spreaker’s description limit.",
        no_match: "No matching archived notes were found. Older sermons may predate archiving, or the archived title/date may differ. Spreaker will use the standard description.",
        ambiguous: "Multiple archived notes match this sermon. None were attached to avoid using the wrong notes.",
        archive_unavailable: "The sermon notes archive is not initialized. Spreaker will use the standard description.",
        metadata_review: "Review the YouTube title and date before sermon notes can be matched.",
        empty: "The archived sermon has no written notes or prayer to embed.",
        too_long: "No notes were attached because the podcast description has insufficient room."
      };
      podcastNotesStatus.textContent = descriptions[option.dataset.podcastNotesStatus] || "";
    }
    if (podcastRecover) {
      podcastRecover.disabled = option.dataset.podcastReview === "1" || podcastRecover.dataset.busy === "1";
    }
  };
  if (podcastSelect) {
    podcastSelect.addEventListener("change", showPodcastPreview);
    showPodcastPreview();
  }

  const message = (text) => { status.textContent = text; };
  const request = async (route, payload) => {
    // The same-origin staff page processes these tiny authorization requests.
    // Only the actual MP4 is sent directly to R2 (see xhr.send below).
    const params = new URLSearchParams({
      action: "surfside_r2_browser_upload",
      r2_nonce: config.nonce,
      mode: route,
      ...payload
    });
    let response;
    try {
      response = await fetch(config.endpoint, {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        redirect: "error",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: params.toString()
      });
    } catch (_) {
      throw new Error("WordPress authorization request failed. Check your login and connection, then retry.");
    }
    const body = await response.json().catch(() => ({}));
    if (!response.ok || body.success !== true) {
      throw new Error(body.data?.message || "WordPress authorization failed (HTTP " + response.status + ").");
    }
    return body.data;
  };

  // Admin-only retry for a failed Cloudflare audio job. WordPress verifies
  // the existing MP4 and signs both status and queue requests server-side.
  if (podcastAudioRetry) podcastAudioRetry.addEventListener("click", async () => {
    const id = podcastSelect?.value || "";
    if (!/^[A-Za-z0-9_-]{11}$/.test(id) || podcastAudioRetry.dataset.busy === "1") return;
    if (!window.confirm("Retry the failed Cloudflare audio-processing job for this sermon using its existing R2 MP4? No video will be re-uploaded. An unpublished Spreaker draft may be created after the audio is ready.")) return;
    podcastAudioRetry.dataset.busy = "1";
    podcastAudioRetry.disabled = true;
    if (podcastAudioRetryStatus) podcastAudioRetryStatus.textContent =
      "Checking Cloudflare job status and requesting an audio retry…";
    try {
      const result = await request("retry_audio", {video_id: id});
      if (result.status !== "queued") throw new Error("Audio processing was not queued.");
      if (podcastAudioRetryStatus) podcastAudioRetryStatus.textContent =
        "Podcast audio retry queued. Check podcast-jobs in Cloudflare R2 for ready." +
        (result.spreaker_auto_publish_requested
          ? " Spreaker will publish automatically after encoding the audio."
          : result.spreaker_draft_requested
            ? " An unpublished Spreaker draft is planned after audio processing."
            : " If no Spreaker draft appears, use Prepare missing Spreaker draft once audio is ready.");
    } catch (error) {
      if (podcastAudioRetryStatus) podcastAudioRetryStatus.textContent =
        error.message || "Could not retry podcast audio.";
    } finally {
      delete podcastAudioRetry.dataset.busy;
      showPodcastPreview();
    }
  });

  // Admin-only recovery for an uploaded sermon with completed audio but no
  // Spreaker draft. Does not send MP4 bytes or re-run audio extraction.
  if (podcastRecover) podcastRecover.addEventListener("click", async () => {
    const option = podcastSelect?.selectedOptions[0];
    const id = option?.value || "";
    if (!/^[A-Za-z0-9_-]{11}$/.test(id) || option.dataset.podcastReview === "1" ||
        podcastRecover.dataset.busy === "1") return;
    if (!window.confirm("Only continue if this sermon already has a completed MP3 in R2 AND no Spreaker draft. Check Spreaker first to avoid duplicates. Prepare an unpublished draft now?")) return;
    podcastRecover.dataset.busy = "1";
    podcastRecover.disabled = true;
    if (podcastRecoverStatus) podcastRecoverStatus.textContent = "Checking existing audio and draft status…";
    try {
      const result = await request("recover_spreaker", {video_id: id});
      if (result.status !== "queued") throw new Error("Spreaker draft was not queued.");
      if (podcastRecoverStatus) podcastRecoverStatus.textContent =
        "Unpublished Spreaker draft queued from the existing MP3. Check podcast-spreaker in R2 and review the draft in Spreaker.";
    } catch (error) {
      if (podcastRecoverStatus) podcastRecoverStatus.textContent =
        error.message || "Could not prepare the missing Spreaker draft.";
    } finally {
      delete podcastRecover.dataset.busy;
      showPodcastPreview();
    }
  });

  cancel.addEventListener("click", () => {
    if (active) active.abort();
  });
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const id = idField.value;
    const file = fileField.files[0];
    if (!/^[A-Za-z0-9_-]{11}$/.test(id) || !file || !/\.mp4$/i.test(file.name)) {
      message("Select a YouTube message and an MP4 file.");
      return;
    }
    submit.disabled = true;
    idField.disabled = true;
    fileField.disabled = true;
    cancel.hidden = false;
    progress.style.display = "block";
    progress.value = 0;
    try {
      message("Requesting upload authorization…");
      const ticket = await request("ticket", {video_id: id, size: file.size});
      message("Uploading directly to R2. Keep this page open.");
      await new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        active = xhr;
        xhr.open("PUT", ticket.upload_url, true);
        xhr.setRequestHeader("Content-Type", "video/mp4");
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) {
            progress.value = Math.floor((e.loaded / e.total) * 100);
            message("Uploading directly to R2: " + progress.value + "%");
          }
        };
        xhr.onload = () => {
          active = null;
          if (xhr.status >= 200 && xhr.status < 300) resolve();
          else reject(new Error("R2 upload returned HTTP " + xhr.status + ". Check bucket CORS and permissions."));
        };
        xhr.onerror = () => { active = null; reject(new Error("R2 upload failed. Check the network and bucket CORS policy.")); };
        xhr.onabort = () => { active = null; reject(new Error("Upload canceled. A new upload must start from the beginning.")); };
        xhr.send(file);
      });
      message("Upload finished. Verifying object in R2…");
      const completion = await request("complete", {video_id: id});
      progress.value = 100;
      const podcastStatus = completion.podcast?.status;
      if (podcastStatus === "queued" || podcastStatus === "processing") {
        message("MP4 verified for Roku. Podcast audio processing queued." +
          (completion.podcast?.spreaker_auto_publish_requested
            ? " Spreaker will publish automatically after audio encoding completes."
            : completion.podcast?.spreaker_draft_requested
              ? " An unpublished Spreaker draft is planned after audio processing."
              : ""));
      } else if (podcastStatus === "ready") {
        message("MP4 verified for Roku. Podcast MP3 is already prepared.");
      } else if (podcastStatus === "queue_unavailable") {
        message("MP4 verified for Roku. Podcast processing could not be queued; the video is safe.");
      } else {
        message("MP4 verified for Roku. Podcast processing is not yet configured.");
      }
      const uploadedOption = idField.querySelector('option[value="' + id + '"]');
      uploadedOption.disabled = true;
      if (podcastSelect && !podcastSelect.querySelector('option[value="' + id + '"]')) {
        const newOption = document.createElement("option");
        newOption.value = id;
        newOption.textContent = uploadedOption.textContent.replace(/^✓\s*/, "");
        newOption.dataset.podcastTitle = uploadedOption.dataset.podcastTitle || "";
        newOption.dataset.podcastDescription = uploadedOption.dataset.podcastDescription || "";
        newOption.dataset.podcastNotesStatus = uploadedOption.dataset.podcastNotesStatus || "no_match";
        newOption.dataset.podcastReview = uploadedOption.dataset.podcastReview || "1";
        podcastSelect.appendChild(newOption);
      }
      if (podcastSelect) {
        podcastSelect.value = id;
        showPodcastPreview();
      }
      fileField.value = "";
    } catch (error) {
      message(error.message || "Upload failed.");
    } finally {
      active = null;
      submit.disabled = false;
      idField.disabled = false;
      fileField.disabled = false;
      cancel.hidden = true;
    }
  });
})();
