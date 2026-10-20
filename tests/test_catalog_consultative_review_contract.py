"""Catalogue must distinguish completed AI advice from external validation."""
from pathlib import Path
import unittest
SRC=(Path(__file__).resolve().parents[1]/"src/Service/CatalogService.php").read_text(encoding="utf-8")
class CatalogConsultativeReviewContract(unittest.TestCase):
    def test_completed_advisory_is_reviewed_without_external_evidence(self):
        self.assertIn("($ai['status'] ?? '') !== 'completed'",SRC)
        self.assertIn("$group['profile_ai_reviewed'] = true", SRC)
        self.assertNotIn("$hasScoredEvidence",SRC)
        self.assertIn("WHERE run_id=? ORDER BY id DESC", SRC)
        self.assertIn("$group['profile']['state'] === 'done'",SRC)
