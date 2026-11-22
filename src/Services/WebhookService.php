<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Uuid;
use App\Repositories\EventoWebhookRepository;
use App\Repositories\PagamentoRepository;

/**
 * Trata notificações da InfinitePay. O webhook não tem assinatura, então NADA do que vem
 * no corpo é confiável: só os identificadores são extraídos e repassados à confirmação,
 * que consulta o gateway (payment_check) e decide.
 */
final class WebhookService
{
    private const PADRAO_ID = '/^[A-Za-z0-9._-]{1,100}$/';

    public function __construct(
        private readonly PagamentoService $pagamentoService,
        private readonly PagamentoRepository $pagamentos,
        private readonly EventoWebhookRepository $eventos
    ) {
    }

    /**
     * @param array<string, mixed> $dados
     * @return array{recebido: bool, processado: bool}
     */
    public function processar(array $dados): array
    {
        $orderNsu = $dados['order_nsu'] ?? null;
        $transactionNsu = $dados['transaction_nsu'] ?? null;
        $slug = $dados['invoice_slug'] ?? null;

        if (
            !is_string($orderNsu) || !Uuid::valido($orderNsu)
            || !is_string($transactionNsu) || preg_match(self::PADRAO_ID, $transactionNsu) !== 1
            || !is_string($slug) || preg_match(self::PADRAO_ID, $slug) !== 1
        ) {
            throw new HttpException(400, 'webhook_invalido', 'Notificação inválida.');
        }

        // Pedido desconhecido: repetir não adianta e nada é gravado (evita encher o banco)
        if ($this->pagamentos->buscarPorOrderNsu($orderNsu) === null) {
            return ['recebido' => true, 'processado' => false];
        }

        $corpo = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        try {
            $resultado = $this->pagamentoService->confirmar([
                'order_nsu'       => $orderNsu,
                'transaction_nsu' => $transactionNsu,
                'slug'            => $slug,
            ]);
        } catch (HttpException $e) {
            if ($e->getStatus() === 503) {
                // Provedor fora do ar: responder não-2xx faz a InfinitePay tentar de novo
                $this->eventos->registrar($orderNsu, $transactionNsu, $corpo, 'indisponivel');
                throw $e;
            }

            // Conflitos (valor divergente, outra transação...): repetir não resolve
            $this->eventos->registrar($orderNsu, $transactionNsu, $corpo, 'conflito:' . $e->getCodigo());

            return ['recebido' => true, 'processado' => false];
        }

        if (!$resultado['confirmado']) {
            // Pode ser atraso do provedor; responder 400 permite nova tentativa
            $this->eventos->registrar($orderNsu, $transactionNsu, $corpo, 'nao_confirmado');
            throw new HttpException(400, 'nao_confirmado', 'Pagamento ainda não confirmado pelo provedor.');
        }

        $this->eventos->registrar(
            $orderNsu,
            $transactionNsu,
            $corpo,
            $resultado['ja_confirmado'] ? 'ja_confirmado' : 'confirmado'
        );

        return ['recebido' => true, 'processado' => true];
    }
}