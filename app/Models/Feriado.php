<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    protected $table = 'feriados';

    /** @var list<string> */
    protected $fillable = [
        'data',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }
}
