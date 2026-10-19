"""Read-only report exporter security contracts, no network or database writes."""
import importlib.util
from pathlib import Path
import unittest

path = Path(__file__).resolve().parents[1] / "scripts" / "publish_run_reports.py"
spec = importlib.util.spec_from_file_location("run_report_publisher", path)
publisher = importlib.util.module_from_spec(spec)
spec.loader.exec_module(publisher)


class ReportsContract(unittest.TestCase):
    def test_sensitive_fields_and_values_excluded(self):
        data = {
            "bpm": 120, "confidence": 0.78,
            "api_token": "abc123", "audio_path": "H:\\stems\\song.mp3",
            "prompt": "private", "notes": "https://example.com/private",
            "nested": {"instruments": [{"label": "piano", "score": 0.83}]},
        }
        clean = publisher.sanitize(data)
        self.assertEqual(clean["bpm"], 120)
        self.assertNotIn("api_token", clean)
        self.assertNotIn("audio_path", clean)
        self.assertNotIn("prompt", clean)
        self.assertNotIn("notes", clean)
        self.assertEqual(clean["nested"]["instruments"][0]["label"], "piano")

    def test_persistent_state_round_trip(self):
        import tempfile
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "published.json"
            report = {"producer": "assistant", "revision_id": 30}
            digest = publisher._digest(report)
            publisher._save_state(path, {"assistant/profile/run-000153/revision-000030.json": digest})
            self.assertEqual(
                publisher._load_state(path)["assistant/profile/run-000153/revision-000030.json"],
                digest,
            )

    def test_oversize_fallback(self):
        import json
        original = publisher.gh
        observed = {}
        def fake_gh(*args, input_json=None):
            if "--method" not in args:
                raise RuntimeError("404")
            observed.update(input_json)
            return {"content": {}}
        publisher.gh = fake_gh
        try:
            report = {"schema": "ezstudio.run-report.v1", "producer": "lab",
                      "metrics": {"tempo": 120}, "diagnostics": {"scores": list(range(30000))}}
            self.assertEqual(publisher.publish("lab/profile/run-000001.json", report), "published")
            import base64
            payload = json.loads(base64.b64decode(observed["content"]))
            self.assertTrue(payload["truncated"])
            self.assertEqual(payload["metrics"]["tempo"], 120)
        finally:
            publisher.gh = original

    def test_bounded_report(self):
        clean = publisher.sanitize({"items": [{"score": n} for n in range(300)]})
        self.assertEqual(len(clean["items"]), 40)
        self.assertIsNone(publisher.sanitize("person@example.com"))


if __name__ == "__main__":
    unittest.main()
