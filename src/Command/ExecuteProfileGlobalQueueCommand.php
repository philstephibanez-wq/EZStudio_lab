<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\GlobalQueueProfileBridge;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Exact globally admitted PROFILE job; job ownership stays with LAB. */
#[AsCommand(name: 'lab:queue:execute-profile')]
final class ExecuteProfileGlobalQueueCommand extends Command
{
    public function __construct(private readonly GlobalQueueProfileBridge $bridge) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('lab-job-id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (string)$input->getArgument('lab-job-id');
        if (!preg_match('/^[1-9][0-9]*$/D', $id)) return Command::INVALID;
        // LAB chooses its own interpreter; Orchestrator passes only the job reference.
        $python = getenv('EZSTUDIO_PROFILE_PYTHON')
            ?: ($_SERVER['EZSTUDIO_PROFILE_PYTHON'] ?? $_ENV['EZSTUDIO_PROFILE_PYTHON'] ?? '')
            ?: getenv('EZSTUDIO_PYTHON')
            ?: ($_SERVER['EZSTUDIO_PYTHON'] ?? $_ENV['EZSTUDIO_PYTHON'] ?? '')
            ?: 'H:/Python/pythoncore-3.14-64/python.exe';
        if (!is_string($python) || !is_file($python)) {
            $output->writeln('<error>PROFILE_PYTHON_UNAVAILABLE</error>');
            return Command::FAILURE;
        }
        try {
            $result = $this->bridge->runOne((int)$id, $python);
            $state = (string)($result['state'] ?? '');
            $output->writeln('LAB_GLOBAL_PROFILE_STATE='.$state);
            return in_array($state, ['completed', 'failed'], true) ? Command::SUCCESS : Command::FAILURE;
        } catch (\Throwable $e) {
            $output->writeln('<error>LAB_GLOBAL_PROFILE_EXECUTION_FAILED: '.get_class($e).'</error>');
            return Command::FAILURE;
        }
    }
}
