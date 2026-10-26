"""Contracts for run-local resampling cache, no scientific processing changes."""
from __future__ import annotations

import sys
import unittest
from pathlib import Path
from unittest.mock import patch

import numpy as np

ROOT = Path(__file__).resolve().parents[2]
PROFILE = ROOT / "python" / "ezstudio" / "pipeline" / "profile"
sys.path.insert(0, str(PROFILE))
from module import ModuleContext


class ProfileAudioCacheTests(unittest.TestCase):
    def context(self):
        return ModuleContext(
            source=Path("test.mp3"), audio_sha256="test",
            y=np.array([0.0, 0.25, -0.5, 0.75], dtype=np.float32),
            sr=22050, model_root=Path("models"), ai_models_root=Path("ai"),
        )

    def test_bitwise_equivalent(self):
        import librosa
        ctx = self.context()
        expected = librosa.resample(
            np.asarray(ctx.y, dtype=np.float32),
            orig_sr=ctx.sr,
            target_sr=32000,
        ).astype(np.float32, copy=False)
        actual = ctx.resampled_audio(32000)
        np.testing.assert_array_equal(actual, expected)
        self.assertEqual(actual.tobytes(), expected.tobytes())
        self.assertFalse(actual.flags.writeable)

    def test_single_computation_within_run(self):
        import librosa
        original = librosa.resample
        calls = []
        def counted(*args, **kwargs):
            calls.append(kwargs["target_sr"])
            return original(*args, **kwargs)
        with patch.object(librosa, "resample", side_effect=counted):
            a = self.context()
            first = a.resampled_audio(32000)
            self.assertIs(first, a.resampled_audio(32000))
            b = self.context()
            self.assertIsNot(first, b.resampled_audio(32000))
        self.assertEqual(calls, [32000, 32000])

    def test_invalid_rate(self):
        with self.assertRaises(ValueError):
            self.context().resampled_audio(0)


if __name__ == "__main__":
    unittest.main()
