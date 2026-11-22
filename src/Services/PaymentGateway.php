<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Contrato dos provedores de pagamento (padrão Strategy).
 * Implementações: SimulatedGatewayService (agora) e InfinitePayService (Etapa 4D).
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
}