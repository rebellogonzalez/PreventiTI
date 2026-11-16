<?php

namespace App\Services;

use App\Models\User;
use App\Support\MariaDbRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDOException;

class DatabaseWorkflow
{
    public static function enabled(): bool
    {
        return MariaDbRules::enabled();
    }

    public static function context(?User $actor, ?string $reason, callable $operation): mixed
    {
        if (! self::enabled()) return $operation();
        $previous = DB::selectOne('SELECT @preventiti_usuario_id AS actor, @preventiti_motivo AS reason, @preventiti_momento AS moment');
        DB::statement('SET @preventiti_usuario_id = ?, @preventiti_motivo = ?, @preventiti_momento = ?', [
            $actor?->id, $reason, now()->utc()->format('Y-m-d H:i:s.u'),
        ]);
        try {
            return $operation();
        } finally {
            DB::statement('SET @preventiti_usuario_id = ?, @preventiti_motivo = ?, @preventiti_momento = ?', [$previous->actor, $previous->reason, $previous->moment]);
        }
    }

    public static function call(string $routine, array $parameters, string $field): void
    {
        if (! in_array($routine, ['asignar_tecnico', 'cambiar_estado_reporte'], true)) {
            throw new \InvalidArgumentException('Rutina no permitida.');
        }
        $statement = DB::connection()->getPdo()->prepare('CALL '.$routine.'('.implode(',', array_fill(0, count($parameters), '?')).')');
        try {
            $statement->execute($parameters);
            while ($statement->nextRowset()) {
                // Drain the CALL result sets before Eloquent issues another query.
            }
        } catch (PDOException $error) {
            if (($error->errorInfo[0] ?? $error->getCode()) === '45000') {
                $message = $error->errorInfo[2] ?? 'La base rechazó la operación.';
                $text = mb_strtolower($message);
                $field = match (true) {
                    str_contains($text, 'diagnóstico requiere'), str_contains($text, 'diagnóstico informado') => 'final_diagnosis',
                    str_contains($text, 'informe de accionar'), str_contains($text, 'el informe debe') => 'action_report',
                    str_contains($text, 'reasignación requiere motivo') => 'reassignment_reason',
                    default => $field,
                };
                throw ValidationException::withMessages([$field => $message]);
            }
            throw $error;
        } finally {
            $statement->closeCursor();
        }
    }
}
