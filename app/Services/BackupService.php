<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SQLite3;
use Throwable;
use ZipArchive;

class BackupService
{
    private const NAME = '/\Apreventiti_[0-9]{8}_[0-9]{6}_[a-f0-9]{12}\.zip\z/';

    public function support(): array
    {
        if (DatabaseWorkflow::enabled()) {
            $supported = class_exists(ZipArchive::class) && app(MariaDbDump::class)->available();
            return ['available' => $supported, 'scope' => 'Base MariaDB completa (datos, claves, índices, triggers y rutinas) y archivos de storage/app. No incluye credenciales de conexión.',
                'reason' => $supported ? null : 'El respaldo MariaDB requiere zip, mariadb-dump y un archivo privado de conexión.'];
        }
        $supported = class_exists(SQLite3::class) && class_exists(ZipArchive::class) && DB::getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() !== ':memory:';
        return ['available' => $supported, 'scope' => 'Base SQLite completa y archivos de storage/app. No incluye código ni configuración secreta del servidor.',
            'reason' => $supported ? null : 'Este respaldo requiere una base SQLite en archivo y las extensiones PHP sqlite3 y zip. Para otro motor se necesita un procedimiento de respaldo específico.'];
    }

    public function create(): array
    {
        $support = $this->support();
        if (! $support['available']) throw new RuntimeException($support['reason']);
        $root = (string) config('infrastructure.backup_directory');
        if (is_link($root)) throw new RuntimeException('El directorio privado de respaldo no puede ser un enlace simbólico.');
        if (! is_dir($root) && ! mkdir($root, 0700, true)) throw new RuntimeException('No se pudo crear el directorio privado de respaldos.');
        chmod($root, 0700);
        $lock = fopen($root.'/.backup.lock', 'c');
        if (! $lock) throw new RuntimeException('No se pudo abrir el bloqueo de respaldos.');
        chmod($root.'/.backup.lock', 0600);
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Ya hay un respaldo en curso. Espere antes de iniciar otro.');
        }
        $token = now()->utc()->format('Ymd_His').'_'.bin2hex(random_bytes(6));
        $work = $root.'/.work-'.$token;
        $filename = 'preventiti_'.$token.'.zip';
        try {
            if (! mkdir($work, 0700)) throw new RuntimeException('No se pudo preparar el respaldo.');
            $started = now()->toIso8601String();
            $driver = DatabaseWorkflow::enabled() ? 'mariadb' : 'sqlite';
            $databaseFile = $driver === 'mariadb' ? 'database.sql' : 'database.sqlite';
            $snapshot = $work.'/'.$databaseFile;
            $this->snapshot($snapshot);
            $entries = [['path' => 'database/'.$databaseFile, 'source' => $snapshot]];
            $storage = (string) config('infrastructure.backup_files_directory');
            $files = $this->files($storage);
            foreach ($files as $relative => $file) {
                $copy = $work.'/files/'.$relative;
                if (! is_dir(dirname($copy)) && ! mkdir(dirname($copy), 0700, true)) throw new RuntimeException('No se pudo preparar la copia de archivos.');
                if (! copy($file, $copy)) throw new RuntimeException('No se pudo copiar un archivo de storage/app.');
                chmod($copy, 0600);
                $entries[] = ['path' => 'storage/app/'.$relative, 'source' => $copy];
            }
            $manifest = ['version' => 1, 'started_at' => $started, 'database' => $driver, 'scope' => $support['scope'],
                'consistency' => ($driver === 'mariadb' ? 'Volcado InnoDB con single-transaction, triggers y rutinas; requiere no ejecutar DDL durante la copia.' : 'Instantánea SQLite transaccional.').' Archivos copiados durante la ventana del respaldo; no es una transacción conjunta de base y archivos.',
                'files' => array_map(fn ($entry) => ['path' => $entry['path'], 'bytes' => filesize($entry['source']), 'sha256' => hash_file('sha256', $entry['source'])], $entries)];
            $zip = new ZipArchive();
            $archive = $work.'/archive.zip';
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('No se pudo crear el archivo ZIP.');
            foreach ($entries as $entry) {
                if (! $zip->addFile($entry['source'], $entry['path'])) throw new RuntimeException('No se pudo agregar un archivo al respaldo.');
            }
            $zip->addEmptyDir('storage/app');
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if (! $zip->close()) throw new RuntimeException('No se pudo completar el archivo ZIP.');
            unset($zip);
            $this->verifyArchive($archive, $manifest);
            // Un cambio de archivos durante la copia requiere repetirla; nunca se declara completa silenciosamente.
            if (array_keys($files) !== array_keys($this->files($storage))) throw new RuntimeException('Cambió la lista de archivos durante el respaldo. Vuelva a intentarlo.');
            foreach ($files as $relative => $source) {
                if (! hash_equals(hash_file('sha256', $work.'/files/'.$relative), (string) @hash_file('sha256', $source))) throw new RuntimeException('Un archivo cambió durante el respaldo. Vuelva a intentarlo.');
            }
            $metadata = ['filename' => $filename, 'created_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'), 'started_at' => $started, 'bytes' => filesize($archive),
                'sha256' => hash_file('sha256', $archive), 'verified' => true, 'database' => $driver, 'storage_files' => count($files), 'scope' => $support['scope'],
                'download_url' => '/api/infraestructura/backups/'.$filename.'/download'];
            chmod($archive, 0600);
            $json = $work.'/metadata.json';
            if (file_put_contents($json, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('No se pudo guardar el manifiesto del respaldo.');
            chmod($json, 0600);
            if (! rename($archive, $root.'/'.$filename) || ! rename($json, $root.'/'.$filename.'.json')) throw new RuntimeException('No se pudo publicar el respaldo privado.');
            return $metadata;
        } finally {
            // Solo temporales creados por esta operación. Ningún respaldo anterior se rota ni elimina.
            unset($zip);
            if (is_dir($work)) File::deleteDirectory($work);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function snapshot(string $destination): void
    {
        if (DatabaseWorkflow::enabled()) {
            app(MariaDbDump::class)->snapshot($destination);
            return;
        }
        $path = DB::connection()->getDatabaseName();
        if (! is_file($path)) throw new RuntimeException('No se encontró el archivo SQLite configurado.');
        $source = new SQLite3($path, SQLITE3_OPEN_READONLY);
        $target = new SQLite3($destination);
        try {
            $source->busyTimeout(5000);
            if (! $source->backup($target)) throw new RuntimeException('No se pudo obtener una instantánea SQLite consistente.');
            if ($target->querySingle('PRAGMA integrity_check') !== 'ok' || $target->query('PRAGMA foreign_key_check')->fetchArray() !== false) throw new RuntimeException('La copia SQLite no pasó la comprobación de integridad.');
        } finally {
            $source->close();
            $target->close();
        }
        chmod($destination, 0600);
    }

    private function files(string $root): array
    {
        if (! is_dir($root) || is_link($root)) throw new RuntimeException('No está disponible el directorio de archivos que debe respaldarse.');
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink()) throw new RuntimeException('Hay un enlace simbólico en storage/app; debe definirse su respaldo antes de continuar.');
            if ($file->isDir()) continue;
            if (! $file->isFile() || ! $file->isReadable()) throw new RuntimeException('No se puede leer un archivo de storage/app.');
            $relative = substr($file->getPathname(), strlen(rtrim($root, '/')) + 1);
            $files[$relative] = $file->getPathname();
        }
        ksort($files);
        return $files;
    }

