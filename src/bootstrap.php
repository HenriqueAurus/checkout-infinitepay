<?php
declare(strict_types=1);

use App\Core\Env;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Autoload simples (sem Composer): App\Core\Env -> src/Core/Env.php
spl_autoload_register(static function (string $classe): void {
    $prefixo = 'App\\';
    if (strncmp($classe, $prefixo, strlen($prefixo)) !== 0) {
        return;
    }

    $relativo = substr($classe, strlen($prefixo));
    $arquivo = __DIR__ . '/' . str_replace('\\', '/', $relativo) . '.php';

    if (is_file($arquivo)) {
        require $arquivo;
    }
});

// O diagnóstico mostrou Europe/Berlin; o projeto usa o horário de Brasília
date_default_timezone_set('America/Sao_Paulo');

// Erros vão para logs/php-error.log; na web nunca são exibidos ao cliente
error_reporting(E_ALL);
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/logs/php-error.log');

Env::load(BASE_PATH . '/.env');