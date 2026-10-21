import sys
import unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"scripts"))
from profile_module_inventory_r1 import module_inventory
class ModuleInventoryContract(unittest.TestCase):
 def archive(self,modules):
  return {"schema":"ezstudio.run-report.v1","producer":"lab","phase":"profile","run_id":153,"diagnostics":{"module_registry":{"modules":modules},"profile_view":{"counts":{"ok":len(modules),"error":0,"skipped":0}}}}
 def test_all_modules_and_stable_ids(self):
  data=self.archive([{"id":"rhythm.librosa-beat","name":"Librosa Beat Tracker","status":"ok","family":"rhythm"},{"id":"semantic.clap-open-vocabulary","status":"ok","family":"semantic"}])
  r=module_inventory(data)
  self.assertEqual(r["module_count"],2)
  self.assertEqual(r["module_status_counts"]["ok"],2)
  self.assertTrue(r["coverage"]["complete_for_supplied_registry"])
  self.assertEqual(r["items"][1]["item_id"],"semantic.clap-open-vocabulary")
  self.assertEqual(module_inventory(data)["items"],r["items"])
 def test_duplicates_fail_closed(self):
  with self.assertRaisesRegex(ValueError,"duplicate_module_id"):
   module_inventory(self.archive([{"id":"same"},{"id":"same"}]))
 def test_missing_diagnostics_not_a_success(self):
  with self.assertRaisesRegex(ValueError,"diagnostics_missing"):
   module_inventory({"producer":"lab","phase":"profile","run_id":153})
