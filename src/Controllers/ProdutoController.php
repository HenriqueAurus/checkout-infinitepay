<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ProdutoRepository;

final class ProdutoController
{
    public function __construct(private readonly ProdutoRepository $produtos)
    {
    }

    /** @param array<string, string> $parametros */
    public function listar(Request $requisicao, array $parametros): Response
    {
        return Response::ok($this->produtos->listarAtivos());
    }
}