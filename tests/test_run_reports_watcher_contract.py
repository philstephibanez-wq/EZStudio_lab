"""Report event watcher contract: only changed reports cause GitHub writes."""
import importlib.util
from pathlib import Path
import sqlite3
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
import sys
sys.path.insert(0, str(ROOT / "scripts"))
import watch_run_reports as watcher


class WatcherContract(unittest.TestCase):
    def test_initial_event_and_idempotence(self):
        with tempfile.TemporaryDirectory() as tmp:
            dbpath = Path(tmp) / "benchmark.sqlite"
            with sqlite3.connect(dbpath) as db:
                db.executescript("""
                CREATE TABLE scientific_runs (
                    id INTEGER, public_id TEXT, item TEXT, state TEXT,
                    engine_name TEXT, engine_version TEXT, metrics_json TEXT,
                    diagnostics_json TEXT, created_at TEXT, finished_at TEXT);
                CREATE TABLE profile_external_revisions (
                    id INTEGER, run_id INTEGER, reviewer TEXT, status TEXT,
                    consensus_json TEXT, comparison_json TEXT, confidence REAL,
                    created_at TEXT, updated_at TEXT);
                INSERT INTO scientific_runs VALUES (
                    1,'id1','profile','done','engine','1','{}','{}','now','now');
                INSERT INTO profile_external_revisions VALUES (
                    2,1,'assistant','draft','{}','{}',0.5,'now','now');
                """)
            sent = []
            original = watcher.publisher.publish
            watcher.publisher.publish = lambda path, report: (sent.append(path) or "published")
            try:
                self.assertEqual(watcher.publish_changes(dbpath, Path(tmp), 5), (2, 0))
                self.assertEqual(watcher.publish_changes(dbpath, Path(tmp), 5), (0, 0))
                self.assertTrue(sent[0].startswith("assistant/"))
                with sqlite3.connect(dbpath) as db:
                    db.execute("UPDATE profile_external_revisions SET confidence=0.7 WHERE id=2")
                self.assertEqual(watcher.publish_changes(dbpath, Path(tmp), 5), (1, 0))
            finally:
                watcher.publisher.publish = original


if __name__ == "__main__":
    unittest.main()
