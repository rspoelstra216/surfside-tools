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
