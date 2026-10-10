from __future__ import annotations

import ast
import sqlite3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

for rel in [
    "python/ezstudio/runtime/module_config.py",
]:
    ast.parse((ROOT/rel).read_text(encoding="utf-8"), filename=rel)

db = (ROOT/"src/Service/Database.php").read_text(encoding="utf-8")
assert "song_id,phase,operation,owner,created_at,expires_at_epoch" in db
assert "song_id,region,operation,owner,created_at,expires_at_epoch" not in db

validation = (ROOT/"src/Service/ProfileValidationService.php").read_text(encoding="utf-8")
for key in [
    "phase:tempo",
    "phase:time_signature",
    "phase:key",
    "phase:instrumentation",
    "phase:choirs",
    "phase:audioset",
]:
    assert key in validation, key

for key in [
    "region:tempo",
    "region:time_signature",
    "region:key",
    "region:instrumentation",
    "region:choirs",
    "region:audioset",
]:
    assert key not in validation, key

preset = (ROOT/"src/Service/PresetRegistry.php").read_text(encoding="utf-8")
assert "$manifest['modules'] ?? null" in preset
assert "$manifest['genes']" not in preset

profile = (ROOT/"templates/workbench/profile.html.twig").read_text(encoding="utf-8")
assert "Phase PROFILE de la chaîne" in profile
assert "Région PROFILE du chromosome" not in profile
assert "conservés dans l’ADN" not in profile
assert "l’ADN conserve" not in profile
assert "subject.scope == 'module' ? 'Module' : 'Phase'" in profile

stems_job = (ROOT/"templates/lab/stems_job.html.twig").read_text(encoding="utf-8")
assert '<a href="#preset">Preset</a>' in stems_job

# Verify the migrated DB schema is coherent with the SQL source.
db_path = ROOT/"data/benchmark.sqlite"
if db_path.is_file():
    con = sqlite3.connect(db_path)
    tables = {r[0] for r in con.execute(
        "SELECT name FROM sqlite_master WHERE type='table'"
    ).fetchall()}
    if "analysis_resource_locks" in tables:
        cols = {r[1] for r in con.execute(
            "PRAGMA table_info(analysis_resource_locks)"
        ).fetchall()}
        assert "phase" in cols
        assert "region" not in cols
    if "profile_human_validations" in tables:
        bad = con.execute(
            "SELECT COUNT(*) FROM profile_human_validations "
            "WHERE scope IN ('region','gene') OR subject_key LIKE 'region:%'"
        ).fetchone()[0]
        assert int(bad) == 0, bad
    assert not con.execute("PRAGMA foreign_key_check").fetchall()
    con.close()

print("TERMINOLOGY_PRESET_R2C_CONTRACT_OK")
print("Runtime DB lock vocabulary: phase")
print("PROFILE human validation keys: phase:*")
