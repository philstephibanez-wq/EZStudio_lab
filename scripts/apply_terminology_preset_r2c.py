from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def patch(rel: str, pairs: list[tuple[str, str]]) -> None:
    path = ROOT / rel
    if not path.is_file():
        raise RuntimeError(f"required_file_missing:{rel}")
    text = path.read_text(encoding="utf-8")
    original = text
    for old, new in pairs:
        text = text.replace(old, new)
    if text != original:
        path.write_text(text, encoding="utf-8", newline="\n")


# 1. Runtime docs / local variable names.
patch("python/ezstudio/runtime/adapters/__init__.py", [
    ("Region-specific scientific policy", "Phase-specific scientific policy"),
])
patch("python/ezstudio/runtime/engine_registry.py", [
    ("Region-agnostic registry", "Phase-agnostic registry"),
])
patch("python/ezstudio/runtime/module_config.py", [
    ('gene = _require_mapping(data.get("module"), "module")',
     'module = _require_mapping(data.get("module"), "module")'),
    ('_require_string(gene, "id", "module")',
     '_require_string(module, "id", "module")'),
    ('_require_string(gene, "revision", "module")',
     '_require_string(module, "revision", "module")'),
    ('region = _require_string(gene, "phase", "module")',
     'phase = _require_string(module, "phase", "module")'),
    ('if region not in {"profile", "stems", "chords", "lyrics"}:',
     'if phase not in {"profile", "stems", "chords", "lyrics"}:'),
])

# 2. PROFILE run metadata.
patch("src/Controller/ProfileController.php", [
    ("'ezstudio-profile-genes'", "'ezstudio-profile-modules'"),
    ("'multi-engine-profile-genes'", "'multi-engine-profile-modules'"),
])

# 3. DB phase-lock SQL: this is functional, not cosmetic.
patch("src/Service/Database.php", [
    ("song_id,region,operation,owner,created_at,expires_at_epoch",
     "song_id,phase,operation,owner,created_at,expires_at_epoch"),
])

# 4. Preset registry no longer accepts active legacy serialized key.
patch("src/Service/PresetRegistry.php", [
    ("$manifest['modules'] ?? $manifest['genes'] ?? null",
     "$manifest['modules'] ?? null"),
])

# 5. Human validation subject keys must match migrated DB.
patch("src/Service/ProfileValidationService.php", [
    ("'region:tempo'", "'phase:tempo'"),
    ("'region:time_signature'", "'phase:time_signature'"),
    ("'region:key'", "'phase:key'"),
    ("'region:instrumentation'", "'phase:instrumentation'"),
    ("'region:choirs'", "'phase:choirs'"),
    ("'region:audioset'", "'phase:audioset'"),
])

# 6. Remaining visible/UI terminology.
patch("templates/lab/stems_job.html.twig", [
    ('<a href="#preset">ADN</a>', '<a href="#preset">Preset</a>'),
])
patch("templates/workbench/home.html.twig", [
    ('id="catalog-live-region"', 'id="catalog-live-phase"'),
])
patch("templates/workbench/profile.html.twig", [
    ("Région PROFILE du chromosome : caractérisation globale du master avant STEMS.",
     "Phase PROFILE de la chaîne : caractérisation globale du master avant STEMS."),
    ("Autres éléments sonores conservés dans l’ADN",
     "Autres éléments sonores conservés dans le résultat d’analyse"),
    ("Le formulaire valide les conclusions principales ; l’ADN conserve les données scientifiques et les scores d’origine.",
     "Le formulaire valide les conclusions principales ; le run conserve les données scientifiques et les scores d’origine."),
    ("subject.scope == 'gene' ? 'Gène' : 'Région'",
     "subject.scope == 'module' ? 'Module' : 'Phase'"),
])

print("TERMINOLOGY_PRESET_R2C_CODE_APPLIED")
print("Phase-lock SQL and PROFILE validation keys aligned")
print("Remaining visible legacy labels cleaned")
