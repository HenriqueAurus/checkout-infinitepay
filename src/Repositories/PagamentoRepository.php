<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PagamentoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function criar(int $pedidoId, string $modo, string $orderNsu): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pagamentos (pedido_id, modo, order_nsu) VALUES (:pedido, :modo, :nsu)'
        );
        $stmt->execute(['pedido' => $pedidoId, 'modo' => $modo, 'nsu' => $orderNsu]);

        return (int) $this->pdo->lastInsertId();
    }

    public function definirCheckout(int $pagamentoId, string $url, ?string $slug): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE pagamentos SET checkout_url = :url, slug = :slug WHERE id = :id'
        );
        $stmt->execute(['url' => $url, 'slug' => $slug, 'id' => $pagamentoId]);
    }

    public function atualizarStatus(int $pagamentoId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE pagamentos SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $pagamentoId]);
    }
}