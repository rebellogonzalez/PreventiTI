<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetRetirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_retiring_and_restoring_a_failed_asset_preserves_its_condition_and_history(): void
    {
        $asset = Asset::findOrFail(1);
        $asset->update(['estado_activo' => 'Fuera de Servicio']);
        $before = [];
        foreach (['reportes', 'reporte_activo', 'mantenimientos', 'consumibles', 'usuarios'] as $table) $before[$table] = DB::table($table)->get()->toJson();
        $this->actingAs(User::findOrFail(1))->patchJson("/api/activos/{$asset->id}/toggle", ['expected_state' => 'Fuera de Servicio'])->assertOk();
        $this->assertDatabaseHas('activos', ['id' => $asset->id, 'estado_activo' => 'Retirado', 'estado_previo_retiro' => 'Fuera de Servicio']);
        $this->patchJson("/api/activos/{$asset->id}/toggle", ['expected_state' => 'Retirado'])->assertOk();
        $this->assertDatabaseHas('activos', ['id' => $asset->id, 'estado_activo' => 'Fuera de Servicio', 'estado_previo_retiro' => null]);
        $this->assertFalse($asset->fresh()->retirado);
        $this->assertFalse($asset->fresh()->activo);
        foreach ($before as $table => $rows) $this->assertSame($rows, DB::table($table)->get()->toJson(), $table);
    }

    public function test_operational_and_maintenance_states_are_restored_exactly(): void
    {
        $this->actingAs(User::findOrFail(1));
        foreach (['Operativo', 'En Mantenimiento'] as $state) {
            $asset = Asset::where('estado_activo', $state)->firstOrFail();
            $this->patchJson("/api/activos/{$asset->id}/toggle", ['expected_state' => $state])->assertOk();
            $this->patchJson("/api/activos/{$asset->id}/toggle", ['expected_state' => 'Retirado'])->assertOk();
            $this->assertSame($state, $asset->fresh()->estado_activo);
        }
    }

    public function test_a_stale_or_repeated_request_does_not_reverse_the_previous_action(): void
    {
        $this->actingAs(User::findOrFail(1));
        $this->patchJson('/api/activos/1/toggle', ['expected_state' => 'Operativo'])->assertOk();
        $this->patchJson('/api/activos/1/toggle', ['expected_state' => 'Operativo'])->assertStatus(409);
        $this->assertDatabaseHas('activos', ['id' => 1, 'estado_activo' => 'Retirado', 'estado_previo_retiro' => 'Operativo']);
    }

    public function test_old_retirement_without_a_recorded_condition_does_not_assume_the_asset_works(): void
    {
        Asset::findOrFail(1)->update(['estado_activo' => 'Retirado']);
        $this->actingAs(User::findOrFail(1))->patchJson('/api/activos/1/toggle', ['expected_state' => 'Retirado'])->assertOk();
        $this->assertDatabaseHas('activos', ['id' => 1, 'estado_activo' => 'Fuera de Servicio']);
    }

    public function test_specialists_cannot_change_retirement_status(): void
    {
        $this->actingAs(User::findOrFail(5))->patchJson('/api/activos/1/toggle')->assertForbidden();
        $this->assertDatabaseHas('activos', ['id' => 1, 'estado_activo' => 'Operativo']);
    }
}
