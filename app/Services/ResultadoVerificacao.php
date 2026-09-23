<?php

namespace App\Services;

final class ResultadoVerificacao
{
    /**
     * @param  list<string>  $problemas
     */
    public function __construct(
        public readonly bool $integro,
        public readonly array $problemas,
    ) {}
}
