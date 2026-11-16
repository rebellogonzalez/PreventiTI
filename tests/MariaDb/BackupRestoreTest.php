<?php

namespace Tests\MariaDb;

use App\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreTest extends TestCase
{
    public function test_a_real_mariadb_backup_restores_data_triggers_and_procedures(): void
    {
        $this->assertSame('mariadb', DB::getDriverName());
        $this->assertSame('preventiti_tests', DB::connection()->getDatabaseName());
        $this->assertSame(0, DB::transactionLevel());
        $folder = sys_get_temp_dir().'/preventiti-mariadb-restore-'.bin2hex(random_bytes(6));
        mkdir($folder.'/files', 0700, true);
        file_put_contents($folder.'/files/evidence.txt', 'Archivo de prueba del respaldo.');
        config(['infrastructure.backup_directory' => $folder.'/backups', 'infrastructure.backup_files_directory' => $folder.'/files']);
        $id = DB::table('auditorias')->insertGetId(['fecha_auditoria' => now(), 'descripcion' => 'Evidencia de restauración MariaDB.']);
        try {
            $backup = app(BackupService::class)->create();
            $this->assertTrue($backup['verified']);
            $this->assertSame('mariadb', $backup['database']);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open(app(BackupService::class)->downloadPath($backup['filename'])));
            $sql = $zip->getFromName('database/database.sql');
            $this->assertIsString($sql);
            $this->assertSame('Archivo de prueba del respaldo.', $zip->getFromName('storage/app/evidence.txt'));
            $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('mariadb', $manifest['database']);
            $zip->close();
            $process = new Process([getenv('MARIADB_CLIENT_BINARY'), '--defaults-file='.getenv('MARIADB_TEST_DEFAULTS_FILE'), '--binary-mode', 'preventiti_restore_tests']);
            $process->setInput($sql)->setTimeout(120)->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            config(['database.connections.restore_probe' => array_merge(config('database.connections.mariadb'), ['database' => 'preventiti_restore_tests'])]);
            $restored = DB::connection('restore_probe');
            $this->assertSame('Evidencia de restauración MariaDB.', $restored->table('auditorias')->where('id', $id)->value('descripcion'));
            $this->assertSame(10, (int) $restored->selectOne('SELECT COUNT(*) AS total FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->total);
            $this->assertSame(3, (int) $restored->selectOne('SELECT COUNT(*) AS total FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()')->total);
        } finally {
            DB::purge('restore_probe');
            DB::table('auditorias')->where('id', $id)->delete();
            File::deleteDirectory($folder);
        }
    }
}
