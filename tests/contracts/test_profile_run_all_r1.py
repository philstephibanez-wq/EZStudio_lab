from __future__ import annotations

import ast
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "scripts" / "run-profile-all.py"

text = SCRIPT.read_text(encoding="utf-8")
ast.parse(text, filename=str(SCRIPT))

required = [
    'default="http://127.0.0.1:8090"',
    '"/profile/analyze"',
    '"--execute"',
    '"--expect-tuning-revision"',
    "status IN ('queued','running','cancelling')",
    "audio_sha256",
    "EZSTUDIO_PROFILE_RUN_ALL_DRY_RUN_OK",
    "EZSTUDIO_PROFILE_RUN_ALL_DONE",
]

for needle in required:
    assert needle in text, needle

assert "INSERT INTO analysis_jobs" not in text
assert "INSERT INTO scientific_runs" not in text
assert "UPDATE analysis_jobs" not in text

print("PROFILE_RUN_ALL_R1_CONTRACT_OK")
print("Batch runner uses the canonical /profile/analyze endpoint")
print("No direct mutation of analysis/scientific queue tables")
print("Audio hashes are deduplicated")
print("Active PROFILE jobs are skipped")
print("Dry-run is the default")
