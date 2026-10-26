import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

class CheckpointContract(unittest.TestCase):
    def test_idempotent_storage(self):
        source = (ROOT / 'src/Service/AssistantJobCheckpointService.php').read_text(encoding='utf-8')
        self.assertIn('UNIQUE(job_id,event_key)', source)
        self.assertIn('INSERT OR IGNORE', source)
        self.assertIn('checkpoint_idempotency_conflict', source)
    def test_authentication(self):
        source = (ROOT / 'src/Controller/ProfileAiRequestController.php').read_text(encoding='utf-8')
        method = source.split('function checkpoints(', 1)[1].split('function claim(', 1)[0]
        self.assertIn('assertAuthorized($request)', method)
        self.assertNotIn('->claim(', method)
        self.assertNotIn('->finish(', method)
