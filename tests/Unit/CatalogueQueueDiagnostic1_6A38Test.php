<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CatalogueQueueDiagnostic1_6A38Test extends TestCase
{
    private function source(string $path): string
    {
        $content = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($content, $path);
        return $content;
    }

    public function testQueueIsOnlyInReadOnlyDiagnosticAndSongRows(): void
    {
        $catalogue = $this->source('templates/workbench/home.html.twig');
        $diagnostic = $this->source('src/Controller/LocalQueueDiagnosticController.php');
        self::assertStringNotContainsString('id="catalogue-file-commune"', $catalogue);
        self::assertStringContainsString('expertise_by_song[song.id]', $catalogue);
        self::assertStringContainsString("job.status in ['queued', 'running']", $catalogue);
        self::assertStringContainsString("ORDER BY created_at ASC, id ASC", $diagnostic);
        self::assertStringContainsString("status IN ('queued', 'running', 'cancelling')", $diagnostic);
        self::assertStringContainsString("methods: ['GET']", $diagnostic);
        self::assertStringNotContainsString('->wake(', $diagnostic);
    }

    public function testLiveRefreshAndProfileTerminalStateAreVisible(): void
    {
        $script = $this->source('public/js/ezstudio-lab.js');
        $panel = $this->source('templates/workbench/profile_ai_button.html.twig');
        self::assertStringContainsString('.always(()=>{window.ezsCatalogRefreshing=false;scheduleCatalog();})', $script);
        self::assertStringContainsString("s==='cancelled' ? 'Révision IA annulée'", $panel);
        self::assertStringContainsString("s==='cancelling' ? 'Annulation de la révision IA demandée'", $panel);
        self::assertStringContainsString('item?.job_id', $panel);
        self::assertStringNotContainsString('window.confirm(', $script);
        self::assertStringNotContainsString('window.alert(', $script);
        self::assertStringNotContainsString('window.prompt(', $script);
    }
}
