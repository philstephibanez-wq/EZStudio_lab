"""Contract for PROFILE-only opt-in GPU activation in the job launcher."""
import ast
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ENTRY = ROOT / "analysis" / "worker_entrypoint.py"
MODULE = ROOT / "python" / "ezstudio" / "pipeline" / "profile" / "modules" / "tonal_madmom_key.py"


class ProfileMadmomLaunchContract(unittest.TestCase):
    def test_backend_set_only_in_profile_branch(self):
        src = ENTRY.read_text(encoding="utf-8")
        ast.parse(src)
        profile_block = src.split('    if kind == "profile":', 1)[1].split('    if kind == "stems":', 1)[0]
        self.assertIn('env.setdefault("EZSTUDIO_PROFILE_MADMOM_BACKEND", "cuda")', profile_block)
        self.assertIn('env=env,', profile_block)
        self.assertNotIn('EZSTUDIO_PROFILE_MADMOM_BACKEND', src.split('    if kind == "profile":', 1)[0])
        self.assertNotIn('EZSTUDIO_PROFILE_MADMOM_BACKEND', src.split('    if kind == "stems":', 1)[1])

    def test_numpy_rollback_and_legacy_default(self):
        src = MODULE.read_text(encoding="utf-8")
        ast.parse(src)
        self.assertIn("os.getenv('EZSTUDIO_PROFILE_MADMOM_BACKEND', 'numpy')", src)
        self.assertIn("backend=\"numpy\"", src)


if __name__ == "__main__":
    unittest.main()
