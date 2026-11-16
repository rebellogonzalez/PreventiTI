<?php

// Ejecutar con una cuenta administrativa, antes de habilitar tráfico en el destino.
// No imprime datos: únicamente conteos y huellas de contenido y esquema.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    if (DB::getDriverName() !== 'mariadb') throw new RuntimeException('Requiere MariaDB y una cuenta que pueda inspeccionar triggers y rutinas.');
    $version = DB::selectOne('SELECT VERSION() AS version')->version;
    if (! str_starts_with($version, '10.11.')) throw new RuntimeException('Requiere MariaDB 10.11.x.');
    DB::statement('SET TRANSACTION READ ONLY');
    DB::beginTransaction();
    try {
        $tables = [];
        foreach (DB::select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=\'BASE TABLE\' ORDER BY TABLE_NAME') as $table) {
            if (! preg_match('/\A[A-Za-z0-9_]+\z/', $table->name)) throw new RuntimeException('Nombre de tabla no permitido.');
            $rows = [];
            foreach (DB::table($table->name)->get() as $row) {
                $row = (array) $row;
                ksort($row);
                $rows[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            }
            sort($rows, SORT_STRING);
            $tables[$table->name] = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
        }
        $schema = [];
        $schema['columns'] = DB::select('SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION');
        $schema['indexes'] = DB::select('SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,INDEX_TYPE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX');
        $schema['foreign_keys'] = DB::select('SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION');
        $schema['triggers'] = DB::select('SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_TIMING,ACTION_STATEMENT,DEFINER FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME');
        $schema['routines'] = DB::select('SELECT ROUTINE_NAME,ROUTINE_TYPE,ROUTINE_DEFINITION,SECURITY_TYPE,DEFINER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() ORDER BY ROUTINE_NAME');
        if (count($tables) !== 32 || count($schema['triggers']) !== 10 || count($schema['routines']) !== 3) throw new RuntimeException('Esquema incompleto o cuenta sin acceso a los metadatos administrativos.');
        if (collect($schema['routines'])->contains(fn ($routine) => $routine->ROUTINE_DEFINITION === null)) throw new RuntimeException('No se pueden inspeccionar las rutinas con esta cuenta.');
        // Guardar huellas, no las definiciones ni los datos privados.
        $schema = array_map(fn ($items) => ['count' => count($items), 'sha256' => hash('sha256', json_encode($items, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))], $schema);
        $manifest = ['format' => 1, 'server_version' => $version, 'tables' => $tables, 'schema' => $schema];
    } finally {
        DB::rollBack();
    }
    if (($argv[1] ?? null) === '--expected') {
        $expected = json_decode(file_get_contents($argv[2] ?? ''), true, flags: JSON_THROW_ON_ERROR);
        foreach (['tables', 'schema'] as $section) {
            if ($expected[$section] !== $manifest[$section]) {
                $different = array_keys(array_filter($manifest[$section], fn ($value, $key) => ($expected[$section][$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));
                throw new RuntimeException('Diferencias en '.$section.': '.implode(', ', $different));
            }
        }
        echo json_encode(['verified' => true, 'tables' => count($tables), 'rows' => array_sum(array_column($tables, 'rows')), 'triggers' => 10, 'routines' => 3], JSON_THROW_ON_ERROR).PHP_EOL;
    } else {
        echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
