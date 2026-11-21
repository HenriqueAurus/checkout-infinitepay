<?php
declare(strict_types=1);

use App\Controllers\ProdutoController;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Repositories\ProdutoRepository;

return static function (Router $router): void {
    $router->add('GET', '/api/ping', static fn (Request $req, array $p): Response => Response::ok([
        'status'          => 'ok',
        'modo_pagamento'  => Env::get('PAYMENT_MODE', 'simulation'),
        'hora_servidor'   => date('c'),
    ]));

    $router->add('GET', '/api/produtos', static fn (Request $req, array $p): Response =>
        (new ProdutoController(new ProdutoRepository(Database::connection())))->listar($req, $p)
    );
};