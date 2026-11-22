<?php
declare(strict_types=1);

use App\Controllers\CheckoutController;
use App\Controllers\ProdutoController;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Repositories\PagamentoRepository;
use App\Repositories\PedidoRepository;
use App\Repositories\ProdutoRepository;
use App\Services\CheckoutService;
use App\Services\GatewayFactory;

return static function (Router $router): void {
    $router->add('GET', '/api/ping', static fn (Request $req, array $p): Response => Response::ok([
        'status'         => 'ok',
        'modo_pagamento' => Env::get('PAYMENT_MODE', 'simulation'),
        'hora_servidor'  => date('c'),
    ]));

    $router->add('GET', '/api/produtos', static fn (Request $req, array $p): Response =>
        (new ProdutoController(new ProdutoRepository(Database::connection())))->listar($req, $p)
    );

    $router->add('POST', '/api/checkout', static function (Request $req, array $p): Response {
        $pdo = Database::connection();

        // O cenário de simulação só vale no modo simulation; no modo real o cabeçalho é ignorado
        $cenario = Env::get('PAYMENT_MODE', 'simulation') === 'simulation'
            ? $req->header('X-Simulacao')
            : null;

        $servico = new CheckoutService(
            $pdo,
            new ProdutoRepository($pdo),
            new PedidoRepository($pdo),
            new PagamentoRepository($pdo),
            GatewayFactory::create($cenario)
        );

        return (new CheckoutController($servico))->criar($req, $p);
    });
};