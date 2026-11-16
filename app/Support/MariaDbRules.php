<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class MariaDbRules
{
    public static function enabled(): bool
    {
        return in_array(DB::getDriverName(), ['mariadb', 'mysql'], true);
    }

    public static function install(): void
    {
        $version = DB::selectOne('SELECT VERSION() AS version')->version;
        if (! preg_match('/^10\.11\..*MariaDB/', $version)) {
            throw new RuntimeException('Las reglas de PreventiTI requieren MariaDB 10.11.x.');
        }
        // migrate:fresh removes tables/triggers, but stored procedures survive.
        foreach (['asignar_tecnico', 'cambiar_estado_reporte', 'validar_asignacion_vigente'] as $routine) {
            DB::unprepared("DROP PROCEDURE IF EXISTS `{$routine}`");
        }
        [$schema, $routines] = explode('DELIMITER //', file_get_contents(database_path('sql/mariadb_rules.sql')), 2);
        foreach (preg_split('/;\s*(?=\r?\n|$)/', $schema) as $statement) {
            if (trim($statement) !== '') DB::unprepared(trim($statement));
        }
        $routines = str_replace('DELIMITER ;', '', $routines);
        foreach (explode('//', $routines) as $statement) {
            if (trim($statement) !== '') DB::unprepared(trim($statement));
        }
    }
}
