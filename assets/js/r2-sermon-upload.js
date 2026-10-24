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
  const podcastTestFile = root.querySelector("#surfside-podcast-test-file");
  const podcastTestConfirm = root.querySelector("#surfside-podcast-test-confirm");
  const podcastTestButton = root.querySelector("#surfside-podcast-test-upload");
  const podcastTestResume = root.querySelector("#surfside-podcast-test-resume");
  const podcastTestProgress = root.querySelector("#surfside-podcast-test-progress");
  const podcastTestStatus = root.querySelector("#surfside-podcast-test-status");
  let testBusy = false;
  let active = null;

  const showPodcastPreview = () => {
    if (!podcastSelect) return;
    const option = podcastSelect.selectedOptions[0];
    const selected = Boolean(option && option.value);
    podcastEmpty.hidden = selected;
    podcastDetails.hidden = !selected;
    if (podcastTestButton) {
      podcastTestButton.disabled = testBusy || !selected || option.dataset.podcastReview === "1" ||
        !podcastTestConfirm.checked || !podcastTestFile.files[0];
    }
    if (podcastTestResume) {
      // Resume requires no file input. PHP checks that the staged MP4 exists,
      // has the correct size/type, and does not have an active Queue job.
      podcastTestResume.disabled = testBusy || !selected || option.dataset.podcastReview === "1" ||
        !podcastTestConfirm.checked;
    }
    if (!selected) return;
    podcastTitle.textContent = option.dataset.podcastTitle || "";
    podcastDescription.textContent = option.dataset.podcastDescription || "";
    podcastReview.hidden = option.dataset.podcastReview !== "1";
  };
  if (podcastSelect) {
    podcastSelect.addEventListener("change", showPodcastPreview);
    showPodcastPreview();
  }

  if (podcastTestConfirm) podcastTestConfirm.addEventListener("change", showPodcastPreview);
  if (podcastTestFile) podcastTestFile.addEventListener("change", showPodcastPreview);

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

  // Browser never receives Worker credentials. Status and resume requests
  // are authenticated and signed server-side using the staff WordPress route.
  const monitorTest = async (videoId, say) => {
    for (let i = 0; i < 100; i++) {
      await new Promise((resolve) => setTimeout(resolve, 10000));
      const result = await request("test_status", { video_id: videoId });
      const audio = result.status || "unknown";
      const draft = result.spreaker?.status || "waiting";
      say("Cloudflare audio: " + audio + " | Spreaker test draft: " + draft);
      if (audio === "failed" || ["review_required", "not_configured", "queue_unavailable"].includes(draft)) {
        say("Test needs investigation. Audio: " + audio + ", Spreaker: " + draft +
          ". Check Cloudflare Observability and the test status objects.");
        return;
      }
      if (audio === "ready" && draft === "uploaded") {
        say("End-to-end TEST succeeded. Verify the [TEST - DO NOT PUBLISH] draft in Spreaker, then delete it.");
        return;
      }
    }
    say("Test is taking longer than expected. Check podcast-test-jobs and podcast-test-spreaker in R2.");
  };

  // Admin-only full-pipeline test: browser MP4 -> isolated R2 upload ->
  // verification -> existing Cloudflare Queue/FFmpeg -> Spreaker draft.
  if (podcastTestButton) podcastTestButton.addEventListener("click", async () => {
    const option = podcastSelect?.selectedOptions[0];
    const videoId = option?.value || "";
    const file = podcastTestFile.files[0];
    if (testBusy || !podcastTestConfirm.checked || !/^[A-Za-z0-9_-]{11}$/.test(videoId) ||
        option.dataset.podcastReview === "1" || !file || !/\\.mp4$/i.test(file.name)) {
      podcastTestStatus.textContent = "Select a valid sermon, MP4 and confirmation.";
      return;
    }
    testBusy = true;
    showPodcastPreview();
    podcastTestProgress.style.display = "block";
    podcastTestProgress.value = 0;
    const say = (text) => { podcastTestStatus.textContent = text; };
    try {
      say("Requesting isolated R2 test-upload authorization…");
      const ticket = await request("test_ticket", { video_id: videoId, size: file.size });
      say("Uploading test MP4 directly to R2 (live Roku copy is unchanged)…");
      await new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open("PUT", ticket.upload_url, true);
        xhr.setRequestHeader("Content-Type", "video/mp4");
        xhr.upload.onprogress = (evt) => {
          if (evt.lengthComputable) {
            podcastTestProgress.value = Math.floor(100 * evt.loaded / evt.total);
            say("Uploading test MP4: " + podcastTestProgress.value + "%");
          }
        };
        xhr.onload = () => xhr.status >= 200 && xhr.status < 300
          ? resolve() : reject(new Error("R2 upload returned HTTP " + xhr.status));
        xhr.onerror = () => reject(new Error("Test MP4 could not be uploaded to R2."));
        xhr.send(file);
      });
      say("MP4 uploaded. Verifying R2 and queuing Cloudflare processing…");
      const complete = await request("test_complete", { video_id: videoId });
      if (!complete.verified || !["queued", "processing", "ready"].includes(complete.podcast?.status)) {
        throw new Error("R2 verified, but processing was not confirmed: " +
          (complete.podcast?.status || "unknown"));
      }
      say("R2 verified. Monitoring Cloudflare audio extraction and Spreaker draft…");
      await monitorTest(videoId, say);
    } catch (error) {
      const text = error.message || "End-to-end test failed to start.";
      say(text + (text.includes("(unauthorized)") ?
        " The MP4 is staged in R2. Confirm that WordPress and Cloudflare use the same podcast shared secret; then click Resume verified test MP4. Do not upload again." : ""));
    } finally {
      testBusy = false;
      podcastTestConfirm.checked = false;
      showPodcastPreview();
    }
  });

  // Resume retries only the signed queue handoff for an already-verified MP4.
  // PHP preflights Worker authentication using the existing /status endpoint.
  if (podcastTestResume) podcastTestResume.addEventListener("click", async () => {
    const option = podcastSelect?.selectedOptions[0];
    const videoId = option?.value || "";
    if (testBusy || !podcastTestConfirm.checked || !/^[A-Za-z0-9_-]{11}$/.test(videoId) ||
        option.dataset.podcastReview === "1") return;
    testBusy = true;
    showPodcastPreview();
    const say = (text) => { podcastTestStatus.textContent = text; };
    try {
      say("Checking staged R2 MP4 and Cloudflare authentication…");
      const complete = await request("test_resume", { video_id: videoId });
      if (!complete.verified || !["queued", "processing", "ready"].includes(complete.podcast?.status)) {
        throw new Error("Could not confirm the resumed test job.");
      }
      say("Staged MP4 verified. Monitoring Cloudflare processing and Spreaker draft…");
      await monitorTest(videoId, say);
    } catch (error) {
      say(error.message || "Could not resume the staged test. Check the Worker shared secret.");
    } finally {
      testBusy = false;
      podcastTestConfirm.checked = false;
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
          (completion.podcast?.spreaker_draft_requested
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
