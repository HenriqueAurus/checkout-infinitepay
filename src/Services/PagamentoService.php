<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Uuid;
use App\Repositories\PagamentoRepository;
use App\Repositories\PedidoRepository;
use PDO;
use PDOException;
use Throwable;

/**
 * Confirma pagamentos. O veredicto vem SEMPRE do gateway; nada do que o navegador
 * envia (parâmetros da redirect_url) decide, por si só, que um pedido está pago.
 */
final class PagamentoService
{
    private const METODOS = ['pix', 'credit_card'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly PagamentoRepository $pagamentos,
        private readonly PedidoRepository $pedidos,
        private readonly PaymentGateway $gateway
    ) {
    }

    /**
     * @param array<string, mixed> $dados corpo JSON da requisição
     * @return array<string, mixed>
     */
    public function confirmar(array $dados): array
    {
        [$orderNsu, $transactionNsu, $slug] = $this->validar($dados);

        $pagamento = $this->pagamentos->buscarPorOrderNsu($orderNsu);
        if ($pagamento === null) {
            throw new HttpException(404, 'pedido_nao_encontrado', 'Pedido não encontrado.');
        }
        if ($pagamento['checkout_url'] === null) {
            throw new HttpException(409, 'pedido_sem_checkout', 'Este pedido não possui um checkout ativo.');
        }

        // Idempotência: pedido já pago não volta ao provedor
        if ($pagamento['status'] === 'pago') {
            return $this->jaPago($orderNsu, $transactionNsu);
        }

        try {
            $verificacao = $this->gateway->checkPayment([
                'order_nsu'       => $orderNsu,
                'transaction_nsu' => $transactionNsu,
                'slug'            => $slug,
            ]);
        } catch (GatewayException $e) {
            error_log("Gateway falhou ao consultar o pedido {$orderNsu}: " . $e->getMessage());
            throw new HttpException(
                503,
                'gateway_indisponivel',
                'O provedor de pagamento está indisponível no momento. Tente novamente em instantes.'
            );
        }

        // Provedor não confirmou (identificadores que não batem, pendente ou recusado)
        if (!$verificacao['success'] || !$verificacao['paid']) {
            return $this->resposta($orderNsu, false, false);
        }

        // O valor confirmado precisa ser o do NOSSO pedido
        if ($verificacao['amount'] !== $pagamento['total_centavos']) {
            error_log(sprintf(
                'Valor divergente no pedido %s: esperado %d, confirmado %s.',
                $orderNsu,
                $pagamento['total_centavos'],
                var_export($verificacao['amount'], true)
            ));
            throw new HttpException(
                409,
                'valor_divergente',
                'O valor confirmado pelo provedor não corresponde ao total do pedido. O pedido não foi marcado como pago.'
            );
        }

        $metodo = in_array($verificacao['capture_method'], self::METODOS, true) ? $verificacao['capture_method'] : null;
        // paid_amount pode ser maior que amount (taxas de parcelamento repassadas)
        $valorPago = $verificacao['paid_amount'] ?? $verificacao['amount'];

        $this->pdo->beginTransaction();
        try {
            $mudou = $this->pagamentos->marcarComoPago($pagamento['id'], $transactionNsu, $slug, $metodo, $valorPago);
            if ($mudou) {
                $this->pedidos->atualizarStatus($pagamento['pedido_id'], 'pago');
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // 1062 = a mesma transação já pagou outro pedido
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                error_log("Transação reutilizada na tentativa de confirmar o pedido {$orderNsu}.");
                throw new HttpException(409, 'transacao_ja_utilizada', 'Esta transação já foi utilizada em outro pedido.');
            }
            throw $e;
        }

        // Outra requisição confirmou primeiro: tratamos como repetição
        if (!$mudou) {
            return $this->jaPago($orderNsu, $transactionNsu);
        }

        return $this->resposta($orderNsu, true, false);
    }

    /** @return array<string, mixed> */
    private function jaPago(string $orderNsu, string $transactionNsu): array
    {
        $atual = $this->pagamentos->buscarPorOrderNsu($orderNsu);

        if ($atual === null || !hash_equals((string) ($atual['transaction_nsu'] ?? ''), $transactionNsu)) {
            error_log("Pedido {$orderNsu} já pago, mas recebeu confirmação com outra transação.");
            throw new HttpException(409, 'transacao_divergente', 'Este pedido já foi pago com outra transação.');
        }

        return $this->resposta($orderNsu, true, true);
    }

    /** @return array<string, mixed> */
    private function resposta(string $codigo, bool $confirmado, bool $jaConfirmado): array
    {
        $pedido = $this->pedidos->buscarPorCodigo($codigo);
        $pagamento = $this->pagamentos->buscarPorOrderNsu($codigo);

        $resposta = [
            'codigo'         => $codigo,
            'status'         => $pedido['status'] ?? 'pendente',
            'confirmado'     => $confirmado,
            'ja_confirmado'  => $jaConfirmado,
            'total_centavos' => $pedido['total_centavos'] ?? 0,
            'pagamento'      => [
                'status'              => $pagamento['status'] ?? 'pendente',
                'metodo'              => $pagamento['metodo'] ?? null,
                'valor_pago_centavos' => $pagamento['valor_pago_centavos'] ?? null,
            ],
        ];

        if (!$confirmado) {
            $resposta['mensagem'] = 'Pagamento ainda não confirmado pelo provedor.';
        }

        return $resposta;
    }

    /**
     * @param array<string, mixed> $dados
     * @return array{0: string, 1: string, 2: string}
     */
    private function validar(array $dados): array
    {
        $erros = [];

        $orderNsu = $dados['order_nsu'] ?? null;
        if (!is_string($orderNsu) || !Uuid::valido($orderNsu)) {
            $erros['order_nsu'] = 'Informe o código do pedido (UUID).';
        }

        foreach (['transaction_nsu', 'slug'] as $campo) {
            $valor = $dados[$campo] ?? null;
            if (!is_string($valor) || preg_match('/^[A-Za-z0-9._-]{1,100}$/', $valor) !== 1) {
                $erros[$campo] = "Informe {$campo} (até 100 caracteres: letras, números, ponto, hífen e sublinhado).";
            }
        }

        if ($erros !== []) {
            throw new HttpException(422, 'dados_invalidos', 'Os dados enviados são inválidos.', $erros);
        }

        return [$orderNsu, $dados['transaction_nsu'], $dados['slug']];
    }
}