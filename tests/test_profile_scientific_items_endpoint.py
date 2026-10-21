from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
CODE = (ROOT / "src/Controller/ProfileAiRequestController.php").read_text(encoding="utf-8")

class ProfileItemsEndpointContract(unittest.TestCase):
    def test_authenticated_and_read_only(self):
        self.assertIn("scientific-items", CODE)
        start = CODE.index("public function scientificItems(")
        end = CODE.index("    #[Route(", start)
        block = CODE[start:end]
        self.assertIn("$this->guard->assertAuthorized($request)", block)
        self.assertIn("$runs->run($id)", block)
        self.assertIn("'item_total'=>count($items)", block)
        self.assertIn("'module'=>$module", block)
        self.assertNotIn("->enqueue(", block)
        self.assertNotIn("->exec(", block)
    def test_rejects_missing_and_duplicate_module_ids(self):
        start = CODE.index("public function scientificItems(")
        end = CODE.index("    #[Route(", start)
        block = CODE[start:end]
        self.assertIn("scientific_module_registry_missing", block)
        self.assertIn("isset($seen[$key])", block)
        self.assertIn("scientific_module_identity_invalid", block)
