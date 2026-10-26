import unittest
from pathlib import Path
R=Path(__file__).resolve().parents[1]
class IdentityFormContract(unittest.TestCase):
 def test_controller_guard(self):
  c=(R/'src/Controller/ProfileController.php').read_text(encoding='utf-8')
  self.assertIn("X-EZS-Song-Identity",c)
  self.assertIn("Sec-Fetch-Site",c)
  self.assertIn('function updateIdentity(',c)
 def test_form_fields(self):
  t=(R/'templates/workbench/profile.html.twig').read_text(encoding='utf-8')
  self.assertIn('name="title"',t)
  self.assertIn('name="artist"',t)
  self.assertIn("profile_song_identity",t)
 def test_only_song_metadata(self):
  s=(R/'src/Service/SongIdentityEditor.php').read_text(encoding='utf-8')
  self.assertIn('UPDATE songs SET title=?, artist=? WHERE id=?',s)
  self.assertNotIn('analysis_jobs',s)
if __name__=='__main__':unittest.main()
