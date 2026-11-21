<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Roteador simples. Caminhos aceitam parâmetros no formato {nome},
 * por exemplo /api/pedidos/{codigo}. Os caminhos são definidos por nós
 * (nunca vêm do usuário), por isso não é preciso escapá-los.
 */
final class Router
{
    /** @var array<int, array{0: string, 1: string, 2: callable}> */
    private array $rotas = [];

    public function add(string $metodo, string $caminho, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[A-Za-z0-9\-]+)', $caminho) . '$#';
        $this->rotas[] = [strtoupper($metodo), $regex, $handler];
    }

    public function dispatch(Request $requisicao): Response
    {
        $metodosPermitidos = [];

        foreach ($this->rotas as [$metodo, $regex, $handler]) {
            if (preg_match($regex, $requisicao->path, $achados) !== 1) {
                continue;
            }

            if ($metodo !== $requisicao->method) {
                $metodosPermitidos[] = $metodo;
                continue;
            }

            $parametros = array_filter($achados, 'is_string', ARRAY_FILTER_USE_KEY);

            return $handler($requisicao, $parametros);
        }

        if ($metodosPermitidos !== []) {
            return Response::error(405, 'metodo_nao_permitido', 'Método HTTP não permitido para este recurso.')
                ->withHeader('Allow', implode(', ', array_unique($metodosPermitidos)));
        }

        return Response::error(404, 'nao_encontrado', 'Recurso não encontrado.');
    }
}