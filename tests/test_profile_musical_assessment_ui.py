"""Check Lab musical assessment slot distinct from documentary score."""
from pathlib import Path
import unittest
S=(Path(__file__).resolve().parents[1]/"templates/workbench/profile.html.twig").read_text(encoding="utf-8")
class ProfileMusicalAssessmentUI(unittest.TestCase):
    def test_advisory_and_human_optional(self):
        self.assertIn("item.advisory",S)
        self.assertIn("item.recommendation",S)
        self.assertIn("Certitude consultative",S)
        self.assertIn("item.decision",S)
