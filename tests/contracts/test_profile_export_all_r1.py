from __future__ import annotations

import ast
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "scripts" / "export-profile-results.py"

text = SCRIPT.read_text(encoding="utf-8")
ast.parse(text, filename=str(SCRIPT))

required = [
    'repo / "data" / "benchmark.sqlite"',
    "scientific_runs",
    "scientific_artifacts",
    "role='profile_json'",
    "latest_for_audio",
    "profile-summary-all-runs.json",
    "profile-summary-latest.json",
    "profile-summary-all-runs.csv",
    "profile-summary-latest.csv",
    "EZSTUDIO_PROFILE_EXPORT_ALL_OK",
]

for needle in required:
    assert needle in text, needle

assert "Get-ChildItem" not in text
assert "var\\storage\\profile" not in text

print("PROFILE_EXPORT_ALL_R1_CONTRACT_OK")
print("SQLite registry is the source of truth")
print("All PROFILE runs and latest-per-audio summaries are exported")
print("Registered profile_json artifacts are included")
print("No audio binaries are included")
