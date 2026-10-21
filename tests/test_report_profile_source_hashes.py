"""Non-regression: preserve module SHA before privacy redaction, without exposing values."""
import sys
import unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))
from report_diagnostics import make_record


class ProfileArchiveSourceHashes(unittest.TestCase):
    def test_pre_and_post_redaction_are_distinct_and_safe(self):
        module = {"id": "semantic.clap-open-vocabulary", "raw": {"api_key": "sensitive-unit-test-value"}}
        values = {"diagnostics_json": {"module_registry": {"modules": [module]}}}
        report = make_record("lab", "profile", 153, values)
        rows = report["module_integrity"]
        self.assertEqual(len(rows), 1)
        self.assertTrue(rows[0]["archive_changed"])
        self.assertNotEqual(rows[0]["source_sha256_before_redaction"],
                            rows[0]["archive_sha256_after_redaction"])
        self.assertEqual(report["fields"]["diagnostics_json"]["module_registry"]["modules"][0]["raw"]["api_key"],
                         "[REDACTED_SECRET_OR_BINARY]")
        self.assertNotIn("sensitive-unit-test-value", str(report))

    def test_unmodified_module_hashes_match(self):
        values = {"diagnostics_json": {"module_registry": {"modules": [
            {"id": "semantic.clap-open-vocabulary", "normalized": {"genre": "folk"}}]}}}
        report = make_record("lab", "profile", 153, values)
        item = report["module_integrity"][0]
        self.assertFalse(item["archive_changed"])
        self.assertEqual(item["source_sha256_before_redaction"], item["archive_sha256_after_redaction"])

    def test_only_lab_profile_instrumented(self):
        value = {"diagnostics_json": {"module_registry": {"modules": [{"id": "a"}]}}}
        self.assertEqual(make_record("assistant", "profile", 153, value)["module_integrity"], [])
        self.assertEqual(make_record("lab", "stems", 153, value)["module_integrity"], [])


if __name__ == "__main__":
    unittest.main()
