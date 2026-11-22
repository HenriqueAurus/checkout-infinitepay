<?php
declare(strict_types=1);

/**
 * Sonda a API REAL da InfinitePay: cria UM link de R$ 1,00 e mostra a resposta.
 * Não toca no banco e nunca imprime a API Key.
 *
 * Simulação do envio (sem rede):  C:\xampp\php\php.exe scripts\infinitepay-probe.php
 * Chamada real:                   C:\xampp\php\php.exe scripts\infinitepay-probe.php --confirmo
 */

use App\Core\Env;

try {
    require __DIR__ . '/../src/bootstrap.php';
    $handle  = Env::required('INFINITEPAY_HANDLE');
    $chave   = Env::required('INFINITEPAY_API_KEY');
    $base    = rtrim(Env::required('INFINITEPAY_BASE_URL'), '/');
    $appUrl  = rtrim(Env::required('APP_URL'), '/');
    $timeout = Env::getInt('INFINITEPAY_TIMEOUT', 15);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO]  ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, '[ERRO]  A extensão curl não está carregada no PHP.' . PHP_EOL);
    exit(1);
}

/** Lista só nomes e tipos dos campos (sem valores). */
function estrutura(mixed $valor, string $prefixo = ''): void
{
    if (!is_array($valor)) {
        return;
    }
    foreach ($valor as $k => $item) {
        $caminho = $prefixo === '' ? (string) $k : $prefixo . '.' . $k;
        echo '  ' . str_pad($caminho, 40) . gettype($item) . PHP_EOL;
        estrutura($item, $caminho);
    }
}

$payload = [
    'handle'       => $handle,
    'order_nsu'    => 'sonda-' . date('Ymd-His'),
    'items'        => [[
        'quantity'    => 1,
        'price'       => 100, // centavos: R$ 1,00 (mínimo da InfinitePay)
        'description' => 'Teste de integracao (sonda)',
    ]],
    'redirect_url' => $appUrl . '/resultado.html',
    'options'      => [
        'single_payment' => true,
        'valid_until'    => (new DateTimeImmutable('+1 day'))->format('c'),
    ],
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

echo "Destino : POST {$base}/links" . PHP_EOL;
echo 'API Key : definida (não exibida)' . PHP_EOL;
echo 'Corpo   : ' . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL;

if (!in_array('--confirmo', $argv ?? [], true)) {
    echo 'SIMULAÇÃO: nada foi enviado. Para fazer a chamada real, acrescente --confirmo.' . PHP_EOL;
    exit(0);
}

$ch = curl_init($base . '/links');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $json,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Api-Key: ' . $chave,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_FOLLOWLOCATION => false,
]);

$corpo    = curl_exec($ch);
$erroNum  = curl_errno($ch);
$erroMsg  = curl_error($ch);
$http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$tipo     = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($erroNum !== 0 || !is_string($corpo)) {
    echo "[ERRO] Falha de rede (cURL {$erroNum}): {$erroMsg}" . PHP_EOL;
    exit(1);
}

// Garantia extra: a chave nunca vai para a tela nem para o arquivo
$corpo = str_replace($chave, '[CHAVE-OCULTA]', $corpo);

echo "HTTP    : {$http}" . PHP_EOL;
echo "Tipo    : {$tipo}" . PHP_EOL . PHP_EOL;

$dados = json_decode($corpo, true);
if (is_array($dados)) {
    echo 'Corpo da resposta:' . PHP_EOL;
    echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL;
    echo 'Estrutura (nomes e tipos, sem valores):' . PHP_EOL;
    estrutura($dados);
} else {
    echo 'A resposta não é JSON. Primeiros 500 caracteres:' . PHP_EOL;
    echo substr($corpo, 0, 500) . PHP_EOL;
}

// Guarda a resposta para consulta (logs/ é ignorado pelo Git)
file_put_contents(BASE_PATH . '/logs/infinitepay-probe-last.txt', "HTTP {$http}\n{$corpo}\n");
echo PHP_EOL . 'Resposta salva em logs\\infinitepay-probe-last.txt' . PHP_EOL;
exit($http >= 200 && $http < 300 ? 0 : 1);