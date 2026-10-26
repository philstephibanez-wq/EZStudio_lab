"""Read-only observability contract for Tuning stage markers."""
import ast
import unittest
from pathlib import Path

SOURCE = Path(__file__).resolve().parents[2] / "python/ezstudio/pipeline/profile/modules/tuning_librosa.py"


class TuningProbeContract(unittest.TestCase):
    def test_parses_and_has_opt_in_gate(self):
        text = SOURCE.read_text(encoding="utf-8")
        ast.parse(text)
        self.assertIn('os.getenv("EZSTUDIO_PROFILE_TUNING_PROBE") != "1"', text)

    def test_stage_coverage(self):
        text = SOURCE.read_text(encoding="utf-8")
        for stage in (
            "harmonic_extraction",
            "global_tuning_estimates",
            "chunk_tuning_estimates",
            "global_cqt_scan",
            "chunk_cqt_scans",
            "consensus_and_projection",
        ):
            self.assertIn('mark("' + stage + '")', text)

    def test_scientific_functions_preserved(self):
        text = SOURCE.read_text(encoding="utf-8")
        for function in (
            "librosa.effects.harmonic",
            "librosa.estimate_tuning",
            "librosa.cqt",
            "_consensus_r2d(",
        ):
            self.assertIn(function, text)


if __name__ == "__main__":
    unittest.main()
