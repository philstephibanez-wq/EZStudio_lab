<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Database;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Migrate only demonstrably unstarted LAB PROFILE requests to the authoritative global outbox. */
#[AsCommand(name: 'lab:queue:admit-existing-profile')]
final class AdmitExistingProfileJobsCommand extends Command
{
    public function __construct(private readonly Database $database) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Autoriser la conversion transactionnelle');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $db = $this->database->pdo();
        $rows = $db->query(<<<'SQL'
SELECT j.id,j.kind,j.status,j.dispatch_owner,j.source_path,j.request_json,
       j.scientific_run_id,j.song_id,r.state AS run_state,r.item AS run_item,
       r.song_id AS run_song_id,s.audio_sha256 AS audio_hash
FROM analysis_jobs j
JOIN scientific_runs r ON r.id=j.scientific_run_id
JOIN songs s ON s.id=j.song_id
WHERE j.status='queued' AND j.kind='profile' AND j.dispatch_owner='lab'
ORDER BY j.created_at ASC,j.id ASC
SQL)->fetchAll(\PDO::FETCH_ASSOC);
        $eligible = [];
        foreach ($rows as $row) {
            $request = json_decode((string) $row['request_json'], true);
            if ($row['run_state'] !== 'queued' || $row['run_item'] !== 'profile'
                || (int)$row['song_id'] !== (int)$row['run_song_id']
                || !is_file((string)$row['source_path'])
                || !is_array($request)
                || ($request['output_contract'] ?? null) !== 'ezstudio.profile.v1'
                || ($request['audio_hash'] ?? null) !== $row['audio_hash']
                || (int)($request['scientific_run_id'] ?? 0) !== (int)$row['scientific_run_id']
                || !is_string($request['output_path'] ?? null)
                || $request['output_path'] === '') {
                $output->writeln('<error>UNSAFE_LEGACY_PROFILE_JOB='.(int)$row['id'].'</error>');
                return Command::FAILURE;
            }
            $eligible[] = (int)$row['id'];
        }
        $output->writeln('LEGACY_PROFILE_ELIGIBLE='.count($eligible));
        if (!$input->getOption('apply')) {
            $output->writeln('READ_ONLY');
            return Command::SUCCESS;
        }
        if ($eligible === []) return Command::SUCCESS;
        try {
            $db->exec('BEGIN IMMEDIATE');
            $update = $db->prepare("UPDATE analysis_jobs SET dispatch_owner='orchestrator' WHERE id=? AND dispatch_owner='lab' AND status='queued' AND kind='profile'");
            $outbox = $db->prepare("INSERT INTO global_queue_admission_outbox(job_id,state,updated_at) VALUES (?,'pending',?)");
            foreach ($eligible as $id) {
                $update->execute([$id]);
                if ($update->rowCount() !== 1) throw new \RuntimeException('legacy_job_concurrently_claimed_'.$id);
                $outbox->execute([$id, gmdate('c')]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $output->writeln('<error>LEGACY_ADMISSION_REFUSED '.get_class($e).'</error>');
            return Command::FAILURE;
        }
        $output->writeln('LEGACY_PROFILE_MOVED_TO_OUTBOX='.count($eligible));
        return Command::SUCCESS;
    }
}
