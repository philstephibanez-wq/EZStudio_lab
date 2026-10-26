"""Contract for run-local, opt-in reuse of Meter HPSS harmonic in Tuning."""
import ast
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MODULE_ROOT = ROOT / "python/ezstudio/pipeline/profile"


class HpssReuseContract(unittest.TestCase):
    def test_ast_and_opt_in(self):
        for relative in (
            "module.py",
            "meter_shared.py",
            "modules/meter_chords_shared.py",
            "modules/tuning_librosa.py",
        ):
            ast.parse((MODULE_ROOT / relative).read_text(encoding="utf-8"))
        adapter = (MODULE_ROOT / "modules/meter_chords_shared.py").read_text(encoding="utf-8")
        tuning = (MODULE_ROOT / "modules/tuning_librosa.py").read_text(encoding="utf-8")
        self.assertIn('os.getenv("EZSTUDIO_PROFILE_HPSS_REUSE") == "1"', adapter)
        self.assertIn('os.getenv("EZSTUDIO_PROFILE_HPSS_REUSE") == "1"', tuning)
        self.assertIn("hpss_margin == 1.0", tuning)

    def test_hpss_dataflow(self):
        meter = (MODULE_ROOT / "meter_shared.py").read_text(encoding="utf-8")
        adapter = (MODULE_ROOT / "modules/meter_chords_shared.py").read_text(encoding="utf-8")
        tuning = (MODULE_ROOT / "modules/tuning_librosa.py").read_text(encoding="utf-8")
        self.assertIn("harmonic, perc = librosa.effects.hpss(y)", meter)
        self.assertIn("harmonic_sink(harmonic)", meter)
        self.assertIn("context._meter_harmonic = harmonic", adapter)
        self.assertIn("cached_harmonic = (", tuning)
        self.assertIn("librosa.effects.harmonic(master_y, margin=hpss_margin)", tuning)
        self.assertIn("cache_hit", tuning)

    def test_no_default_enablement(self):
        entry = (ROOT / "analysis/worker_entrypoint.py").read_text(encoding="utf-8")
        self.assertNotIn("EZSTUDIO_PROFILE_HPSS_REUSE", entry)


if __name__ == "__main__":
    unittest.main()
