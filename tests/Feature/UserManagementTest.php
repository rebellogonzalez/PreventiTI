<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Report;
use App\Models\TechnicalChief;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function account(string $role): array
    {
        return [
            'nombre' => 'Andrea', 'apellido' => 'De León', 'email' => $role.'@example.test',
            'password' => 'Clave inicial 123', 'role' => $role, 'specialty' => 'Software',
        ];
    }

    public function test_both_managers_can_create_and_disable_all_four_roles(): void
    {
        foreach ([1, 4] as $actorId) {
            $this->actingAs(User::findOrFail($actorId))->get('/')->assertOk()->assertSee('data-route="usuarios"', false);
            foreach (['empleado', 'tecnico', 'tecnico_lider', 'administrador'] as $role) {
                $data = $this->account($role);
                $data['email'] = strtoupper($actorId.$data['email']);
                $id = $this->postJson('/api/auth/users', $data)->assertCreated()->assertJsonMissingPath('data.password')->json('data.id');
                $user = User::findOrFail($id);
                $this->assertSame($role, $user->roleName());
                $this->assertSame('Andrea De León', $user->nombre);
                $this->assertSame(mb_strtolower($data['email']), $user->email);
                $this->assertTrue(Hash::check($data['password'], $user->password));
                if (in_array($role, ['tecnico', 'tecnico_lider'], true)) {
                    $this->getJson('/api/auth/task-assignees')->assertOk()->assertJsonFragment(['id' => $id]);
                    $this->assertSame('Software', $user->especialidad);
                }
                $this->deleteJson('/api/auth/users/'.$id)->assertOk();
                $this->deleteJson('/api/auth/users/'.$id)->assertOk();
                $this->assertFalse($user->fresh()->activo);
                $this->assertSame(1, DB::table('auditorias')->where('descripcion', "Baja de cuenta {$id} por usuario {$actorId}.")->count());
                $this->getJson('/api/auth/task-assignees')->assertOk()->assertJsonMissing(['id' => $id]);
            }
        }
    }

    public function test_employee_specialist_and_anonymous_requests_cannot_manage_accounts(): void
    {
        $data = $this->account('administrador');
        $this->postJson('/api/auth/users', $data)->assertUnauthorized();
        $this->deleteJson('/api/auth/users/1')->assertUnauthorized();
        foreach ([9, 5] as $actorId) {
            $this->actingAs(User::findOrFail($actorId));
            $this->getJson('/api/auth/users')->assertForbidden();
            $this->postJson('/api/auth/users', $data)->assertForbidden();
            $this->deleteJson('/api/auth/users/1')->assertForbidden();
            $this->get('/')->assertDontSee('data-route="usuarios"', false);
        }
        $this->assertTrue(User::findOrFail(1)->activo);
        $this->assertDatabaseMissing('usuarios', ['email' => $data['email']]);
    }

    public function test_duplicate_email_invalid_role_and_missing_specialty_are_rejected(): void
    {
        $this->actingAs(User::findOrFail(4));
        $data = $this->account('tecnico');
        $this->postJson('/api/auth/users', [...$data, 'email' => strtoupper(User::findOrFail(1)->email)])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/auth/users', [...$data, 'role' => 'otro'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/api/auth/users', [...$data, 'specialty' => ''])->assertUnprocessable()->assertJsonValidationErrors('specialty');
        $this->assertDatabaseCount('usuarios', 40);
        $this->deleteJson('/api/auth/users/4')->assertUnprocessable();
        $this->assertTrue(User::findOrFail(4)->activo);
    }

    public function test_new_chief_can_log_in_and_a_disabled_chief_loses_access_but_retains_history(): void
    {
        $data = $this->account('tecnico_lider');
        $id = $this->actingAs(User::findOrFail(1))->postJson('/api/auth/users', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/login', ['email' => $data['email'], 'password' => $data['password']])
            ->assertOk()->assertJsonPath('role', 'tecnico_lider');
        $oldChief = User::findOrFail(4);
        $oldChief->forceFill(['remember_token' => 'old-token', 'password' => 'Clave anterior 123'])->save();
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => 4, 'payload' => '', 'last_activity' => time()]);
        $finished = Report::where('jefe_id', 4)->firstOrFail();
        $reports = app(\App\Services\ReportService::class);
        $reports->assignTechnician($finished, $oldChief, 5, null);
        $reports->transition($finished->fresh(), $oldChief, 'Finalizado', 'Solución registrada antes de la baja.', null);
        $openIds = Report::open()->where('jefe_id', 4)->pluck('id');
        $this->assertNotEmpty($openIds);
        $this->deleteJson('/api/auth/users/4')->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertNull($oldChief->fresh()->remember_token);
        $this->assertEquals(4, $finished->fresh()->jefe_id);
        $this->assertEquals($openIds->count(), Report::whereKey($openIds)->where('jefe_id', $id)->count());
        $this->assertSame($id, TechnicalChief::firstActiveId());
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/login', ['email' => $oldChief->email, 'password' => 'Clave anterior 123'])->assertUnauthorized();
    }

    public function test_vacant_chief_receives_open_reports_when_a_new_chief_joins(): void
    {
        $this->actingAs(User::findOrFail(1))->deleteJson('/api/auth/users/4')->assertOk();
        $this->assertNull(TechnicalChief::firstActiveId());
        $reportId = $this->postJson('/api/reportes', [
            'asset_id' => 1, 'priority' => 'Media', 'description' => 'Revisión del dispositivo durante la vacante.',
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('reportes', ['id' => $reportId, 'jefe_id' => null]);
        $chief = $this->postJson('/api/auth/users', $this->account('tecnico_lider'))->assertCreated()->json('data.id');
        $this->assertSame(0, Report::open()->whereNull('jefe_id')->count());
        $this->assertDatabaseHas('reportes', ['id' => $reportId, 'jefe_id' => $chief]);
    }

    public function test_staff_turnover_releases_open_work_and_preserves_the_history_of_finished_work(): void
    {
        $chief = User::findOrFail(4);
        $this->actingAs($chief);
        $revision = $this->getJson('/api/auth/staff-revision')->assertOk()->json('staff_revision');
        $id = $this->postJson('/api/auth/users', $this->account('tecnico'))->assertCreated()->json('data.id');
        $afterHiring = $this->getJson('/api/auth/staff-revision')->assertOk()->json('staff_revision');
        $this->assertNotSame($revision, $afterHiring);
        $this->getJson('/api/reportes/tecnicos')->assertOk()->assertJsonFragment(['id' => $id]);
        $date = '2030-06-03';
        $task = ['fecha' => $date, 'hora' => '17:30', 'titulo' => 'Revisión técnica', 'tipo' => 'mantenimiento', 'technician_id' => $id];
        $pending = $this->postJson('/api/calendario', $task)->assertCreated()->json('data.id');
        $done = $this->postJson('/api/calendario', $task)->assertCreated()->json('data.id');
        $this->patchJson("/api/calendario/{$done}/toggle")->assertOk();
        $report = ['asset_id' => 1, 'priority' => 'Alta', 'description' => 'El equipo requiere revisión de su conexión.'];
        $this->actingAs(User::findOrFail($id));
        $open = $this->postJson('/api/reportes', $report)->assertCreated()->json('data.id');
        $closed = $this->postJson('/api/reportes', $report)->assertCreated()->json('data.id');
        $this->actingAs($chief);
        foreach ([$open, $closed] as $reportId) {
            $this->patchJson("/api/reportes/{$reportId}/asignacion", ['technician_id' => $id])
                ->assertOk()->assertJsonPath('data.status', 'En curso');
        }
        $finish = ['status' => 'Finalizado', 'action_report' => 'Conexión reparada.'];
        $this->patchJson("/api/reportes/{$closed}/estado", $finish)->assertOk();
        $this->deleteJson('/api/auth/users/'.$id)->assertOk()->assertJsonPath('data.tasks', 1)->assertJsonPath('data.reports', 1);
        $this->assertNotSame($afterHiring, $this->getJson('/api/auth/staff-revision')->assertOk()->json('staff_revision'));
        $this->getJson('/api/reportes/tecnicos')->assertJsonMissing(['id' => $id]);
        $this->getJson("/api/reportes/{$open}")->assertOk()->assertJsonPath('data.assigned_technician', null)
            ->assertJsonPath('data.status', 'En curso')->assertJsonPath('data.creator_id', $id)
            ->assertJsonPath('data.assignment_history.0.reassignment_reason', 'Baja de la cuenta del técnico.');
        $this->getJson("/api/reportes/{$closed}")->assertJsonPath('data.assigned_technician', null)->assertJsonPath('data.last_technician.id', $id)->assertJsonPath('data.creator_id', $id);
        $this->patchJson("/api/reportes/{$open}/estado", $finish)->assertUnprocessable();
        $this->patchJson("/api/reportes/{$open}/asignacion", ['technician_id' => $id])->assertUnprocessable();
        $this->patchJson("/api/reportes/{$open}/asignacion", ['technician_id' => 5])->assertOk();
        $this->patchJson("/api/reportes/{$open}/estado", $finish)->assertOk();
        $this->assertDatabaseHas('tareas_calendario', ['id' => $done, 'completed' => true, 'technician_id' => $id]);
        $this->assertDatabaseHas('tareas_calendario', ['id' => $pending, 'completed' => false, 'technician_id' => null, 'technician_name' => null]);
        $ranking = collect($this->getJson('/api/estadisticas')->assertOk()->json('data.technicians_ranking'))->firstWhere('id', $id);
        $this->assertFalse($ranking['active']);
        $this->assertSame(0, $ranking['maintenances']);
        $this->patchJson("/api/calendario/{$pending}/toggle")->assertUnprocessable();
        $this->postJson('/api/calendario', $task)->assertUnprocessable();
        $this->patchJson("/api/calendario/{$pending}/asignacion", ['technician_id' => $id])->assertUnprocessable();
        $this->patchJson("/api/calendario/{$done}/asignacion", ['technician_id' => 5])->assertConflict();
        $this->actingAs(User::findOrFail(5))->patchJson("/api/calendario/{$pending}/asignacion", ['technician_id' => 5])->assertForbidden();
        $this->actingAs(User::findOrFail(1))->patchJson("/api/calendario/{$pending}/asignacion", ['technician_id' => 5])->assertOk();
        $this->patchJson("/api/calendario/{$pending}/asignacion", ['technician_id' => 6])->assertConflict();
        $this->patchJson("/api/calendario/{$done}/toggle", ['completed' => false])->assertOk()->assertJsonPath('data.technician_id', null);
        $this->actingAs(User::findOrFail(5))->getJson('/api/dashboard/today-tasks?date='.$date)->assertJsonFragment(['id' => $pending])->assertJsonMissing(['id' => $done]);
    }

    public function test_departing_asset_owner_is_released_by_id_even_when_two_people_share_a_name(): void
    {
        $this->actingAs(User::findOrFail(1));
        $person = $this->account('empleado');
        $first = $this->postJson('/api/auth/users', $person)->assertCreated()->json('data.id');
        $second = $this->postJson('/api/auth/users', [...$person, 'email' => 'otra@example.test'])->assertCreated()->json('data.id');
        $device = ['categoria_id' => 1, 'ubicacion_id' => 1, 'nombre' => 'Terminal de pruebas', 'responsable' => 'Andrea De León'];
        $this->postJson('/api/activos', [...$device, 'serie' => 'AMBIGUA'])->assertUnprocessable();
        $make = fn ($serial, $id) => $this->postJson('/api/activos', [...$device, 'serie' => $serial, 'responsable_id' => $id])
            ->assertCreated()->json('data.id');
        $live = $make('TRABAJANDO', $first);
        $archived = $make('RETIRADO', $first);
        $other = $make('HOMONIMO', $second);
        $this->deleteJson('/api/activos/'.$archived)->assertOk();
        $this->deleteJson('/api/auth/users/'.$first)->assertOk()->assertJsonPath('data.assets', 1);
        $this->assertSame('No asignado', Asset::findOrFail($live)->responsable);
        foreach (['/api/activos' => 'data', '/api/floorplan' => 'data.assets'] as $endpoint => $key) {
            $assets = collect($this->getJson($endpoint)->assertOk()->json($key))->keyBy('id');
            $this->assertSame('No asignado', $assets[$live]['responsible']);
            $this->assertNull($assets[$live]['responsible_id']);
            $this->assertSame('Andrea De León', $assets[$other]['responsible']);
            $this->assertEquals($second, $assets[$other]['responsible_id']);
        }
        $this->assertDatabaseHas('activos', ['id' => $other, 'responsable_id' => $second]);
        $this->assertDatabaseHas('activos', ['id' => $archived, 'responsable_id' => $first]);
        $this->postJson('/api/activos', [...$device, 'serie' => 'INACTIVO', 'responsable_id' => $first])->assertUnprocessable();
        $this->patchJson("/api/activos/{$archived}/toggle", ['expected_state' => 'Retirado'])->assertOk();
        $this->assertDatabaseHas('activos', ['id' => $archived, 'responsable_id' => null]);
    }

    public function test_migration_handles_accounts_disabled_before_work_was_released(): void
    {
        $this->actingAs(User::findOrFail(4));
        $task = $this->postJson('/api/calendario', [
            'fecha' => '2030-06-04', 'hora' => '08:30', 'titulo' => 'Revisión pendiente', 'tipo' => 'mantenimiento', 'technician_id' => 5,
        ])->assertCreated()->json('data.id');
        $open = Report::open()->firstOrFail();
        $this->patchJson("/api/reportes/{$open->id}/asignacion", ['technician_id' => 5])->assertOk();
        $asset = Asset::where('responsable_id', 5)->firstOrFail();
        DB::table('activos')->where('id', $asset->id)->update(['responsable_id' => null]);
        User::whereKey(5)->update(['activo' => false]);

        $migration = require database_path('migrations/2026_11_09_000001_link_asset_owners_and_release_inactive_staff.php');
        $migration->up();
        $this->assertSame('No asignado', $asset->fresh()->responsable);
        $this->assertDatabaseHas('tareas_calendario', ['id' => $task, 'technician_id' => null]);
        $this->getJson("/api/reportes/{$open->id}")->assertJsonPath('data.assigned_technician', null)
            ->assertJsonPath('data.assignment_history.0.technician_id', 5);
    }
}
