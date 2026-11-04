<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aligns the development replica with the MariaDB source model and the
     * agreed report workflow. Data is simulated, but the constraints are kept
     * compatible with the future MariaDB deployment.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('usuarios', 'nombre_completo')) {
            Schema::table('usuarios', fn (Blueprint $table) => $table->string('nombre_completo', 150)->nullable()->after('id'));
        }

        if (! Schema::hasColumn('usuarios', 'direccion')) {
            Schema::table('usuarios', fn (Blueprint $table) => $table->string('direccion', 200)->nullable()->after('email'));
        }

        if (! Schema::hasColumn('activos', 'ubicacion')) {
            Schema::table('activos', fn (Blueprint $table) => $table->string('ubicacion', 200)->nullable()->after('serial'));
        }

        if (! Schema::hasColumn('reportes', 'informe_accionar')) {
            Schema::table('reportes', fn (Blueprint $table) => $table->text('informe_accionar')->nullable()->after('resolucion'));
        }

        if (! Schema::hasColumn('reportes', 'diagnostico_final')) {
            Schema::table('reportes', fn (Blueprint $table) => $table->text('diagnostico_final')->nullable()->after('informe_accionar'));
        }

        // The former prototype used "Abierto". All pre-existing reports must
        // enter the agreed single initial state so they are not blocked from
        // the new transition graph.
        DB::table('reportes')
            ->where('estado_reporte', 'Abierto')
            ->update(['estado_reporte' => 'Recibido/Derivado', 'updated_at' => now()]);

        // Remap only the simulated legacy areas. The supplied image is used
        // as an area reference; no quantity or device data is inferred from it.
        $areas = [
            1 => ['piso' => 'Planta principal', 'sala' => 'Área administrativa abierta — sector A'],
            2 => ['piso' => 'Planta principal', 'sala' => 'Área administrativa abierta — sector B'],
            3 => ['piso' => 'Planta principal', 'sala' => 'Atención al cliente'],
            4 => ['piso' => 'Planta principal', 'sala' => 'Sala de servidores / IT'],
            5 => ['piso' => 'Planta principal', 'sala' => 'Oficina de gerencia y coordinación'],
            6 => ['piso' => 'Planta principal', 'sala' => 'Administración y recursos humanos'],
            7 => ['piso' => 'Planta principal', 'sala' => 'Sala de personal técnico'],
            8 => ['piso' => 'Exterior', 'sala' => 'Perímetro y accesos'],
        ];
        $legacyAreaIds = [
            'Línea de Cajas (Cajas 01 a 12)' => 1,
            'Salón de Ventas y Exposición' => 2,
            'Sector Espera y Turnos' => 3,
            'Sala de Servidores y Racks' => 4,
            'Oficinas de Gerencia' => 5,
            'Oficinas de Administración y RRHH' => 6,
            'Depósito y Taller de Soporte' => 7,
            'Perímetro Exterior e Ingresos' => 8,
        ];
        $overrides = [30 => 5, 42 => 3, 79 => 1, 80 => 1, 81 => 1, 82 => 1, 83 => 1, 84 => 7, 85 => 7];

        foreach (DB::table('activo_ubicaciones')->get(['activo_id', 'piso']) as $location) {
            $areaId = $overrides[$location->activo_id] ?? ($legacyAreaIds[$location->piso] ?? null);
            if (! $areaId) continue;

            DB::table('activo_ubicaciones')->where('activo_id', $location->activo_id)->update($areas[$areaId]);
            DB::table('activos')->where('id', $location->activo_id)->update([
                'ubicacion' => $areas[$areaId]['piso'].' - '.$areas[$areaId]['sala'],
                'updated_at' => now(),
            ]);
        }

        // The prior replica used tecnico_id as the only primary key, which
        // prevented a technician from having assignment history on reports.
        Schema::dropIfExists('tecnico_jefe_tecnico');
        Schema::create('tecnico_jefe_tecnico', function (Blueprint $table) {
            $table->unsignedBigInteger('tecnico_id');
            $table->unsignedBigInteger('jefe_id');
            $table->unsignedBigInteger('reporte_id');
            $table->dateTime('fecha_asignacion');
            $table->dateTime('fecha_finalizacion')->nullable();
            $table->text('motivo_reasignacion')->nullable();

            // The assignment instant is part of the key so a technician can
            // be reassigned to the same report later without losing history.
            $table->primary(['tecnico_id', 'jefe_id', 'reporte_id', 'fecha_asignacion']);
            $table->foreign('tecnico_id')->references('id')->on('tecnicos')->cascadeOnDelete();
            $table->foreign('jefe_id')->references('id')->on('jefes_tecnicos')->cascadeOnDelete();
            $table->foreign('reporte_id')->references('id')->on('reportes')->cascadeOnDelete();
            $table->index(['reporte_id', 'fecha_finalizacion'], 'idx_asignacion_reporte_activa');
        });

        // A new report always identifies one exact device. Historical reports
        // without a current device relation remain valid archival records.
        Schema::table('reporte_activo', function (Blueprint $table) {
            $table->unique('reporte_id', 'uq_reporte_un_activo');
        });
    }

    public function down(): void
    {
        Schema::table('reporte_activo', function (Blueprint $table) {
            $table->dropUnique('uq_reporte_un_activo');
        });

        Schema::dropIfExists('tecnico_jefe_tecnico');
        Schema::create('tecnico_jefe_tecnico', function (Blueprint $table) {
            $table->unsignedBigInteger('tecnico_id')->primary();
            $table->unsignedBigInteger('jefe_id');
            $table->unsignedBigInteger('reporte_id')->nullable();
            $table->date('fecha_asignacion');
            $table->date('fecha_finalizacion')->nullable();
            $table->foreign('tecnico_id')->references('id')->on('tecnicos')->cascadeOnDelete();
            $table->foreign('jefe_id')->references('id')->on('jefes_tecnicos')->restrictOnDelete();
            $table->foreign('reporte_id')->references('id')->on('reportes')->nullOnDelete();
        });

        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn(['informe_accionar', 'diagnostico_final']);
        });
        Schema::table('activos', fn (Blueprint $table) => $table->dropColumn('ubicacion'));
        Schema::table('usuarios', fn (Blueprint $table) => $table->dropColumn(['nombre_completo', 'direccion']));
    }
};
