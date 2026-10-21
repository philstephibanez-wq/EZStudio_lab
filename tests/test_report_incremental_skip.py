import sqlite3
import sys
import unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"scripts"))
from report_diagnostics import reports

class IncrementalSkipContract(unittest.TestCase):
 def test_skips_published_before_invalid_json_decode(self):
  db=sqlite3.connect(":memory:")
  db.execute("CREATE TABLE scientific_runs (id INTEGER,item TEXT,state TEXT,metrics_json TEXT,diagnostics_json TEXT)")
  db.execute("INSERT INTO scientific_runs VALUES(3,'profile','done','INVALID','{}')")
  db.execute("INSERT INTO scientific_runs VALUES(2,'profile','done','{}','{}')")
  db.execute("INSERT INTO scientific_runs VALUES(1,'profile','done','{}','{}')")
  result=list(reports(db,limit=1,skip_row=lambda producer,phase,run_id,revision_id,row:run_id==3))
  self.assertEqual([record["run_id"] for _,record in result],[2])
