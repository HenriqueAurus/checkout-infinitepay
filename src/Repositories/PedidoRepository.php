<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PedidoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function criar(string $codigo, string $comprador, string $email, int $totalCentavos): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pedidos (codigo, comprador, email, total_centavos)
             VALUES (:codigo, :comprador, :email, :total)'
        );
        $stmt->execute([
            'codigo'    => $codigo,
            'comprador' => $comprador,
            'email'     => $email,
            'total'     => $totalCentavos,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function adicionarItem(int $pedidoId, int $produtoId, int $quantidade, int $precoUnitarioCentavos): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario_centavos)
             VALUES (:pedido, :produto, :quantidade, :preco)'
        );
        $stmt->execute([
            'pedido'     => $pedidoId,
            'produto'    => $produtoId,
            'quantidade' => $quantidade,
            'preco'      => $precoUnitarioCentavos,
        ]);
    }

    public function atualizarStatus(int $pedidoId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE pedidos SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $pedidoId]);
    }
}