import io
import sys
import unittest
from contextlib import redirect_stdout, redirect_stderr
from pathlib import Path
from types import SimpleNamespace

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))
from publish_diagnostics_batches import run_batches

class BatchPublisherContract(unittest.TestCase):
    def test_resume_and_stop_when_empty(self):
        outputs = iter([
            "REPORTS checked=2 attempted=0 published=0 unchanged=2 failed=0\n",
            "REPORTS checked=2 attempted=2 published=2 unchanged=0 failed=0\n",
            "REPORTS checked=0 attempted=0 published=0 unchanged=0 failed=0\n",
        ])
        calls = []
        def runner(command, **kwargs):
            calls.append(command)
            return SimpleNamespace(returncode=0, stdout=next(outputs), stderr="")
        with redirect_stdout(io.StringIO()) as output:
            self.assertEqual(run_batches(batch_size=2, max_batches=8, runner=runner),0)
        self.assertEqual(len(calls),3)
        self.assertIn("published=2",output.getvalue())
    def test_stop_on_publication_error(self):
        calls=[]
        def runner(command, **kwargs):
            calls.append(command)
            return SimpleNamespace(returncode=1, stdout="REPORTS checked=1 attempted=1 published=0 unchanged=0 failed=1\n", stderr="error\n")
        with redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()):
            self.assertEqual(run_batches(batch_size=2,max_batches=8,runner=runner),1)
        self.assertEqual(len(calls),1)
    def test_bounded_at_max_batches(self):
        calls=[]
        def runner(command, **kwargs):
            calls.append(command)
            return SimpleNamespace(returncode=0,stdout="REPORTS checked=1 attempted=1 published=1 unchanged=0 failed=0\n",stderr="")
        with redirect_stdout(io.StringIO()) as output:
            self.assertEqual(run_batches(batch_size=1,max_batches=3,runner=runner),0)
        self.assertEqual(len(calls),3)
        self.assertIn("batch_cap_reached",output.getvalue())
