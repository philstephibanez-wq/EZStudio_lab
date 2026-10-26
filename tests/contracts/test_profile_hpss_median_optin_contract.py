"""Contracts for the opt-in HPSS median backend."""
import ast
import pathlib
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
METER = ROOT / "python/ezstudio/pipeline/profile/meter_shared.py"
OPT = ROOT / "python/ezstudio/pipeline/profile/hpss_median_optin.py"


class HpssMedianOptinContract(unittest.TestCase):
    def test_ast(self):
        ast.parse(METER.read_text(encoding="utf-8"))
        ast.parse(OPT.read_text(encoding="utf-8"))

    def test_default_and_gate(self):
        source = METER.read_text(encoding="utf-8")
        self.assertIn('EZSTUDIO_PROFILE_HPSS_MEDIAN_BACKEND", "scipy"', source)
        self.assertIn("harmonic, perc = librosa.effects.hpss(y)", source)

    def test_science_parameters(self):
        source = OPT.read_text(encoding="utf-8")
        for needle in ('(1, 31)', '(31, 1)', 'mode="symmetric"', "power=2.0", "split_zeros=True", "length=y.shape[-1]"):
            self.assertIn(needle, source)


if __name__ == "__main__":
    unittest.main()
