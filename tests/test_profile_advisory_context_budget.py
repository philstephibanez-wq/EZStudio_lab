import json
from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[1]
class ProfileAdvisoryContextBudgetContract(unittest.TestCase):
    def test_preset_leaves_room_for_lab_context(self):
        p=json.loads((ROOT/"presets/assistant/profile-baseline-1.json").read_text(encoding="utf-8"))
        self.assertLess(len(p["prompt"])+len(p["output_contract"]),2600)
        self.assertGreaterEqual(p["context_max_chars"]-len(p["prompt"])-len(p["output_contract"]),3900)
        for field in ("advisory","certainty","recommendation","not_scored","recording"):
            self.assertIn(field, p["prompt"]+" "+p["output_contract"])
