<?php

namespace Tests\Feature;

use App\Models\CalendarTask;
use App\Models\User;
use App\Support\AccountPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_headers_and_legacy_sessions_never_authenticate(): void
    {
        $this->withHeader('X-User-Id', '1')->getJson('/api/auth/me')->assertUnauthorized();
        $this->withSession(['current_user_id' => 1])->getJson('/api/auth/me')->assertUnauthorized();
        $this->get('/')->assertRedirect('/login');
    }

    public function test_a_header_cannot_replace_an_authenticated_employee(): void
    {
        $this->actingAs(User::findOrFail(9))->withHeader('X-User-Id', '1')
            ->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.id', 9)->assertJsonPath('role', 'empleado');
        $this->getJson('/api/activos')->assertForbidden();
        $this->postJson('/api/auth/switch-user', ['user_id' => 1])->assertNotFound();
    }

    public function test_login_uses_individual_credentials_and_logout_revokes_access(): void
    {
        $employee = User::findOrFail(9);
        $password = AccountPassword::generate($employee->email);
        $employee->update(['password' => $password]);
        $this->postJson('/api/auth/login', ['email' => $employee->email, 'password' => 'incorrect'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => strtoupper($employee->email), 'password' => $password])
            ->assertOk()->assertJsonPath('user.id', 9)->assertJsonMissingPath('user.password');
        $this->getJson('/api/auth/me')->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_inactive_account_is_rejected_and_repeated_logins_are_limited(): void
    {
        $user = User::findOrFail(9);
        $user->update(['activo' => false, 'password' => 'DisabledAccount!123']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'DisabledAccount!123'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'DisabledAccount!123'])->assertStatus(429);
    }

    public function test_cookie_api_requires_csrf_token_outside_testing(): void
    {
        $this->app['env'] = 'local';
        $this->actingAs(User::findOrFail(9))->postJson('/api/reportes', [])->assertStatus(419);
        $this->withSession(['_token' => 'csrf-verification'])
            ->withHeader('X-CSRF-TOKEN', 'csrf-verification')->postJson('/api/reportes', [])->assertUnprocessable();
    }

    public function test_roles_cannot_read_other_roles_private_modules(): void
    {
        $matrix = [
            1 => ['activos' => 200, 'auth/users' => 200, 'estadisticas' => 200, 'infraestructura/status' => 200, 'notificaciones' => 403],
            4 => ['activos' => 200, 'auth/users' => 200, 'estadisticas' => 200, 'infraestructura/status' => 200, 'notificaciones' => 200],
            5 => ['activos' => 200, 'auth/users' => 403, 'estadisticas' => 403, 'infraestructura/status' => 403, 'notificaciones' => 403],
            9 => ['activos' => 403, 'auth/users' => 403, 'estadisticas' => 403, 'infraestructura/status' => 403, 'calendario' => 403, 'dashboard/semaforo' => 403, 'dashboard/today-tasks' => 403, 'dashboard/audit-logs' => 403, 'activos/categories' => 403, 'activos/locations' => 403],
        ];
        foreach ($matrix as $id => $endpoints) {
            $this->actingAs(User::findOrFail($id));
            foreach ($endpoints as $endpoint => $status) $this->getJson('/api/'.$endpoint)->assertStatus($status);
        }
        $this->getJson('/api/activos?incluir_historico=1')->assertForbidden();
        $response = $this->getJson('/api/reportes/activos-disponibles')->assertOk();
        $this->assertSame(['id', 'code', 'name', 'category_name', 'location_name', 'active'], array_keys($response->json('data.0')));
    }

    public function test_technician_calendar_is_own_only_and_cannot_schedule_for_others(): void
    {
        $task = CalendarTask::create(['fecha' => '2026-10-21', 'hora' => '09:00', 'titulo' => 'Tarea privada', 'tipo' => 'mantenimiento', 'technician_id' => 6]);
        $own = CalendarTask::create(['fecha' => '2026-10-21', 'hora' => '10:00', 'titulo' => 'Tarea propia', 'tipo' => 'mantenimiento', 'technician_id' => 5]);
        $response = $this->actingAs(User::findOrFail(5))->getJson('/api/calendario')->assertOk();
        foreach ($response->json('data.tasks') as $entry) $this->assertEquals(5, $entry['technician_id']);
        $this->postJson('/api/calendario', [])->assertForbidden();
        $this->patchJson('/api/calendario/'.$task->id.'/toggle')->assertForbidden();
        $this->patchJson('/api/calendario/'.$own->id.'/toggle')->assertOk();
    }

    public function test_issued_credentials_are_unique_match_active_accounts_and_revoke_sessions(): void
    {
        $path = sys_get_temp_dir().'/preventiti-credentials-test-'.bin2hex(random_bytes(6)).'.md';
        DB::table('sessions')->insert(['id' => 'obsolete', 'user_id' => 1, 'payload' => '', 'last_activity' => time()]);
        User::query()->update(['remember_token' => 'obsolete']);
        $inactive = User::findOrFail(9);
        $inactive->forceFill(['activo' => false, 'remember_token' => null])->save();
        try {
            $this->artisan('accounts:issue-credentials', ['output' => $path])->assertSuccessful();
            preg_match_all('/\| ([^|\s]+@[^|\s]+) \| `([^`]+)` \|/', file_get_contents($path), $matches, PREG_SET_ORDER);
            $this->assertCount(39, $matches);
            $this->assertStringNotContainsString($inactive->email, file_get_contents($path));
            $this->assertSame($inactive->password, $inactive->fresh()->password);
            $passwords = [];
            foreach ($matches as $match) {
                $user = User::where('email', $match[1])->firstOrFail();
                $this->assertTrue(Hash::check($match[2], $user->password));
                $this->assertStringStartsWith(ucfirst(explode('.', $match[1])[0]), $match[2]);
                $passwords[] = $match[2];
            }
            $this->assertCount(39, array_unique($passwords));
            $this->assertSame(0, DB::table('sessions')->count());
            $this->assertSame(0, User::whereNotNull('remember_token')->count());
        } finally {
            if (file_exists($path)) unlink($path);
        }
    }

    public function test_login_and_application_no_longer_expose_demo_identity_controls(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('fillDemo')->assertDontSee('Demostración')->assertDontSee('preventiti2026');
        $this->actingAs(User::findOrFail(9))->get('/')->assertOk()
            ->assertSee('laura.mendez@preventiti.local')->assertDontSee('topbar-user-selector')->assertDontSee('resetDemoData')
            ->assertDontSee('data-route="usuarios"', false)->assertDontSee('data-route="inventario"', false);
    }
}
