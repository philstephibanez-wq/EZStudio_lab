"""Static safety contract for recurring MAIN reporting installation."""
from pathlib import Path
import unittest
ROOT = Path(__file__).resolve().parents[1]
class ScheduledReportsContract(unittest.TestCase):
    def test_scheduled_task_is_bounded_and_non_overlapping(self):
        script = (ROOT / "scripts" / "install_reports_publisher_task.ps1").read_text(encoding="utf-8")
        self.assertIn("-MultipleInstances IgnoreNew", script)
        self.assertIn("--producer all --limit 100", script)
        self.assertIn("-RepetitionInterval (New-TimeSpan -Minutes 5)", script)
        self.assertNotIn("Stop-Process", script)
        self.assertIn("-LogonType Interactive", script)
