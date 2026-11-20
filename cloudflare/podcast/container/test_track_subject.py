"""Offline pilot checks for speaker tracking and safe framing fallback."""
import importlib.util
import pathlib
import shutil
import subprocess
import tempfile
import unittest

SOURCE = pathlib.Path(__file__).with_name("track-subject.py")
SPEC = importlib.util.spec_from_file_location("surfside_subject_tracker", SOURCE)
module = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(module)


class TrackerTests(unittest.TestCase):
    def test_detector_bundled_in_image(self):
        self.assertIsNotNone(
            module.cascade("haarcascade_frontalface_default.xml"))

    def test_motion_creates_gradual_commands_in_valid_crop_bounds(self):
        track = {
            "width": 1920, "height": 1080, "duration": 3,
            "times": [0, 0.5, 1, 1.5, 2, 2.5],
            "centers": [450, 600, 760, 950, 1130, 1300],
            "initial_center": 450,
            "face_samples": 5, "feature_samples": 1, "samples": 6,
        }
        with tempfile.TemporaryDirectory() as folder:
            commands = pathlib.Path(folder) / "sendcmd.txt"
            result = module.make_commands(track, str(commands))
            self.assertEqual(result["mode"], "tracked")
            lines = commands.read_text().strip().splitlines()
            self.assertGreater(len(lines), 15)
            x = [int(line.split(" x ")[-1].rstrip(";")) for line in lines]
            self.assertTrue(all(0 <= n <= 1920 - result["crop_width"] for n in x))
            self.assertGreater(x[-1], x[0])
            self.assertTrue(all(abs(a - b) < 80 for a, b in zip(x, x[1:])))
            self.assertTrue(all(line.endswith(";") for line in lines))

    def test_smooth_camera_is_stable_when_pastor_stays_near_center(self):
        track = {
            "width": 1920, "height": 1080, "duration": 5,
            "times": [i * 0.5 for i in range(10)],
            "centers": [960, 974, 940, 971, 956, 983, 960, 948, 972, 959],
            "initial_center": 960,
            "face_samples": 10, "feature_samples": 0, "samples": 10,
        }
        crop_width, points = module.camera_positions(track)
        self.assertEqual(crop_width, 606)  # nearest even 9:16 crop
        self.assertEqual(len(points), 120)  # one update per 24fps frame
        self.assertEqual(len(set(points)), 1, "minor face jitter must not pan the camera")
        with tempfile.TemporaryDirectory() as folder:
            commands = pathlib.Path(folder) / "commands.txt"
            result = module.make_commands(track, str(commands))
            lines = commands.read_text().strip().splitlines()
            self.assertEqual(len(lines), 120)
            self.assertIn("0.040667 crop@subject x", lines[1])
            # Command times precede the corresponding exact 24fps PTS,
            # avoiding frame-late jumps from decimal timestamp rounding.
            for i, line in enumerate(lines[1:], 1):
                at = float(line.split()[0])
                self.assertGreater(at, (i - 1) / 24)
                self.assertLess(at, i / 24)
            self.assertEqual(result["camera_fps"], 24)
            self.assertEqual(result["max_source_pan_px_per_frame"], 0)

    def test_smooth_camera_tracks_real_walk_without_8hz_jump_cuts(self):
        track = {
            "width": 1920, "height": 1080, "duration": 7,
            "times": [i * 0.5 for i in range(14)],
            "centers": [660, 690, 750, 870, 980, 1120, 1290, 1470,
                        1480, 1510, 1390, 1250, 1130, 1070],
            "initial_center": 660,
            "face_samples": 14, "feature_samples": 0, "samples": 14,
        }
        width, positions = module.camera_positions(track)
        self.assertEqual(len(positions), 168)
        self.assertGreater(max(positions) - min(positions), 200)
        jumps = [abs(a - b) for a, b in zip(positions, positions[1:])]
        self.assertLessEqual(max(jumps), 18, "each camera step must be bounded")
        self.assertGreater(sum(1 for x in jumps if x), 35,
            "camera pan must use many small steps, not 8Hz teleports")
        self.assertTrue(all(x % 2 == 0 for x in positions))

    def test_camera_catches_up_earlier_during_sustained_stage_walk(self):
        # Representative 1920x1080 horizontal movement: hold center for
        # two seconds, then walk to stage right and stop. The old camera
        # allowed >230 source-pixel offsets near t=3.5; follow-up should
        # stay closer while retaining smooth individual frame steps.
        track = {
            "width": 1920, "height": 1080, "duration": 8,
            "times": [i * 0.5 for i in range(16)],
            "centers": [960, 960, 960, 960, 1020, 1100, 1240, 1370,
                        1490, 1520, 1500, 1500, 1500, 1500, 1500, 1500],
            "initial_center": 960,
            "face_samples": 16, "feature_samples": 0, "samples": 16,
        }
        crop_width, positions = module.camera_positions(track)
        self.assertEqual(len(positions), 192)
        self.assertLessEqual(max(abs(b - a) for a, b in
                                 zip(positions, positions[1:])), 18)
        target_at_3_5s = track["centers"][7]
        camera_center = positions[84] + crop_width / 2
        self.assertLess(abs(target_at_3_5s - camera_center), 205,
                        "walking pastor should not linger near frame edge")
        # Once the pastor stops, settle toward the middle, with no
        # substantial hunting or momentum-driven overshoot.
        settled = positions[round(6.5 * 24):]
        self.assertLessEqual(max(settled) - min(settled), 6)

    def test_single_false_face_does_not_throw_camera_across_stage(self):
        track = {
            "width": 1920, "height": 1080, "duration": 3,
            "times": [0, 0.5, 1, 1.5, 2, 2.5],
            "centers": [960, 962, 965, 1650, 963, 961],
            "initial_center": 960,
            "face_samples": 6, "feature_samples": 0, "samples": 6,
        }
        self.assertEqual(module.median_filtered_centers(track["centers"])[3], 965)
        _, positions = module.camera_positions(track)
        self.assertLessEqual(max(positions) - min(positions), 6,
            "single false detection must not cause a whip-pan")

    def test_face_detection_failure_uses_safe_wide_background(self):
        track = {
            "width": 1280, "height": 720, "duration": 2,
            "times": [0, 0.5, 1, 1.5],
            "centers": [640] * 4,
            "initial_center": 640,
            "face_samples": 0, "feature_samples": 0, "samples": 4,
        }
        with tempfile.TemporaryDirectory() as folder:
            result = module.make_commands(track, str(pathlib.Path(folder) / "cmd.txt"))
            self.assertEqual(result["mode"], "wide_fallback")
            self.assertEqual(result["confidence"], 0)
            self.assertEqual(result["height"], 720)

    def test_tracking_coordinates_scale_to_full_quality_source(self):
        tiny = {
            "width": 480, "height": 270, "duration": 2,
            "times": [0, 0.5, 1, 1.5],
            "centers": [120, 170, 240, 360],
            "initial_center": 120,
            "face_samples": 3, "feature_samples": 0, "samples": 4,
        }
        mapped = module.scale_to_source(tiny, 1920, 1080)
        self.assertEqual(mapped["centers"], [480, 680, 960, 1440])
        self.assertEqual(mapped["initial_center"], 480)
        self.assertEqual(mapped["height"], 1080)
        self.assertEqual(tiny["width"], 480)
        with self.assertRaisesRegex(ValueError, "tracking_proxy_aspect_mismatch"):
            module.scale_to_source(tiny, 1920, 800)

    @unittest.skipUnless(shutil.which("ffmpeg"), "FFmpeg not installed")
    def test_sequential_proxy_frames_and_full_source_mapping(self):
        with tempfile.TemporaryDirectory() as folder:
            file = pathlib.Path(folder) / "proxy.mp4"
            subprocess.run([
                "ffmpeg", "-hide_banner", "-loglevel", "error", "-y",
                "-f", "lavfi", "-i",
                "color=c=gray:size=480x270:rate=2:duration=4",
                "-c:v", "libx264", "-preset", "ultrafast", str(file),
            ], check=True, timeout=30)
            result = module.tracked_centers(str(file), 53)
            self.assertEqual(result["samples"], 8)
            self.assertEqual(result["face_samples"], 0)
            mapped = module.scale_to_source(result, 1920, 1080)
            self.assertAlmostEqual(mapped["initial_center"], 1017.6)
            with tempfile.TemporaryDirectory() as out:
                info = module.make_commands(
                    mapped, str(pathlib.Path(out) / "commands.txt"))
                self.assertEqual(info["mode"], "wide_fallback")
                self.assertEqual(info["height"], 1080)

    @unittest.skipUnless(shutil.which("ffmpeg"), "FFmpeg not installed")
    def test_real_video_without_face_enters_safe_fallback(self):
        with tempfile.TemporaryDirectory() as folder:
            file = pathlib.Path(folder) / "source.mp4"
            subprocess.run([
                "ffmpeg", "-hide_banner", "-loglevel", "error", "-y",
                "-f", "lavfi", "-i",
                "color=c=gray:size=640x360:rate=24:duration=2",
                "-c:v", "libx264", "-preset", "ultrafast", str(file),
            ], check=True, timeout=30)
            result = module.tracked_centers(str(file), 50)
            self.assertEqual(result["width"], 640)
            self.assertEqual(result["face_samples"], 0)
            self.assertEqual(result["samples"], 4)


if __name__ == "__main__":
    unittest.main()
