<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use JsonException;
use RuntimeException;

/**
 * Integração REAL com a InfinitePay (Checkout Integrado).
 * Endpoints (conforme a documentação oficial):
 *   POST /links          -> cria o link de checkout (resposta observada: {"url": "..."})
 *   POST /payment_check  -> consulta o pagamento
 * A API Key só vai no header X-Api-Key e nunca aparece em mensagens de erro ou logs.
 */
final class InfinitePayService implements PaymentGateway
{
    private const LINK_VALIDADE = '+24 hours';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $handle,
        private readonly string $apiKey,
        private readonly string $appUrl,
        private readonly int $timeout
    ) {
        if (!str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('INFINITEPAY_BASE_URL deve começar com https://');
        }
        if (!extension_loaded('curl')) {
            throw new RuntimeException('A extensão curl não está carregada no PHP.');
        }
    }

    public function modo(): string
    {
        return 'real';
    }

    public function createCheckoutLink(array $pedido): array
    {
        $itens = [];
        $soma = 0;
        foreach ($pedido['itens'] as $item) {
            $itens[] = [
                'quantity'    => $item['quantidade'],
                'price'       => $item['preco_centavos'], // centavos, como exige a API
                'description' => $item['descricao'],
            ];
            $soma += $item['quantidade'] * $item['preco_centavos'];
        }

        // Trava de consistência: o que será cobrado precisa ser o total do pedido
        if ($itens === [] || $soma !== $pedido['total_centavos']) {
            throw new GatewayException('Itens do pedido inconsistentes com o total.');
        }

        $resposta = $this->requisitar('/links', [
            'handle'       => $this->handle,
            'order_nsu'    => $pedido['codigo'],
            'items'        => $itens,
            'redirect_url' => $this->appUrl . '/resultado.html',
            'customer'     => ['name' => $pedido['comprador'], 'email' => $pedido['email']],
            'options'      => [
                'single_payment' => true,
                'valid_until'    => (new DateTimeImmutable(self::LINK_VALIDADE))->format('c'),
            ],
        ], true);

        $url = $resposta['url'] ?? null;
        if (!is_string($url) || !$this->urlConfiavel($url)) {
            throw new GatewayException('Resposta inesperada da InfinitePay ao criar o link.');
        }

        // A resposta não traz o slug; ele chega depois, pela redirect_url e pelo webhook
        return ['url' => $url, 'slug' => null];
    }

    public function checkPayment(array $dados): array
    {
        $resposta = $this->requisitar('/payment_check', [
            'handle'          => $this->handle,
            'order_nsu'       => $dados['order_nsu'],
            'transaction_nsu' => $dados['transaction_nsu'],
            'slug'            => $dados['slug'],
        ], false);

        $success = ($resposta['success'] ?? false) === true;
        $paid = ($resposta['paid'] ?? false) === true;

        // Qualquer coisa diferente de success=true E paid=true significa "não confirmado"
        if (!$success || !$paid) {
            return [
                'success'        => $success,
                'paid'           => false,
                'amount'         => null,
                'paid_amount'    => null,
                'installments'   => null,
                'capture_method' => null,
            ];
        }

        // Pago: os campos precisam ter o tipo esperado, senão a resposta não é confiável
        $amount = $resposta['amount'] ?? null;
        $paidAmount = $resposta['paid_amount'] ?? null;
        $parcelas = $resposta['installments'] ?? null;
        $metodo = $resposta['capture_method'] ?? null;

        if (
            !is_int($amount)
            || ($paidAmount !== null && !is_int($paidAmount))
            || ($parcelas !== null && !is_int($parcelas))
            || ($metodo !== null && !is_string($metodo))
        ) {
            error_log('InfinitePay /payment_check: resposta "paga" com campos em formato inesperado.');
            throw new GatewayException('Resposta inválida da InfinitePay ao consultar o pagamento.');
        }

        return [
            'success'        => true,
            'paid'           => true,
            'amount'         => $amount,
            'paid_amount'    => $paidAmount,
            'installments'   => $parcelas,
            'capture_method' => $metodo,
        ];
    }

    /** Só aceita https e o domínio infinitepay.io (ou subdomínios), sem usuário/senha na URL. */
    private function urlConfiavel(string $url): bool
    {
        if (strlen($url) > 1000) {
            return false;
        }

        $partes = parse_url($url);
        if (!is_array($partes) || isset($partes['user']) || isset($partes['pass'])) {
            return false;
        }

        $host = strtolower($partes['host'] ?? '');

        return ($partes['scheme'] ?? '') === 'https'
            && ($host === 'infinitepay.io' || str_ends_with($host, '.infinitepay.io'));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws GatewayException
     */
    private function requisitar(string $caminho, array $payload, bool $comChave): array
    {
        try {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new GatewayException('Não foi possível montar a requisição para a InfinitePay.');
        }

        $cabecalhos = ['Content-Type: application/json', 'Accept: application/json'];
        if ($comChave) {
            $cabecalhos[] = 'X-Api-Key: ' . $this->apiKey;
        }

        $ch = curl_init($this->baseUrl . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => $cabecalhos,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $corpo = curl_exec($ch);
        $erroNum = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($erroNum !== 0 || !is_string($corpo)) {
            error_log("InfinitePay {$caminho}: falha de rede (cURL {$erroNum}).");
            throw new GatewayException('Falha de comunicação com a InfinitePay.');
        }

        if ($http < 200 || $http >= 300) {
            error_log(sprintf('InfinitePay %s: HTTP %d - %s', $caminho, $http, $this->resumo($corpo)));
            throw new GatewayException("A InfinitePay respondeu HTTP {$http}.");
        }

        try {
            $dados = json_decode($corpo, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            error_log("InfinitePay {$caminho}: resposta que não é JSON.");
            throw new GatewayException('Resposta inválida da InfinitePay.');
        }

        if (!is_array($dados)) {
            throw new GatewayException('Resposta inválida da InfinitePay.');
        }

        return $dados;
    }

    /** Trecho curto da resposta para o log, com a chave mascarada e sem caracteres de controle. */
    private function resumo(string $corpo): string
    {
        $corpo = str_replace($this->apiKey, '[CHAVE-OCULTA]', $corpo);
        $corpo = preg_replace('/[\x00-\x1F\x7F]/', ' ', $corpo) ?? '';

        return substr($corpo, 0, 300);
    }
}