from __future__ import annotations
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
path = ROOT / "python/ezstudio/runtime/contracts.py"
if not path.is_file():
    raise RuntimeError(f"contracts_missing:{path}")

text = path.read_text(encoding="utf-8")

old = '''            display_name=_required_text(data.get("display_name"), "module.display_name"),
            family=_required_text(data.get("family"), "module.family"),
'''
new = '''            display_name=_required_text(
                data.get("display_name") or data.get("id"),
                "module.display_name",
            ),
            family=_required_text(
                data.get("family")
                or str(data.get("id") or "").split(".", 1)[0],
                "module.family",
            ),
'''

if old not in text:
    raise RuntimeError("v2b_compat_anchor_missing")

text = text.replace(old, new)
path.write_text(text, encoding="utf-8", newline="\n")

print("ANALYSIS_ENGINE_V2B_V2A_COMPAT_HOTFIX_APPLIED")
print("V2B explicit display_name/family preserved")
print("V2A legacy presets gain deterministic read compatibility")
