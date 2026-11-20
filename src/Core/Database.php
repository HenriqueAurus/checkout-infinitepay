<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexão PDO única (criada na primeira chamada).
 * Prepared statements reais (EMULATE_PREPARES desligado) contra SQL Injection.
 * Erros de conexão são registrados só com o código, sem host, usuário ou senha.
 */
final class Database
{
    private static ?PDO $conexao = null;

    public static function connection(): PDO
    {
        if (self::$conexao !== null) {
            return self::$conexao;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            Env::required('DB_HOST'),
            Env::getInt('DB_PORT', 3306),
            Env::required('DB_NAME')
        );

        try {
            self::$conexao = new PDO($dsn, Env::required('DB_USER'), Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
                // Horário de Brasília nos DEFAULT CURRENT_TIMESTAMP
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'",
            ]);
        } catch (PDOException $e) {
            error_log('Falha ao conectar ao banco (código ' . $e->getCode() . ').');
            throw new RuntimeException('Não foi possível conectar ao banco de dados.');
        }

        return self::$conexao;
    }
}