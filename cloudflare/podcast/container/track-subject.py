#!/usr/bin/python3
"""Offline, conservative moving-face tracking for an opt-in church video pilot.

Never identifies a person; only follows a nearby face/visual feature from a
staff-supplied horizontal starting position. The script writes FFmpeg sendcmd
events so final H.264/AAC encoding remains in FFmpeg, not OpenCV VideoWriter.
No video frames or identity information leave this Container.
"""
import argparse
import json
import math
import os
import statistics
import sys

import cv2
import numpy as np

SAMPLE_RATE = 2.0
OUTPUT_FPS = 24
OUTPUT_W = 720
OUTPUT_H = 1280


def cascade(name):
    candidates = [
        os.path.join(getattr(getattr(cv2, "data", None), "haarcascades", ""), name),
        os.path.join("/usr/share/opencv4/haarcascades", name),
        os.path.join("/usr/share/opencv/haarcascades", name),
    ]
    for candidate in candidates:
        if os.path.isfile(candidate):
            detector = cv2.CascadeClassifier(candidate)
            if not detector.empty():
                return detector
    return None


def frame_faces(gray, frontal, profile):
    found = list(frontal.detectMultiScale(
        gray, scaleFactor=1.12, minNeighbors=4,
        minSize=(21, 21))) if frontal else []
    if profile is not None:
        found.extend(profile.detectMultiScale(
            gray, scaleFactor=1.16, minNeighbors=4,
            minSize=(23, 23)))
        mirrored = cv2.flip(gray, 1)
        for x, y, w, h in profile.detectMultiScale(
                mirrored, scaleFactor=1.16, minNeighbors=4,
                minSize=(23, 23)):
            found.append((gray.shape[1] - x - w, y, w, h))
    return found


def tracked_centers(video, anchor_pct):
    cap = cv2.VideoCapture(video)
    if not cap.isOpened():
        raise RuntimeError("vertical_source_unreadable")
    fps = cap.get(cv2.CAP_PROP_FPS)
    total = cap.get(cv2.CAP_PROP_FRAME_COUNT)
    width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
    height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
    if not all(math.isfinite(x) for x in (fps, total, width, height)) or (
            (fps < 1.95 or (fps > 2.05 and fps < 8)) or
            width < 320 or height < 180 or total <= 0):
        cap.release()
        raise RuntimeError("vertical_source_invalid")
    duration = total / fps
    if duration > 121:
        cap.release()
        raise RuntimeError("vertical_source_too_long")
    frontal = cascade("haarcascade_frontalface_default.xml")
    profile = cascade("haarcascade_profileface.xml")
    if frontal is None:
        cap.release()
        raise RuntimeError("vertical_face_detector_missing")

    work_w = min(720, width)
    work_h = int(round(height * work_w / width))
    prior_gray = None
    points = None
    previous_x = work_w * anchor_pct / 100
    initial_x = previous_x
    times, centers = [], []
    face_samples = 0
    feature_samples = 0
    missed_face_steps = 0
    attempts = max(1, int(math.ceil(duration * SAMPLE_RATE)))

    try:
        for i in range(attempts):
            seconds = i / SAMPLE_RATE
            # A 2-fps proxy needs only sequential reads. Repeated timestamp
            # seeks previously forced FFmpeg to decode from nearby keyframes
            # for each sample, doing far more work on the full-resolution MP4.
            if not 1.95 <= fps <= 2.05:
                cap.set(cv2.CAP_PROP_POS_MSEC, seconds * 1000)
            okay, frame = cap.read()
            if not okay:
                break
            image = cv2.resize(frame, (work_w, work_h),
                               interpolation=cv2.INTER_AREA)
            gray = cv2.equalizeHist(cv2.cvtColor(image, cv2.COLOR_BGR2GRAY))
            target = previous_x
            flowed = False
            flow_points = None

            # Optical flow bridges speaker movement through profile turns.
            if prior_gray is not None and points is not None and len(points) >= 6:
                try:
                    next_points, good, _ = cv2.calcOpticalFlowPyrLK(
                        prior_gray, gray, points, None,
                        winSize=(27, 27), maxLevel=3,
                        criteria=(cv2.TERM_CRITERIA_EPS |
                                  cv2.TERM_CRITERIA_COUNT, 20, 0.02))
                    if next_points is not None and good is not None:
                        kept = good.reshape(-1) == 1
                        original = points.reshape(-1, 2)[kept]
                        updated = next_points.reshape(-1, 2)[kept]
                        if len(updated) >= 6:
                            dx = statistics.median((updated - original)[:, 0])
                            if abs(dx) <= work_w * 0.22:
                                target = max(0, min(work_w, target + dx))
                                flowed = True
                                flow_points = updated.reshape(-1, 1, 2).astype(np.float32)
                except cv2.error:
                    pass

            faces = frame_faces(gray, frontal, profile)
            chosen = None
            if faces:
                # Anchor/continuity prevent switching to another stage person.
                faces = sorted(faces, key=lambda face: (
                    abs(face[0] + face[2] / 2 - target) / work_w -
                    0.12 * face[2] / work_w))
                candidate = faces[0]
                candidate_x = candidate[0] + candidate[2] / 2
                # A sudden faraway face is probably a different person.
                if abs(candidate_x - target) < work_w * 0.34:
                    chosen = candidate
                    target = candidate_x
                    face_samples += 1
            if chosen is not None:
                missed_face_steps = 0
            else:
                missed_face_steps += 1
                if flowed and missed_face_steps <= 8:
                    feature_samples += 1

            # Refresh features when face returns; retain the last tracked
            # points through short profile turns, but expire them after four
            # seconds to prevent gradually drifting to somebody else.
            points = flow_points if flowed and missed_face_steps <= 8 else None
            if chosen is not None:
                x, y, w, h = (int(z) for z in chosen)
                pad = max(6, int(w * 0.4))
                x1, x2 = max(0, x - pad), min(work_w, x + w + pad)
                y1, y2 = max(0, y - pad), min(work_h, y + h + pad)
                region = gray[y1:y2, x1:x2]
                if region.size:
                    corners = cv2.goodFeaturesToTrack(
                        region, maxCorners=60, qualityLevel=0.02,
                        minDistance=5)
                    if corners is not None:
                        corners[:, :, 0] += x1
                        corners[:, :, 1] += y1
                        points = corners
            previous_x = max(0, min(work_w, target))
            prior_gray = gray
            times.append(seconds)
            centers.append(previous_x / work_w * width)
    finally:
        cap.release()
    if not times:
        raise RuntimeError("vertical_frames_missing")
    return {
        "duration": duration, "width": width, "height": height,
        "times": times, "centers": centers,
        "face_samples": face_samples, "feature_samples": feature_samples,
        "samples": len(times),
        "initial_center": initial_x / work_w * width,
    }


