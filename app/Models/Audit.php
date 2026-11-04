<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    protected $table = 'auditorias';

    public $timestamps = false;

    protected $fillable = ['fecha_auditoria', 'descripcion'];

    protected function casts(): array
    {
        return ['fecha_auditoria' => 'datetime'];
    }
}
