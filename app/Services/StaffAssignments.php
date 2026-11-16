<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Audit;
use App\Models\CalendarTask;
use App\Models\Report;
use App\Models\TechnicalChief;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffAssignments
{
    /** Se ejecuta en la transacción que bloquea y deshabilita la cuenta. */
    public function release(User $user, array $databaseReleasedReportIds = []): array
    {
        $pending = CalendarTask::where('technician_id', $user->id)->where('completed', false);
        $versioned = Schema::hasColumn('tareas_calendario', 'revision');
        if ($versioned) $pending->whereNull('cancel_reason');
        $tasks = $pending->lockForUpdate()->pluck('id');
        if ($tasks->isNotEmpty()) {
            $release = ['technician_id' => null, 'technician_name' => null];
            if ($versioned) $release['revision'] = DB::raw('revision + 1');
            CalendarTask::whereKey($tasks)->update($release);
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Tareas sin asignar por baja de la cuenta {$user->id}: ".$tasks->implode(', ').'.']);
        }

        $assets = Asset::inInventory()->where('responsable_id', $user->id)->lockForUpdate()->get();
        foreach ($assets as $asset) {
            $asset->responsable_id = null;
            $asset->setInventoryDetails($asset->nombre, null);
            $asset->save();
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Activo {$asset->codigo} sin responsable por baja de la cuenta {$user->id}."]);
        }

        $assignments = DB::table('tecnico_jefe_tecnico')->where('tecnico_id', $user->id)->whereNull('fecha_finalizacion');
        $openIds = Report::open()->whereIn('id', (clone $assignments)->select('reporte_id'))
            ->orderBy('id')->lockForUpdate()->pluck('id');
        $released = $databaseReleasedReportIds ? count(array_unique($databaseReleasedReportIds)) : $assignments->whereIn('reporte_id', $openIds)->update([
            'fecha_finalizacion' => now(), 'motivo_reasignacion' => 'Baja de la cuenta del técnico.',
        ]);
        // The earlier staff migration also uses this release before report versioning exists.
        if ($released && ! DatabaseWorkflow::enabled() && Schema::hasColumn('reportes', 'revision_flujo')) {
            Report::whereKey($openIds)->increment('revision_flujo');
        }
        $transferred = 0;
        $nextChief = null;
        if (TechnicalChief::whereKey($user->id)->exists()) {
            $nextChief = TechnicalChief::firstActiveId();
            $transferred = Report::open()->where('jefe_id', $user->id)->update(['jefe_id' => $nextChief]);
            if ($transferred) {
                $destination = $nextChief ? "jefatura de la cuenta {$nextChief}" : 'pendientes de una nueva jefatura';
                Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Baja del jefe {$user->id}. Reportes abiertos: {$transferred}, {$destination}."]);
            }
        }

        return ['tasks' => $tasks->count(), 'assets' => $assets->count(), 'reports' => $released, 'chief_reports' => $transferred, 'next_chief_id' => $nextChief];
    }
}
