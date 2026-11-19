// Render a deliberately selected piece of the original sermon MP4 for review.
// These are unpublished private previews, not social-media deliverables.
export const previewKey = (id, clip) => {
  const start = Math.round(clip.start * 100);
  const end = Math.round(clip.end * 100);
  return `sermon-highlights/previews/${id}/${clip.number}-${start}-${end}.mp4`;
};

// Immutable final export key, distinct from the padded review previews.
export const exportKey = (id, clip) =>
  `sermon-highlights/exports/${id}/${clip.number}-${Math.round(clip.start * 100)}-${Math.round(clip.end * 100)}.mp4`;

export function clipVideoArgs(source, output, start, end) {
  if (!Number.isFinite(start) || !Number.isFinite(end) || start < 0 ||
      end <= start || end - start > 120 || end > 72 * 60)
    throw Error("invalid_clip_times");
  // Accurate seeking with transcoding; stream copy would cut at nearby keyframes.
  // Keep 720p-ish review files small, with playable H.264/AAC and faststart.
  return [
    "-hide_banner", "-nostdin", "-loglevel", "error", "-y",
    "-ss", String(start), "-i", source, "-t", String(Math.round((end - start) * 100) / 100),
    "-map", "0:v:0", "-map", "0:a:0?", "-sn", "-dn",
    "-vf", "scale=trunc(min(1280\\,iw)/2)*2:-2",
    "-c:v", "libx264", "-preset", "veryfast", "-crf", "25",
    "-maxrate", "2500k", "-bufsize", "5000k", "-pix_fmt", "yuv420p",
    "-c:a", "aac", "-b:a", "128k", "-ar", "44100",
    "-movflags", "+faststart", "-avoid_negative_ts", "make_zero", output,
  ];
}
