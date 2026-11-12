<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Location;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FloorplanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_map_uses_current_inventory_and_preserves_locations(): void
    {
        $data = $this->actingAs(\App\Models\User::findOrFail(1))->getJson('/api/floorplan')->assertOk()->json('data');
        $this->assertCount(68, $data['assets']);
        $this->assertCount(8, $data['rooms']);
        $this->assertEquals(Asset::inInventory()->orderBy('id')->pluck('id')->all(), array_column($data['assets'], 'id'));
        $leaderNotebook = collect($data['assets'])->firstWhere('id', 30);
        $this->assertSame('management', $leaderNotebook['room_id']);
        $this->assertNull($leaderNotebook['position']);
        $this->assertTrue($data['can_edit_positions']);
    }

    public function test_missing_and_new_locations_never_drop_assets(): void
    {
        Location::where('activo_id', 1)->update(['sala' => 'Nueva sala']);
        Location::where('activo_id', 2)->delete();
        Asset::findOrFail(2)->update(['ubicacion' => null]);
        $data = $this->actingAs(\App\Models\User::findOrFail(1))->getJson('/api/floorplan')->assertOk()->json('data');
        $this->assertCount(68, $data['assets']);
        $this->assertCount(10, $data['rooms']);
        $this->assertCount(2, array_filter($data['rooms'], fn ($r) => $r['unconfigured'] ?? false));
    }

    public function test_map_requires_inventory_permission(): void
    {
        $this->getJson('/api/floorplan')->assertUnauthorized();
        $this->actingAs(\App\Models\User::findOrFail(9))->getJson('/api/floorplan')->assertForbidden();
    }

    public function test_position_is_validated_saved_and_invalidated_when_inventory_room_changes(): void
    {
        $location = Location::findOrFail(1);
        $position = ['x' => .3, 'z' => .5, 'elevation' => 0, 'rotation' => 90, 'floor' => $location->piso, 'room' => $location->sala];
        $before = Asset::findOrFail(1)->getAttributes();
        $this->actingAs(\App\Models\User::findOrFail(1))->patchJson('/api/floorplan/assets/1/position', $position)->assertOk();
        $this->assertDatabaseHas('asset_map_positions', ['activo_id' => 1, 'x' => .3, 'rotation' => 90]);
        $this->assertEquals($before, Asset::findOrFail(1)->getAttributes());
        $asset = collect($this->getJson('/api/floorplan')->json('data.assets'))->firstWhere('id', 1);
        $this->assertSame('confirmed', $asset['position']['source']);
        $this->patchJson('/api/floorplan/assets/1/position', array_merge($position, ['x' => 100]))->assertUnprocessable();
        $location->update(['sala' => 'Otra sala']);
        $asset = collect($this->getJson('/api/floorplan')->json('data.assets'))->firstWhere('id', 1);
        $this->assertTrue($asset['position_stale']);
        $this->assertNull($asset['position']);
        $this->patchJson('/api/floorplan/assets/1/position', $position)->assertConflict();
        $this->assertSame(69, Asset::count());
    }

    public function test_technical_leader_can_read_but_cannot_change_positions(): void
    {
        $this->actingAs(\App\Models\User::findOrFail(4))->getJson('/api/floorplan')->assertOk()->assertJsonPath('data.can_edit_positions', false);
        $this->patchJson('/api/floorplan/assets/1/position', [])->assertForbidden();
        $this->assertSame(0, DB::table('asset_map_positions')->count());
    }

    public function test_monitoring_uses_live_reports_and_finalized_reports_stop_counting(): void
    {
        $report = Report::firstOrFail();
        $asset = $report->activos()->firstOrFail();
        $data = $this->actingAs(\App\Models\User::findOrFail(1))->getJson('/api/floorplan')->json('data.assets');
        $this->assertGreaterThan(0, collect($data)->firstWhere('id', $asset->id)['active_incidents']);
        $asset->reportes()->update(['estado_reporte' => 'Finalizado']);
        $data = $this->getJson('/api/floorplan')->json('data.assets');
        $this->assertSame(0, collect($data)->firstWhere('id', $asset->id)['active_incidents']);
        $this->assertSame([], collect($data)->firstWhere('id', $asset->id)['reports']);
        $asset->update(['estado_activo' => 'Desconectado']);
        $data = $this->getJson('/api/floorplan')->json('data.assets');
        $this->assertSame('disconnected', collect($data)->firstWhere('id', $asset->id)['operational_status']);
    }

    public function test_report_descriptions_respect_existing_report_permissions(): void
    {
        $data = $this->actingAs(\App\Models\User::findOrFail(5))->getJson('/api/floorplan')->assertOk()->json('data.assets');
        foreach ($data as $asset) {
            foreach ($asset['reports'] as $report) {
                $this->assertTrue(Report::findOrFail($report['id'])->creadores()->whereKey(5)->exists());
            }
        }
    }
}
