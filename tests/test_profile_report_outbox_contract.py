import unittest
from pathlib import Path
class ProfileReportOutboxContract(unittest.TestCase):
 def test_outbox_after_persistence_only(self):
  src=(Path(__file__).resolve().parents[1]/"src/Service/ProfileExternalReviewService.php").read_text(encoding="utf-8")
  self.assertIn("profile_report_events",src)
  self.assertIn("ON CONFLICT(revision_id) DO NOTHING",src)
  self.assertIn("if ($status === 'completed')",src)
  self.assertLess(src.index("$row = $select->fetch()"),src.index("$this->recordCompletedReportEvent"))
  self.assertNotIn("shell_exec(",src)
