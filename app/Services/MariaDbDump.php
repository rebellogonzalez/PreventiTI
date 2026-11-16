<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class MariaDbDump
{
    public function available(): bool
    {
        $binary = (string) config('infrastructure.mariadb_dump_binary');
        $defaults = (string) config('infrastructure.mariadb_backup_defaults');
        return $binary !== '' && is_executable($binary) && $defaults !== '' && is_file($defaults) && ! is_link($defaults) && is_readable($defaults);
    }

    public function snapshot(string $destination): void
    {
        if (! $this->available()) throw new RuntimeException('Configure mariadb-dump y su archivo privado de conexión para respaldar MariaDB.');
        $database = DB::connection()->getDatabaseName();
        if (! preg_match('/\A[A-Za-z0-9_]+\z/', $database)) throw new RuntimeException('Nombre de base no válido para respaldo.');
        $handle = fopen($destination, 'x');
        if (! $handle) throw new RuntimeException('No se puede preparar el volcado privado.');
        fclose($handle);
        chmod($destination, 0600);
        $process = new Process([
            config('infrastructure.mariadb_dump_binary'), '--defaults-file='.config('infrastructure.mariadb_backup_defaults'),
            '--single-transaction', '--quick', '--routines', '--triggers', '--events', '--hex-blob', '--no-tablespaces',
            '--default-character-set=utf8mb4', '--result-file='.$destination, $database,
        ]);
        $process->setTimeout(120);
        $process->run();
        if (! $process->isSuccessful() || filesize($destination) === 0) {
            throw new RuntimeException('No se pudo completar el volcado MariaDB; no se publicó ningún respaldo.');
        }
    }
}
