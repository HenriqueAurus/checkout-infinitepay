<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Falha ao falar com o provedor de pagamento. A mensagem NUNCA deve conter segredos. */
final class GatewayException extends RuntimeException
{
}