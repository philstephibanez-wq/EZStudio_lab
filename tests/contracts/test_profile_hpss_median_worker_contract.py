"""Contract: PROFILE-only HPSS median activation and explicit rollback."""
import ast
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKER = ROOT / "analysis" / "worker_entrypoint.py"
METER = ROOT / "python" / "ezstudio" / "pipeline" / "profile" / "meter_shared.py"


class HpssMedianWorkerContract(unittest.TestCase):
    def test_syntax(self):
        ast.parse(WORKER.read_text(encoding="utf-8"))
        ast.parse(METER.read_text(encoding="utf-8"))

    def test_profile_scope_and_rollback(self):
        worker = WORKER.read_text(encoding="utf-8")
        profile = worker.split('if kind == "profile":', 1)[1].split('if kind == "stems":', 1)[0]
        self.assertIn('env.setdefault("EZSTUDIO_PROFILE_HPSS_MEDIAN_BACKEND", "medfilt2d")', profile)
        self.assertIn('profile_hpss_median_backend=', profile)
        meter = METER.read_text(encoding="utf-8")
        self.assertIn('os.getenv("EZSTUDIO_PROFILE_HPSS_MEDIAN_BACKEND", "scipy")', meter)
        self.assertIn('harmonic, perc = librosa.effects.hpss(y)', meter)


if __name__ == "__main__":
    unittest.main()
