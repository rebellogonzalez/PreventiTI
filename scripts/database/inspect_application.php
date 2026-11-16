<?php

// Solo lectura: contrasta respuestas y manifiestos entre los dos motores.
use App\Models\User;
use App\Services\SimulationSnapshot;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->instance('request', Request::create('/'));
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
config(['session.driver' => 'array', 'cache.default' => 'array', 'app.debug' => false]);
if (DB::getDriverName() === 'sqlite') DB::statement('PRAGMA query_only = ON');
else DB::statement('SET TRANSACTION READ ONLY');
DB::beginTransaction();
try {
    $manifests = [];
    $snapshot = app(SimulationSnapshot::class);
    foreach (DB::table('simulation_runs')->orderBy('id')->get() as $run) {
        foreach (json_decode($run->manifest, true, flags: JSON_THROW_ON_ERROR) as $entry) {
            try {
                $hash = $snapshot->record($entry['table'], (int) $entry['id'])['hash'];
            } catch (RuntimeException $error) {
                $hash = 'missing';
            }
            $manifests[$run->id.':'.$entry['table'].':'.$entry['id']] = ['hash' => $hash, 'unchanged' => $hash === $entry['hash']];
        }
    }
    $responses = [];
    foreach ([1, 4, 5, 9] as $user) {
        Auth::setUser(User::findOrFail($user));
        foreach (['/api/auth/me', '/api/activos', '/api/reportes', '/api/reportes/historial', '/api/floorplan', '/api/dashboard/stats', '/api/mantenimientos', '/api/consumibles', '/api/calendario', '/api/estadisticas'] as $path) {
            $response = $kernel->handle(Request::create($path, 'GET', server: ['HTTP_ACCEPT' => 'application/json']));
            $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
            // La hora de emisión del plano no es información almacenada.
            if ($path === '/api/floorplan') unset($data['data']['updated_at']);
            $responses[$user.':'.$path] = ['status' => $response->getStatusCode(), 'hash' => hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))];
        }
    }
    echo json_encode(['driver' => DB::getDriverName(), 'manifests' => $manifests, 'responses' => $responses], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    DB::rollBack();
}
