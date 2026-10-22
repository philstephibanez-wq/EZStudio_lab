from pathlib import Path
import unittest

TEMPLATE=(Path(__file__).resolve().parents[1]/"templates/workbench/profile.html.twig").read_text(encoding="utf-8")
BLOCK=TEMPLATE.split("{# Musical dimensions, not scientific engine inventory. Technical output is preserved. #}",1)[1].split('<details class="profile-ai-technical">',1)[0]

class ProfileMusicalSummary(unittest.TestCase):
    def test_one_tonality_and_no_engine_rows(self):
        self.assertEqual(BLOCK.count("{'name':'Tonalité'"),1)
        self.assertNotIn("{'name':'Tonalité (",BLOCK)
        self.assertIn("pv_simple.tonal.label",BLOCK)
    def test_two_requested_columns_and_nine_musical_items(self):
        self.assertIn("LAB : valeur",BLOCK)
        self.assertIn("ASSISTANT : résultat",BLOCK)
        self.assertEqual(BLOCK.count("{'name':"),9)
        self.assertIn("vocal_harmonies",BLOCK)
    def test_no_technical_scores_shown(self):
        for forbidden in ("module_results", "source_sha256", "duration_ms", "number_format(0) }} %"):
            self.assertNotIn(forbidden,BLOCK)

if __name__=="__main__": unittest.main()
