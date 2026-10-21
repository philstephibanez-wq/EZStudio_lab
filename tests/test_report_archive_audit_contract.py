from pathlib import Path
import unittest
SOURCE=(Path(__file__).resolve().parents[1]/"scripts/publish_run_reports.py").read_text(encoding="utf-8")
class AuditModeContract(unittest.TestCase):
 def test_read_only_mode(self):
  self.assertIn('parser.add_argument("--audit-detailed"',SOURCE)
  self.assertIn('status = "audit-ok"',SOURCE)
  self.assertIn('elif args.dry_run:',SOURCE)
  self.assertIn('if len(raw) > 4_000_000:',SOURCE)
  self.assertIn('(detailed_reports(db),) if args.audit_detailed',SOURCE)
