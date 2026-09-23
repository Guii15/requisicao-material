<?php

namespace App\Models;

use App\Enums\PapelSetor;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SetorAprovador extends Pivot
{
    public $incrementing = true;

    protected $table = 'setor_aprovadores';

    protected function casts(): array
    {
        return [
            'papel' => PapelSetor::class,
        ];
    }
}
