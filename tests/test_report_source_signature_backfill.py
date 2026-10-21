import json
import sys
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"scripts"))
import publish_run_reports as mod

class BackfillSourceSignatureContract(unittest.TestCase):
    def test_unchanged_report_updates_source_signature(self):
        src=(Path(__file__).resolve().parents[1]/"scripts/publish_run_reports.py").read_text(encoding="utf-8")
        self.assertIn('state.get("source:"+path) != pending_signatures[path]',src)
        self.assertIn('state["source:"+path] = pending_signatures[path]',src)
        anchor=src.index('if not args.dry_run and not args.audit_detailed and state.get(path) == digest:')
        tail=src[anchor:src.index('            if args.limit and attempted >= args.limit:',anchor)]
        self.assertIn('_save_state(state_file, state)',tail)
        self.assertIn('continue',tail)
