<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::findOrFail(4));
    }

    private function payload(string $priority = 'Alta'): array
    {
        return ['asset_id' => 1, 'priority' => $priority, 'description' => 'Falla intermitente en la terminal.'];
    }

    public function test_a_retry_returns_the_confirmed_report_even_after_the_device_is_retired(): void
    {
        $data = $this->payload() + ['submission_id' => (string) Str::uuid()];
        $counts = [Report::count(), Alert::count()];
        $id = $this->postJson('/api/reportes', $data)->assertCreated()->json('data.id');
        Asset::whereKey(1)->update(['estado_activo' => 'Retirado']);
        $this->postJson('/api/reportes', $data)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/api/reportes', [...$data, 'priority' => 'Media'])->assertConflict();
        $this->postJson('/api/reportes', $this->payload())->assertUnprocessable();
        $this->assertSame([$counts[0] + 1, $counts[1] + 1], [Report::count(), Alert::count()]);
    }

    public function test_all_filters_match_the_saved_priority_and_out_of_service_devices_accept_reports(): void
    {
        Asset::whereKey(1)->update(['estado_activo' => 'Fuera de Servicio']);
        $this->getJson('/api/reportes/activos-disponibles')->assertOk()->assertJsonFragment(['id' => 1]);
        foreach (['Baja', 'Media', 'Alta', 'Critica'] as $priority) {
            $id = $this->postJson('/api/reportes', $this->payload($priority))->assertCreated()
                ->assertJsonPath('data.priority', $priority)->json('data.id');
            $page = $this->getJson('/api/reportes?resumen=1&prioridad='.$priority)->assertOk();
            $this->assertContains($id, array_column($page->json('data'), 'id'));
            $this->assertSame([$priority], array_values(array_unique(array_column($page->json('data'), 'priority'))));
        }
    }

    public function test_concurrent_edits_require_review_and_both_interventions_remain_attributed(): void
    {
        $getRevision = fn () => $this->getJson('/api/auth/staff-revision')->assertOk()->json('report_revision');
        $revisions = [$getRevision()];
        $report = $this->postJson('/api/reportes', $this->payload())->assertCreated()->json('data');
        $id = $report['id'];
        $path = "/api/reportes/$id";
        $revisions[] = $getRevision();
        $this->patchJson($path.'/asignacion', ['technician_id' => 5, 'expected_version' => $report['version']])->assertOk()
            ->assertJsonPath('data.status', 'En curso')->assertJsonPath('data.version', 1);
        $revisions[] = $getRevision();
        $this->patchJson($path.'/asignacion', ['technician_id' => 6, 'reassignment_reason' => 'Cambio de responsable.', 'expected_version' => 0])->assertConflict();
        $this->patchJson($path.'/estado', ['status' => 'En curso', 'expected_version' => 0])->assertConflict();
        $this->patchJson($path.'/estado', ['status' => 'Diagnóstico', 'action_report' => 'Se aisló la fuente.', 'final_diagnosis' => 'Fuente averiada.'])->assertOk();
        $revisions[] = $getRevision();
        $this->patchJson($path.'/asignacion', ['technician_id' => 6, 'reassignment_reason' => 'Relevo de turno.', 'expected_version' => 2])
            ->assertOk()->assertJsonPath('data.status', 'Diagnóstico')->assertJsonPath('data.version', 3);
        $end = $this->patchJson($path.'/estado', ['status' => 'Finalizado', 'action_report' => 'Se instaló el reemplazo.'])->assertOk()
            ->assertJsonPath('data.assigned_technician', null)->assertJsonPath('data.last_technician.id', 6)
            ->assertJsonPath('data.interventions.0.text', 'Se aisló la fuente.')->assertJsonPath('data.interventions.0.stage', 'Diagnóstico')
            ->assertJsonPath('data.interventions.1.text', 'Se instaló el reemplazo.')->assertJsonPath('data.interventions.1.stage', 'Finalizado');
        foreach ($end->json('data.interventions') as $note) {
            $this->assertSame(4, $note['author_id']);
            $this->assertNotEmpty($note['author']);
            $this->assertNotEmpty($note['at']);
        }
        $finalPeriod = collect($end->json('data.assignment_history'))->firstWhere('technician_id', 6);
        $this->assertSame($end->json('data.closed_at'), $finalPeriod['finished_at']);
        $this->getJson('/api/reportes/historial?query=aislo%20fuente')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $id);
        $revisions[] = $getRevision();
        $this->assertSame($revisions, array_values(array_unique($revisions)));
    }

    public function test_a_received_report_with_a_previous_assignment_can_start_with_the_same_technician_or_a_replacement(): void
    {
        foreach ([5, 6] as $selected) {
            $id = $this->postJson('/api/reportes', $this->payload())->assertCreated()->json('data.id');
            $path = "/api/reportes/$id/asignacion";
            DB::table('tecnico_jefe_tecnico')->insert([
                'reporte_id' => $id, 'tecnico_id' => 5, 'jefe_id' => 4, 'fecha_asignacion' => now()->subDay(),
            ]);
            if (\App\Services\DatabaseWorkflow::enabled()) {
                $this->actingAs(User::findOrFail(5))->getJson("/api/reportes/$id")->assertOk()
                    ->assertJsonPath('data.status', 'En curso')->assertJsonPath('data.can_update_status', true)->assertJsonPath('data.version', 1);
                $this->actingAs(User::findOrFail(4));
                $this->patchJson($path, ['technician_id' => $selected, 'expected_version' => 0])->assertConflict();
                $data = ['technician_id' => $selected, 'expected_version' => 1];
                $this->patchJson($path, $data)->assertUnprocessable();
                if ($selected !== 5) {
                    $this->patchJson($path, $data + ['reassignment_reason' => 'Cambio de turno.'])->assertOk()
                        ->assertJsonPath('data.status', 'En curso')->assertJsonPath('data.version', 2)
                        ->assertJsonPath('data.assigned_technician.id', $selected)->assertJsonCount(2, 'data.assignment_history');
                }
                continue;
            }
            $this->actingAs(User::findOrFail(5))->getJson("/api/reportes/$id")->assertOk()->assertJsonPath('data.can_update_status', false);
            $this->patchJson("/api/reportes/$id/estado", ['status' => 'En curso'])->assertForbidden();
            $this->actingAs(User::findOrFail(4));
            $data = ['technician_id' => $selected, 'expected_version' => 0];
            if ($selected !== 5) {
                $this->patchJson($path, $data)->assertUnprocessable();
                $this->assertDatabaseHas('reportes', ['id' => $id, 'estado_reporte' => 'Recibido/Derivado', 'revision_flujo' => 0]);
                $data['reassignment_reason'] = 'Cambio de turno.';
            }
            $this->patchJson($path, $data)->assertOk()
                ->assertJsonPath('data.status', 'En curso')->assertJsonPath('data.assigned_technician.id', $selected)
                ->assertJsonPath('data.version', 1)->assertJsonCount($selected === 5 ? 1 : 2, 'data.assignment_history');
            $this->assertDatabaseHas('reportes', ['id' => $id, 'estado_reporte' => 'En curso']);
        }
    }

    public function test_only_the_current_specialist_can_diagnose_or_close_an_assigned_report(): void
    {
        $chief = User::findOrFail(4);
        $first = User::findOrFail(5);
        $second = User::findOrFail(6);
        $creator = User::findOrFail(7);
        $id = $this->actingAs($creator)->postJson('/api/reportes', $this->payload())->assertCreated()->json('data.id');
        $path = "/api/reportes/$id";
        $close = ['status' => 'Finalizado', 'action_report' => 'Se verificó el funcionamiento.'];
        $this->actingAs($chief)->patchJson("$path/asignacion", ['technician_id' => $first->id])->assertOk();
        foreach ([$creator, $second, User::findOrFail(1), User::findOrFail(9)] as $viewer) {
            $this->actingAs($viewer)->patchJson("$path/estado", $close)->assertForbidden();
        }
        $this->actingAs($first)->getJson('/api/reportes?resumen=1')->assertOk()
            ->assertJsonFragment(['id' => $id, 'can_update_status' => true]);
        $this->getJson('/api/reportes/tecnicos')->assertForbidden();
        $this->patchJson("$path/asignacion", ['technician_id' => $second->id])->assertForbidden();
        $diagnose = ['status' => 'Diagnóstico', 'action_report' => 'Se verificó la conexión.'];
        $this->patchJson("$path/estado", $diagnose)->assertUnprocessable()->assertJsonValidationErrors('final_diagnosis');
        $this->patchJson("$path/estado", [...$diagnose, 'final_diagnosis' => 'Conector defectuoso.', 'expected_version' => 1])
            ->assertOk()->assertJsonPath('data.status', 'Diagnóstico')->assertJsonPath('data.interventions.0.author_id', $first->id);

        $this->actingAs($chief)->patchJson("$path/asignacion", ['technician_id' => $second->id, 'reassignment_reason' => 'Cambio de turno.'])->assertOk();
        $this->actingAs($first)->getJson($path)->assertOk()->assertJsonPath('data.can_update_status', false);
        $this->patchJson("$path/estado", [...$close, 'expected_version' => 2])->assertForbidden();
        $this->patchJson("$path/estado", $close)->assertForbidden();
        $this->actingAs($second)->patchJson("$path/estado", [...$close, 'expected_version' => 2])->assertConflict();
        $this->patchJson("$path/estado", [...$close, 'expected_version' => 3])->assertOk()
            ->assertJsonPath('data.closed', true)->assertJsonPath('data.can_update_status', false)
            ->assertJsonPath('data.last_technician.id', $second->id)->assertJsonPath('data.interventions.1.author_id', $second->id);
        $this->patchJson("$path/estado", $close)->assertForbidden();

        $id = $this->postJson('/api/reportes', $this->payload())->assertCreated()->json('data.id');
        $this->actingAs($chief)->patchJson("/api/reportes/$id/asignacion", ['technician_id' => $first->id])->assertOk();
        $this->actingAs($first)->patchJson("/api/reportes/$id/estado", $close)->assertOk()
            ->assertJsonPath('data.closed', true)->assertJsonPath('data.interventions.0.author_id', $first->id);
    }

    public function test_legacy_solution_is_visible_and_closed_records_cannot_be_changed(): void
    {
        $id = $this->postJson('/api/reportes', $this->payload())->assertCreated()->json('data.id');
        if (\App\Services\DatabaseWorkflow::enabled()) {
            $path = "/api/reportes/$id";
            $this->patchJson($path.'/asignacion', ['technician_id' => 5])->assertOk();
            $this->patchJson($path.'/estado', ['status' => 'Finalizado', 'action_report' => 'Solución anterior.'])->assertOk();
            foreach (['Resuelto', 'Cerrado', 'Cancelado'] as $status) {
                // The reader must understand imported states; the new SQL
                // graph deliberately rejects creating them as new transitions.
                $legacy = Report::findOrFail($id);
                $legacy->estado_reporte = $status;
                $legacy->informe_accionar = null;
                $record = app(\App\Services\ReportReadService::class)->record($legacy, User::findOrFail(4));
                $this->assertTrue($record['closed']);
                $this->assertSame('Solución anterior.', $record['action_report']);
            }
            $this->getJson($path)->assertJsonPath('data.closed', true)->assertJsonPath('data.action_report', 'Solución anterior.');
            $this->patchJson($path.'/asignacion', ['technician_id' => 5])->assertUnprocessable();
            $this->patchJson($path.'/estado', ['status' => 'En curso'])->assertUnprocessable();
            return;
        }
        foreach (['Resuelto', 'Cerrado', 'Cancelado'] as $status) {
            Report::whereKey($id)->update(['estado_reporte' => $status, 'resolucion' => 'Solución anterior.']);
            $path = "/api/reportes/$id";
            $this->getJson($path)->assertJsonPath('data.closed', true)->assertJsonPath('data.action_report', 'Solución anterior.');
            $this->patchJson($path.'/asignacion', ['technician_id' => 5])->assertUnprocessable();
        }
        Report::whereKey($id)->update(['estado_reporte' => 'Recibido/Derivado', 'fecha_cierre' => now()]);
        $this->patchJson($path.'/estado', ['status' => 'En curso'])->assertUnprocessable();
    }

    public function test_history_pages_use_the_uruguayan_day_and_respect_authorization(): void
    {
        Carbon::setTestNow('2026-11-10 01:15:00 UTC');
        try {
            $this->actingAs(User::findOrFail(9));
            $ids = [];
            for ($n = 0; $n < 21; $n++) {
                $ids[] = $this->postJson('/api/reportes', $this->payload())->assertCreated()
                    ->assertJsonPath('data.created_at', '2026-11-09 22:15:00')->json('data.id');
            }
            $url = '/api/reportes/historial?from=2026-11-09&to=2026-11-09';
            $this->getJson($url)->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 21)->assertJsonPath('data.0.id', end($ids));
            $this->getJson($url.'&page=2')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ids[0]);
            $this->getJson('/api/reportes/historial?from=2026-11-10')->assertJsonPath('total', 0);
            $this->actingAs(User::findOrFail(10))->getJson($url)->assertJsonPath('total', 0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_report_list_query_count_is_independent_of_the_number_of_assigned_reports(): void
    {
        $queries = function () {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->getJson('/api/reportes')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            return $count;
        };
        for ($i = 0; $i < 8; $i++) {
            $id = $this->postJson('/api/reportes', $this->payload())->json('data.id');
            $this->patchJson("/api/reportes/$id/asignacion", ['technician_id' => 5])->assertOk();
            if ($i === 0) $initial = $queries();
        }
        $this->assertSame($initial, $queries());
        $this->getJson('/api/reportes?resumen=1')->assertOk()->assertJsonMissingPath('data.0.assignment_history')->assertJsonMissingPath('data.0.interventions');
    }
}
