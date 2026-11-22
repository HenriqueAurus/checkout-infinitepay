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
}