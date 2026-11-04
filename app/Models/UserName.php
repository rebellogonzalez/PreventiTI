<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserName extends Model
{
    protected $table = 'usuario_nombres';

    protected $primaryKey = 'usuario_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['usuario_id', 'nombre', 'apellido'];
}
