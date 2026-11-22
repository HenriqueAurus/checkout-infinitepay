<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\SimuladorService;

final class SimuladorController
{
    public function __construct(private readonly SimuladorService $servico)
    {
    }

    /** @param array<string, string> $parametros */
    public function resolver(Request $requisicao, array $parametros): Response
    {
        return Response::ok($this->servico->resolver($requisicao->json()));
    }
}