<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\AnalysisRunRegistry;
use App\Service\Database;
use App\Service\GlobalQueueAdmission;
use App\Service\GlobalQueueProfileBridge;
use App\Service\GlobalQueuePersistedReceipt;
use App\Service\ProfileExecutionService;
use PHPUnit\Framework\TestCase;

final class GlobalQueueProfileBridge1_6A4Test extends TestCase
{
    public function testUnroutedScientificKindsStayWithLegacyLabWorker(): void
    {
        $before = getenv('EZSTUDIO_GLOBAL_QUEUE_ADMISSION');
        try {
            putenv('EZSTUDIO_GLOBAL_QUEUE_ADMISSION=1');
            self::assertSame('lab', GlobalQueueAdmission::owner('stems'));
            self::assertSame('lab', GlobalQueueAdmission::owner('chords_scientific'));
            $this->expectException(\InvalidArgumentException::class);
            GlobalQueueAdmission::owner('untrusted_kind');
        } finally {
            if ($before === false) putenv('EZSTUDIO_GLOBAL_QUEUE_ADMISSION');
            else putenv('EZSTUDIO_GLOBAL_QUEUE_ADMISSION='.$before);
        }
    }

    public function testEligibilityMustMatchExplicitGlobalAdmission(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite_required');
        $temp = PHP_OS_FAMILY === 'Windows' ? 'H:/temp' : sys_get_temp_dir();
        $file = $temp.'/ezs-profile-bridge-'.bin2hex(random_bytes(8)).'.sqlite';
        try {
            $db = new Database($file);
            $songId = $db->createSong('Test','Fixture','file.wav',str_repeat('a',64));
            $runs = new AnalysisRunRegistry($db);
            $run = $runs->createRun($songId, 'profile', ['name'=>'fixture'], []);
            $runId = (int) $run['id'];
            $runs->setState($runId, 'queued');
            $source = $temp.'/ezs-profile-bridge-source-'.bin2hex(random_bytes(8)).'.wav';
            file_put_contents($source, 'fixture');
            $pdo = $db->pdo();
            $now = gmdate('c');
            $request = json_encode([
                'scientific_run_id'=>$runId, 'audio_hash'=>str_repeat('a',64),
                'output_contract'=>'ezstudio.profile.v1', 'output_path'=>$temp.'/fixture-profile.json',
            ], JSON_THROW_ON_ERROR);
            $pdo->prepare(<<<'SQL'
INSERT INTO analysis_jobs(scientific_run_id,song_id,source_path,kind,status,progress,request_json,created_at,updated_at,dispatch_owner)
VALUES(?,?,?,'profile','queued',0,?,?,?,'orchestrator')
SQL)->execute([$runId,$songId,$source,$request,$now,$now]);
            $id = (int)$pdo->lastInsertId();
            $service = new GlobalQueueProfileBridge(
                $db,
                (new \ReflectionClass(ProfileExecutionService::class))->newInstanceWithoutConstructor(),
                new GlobalQueuePersistedReceipt($db),
            );
            try {
                $service->eligible($id);
                self::fail('Pending admission must be blocked');
            } catch (\DomainException $e) {
                self::assertSame('profile_global_job_not_eligible',$e->getMessage());
            }
            $pdo->prepare("UPDATE global_queue_admission_outbox SET state='admitted',global_job_id=991 WHERE job_id=?")
                ->execute([$id]);
            self::assertSame(['lab_job_id'=>$id,'global_job_id'=>991,'scientific_run_id'=>$runId],
                $service->eligible($id));
            $pdo->prepare("UPDATE global_queue_admission_outbox SET state='pending' WHERE job_id=?")->execute([$id]);
            $this->expectExceptionMessage('profile_global_job_not_eligible');
            $service->eligible($id);
        } finally {
            unset($service, $db, $pdo, $runs);
            if (isset($source) && is_file($source)) @unlink($source);
            foreach ([$file, $file.'-wal', $file.'-shm'] as $path) if (is_file($path)) @unlink($path);
        }
    }
}
