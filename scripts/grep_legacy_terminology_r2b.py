from __future__ import annotations

import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PATTERN = re.compile(
    r"\b(?:ADN|DNA|Chromosome|chromosome|Genome|genome|Gene|gene|Genes|genes|"
    r"Région|région|Region|region)\b"
)
ACTIVE_ROOTS = ("src/","templates/","python/","presets/","config/","contracts/")
EXCLUDE = {
    "python/ezstudio/pipeline/profile/gene.py",
    "python/ezstudio/pipeline/profile/gene_registry.py",
    "python/ezstudio/runtime/gene_spec.py",
    "src/Service/DnaRegistry.php",
    "src/Service/GenomeRegistry.php",
}

cp = subprocess.run(
    ["git","ls-files","-co","--exclude-standard"],
    cwd=ROOT, check=True, capture_output=True, text=True,
    encoding="utf-8", errors="replace",
)
rows=[]
for rel in sorted(set(x.strip().replace("\\","/") for x in cp.stdout.splitlines() if x.strip())):
    if not rel.startswith(ACTIVE_ROOTS) or rel in EXCLUDE:
        continue
    p=ROOT/rel
    if not p.is_file() or p.suffix.lower() not in {".py",".php",".twig",".json",".yaml",".yml"}:
        continue
    try:
        text=p.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        continue
    for n,line in enumerate(text.splitlines(),1):
        if PATTERN.search(line):
            rows.append((rel,n,line.strip()))

out=ROOT/"TERMINOLOGY_GREP_R2B_AFTER.txt"
out.write_text(
    "\n".join(f"{p}:{n}:{line}" for p,n,line in rows)+("\n" if rows else ""),
    encoding="utf-8",
)
print("TERMINOLOGY_GREP_R2B_OK")
print(f"active_remaining_occurrences={len(rows)}")
print(f"report={out}")
for p,n,line in rows[:80]:
    print(f"{p}:{n}:{line}")
