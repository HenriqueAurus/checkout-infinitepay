<?php
declare(strict_types=1);

namespace App\Core;

use JsonException;

/** Encapsula a requisição HTTP recebida. */
final class Request
{
    private const MAX_CORPO_BYTES = 65536;

    /** @param array<string, mixed> $query */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        private readonly string $corpo
    ) {
    }

    public static function fromGlobals(): self
    {
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $caminho = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

        // Remove o prefixo da aplicação (ex.: /checkout-infinitepay) usando o APP_URL
        $base = rtrim((string) parse_url((string) Env::get('APP_URL', ''), PHP_URL_PATH), '/');
        if ($base !== '' && ($caminho === $base || str_starts_with($caminho, $base . '/'))) {
            $caminho = substr($caminho, strlen($base));
        }
        $caminho = '/' . trim($caminho, '/');

        $corpo = '';
        if (in_array($metodo, ['POST', 'PUT', 'PATCH'], true)) {
            $corpo = (string) file_get_contents('php://input', false, null, 0, self::MAX_CORPO_BYTES + 1);
            if (strlen($corpo) > self::MAX_CORPO_BYTES) {
                throw new HttpException(413, 'corpo_grande_demais', 'O corpo da requisição é grande demais.');
            }
        }

        return new self($metodo, $caminho, $_GET, $corpo);
    }

    public function header(string $nome): ?string
    {
        $chave = strtoupper(str_replace('-', '_', $nome));
        if (!in_array($chave, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
            $chave = 'HTTP_' . $chave;
        }
        $valor = $_SERVER[$chave] ?? null;

        return is_string($valor) ? $valor : null;
    }

    /** @return array<string, mixed> */
    public function json(bool $exigirContentType = true): array
    {
        if ($exigirContentType && stripos($this->header('Content-Type') ?? '', 'application/json') !== 0) {
            throw new HttpException(415, 'tipo_nao_suportado', 'Envie o corpo como application/json.');
        }
        if ($this->corpo === '') {
            throw new HttpException(400, 'corpo_vazio', 'O corpo da requisição está vazio.');
        }

        try {
            $dados = json_decode($this->corpo, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpException(400, 'json_invalido', 'O corpo não é um JSON válido.');
        }

        if (!is_array($dados) || array_is_list($dados)) {
            throw new HttpException(400, 'json_invalido', 'O corpo deve ser um objeto JSON.');
        }

        return $dados;
    }
}