<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Resultado escolhido no checkout simulado. Uma linha por pagamento (a última escolha vale). */
final class SimulacaoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function registrar(
        int $pagamentoId,
        string $resultado,
        ?string $metodo,
        string $transactionNsu,
        string $slug
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO simulacoes (pagamento_id, resultado, metodo, transaction_nsu, slug)
             VALUES (:pagamento, :resultado, :metodo, :transacao, :slug)
             ON DUPLICATE KEY UPDATE
               resultado       = VALUES(resultado),
               metodo          = VALUES(metodo),
               transaction_nsu = VALUES(transaction_nsu),
               slug            = VALUES(slug)'
        );
        $stmt->execute([
            'pagamento' => $pagamentoId,
            'resultado' => $resultado,
            'metodo'    => $metodo,
            'transacao' => $transactionNsu,
            'slug'      => $slug,
        ]);
    }

    /**
     * O que o "provedor simulado" sabe sobre um pedido: a escolha feita no checkout falso
     * e o valor do link (o total do pedido).
     *
     * @return array{resultado: string, metodo: ?string, transaction_nsu: string, slug: string, valor_link_centavos: int}|null
     */
    public function buscarPorOrderNsu(string $orderNsu): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.resultado, s.metodo, s.transaction_nsu, s.slug, p.total_centavos
             FROM simulacoes s
             JOIN pagamentos pg ON pg.id = s.pagamento_id
             JOIN pedidos p ON p.id = pg.pedido_id
             WHERE pg.order_nsu = :nsu'
        );
        $stmt->execute(['nsu' => $orderNsu]);
        $l = $stmt->fetch();

        if ($l === false) {
            return null;
        }

        return [
            'resultado'           => $l['resultado'],
            'metodo'              => $l['metodo'],
            'transaction_nsu'     => $l['transaction_nsu'],
            'slug'                => $l['slug'],
            'valor_link_centavos' => (int) $l['total_centavos'],
        ];
    }
}