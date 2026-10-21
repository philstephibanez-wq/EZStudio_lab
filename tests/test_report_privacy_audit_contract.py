import unittest
import sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"scripts"))
from report_privacy_audit import scan_record

class PrivacyAuditContract(unittest.TestCase):
    def test_blocks_residual_credentials_and_email_without_echo(self):
        doc={"fields":{"nested":{"email":"human@example.org","auth":"Bearer abcdefghijklmnop","url":"https://example.org/a?auth=x"}}}
        results=scan_record(doc)
        self.assertEqual({r["reason"] for r in results},{"email","token_shape","unsafe_url"})
        self.assertNotIn("abcdefgh",str(results))
        self.assertNotIn("human@",str(results))
    def test_keeps_safe_musical_analysis(self):
        self.assertEqual(scan_record({"fields":{"genre":"pop rock","score":0.81,"url":"https://musicbrainz.org/recording/xyz"}}),[])
    def test_publisher_audits_before_reporting_ok(self):
        text=(Path(__file__).resolve().parents[1]/"scripts/publish_run_reports.py").read_text(encoding="utf-8")
        self.assertIn("findings = scan_record(report)",text)
        self.assertIn('raise ValueError("privacy_findings:"',text)
