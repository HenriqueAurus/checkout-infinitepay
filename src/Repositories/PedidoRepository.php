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

    /** @return array{id: int, codigo: string, total_centavos: int, status: string, criado_em: string}|null */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, codigo, total_centavos, status, criado_em FROM pedidos WHERE codigo = :codigo'
        );
        $stmt->execute(['codigo' => $codigo]);
        $l = $stmt->fetch();

        if ($l === false) {
            return null;
        }

        return [
            'id'             => (int) $l['id'],
            'codigo'         => $l['codigo'],
            'total_centavos' => (int) $l['total_centavos'],
            'status'         => $l['status'],
            'criado_em'      => $l['criado_em'],
        ];
    }

    /** @return array<int, array{produto_id: int, nome: string, quantidade: int, preco_unitario_centavos: int}> */
    public function listarItens(int $pedidoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.produto_id, p.nome, i.quantidade, i.preco_unitario_centavos
             FROM itens_pedido i
             JOIN produtos p ON p.id = i.produto_id
             WHERE i.pedido_id = :id
             ORDER BY i.id'
        );
        $stmt->execute(['id' => $pedidoId]);

        return array_map(static fn (array $l): array => [
            'produto_id'              => (int) $l['produto_id'],
            'nome'                    => $l['nome'],
            'quantidade'              => (int) $l['quantidade'],
            'preco_unitario_centavos' => (int) $l['preco_unitario_centavos'],
        ], $stmt->fetchAll());
    }
}