def median_filtered_centers(centers):
    """Reject single-sample face swaps without blunting genuine walks."""
    if len(centers) < 3:
        return list(centers)
    result = list(centers)
    for i in range(1, len(centers) - 1):
        result[i] = statistics.median(centers[i - 1:i + 2])
    return result


def camera_positions(track, fps=OUTPUT_FPS):
    """A virtual camera operator: center-safe-zone, eased 24fps pans.

    Face detection remains 2Hz, but the *crop* must move on every 24fps
    output frame. Frequent FFmpeg 8Hz jumps previously produced ~70px
    discontinuities in the portrait output.
    """
    if fps != OUTPUT_FPS or track["duration"] <= 0:
        raise ValueError("invalid_camera_fps")
    width, height = track["width"], track["height"]
    crop_w = max(2, int(height * 9 / 16) // 2 * 2)
    if crop_w >= width:
        raise RuntimeError("vertical_source_already_narrow")
    times = track["times"]
    centers = median_filtered_centers(track["centers"])
    if len(times) != len(centers) or not times:
        raise ValueError("invalid_tracking_samples")
    if any(not math.isfinite(v) for v in times + centers):
        raise ValueError("invalid_tracking_samples")

    dt = 1.0 / fps
    safe_radius = crop_w * 0.14  # Subject can move inside central 28%.
    max_x = width - crop_w
    position = max(0.0, min(max_x, track["initial_center"] - crop_w / 2))
    velocity = 0.0
    index = 0
    positions = []
    count = max(1, int(math.ceil(track["duration"] * fps)))
    for i in range(count):
        t = i * dt
        while index + 1 < len(times) and times[index + 1] <= t:
            index += 1
        right = min(index + 1, len(times) - 1)
        span = times[right] - times[index]
        mix = 0 if span <= 0 else max(0.0, min(1.0, (t - times[index]) / span))
        # The detected target moves between 2Hz samples. Interpolate it,
        # then move the virtual camera continuously with limited velocity
        # and acceleration rather than issuing discrete pan jumps.
        subject = centers[index] * (1 - mix) + centers[right] * mix
        offset = subject - (position + crop_w / 2)
        if abs(offset) <= safe_radius:
            desired = position
        else:
            desired = max(0.0, min(max_x,
                subject - math.copysign(safe_radius * 0.72, offset) -
                crop_w / 2))

        # Allow a moderately faster *smooth* catch-up only when the speaker
        # is approaching the edge. In normal motion the frame is deliberately
        # more stable than the face detections.
        urgent = abs(offset) > crop_w * 0.4
        max_speed = width * (0.20 if urgent else 0.12)
        max_accel = width * (0.58 if urgent else 0.34)
        requested_velocity = max(-max_speed, min(max_speed,
            (desired - position) * (1.7 if urgent else 1.1)))
        acceleration = max(-max_accel * dt, min(max_accel * dt,
            requested_velocity - velocity))
        velocity += acceleration
        position = max(0.0, min(max_x, position + velocity * dt))
        if position <= 0 and velocity < 0 or position >= max_x and velocity > 0:
            velocity = 0.0
        # Two-pixel alignment preserves YUV420 chroma sampling at source.
        x = max(0, min(max_x, int(round(position / 2)) * 2))
        positions.append(x)
    return crop_w, positions


def make_commands(track, output):
    width, height = track["width"], track["height"]
    crop_w = max(2, int(height * 9 / 16) // 2 * 2)
    if crop_w >= width:
        raise RuntimeError("vertical_source_already_narrow")
    confidence = (track["face_samples"] +
                  0.5 * track.get("feature_samples", 0)) / max(1, track["samples"])
    mode = "tracked" if confidence >= 0.12 else "wide_fallback"
    if mode == "wide_fallback":
        with open(output, "w", encoding="utf-8") as handle:
            handle.write("0.000 crop@subject x 0;\n")
        return {"mode": mode, "confidence": round(confidence, 3),
                "face_samples": track["face_samples"],
                "samples": track["samples"], "crop_width": crop_w, "height": height}

    crop_w, positions = camera_positions(track)
    # FFmpeg applies the commands after its fps=24 filter. Using one command
    # per frame produces a continuous pan instead of the former 8Hz steps.
    with open(output, "w", encoding="utf-8") as handle:
        for frame, x in enumerate(positions):
            # Schedule just ahead of each frame timestamp: FFmpeg timestamps
            # are rational 1/24 seconds, and rounding .041667 upward could
            # delay an update to the following frame and combine two pans.
            at = max(0.0, frame / OUTPUT_FPS - 0.001)
            handle.write(f"{at:.6f} crop@subject x {x};\n")
    max_jump = max((abs(a - b) for a, b in zip(positions, positions[1:])),
                   default=0)
    return {"mode": mode, "confidence": round(confidence, 3),
            "face_samples": track["face_samples"],
            "samples": track["samples"], "crop_width": crop_w,
            "height": height, "camera_fps": OUTPUT_FPS,
            "max_source_pan_px_per_frame": max_jump}


def scale_to_source(track, render_width, render_height):
    """Map proxy-face x coordinates back to the full-quality source frame."""
    if (render_width < 320 or render_width > 8192 or
            render_height < 180 or render_height > 4320):
        raise ValueError("invalid_render_dimensions")
    old_ratio = track["width"] / track["height"]
    new_ratio = render_width / render_height
    if abs(old_ratio - new_ratio) / new_ratio > 0.04:
        raise ValueError("tracking_proxy_aspect_mismatch")
    scale_x = render_width / track["width"]
    return {
        **track,
        "centers": [x * scale_x for x in track["centers"]],
        "initial_center": track["initial_center"] * scale_x,
        "width": render_width,
        "height": render_height,
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("source")
    parser.add_argument("commands")
    parser.add_argument("anchor", type=int)
    parser.add_argument("render_width", nargs="?", type=int)
    parser.add_argument("render_height", nargs="?", type=int)
    args = parser.parse_args()
    if not 0 <= args.anchor <= 100:
        raise ValueError("invalid_subject_anchor")
    tracked = tracked_centers(args.source, args.anchor)
    if args.render_width is not None or args.render_height is not None:
        if args.render_width is None or args.render_height is None:
            raise ValueError("invalid_render_dimensions")
        tracked = scale_to_source(
            tracked, args.render_width, args.render_height)
    result = make_commands(tracked, args.commands)
    print(json.dumps(result, separators=(",", ":")))


if __name__ == "__main__":
    try:
        main()
    except (ValueError, RuntimeError) as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(2)
