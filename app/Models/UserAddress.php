<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    protected $table = 'usuario_direcciones';

    protected $primaryKey = 'usuario_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['usuario_id', 'calle', 'numero_calle'];
}
