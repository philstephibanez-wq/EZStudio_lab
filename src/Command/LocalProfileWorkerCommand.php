<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Database;
use App\Service\ProfileExecutionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/** Explicit local-only PROFILE worker. Never claims Orchestrator-owned jobs. */
#[AsCommand(name: 'lab:profile:execute-local', description: 'Exécuter un job PROFILE LAB local existant, après vérification de son propriétaire')]
final class LocalProfileWorkerCommand extends Command
{
    public function __construct(
        private readonly Database $database,
        private readonly ProfileExecutionService $profile,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('job-id', InputArgument::REQUIRED, 'ID du job LAB déjà existant');
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Autoriser une seule exécution scientifique');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $idString = (string) $input->getArgument('job-id');
        if (preg_match('/^[1-9][0-9]*$/D', $idString) !== 1) {
            $output->writeln('<error>LOCAL_PROFILE_INVALID_ID</error>');
            return Command::INVALID;
        }
        $id = (int) $idString;
        $job = $this->database->analysisJob($id);
        if (!is_array($job) || ($job['kind'] ?? null) !== 'profile'
            || ($job['dispatch_owner'] ?? null) !== 'lab'
            || ($job['status'] ?? null) !== 'queued') {
            $output->writeln('<error>LOCAL_PROFILE_OWNER_OR_STATE_REFUSED</error>');
            return Command::FAILURE;
        }
        // No retroactive ownership changes: global jobs #204/#205 are fenced.
        if (!$input->getOption('apply')) {
            $output->writeln('LOCAL_PROFILE_ELIGIBLE_READ_ONLY job='.$id);
            return Command::SUCCESS;
        }
        if (self::setting('EZSTUDIO_GLOBAL_QUEUE_ADMISSION') !== '0'
            || self::setting('EZSTUDIO_GLOBAL_QUEUE_EXECUTOR') !== '0') {
            $output->writeln('<error>LOCAL_PROFILE_GLOBAL_ROUTE_NOT_DISABLED</error>');
            return Command::FAILURE;
        }
        // A live global claim may still have a child running: never overlap it.
        $globalPath = 'H:/EZS_orchestrator/data/queue/global.sqlite';
        if (!is_file($globalPath)) {
            $output->writeln('<error>LOCAL_PROFILE_GLOBAL_QUEUE_NOT_OBSERVABLE</error>');
            return Command::FAILURE;
        }
        try {
            $global = new \PDO('sqlite:file:H:/EZS_orchestrator/data/queue/global.sqlite?mode=ro');
            $global->exec('PRAGMA query_only=ON');
            $running = $global->query("SELECT 1 FROM global_queue_jobs WHERE state='running' LIMIT 1")->fetchColumn();
            $global = null;
        } catch (\Throwable) {
            $output->writeln('<error>LOCAL_PROFILE_GLOBAL_QUEUE_UNREADABLE</error>');
            return Command::FAILURE;
        }
        if ($running !== false) {
            $output->writeln('<error>LOCAL_PROFILE_GLOBAL_LEASE_STILL_RUNNING</error>');
            return Command::FAILURE;
        }
        $python = self::setting('EZSTUDIO_PROFILE_PYTHON');
        if ($python === '' || !is_file($python)) {
            $output->writeln('<error>LOCAL_PROFILE_PYTHON_UNAVAILABLE</error>');
            return Command::FAILURE;
        }
        $project = dirname(__DIR__, 2);
        $worker = $project.'/analysis/worker_entrypoint.py';
        if (!is_file($worker) || !is_file((string)$job['source_path'])) {
            $output->writeln('<error>LOCAL_PROFILE_WORKER_OR_AUDIO_UNAVAILABLE</error>');
            return Command::FAILURE;
        }
        $context = $this->database->analysisJobContext($id);
        if (($context['protocol'] ?? null) !== 'ezscore.analysis-job.v2'
            || ($context['kind'] ?? null) !== 'profile'
            || (int)($context['job_id'] ?? 0) !== $id
            || ($context['status'] ?? null) !== 'queued') {
            $output->writeln('<error>LOCAL_PROFILE_CONTEXT_REFUSED</error>');
            return Command::FAILURE;
        }
        $request = $context['request'] ?? [];
        if (($request['output_contract'] ?? null) !== 'ezstudio.profile.v1') {
            $output->writeln('<error>LOCAL_PROFILE_CONTRACT_REFUSED</error>');
            return Command::FAILURE;
        }
        $tmp = 'H:/temp/EZSTUDIO_LAB_AUTONOME';
        if (!is_dir($tmp) && !mkdir($tmp, 0700, true) && !is_dir($tmp)) {
            $output->writeln('<error>LOCAL_PROFILE_TEMP_UNAVAILABLE</error>');
            return Command::FAILURE;
        }
        $lock = fopen($tmp.'/worker.lock', 'c+b');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            $output->writeln('<error>LOCAL_PROFILE_WORKER_ALREADY_RUNNING</error>');
            return Command::FAILURE;
        }
        $claimed = false;
        try {
            // Protect all LAB phases from competing local execution.
            $pdo = $this->database->pdo();
            $pdo->exec('BEGIN IMMEDIATE');
            try {
                $busy = $pdo->query("SELECT 1 FROM analysis_jobs WHERE status='running' LIMIT 1");
                if ($busy->fetchColumn() !== false) {
                    $pdo->rollBack();
                    $output->writeln('<error>LOCAL_PROFILE_ANOTHER_LAB_JOB_RUNNING</error>');
                    return Command::FAILURE;
                }
                $statement = $pdo->prepare("UPDATE analysis_jobs SET status='running',progress=1,updated_at=?,error=NULL WHERE id=? AND kind='profile' AND dispatch_owner='lab' AND status='queued'");
                $statement->execute([gmdate('c'), $id]);
                if ($statement->rowCount() !== 1) {
                    $pdo->rollBack();
                    $output->writeln('<error>LOCAL_PROFILE_CONCURRENT_CLAIM_REFUSED</error>');
                    return Command::FAILURE;
                }
                $pdo->commit();
                $claimed = true;
            } catch (\Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            $jobFile = $tmp.'/job-'.$id.'.json';
            $context['status'] = 'running';
            if (file_put_contents($jobFile, json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) === false) {
                throw new \RuntimeException('local_profile_job_file_failed');
            }
            $env = getenv();
            if (!is_array($env)) $env = [];
            foreach ($_SERVER as $key => $value) {
                if (is_string($key) && is_string($value) && str_starts_with($key, 'EZSTUDIO_')) $env[$key] = $value;
            }
            $env['EZS_TARGET'] = 'lab';
            $env['EZS_EXPECTED_ROOT'] = realpath($project) ?: $project;
            $env['PYTHONUTF8'] = '1';
            $env['PYTHONIOENCODING'] = 'utf-8';
            unset($env['EZS_QUEUE_EXECUTOR_TOKEN'], $env['EZS_QUEUE_LAB_TOKEN'], $env['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY']);
            $process = new Process([$python, $worker, '--job-file', $jobFile], $project, $env);
            $process->setTimeout(null);
            $output->writeln('LOCAL_PROFILE_SCIENCE_START job='.$id);
            $process->run(function ($type, $buffer) use ($output): void {
                // No unfiltered worker logs (paths/secrets) in console.
            });
            if (!$process->isSuccessful()) {
                $this->profile->failFromJob($id, 'local_profile_worker_exit_'.$process->getExitCode());
                $this->database->failAnalysisJob($id, 'local_profile_worker_exit_'.$process->getExitCode());
                $output->writeln('<error>LOCAL_PROFILE_SCIENCE_FAILED</error>');
                return Command::FAILURE;
            }
            $this->profile->completeFromJob($id);
            $this->database->completeAnalysisJob($id);
            $output->writeln('LOCAL_PROFILE_COMPLETED job='.$id);
            return Command::SUCCESS;
        } catch (\Throwable $error) {
            if ($claimed) {
                try {
                    $this->profile->failFromJob($id, 'local_profile_exception');
                    $this->database->failAnalysisJob($id, 'local_profile_exception');
                } catch (\Throwable) {
                    // Retain original incident and fail closed; never retry.
                }
            }
            $output->writeln('<error>LOCAL_PROFILE_FAILED '.get_class($error).'</error>');
            return Command::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function setting(string $key): string
    {
        foreach ([getenv($key), $_SERVER[$key] ?? null, $_ENV[$key] ?? null] as $value) {
            if (is_string($value) && $value !== '') return $value;
        }
        $path = dirname(__DIR__, 2).'/.env.local';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/D', trim((string)$line), $m) === 1) {
                    return trim($m[1], " \t\"'");
                }
            }
        }
        return '';
    }
}
