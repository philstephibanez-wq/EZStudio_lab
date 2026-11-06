<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Database;
use PDO;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** LAB DEV-only queue observation: no status changes, no secrets or source paths. */
final class QueueDiagnosticController
{
    #[Route('/api/dev/queue-diagnostic', name: 'ezstudio_queue_diagnostic', methods: ['GET'])]
    public function diagnostic(Request $request, Database $database): JsonResponse
    {
        $environment = (string) ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '');
        if ($environment !== 'dev' || !in_array($request->getHost(), ['127.0.0.1','localhost'], true)
            || !in_array((string) $request->server->get('REMOTE_ADDR', ''), ['127.0.0.1','::1'], true)) {
            return $this->reply(['error'=>'dev_loopback_only'], 403);
        }
        try {
            $pdo = $database->pdo();
            $jobs = $pdo->query("SELECT j.id,j.kind,j.status,j.dispatch_owner,j.progress,j.scientific_run_id,j.created_at,j.updated_at,o.state AS admission_state,o.global_job_id,o.attempts FROM analysis_jobs j LEFT JOIN global_queue_admission_outbox o ON o.job_id=j.id ORDER BY j.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
            $counts = $pdo->query('SELECT status,dispatch_owner,COUNT(*) AS quantity FROM analysis_jobs GROUP BY status,dispatch_owner')->fetchAll(PDO::FETCH_ASSOC);
            $runs = $pdo->query("SELECT id,item,state,song_id,created_at,finished_at FROM scientific_runs ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
            return $this->reply([
                'schema'=>'ezstudio.queue.diagnostic.v1','source'=>'lab_local_sqlite',
                'observed_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'available'=>true,
                'counts'=>$counts,'jobs'=>$jobs,'scientific_runs'=>$runs,
                'global_state'=>'NOT_OBSERVED_FROM_LAB',
                'limits'=>['jobs'=>100,'scientific_runs'=>50,'no_private_audio_or_request_payload'=>true],
            ], 200);
        } catch (\Throwable) {
            return $this->reply(['schema'=>'ezstudio.queue.diagnostic.v1','available'=>false,'reason'=>'read_failed'], 200);
        }
    }
    private function reply(array $body,int $status): JsonResponse
    {
        return new JsonResponse($body,$status,[
            'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff',
            'X-Robots-Tag'=>'noindex, nofollow',
        ]);
    }
}
