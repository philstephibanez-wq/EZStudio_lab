"""Guardrails for displaying persisted Qwen consultations in PROFILE dimension rows."""
import unittest
from pathlib import Path

TEMPLATE = (Path(__file__).resolve().parents[1] / "templates/workbench/profile.html.twig").read_text(encoding="utf-8")

class ProfileReviewDimensionDisplay(unittest.TestCase):
    def test_consultations_from_existing_revision(self):
        self.assertIn("revision.comparison.module_consultations.results", TEMPLATE)
        for name in ("genre", "instrumentation", "lead_vocal", "backing_vocals", "choir"):
            self.assertIn("'"+name+"':", TEMPLATE)
        self.assertIn("module.status == 'completed'", TEMPLATE)
        self.assertIn("module.assessment.summary", TEMPLATE)
        self.assertIn("module.assessment.uncertainty", TEMPLATE)

    def test_advisory_does_not_replace_external_scoring(self):
        self.assertIn("item.decision ?? 'Non évalué'", TEMPLATE)
        self.assertIn("item.confidence is defined and item.confidence is not null", TEMPLATE)
        self.assertIn("Avis non validés ; scores moteurs non comparables", TEMPLATE)
        self.assertIn("non synthèse de la dimension", TEMPLATE)

    def test_no_fabricated_empty_advice(self):
        self.assertIn("Aucun avis Qwen exploitable pour cette dimension", TEMPLATE)
        self.assertNotIn("Avis musical non fourni", TEMPLATE)

if __name__ == "__main__":
    unittest.main()
