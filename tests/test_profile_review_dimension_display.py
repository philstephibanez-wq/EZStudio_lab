"""PROFILE display must not repeat the same module text in all dimensions."""
import unittest
from pathlib import Path

TEMPLATE=(Path(__file__).resolve().parents[1]/"templates/workbench/profile.html.twig").read_text(encoding="utf-8")

class ProfileReviewDimensionDisplay(unittest.TestCase):
    def test_separate_dimension_advisory(self):
        self.assertIn("revision.comparison.dimension_advisories.dimensions", TEMPLATE)
        self.assertIn("attribute(musical_dimensions, key)", TEMPLATE)
        self.assertIn("musical_advice.advisory", TEMPLATE)
        self.assertIn("musical_advice.uncertainty", TEMPLATE)
        self.assertIn("musical_advice.contributors", TEMPLATE)
    def test_does_not_repeat_generic_module_commentary(self):
        self.assertNotIn("consultation du moteur, non synthèse de la dimension", TEMPLATE)
        self.assertNotIn("module.assessment.summary }}</div>", TEMPLATE)
        self.assertIn("Synthèse musicale indisponible pour cette révision", TEMPLATE)
        self.assertIn("revision.comparison.module_consultations", TEMPLATE)
    def test_external_validation_untouched(self):
        self.assertIn("item.decision ?? 'Non évalué'", TEMPLATE)
        self.assertIn("item.confidence is defined and item.confidence is not null", TEMPLATE)
if __name__=="__main__":
    unittest.main()
