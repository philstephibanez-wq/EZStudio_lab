<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AnalysisRunRegistry;
use App\Service\GlobalQueueProfileBridge;
use App\Service\ProfileExecutionService;
use App\Service\WorkbenchCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Explicit first real PROFILE run. Uses the same services as ProfileController::analyze. */
#[AsCommand(name: 'lab:profile:analyze-once', description: 'Creer puis executer un seul nouveau PROFILE par la queue Orchestrator')]
final class AnalyzeProfileOnceCommand extends Command
{
    public function __construct(
        private readonly WorkbenchCatalog $catalog,
        private readonly AnalysisRunRegistry $runs,
        private readonly ProfileExecutionService $execution,
        private readonly GlobalQueueProfileBridge $bridge,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('song-id', null, InputOption::VALUE_REQUIRED, 'ID de chanson LAB existante');
        $this->addOption('python', null, InputOption::VALUE_REQUIRED, 'Python canonique PROFILE existant');
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Autoriser la creation, le claim et le worker');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $songId = filter_var($input->getOption('song-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($songId === false || $songId === null) {
            $output->writeln('<error>SONG_ID_REQUIRED</error>');
            return Command::INVALID;
        }
        $song = $this->catalog->selectedSong((int) $songId);
        if (!$song) {
            $output->writeln('<error>PROFILE_SONG_NOT_FOUND</error>');
            return Command::FAILURE;
        }
        $source = $this->catalog->sourcePath($song);
        if ($source === null || !is_file($source)) {
            $output->writeln('<error>PROFILE_SOURCE_UNAVAILABLE</error>');
            return Command::FAILURE;
        }
        if (!$input->getOption('apply')) {
            $output->writeln('PROFILE_SONG_ELIGIBLE_READ_ONLY_NO_JOB_CREATED');
            return Command::SUCCESS;
        }
        // Validate everything possible before the first persistent write.
        if (getenv('EZSTUDIO_GLOBAL_QUEUE_ADMISSION') !== '1'
            || getenv('EZSTUDIO_GLOBAL_QUEUE_EXECUTOR') !== '1') {
            $output->writeln('<error>GLOBAL_PROFILE_NATIVE_ENV_NOT_ENABLED</error>');
            return Command::FAILURE;
        }
        $python = trim((string) $input->getOption('python'));
        if ($python === '' || !is_file($python)) {
            $output->writeln('<error>PROFILE_PYTHON_MISSING</error>');
            return Command::INVALID;
        }
        $run = $this->runs->createRun(
            (int) $song['id'],
            'profile',
            ['name' => 'ezstudio-profile-modules', 'version' => 'r3b31', 'model' => 'multi-engine-profile-modules'],
            [
                'audio_sha256' => (string) $song['audio_sha256'],
                'output_contract' => 'ezstudio.profile.v1',
                'automatic_next_stage' => false,
            ],
        );
        // Exactly the existing UI's scientific job creation, presets and admission.
        $job = $this->execution->queue((int) $run['id'], $source, (string) $song['audio_sha256']);
        $jobId = (int) $job['job_id'];
        $output->writeln('PROFILE_NEW_SCIENTIFIC_RUN='.(int) $run['id']);
        $output->writeln('PROFILE_NEW_LAB_JOB='.$jobId);
        // This is deliberately the only invocation of the scientific worker.
        // Any failure retains evidence and is NOT automatically retried.
        $result = $this->bridge->runOne($jobId, $python);
        $output->writeln('PROFILE_SINGLE_RUN_RESULT='.json_encode($result, JSON_THROW_ON_ERROR));
        $output->writeln('EZS_PROFILE_ONESHOT_EXECUTION_COMPLETED');
        return Command::SUCCESS;
    }
}
