<?php
declare(strict_types=1);

namespace App\Core;

/** Resposta JSON padronizada: {"dados": ...} ou {"erro": {"codigo", "mensagem", "detalhes"?}}. */
final class Response
{
    /**
     * @param array<string, mixed> $conteudo
     * @param array<string, string> $cabecalhos
     */
    private function __construct(
        private readonly int $status,
        private readonly array $conteudo,
        private readonly array $cabecalhos = []
    ) {
    }

    public static function ok(mixed $dados, int $status = 200): self
    {
        return new self($status, ['dados' => $dados]);
    }

    /** @param array<string, string> $detalhes */
    public static function error(int $status, string $codigo, string $mensagem, array $detalhes = []): self
    {
        $erro = ['codigo' => $codigo, 'mensagem' => $mensagem];
        if ($detalhes !== []) {
            $erro['detalhes'] = $detalhes;
        }

        return new self($status, ['erro' => $erro]);
    }

    public function withHeader(string $nome, string $valor): self
    {
        return new self($this->status, $this->conteudo, $this->cabecalhos + [$nome => $valor]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By');
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        foreach ($this->cabecalhos as $nome => $valor) {
            header("{$nome}: {$valor}");
        }

        echo json_encode(
            $this->conteudo,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}