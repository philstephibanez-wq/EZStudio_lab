import hashlib
import sys
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"scripts"))
import publish_run_reports as mod
class GitBlobContract(unittest.TestCase):
 def test_sha1_standard(self):
  self.assertEqual(mod._git_blob_sha(b"hello\n"),"ce013625030ba8dba906f756967f9e9ca394464a")
 def test_large_content_absent_but_sha_matches(self):
  report={"producer":"lab"}
  import json
  raw=(json.dumps(report,ensure_ascii=False,sort_keys=True,indent=2)+"\n").encode()
  with patch.object(mod,"gh",return_value={"sha":mod._git_blob_sha(raw),"content":""}):
   self.assertEqual(mod.publish("diagnostics/lab/profile/run-000156/main-db.json",report),"unchanged")
 def test_remote_sha_mismatch_fails(self):
  with patch.object(mod,"gh",side_effect=[RuntimeError("404"),{},{"sha":"bad","content":""}]):
   with self.assertRaisesRegex(RuntimeError,"github_roundtrip_mismatch"):
    mod.publish("diagnostics/lab/profile/run-000156/main-db.json",{"producer":"lab"})
