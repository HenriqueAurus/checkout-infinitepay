<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CheckoutService;

final class CheckoutController
{
    public function __construct(private readonly CheckoutService $servico)
    {
    }

    /** @param array<string, string> $parametros */
    public function criar(Request $requisicao, array $parametros): Response
    {
        return Response::ok($this->servico->iniciar($requisicao->json()), 201);
    }
}