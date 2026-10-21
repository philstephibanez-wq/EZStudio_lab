import sqlite3,sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"scripts"))
from report_diagnostics import reports
class ReportPaginationContract(unittest.TestCase):
 def test_skips_bad_json_without_decoding_out_of_page(self):
  db=sqlite3.connect(":memory:")
  db.execute("CREATE TABLE scientific_runs (id INTEGER,item TEXT,state TEXT,metrics_json TEXT, diagnostics_json TEXT)")
  for n in range(1,9):
   db.execute("INSERT INTO scientific_runs VALUES(?,?,?,?,?)",(n,"profile","done","INVALID" if n>=5 else '{"ok":true}',"{}"))
  selected=list(reports(db,offset=4,limit=2))
  self.assertEqual([r["run_id"] for _,r in selected],[4,3])
 def test_limit_does_not_decode_next_item(self):
  db=sqlite3.connect(":memory:")
  db.execute("CREATE TABLE scientific_runs (id INTEGER,item TEXT,state TEXT,metrics_json TEXT,diagnostics_json TEXT)")
  db.execute("INSERT INTO scientific_runs VALUES(2,'profile','done','{}','{}')")
  db.execute("INSERT INTO scientific_runs VALUES(1,'profile','done','INVALID','{}')")
  self.assertEqual(len(list(reports(db,limit=1))),1)
