import unittest
from pathlib import Path
SRC=(Path(__file__).resolve().parents[1]/"scripts/publish_run_reports.py").read_text(encoding="utf-8")
class DetailedPublicationGateContract(unittest.TestCase):
 def test_all_diagnostics_checked_before_publish(self):
  self.assertIn('if path.startswith("diagnostics/"):',SRC)
  self.assertIn("findings = scan_record(report)",SRC)
  self.assertIn('print(f"blocked: {path}',SRC)
  self.assertLess(SRC.index('if path.startswith("diagnostics/"):'), SRC.index('status = publish(path, report)', SRC.index('def main():')))
 def test_pilot_restricts_to_detailed_records(self):
  self.assertIn('parser.add_argument("--detailed-only"',SRC)
  self.assertIn('args.audit_detailed or args.detailed_only',SRC)
  self.assertIn('requires --detailed',SRC)
