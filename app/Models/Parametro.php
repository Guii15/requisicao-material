<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Parametro extends Model
{
    public $incrementing = false;

    protected $table = 'parametros';

    protected $primaryKey = 'chave';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'chave',
        'valor',
    ];

    public static function valor(string $chave): string
    {
        $valor = static::query()->whereKey($chave)->value('valor');

        if ($valor === null) {
            throw new InvalidArgumentException("Parâmetro inexistente: {$chave}");
        }

        return $valor;
    }

    public static function inteiro(string $chave): int
    {
        return (int) static::valor($chave);
    }

    public static function booleano(string $chave): bool
    {
        return filter_var(static::valor($chave), FILTER_VALIDATE_BOOLEAN);
    }
}
