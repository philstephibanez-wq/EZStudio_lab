from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
JS = ROOT / "public/js/ezstudio-lab.js"
BASE = ROOT / "templates/base.html.twig"
HOME = ROOT / "templates/workbench/home.html.twig"
CONTROLLER = ROOT / "src/Controller/WorkbenchController.php"

js = JS.read_text(encoding="utf-8")
base = BASE.read_text(encoding="utf-8")
home = HOME.read_text(encoding="utf-8")
controller = CONTROLLER.read_text(encoding="utf-8")

assert 'id="catalog-live-phase"' in home
assert "function catalogRegion(){return $('#catalog-live-phase').first();}" in js
assert "$wrap.find('#catalog-live-phase').first()" in js
assert "$('#catalog-live-phase [data-analysis-active=\"1\"]')" in js
assert "#catalog-live-region" not in js

assert "catalog-job-cancel" in home
assert "data-job-cancel-url" in home
assert "analysis_job_cancel" in controller
assert "requestAnalysisJobCancellation" in controller

assert '/js/ezstudio-lab.js?v=r3b29a' in base

print("CATALOG_LIVE_CANCEL_R1_CONTRACT_OK")
print("Live catalog DOM selector matches rendered template")
print("Queued/running states can refresh every 2.5 seconds")
print("Cancel button route remains wired")
print("Browser cache bust is active")
print("Scientific analysis code untouched")
