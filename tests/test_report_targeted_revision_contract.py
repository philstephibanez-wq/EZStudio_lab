"""Guardrails for targeted, complete Assistant revision archive."""
import pathlib
import sqlite3
import sys
import unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'))
from report_diagnostics import reports

class TargetedArchiveTest(unittest.TestCase):
    def test_revision_filter_never_exports_unrelated_records(self):
        db=sqlite3.connect(':memory:')
        db.execute('CREATE TABLE profile_external_revisions (id INTEGER,run_id INTEGER,comparison_json TEXT)')
        db.executemany('INSERT INTO profile_external_revisions VALUES (?,?,?)',[(51,153,'{}'),(52,153,'{"module_consultations":{"item_total":12,"complete":true,"results":[]}}')])
        rows=list(reports(db,revision_id=52))
        self.assertEqual(len(rows),1)
        self.assertIn('revision-000052',rows[0][0])
        self.assertEqual(rows[0][1]['revision_id'],52)

    def test_publisher_contains_fail_closed_coverage_gate(self):
        source=(ROOT/'scripts'/'publish_run_reports.py').read_text(encoding='utf-8')
        self.assertIn('selected_revision_missing_module_consultations',source)
        self.assertIn('selected_revision_incomplete_module_coverage',source)
        self.assertIn('selected_revision_module_status_invalid',source)
        self.assertIn('selected_revision_not_found_or_incomplete',source)

if __name__=='__main__':
    unittest.main()
