<?php

use App\Services\SimulationSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $manifests = $this->matchingManifests();
        Schema::table('tareas_calendario', function (Blueprint $table) {
            $table->foreignId('reporte_id')->nullable()->constrained('reportes')->restrictOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->char('submission_key', 64)->nullable()->unique();
            $table->char('submission_hash', 64)->nullable();
        });
        Schema::table('mantenimientos', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->unique()->constrained('tareas_calendario')->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->string('technician_name')->nullable();
            $table->unsignedInteger('interval_days')->nullable();
            $table->date('projected_date')->nullable();
            $table->boolean('requires_review')->default(false);
            $table->char('submission_key', 64)->nullable()->unique();
            $table->char('submission_hash', 64)->nullable();
        });
        DB::table('mantenimientos')->whereDate('fecha_mantenimiento', '>', now('America/Montevideo')->toDateString())
            ->update(['requires_review' => true]);
        DB::table('tareas_calendario')->where('completed', false)
            ->whereIn('asset_id', DB::table('activos')->whereRaw("LOWER(TRIM(estado_activo)) = 'retirado'")->select('id'))
            ->update(['cancel_reason' => 'Retiro del dispositivo.']);
        $this->refreshManifests($manifests);
    }

    public function down(): void
    {
        $manifests = $this->matchingManifests();
        Schema::table('mantenimientos', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['technician_id']);
            $table->dropUnique(['task_id']);
            $table->dropUnique(['submission_key']);
            $table->dropColumn(['task_id', 'technician_id', 'technician_name', 'interval_days', 'projected_date', 'requires_review', 'submission_key', 'submission_hash']);
        });
        Schema::table('tareas_calendario', function (Blueprint $table) {
            $table->dropForeign(['reporte_id']);
            $table->dropUnique(['submission_key']);
            $table->dropColumn(['reporte_id', 'cancel_reason', 'revision', 'submission_key', 'submission_hash']);
        });
        $this->refreshManifests($manifests);
    }

    private function matchingManifests(): array
    {
        $snapshot = new SimulationSnapshot;
        $manifests = [];
        foreach (DB::table('simulation_runs')->whereNull('retired_at')->get() as $run) {
            $entries = json_decode($run->manifest, true, flags: JSON_THROW_ON_ERROR);
            $matching = [];
            foreach ($entries as $index => $entry) {
                $table = $entry['table'];
                if (in_array($table, ['reportes', 'mantenimientos', 'tareas_calendario'], true)
                    && DB::table($table)->where('id', $entry['id'])->exists()
                    && $snapshot->record($table, $entry['id'])['hash'] === $entry['hash']) {
                    $matching[] = $index;
                }
            }
            $manifests[$run->id] = [$entries, $matching];
        }
        return $manifests;
    }

    private function refreshManifests(array $manifests): void
    {
        $snapshot = new SimulationSnapshot;
        foreach ($manifests as $id => [$entries, $matching]) {
            foreach ($matching as $index) $entries[$index] = $snapshot->record($entries[$index]['table'], $entries[$index]['id']);
            DB::table('simulation_runs')->where('id', $id)->update(['manifest' => json_encode($entries, JSON_THROW_ON_ERROR)]);
        }
    }
};