    private function verifyArchive(string $path, array $manifest): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('El ZIP no pasó la comprobación de integridad.');
        try {
            if ($zip->getFromName('manifest.json') === false) throw new RuntimeException('Falta el manifiesto del respaldo.');
            foreach ($manifest['files'] as $entry) {
                $stream = $zip->getStream($entry['path']);
                if (! $stream) throw new RuntimeException('Falta un archivo en el ZIP.');
                $hash = hash_init('sha256');
                $bytes = hash_update_stream($hash, $stream);
                fclose($stream);
                if ($bytes !== $entry['bytes'] || ! hash_equals($entry['sha256'], hash_final($hash))) throw new RuntimeException('Un archivo del ZIP no coincide con la copia original.');
            }
        } finally {
            $zip->close();
        }
    }

    public function latest(): ?array
    {
        $files = glob(config('infrastructure.backup_directory').'/preventiti_*.zip.json') ?: [];
        // El sufijo aleatorio del nombre no determina el orden de copias creadas en el mismo segundo.
        $created = [];
        foreach ($files as $file) {
            $created[$file] = (float) filemtime($file);
            if (is_link($file)) continue;
            try {
                $metadata = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                if (! empty($metadata['created_at'])) $created[$file] = (float) (new \DateTimeImmutable($metadata['created_at']))->format('U.u');
            } catch (Throwable) {
                // Una copia ilegible también debe mostrarse como fallo, no ocultarse.
            }
        }
        usort($files, fn ($a, $b) => $created[$b] <=> $created[$a]);
        if (! $files) return null;
        $name = basename($files[0], '.json');
        try {
            return $this->metadata($name);
        } catch (Throwable) {
            return ['filename' => $name, 'verified' => false, 'error' => 'El último respaldo no está disponible o no coincide con su SHA-256.'];
        }
    }

    public function metadata(string $filename): array
    {
        if (! preg_match(self::NAME, $filename)) throw new RuntimeException('Nombre de respaldo inválido.');
        $path = config('infrastructure.backup_directory').'/'.$filename;
        if (! is_file($path) || is_link($path) || ! is_file($path.'.json') || is_link($path.'.json')) throw new RuntimeException('Respaldo no disponible.');
        $metadata = json_decode(file_get_contents($path.'.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($metadata['filename'] ?? null) !== $filename || ! hash_equals((string) ($metadata['sha256'] ?? ''), hash_file('sha256', $path))) throw new RuntimeException('El respaldo no coincide con su SHA-256.');
        return $metadata;
    }

    public function downloadPath(string $filename): string
    {
        $this->metadata($filename);
        return config('infrastructure.backup_directory').'/'.$filename;
    }
}
