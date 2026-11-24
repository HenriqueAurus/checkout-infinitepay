<?php
declare(strict_types=1);

use App\Core\Env;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

// 1) Inicialização: se falhar, responde 500 genérico
try {
    require __DIR__ . '/../../src/bootstrap.php';
} catch (Throwable $e) {
    error_log('Falha na inicialização: ' . get_class($e));
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"erro":{"codigo":"erro_interno","mensagem":"Erro interno. Tente novamente mais tarde."}}';
    exit;
}

// 2) Roteamento: erros esperados viram a resposta correspondente
try {
    $router = new Router();
    (require BASE_PATH . '/src/routes.php')($router);
    $resposta = $router->dispatch(Request::fromGlobals());
} catch (HttpException $e) {
        $resposta = Response::error($e->getStatus(), $e->getCodigo(), $e->getMessage(), $e->getDetalhes());
} catch (Throwable $e) {
    // Detalhes só no log; o cliente recebe uma mensagem genérica
    $detalhe = sprintf('%s em %s:%d', get_class($e), basename($e->getFile()), $e->getLine());
    if (Env::get('APP_ENV') === 'local') {
        $detalhe .= ' - ' . $e->getMessage();
    }
    error_log('Erro não tratado: ' . $detalhe);
    $resposta = Response::error(500, 'erro_interno', 'Erro interno. Tente novamente mais tarde.');
}

$resposta->send();