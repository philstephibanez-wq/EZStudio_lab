"""Contract: PROFILE phase probes must not affect science or presets."""
from __future__ import annotations
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
PROBE = ROOT / "python/ezstudio/runtime/profile_phase_probe.py"

class ProfilePhaseProbeTests(unittest.TestCase):
    def load(self):
        spec = importlib.util.spec_from_file_location("profile_phase_probe_test", PROBE)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        return module

    def test_sidecar_is_external_to_scientific_results(self):
        with tempfile.TemporaryDirectory() as tmp:
            with patch.dict(os.environ, {"EZSTUDIO_OBSERVABILITY_ROOT": tmp,
                                         "EZSTUDIO_PROFILE_OBSERVABILITY_SESSION": "run-000159"}):
                self.load().mark("clap", "model_start")
            path = Path(tmp) / "profile/phase-events/run-000159/phases.jsonl"
            rows = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines()]
            self.assertEqual(("clap", "model_start"), (rows[0]["module"], rows[0]["phase"]))
            self.assertIn("monotonic_ns", rows[0])
            self.assertEqual(set(rows[0]), {"schema", "module", "phase", "utc", "monotonic_ns", "pid"})

    def test_observability_failure_cannot_stop_science(self):
        with tempfile.TemporaryDirectory() as tmp:
            blocked = Path(tmp) / "blocked"
            blocked.write_text("not a directory", encoding="utf-8")
            with patch.dict(os.environ, {"EZSTUDIO_OBSERVABILITY_ROOT": str(blocked)}):
                self.load().mark("meter", "hpss_start")

if __name__ == "__main__":
    unittest.main()
