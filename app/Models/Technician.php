<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Technician extends Model
{
    protected $table = 'tecnicos';

    public $timestamps = false;

    protected $fillable = ['id', 'tipo_tecnico'];
}
