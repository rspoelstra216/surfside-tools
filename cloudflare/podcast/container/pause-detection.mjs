import { spawn } from "node:child_process";

// Pauses refer to the original sermon audio, not the podcast with bumpers.
export function silenceDetectArgs(file) {
  return ["-hide_banner", "-nostdin", "-loglevel", "info", "-i", file,
    "-vn", "-af", "silencedetect=noise=-32dB:d=0.30", "-f", "null", "-"];
}

export function parseSilenceLog(log) {
  const pauses = [];
  let began = null;
  for (const line of log.split(/\r?\n/)) {
    const start = line.match(/silence_start:\s*([0-9]+(?:\.[0-9]+)?)/);
    if (start) began = Number(start[1]);
    const end = line.match(/silence_end:\s*([0-9]+(?:\.[0-9]+)?)/);
    if (end && began !== null) {
      const finished = Number(end[1]);
      if (Number.isFinite(began) && Number.isFinite(finished) &&
          finished - began >= 0.3 && finished - began <= 12) {
        pauses.push({ start: Math.round(began * 100) / 100,
          end: Math.round(finished * 100) / 100 });
      }
      began = null;
    }
  }
  return pauses.slice(0, 3000);
}

// Optional, bounded FFmpeg analysis. Failures must not prevent transcription.
export function detectSpeechPauses(file) {
  return new Promise((resolve, reject) => {
    const child = spawn("ffmpeg", silenceDetectArgs(file),
      { stdio: ["ignore", "ignore", "pipe"] });
    let output = "", settled = false;
    const timeout = setTimeout(() => child.kill("SIGKILL"), 3 * 60 * 1000);
    child.stderr.on("data", (chunk) => {
      output = (output + chunk.toString()).slice(-1_500_000);
    });
    child.once("error", (error) => {
      if (settled) return;
      settled = true; clearTimeout(timeout); reject(error);
    });
    child.once("close", (code) => {
      if (settled) return;
      settled = true; clearTimeout(timeout);
      if (code !== 0) reject(Error("pause_detection_failed"));
      else resolve(parseSilenceLog(output));
    });
  });
}
