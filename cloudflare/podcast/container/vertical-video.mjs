// Offline MP4 portrait composition. Sendcmd operates on FFmpeg video frames
// and follows smoothed per-frame crop coordinates from track-subject.py.
export function verticalFilter(mode, commandFile, cropWidth, height) {
  if (mode === "tracked") {
    if (!Number.isInteger(cropWidth) || cropWidth < 2 ||
        !Number.isInteger(height) || height < 180 ||
        !/^\/tmp\/[A-Za-z0-9_/-]+\.txt$/.test(commandFile))
      throw Error("invalid_vertical_tracking");
    return [
      "fps=24",
      `sendcmd=f=${commandFile}`,
      `crop@subject=w=${cropWidth}:h=${height}:x=0:y=0`,
      "scale=720:1280:flags=lanczos",
      "setsar=1",
    ].join(",");
  }
  if (mode !== "wide_fallback") throw Error("invalid_vertical_mode");
  // Retain the entire wide shot when no trustworthy moving face is found.
  // A blurred background fills the portrait canvas without losing subjects.
  return [
    "fps=24,split=2[background][foreground]",
    "[background]scale=720:1280:force_original_aspect_ratio=increase," +
      "crop=720:1280,boxblur=24:2[bg]",
    "[foreground]scale=720:-2[fg]",
    "[bg][fg]overlay=(W-w)/2:(H-h)/2:shortest=1,setsar=1",
  ].join(";");
}

export function verticalRenderArgs(source, output, filter) {
  return [
    "-hide_banner", "-nostdin", "-loglevel", "error", "-y",
    "-i", source, "-filter_complex", `[0:v]${filter}[portrait]`,
    "-map", "[portrait]", "-map", "0:a:0?", "-sn", "-dn",
    "-c:v", "libx264", "-preset", "veryfast", "-crf", "23",
    "-maxrate", "3000k", "-bufsize", "6000k", "-pix_fmt", "yuv420p",
    "-c:a", "aac", "-b:a", "128k", "-ar", "44100",
    "-movflags", "+faststart", "-shortest", output,
  ];
}

export function verticalExtractArgs(source, output, start, end) {
  if (!Number.isFinite(start) || !Number.isFinite(end) ||
      start < 0 || end > 4320 || end - start < 15 || end - start > 120)
    throw Error("invalid_vertical_cut");
  return [
    "-hide_banner", "-nostdin", "-loglevel", "error", "-y",
    "-ss", String(start), "-i", source,
    "-t", String(Math.round((end - start) * 100) / 100),
    "-map", "0:v:0", "-map", "0:a:0?", "-sn", "-dn",
    "-c:v", "libx264", "-preset", "veryfast", "-crf", "19",
    "-pix_fmt", "yuv420p", "-c:a", "aac", "-b:a", "160k",
    "-movflags", "+faststart", "-avoid_negative_ts", "make_zero", output,
  ];
}

/** Downsample only the TRACKING input; final MP4 still uses the original-quality approved cut. */
export function verticalTrackingProxyArgs(source, proxy) {
  return [
    "-hide_banner", "-nostdin", "-loglevel", "error", "-y",
    "-i", source, "-map", "0:v:0",
    "-vf", "fps=2,scale=480:-2:flags=fast_bilinear",
    "-an", "-sn", "-dn", "-c:v", "libx264",
    "-preset", "ultrafast", "-crf", "32", "-pix_fmt", "yuv420p",
    "-movflags", "+faststart", proxy,
  ];
}

/** Validate video dimensions measured by ffprobe before scaling tracking into the source frame. */
export function parseVerticalDimensions(text) {
  const match = /^(\d{3,5})x(\d{3,5})$/.exec(String(text).trim());
  if (!match) throw Error("invalid_vertical_dimensions");
  const width = Number(match[1]), height = Number(match[2]);
  if (width < 320 || width > 8192 || height < 180 || height > 4320)
    throw Error("invalid_vertical_dimensions");
  return { width, height };
}
