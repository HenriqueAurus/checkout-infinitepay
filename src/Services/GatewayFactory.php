<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Repositories\SimulacaoRepository;
use PDO;
use RuntimeException;

/** Escolhe o gateway conforme PAYMENT_MODE no .env. */
final class GatewayFactory
{
    /**
     * @param string|null $cenarioSimulado só é considerado no modo simulation
     */
    public static function create(PDO $pdo, ?string $cenarioSimulado = null): PaymentGateway
    {
        $modo = Env::get('PAYMENT_MODE', 'simulation');

        return match ($modo) {
            'simulation' => new SimulatedGatewayService(
                $cenarioSimulado === 'indisponivel',
                new SimulacaoRepository($pdo)
            ),
            'real'       => throw new RuntimeException('Modo real ainda não implementado (Etapa 4E).'),
            default      => throw new RuntimeException('PAYMENT_MODE inválido.'),
        };
    }
}