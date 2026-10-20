"""Guard advisory vs external-proof distinctions in PROFILE Twig."""
from pathlib import Path
import unittest
TEMPLATE = (Path(__file__).resolve().parents[1] / "templates/workbench/profile.html.twig").read_text(encoding="utf-8")
class ProfileReviewUiContract(unittest.TestCase):
    def test_rationale_distinct_from_abstention_reason(self):
        self.assertIn("Avis musical non fourni", TEMPLATE)
        self.assertIn("Motif : {{ item.reason }}", TEMPLATE)
        self.assertNotIn("item.rationale ?? item.reason", TEMPLATE)
    def test_consulted_is_not_called_accepted_evidence(self):
        self.assertIn("source.evidence_accepted ?? false", TEMPLATE)
        self.assertIn("source pertinente, sans preuve validée", TEMPLATE)
        self.assertIn("source consultée, non validée", TEMPLATE)
