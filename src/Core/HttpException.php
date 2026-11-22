<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Erro "esperado" que deve virar uma resposta HTTP (400, 404, 422...). */
final class HttpException extends RuntimeException
{
    /** @param array<string, string> $detalhes erros por campo (opcional) */
    public function __construct(
        private readonly int $status,
        private readonly string $codigo,
        string $mensagem,
        private readonly array $detalhes = []
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

    /** @return array<string, string> */
    public function getDetalhes(): array
    {
        return $this->detalhes;
    }
}