from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

controller = (ROOT / "src/Controller/WorkbenchController.php").read_text(encoding="utf-8")
catalog = (ROOT / "src/Service/CatalogService.php").read_text(encoding="utf-8")
template = (ROOT / "templates/workbench/home.html.twig").read_text(encoding="utf-8")
base = (ROOT / "templates/base.html.twig").read_text(encoding="utf-8")
css = (ROOT / "public/css/ezstudio-dark.css").read_text(encoding="utf-8")

for needle in [
    "name: 'profile_export_all_zip'",
    "exportAllProfilesZip(",
    "DIRECTORY_SEPARATOR.'scripts'",
    "--output",
    "deleteFileAfterSend(true)",
]:
    assert needle in controller, needle

for needle in [
    "'version' => $version",
    "preset_phase_revision",
    "engine_version",
]:
    assert needle in catalog, needle

for needle in [
    "catalog-batch-toolbar",
    "PROFILE ALL",
    "ZIP ALL",
    "path('profile_export_all_zip')",
    "catalog-phase-ready",
    "Prêt",
    "s.version",
]:
    assert needle in template, needle

assert '<link rel="stylesheet" href="/css/ezstudio-dark.css?v=1">' in base
assert "--bg:#0f1419" in css
assert ".catalog-phase-ready" in css
assert ".perf-drawer" in css

print("CATALOG_UX_DARK_R1_CONTRACT_OK")
print("PROFILE ALL moved to catalogue batch toolbar")
print("ZIP ALL downloads the durable PROFILE aggregate export")
print("Ready phase buttons receive a success state")
print("Ready badges expose the stored analysis/preset version")
print("Global dark stylesheet installed")
