<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\PedidoService;

final class PedidoController
{
    public function __construct(private readonly PedidoService $servico)
    {
    }

    /** @param array<string, string> $parametros */
    public function consultar(Request $requisicao, array $parametros): Response
    {
        return Response::ok($this->servico->consultar($parametros['codigo'] ?? ''));
    }
}