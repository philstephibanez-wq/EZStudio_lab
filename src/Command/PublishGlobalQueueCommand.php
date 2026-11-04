<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\GlobalQueueAdmission;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** One-shot, explicit retry. No scheduler, worker, claim or background activity. */
#[AsCommand(name: 'lab:queue:publish-pending', description: 'Inspecter ou publier les nouvelles admissions globales en attente')]
final class PublishGlobalQueueCommand extends Command
{
    public function __construct(private readonly GlobalQueueAdmission $admission)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Autoriser les POST d\'admission');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pending = $this->admission->pending();
        $output->writeln('GLOBAL_ADMISSION_PENDING='.count($pending));
        if (!$input->getOption('apply')) {
            $output->writeln('READ_ONLY_NO_POST');
            return Command::SUCCESS;
        }
        $failures = 0;
        foreach ($pending as $row) {
            $id = (int) $row['job_id'];
            $ok = $this->admission->publish($id);
            $output->writeln('JOB='.$id.' ADMISSION='.($ok ? 'OK' : 'PENDING'));
            if (!$ok) ++$failures;
        }
        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
