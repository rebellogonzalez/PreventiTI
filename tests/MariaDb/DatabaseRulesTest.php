<?php

namespace Tests\MariaDb;

use App\Models\{Alert, Asset, AssetConsumable, Report, User};
use App\Services\{DatabaseWorkflow, ReportService};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mariadb', DB::getDriverName());
        $this->assertStringEndsWith('_tests', DB::connection()->getDatabaseName());
        $this->seed();
    }

    private function report(): Report
    {
        return app(ReportService::class)->createReport(1, User::findOrFail(9), 'Alta', 'Caso aislado para comprobar las reglas SQL.');
    }

    private function rejected(callable $operation, string $state): void
    {
        try {
            $operation();
            $this->fail('La base aceptó una escritura inválida.');
        } catch (QueryException $error) {
            $this->assertSame($state, $error->errorInfo[0]);
        }
    }

    private function rawAssignment(int $report, int $technician): void
    {
        DB::table('tecnico_jefe_tecnico')->insert(['reporte_id' => $report, 'tecnico_id' => $technician, 'jefe_id' => 4, 'fecha_asignacion' => now()->utc()->format('Y-m-d H:i:s.u')]);
    }

    public function test_closing_through_the_api_keeps_interventions_and_rejects_repeated_closure(): void
    {
        $report = $this->report();
        $this->actingAs(User::findOrFail(4));
        $path = '/api/reportes/'.$report->id;
        $this->patchJson($path.'/asignacion', ['technician_id' => 5, 'expected_version' => 0])->assertOk()->assertJsonPath('data.version', 1);
        $this->patchJson($path.'/estado', ['status' => 'Diagnóstico', 'action_report' => 'Informe de diagnóstico.', 'final_diagnosis' => 'Diagnóstico documentado.', 'expected_version' => 1])->assertOk()->assertJsonPath('data.version', 2);
        $this->patchJson($path.'/estado', ['status' => 'Finalizado', 'action_report' => 'Informe de cierre.', 'expected_version' => 2])->assertOk()
            ->assertJsonPath('data.version', 3)->assertJsonCount(2, 'data.interventions');
        $end = $report->fresh()->getRawOriginal('fecha_cierre');
        $this->assertSame($end, DB::table('tecnico_jefe_tecnico')->where('reporte_id', $report->id)->value('fecha_finalizacion'));
        $this->patchJson($path.'/estado', ['status' => 'Finalizado', 'action_report' => 'Intento de sobrescritura.'])->assertUnprocessable();
        $this->assertSame('Informe de cierre.', $report->fresh()->informe_accionar);
    }

    public function test_direct_writes_cannot_assign_closed_reports_inactive_accounts_or_two_technicians(): void
    {
        $report = $this->report();
        $this->rawAssignment($report->id, 5);
        $this->assertSame('En curso', $report->fresh()->estado_reporte);
        $this->assertSame(1, $report->fresh()->revision_flujo);
        $this->rejected(fn () => $this->rawAssignment($report->id, 6), '23000');
        DB::table('usuarios')->where('id', 5)->update(['activo' => false]);
        $this->assertSame(0, DB::table('tecnico_jefe_tecnico')->where('tecnico_id', 5)->whereNull('fecha_finalizacion')->count());
        $this->assertSame('En curso', $report->fresh()->estado_reporte);
        $this->rejected(fn () => $this->rawAssignment($report->id, 5), '45000');
        $service = app(ReportService::class);
        $service->assignTechnician($report->fresh(), User::findOrFail(4), 6, null);
        $service->transition($report->fresh(), User::findOrFail(4), 'Finalizado', 'Cierre directo documentado.', null);
        $this->assertNull($report->fresh()->diagnostico_final);
        $this->rejected(fn () => $this->rawAssignment($report->id, 7), '45000');
        $this->assertForeignKeyIntegrity();
    }

    public function test_raw_transitions_require_the_correct_fields_and_an_active_assignment(): void
    {
        $report = $this->report();
        $this->rejected(fn () => DB::table('reportes')->where('id', $report->id)->update(['estado_reporte' => 'En curso']), '45000');
        $this->rawAssignment($report->id, 5);
        $this->rejected(fn () => DB::table('reportes')->where('id', $report->id)->update(['estado_reporte' => 'Diagnóstico', 'informe_accionar' => 'Informe documentado.']), '45000');
        DB::table('reportes')->where('id', $report->id)->update(['estado_reporte' => 'Diagnóstico', 'informe_accionar' => 'Informe documentado.', 'diagnostico_final' => 'Diagnóstico documentado.']);
        $this->rejected(fn () => DB::table('reportes')->where('id', $report->id)->update(['diagnostico_final' => null]), '45000');
        DB::table('reportes')->where('id', $report->id)->update(['estado_reporte' => 'Finalizado']);
        $this->rejected(fn () => DB::table('reportes')->where('id', $report->id)->update(['estado_reporte' => 'En curso']), '45000');
    }

    public function test_asset_history_records_real_changes_and_cleans_optional_actor_context(): void
    {
        $before = DB::table('historial_estados_activo')->count();
        DatabaseWorkflow::context(User::findOrFail(1), 'Motivo de prueba.', fn () => Asset::whereKey(1)->update(['estado_activo' => 'En Mantenimiento']));
        Asset::whereKey(1)->update(['estado_activo' => 'En Mantenimiento']);
        $this->assertSame($before + 1, DB::table('historial_estados_activo')->count());
        $this->assertDatabaseHas('historial_estados_activo', ['activo_id' => 1, 'estado_anterior' => 'Operativo', 'estado_nuevo' => 'En Mantenimiento', 'usuario_id' => 1, 'motivo' => 'Motivo de prueba.']);
        $this->assertNull(DB::selectOne('SELECT @preventiti_usuario_id AS actor')->actor);
        Asset::whereKey(1)->update(['estado_activo' => 'Fuera de Servicio']);
        $this->assertDatabaseHas('historial_estados_activo', ['activo_id' => 1, 'estado_nuevo' => 'Fuera de Servicio', 'usuario_id' => null, 'motivo' => null]);
        $this->rejected(fn () => Asset::whereKey(1)->update(['estado_activo' => 'operativo']), '23000');
        $this->actingAs(User::findOrFail(1))->patchJson('/api/activos/1/toggle', ['expected_state' => 'Fuera de Servicio'])->assertOk();
        $this->assertDatabaseHas('historial_estados_activo', ['activo_id' => 1, 'estado_nuevo' => 'Retirado', 'usuario_id' => 1]);
    }

    public function test_consumable_crossing_creates_one_report_and_alert_entirely_in_sql(): void
    {
        $consumable = AssetConsumable::firstOrFail();
        $consumable->update(['nivel' => 100]);
        $reports = Report::count();
        DB::table('consumibles')->where('id', $consumable->id)->update(['nivel' => 20]);
        $this->assertSame($reports + 1, Report::count());
        $report = Report::latest('id')->firstOrFail();
        $this->assertSame('Recibido/Derivado', $report->estado_reporte);
        $this->assertSame($consumable->activo_id, $report->activo_id);
        $this->assertNull($report->creador_id);
        $this->assertDatabaseHas('alertas', ['reporte_id' => $report->id, 'tipo_alerta' => 'Consumible', 'nivel_alerta' => 'Medio', 'urgencia' => 'Medio', 'estado_alerta' => 'Pendiente']);
        $this->actingAs(User::findOrFail(4))->getJson('/api/reportes/'.$report->id)->assertOk()->assertJsonPath('data.creator_id', null);
        foreach ([20, 15, 0, 100] as $level) $consumable->update(['nivel' => $level]);
        $this->assertSame($reports + 1, Report::count());
        $consumable->update(['nivel' => 0]);
        $this->assertSame($reports + 2, Report::count());
        $this->rejected(fn () => $consumable->update(['nivel' => -1]), '23000');
        $this->rejected(fn () => $consumable->update(['nivel' => 101]), '23000');
        $consumable->update(['nivel' => 100]);
        $count = Report::count();
        try {
            DB::transaction(function () use ($consumable) {
                $consumable->update(['nivel' => 20]);
                throw new \RuntimeException('Revertir el caso de prueba.');
            });
        } catch (\RuntimeException $error) {
            $this->assertSame('Revertir el caso de prueba.', $error->getMessage());
        }
        $this->assertSame($count, Report::count());
        $this->assertSame(100.0, $consumable->fresh()->nivel);
        $this->assertForeignKeyIntegrity();
    }
}
