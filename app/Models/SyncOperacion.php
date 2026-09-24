<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncOperacion extends Model
{
    protected $table = 'sync_operaciones';

    protected $fillable = ['client_uuid', 'user_id', 'tipo', 'respuesta'];

    protected $casts = ['respuesta' => 'array'];
}
