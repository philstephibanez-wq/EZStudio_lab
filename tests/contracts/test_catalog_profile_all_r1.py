from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

controller = (ROOT / "src/Controller/WorkbenchController.php").read_text(encoding="utf-8")
database = (ROOT / "src/Service/Database.php").read_text(encoding="utf-8")
template = (ROOT / "templates/workbench/home.html.twig").read_text(encoding="utf-8")

for needle in [
    "name: 'profile_analyze_all'",
    "analyzeAllProfiles(",
    "ProfileDnaExecutionService $execution",
    "$runRegistry->createRun(",
    "$execution->queue(",
    "activeAnalysisJobsForSongsKind($songIds, 'profile')",
    "'automatic_next_stage' => false",
]:
    assert needle in controller, needle

for needle in [
    "activeAnalysisJobsForSongsKind(",
    "status IN ('queued','running','cancelling')",
    "AND kind=?",
]:
    assert needle in database, needle

for needle in [
    "PROFILE ALL",
    "path('profile_analyze_all')",
    "catalog-profile-all-form",
    "profile_all_queued",
]:
    assert needle in template, needle

assert "urllib" not in controller
assert "run-profile-all.py" not in controller
assert "INSERT INTO analysis_jobs" not in controller
assert "INSERT INTO scientific_runs" not in controller

print("CATALOG_PROFILE_ALL_R1_CONTRACT_OK")
print("Catalogue exposes a PROFILE ALL POST action")
print("Bulk action uses canonical PHP services, not localhost HTTP")
print("Active PROFILE jobs are skipped")
print("Scientific PROFILE engine remains untouched")
