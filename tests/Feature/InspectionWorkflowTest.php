<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\Audit;
use App\Models\CalendarTask;
use App\Models\Maintenance;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $assetId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-10 15:00:00', 'UTC'));
        $this->seed();
        $this->asUser(4);
        $this->assetId = $this->postJson('/api/activos', ['categoria_id' => 4, 'ubicacion_id' => 1,
            'nombre' => 'Impresora de ensayo', 'serie' => 'QA-INSPECTION', 'frecuencia_service' => 30])->assertCreated()->json('data.id');
    }

    private function asUser(int $id): void
    {
        $this->actingAs(User::findOrFail($id));
    }

    private function plan(array $changes = []): array
    {
        return array_replace(['asset_id' => $this->assetId, 'fecha' => '2026-11-10', 'hora' => '14:15',
            'titulo' => 'Inspección de impresora', 'tipo' => 'mantenimiento', 'technician_id' => 5,
            'submission_id' => (string) Str::uuid()], $changes);
    }

    private function inspection(array $changes = []): array
    {
        return array_replace(['asset_id' => $this->assetId, 'maintenance_date' => '2026-11-10',
            'detail' => 'Verificación funcional y de alimentación.', 'submission_id' => (string) Str::uuid()], $changes);
    }

    public function test_one_submission_completes_the_task_records_actual_work_and_creates_one_navigable_report(): void
    {
        $this->asUser(5);
        $revision = $this->getJson('/api/auth/staff-revision')->json('operation_revision');
        $this->asUser(4);
        $plan = $this->plan();
        $task = $this->postJson('/api/calendario', $plan)->assertCreated()->json('data.id');
        $this->postJson('/api/calendario', $plan)->assertCreated()->assertJsonPath('data.id', $task);
        $this->postJson('/api/calendario', [...$plan, 'hora' => '15:15'])->assertConflict();
        $this->asUser(5);
        $this->assertNotSame($revision, $this->getJson('/api/auth/staff-revision')->json('operation_revision'));
        $this->assertSame($this->getJson('/api/activos')->json('total'), $this->getJson('/api/dashboard/stats')->json('data.total_activos'));
        $this->patchJson("/api/calendario/$task/toggle", ['completed' => true])->assertUnprocessable();
        $data = $this->inspection(['task_id' => $task, 'finding' => 'Desgaste del rodillo principal.', 'priority' => 'Alta']);
        $this->asUser(6);
        $this->postJson('/api/mantenimientos', $data)->assertForbidden();
        $this->asUser(5);
        $record = $this->postJson('/api/mantenimientos', $data)->assertCreated()->json('data');
        $this->postJson('/api/mantenimientos', $data)->assertCreated()->assertJsonPath('data.id', $record['id']);
        $this->postJson('/api/mantenimientos', [...$data, 'detail' => 'Otro resultado.'])->assertConflict();
        $this->postJson('/api/mantenimientos', [...$data, 'submission_id' => (string) Str::uuid()])->assertConflict();
        $this->assertSame(1, Maintenance::where('task_id', $task)->count());
        $this->assertSame(1, Report::where('descripcion', $data['finding'])->count());
        $this->assertSame(1, Alert::where('reporte_id', $record['reporte_id'])->count());
        $this->getJson('/api/reportes/'.$record['reporte_id'])->assertOk()->assertJsonPath('data.inspections.0.id', $record['id']);
        $this->assertSame($record['id'], collect($this->getJson('/api/calendario')->json('data.tasks'))->firstWhere('id', $task)['inspection_id']);
        $this->getJson("/api/activos/{$this->assetId}")->assertJsonPath('data.asset.next_maintenance', '2026-12-10')
            ->assertJsonPath('data.asset.mantenimientos.0.technician_id', 5);
        $this->assertDatabaseHas('tareas_calendario', ['id' => $task, 'completed' => true]);
        $this->patchJson("/api/calendario/$task/toggle", ['completed' => false])->assertUnprocessable();
        $this->asUser(6);
        $this->getJson('/api/reportes/'.$record['reporte_id'])->assertForbidden();
        $this->getJson("/api/activos/{$this->assetId}")->assertJsonPath('data.asset.mantenimientos.0.reporte_id', null);
    }

    public function test_spontaneous_inspections_dates_history_and_monthly_counts(): void
    {
        $asset = Asset::find($this->assetId);
        $this->assertSame('2026-12-10', $asset->next_maintenance->toDateString());
        foreach (['2026-11-09', '2026-11-11'] as $invalid) {
            $this->postJson('/api/mantenimientos', $this->inspection(['maintenance_date' => $invalid]))->assertUnprocessable();
        }
        $month = $this->getJson('/api/estadisticas')->json('data.maintenance_months.2');
        $reportCount = Report::count();
        $payload = $this->inspection();
        $record = $this->postJson('/api/mantenimientos', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/mantenimientos', $payload)->assertJsonPath('data.id', $record);
        $this->postJson('/api/mantenimientos', $this->inspection())->assertCreated();
        $this->assertSame(2, Maintenance::where('activo_id', $asset->id)->count());
        $this->assertSame($reportCount, Report::count());
        $monthAfter = $this->getJson('/api/estadisticas')->json('data.maintenance_months.2');
        $this->assertSame($month['completed'] + 2, $monthAfter['completed']);
        $this->assertSame($month['scheduled'], $monthAfter['scheduled']);
        $asset->update(['frecuencia_service' => 90]);
        $row = collect($this->getJson('/api/mantenimientos')->json('data'))->firstWhere('id', $record);
        $this->assertSame('2026-12-10', $row['next_date']);
        $this->assertSame(30, $row['periodicity_days']);
        $this->assertSame(User::find(4)->nombre, $row['technician_name']);
        $this->assertSame('2027-02-08', $asset->next_maintenance->toDateString());
        $this->travelTo(Carbon::parse('2027-02-09 01:00:00', 'UTC'));
        $this->assertSame(0, $asset->days_to_maintenance);
    }

    public function test_an_inspection_without_a_periodic_interval_keeps_its_history_without_a_due_date(): void
    {
        $asset = Asset::findOrFail($this->assetId);
        $asset->update(['frecuencia_service' => 0]);
        $id = $this->postJson('/api/mantenimientos', $this->inspection())->assertCreated()
            ->assertJsonPath('data.periodicity_days', 0)->assertJsonPath('data.proxima_fecha', null)->json('data.id');
        $asset->update(['frecuencia_service' => 30]);
        $row = collect($this->getJson('/api/mantenimientos')->assertOk()->json('data'))->firstWhere('id', $id);
        $this->assertNull($row['next_date']);
        $this->assertSame(0, $row['periodicity_days']);
    }

    public function test_release_reschedule_retire_and_stale_requests_preserve_the_agenda(): void
    {
        $task = $this->postJson('/api/calendario', $this->plan())->json('data.id');
        $edit = ['expected_version' => 0, 'fecha' => '2026-11-12', 'hora' => '23:45', 'technician_id' => 6];
        $this->patchJson("/api/calendario/$task", $edit)->assertOk()->assertJsonPath('data.technician_id', 6);
        $this->patchJson("/api/calendario/$task", $edit)->assertConflict();
        $this->asUser(5);
        $this->getJson('/api/calendario')->assertJsonMissing(['id' => $task]);
        $this->asUser(6);
        $this->patchJson("/api/calendario/$task", $edit)->assertForbidden();
        $this->asUser(4);
        $this->deleteJson("/api/activos/{$this->assetId}")->assertOk();
        $this->assertDatabaseHas('tareas_calendario', ['id' => $task, 'completed' => false, 'cancel_reason' => 'Retiro del dispositivo.']);
        $this->postJson('/api/calendario', $this->plan())->assertConflict();
        $this->postJson('/api/mantenimientos', $this->inspection(['task_id' => $task]))->assertConflict();
        $this->patchJson("/api/calendario/$task/toggle", ['completed' => true])->assertConflict();
        $this->patchJson("/api/activos/{$this->assetId}/toggle", ['expected_state' => 'Retirado'])->assertOk();
        $new = $this->postJson('/api/calendario', $this->plan())->assertCreated()->json('data.id');
        $this->patchJson("/api/calendario/$new", ['cancel_reason' => 'Cambio de plan.', 'expected_version' => 0])->assertOk();
        $this->getJson('/api/dashboard/today-tasks')->assertJsonMissing(['id' => $new]);
    }

    public function test_report_links_require_access_and_the_same_equipment_and_failed_writes_roll_back(): void
    {
        $report = $this->postJson('/api/reportes', ['asset_id' => $this->assetId, 'priority' => 'Alta', 'description' => 'Intermitencia en el rodillo.'])->json('data.id');
        $this->postJson('/api/calendario', $this->plan(['tipo' => 'reporte']))->assertUnprocessable();
        $this->postJson('/api/calendario', $this->plan(['tipo' => 'reporte', 'report_id' => $report]))->assertUnprocessable();
        $this->patchJson("/api/reportes/$report/asignacion", ['technician_id' => 6])->assertOk();
        $this->postJson('/api/calendario', $this->plan(['tipo' => 'reporte', 'report_id' => $report, 'technician_id' => 6]))->assertCreated();
        $task = $this->postJson('/api/calendario', $this->plan())->json('data.id');
        $this->asUser(5);
        $payload = $this->inspection(['task_id' => $task, 'report_id' => $report]);
        $this->postJson('/api/mantenimientos', $payload)->assertUnprocessable();
        $this->assertFalse(CalendarTask::find($task)->completed);
        $this->asUser(4);
        $this->postJson('/api/mantenimientos', [...$payload, 'asset_id' => 1])->assertUnprocessable();
        $this->postJson('/api/mantenimientos', $payload)->assertCreated()->assertJsonPath('data.reporte_id', $report);
        $supply = $this->postJson('/api/calendario', $this->plan(['tipo' => 'consumible']))->json('data.id');
        $this->patchJson("/api/consumibles/{$this->assetId}", ['level' => 87, 'task_id' => $supply])->assertOk();
        $this->assertTrue(CalendarTask::find($supply)->completed);
        Audit::creating(static function () { throw new \RuntimeException('Fallo inducido de auditoría.'); });
        $this->patchJson("/api/consumibles/{$this->assetId}", ['level' => 3])->assertInternalServerError();
        $this->assertDatabaseHas('consumibles', ['activo_id' => $this->assetId, 'nivel' => 87]);
    }

    public function test_flagged_legacy_date_requires_an_explicit_confirmation(): void
    {
        $record = Maintenance::create(['activo_id' => $this->assetId, 'fecha_mantenimiento' => '2026-11-24',
            'descripcion' => 'Registro anterior', 'observaciones' => 'Registrado por Técnico de prueba. Próxima revisión calculada: 2026-12-24.']);
        $record->forceFill(['requires_review' => true])->save();
        $this->assertNull(Asset::find($this->assetId)->last_maintenance);
        $path = "/api/mantenimientos/{$record->id}/fecha";
        $this->asUser(5);
        $this->patchJson($path, ['maintenance_date' => '2026-11-10'])->assertForbidden();
        $this->asUser(4);
        $this->patchJson($path, ['maintenance_date' => '2026-11-24'])->assertUnprocessable();
        $this->patchJson($path, ['maintenance_date' => '2026-11-10'])->assertOk();
        $this->patchJson($path, ['maintenance_date' => '2026-11-10'])->assertConflict();
        $record->refresh();
        $this->assertFalse($record->requires_review);
        $this->assertSame('2026-12-10', $record->proxima_fecha->toDateString());
    }
}
