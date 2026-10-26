"""PROFILE-only Tuning probe propagation contract."""
import ast
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ENTRY = ROOT / "analysis" / "worker_entrypoint.py"
TUNING = ROOT / "python/ezstudio/pipeline/profile/modules/tuning_librosa.py"


class ProfileTuningProbeLaunchContract(unittest.TestCase):
    def test_launcher_propagates_only_to_profile(self):
        src = ENTRY.read_text(encoding="utf-8")
        ast.parse(src)
        before, after = src.split('    if kind == "profile":', 1)
        profile, remaining = after.split('    if kind == "stems":', 1)
        self.assertNotIn("EZSTUDIO_PROFILE_TUNING_PROBE", before + remaining)
        self.assertIn('env.setdefault("EZSTUDIO_PROFILE_TUNING_PROBE", "1")', profile)
        self.assertIn('profile_tuning_probe=', profile)
        self.assertIn('env=env,', profile)

    def test_probe_is_opt_in_at_module_level(self):
        src = TUNING.read_text(encoding="utf-8")
        ast.parse(src)
        self.assertIn('os.getenv("EZSTUDIO_PROFILE_TUNING_PROBE") != "1"', src)

    def test_explicit_zero_override_remains_possible(self):
        src = ENTRY.read_text(encoding="utf-8")
        self.assertIn('env.setdefault("EZSTUDIO_PROFILE_TUNING_PROBE", "1")', src)
        self.assertNotIn('env["EZSTUDIO_PROFILE_TUNING_PROBE"] = "1"', src)


if __name__ == "__main__":
    unittest.main()
