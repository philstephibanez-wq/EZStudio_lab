import base64
import sys
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'))
import publish_run_reports as publisher

class GithubRoundtripContract(unittest.TestCase):
    def test_verified_diagnostic(self):
        stash={}
        def fake(*args,input_json=None):
            if input_json is not None:
                stash['raw']=base64.b64decode(input_json['content']); return {}
            if 'raw' not in stash: raise RuntimeError('404')
            return {'content':base64.b64encode(stash['raw']).decode(),'sha':'abc'}
        with patch.object(publisher,'gh',side_effect=fake):
            self.assertEqual(publisher.publish('diagnostics/lab/profile/run-000153/main-db.json',{'producer':'lab'}),'published')
    def test_mismatch_fails(self):
        n=[0]
        def fake(*args,input_json=None):
            n[0]+=1
            if n[0]==1: raise RuntimeError('404')
            if input_json is not None: return {}
            return {'content':base64.b64encode(b'wrong').decode()}
        with patch.object(publisher,'gh',side_effect=fake):
            with self.assertRaisesRegex(RuntimeError,'github_roundtrip_mismatch'):
                publisher.publish('diagnostics/lab/profile/run-000153/main-db.json',{'producer':'lab'})
