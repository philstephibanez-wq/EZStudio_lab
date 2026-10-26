"""Scientific guard: cached HPSS harmonic novelty equals engine baseline."""
from __future__ import annotations
import importlib.util
import sys
import unittest
from pathlib import Path
import numpy as np

ROOT = Path(__file__).resolve().parents[2]
PYTHON = ROOT / "python"
PROFILE = PYTHON / "ezstudio/pipeline/profile"
for entry in (str(PROFILE), str(PYTHON)):
    if entry not in sys.path:
        sys.path.insert(0, entry)

from meter_shared import _harmonic_novelty_from_harmonic
from engine import harmonic_novelty


class MeterHPSSReuseContract(unittest.TestCase):
    def test_harmonic_novelty_same_values(self):
        import librosa
        sr = 22050
        rng = np.random.default_rng(22050)
        # Enough non-silent audio for STFT, HPSS and chroma.
        y = rng.standard_normal(sr * 3).astype(np.float32) * 0.02
        hop = 512
        harmonic, _ = librosa.effects.hpss(y)
        bf = np.arange(4, 60, 5, dtype=np.int64)
        baseline = harmonic_novelty(y, sr, hop, bf)
        cached = _harmonic_novelty_from_harmonic(harmonic, sr, hop, bf)
        np.testing.assert_array_equal(baseline, cached)
        self.assertEqual(baseline.tobytes(), cached.tobytes())

    def test_no_shared_engine_edit(self):
        source = (PROFILE / "meter_shared.py").read_text(encoding="utf-8")
        self.assertEqual(source.count("librosa.effects.hpss(y)"), 1)
        self.assertIn("_harmonic_novelty_from_harmonic(harmonic, sr, hop, bf)", source)


if __name__ == "__main__":
    unittest.main()
