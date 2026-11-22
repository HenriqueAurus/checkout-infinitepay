<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProdutoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listarAtivos(): array
    {
        $linhas = $this->pdo
            ->query('SELECT id, slug, nome, descricao, imagem_url, preco_centavos FROM produtos WHERE ativo = 1 ORDER BY id')
            ->fetchAll();

        return array_map(fn (array $l): array => $this->mapear($l), $linhas);
    }

    /** @return array<string, mixed>|null */
    public function buscarAtivoPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, nome, descricao, imagem_url, preco_centavos FROM produtos WHERE id = :id AND ativo = 1'
        );
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $this->mapear($linha);
    }

    /**
     * @param array<string, mixed> $l
     * @return array<string, mixed>
     */
    private function mapear(array $l): array
    {
        return [
            'id'             => (int) $l['id'],
            'slug'           => $l['slug'],
            'nome'           => $l['nome'],
            'descricao'      => $l['descricao'],
            'imagem_url'     => $l['imagem_url'],
            'preco_centavos' => (int) $l['preco_centavos'],
        ];
    }
}