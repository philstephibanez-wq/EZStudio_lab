import unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class Contract(unittest.TestCase):
 def test_job_identity_is_joined(self):
  data=(ROOT/'src/Service/ProfileAiRequestService.php').read_text(encoding='utf-8')
  self.assertIn('aj.song_id=sr.song_id',data)
  self.assertIn("aj.id=? AND aj.kind='assistant.profile.review'",data)
 def test_provenance_not_in_http_projection(self):
  data=(ROOT/'src/Controller/ProfileAiRequestController.php').read_text(encoding='utf-8')
  part=data.split('function jobContext(',1)[1].split('function claim(',1)[0]
  self.assertIn('final_assertions',part)
  self.assertIn('recording',part)
  for forbidden in ['diagnostics','metrics_json','config_json','module_registry','engine_parameters','scientific-items']:
   self.assertNotIn(forbidden,part)
 def test_no_implicit_job_claim(self):
  data=(ROOT/'src/Controller/ProfileAiRequestController.php').read_text(encoding='utf-8')
  part=data.split('function jobContext(',1)[1].split('function claim(',1)[0]
  self.assertNotIn('->claim(',part)
  self.assertNotIn('->finish(',part)
if __name__=='__main__': unittest.main()
