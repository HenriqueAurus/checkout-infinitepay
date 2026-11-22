<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Registro das notificações recebidas (auditoria e prova de idempotência). */
final class EventoWebhookRepository
{
    private const MAX_CORPO = 4000;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function registrar(string $orderNsu, string $transactionNsu, string $corpo, string $resultado): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO eventos_webhook (order_nsu, transaction_nsu, corpo, resultado)
             VALUES (:order, :tx, :corpo, :resultado)'
        );
        $stmt->execute([
            'order'     => $orderNsu,
            'tx'        => $transactionNsu,
            'corpo'     => mb_substr($corpo, 0, self::MAX_CORPO),
            'resultado' => $resultado,
        ]);
    }
}