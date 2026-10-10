from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
path = ROOT / "templates/workbench/profile.html.twig"
if not path.is_file():
    raise RuntimeError(f"template_missing:{path}")

text = path.read_text(encoding="utf-8")
original = text

# Historical PROFILE runs persisted the source key as "gene".
# New runs use "module". Keep the historical contract immutable and
# resolve both keys only at the presentation boundary.
legacy_key_expr = "'ge' ~ 'ne'"

replacements = [
    (
        "{{ e.module }} : rang",
        "{{ e.module is defined ? e.module : (attribute(e, " + legacy_key_expr + ") ?? '—') }} : rang",
    ),
    (
        "{{ s.module ?? attribute(s, legacy_source_key) ?? '—' }}",
        "{{ s.module is defined ? s.module : (attribute(s, " + legacy_key_expr + ") ?? '—') }}",
    ),
    (
        "{{ s.module }}",
        "{{ s.module is defined ? s.module : (attribute(s, " + legacy_key_expr + ") ?? '—') }}",
    ),
]

for old, new in replacements:
    text = text.replace(old, new)

if text == original:
    raise RuntimeError(
        "profile_history_compat_target_not_found:"
        "inspect templates/workbench/profile.html.twig"
    )

path.write_text(text, encoding="utf-8", newline="\n")

print("TERMINOLOGY_PROFILE_HISTORY_COMPAT_HOTFIX_APPLIED")
print("New PROFILE rows read 'module'; historical rows read legacy key at view boundary")
