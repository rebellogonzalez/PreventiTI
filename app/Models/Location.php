<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $table = 'activo_ubicaciones';

    protected $primaryKey = 'activo_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['activo_id', 'piso', 'sala'];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'activo_id');
    }

    public function getNombreAttribute(): string
    {
        return trim("{$this->piso} - {$this->sala}", ' -');
    }
}
