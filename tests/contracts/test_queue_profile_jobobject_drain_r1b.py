"""Static regression contract for the Windows process adapter, without starting engines."""
from pathlib import Path
import ast
import unittest


SOURCE = Path(__file__).resolve().parents[2] / "analysis" / "queue_profile_jobobject_launcher.py"


class TestJobObjectDrain(unittest.TestCase):
    def test_canonical_worker_is_unchanged_and_isolation_is_explicit(self):
        text = SOURCE.read_text(encoding="utf-8")
        ast.parse(text)
        self.assertIn('"canonical_worker_path_required"', text)
        self.assertIn('"windows_jobobject_required"', text)
        self.assertIn('AssignProcessToJobObject(job, proc.hProcess)', text)
        self.assertIn('0x00002000  # KILL_ON_JOB_CLOSE', text)

    def test_success_waits_for_accounted_processes_not_job_signal(self):
        text = SOURCE.read_text(encoding="utf-8")
        self.assertNotIn('WaitForSingleObject(job,', text)
        self.assertIn('if info.ActiveProcesses == 0:', text)
        self.assertIn('time.monotonic() >= deadline', text)
        self.assertIn('"jobobject_descendants_still_running"', text)
        self.assertIn('kernel32.CloseHandle(job)', text)


if __name__ == "__main__":
    unittest.main()
