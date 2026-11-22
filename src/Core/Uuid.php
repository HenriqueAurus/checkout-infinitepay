<?php
declare(strict_types=1);

namespace App\Core;

final class Uuid
{
    /** UUID v4 (aleatório), gerado com random_bytes. */
    public static function v4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** Aceita só o formato que nós geramos (v4, minúsculas). */
    public static function valido(string $valor): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $valor
        ) === 1;
    }
}