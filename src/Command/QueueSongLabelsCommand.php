<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Database;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Read-only song labels owned by LAB, exported through a bounded CLI protocol. */
#[AsCommand(name: 'lab:queue:song-labels')]
final class QueueSongLabelsCommand extends Command
{
    public function __construct(private readonly Database $database)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $pdo = $this->database->pdo();
            $rows = $pdo->query(<<<'SQL'
SELECT j.id AS job_id, s.title, s.artist
FROM analysis_jobs j
JOIN songs s ON s.id=j.song_id
WHERE j.dispatch_owner='orchestrator'
ORDER BY j.id DESC LIMIT 100
SQL)->fetchAll(\PDO::FETCH_ASSOC);
            $labels = [];
            foreach ($rows as $row) {
                $id = (int) $row['job_id'];
                if ($id < 1) continue;
                $title = trim((string) $row['title']);
                $artist = trim((string) ($row['artist'] ?? ''));
                $labels[(string) $id] = mb_substr($title, 0, 160);
                if ($artist !== '') $labels[(string) $id] .= ' — '.mb_substr($artist, 0, 120);
            }
            $output->writeln(json_encode([
                'schema' => 'ezstudio.lab.queue-labels.v1',
                'labels' => (object) $labels,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        } catch (\Throwable) {
            $output->writeln('<error>QUEUE_LABELS_READ_FAILED</error>');
            return Command::FAILURE;
        }
    }
}
