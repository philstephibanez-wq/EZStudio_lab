import json
import sys
import unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"scripts"))
from report_diagnostics import make_record

class DiagnosticPrivacyExtendedContract(unittest.TestCase):
    def test_credential_urls_and_personal_fields(self):
        data={"diagnostics_json":{"owner_email":"person@example.org","auth_token":"secret123","source_url":"https://user:pass@en.wikipedia.org/wiki/Test?token=abc#fragment","scores":[{"i":i} for i in range(90)]}}
        result=make_record("lab","profile",153,data)
        raw=json.dumps(result)
        for item in ("person@example.org","secret123","user:pass","token=abc","fragment"):
            self.assertNotIn(item,raw)
        self.assertIn("https://en.wikipedia.org/wiki/Test",raw)
        self.assertEqual(len(result["fields"]["diagnostics_json"]["scores"]),90)
        self.assertTrue(result["redactions"])
