<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/**
 * Gateway SIMULADO: não contacta nenhum provedor e não movimenta dinheiro.
 * O "checkout" é uma página local (simulador.html, criada na Etapa 5).
 */
final class SimulatedGatewayService implements PaymentGateway
{
    public function __construct(private readonly bool $forcarIndisponibilidade = false)
    {
    }

    public function modo(): string
    {
        return 'simulado';
    }

    public function createCheckoutLink(array $pedido): array
    {
        if ($this->forcarIndisponibilidade) {
            throw new GatewayException('Indisponibilidade simulada do provedor.');
        }

        $base = rtrim(Env::required('APP_URL'), '/');

        return [
            'url'  => $base . '/simulador.html?pedido=' . rawurlencode($pedido['codigo']),
            'slug' => null,
        ];
    }
}