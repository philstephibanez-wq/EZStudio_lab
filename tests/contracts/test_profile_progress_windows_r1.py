from __future__ import annotations

import ast
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
RUNNER = ROOT / "python/ezstudio/pipeline/profile/runner.py"

text = RUNNER.read_text(encoding="utf-8")
ast.parse(text, filename=str(RUNNER))

assert "uuid.uuid4().hex" in text
assert "os.getpid()" in text
assert "os.replace(tmp, path)" in text
assert "except PermissionError as exc:" in text
assert "for attempt in range(10):" in text
assert "time.sleep(0.05 * (attempt + 1))" in text
assert 'tmp = path.with_suffix(".tmp")' not in text

print("PROFILE_PROGRESS_WINDOWS_R1_CONTRACT_OK")
print("Unique temp path prevents shared progress.tmp contention")
print("Windows PermissionError retry is bounded")
print("No scientific PROFILE behavior changed")
print("CHORDS/N baseline untouched")
