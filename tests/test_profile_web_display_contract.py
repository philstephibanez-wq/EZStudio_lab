"""Static display contract for independent Web investigation PROFILE.
No engine or model is called by this test.
"""
from pathlib import Path
import unittest

TEMPLATE = Path(__file__).resolve().parents[1] / "templates/workbench/profile.html.twig"

class ProfileWebDisplayContract(unittest.TestCase):
    def test_sources_are_displayed_only_for_matching_item(self):
        source = TEMPLATE.read_text(encoding="utf-8")
        self.assertIn("revision.comparison.final_verification.web_investigation", source)
        self.assertIn("(web_candidate.key ?? '') == lab_row.key", source)
        self.assertIn("web_item.citation_urls", source)
        self.assertIn("source_url starts with 'https://'", source)
    def test_document_leads_are_not_verdicts(self):
        source = TEMPLATE.read_text(encoding="utf-8")
        self.assertIn("Ces résultats ne justifient ni BON ni FAUX.", source)
        self.assertIn("verdict_row.verdict ?? 'non_trouve'", source)
        self.assertIn("Recherche Internet —", source)

if __name__ == "__main__":
    unittest.main()
