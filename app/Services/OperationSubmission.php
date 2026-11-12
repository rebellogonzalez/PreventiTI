<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OperationSubmission
{
    /** Call inside the write transaction. Locking the actor also serializes a retry before the first insert. */
    public static function attributes(User $actor, ?string $id, array $payload): array
    {
        abort_unless(User::whereKey($actor->id)->lockForUpdate()->value('activo'), 403);
        if (! $id) return [];
        return [
            'submission_key' => hash('sha256', $actor->id.'/'.strtolower($id)),
            'submission_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
    }

    public static function previous(string $model, array $attributes): ?Model
    {
        if (! $attributes) return null;
        $previous = $model::where('submission_key', $attributes['submission_key'])->lockForUpdate()->first();
        abort_if($previous && $previous->submission_hash !== $attributes['submission_hash'], 409, 'El envío ya corresponde a otra operación.');
        return $previous;
    }
}
