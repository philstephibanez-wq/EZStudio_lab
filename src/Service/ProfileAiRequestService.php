<?php

declare(strict_types=1);
namespace App\Service;

/** Canonical analysis_jobs-backed PROFILE AI requests. No sidecar queue. */
final class ProfileAiRequestService
{
    private const KIND = 'assistant.profile.review';
    public function __construct(private readonly Database $db) {}

    private function pdo(): \PDO { return $this->db->pdo(); }

    private function latest(int $runId): ?array
    {
        $sql = "SELECT id, scientific_run_id AS run_id, status, progress, error AS message, updated_at
                FROM analysis_jobs WHERE scientific_run_id=? AND kind=? ORDER BY id DESC LIMIT 1";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$runId,self::KIND]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['run_id'] = (int)$row['run_id'];
        $row['job_id'] = (int)$row['id'];
        $row['attempt'] = 0;
        $row['message'] = $row['message'] ?? '';
        return $row;
    }

    public function get(int $runId): ?array { return $this->latest($runId); }

    public function enqueue(int $runId): array
    {
        $pdo = $this->pdo();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $stmt=$pdo->prepare("SELECT sr.id,sr.song_id,s.source_path FROM scientific_runs sr JOIN songs s ON s.id=sr.song_id WHERE sr.id=? AND sr.item='profile' AND sr.state='done'");
            $stmt->execute([$runId]);
            $run=$stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$run) throw new \RuntimeException('run_profile_non_termine');
            $current=$this->latest($runId);
            if ($current && in_array($current['status'],['queued','running'],true)) {
                $pdo->commit(); return $current;
            }
            $now=gmdate('c');
            // Reuse an existing logical job; never create duplicates for the same run.
            if ($current) {
                $pdo->prepare("UPDATE analysis_jobs SET status='queued',progress=0,error=NULL,updated_at=? WHERE id=?")
                    ->execute([$now,(int)$current['job_id']]);
            } else {
                $req=json_encode(['phase'=>'profile','scientific_run_id'=>$runId,'processor'=>'qwen','schema'=>'ezs.assistant.profile.job.v1'],JSON_UNESCAPED_UNICODE);
                $pdo->prepare('INSERT INTO analysis_jobs(run_id,scientific_run_id,song_id,source_path,kind,status,progress,request_json,error,created_at,updated_at) VALUES(NULL,?,?,?, ?,\'queued\',0,?,NULL,?,?)')
                    ->execute([$runId,(int)$run['song_id'],(string)($run['source_path']??''),self::KIND,$req,$now,$now]);
            }
            $pdo->commit();
            return $this->latest($runId) ?? throw new \RuntimeException('ai_job_insert_failed');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Read-only canonical AI job history for Orchestrator; no queue migration. */
    public function recent(int $limit = 80): array
    {
        $limit = max(1, min(100, $limit));
        $sql = "SELECT aj.id AS job_id, aj.scientific_run_id AS run_id,
                       aj.status, aj.progress, aj.error AS message,
                       aj.created_at, aj.updated_at,
                       aj.song_id, s.title AS song_title
                FROM analysis_jobs aj
                LEFT JOIN songs s ON s.id = aj.song_id
                WHERE aj.kind = 'assistant.profile.review'
                ORDER BY aj.updated_at DESC, aj.id DESC LIMIT ?";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static function (array $row): array {
            foreach (['job_id', 'run_id', 'progress', 'song_id'] as $key) {
                if ($row[$key] !== null) $row[$key] = (int)$row[$key];
            }
            return $row;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function pending(): array
    {
        $this->migrateLegacy();
        $rows=$this->pdo()->query("SELECT id AS job_id,scientific_run_id AS run_id,status,updated_at FROM analysis_jobs WHERE kind='assistant.profile.review' AND status='queued' ORDER BY created_at ASC,id ASC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(static function(array $r): array {$r['run_id']=(int)$r['run_id'];$r['job_id']=(int)$r['job_id'];return $r;},$rows);
    }

    public function claim(int $runId): bool
    {
        $stmt=$this->pdo()->prepare("UPDATE analysis_jobs SET status='running',progress=1,error=NULL,updated_at=? WHERE scientific_run_id=? AND kind=? AND status='queued'");
        $stmt->execute([gmdate('c'),$runId,self::KIND]);
        return $stmt->rowCount()===1;
    }

    public function finish(int $runId,string $status,string $message): array
    {
        if (!in_array($status,['completed','error'],true)) throw new \InvalidArgumentException('invalid_status');
        $stmt=$this->pdo()->prepare("UPDATE analysis_jobs SET status=?,progress=?,error=?,updated_at=? WHERE scientific_run_id=? AND kind=? AND status='running'");
        $stmt->execute([$status,$status==='completed'?100:0,mb_substr($message,0,1000),gmdate('c'),$runId,self::KIND]);
        if ($stmt->rowCount()!==1) throw new \RuntimeException('request_not_running');
        return $this->latest($runId) ?? throw new \RuntimeException('result_not_found');
    }

    /** Called only after the dedicated old worker has been stopped. */
    public function migrateLegacy(): array
    {
        $pdo=$this->pdo();
        $exists=(bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='profile_ai_requests'")->fetchColumn();
        if (!$exists) return ['legacy'=>0,'migrated'=>0];
        $rows=$pdo->query("SELECT run_id,status FROM profile_ai_requests WHERE status IN ('pending','running','error')")->fetchAll(\PDO::FETCH_ASSOC);
        $migrated=0;
        foreach ($rows as $row) {
            // Retain terminal records and do not mutate the old table. Rows are idempotently imported.
            $runId=(int)$row['run_id'];
            if ($this->get($runId)===null) { $this->enqueue($runId); ++$migrated; }
        }
        return ['legacy'=>count($rows),'migrated'=>$migrated];
    }
}
