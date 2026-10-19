<?php

declare(strict_types=1);
namespace App\Controller;

use App\Service\LabAnalysisTokenGuard;
use App\Service\ProfileAiRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileAiRequestController extends AbstractController
{
    public function __construct(private readonly ProfileAiRequestService $queue, private readonly LabAnalysisTokenGuard $guard) {}

    #[Route('/workbench/profile/{id<\d+>}/ai-review',name:'profile_ai_review_request',methods:['POST'])]
    public function request(int $id,Request $request): JsonResponse
    {
        // LAB does not install symfony/form or Twig csrf_token().
        // Require a non-simple same-origin AJAX request instead of a cross-site form POST.
        if ($request->headers->get('X-EZS-Profile-AI-Action') !== 'request') {
            return $this->json(['error'=>'same_origin_ajax_required'],403);
        }
        $origin = $request->headers->get('Origin');
        if ($origin !== null && $origin !== $request->getSchemeAndHttpHost()) {
            return $this->json(['error'=>'cross_origin_denied'],403);
        }
        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        if ($fetchSite !== null && !in_array($fetchSite, ['same-origin','none'],true)) {
            return $this->json(['error'=>'cross_site_denied'],403);
        }
        try {return $this->json(['ok'=>true,'request'=>$this->queue->enqueue($id)],202);}
        catch (\RuntimeException $e) {return $this->json(['error'=>$e->getMessage()],409);}
    }

    #[Route('/workbench/profile/{id<\d+>}/ai-review',name:'profile_ai_review_status',methods:['GET'])]
    public function status(int $id): JsonResponse
    {
        return $this->json(['request'=>$this->queue->get($id)]);
    }

    #[Route('/internal/analysis/desktop/profile/ai-requests/recent', name: 'lab_internal_profile_ai_recent', methods: ['GET'])]
    public function recent(Request $request): JsonResponse
    {
        $this->guard->assertAuthorized($request);
        $limit = filter_var($request->query->get('limit', 80), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($limit === false) return $this->json(['error' => 'invalid_limit'], 422);
        return $this->json(['schema' => 'ezstudio.profile-ai-history.v1',
            'jobs' => $this->queue->recent((int)$limit)]);
    }

    #[Route('/internal/analysis/desktop/profile/ai-requests',name:'lab_internal_profile_ai_requests',methods:['GET'])]
    public function pending(Request $request): JsonResponse
    {
        $this->guard->assertAuthorized($request);
        return $this->json(['schema'=>'ezstudio.profile-ai-requests.v1','requests'=>$this->queue->pending()]);
    }

    #[Route('/internal/analysis/desktop/profile/ai-requests/{id<\d+>}/claim',name:'lab_internal_profile_ai_claim',methods:['POST'])]
    public function claim(Request $request,int $id): JsonResponse
    {
        $this->guard->assertAuthorized($request);
        return $this->json(['claimed'=>$this->queue->claim($id)]);
    }

    #[Route('/internal/analysis/desktop/profile/ai-requests/{id<\d+>}/finish',name:'lab_internal_profile_ai_finish',methods:['POST'])]
    public function finish(Request $request,int $id): JsonResponse
    {
        $this->guard->assertAuthorized($request);
        try {
            $p=$request->toArray();
            return $this->json(['ok'=>true,'request'=>$this->queue->finish($id,(string)($p['status']??''),(string)($p['message']??''))]);
        } catch (\Throwable $e) {return $this->json(['error'=>$e->getMessage()],409);}
    }
}
