<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;
use ZipArchive;

// Sem RefreshDatabase: o VACUUM INTO não roda dentro de transação.
class FazerBackupTest extends TestCase
{
    private string $pasta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pasta = sys_get_temp_dir().'/backup-teste-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pasta);

        parent::tearDown();
    }

    public function test_gera_zip_com_o_banco(): void
    {
        DB::statement('CREATE TABLE marcador (valor TEXT)');
        DB::table('marcador')->insert(['valor' => 'antes do backup']);

        $this->artisan('backup:fazer', ['--pasta' => $this->pasta])->assertSuccessful();

        $zips = File::glob($this->pasta.'/backup-*.zip');
        $this->assertCount(1, $zips);

        $zip = new ZipArchive;
        $zip->open($zips[0]);
        $zip->extractTo($this->pasta.'/extraido', 'database.sqlite');
        $zip->close();

        $copia = new \PDO('sqlite:'.$this->pasta.'/extraido/database.sqlite');
        $this->assertSame('antes do backup', $copia->query('SELECT valor FROM marcador')->fetchColumn());

        // A cópia temporária do banco não fica para trás.
        $this->assertSame([], File::glob($this->pasta.'/.banco-*'));
    }

    public function test_mantem_so_os_mais_recentes(): void
    {
        File::ensureDirectoryExists($this->pasta);

        foreach (['2026-01-01_000000', '2026-01-02_000000', '2026-01-03_000000'] as $data) {
            File::put($this->pasta."/backup-{$data}.zip", 'antigo');
        }

        $this->artisan('backup:fazer', ['--pasta' => $this->pasta, '--manter' => 2])->assertSuccessful();

        $restantes = array_map('basename', File::glob($this->pasta.'/backup-*.zip'));
        $this->assertCount(2, $restantes);
        $this->assertContains('backup-2026-01-03_000000.zip', $restantes);
        $this->assertNotContains('backup-2026-01-01_000000.zip', $restantes);
    }

    public function test_backup_esta_agendado_todo_dia(): void
    {
        $evento = collect(Schedule::events())->first(fn ($e) => str_contains($e->command, 'backup:fazer'));

        $this->assertNotNull($evento);
        $this->assertSame('0 20 * * *', $evento->expression);
    }
}
