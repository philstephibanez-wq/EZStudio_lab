<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\GlobalQueueProfileBridge;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Manual one-shot only. No background service, task or automatic activation. */
#[AsCommand(name: 'lab:queue:run-profile', description: 'Executer un nouveau job PROFILE admis par Orchestrator')]
final class RunGlobalProfileCommand extends Command
{
    public function __construct(private readonly GlobalQueueProfileBridge $bridge)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('lab-job-id', null, InputOption::VALUE_REQUIRED, 'Identifiant LAB explicitement choisi');
        $this->addOption('python', null, InputOption::VALUE_REQUIRED, 'Executable Python du moteur LAB existant');
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Autoriser le claim et le lancement du moteur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = filter_var($input->getOption('lab-job-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $output->writeln('<error>LAB_JOB_ID_REQUIRED</error>');
            return Command::INVALID;
        }
        $job = $this->bridge->eligible((int) $id);
        $output->writeln('LAB_JOB='.$id.' GLOBAL_JOB='.$job['global_job_id'].' ELIGIBLE');
        if (!$input->getOption('apply')) {
            $output->writeln('READ_ONLY_NO_CLAIM_NO_WORKER');
            return Command::SUCCESS;
        }
        $python = trim((string) $input->getOption('python'));
        if ($python === '') {
            $output->writeln('<error>PYTHON_REQUIRED</error>');
            return Command::INVALID;
        }
        $result = $this->bridge->runOne((int) $id, $python);
        $output->writeln('PROFILE_GLOBAL_RESULT='.json_encode($result, JSON_THROW_ON_ERROR));
        return Command::SUCCESS;
    }
}
