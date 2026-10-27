import sqlite3
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
from scripts import publish_review_event as event

class TargetedReviewEventTest(unittest.TestCase):
 def test_completed_only(self):
  with tempfile.TemporaryDirectory() as td:
   db=Path(td)/"test.sqlite"
   c=sqlite3.connect(db)
   c.executescript("CREATE TABLE scientific_runs(id INTEGER PRIMARY KEY,item TEXT,state TEXT);"
                   "CREATE TABLE profile_external_revisions(id INTEGER PRIMARY KEY,run_id INTEGER,status TEXT);"
                   "INSERT INTO scientific_runs VALUES(42,'profile','done');"
                   "INSERT INTO profile_external_revisions VALUES(7,42,'draft');")
   c.commit();c.close()
   self.assertIsNone(event.select_revision(db,7))
   with patch.object(event.reports,"collect",return_value=iter([(
      "assistant/profile/run-000042/revision-000007.json",
      {"revision_id":7,"status":"completed"})])):
    with sqlite3.connect(db) as c:c.execute("UPDATE profile_external_revisions SET status='completed'");c.commit()
    self.assertEqual(event.select_revision(db,7)[0],"assistant/profile/run-000042/revision-000007.json")
 def test_no_publication_on_dry_run(self):
  with patch.object(event,"select_revision",return_value=("one.json",{"revision_id":8})),patch.object(event.reports,"publish") as upload:
   self.assertEqual(event.publish_revision(Path("any"),Path("any"),8,True),"ready")
   upload.assert_not_called()
