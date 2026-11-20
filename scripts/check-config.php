<?php
declare(strict_types=1);

/**
 * Valida a configuração (.env) SEM exibir segredos.
 * Uso: C:\xampp\php\php.exe scripts\check-config.php
 */

use App\Core\Env;

try {
    require __DIR__ . '/../src/bootstrap.php';
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO]  ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$erros = 0;

$linha = static function (string $status, string $mensagem) use (&$erros): void {
    if ($status === 'ERRO') {
        $erros++;
    }
    echo str_pad("[{$status}]", 9) . $mensagem . PHP_EOL;
};

echo "Verificação da configuração (valores secretos nunca são exibidos)" . PHP_EOL . PHP_EOL;

// ---- Modo de pagamento ----
$modo = Env::get('PAYMENT_MODE', '');
if (in_array($modo, ['simulation', 'real'], true)) {
    $linha('OK', "PAYMENT_MODE = {$modo}");
} else {
    $linha('ERRO', 'PAYMENT_MODE deve ser "simulation" ou "real".');
}
$real = ($modo === 'real');

// ---- Aplicação ----
$appUrl = Env::get('APP_URL', '');
if ($appUrl !== '' && filter_var($appUrl, FILTER_VALIDATE_URL) && preg_match('#^https?://#', $appUrl)) {
    $linha('OK', "APP_URL = {$appUrl}");
} else {
    $linha('ERRO', 'APP_URL ausente ou inválida (ex.: http://localhost/checkout-infinitepay).');
}

// ---- Banco de dados ----
foreach (['DB_HOST', 'DB_USER'] as $chave) {
    Env::get($chave) !== null
        ? $linha('OK', "{$chave} definida")
        : $linha('ERRO', "{$chave} não definida.");
}

$porta = Env::get('DB_PORT', '');
(ctype_digit($porta) && (int) $porta >= 1 && (int) $porta <= 65535)
    ? $linha('OK', "DB_PORT = {$porta}")
    : $linha('ERRO', 'DB_PORT inválida.');

$banco = Env::get('DB_NAME', '');
preg_match('/^[A-Za-z0-9_]+$/', $banco)
    ? $linha('OK', "DB_NAME = {$banco}")
    : $linha('ERRO', 'DB_NAME ausente ou com caracteres inválidos (use letras, números e _).');

Env::get('DB_PASS') === null
    ? $linha('INFO', 'DB_PASS vazia (padrão do XAMPP).')
    : $linha('OK', 'DB_PASS definida (valor não exibido).');

// ---- InfinitePay ----
$baseUrl = Env::get('INFINITEPAY_BASE_URL', '');
str_starts_with($baseUrl, 'https://')
    ? $linha('OK', "INFINITEPAY_BASE_URL = {$baseUrl}")
    : $linha('ERRO', 'INFINITEPAY_BASE_URL deve começar com https://');

$timeout = Env::get('INFINITEPAY_TIMEOUT', '');
(ctype_digit($timeout) && (int) $timeout >= 1 && (int) $timeout <= 60)
    ? $linha('OK', "INFINITEPAY_TIMEOUT = {$timeout}s")
    : $linha('ERRO', 'INFINITEPAY_TIMEOUT deve ser um inteiro entre 1 e 60.');

$handle = Env::get('INFINITEPAY_HANDLE');
if ($handle === null) {
    $real
        ? $linha('ERRO', 'INFINITEPAY_HANDLE obrigatória no modo real.')
        : $linha('AVISO', 'INFINITEPAY_HANDLE não definida (não exigida no modo simulation).');
} elseif (str_starts_with($handle, '$') || preg_match('/\s/', $handle)) {
    $linha('ERRO', 'INFINITEPAY_HANDLE deve ser a InfiniteTag SEM o "$" e sem espaços.');
} else {
    $linha('OK', "INFINITEPAY_HANDLE = {$handle}");
}

$chaveApi = Env::get('INFINITEPAY_API_KEY');
if ($chaveApi === null) {
    $real
        ? $linha('ERRO', 'INFINITEPAY_API_KEY obrigatória no modo real.')
        : $linha('AVISO', 'INFINITEPAY_API_KEY não definida (não exigida no modo simulation).');
} elseif (preg_match('/\s/', $chaveApi) || !str_starts_with($chaveApi, 'inchk_')) {
    $linha('ERRO', 'INFINITEPAY_API_KEY com formato inesperado (deve começar com inchk_, sem espaços).');
} else {
    $linha('OK', 'INFINITEPAY_API_KEY definida, formato inchk_ confere (valor não exibido).');
}

$webhook = Env::get('INFINITEPAY_WEBHOOK_URL');
if ($webhook === null) {
    $linha('INFO', 'INFINITEPAY_WEBHOOK_URL não definida (normal sem túnel HTTPS).');
} elseif (str_starts_with($webhook, 'https://') && filter_var($webhook, FILTER_VALIDATE_URL)) {
    $linha('OK', 'INFINITEPAY_WEBHOOK_URL definida e em HTTPS.');
} else {
    $linha('ERRO', 'INFINITEPAY_WEBHOOK_URL precisa ser uma URL https:// válida.');
}

// ---- Higiene do repositório e do ambiente ----
$gitignore = is_file(BASE_PATH . '/.gitignore')
    ? (file(BASE_PATH . '/.gitignore', FILE_IGNORE_NEW_LINES) ?: [])
    : [];
in_array('.env', array_map('trim', $gitignore), true)
    ? $linha('OK', '.gitignore contém ".env".')
    : $linha('ERRO', '.gitignore NÃO ignora o .env. Corrija antes de qualquer commit.');

is_writable(BASE_PATH . '/logs')
    ? $linha('OK', 'Pasta logs/ gravável.')
    : $linha('ERRO', 'Pasta logs/ sem permissão de escrita.');

echo PHP_EOL . ($erros === 0 ? 'Resultado: sem erros.' : "Resultado: {$erros} erro(s) encontrado(s).") . PHP_EOL;
exit($erros === 0 ? 0 : 1);