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
        // Only refresh fingerprints of simulation records that still match their original manifest.
        $snapshot = new SimulationSnapshot;
        $manifests = [];
        foreach (DB::table('simulation_runs')->whereNull('retired_at')->get() as $run) {
            $entries = json_decode($run->manifest, true, flags: JSON_THROW_ON_ERROR);
            $matching = [];
            foreach ($entries as $index => $entry) {
                if ($entry['table'] === 'reportes' && DB::table('reportes')->where('id', $entry['id'])->exists()
                    && $snapshot->record('reportes', $entry['id'])['hash'] === $entry['hash']) {
                    $matching[] = $index;
                }
            }
            if ($matching) $manifests[$run->id] = [$entries, $matching];
        }

        Schema::table('reportes', function (Blueprint $table) {
            $table->char('clave_envio', 64)->nullable()->unique();
            $table->unsignedInteger('revision_flujo')->default(0);
            $table->json('intervenciones')->nullable();
        });
        DB::transaction(function () use ($snapshot, $manifests) {
            DB::table('tecnico_jefe_tecnico')->whereNull('fecha_finalizacion')
                ->whereIn('reporte_id', DB::table('reportes')->select('id')->whereNotNull('fecha_cierre'))
                ->update(['fecha_finalizacion' => DB::raw('(SELECT fecha_cierre FROM reportes WHERE reportes.id = tecnico_jefe_tecnico.reporte_id)')]);
            foreach ($manifests as $id => [$entries, $matching]) {
                foreach ($matching as $index) $entries[$index] = $snapshot->record('reportes', $entries[$index]['id']);
                DB::table('simulation_runs')->where('id', $id)->update(['manifest' => json_encode($entries, JSON_THROW_ON_ERROR)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reportes', fn (Blueprint $table) => $table->dropUnique(['clave_envio']));
        Schema::table('reportes', fn (Blueprint $table) => $table->dropColumn(['clave_envio', 'revision_flujo', 'intervenciones']));
    }
};
