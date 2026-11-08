<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AnalysisRunRegistry;
use App\Service\Database;
use App\Service\ProfileExecutionService;
use App\Service\ProfileOnceEnvironment;
use App\Service\WorkbenchCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/** One analysis, one process, no Messenger, no global admissions, no local scheduler. */
#[AsCommand(name: 'lab:profile:once', description: 'Analyse PROFILE directe, sans FIFO')]
final class ProfileOnceCommand extends Command
{
    public function __construct(
        private readonly Database $database,
        private readonly WorkbenchCatalog $catalog,
        private readonly AnalysisRunRegistry $runs,
        private readonly ProfileExecutionService $profile,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('song-id', null, InputOption::VALUE_REQUIRED, 'ID de chanson LAB existante');
        $this->addOption('python', null, InputOption::VALUE_REQUIRED, 'Python PROFILE (chemin absolu)');
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Créer UNE nouvelle analyse et exécuter directement Python');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = filter_var($input->getOption('song-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id === null) {
            $output->writeln('<error>PROFILE_ONCE_SONG_ID_REQUIRED</error>');
            return Command::INVALID;
        }
        $song = $this->catalog->selectedSong((int)$id);
        if (!$song || !in_array((int)$id, array_map('intval', $song['song_ids'] ?? [(int)$song['id']]), true)) {
            $output->writeln('<error>PROFILE_ONCE_SONG_MISSING</error>');
            return Command::FAILURE;
        }
        $root = dirname(__DIR__, 2);
        $source = $this->catalog->sourcePath($song);
        $python = trim((string)$input->getOption('python'));
        if ($python === '') {
            $python = (string)(getenv('EZSTUDIO_PROFILE_PYTHON') ?: ($_SERVER['EZSTUDIO_PROFILE_PYTHON'] ?? '') ?: 'H:/Python/pythoncore-3.14-64/python.exe');
        }
        $python = realpath($python) ?: $python;
        if (!is_string($source) || !is_file($source) || !is_file($python)
            || !is_file($root.'/analysis/worker_entrypoint.py')
            || !is_file($root.'/python/ezstudio/pipeline/profile/runner.py')) {
            $output->writeln('<error>PROFILE_ONCE_SOURCE_OR_ENGINE_MISSING</error>');
            return Command::FAILURE;
        }
        $output->writeln('PROFILE_ONCE_PREFLIGHT_OK song='.(int)$song['id'].' title='.(string)$song['title']);
        if (!$input->getOption('apply')) {
            $output->writeln('READ_ONLY_NO_JOB_CREATED');
            return Command::SUCCESS;
        }

