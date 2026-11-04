<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use SQLite3;
use Tests\TestCase;
use ZipArchive;

class BackupServiceTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir().'/preventiti-backup-test-'.bin2hex(random_bytes(6));
        mkdir($this->folder.'/storage/nested', 0700, true);
        file_put_contents($this->folder.'/storage/nested/evidencia.txt', 'Información que debe preservarse.');
        $db = new SQLite3($this->folder.'/source.sqlite');
        $db->exec('CREATE TABLE evidence (id INTEGER PRIMARY KEY, content TEXT)');
        $db->exec("INSERT INTO evidence VALUES (1, 'registro original')");
        $db->close();
        config(['database.connections.backup_fixture' => ['driver' => 'sqlite', 'database' => $this->folder.'/source.sqlite', 'prefix' => '', 'foreign_key_constraints' => true],
            'infrastructure.backup_directory' => $this->folder.'/backups', 'infrastructure.backup_files_directory' => $this->folder.'/storage']);
        DB::setDefaultConnection('backup_fixture');
    }

    protected function tearDown(): void
    {
        DB::purge('backup_fixture');
        File::deleteDirectory($this->folder); // Solo la base y archivos ficticios creados por esta prueba.
        parent::tearDown();
    }

    public function test_backup_can_be_restored_and_preserves_the_source_and_older_backups(): void
    {
        $service = app(BackupService::class);
        $original = hash_file('sha256', $this->folder.'/source.sqlite');
        $first = $service->create();
        $path = $service->downloadPath($first['filename']);
        $this->assertTrue($first['verified']);
        $this->assertSame(hash_file('sha256', $path), $first['sha256']);
        $this->assertSame(filesize($path), $first['bytes']);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CHECKCONS));
        $this->assertSame('Información que debe preservarse.', $zip->getFromName('storage/app/nested/evidencia.txt'));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertCount(2, $manifest['files']);
        $zip->extractTo($this->folder.'/restore');
        $zip->close();
        $restored = new SQLite3($this->folder.'/restore/database/database.sqlite', SQLITE3_OPEN_READONLY);
        $this->assertSame('ok', $restored->querySingle('PRAGMA integrity_check'));
        $this->assertSame('registro original', $restored->querySingle('SELECT content FROM evidence WHERE id=1'));
        $restored->close();
        $second = $service->create();
        $this->assertNotSame($first['filename'], $second['filename']);
        $this->assertFileExists($service->downloadPath($first['filename']));
        $this->assertFileExists($service->downloadPath($second['filename']));
        $this->assertSame($original, hash_file('sha256', $this->folder.'/source.sqlite'));
    }

    public function test_snapshot_includes_committed_wal_data(): void
    {
        $writer = new SQLite3($this->folder.'/source.sqlite');
        $writer->exec('PRAGMA journal_mode=WAL');
        $writer->exec("INSERT INTO evidence VALUES (2, 'dato en WAL')");
        $backup = app(BackupService::class)->create();
        $zip = new ZipArchive();
        $zip->open(app(BackupService::class)->downloadPath($backup['filename']));
        $zip->extractTo($this->folder.'/wal-restore');
        $zip->close();
        $restored = new SQLite3($this->folder.'/wal-restore/database/database.sqlite', SQLITE3_OPEN_READONLY);
        $this->assertSame('dato en WAL', $restored->querySingle('SELECT content FROM evidence WHERE id=2'));
        $restored->close();
        $writer->close();
    }

    public function test_tampered_archive_is_never_offered_as_verified(): void
    {
        $service = app(BackupService::class);
        $backup = $service->create();
        file_put_contents($this->folder.'/backups/'.$backup['filename'], 'corrupted', FILE_APPEND);
        $this->assertFalse($service->latest()['verified']);
        $this->expectException(RuntimeException::class);
        $service->downloadPath($backup['filename']);
    }

    public function test_paths_cannot_escape_the_backup_directory(): void
    {
        $this->expectException(RuntimeException::class);
        app(BackupService::class)->downloadPath('../source.sqlite');
    }

    public function test_symlinks_do_not_silently_exclude_or_expose_files(): void
    {
        symlink($this->folder.'/source.sqlite', $this->folder.'/storage/link');
        try {
            app(BackupService::class)->create();
            $this->fail('El respaldo no debe seguir un enlace simbólico.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('enlace simbólico', $error->getMessage());
        }
        $this->assertFileExists($this->folder.'/source.sqlite');
        $this->assertSame([], glob($this->folder.'/backups/*.zip'));
    }
}
