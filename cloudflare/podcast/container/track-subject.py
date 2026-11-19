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
            fps < 8 or width < 320 or height < 180 or total <= 0):
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
    attempts = max(1, int(math.ceil(duration * SAMPLE_RATE)))

    try:
        for i in range(attempts):
            seconds = i / SAMPLE_RATE
            cap.set(cv2.CAP_PROP_POS_MSEC, seconds * 1000)
            okay, frame = cap.read()
            if not okay:
                break
            image = cv2.resize(frame, (work_w, work_h),
                               interpolation=cv2.INTER_AREA)
            gray = cv2.equalizeHist(cv2.cvtColor(image, cv2.COLOR_BGR2GRAY))
            target = previous_x
            flowed = False

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
                if i == 0 or abs(candidate_x - target) < work_w * 0.34:
                    chosen = candidate
                    target = candidate_x
                    face_samples += 1
            if flowed and chosen is None:
                feature_samples += 1

            # Refresh tracker features around the chosen face. If the face is
            # missing, retain last detection and smoothly interpolate instead.
            points = None
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


def make_commands(track, output):
    width, height = track["width"], track["height"]
    crop_w = max(2, int(height * 9 / 16) // 2 * 2)
    if crop_w >= width:
        raise RuntimeError("vertical_source_already_narrow")
    confidence = track["face_samples"] / max(1, track["samples"])
    mode = "tracked" if confidence >= 0.12 else "wide_fallback"
    # Avoid a static crop if the visual detector cannot reliably identify
    # the moving subject. Full-width-in-portrait is the safe beta fallback.
    if mode == "wide_fallback":
        with open(output, "w", encoding="utf-8") as handle:
            handle.write("0.000 crop@subject x 0;\n")
        return {"mode": mode, "confidence": round(confidence, 3),
                "face_samples": track["face_samples"],
                "samples": track["samples"], "crop_width": crop_w, "height": height}

    step = 1 / 8
    maximum_x = width - crop_w
    previous = max(0, min(maximum_x, track["initial_center"] - crop_w / 2))
    # Smooth at 8 updates/sec, constrain panning velocity to avoid whip-pans.
    max_delta = width * 0.28 * step
    with open(output, "w", encoding="utf-8") as handle:
        index = 0
        count = int(math.ceil(track["duration"] / step))
        for i in range(count):
            seconds = i * step
            while index + 1 < len(track["times"]) and (
                    track["times"][index + 1] <= seconds):
                index += 1
            j = min(index + 1, len(track["times"]) - 1)
            left_t, right_t = track["times"][index], track["times"][j]
            ratio = 0 if right_t <= left_t else max(
                0, min(1, (seconds - left_t) / (right_t - left_t)))
            # Smoothstep reduces abrupt changes when detections return.
            ratio = ratio * ratio * (3 - 2 * ratio)
            center = (track["centers"][index] * (1 - ratio) +
                      track["centers"][j] * ratio)
            wanted = max(0, min(maximum_x, center - crop_w / 2))
            previous += max(-max_delta, min(max_delta, wanted - previous))
            x = max(0, min(maximum_x, int(round(previous / 2)) * 2))
            handle.write(f"{seconds:.3f} crop@subject x {x};\n")
    return {"mode": mode, "confidence": round(confidence, 3),
            "face_samples": track["face_samples"],
            "samples": track["samples"], "crop_width": crop_w, "height": height}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("source")
    parser.add_argument("commands")
    parser.add_argument("anchor", type=int)
    args = parser.parse_args()
    if not 0 <= args.anchor <= 100:
        raise ValueError("invalid_subject_anchor")
    tracked = tracked_centers(args.source, args.anchor)
    result = make_commands(tracked, args.commands)
    print(json.dumps(result, separators=(",", ":")))


if __name__ == "__main__":
    try:
        main()
    except (ValueError, RuntimeError) as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(2)
