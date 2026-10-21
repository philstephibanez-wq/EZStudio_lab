import json
import sqlite3
import sys
import unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"scripts"))
from report_diagnostics import filtered,make_record,reports
class CompleteArchiveContract(unittest.TestCase):
 def test_nested_items_are_not_silently_cut(self):
  value={"scores":[{"index":i,"score":i/100} for i in range(101)]}
  record=make_record("lab","profile",153,{"metrics_json":value})
  self.assertEqual(len(record["fields"]["metrics_json"]["scores"]),101)
  self.assertEqual(record["redactions"],[])
 def test_secret_and_personal_paths_removed(self):
  value={"config_json":{"api_token":"secret","context":"Bearer abcdefghijklmnop","path":"C:/Users/privateperson/cache"}}
  result=make_record("assistant","profile",153,value,48)
  output=json.dumps(result)
  for forbidden in ("abcdefghijklm","privateperson",'"secret"'):
   self.assertNotIn(forbidden,output)
  self.assertGreaterEqual(len(result["redactions"]),2)
 def test_two_producers_and_missing_trace_declared(self):
  db=sqlite3.connect(":memory:")
  db.execute("CREATE TABLE scientific_runs (id INTEGER,item TEXT,state TEXT,metrics_json TEXT,diagnostics_json TEXT,source_path TEXT)")
  db.execute("INSERT INTO scientific_runs VALUES(153,'profile','done',?,?,'C:/private/audio.mp3')",(json.dumps({"items":[1,2,3]}),"{}"))
  db.execute("CREATE TABLE profile_external_revisions (id INTEGER,run_id INTEGER,comparison_json TEXT)")
  db.execute("INSERT INTO profile_external_revisions VALUES(48,153,?)",('{"assistant_ai":{"advisory":"unknown"}}',))
  got=list(reports(db))
  self.assertEqual(len(got),2)
  self.assertEqual(got[0][1]["fields"]["metrics_json"]["items"],[1,2,3])
  self.assertNotIn("source_path",got[0][1]["fields"])
  self.assertIn("raw_xps_qwen_response",got[1][1]["coverage"]["not_available_from_main"])

class PublisherActivationContract(unittest.TestCase):
 def test_detailed_exports_are_explicit_opt_in(self):
  src=(Path(__file__).resolve().parents[1]/"scripts/publish_run_reports.py").read_text(encoding="utf-8")
  self.assertIn('args.detailed',src)
  self.assertIn('parser.add_argument("--detailed"',src)
