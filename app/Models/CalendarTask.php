<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'completed',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'completed' => 'boolean',
        ];
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
