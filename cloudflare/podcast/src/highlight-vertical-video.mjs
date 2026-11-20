export const VERTICAL_RENDER_VERSION = "follow-pan-v4";

// Optional private vertical pilot; does not alter existing preview/export keys.
export function validVerticalClip(clip, anchor) {
  return clip && typeof clip === "object" && !Array.isArray(clip) &&
    Object.keys(clip).every((k) => ["number", "start", "end"].includes(k)) &&
    Number.isInteger(clip.number) && clip.number >= 1 && clip.number <= 10 &&
    typeof clip.start === "number" && Number.isFinite(clip.start) &&
    typeof clip.end === "number" && Number.isFinite(clip.end) &&
    clip.start >= 0 && clip.end <= 4320 &&
    clip.end - clip.start >= 15 && clip.end - clip.start <= 120 &&
    Number.isInteger(anchor) && anchor >= 0 && anchor <= 100;
}

export function verticalKey(id, clip, anchor) {
  if (!/^[A-Za-z0-9_-]{11}$/.test(id) || !validVerticalClip(clip, anchor))
    throw Error("invalid_vertical_clip");
  return `sermon-highlights/vertical/${id}/${clip.number}-${Math.round(clip.start * 100)}-${Math.round(clip.end * 100)}-a${anchor}.mp4`;
}

export function sameVerticalJob(job, clip, anchor) {
  return job?.clip?.number === clip.number &&
    job?.clip?.start === clip.start &&
    job?.clip?.end === clip.end &&
    job?.anchor === anchor;
}

/** Only accept a version reported by the actual Container render, not just the Worker. */
export function verifiedContainerRender(info) {
  return info?.render_version === VERTICAL_RENDER_VERSION;
}

/** Render-version-aware cache: improvements must not return old or unverified MP4s. */
export function currentVerticalRender(job, clip, anchor) {
  return sameVerticalJob(job, clip, anchor) &&
    job.render_version === VERTICAL_RENDER_VERSION;
}
