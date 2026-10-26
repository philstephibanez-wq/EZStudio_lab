"""Contract: CQT chunk parallel pilot must preserve scientific ordering."""
import ast
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "python/ezstudio/pipeline/profile/modules/tuning_librosa.py"


class CqtChunkWorkersContract(unittest.TestCase):
    def test_syntax_and_opt_in_default_sequential(self):
        source = SOURCE.read_text(encoding="utf-8")
        ast.parse(source)
        self.assertIn('os.getenv("EZSTUDIO_PROFILE_CQT_WORKERS", "1")', source)
        self.assertIn("if workers == 1:", source)

    def test_bounded_order_preserving_workers(self):
        source = SOURCE.read_text(encoding="utf-8")
        self.assertIn("min(workers, 2, len(chunks))", source)
        self.assertIn("executor.map(scan, chunks)", source)
        self.assertIn("results = [scan(chunk) for chunk in chunks]", source)

    def test_same_scientific_scan_for_both_paths(self):
        source = SOURCE.read_text(encoding="utf-8")
        chunk = source.split("def _chunk_cqt_scan(", 1)[1].split("def _consensus_r2d(", 1)[0]
        self.assertEqual(chunk.count("_cqt_detune_scan("), 1)
        self.assertIn('result.get("available")', chunk)


if __name__ == "__main__":
    unittest.main()
