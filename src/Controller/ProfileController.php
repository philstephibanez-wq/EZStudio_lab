<?php

namespace App\Controller;

use App\Service\AnalysisRunRegistry;
use App\Service\ProfileExecutionService;
use App\Service\ProfileFrenchSummary;
use App\Service\ProfileExternalReviewService;
use App\Service\ProfileValidationService;
use App\Service\WorkbenchCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    private function songId(Request $request): ?int
    {
        $value = filter_var($request->query->get('song'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return ($value === false || $value === null) ? null : (int)$value;
    }

    #[Route('/workbench/profile', name: 'workbench_profile', methods: ['GET'])]
    public function index(
        Request $request,
        WorkbenchCatalog $catalog,
        AnalysisRunRegistry $runRegistry,
        ProfileExecutionService $execution,
        ProfileFrenchSummary $summary,
        ProfileValidationService $validation,
        ProfileExternalReviewService $externalReview,
    ): Response {
        $song = $catalog->selectedSong($this->songId($request));
        $runs = [];
        $selected = null;

        if ($song) {
            $seen = [];
            foreach ($song['song_ids'] ?? [(int)$song['id']] as $songId) {
                foreach ($runRegistry->runsForSongItem((int)$songId, 'profile') as $row) {
                    $id = (int)$row['id'];
                    if (isset($seen[$id])) continue;
                    $seen[$id] = true;
                    $full = $runRegistry->run($id);
                    if ($full) $runs[] = $full;
                }
            }
            usort($runs, static fn(array $a, array $b): int => (int)$b['id'] <=> (int)$a['id']);

            $requested = filter_var($request->query->get('run'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($requested !== false && $requested !== null) {
                foreach ($runs as $candidate) {
                    if ((int)$candidate['id'] === (int)$requested) {
                        $selected = $candidate;
                        break;
                    }
                }
            }
            if ($selected === null && $runs !== []) $selected = $runs[0];
        }

        return $this->render('workbench/profile.html.twig', [
            'songs' => $catalog->songs(),
            'selected_song' => $song,
            'profile_runs' => $runs,
            'selected_profile_run' => $selected,
            'profile_job' => $selected ? $execution->jobForRun((int)$selected['id']) : null,
            'profile_summary' => $selected ? $summary->build($selected) : null,
            'profile_external_revisions' => $selected ? $externalReview->revisionsForRun((int)$selected['id']) : [],
            'profile_validation_subjects' => $selected
                ? $validation->subjectsForRun($selected)
                : [],
            'profile_validations' => $selected
                ? $validation->validationsForRun((int)$selected['id'])
                : [],
            'profile_validation_stats' => $selected
                ? $validation->statsForRun(
                    (int)$selected['id'],
                    array_column(
                        $validation->subjectsForRun($selected),
                        'subject_key'
                    )
                )
                : [
                    'reviewed' => 0,
                    'ok' => 0,
                    'ko' => 0,
                    'unknown' => 0,
                    'known' => 0,
                    'accuracy' => null,
                ],
        ]);
    }

    #[Route(
        '/profile/{id<\d+>}/validate',
        name: 'profile_validate',
        methods: ['POST']
    )]
    public function validate(
        int $id,
        Request $request,
        AnalysisRunRegistry $runRegistry,
        ProfileValidationService $validation,
    ): Response {
        $run = $runRegistry->run($id);
        if (!$run || (string)($run['item'] ?? '') !== 'profile') {
            return new Response('Run PROFILE introuvable.', 404);
        }

        $annotations = $request->request->all('annotations');
        try {
            $validation->save(
                $run,
                is_array($annotations) ? $annotations : [],
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return new Response(
                'Validation refusée : '.htmlspecialchars(
                    $e->getMessage(),
                    ENT_QUOTES
                ),
                409
            );
        }

        return new RedirectResponse(
            $this->generateUrl('workbench_profile', [
                'song' => (int)$run['song_id'],
                'run' => (int)$run['id'],
            ])
        );
    }

    #[Route('/workbench/profile/song/{id<\\d+>}/identity',name:'profile_song_identity',methods:['POST'])]
    public function updateIdentity(int $id, Request $request, \App\Service\SongIdentityEditor $editor): Response
    {
        // Same-origin AJAX with non-simple header; no Symfony Form/CSRF extension installed.
        if ($request->headers->get('X-EZS-Song-Identity') !== 'save') {
            return new Response('Requête non autorisée.',403);
        }
        $origin=$request->headers->get('Origin');
        if ($origin !== null && $origin !== $request->getSchemeAndHttpHost()) {
            return new Response('Origine non autorisée.',403);
        }
        $site=$request->headers->get('Sec-Fetch-Site');
        if ($site !== null && !in_array($site,['same-origin','none'],true)) {
            return new Response('Origine non autorisée.',403);
        }
        try {
            $editor->update($id, (string)$request->request->get('title',''),
                (string)$request->request->get('artist',''));
        } catch (\InvalidArgumentException $e) {
            return new Response($e->getMessage(),422);
        } catch (\RuntimeException $e) {
            return new Response($e->getMessage(),404);
        }
        return new \Symfony\Component\HttpFoundation\JsonResponse(['ok'=>true,'song_id'=>$id]);
    }

    #[Route('/profile/analyze', name: 'profile_analyze', methods: ['POST'])]
    public function analyze(Request $request, WorkbenchCatalog $catalog, AnalysisRunRegistry $runRegistry, ProfileExecutionService $execution): Response
    {
        $songId = filter_var($request->request->get('song'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($songId === false || $songId === null) return new Response('Chanson invalide.', 400);

        $song = $catalog->selectedSong((int)$songId);
        if (!$song) return new Response('Chanson introuvable.', 404);

        $source = $catalog->sourcePath($song);
        if ($source === null) return new Response('Source IMPORT indisponible.', 409);

        $run = $runRegistry->createRun(
            (int)$song['id'],
            'profile',
            ['name' => 'ezstudio-profile-modules', 'version' => 'r3b31', 'model' => 'multi-engine-profile-modules'],
            [
                'audio_sha256' => (string)$song['audio_sha256'],
                'output_contract' => 'ezstudio.profile.v1',
                'automatic_next_stage' => false,
            ],
        );

        $execution->queue((int)$run['id'], $source, (string)$song['audio_sha256']);

        return new RedirectResponse($this->generateUrl('workbench_profile', [
            'song' => (int)$song['id'],
            'run' => (int)$run['id'],
        ]));
    }
}
