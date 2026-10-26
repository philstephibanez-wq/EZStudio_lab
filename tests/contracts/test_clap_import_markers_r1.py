"""Contract of CLAP import attribution markers: preserve import statements and order."""
from pathlib import Path
import unittest


class ClapImportMarkersTests(unittest.TestCase):
    def test_imports_remain_unchanged_and_marked(self):
        source = (Path(__file__).resolve().parents[2]
                  / "python/ezstudio/runtime/adapters/clap_zero_shot.py").read_text(encoding="utf-8")
        expected = [
            'phase_mark("clap", "import_librosa_start")',
            "        import librosa",
            'phase_mark("clap", "import_librosa_done")',
            'phase_mark("clap", "import_torch_start")',
            "        import torch",
            'phase_mark("clap", "import_torch_done")',
            'phase_mark("clap", "import_transformers_start")',
            "        from transformers import ClapModel, ClapProcessor",
            'phase_mark("clap", "import_transformers_done")',
        ]
        positions = [source.index(value, source.index("def run_clap_zero_shot(")) for value in expected]
        self.assertEqual(positions, sorted(positions))
        self.assertEqual(source.count("from transformers import ClapModel, ClapProcessor"), 1)


if __name__ == "__main__":
    unittest.main()