        // One-shot mutual exclusion is a lock, not a job queue. Never adopt an older job.
        $lockDir = $root.'/var/runtime';
        if (!is_dir($lockDir) && !mkdir($lockDir, 0700, true) && !is_dir($lockDir)) {
            throw new \RuntimeException('profile_once_lock_dir_missing');
        }
        $handle = fopen($lockDir.'/profile-once.lock', 'c+b');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) fclose($handle);
            $output->writeln('<error>PROFILE_ONCE_ALREADY_RUNNING</error>');
            return Command::FAILURE;
        }
        $jobId = null;
        try {
            // Refuse coexistence with any LAB analysis already recorded running.
            $active = $this->database->pdo()->query("SELECT COUNT(*) FROM analysis_jobs WHERE status='running'")->fetchColumn();
            if ((int)$active > 0) {
                $output->writeln('<error>PROFILE_ONCE_LAB_ALREADY_RUNNING</error>');
                return Command::FAILURE;
            }
            $run = $this->runs->createRun(
                (int)$song['id'], 'profile',
                ['name'=>'ezstudio-profile-modules','version'=>'r3b31','model'=>'multi-engine-profile-modules'],
                ['audio_sha256'=>(string)$song['audio_sha256'], 'output_contract'=>'ezstudio.profile.v1', 'automatic_next_stage'=>false]
            );
            $created = $this->profile->beginOneShot((int)$run['id'], $source, (string)$song['audio_sha256']);
            $jobId = (int)$created['job_id'];
            $context = $this->database->analysisJobContext($jobId);
            $context['target'] = 'lab';
            $runtime = $root.'/var/tmp/profile-once/'.$jobId;
            if (!is_dir($runtime) && !mkdir($runtime, 0700, true) && !is_dir($runtime)) {
                throw new \RuntimeException('profile_once_runtime_unavailable');
            }
            $jobFile = $runtime.'/job.json';
            file_put_contents($jobFile, json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $cancelFile = $runtime.'/cancel.request';
            if (is_file($cancelFile)) {
                throw new \RuntimeException('profile_once_stale_cancel_marker');
            }
            $environment = ProfileOnceEnvironment::build($root);
            $environment['EZSTUDIO_PROFILE_ONESHOT_CANCEL_FILE'] = $cancelFile;
            $process = new Process([$python, $root.'/analysis/worker_entrypoint.py', '--job-file', $jobFile], $root, $environment);
            $process->setTimeout(null);
            $output->writeln('PROFILE_ONCE_PYTHON_START job='.$jobId.' run='.(int)$run['id']);
            $process->start();
            $last = -1;
            while ($process->isRunning()) {
                $current = $this->database->analysisJob($jobId);
                if (($current['status'] ?? '') === 'cancelling' && !is_file($cancelFile)) {
                    if (file_put_contents($cancelFile, "cancel\n", LOCK_EX) === false) {
                        throw new \RuntimeException('profile_once_cancel_marker_failed');
                    }
                    $output->writeln('PROFILE_ONCE_CANCEL_SIGNAL job='.$jobId);
                }
                $out = $process->getIncrementalOutput().$process->getIncrementalErrorOutput();
                if ($out !== '') $output->write($out);
                $progressFile = $context['paths']['progress_file'] ?? '';
                if (is_string($progressFile) && is_file($progressFile)) {
                    $state = json_decode((string)file_get_contents($progressFile), true);
                    $percent = is_array($state) ? ($state['percent'] ?? null) : null;
                    if (is_int($percent) && $percent > $last && $percent <= 99) {
                        $last = $percent;
                        $this->database->updateAnalysisJobProgress($jobId, $percent);
                        $this->profile->progressFromJob($jobId, $percent);
                        $output->writeln('PROFILE_ONCE_PROGRESS='.$percent);
                    }
                }
                usleep(400000);
            }
            $tail = $process->getIncrementalOutput().$process->getIncrementalErrorOutput();
            if ($tail !== '') $output->write($tail);
            // Terminal fence: a cancellation that arrived at the final step
            // must not register a successful profile.json.
            $finalJob = $this->database->analysisJob($jobId);
            if (($finalJob['status'] ?? '') === 'cancelling') {
                if ($process->isRunning()) {
                    throw new \RuntimeException('profile_cancel_child_still_running');
                }
                $this->database->markAnalysisJobCancelled($jobId);
                $output->writeln('PROFILE_ONCE_CANCELLED job='.$jobId.' run='.(int)$run['id']);
                return Command::SUCCESS;
            }
            if (!$process->isSuccessful()) {
                throw new \RuntimeException('profile_worker_exit_'.$process->getExitCode());
            }
            // Exact historic output registration and preset capture service.
            $this->profile->completeFromJob($jobId);
            $this->database->completeAnalysisJob($jobId);
            $output->writeln('PROFILE_ONCE_COMPLETED job='.$jobId.' run='.(int)$run['id']);
            return Command::SUCCESS;
        } catch (\Throwable $error) {
            if ($jobId !== null) {
                try {
                    $this->profile->failFromJob($jobId, $error->getMessage());
                    $this->database->failAnalysisJob($jobId, $error->getMessage());
                } catch (\Throwable) {
                    // Preserve the original exception; operator inspects the stored run.
                }
            }
            $output->writeln('<error>PROFILE_ONCE_FAILED '.get_class($error).' '.$error->getMessage().'</error>');
            return Command::FAILURE;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
