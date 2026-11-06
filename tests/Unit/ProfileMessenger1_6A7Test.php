<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Message\ProfileQueueJob;
use App\Messenger\ProfileOutboxTransport;
use App\Service\AnalysisRunRegistry;
use App\Service\Database;
use App\Service\GlobalQueueAdmission;
use PHPUnit\Framework\TestCase;

final class ProfileMessenger1_6A7Test extends TestCase
{
    public function testExistingOutboxIsTheOnlySourceAndBaselineExcludesOlderJobs(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite_required');
        $tmp = PHP_OS_FAMILY === 'Windows' ? 'H:/temp' : sys_get_temp_dir();
        $file = $tmp.'/ezs-profile-messenger-'.bin2hex(random_bytes(8)).'.sqlite';
        $old = getenv('EZSTUDIO_GLOBAL_QUEUE_EXECUTOR');
        try {
            putenv('EZSTUDIO_GLOBAL_QUEUE_EXECUTOR=1');
            $db = new Database($file);
            $songId = $db->createSong('Fixture','Fixture','fixture.wav',str_repeat('a',64));
            $runs = new AnalysisRunRegistry($db);
            $run = $runs->createRun($songId,'profile',['name'=>'fixture'],[]);
            $runId = (int)$run['id'];
            $runs->setState($runId,'queued');
            $now = gmdate('c');
            $pdo = $db->pdo();
            $stmt = $pdo->prepare(<<<'SQL'
INSERT INTO analysis_jobs(scientific_run_id,song_id,source_path,kind,status,progress,request_json,created_at,updated_at,dispatch_owner)
VALUES(?,?,?,'profile','queued',0,'{}',?,?,'orchestrator')
SQL);
            $stmt->execute([$runId,$songId,'fixture.wav',$now,$now]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE global_queue_admission_outbox SET state='admitted',global_job_id=999 WHERE job_id=?")
                ->execute([$id]);
            $admission = new GlobalQueueAdmission($db);
            self::assertSame([], iterator_to_array((function () use ($db,$admission,$id) {
                yield from (new ProfileOutboxTransport($db,$admission,$id))->get();
            })()));
            $transport = new ProfileOutboxTransport($db,$admission,$id-1);
            $messages = $transport->get();
            self::assertCount(1, $messages);
            self::assertInstanceOf(ProfileQueueJob::class, $messages[0]->getMessage());
            self::assertSame($id, $messages[0]->getMessage()->labJobId);
            $transport->ack($messages[0]);
            self::assertSame([], $transport->get(), 'No immediate duplicate on acknowledgement');
            $runs->setState($runId,'done');
            self::assertSame([], (new ProfileOutboxTransport($db,$admission,$id-1))->get(), 'Completed scientific run is excluded');
            $this->expectException(\LogicException::class);
            $transport->send($messages[0]);
        } finally {
            $old === false ? putenv('EZSTUDIO_GLOBAL_QUEUE_EXECUTOR') : putenv('EZSTUDIO_GLOBAL_QUEUE_EXECUTOR='.$old);
            unset($transport, $admission, $pdo, $runs, $db);
            foreach ([$file, $file.'-wal', $file.'-shm'] as $path) if (is_file($path)) @unlink($path);
        }
    }
}
