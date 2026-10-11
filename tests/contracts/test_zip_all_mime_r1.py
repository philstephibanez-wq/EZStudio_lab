from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
controller = (ROOT / "src/Controller/WorkbenchController.php").read_text(encoding="utf-8")

assert "name: 'profile_export_all_zip'" in controller
assert "$response->headers->set('Content-Type', 'application/zip');" in controller
assert "$response->deleteFileAfterSend(true);" in controller
assert "composer require symfony/mime" not in controller

print("ZIP_ALL_MIME_R1_CONTRACT_OK")
print("ZIP ALL response has an explicit application/zip MIME type")
print("Optional symfony/mime package is not required")
