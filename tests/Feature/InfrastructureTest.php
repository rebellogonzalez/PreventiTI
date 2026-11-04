<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\User;
use App\Services\BackupService;
use App\Services\InfrastructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['infrastructure.backup_directory' => sys_get_temp_dir().'/preventiti-empty-test-'.bin2hex(random_bytes(5))]);
    }

    public function test_status_describes_the_actual_runtime_and_does_not_claim_firewall_or_vpn_health(): void
    {
        $response = $this->actingAs(User::findOrFail(1))->getJson('/api/infraestructura/status')->assertOk();
        $response->assertJsonPath('data.server.php', PHP_VERSION)->assertJsonPath('data.server.os', PHP_OS_FAMILY)
            ->assertJsonPath('data.database.available', true)->assertJsonPath('data.database.driver', 'sqlite')
            ->assertJsonPath('data.backup_support.available', false)->assertJsonPath('data.latest_backup', null);
        $this->assertArrayNotHasKey('vpn', $response->json('data'));
        $this->assertArrayNotHasKey('firewall', $response->json('data'));
    }

    public function test_scan_delegates_to_measurement_and_rejects_client_supplied_targets_or_vlan_origins(): void
    {
        $this->mock(InfrastructureService::class, function ($mock) {
            $mock->shouldReceive('scan')->once()->andReturn(['host' => '127.0.0.1', 'source' => 'Servidor de la aplicación', 'ports' => [['port' => 8000, 'reachable' => false]]]);
        });
        $this->actingAs(User::findOrFail(4))->postJson('/api/infraestructura/scan')->assertOk()->assertJsonPath('data.ports.0.reachable', false);
        foreach (['origin' => 'vlan10', 'host' => 'example.com', 'ports' => [22]] as $key => $value) {
            $this->postJson('/api/infraestructura/scan', [$key => $value])->assertUnprocessable();
        }
    }

    public function test_failed_backup_returns_an_error_without_inventing_a_successful_audit_record(): void
    {
        $before = Audit::count();
        $this->mock(BackupService::class, fn ($mock) => $mock->shouldReceive('create')->once()->andThrow(new RuntimeException('No hay espacio para la copia.')));
        $this->actingAs(User::findOrFail(1))->postJson('/api/infraestructura/drp-backup')->assertStatus(503)->assertJsonPath('success', false);
        $this->assertSame($before, Audit::count());
    }

    public function test_specialists_cannot_scan_create_or_download_private_backups(): void
    {
        $this->actingAs(User::findOrFail(5));
        $this->postJson('/api/infraestructura/scan')->assertForbidden();
        $this->postJson('/api/infraestructura/drp-backup')->assertForbidden();
        $this->getJson('/api/infraestructura/backups/preventiti_20261022_120000_abcdef123456.zip/download')->assertForbidden();
    }

    public function test_login_and_application_load_local_dependencies(): void
    {
        $this->get('/login')->assertOk()->assertSee('css/tailwind.css')->assertSee('css/fonts.css')->assertDontSee('cdn.tailwindcss.com')->assertDontSee('fonts.googleapis.com')->assertDontSee('unpkg.com');
        $this->actingAs(User::findOrFail(1))->get('/')->assertOk()->assertSee('js/vendor/chart/chart-4.6.1.min.js')->assertDontSee('cdn.jsdelivr.net')->assertDontSee('unpkg.com');
    }
}
