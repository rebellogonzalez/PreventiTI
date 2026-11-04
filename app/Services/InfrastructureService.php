<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class InfrastructureService
{
    public function status(BackupService $backups): array
    {
        $started = microtime(true);
        try {
            DB::select('SELECT 1');
            $database = ['available' => true, 'driver' => DB::getDriverName(), 'latency_ms' => round((microtime(true) - $started) * 1000, 1)];
        } catch (Throwable) {
            $database = ['available' => false, 'driver' => config('database.default'), 'latency_ms' => null];
        }
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        return [
            'measured_at' => now()->toIso8601String(),
            'server' => ['hostname' => gethostname() ?: 'No disponible', 'os' => PHP_OS_FAMILY, 'php' => PHP_VERSION],
            'database' => $database,
            'load_average' => $load ?: null,
            'php_memory_bytes' => memory_get_usage(true),
            'disk' => $total && $free !== false ? ['total_bytes' => $total, 'free_bytes' => $free, 'used_percent' => round(($total - $free) / $total * 100, 1)] : null,
            'network' => ['host' => config('infrastructure.scan_host'), 'ports' => config('infrastructure.scan_ports'), 'source' => 'Servidor de la aplicación'],
            'backup_support' => $backups->support(),
            'latest_backup' => $backups->latest(),
        ];
    }

    public function scan(): array
    {
        $host = (string) config('infrastructure.scan_host');
        $ports = config('infrastructure.scan_ports');
        if (! preg_match('/\A[a-zA-Z0-9.:-]+\z/', $host) || ! is_array($ports) || ! $ports || count($ports) > 8 || array_filter($ports, fn ($p) => ! is_int($p) || $p < 1 || $p > 65535)) {
            throw new RuntimeException('La configuración del destino TCP no es válida.');
        }
        $started = microtime(true);
        $results = array_map(fn (int $port) => $this->probe($host, $port), $ports);
        return ['host' => $host, 'source' => 'Servidor de la aplicación', 'measured_at' => now()->toIso8601String(), 'duration_ms' => round((microtime(true) - $started) * 1000), 'ports' => $results];
    }

    protected function probe(string $host, int $port): array
    {
        $address = str_contains($host, ':') ? '['.$host.']' : $host;
        $started = microtime(true);
        $socket = @stream_socket_client('tcp://'.$address.':'.$port, $errno, $error, min(3.0, max(0.1, (float) config('infrastructure.scan_timeout', 1))), STREAM_CLIENT_CONNECT);
        $reachable = is_resource($socket);
        if ($reachable) fclose($socket);
        return ['port' => $port, 'reachable' => $reachable, 'latency_ms' => round((microtime(true) - $started) * 1000, 1),
            'detail' => $reachable ? 'Conexión TCP establecida.' : 'No se pudo establecer la conexión TCP; no determina por sí solo si existe filtrado de firewall.'];
    }
}
