<?php

namespace Tests\Feature;

use App\Models\TechnicalChief;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTechnicianTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_include_every_specialist_but_never_the_chief_even_when_a_specialist_is_inactive(): void
    {
        $this->seed();
        $inactive = User::findOrFail(5);
        $inactive->update(['activo' => false]);
        $chiefIds = TechnicalChief::query()->pluck('id')->all();

        foreach ([1, 4] as $viewerId) {
            $technicians = $this->actingAs(User::findOrFail($viewerId))
                ->getJson('/api/estadisticas')
                ->assertOk()
                ->assertJsonCount(6, 'data.technicians_ranking')
                ->json('data.technicians_ranking');

            $this->assertEmpty(array_intersect($chiefIds, array_column($technicians, 'id')));
            $this->assertSame(['tecnico'], array_values(array_unique(array_column($technicians, 'role'))));
            $this->assertSame(6, count(array_unique(array_column($technicians, 'id'))));
            $record = collect($technicians)->firstWhere('id', 5);
            $this->assertNotNull($record);
            $this->assertSame($inactive->nombre, $record['name']);
            $this->assertFalse($record['active']);
        }
    }
}
