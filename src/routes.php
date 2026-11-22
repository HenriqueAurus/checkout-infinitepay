<?php
declare(strict_types=1);

use App\Controllers\CheckoutController;
use App\Controllers\PedidoController;
use App\Controllers\ProdutoController;
use App\Controllers\SimuladorController;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Repositories\PagamentoRepository;
use App\Repositories\PedidoRepository;
use App\Repositories\ProdutoRepository;
use App\Repositories\SimulacaoRepository;
use App\Services\CheckoutService;
use App\Services\GatewayFactory;
use App\Services\PedidoService;
use App\Services\SimuladorService;

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

    $router->add('GET', '/api/pedidos/{codigo}', static function (Request $req, array $p): Response {
        $pdo = Database::connection();
        $servico = new PedidoService(new PedidoRepository($pdo), new PagamentoRepository($pdo));

        return (new PedidoController($servico))->consultar($req, $p);
    });

    // O simulador só existe no modo simulation. No modo real a rota responde como se não existisse.
    $router->add('POST', '/api/simulador/resolver', static function (Request $req, array $p): Response {
        if (Env::get('PAYMENT_MODE', 'simulation') !== 'simulation') {
            return Response::error(404, 'nao_encontrado', 'Recurso não encontrado.');
        }

        $pdo = Database::connection();
        $servico = new SimuladorService(new PagamentoRepository($pdo), new SimulacaoRepository($pdo));

        return (new SimuladorController($servico))->resolver($req, $p);
    });
};