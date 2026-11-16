<?php

namespace App\Console\Commands;

use App\Services\{BackupService, DatabaseWorkflow, OperationalSimulation, SimulationSnapshot};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefreshOperationalSimulation extends Command
{
    protected $signature = 'simulation:refresh {--date= : Fecha de referencia YYYY-MM-DD; por defecto hoy en Uruguay} {--legacy-manifest= : Manifiesto privado y revisado de datos de demostración antiguos} {--apply : Aplicar después de guardar un respaldo}';
    protected $description = 'Renueva únicamente registros de simulación identificados y sin modificaciones posteriores.';

    public function handle(SimulationSnapshot $snapshot, OperationalSimulation $simulation): int
    {
        try {
            if (! app()->environment(['local', 'testing']) || ! in_array(DB::getDriverName(), ['sqlite', 'mariadb'], true)) throw new \RuntimeException('Este comando está limitado al entorno local SQLite o MariaDB.');
            $value = $this->option('date') ?: CarbonImmutable::today('America/Montevideo')->toDateString();
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'America/Montevideo');
            if (! $date || $date->toDateString() !== $value) throw new \RuntimeException('Fecha de referencia inválida.');
            $legacy = $this->option('legacy-manifest') ? json_decode(file_get_contents($this->option('legacy-manifest')), true, 512, JSON_THROW_ON_ERROR) : [];
            $manifest = $legacy;
            foreach (DB::table('simulation_runs')->whereNull('retired_at')->get() as $run) $manifest = [...$manifest, ...json_decode($run->manifest, true, 512, JSON_THROW_ON_ERROR)];
            $snapshot->assertUnchanged($manifest);
            $this->line(json_encode(['reference_date' => $value, 'replace' => collect($manifest)->countBy('table'), 'new_reports' => 36, 'official_assets' => DB::table('activos')->count(), 'accounts_preserved' => DB::table('usuarios')->count()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if (! $this->option('apply')) {
                $this->info('Vista previa: no se modificó la base. Use --apply para aplicar este plan.');
                return self::SUCCESS;
            }
            $backup = null;
            if (DatabaseWorkflow::enabled()) {
                $createdBackup = app(BackupService::class)->create();
                $backup = app(BackupService::class)->downloadPath($createdBackup['filename']);
                $this->info('Respaldo: '.$backup);
            } elseif (DB::connection()->getDatabaseName() !== ':memory:') {
                $folder = base_path('output/backups/simulacion-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)));
                if (! mkdir($folder, 0700, true)) throw new \RuntimeException('No se pudo crear el respaldo.');
                $backup = $folder.'/database.sqlite';
                DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($backup));
                chmod($backup, 0600);
                $this->info('Respaldo: '.$backup);
            } elseif (! app()->environment('testing')) {
                throw new \RuntimeException('No se aplica una simulación sin respaldo en una base persistente.');
            }
            $result = $simulation->refresh($date, $legacy);
            if ($backup) {
                file_put_contents(dirname($backup).'/resultado.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                chmod(dirname($backup).'/resultado.json', 0600);
            }
            $this->info(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
