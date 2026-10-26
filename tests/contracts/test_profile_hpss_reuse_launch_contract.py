"""PROFILE-only launcher activation contract for run-local HPSS reuse."""
import ast
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ENTRY = ROOT / "analysis/worker_entrypoint.py"


class ProfileHPSSReuseLaunchContract(unittest.TestCase):
    def test_scoped_to_profile_and_passed_to_child(self):
        src = ENTRY.read_text(encoding="utf-8")
        ast.parse(src)
        prefix, rest = src.split('    if kind == "profile":', 1)
        profile, suffix = rest.split('    if kind == "stems":', 1)
        self.assertNotIn("EZSTUDIO_PROFILE_HPSS_REUSE", prefix + suffix)
        self.assertIn('env.setdefault("EZSTUDIO_PROFILE_HPSS_REUSE", "1")', profile)
        self.assertIn('profile_hpss_reuse=', profile)
        self.assertIn('env=env,', profile)

    def test_explicit_zero_rollback(self):
        src = ENTRY.read_text(encoding="utf-8")
        self.assertIn('env.setdefault("EZSTUDIO_PROFILE_HPSS_REUSE", "1")', src)
        self.assertNotIn('env["EZSTUDIO_PROFILE_HPSS_REUSE"] = "1"', src)


if __name__ == "__main__":
    unittest.main()
