<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

/** Escolhe o gateway conforme PAYMENT_MODE no .env. */
final class GatewayFactory
{
    /**
     * @param string|null $cenarioSimulado só é considerado no modo simulation
     */
    public static function create(?string $cenarioSimulado = null): PaymentGateway
    {
        $modo = Env::get('PAYMENT_MODE', 'simulation');

        return match ($modo) {
            'simulation' => new SimulatedGatewayService($cenarioSimulado === 'indisponivel'),
            'real'       => throw new RuntimeException('Modo real ainda não implementado (Etapa 4D).'),
            default      => throw new RuntimeException('PAYMENT_MODE inválido.'),
        };
    }
}