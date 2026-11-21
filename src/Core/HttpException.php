<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Erro "esperado" que deve virar uma resposta HTTP (400, 404, 422...). */
final class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        private readonly string $codigo,
        string $mensagem
    ) {
        parent::__construct($mensagem);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }
}