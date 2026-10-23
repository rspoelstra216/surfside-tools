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
  const testConfirm = root.querySelector("#surfside-podcast-test-confirm");
  const testButton = root.querySelector("#surfside-podcast-test-button");
  const testStatus = root.querySelector("#surfside-podcast-test-status");
  let draftPending = false;
  let active = null;

  const showPodcastPreview = () => {
    if (!podcastSelect) return;
    const option = podcastSelect.selectedOptions[0];
    const selected = Boolean(option && option.value);
    podcastEmpty.hidden = selected;
    podcastDetails.hidden = !selected;
    if (testButton) {
      testButton.disabled = !selected || option.dataset.podcastReview === "1" ||
        !testConfirm.checked || draftPending;
    }
    if (!selected) return;
    podcastTitle.textContent = option.dataset.podcastTitle || "";
    podcastDescription.textContent = option.dataset.podcastDescription || "";
    podcastReview.hidden = option.dataset.podcastReview !== "1";
  };
  if (podcastSelect) {
    podcastSelect.addEventListener("change", () => {
      if (testConfirm) testConfirm.checked = false;
      if (testStatus) testStatus.textContent = "";
      showPodcastPreview();
    });
    showPodcastPreview();
  }
  if (testConfirm) testConfirm.addEventListener("change", showPodcastPreview);

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

  if (testButton) testButton.addEventListener("click", async () => {
    const option = podcastSelect?.selectedOptions[0];
    if (!option?.value || option.dataset.podcastReview === "1" || !testConfirm?.checked || draftPending) return;
    draftPending = true;
    showPodcastPreview();
    testStatus.textContent = "Requesting unpublished Spreaker test draft…";
    try {
      const result = await request("spreaker_draft_test", { video_id: option.value });
      if (result.status !== "queued") throw new Error("Unexpected draft queue response.");
      testStatus.textContent = "TEST draft queued for " + result.video_id +
        ". Check podcast-spreaker/" + result.video_id +
        ".json for uploaded, then inspect Spreaker Drafts. Do not publish the test episode.";
    } catch (error) {
      testStatus.textContent = error.message || "Spreaker test draft request failed.";
    } finally {
      draftPending = false;
      testConfirm.checked = false;
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
