<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Contrato dos provedores de pagamento (padrão Strategy).
 * Implementações: SimulatedGatewayService (agora) e InfinitePayService (Etapa 4E).
 */
interface PaymentGateway
{
    /** Valor gravado em pagamentos.modo: 'real' ou 'simulado'. */
    public function modo(): string;

    /**
     * Cria o link de checkout para um pedido.
     *
     * @param array{codigo: string, total_centavos: int, comprador: string, email: string,
     *              itens: array<int, array{descricao: string, quantidade: int, preco_centavos: int}>} $pedido
     * @return array{url: string, slug: ?string}
     * @throws GatewayException quando o provedor está indisponível ou responde de forma inválida
     */
    public function createCheckoutLink(array $pedido): array;

    /**
     * Consulta o pagamento no provedor (equivalente ao POST /payment_check da InfinitePay).
     * Quando os identificadores não batem, o provedor responde success=false ou paid=false, sem erro.
     *
     * @param array{order_nsu: string, transaction_nsu: string, slug: string} $dados
     * @return array{success: bool, paid: bool, amount: ?int, paid_amount: ?int,
     *               installments: ?int, capture_method: ?string}
     * @throws GatewayException quando o provedor está indisponível ou responde de forma inválida
     */
    public function checkPayment(array $dados): array;
}