<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VillaGesellDia extends Model
{
    protected $table = 'villa_gesell_dias';

    protected $fillable = ['fecha', 'notas'];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function tocadas(): HasMany
    {
        return $this->hasMany(VillaGesellTocada::class, 'dia_id')->orderBy('orden')->orderBy('hora');
    }
}
