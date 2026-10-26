"""Contract tests for opt-in PROFILE scheduler probes and exact result comparisons."""
from __future__ import annotations
import os
import sys
import unittest
from pathlib import Path
from unittest.mock import patch
PROFILE = Path(__file__).resolve().parents[2] / "python/ezstudio/pipeline/profile"
sys.path.insert(0, str(PROFILE))
from optimization_guard import compare_scientific, run_with_probe

class GuardContracts(unittest.TestCase):
    def test_equal_only_timing_differs(self):
        a = {"modules":[{"id":"a","raw":{"score":0.4},"timing":{"elapsed_seconds":2.0}}]}
        b = {"modules":[{"id":"a","raw":{"score":0.4},"timing":{"elapsed_seconds":1.0}}]}
        self.assertTrue(compare_scientific(a,b)["equivalent"])
        b["modules"][0]["raw"]["score"] = 0.5
        self.assertFalse(compare_scientific(a,b)["equivalent"])

    def test_order_and_missing_dimensions_block(self):
        a = {"modules":[{"id":"a"},{"id":"b"}]}
        b = {"modules":[{"id":"b"},{"id":"a"}]}
        self.assertFalse(compare_scientific(a,b)["equivalent"])
        self.assertFalse(compare_scientific(a,{"modules":[{"id":"a"}]})["equivalent"])

    def test_probe_disabled_keeps_executor_identical(self):
        class Config: module_id = "test"
        class Context: audio_sha256 = "test"
        obj = {"status":"ok","timing":{"elapsed_seconds":0.1},"raw":{"answer":1}}
        with patch.dict(os.environ, {"EZSTUDIO_PROFILE_SCHEDULER_PROBE":"0"}):
            self.assertIs(run_with_probe(Config(), Context(), None, lambda *args: obj), obj)

    def test_probe_enabled_keeps_executor_identical(self):
        class Config: module_id = "test"
        class Context: audio_sha256 = "test"
        obj = {"status":"ok","timing":{"elapsed_seconds":0.1},"raw":{"answer":1}}
        with patch.dict(os.environ, {"EZSTUDIO_PROFILE_SCHEDULER_PROBE":"1"}):
            with patch("builtins.print") as output:
                self.assertIs(run_with_probe(Config(), Context(), None, lambda *args: obj), obj)
                self.assertIn("EZSTUDIO_PROFILE_SCHEDULER_PROBE", output.call_args.args[0])

if __name__ == "__main__":
    unittest.main()
