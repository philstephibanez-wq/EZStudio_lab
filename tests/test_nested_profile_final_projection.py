import unittest
from pathlib import Path
SOURCE=(Path(__file__).resolve().parents[1]/'src/Service/ProfileExternalReviewService.php').read_text(encoding='utf-8')
class MusicalProjectionContract(unittest.TestCase):
 def test_nested_tempo_meter_key(self):
  for fragment in ["$metrics['tempo']['bpm']", "$metrics['time_signature']['label']", "$metrics['key']['label']"]:
   self.assertIn(fragment,SOURCE)
 def test_no_candidate_auto_promotion(self):
  self.assertIn("['accepted','confirmed','detected']",SOURCE)
  self.assertIn("$items['instruments'] = $confirmed === [] ? null : $confirmed",SOURCE)
  self.assertIn("$choirs === 'detected' ? 'présents'",SOURCE)
 def test_only_final_values_exposed(self):
  self.assertIn("'final_assertions'=>$assertions",SOURCE)
  self.assertIn("No diagnostics, engine parameters",SOURCE)
if __name__=='__main__':unittest.main()
