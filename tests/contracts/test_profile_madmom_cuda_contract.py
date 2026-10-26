"""Durable contract: Madmom historical NumPy remains the default backend."""
from pathlib import Path
import ast
import unittest

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "python/ezstudio/pipeline/profile/modules/tonal_madmom_key.py"


class MadmomCudaContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.text = SOURCE.read_text(encoding="utf-8")
        cls.tree = ast.parse(cls.text)

    def test_cuda_is_explicit_opt_in(self):
        self.assertIn("EZSTUDIO_PROFILE_MADMOM_BACKEND", self.text)
        self.assertIn("os.getenv('EZSTUDIO_PROFILE_MADMOM_BACKEND', 'numpy')", self.text)
        self.assertIn("backend=\"numpy\"", self.text)

    def test_historical_models_unchanged(self):
        self.assertIn("key_cnn_{index}.pkl", self.text)
        self.assertIn("key_cnn.pkl", self.text)
        self.assertIn("2017", self.text)
        self.assertIn("2018", self.text)

    def test_cuda_uses_existing_processor_weights_and_frontend(self):
        self.assertIn("to_torch(stages[index])", self.text)
        self.assertIn("for stage in stages[:index]", self.text)
        self.assertIn("for stage in stages[index + 1:]", self.text)
        self.assertIn("torch.inference_mode()", self.text)
        self.assertIn("madmom_cuda_unexpected_pipeline", self.text)

    def test_output_contract_unchanged(self):
        for key in ("candidate_scores", "probabilities", "calibrated", "margin"):
            self.assertIn(key, self.text)


if __name__ == "__main__":
    unittest.main()
