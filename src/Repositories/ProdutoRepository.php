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

        return array_map(static fn (array $l): array => [
            'id'             => (int) $l['id'],
            'slug'           => $l['slug'],
            'nome'           => $l['nome'],
            'descricao'      => $l['descricao'],
            'imagem_url'     => $l['imagem_url'],
            'preco_centavos' => (int) $l['preco_centavos'],
        ], $linhas);
    }
}