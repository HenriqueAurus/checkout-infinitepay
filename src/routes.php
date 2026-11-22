<?php
declare(strict_types=1);

use App\Controllers\CheckoutController;
use App\Controllers\PagamentoController;
use App\Controllers\PedidoController;
use App\Controllers\ProdutoController;
use App\Controllers\SimuladorController;
use App\Controllers\WebhookController;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Repositories\EventoWebhookRepository;
use App\Repositories\PagamentoRepository;
use App\Repositories\PedidoRepository;
use App\Repositories\ProdutoRepository;
use App\Repositories\SimulacaoRepository;
use App\Services\CheckoutService;
use App\Services\GatewayFactory;
use App\Services\PagamentoService;
use App\Services\PedidoService;
use App\Services\SimuladorService;
use App\Services\WebhookService;

return static function (Router $router): void {
    // O cenário de simulação só vale no modo simulation; no modo real o cabeçalho é ignorado
    $cenarioSimulado = static fn (Request $req): ?string =>
        Env::get('PAYMENT_MODE', 'simulation') === 'simulation' ? $req->header('X-Simulacao') : null;

    $router->add('GET', '/api/ping', static fn (Request $req, array $p): Response => Response::ok([
        'status'         => 'ok',
        'modo_pagamento' => Env::get('PAYMENT_MODE', 'simulation'),
        'hora_servidor'  => date('c'),
    ]));

    $router->add('GET', '/api/produtos', static fn (Request $req, array $p): Response =>
        (new ProdutoController(new ProdutoRepository(Database::connection())))->listar($req, $p)
    );

    $router->add('POST', '/api/checkout', static function (Request $req, array $p) use ($cenarioSimulado): Response {
        $pdo = Database::connection();

        $servico = new CheckoutService(
            $pdo,
            new ProdutoRepository($pdo),
            new PedidoRepository($pdo),
            new PagamentoRepository($pdo),
            GatewayFactory::create($pdo, $cenarioSimulado($req))
        );

        return (new CheckoutController($servico))->criar($req, $p);
    });

    $router->add('GET', '/api/pedidos/{codigo}', static function (Request $req, array $p): Response {
        $pdo = Database::connection();
        $servico = new PedidoService(new PedidoRepository($pdo), new PagamentoRepository($pdo));

        return (new PedidoController($servico))->consultar($req, $p);
    });

    $router->add('POST', '/api/pagamentos/confirmar', static function (Request $req, array $p) use ($cenarioSimulado): Response {
        $pdo = Database::connection();

        $servico = new PagamentoService(
            $pdo,
            new PagamentoRepository($pdo),
            new PedidoRepository($pdo),
            GatewayFactory::create($pdo, $cenarioSimulado($req))
        );

        return (new PagamentoController($servico))->confirmar($req, $p);
    });

    // Notificação da InfinitePay: o corpo NÃO decide nada, o veredicto vem sempre do payment_check
    $router->add('POST', '/api/webhooks/infinitepay', static function (Request $req, array $p) use ($cenarioSimulado): Response {
        $pdo = Database::connection();
        $pagamentos = new PagamentoRepository($pdo);

        $pagamentoService = new PagamentoService(
            $pdo,
            $pagamentos,
            new PedidoRepository($pdo),
            GatewayFactory::create($pdo, $cenarioSimulado($req))
        );

        $servico = new WebhookService($pagamentoService, $pagamentos, new EventoWebhookRepository($pdo));

        return (new WebhookController($servico))->infinitepay($req, $p);
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