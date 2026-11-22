<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PagamentoRepository
{
    private const SELECT = 'SELECT pg.id, pg.pedido_id, pg.modo, pg.order_nsu, pg.slug, pg.transaction_nsu,
                                   pg.metodo, pg.status, pg.checkout_url, pg.valor_pago_centavos, p.total_centavos
                            FROM pagamentos pg
                            JOIN pedidos p ON p.id = pg.pedido_id';

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

    /**
     * Marca como pago de forma ATÔMICA. Devolve true só para a requisição que fez a transição;
     * se o pagamento já estava pago, nada muda e devolve false (base da idempotência).
     * Pode lançar PDOException 1062 se o transaction_nsu já pertencer a outro pagamento.
     */
    public function marcarComoPago(
        int $pagamentoId,
        string $transactionNsu,
        string $slug,
        ?string $metodo,
        int $valorPagoCentavos
    ): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE pagamentos
             SET status = 'pago', transaction_nsu = :tx, slug = :slug, metodo = :metodo,
                 valor_pago_centavos = :valor
             WHERE id = :id AND status <> 'pago'"
        );
        $stmt->execute([
            'tx'     => $transactionNsu,
            'slug'   => $slug,
            'metodo' => $metodo,
            'valor'  => $valorPagoCentavos,
            'id'     => $pagamentoId,
        ]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<string, mixed>|null */
    public function buscarPorOrderNsu(string $orderNsu): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE pg.order_nsu = :valor');
        $stmt->execute(['valor' => $orderNsu]);

        return $this->mapear($stmt->fetch());
    }

    /** @return array<string, mixed>|null */
    public function buscarPorPedidoId(int $pedidoId): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE pg.pedido_id = :valor');
        $stmt->execute(['valor' => $pedidoId]);

        return $this->mapear($stmt->fetch());
    }

    /**
     * @param array<string, mixed>|false $l
     * @return array<string, mixed>|null
     */
    private function mapear(array|false $l): ?array
    {
        if ($l === false) {
            return null;
        }

        return [
            'id'                  => (int) $l['id'],
            'pedido_id'           => (int) $l['pedido_id'],
            'modo'                => $l['modo'],
            'order_nsu'           => $l['order_nsu'],
            'slug'                => $l['slug'],
            'transaction_nsu'     => $l['transaction_nsu'],
            'metodo'              => $l['metodo'],
            'status'              => $l['status'],
            'checkout_url'        => $l['checkout_url'],
            'valor_pago_centavos' => $l['valor_pago_centavos'] === null ? null : (int) $l['valor_pago_centavos'],
            'total_centavos'      => (int) $l['total_centavos'],
        ];
    }
}