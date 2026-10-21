from pathlib import Path
import unittest
PAGE=(Path(__file__).resolve().parents[1]/"templates/workbench/profile_ai_button.html.twig").read_text(encoding="utf-8")
class ProfileAiProgressContract(unittest.TestCase):
    def test_progress_and_real_item(self):
        for token in ('id="profile-ai-progress"','max="100"',"Number(item?.progress)","progress.removeAttribute('value')","progress.value=100",'id="profile-ai-current-item"',"item?.current_item","item?.item_index","item?.item_total"):
            self.assertIn(token,PAGE)
        self.assertNotIn("État :",PAGE)
        self.assertIn("currentItem.hidden=!currentItem.textContent",PAGE)
    def test_single_job_polling(self):
        self.assertIn("path('profile_ai_review_status'",PAGE)
        self.assertIn("setInterval(refresh,5000)",PAGE)
