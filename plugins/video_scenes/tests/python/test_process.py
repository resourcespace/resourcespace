"""Pipeline regressions: real FFmpeg fixtures, synthetic inference and mocked OpenAI."""

import importlib.util
import io
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest.mock import patch

from PIL import Image


spec = importlib.util.spec_from_file_location("video_scene_process", Path(__file__).parents[2] / "scripts/process.py")
worker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(worker)


class Socket:
    def __init__(self, events):
        self.events = iter(events)
        self.sent = []
        self.closed = False

    def send(self, data):
        self.sent.append(json.loads(data))

    def recv(self):
        return json.dumps(next(self.events))

    def settimeout(self, value):
        pass

    def close(self):
        self.closed = True


class WorkerTests(unittest.TestCase):
    @unittest.skipUnless(importlib.util.find_spec("transnetv2_pytorch"), "Install transnetv2-pytorch for real CLI checks")
    def test_real_cli_pipeline_preserves_cfr_and_vfr_frames(self):
        with tempfile.TemporaryDirectory(prefix="rs-scene-cli-") as directory:
            for variable_rate in (False, True):
                video = str(Path(directory) / ("vfr.mkv" if variable_rate else "cfr.mkv"))
                command = ["ffmpeg", "-v", "error", "-y", "-f", "lavfi", "-i",
                           "testsrc2=size=160x90:rate=10:duration=2"]
                if variable_rate:
                    command.extend(["-vf", "setpts='if(lt(N,10),N,10+2*(N-10))/(10*TB)'"])
                command.extend(["-vsync", "0", "-c:v", "ffv1", video])
                subprocess.run(command, check=True, capture_output=True, timeout=30)
                request = {"video": video, "directory": directory, "ffmpeg": shutil.which("ffmpeg"),
                           "ffprobe": shutil.which("ffprobe"), "threshold": 0.5, "api_key": "", "timeout": 120}
                timestamps, _ = worker.media_info(request)
                self.assertEqual(len(timestamps), 21)
                result = worker.process_video(request)
                raw = json.loads(Path(directory, "scenes.json").read_text())
                self.assertGreater(len(result["scenes"]), 0)
                for index, scene in enumerate(result["scenes"]):
                    for field in ("shot_id", "start_frame", "end_frame", "probability"):
                        self.assertEqual(scene[field], raw[index][field])
                    self.assertEqual(scene["start_time"], timestamps[scene["start_frame"]])
                    self.assertEqual(scene["end_time"], timestamps[scene["end_frame"] + 1])
                    self.assertTrue(Path(directory, f"thumb_{index}.jpg").is_file())
                self.assertEqual(Path(directory, "detection.nut").exists(), variable_rate)

    def test_cli_output_maps_zero_based_frames_to_source_times(self):
        scenes = [{"shot_id": 1, "start_frame": 0, "end_frame": 0, "probability": 0.7,
                   "start_time": "0.000", "end_time": "0.000"},
                  {"shot_id": 2, "start_frame": 1, "end_frame": 2, "probability": 0.9,
                   "start_time": "0.040", "end_time": "0.080"}]
        with tempfile.TemporaryDirectory() as directory:
            def run(command, **options):
                if "transnetv2_pytorch" in command:
                    Path(directory, "scenes.json").write_text(json.dumps(scenes))
                return subprocess.CompletedProcess(command, 0, "", "")
            request = {"directory": directory, "video": "fixture", "ffmpeg": "ffmpeg", "ffprobe": "ffprobe", "threshold": 0.5}
            with patch.object(worker.subprocess, "run", side_effect=run):
                result = worker.detect_scenes(request, [0, 0.04, 0.12, 0.16])
            self.assertEqual(result[0]["end_time"], 0.04)  # Single-frame scene retains its duration.
            self.assertEqual(result[1]["start_time"], 0.04)
            self.assertEqual(result[1]["end_time"], 0.16)  # VFR end is exclusive.
            self.assertEqual(result[1]["probability"], 0.9)

    def test_detector_failure_reports_cli_diagnostic(self):
        with tempfile.TemporaryDirectory() as directory:
            request = {"directory": directory, "video": "fixture", "ffmpeg": "ffmpeg", "ffprobe": "ffprobe", "threshold": 0.5}
            with patch.object(worker.subprocess, "run", return_value=
                    subprocess.CompletedProcess([], 1, "", "No module named transnetv2_pytorch")):
                with self.assertRaisesRegex(RuntimeError, "No module named transnetv2_pytorch"):
                    worker.detect_scenes(request, [0, 0.04])

    def test_worker_diagnostics_redact_credentials_and_private_paths(self):
        request = {"api_key": "fixture-secret", "video": "/private/original.mp4", "directory": "/private/workspace"}
        output = io.StringIO()
        with patch.object(worker.sys, "stdin", io.StringIO(json.dumps(request))), \
                patch.object(worker.sys, "stdout", output), \
                patch.object(worker, "process_video", side_effect=RuntimeError(
                    "CLI failed: fixture-secret /private/original.mp4 /private/workspace")):
            self.assertEqual(worker.main(), 1)
        diagnostic = json.loads(output.getvalue())["error"]
        self.assertIn("CLI failed", diagnostic)
        for secret in request.values():
            self.assertNotIn(secret, diagnostic)

    def test_even_frame_samples(self):
        self.assertEqual(worker.sample_times(10, 28), [11, 13, 15, 17, 19, 21, 23, 25, 27])

    def request(self):
        return {"model": "gpt-realtime-2.1", "api_key": "test-placeholder"}

    def completion_events(self, audio):
        events = [{"type": "session.created"}, {"type": "session.updated"}]
        if audio:
            events.append({"type": "input_audio_buffer.committed"})
        events.append({"type": "response.done", "response": {"status": "completed", "output": [
            {"content": [{"type": "output_text", "text": "A red screen changes to blue."}]}]}})
        return events

    def test_one_response_with_both_audio_and_image(self):
        socket = Socket(self.completion_events(True))
        description = worker.realtime_description(self.request(), b"jpeg", b"\x00" * 48000, lambda *a, **k: socket)
        self.assertEqual(description, "A red screen changes to blue.")
        self.assertEqual(sum(e["type"] == "response.create" for e in socket.sent), 1)
        self.assertEqual(sum(e["type"] == "input_audio_buffer.append" for e in socket.sent), 2)
        image = next(e for e in socket.sent if e["type"] == "conversation.item.create")
        self.assertEqual(image["item"]["content"][1]["type"], "input_image")
        self.assertTrue(socket.closed)

    def test_silent_video_uses_image_only(self):
        socket = Socket(self.completion_events(False))
        worker.realtime_description(self.request(), b"jpeg", None, lambda *a, **k: socket)
        self.assertFalse(any(e["type"].startswith("input_audio") for e in socket.sent))

    def test_provider_error_and_incomplete_response_fail(self):
        for event in ({"type": "error", "error": {"message": "private-provider-diagnostic"}},
                      {"type": "response.done", "response": {"status": "incomplete"}}):
            socket = Socket([{"type": "session.created"}, {"type": "session.updated"}, event])
            with self.assertRaises(ValueError) as error:
                worker.realtime_description(self.request(), b"jpeg", None, lambda *a, **k: socket)
            self.assertNotIn("private-provider-diagnostic", str(error.exception))
            self.assertTrue(socket.closed)

    @unittest.skipUnless(shutil.which("ffmpeg") and shutil.which("ffprobe"), "FFmpeg is required")
    def test_real_media_frames_collage_and_audio(self):
        with tempfile.TemporaryDirectory(prefix="rs-scene-test-") as directory:
            path = str(Path(directory) / "clip.mp4")
            subprocess.run(["ffmpeg", "-v", "error", "-y", "-f", "lavfi", "-i", "color=red:s=160x90:r=10:d=1",
                            "-f", "lavfi", "-i", "sine=frequency=440:duration=1", "-shortest", "-c:v", "libx264",
                            "-pix_fmt", "yuv420p", path], check=True, timeout=30)
            request = {"ffmpeg": "ffmpeg", "ffprobe": "ffprobe", "video": path, "directory": directory}
            timestamps, has_audio = worker.media_info(request)
            self.assertTrue(has_audio)
            self.assertEqual(len(timestamps), 11)
            self.assertAlmostEqual(timestamps[-1], 1.0)
            collage = worker.scene_images(request, 0, 0, 1, timestamps[-2], True)
            with Image.open(io.BytesIO(collage)) as image:
                self.assertEqual(image.size, (960, 540))
            with Image.open(Path(directory) / "thumb_0.jpg") as image:
                self.assertEqual(image.size, (320, 180))
            self.assertAlmostEqual(len(worker.extract_audio(request, 0, 1)) / 48000, 1.0, places=1)
            # A final one-frame scene must seek to its presentation time, never beyond EOF.
            worker.scene_images(request, 1, 0.9, 1.0, 0.9, True)

    def test_description_failure_keeps_local_scenes(self):
        with patch.object(worker, "media_info", return_value=([0, 0.04], True)), \
                patch.object(worker, "detect_scenes", return_value=[{
                    "shot_id": 1, "start_frame": 0, "end_frame": 0, "probability": 0.8,
                    "start_time": 0, "end_time": 0.04}]), \
                patch.object(worker, "scene_images", return_value=b"jpeg"), \
                patch.object(worker, "extract_audio", return_value=b"pcm"), \
                patch.object(worker, "realtime_description", side_effect=ValueError("failed")):
            result = worker.process_video({"ffmpeg": "ffmpeg", "ffprobe": "ffprobe", "api_key": "test", "audio": True,
                                           "threshold": 0.5})
        self.assertEqual(result["description_errors"], 1)
        self.assertEqual(result["scenes"][0]["end_frame"], 0)
        self.assertEqual(result["scenes"][0]["description"], "")


if __name__ == "__main__":
    unittest.main()
