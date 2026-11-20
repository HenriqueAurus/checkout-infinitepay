<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Lê o arquivo .env e guarda os valores apenas em memória.
 *
 * Propositalmente NÃO usa putenv(), $_ENV nem $_SERVER, para que os segredos
 * não apareçam em phpinfo() ou em dumps de variáveis.
 * Valor vazio é tratado como "não definido".
 */
final class Env
{
    /** @var array<string, string> */
    private static array $valores = [];

    public static function load(string $caminho): void
    {
        if (!is_file($caminho) || !is_readable($caminho)) {
            throw new RuntimeException('Arquivo .env não encontrado. Copie .env.example para .env.');
        }

        $linhas = file($caminho, FILE_IGNORE_NEW_LINES);
        if ($linhas === false) {
            throw new RuntimeException('Não foi possível ler o arquivo .env.');
        }

        $valores = [];
        foreach ($linhas as $indice => $linha) {
            if ($indice === 0) {
                // Remove BOM, que alguns editores do Windows adicionam
                $linha = preg_replace('/^\xEF\xBB\xBF/', '', $linha) ?? $linha;
            }

            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#') {
                continue;
            }

            $pos = strpos($linha, '=');
            if ($pos === false) {
                continue;
            }

            $chave = trim(substr($linha, 0, $pos));
            $valor = trim(substr($linha, $pos + 1));

            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $chave)) {
                continue;
            }

            // Remove aspas simples ou duplas ao redor do valor
            if (
                strlen($valor) >= 2
                && ($valor[0] === '"' || $valor[0] === "'")
                && substr($valor, -1) === $valor[0]
            ) {
                $valor = substr($valor, 1, -1);
            }

            $valores[$chave] = $valor;
        }

        self::$valores = $valores;
    }

    public static function get(string $chave, ?string $padrao = null): ?string
    {
        $valor = self::$valores[$chave] ?? null;

        return ($valor === null || $valor === '') ? $padrao : $valor;
    }

    /** A mensagem de erro cita só o NOME da variável, nunca o valor. */
    public static function required(string $chave): string
    {
        $valor = self::get($chave);
        if ($valor === null) {
            throw new RuntimeException("Variável de configuração obrigatória ausente: {$chave}");
        }

        return $valor;
    }

    public static function getInt(string $chave, int $padrao): int
    {
        $valor = self::get($chave);

        return ($valor !== null && ctype_digit($valor)) ? (int) $valor : $padrao;
    }
}