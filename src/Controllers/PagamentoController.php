<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\PagamentoService;

final class PagamentoController
{
    public function __construct(private readonly PagamentoService $servico)
    {
    }

    /** @param array<string, string> $parametros */
    public function confirmar(Request $requisicao, array $parametros): Response
    {
        return Response::ok($this->servico->confirmar($requisicao->json()));
    }
}