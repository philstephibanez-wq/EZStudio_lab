"""Contract: PROFILE verdict labels and documentary abstention must remain distinct."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SERVICE = (ROOT/'src/Service/ProfileExternalReviewService.php').read_text(encoding='utf-8')
TEMPLATE = (ROOT/'templates/workbench/profile.html.twig').read_text(encoding='utf-8')

class ProfileVerdictLabels1640(unittest.TestCase):
    def test_service_projection_uses_contract_labels(self):
        self.assertIn("$verdict === 'faux' ? 'FAUX'", SERVICE)
        self.assertIn("$verdict === 'sans_valeur_lab' ? 'SANS VALEUR LAB' : 'NON TROUVÉ'",SERVICE)
        self.assertNotIn("$verdict === 'faux' ? 'MAUVAIS' : 'INCONNU'",SERVICE)
    def test_template_distinguishes_no_lab_value_and_no_external_evidence(self):
        self.assertIn('SANS VALEUR LAB — aucune proposition à vérifier', TEMPLATE)
        self.assertIn('NON TROUVÉ — aucune preuve externe admissible pour cette valeur',TEMPLATE)
        self.assertIn("verdict == 'faux' ? 'FAUX'",TEMPLATE)
        self.assertNotIn("verdict == 'faux' ? 'MAUVAIS'",TEMPLATE)
    def test_documentary_rationale_is_visible_without_promoting_proposals(self):
        self.assertIn('Motif du verdict : {{ verdict_row.reason }}',TEMPLATE)
        self.assertIn('Motif documentaire : {{ web_item.reason }}',TEMPLATE)
        self.assertIn("{% if verdict in ['bon','faux'] %}",TEMPLATE)
        self.assertIn('Enregistrement exact non confirmé · conclusion scientifique inchangée.',TEMPLATE)
    def test_no_scientific_or_job_mutation(self):
        self.assertNotIn('UPDATE analysis_jobs',TEMPLATE)
        self.assertNotIn('persist()',TEMPLATE)
        self.assertIn('Chaque proposition musicale LAB est vérifiée séparément.',TEMPLATE)

if __name__=='__main__': unittest.main()
