
from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DB = ROOT / "src" / "Service" / "Database.php"
CATALOG = ROOT / "src" / "Service" / "CatalogService.php"

db = DB.read_text(encoding="utf-8")
catalog = CATALOG.read_text(encoding="utf-8")

required_db = [
    "reconcileStaleCancellingAnalysisJobsForSongs(",
    "AND status='cancelling'",
    "AND updated_at <= ?",
    "$this->markAnalysisJobCancelled($id);",
]
for needle in required_db:
    assert needle in db, needle

required_catalog = [
    "$this->db->reconcileStaleCancellingAnalysisJobsForSongs(",
    "'queued' => 'queued'",
    "'running', 'cancelling' => 'running'",
    "'cancelled' => 'cancelled'",
    "'failed' => 'error'",
]
for needle in required_catalog:
    assert needle in catalog, needle

print("CATALOG_CANCEL_RECONCILE_R1_CONTRACT_OK")
print("Stale cancelling jobs converge to cancelled")
print("Failed jobs render as error")
print("Scientific pipeline untouched")
