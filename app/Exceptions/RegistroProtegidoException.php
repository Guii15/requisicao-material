<?php

namespace App\Exceptions;

use LogicException;

/**
 * Tentativa de alterar ou excluir um registro que só muda pelo workflow (ou que nunca muda).
 * É erro de programação, não de usuário: nenhuma tela deve chegar aqui.
 */
class RegistroProtegidoException extends LogicException
{
    public static function exclusao(string $registro): self
    {
        return new self("{$registro} não pode ser excluído. Nada é apagado neste sistema.");
    }

    public static function imutavel(string $registro): self
    {
        return new self("{$registro} é imutável: não pode ser editado nem excluído.");
    }

    public static function foraDoWorkflow(string $registro): self
    {
        return new self("{$registro} só pode ser alterado pelo RequisicaoWorkflow.");
    }
}
