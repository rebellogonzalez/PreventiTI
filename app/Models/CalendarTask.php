<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CalendarTask extends Model
{
    protected $table = 'tareas_calendario';

    protected $fillable = [
        'fecha',
        'hora',
        'titulo',
        'tipo',
        'category',
        'technician_id',
        'technician_name',
        'asset_id',
        'asset_code',
        'priority',
        'completed', 'reporte_id', 'cancel_reason', 'revision', 'submission_key', 'submission_hash',
    ];

    protected $hidden = ['submission_key', 'submission_hash'];

    public function inspection(): HasOne
    {
        return $this->hasOne(Maintenance::class, 'task_id');
    }

    public function authorizeExecution(User $actor): void
    {
        abort_unless($actor->hasHierarchyAtLeast(2) || (int) $this->technician_id === (int) $actor->id, 403);
        abort_if($this->cancel_reason !== null, 409, 'La tarea fue cancelada.');
    }

    public function complete(User $actor): void
    {
        abort_unless($this->technician_id && User::whereKey($this->technician_id)->sharedLock()->value('activo'), 422, 'Asigne un técnico activo.');
        $this->update(['completed' => true, 'revision' => $this->revision + 1]);
        Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Tarea {$this->id} completada por {$actor->nombre} (cuenta {$actor->id})."]);
    }

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'completed' => 'boolean',
        ];
    }

    // Older tasks could store an unpadded hour, seconds or an AM/PM suffix.
    public function getHoraAttribute(?string $value): ?string
    {
        if (! preg_match('/^(\d{1,2}):([0-5]\d)(?::[0-5]\d)?(?:\s*([ap])\.?\s*m\.?)?$/iD', trim($value ?? ''), $parts)) {
            return null;
        }

        $hour = (int) $parts[1];
        if (isset($parts[3])) {
            if ($hour < 1 || $hour > 12) return null;
            $hour = $hour % 12 + (strtolower($parts[3]) === 'p' ? 12 : 0);
        }

        return $hour < 24 ? sprintf('%02d:%s', $hour, $parts[2]) : null;
    }

    public function scheduleSortKey(): array
    {
        return [$this->getRawOriginal('fecha'), $this->hora ?? '99:99', $this->id];
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
