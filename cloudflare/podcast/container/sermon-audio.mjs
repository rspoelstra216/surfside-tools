// Pure FFmpeg argument builders for the podcast's optional sermon-only audio sidecar.
// Source is the original MP4 audio stream, never the bumper-inclusive podcast MP3.
export const sermonAudioKey = (id, test = false) =>
  (test ? "podcast-test-sermon-audio/" : "sermon-audio/") + id + ".mp3";

export function podcastAudioArgs(intro, sermon, outro, output, sermonOnly = null) {
  // Retain the established podcast processing chain without changing normalization.
  const a0 = "[0:a:0]aresample=44100,aformat=sample_fmts=fltp:channel_layouts=stereo,asetpts=PTS-STARTPTS[a0];";
  const a2 = "[2:a:0]aresample=44100,aformat=sample_fmts=fltp:channel_layouts=stereo,asetpts=PTS-STARTPTS[a2];";
  const a1 = sermonOnly
    ? "[1:a:0]asplit=2[sermon_podcast][sermon_raw];" +
      "[sermon_podcast]aresample=44100,aformat=sample_fmts=fltp:channel_layouts=stereo,asetpts=PTS-STARTPTS[a1];" +
      "[sermon_raw]aresample=async=1:first_pts=0,aformat=sample_fmts=fltp:channel_layouts=mono,asetpts=PTS-STARTPTS[raw];"
    : "[1:a:0]aresample=44100,aformat=sample_fmts=fltp:channel_layouts=stereo,asetpts=PTS-STARTPTS[a1];";
  const args = [
    "-hide_banner", "-nostdin", "-loglevel", "error", "-y",
    "-i", intro, "-i", sermon, "-i", outro,
    "-filter_complex",
    a0 + a1 + a2 + "[a0][a1][a2]concat=n=3:v=0:a=1,loudnorm=I=-16:TP=-1.5:LRA=11[out]",
    "-map", "[out]", "-vn", "-c:a", "libmp3lame", "-b:a", "128k",
    "-ar", "44100", "-ac", "2", output,
  ];
  if (sermonOnly) {
    args.push("-map", "[raw]", "-vn", "-c:a", "libmp3lame", "-b:a", "48k",
      "-ar", "16000", "-ac", "1", sermonOnly);
  }
  return args;
}

export function extractSermonAudioArgs(source, output) {
  return [
    "-hide_banner", "-nostdin", "-loglevel", "error", "-y",
    "-i", source, "-map", "0:a:0", "-vn",
    "-af", "aresample=async=1:first_pts=0",
    "-ac", "1", "-ar", "16000", "-c:a", "libmp3lame", "-b:a", "48k",
    output,
  ];
}

export function chunkSermonAudioArgs(source, pattern) {
  // Already 16 kHz mono MP3. Splitting compressed frames avoids another encode.
  return [
    "-hide_banner", "-nostdin", "-loglevel", "error", "-y",
    "-i", source, "-map", "0:a:0", "-vn", "-c:a", "copy",
    "-f", "segment", "-segment_time", "180", "-reset_timestamps", "1", pattern,
  ];
}
