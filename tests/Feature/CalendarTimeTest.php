<?php

namespace Tests\Feature;

use App\Models\CalendarTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_accepts_only_24_hour_times_and_lists_legacy_times_chronologically(): void
    {
        $this->seed();
        $this->actingAs(User::findOrFail(4));
        $task = ['fecha' => '2030-06-01', 'titulo' => 'Revisión técnica', 'tipo' => 'mantenimiento', 'technician_id' => 5];

        foreach (['23:59', '00:00', '12:00'] as $time) {
            $this->postJson('/api/calendario', [...$task, 'hora' => $time])
                ->assertCreated()->assertJsonPath('data.hora', $time);
        }
        foreach (['24:00', '09:60', '3:15 PM', '9:05', ''] as $invalid) {
            $this->postJson('/api/calendario', [...$task, 'hora' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('hora');
        }
        foreach (['3:15 PM', '12:05 a.m.', '7:20', '13:02:30', 'sin hora'] as $legacy) {
            CalendarTask::create([...$task, 'hora' => $legacy]);
        }

        $expected = ['00:00', '00:05', '07:20', '12:00', '13:02', '15:15', '23:59', null];
        $dailyTasks = $this->getJson('/api/dashboard/today-tasks?date='.$task['fecha'])->assertOk()->json('data');
        $calendar = $this->getJson('/api/calendario')->assertOk()->json('data.tasks');
        $this->assertSame($expected, array_column($dailyTasks, 'hora'));
        $this->assertSame($expected, collect($calendar)->where('date', $task['fecha'])->pluck('time')->all());
    }
}
