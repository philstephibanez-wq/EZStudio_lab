import unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
SRC=(ROOT/"scripts/publish_run_reports.py").read_text(encoding="utf-8")
class IncrementalPublisherContract(unittest.TestCase):
 def test_source_signature_saved_after_remote_publish(self):
  self.assertIn("def skip_published(",SRC)
  self.assertIn('state.get("source:"+path) == source_sha and path in state',SRC)
  self.assertIn("skip_row=row_skip",SRC)
  self.assertLess(SRC.index("status = publish(path, report)"),SRC.index('state["source:"+path] = pending_signatures[path]'))
