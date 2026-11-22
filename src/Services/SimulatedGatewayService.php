<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Repositories\SimulacaoRepository;

/**
 * Gateway SIMULADO: não contacta nenhum provedor e não movimenta dinheiro.
 * O "provedor" aqui é a tabela simulacoes (o que foi escolhido no checkout falso)
 * mais o total do pedido (o valor do "link").
 */
final class SimulatedGatewayService implements PaymentGateway
{
    public function __construct(
        private readonly bool $forcarIndisponibilidade,
        private readonly SimulacaoRepository $simulacoes
    ) {
    }

    public function modo(): string
    {
        return 'simulado';
    }

    public function createCheckoutLink(array $pedido): array
    {
        if ($this->forcarIndisponibilidade) {
            throw new GatewayException('Indisponibilidade simulada do provedor.');
        }

        $base = rtrim(Env::required('APP_URL'), '/');

        return [
            'url'  => $base . '/simulador.html?pedido=' . rawurlencode($pedido['codigo']),
            'slug' => null,
        ];
    }

    public function checkPayment(array $dados): array
    {
        if ($this->forcarIndisponibilidade) {
            throw new GatewayException('Indisponibilidade simulada do provedor.');
        }

        $semDados = ['amount' => null, 'paid_amount' => null, 'installments' => null, 'capture_method' => null];

        $sim = $this->simulacoes->buscarPorOrderNsu($dados['order_nsu']);

        // Identificadores que não batem com o registrado: o provedor responde sem confirmar
        if (
            $sim === null
            || !hash_equals($sim['transaction_nsu'], $dados['transaction_nsu'])
            || !hash_equals($sim['slug'], $dados['slug'])
        ) {
            return ['success' => false, 'paid' => false] + $semDados;
        }

        // payment_check não distingue "pendente" de "recusado": ambos são paid=false
        if (!in_array($sim['resultado'], ['aprovado', 'aprovado_valor_divergente'], true)) {
            return ['success' => true, 'paid' => false] + $semDados;
        }

        $valor = $sim['valor_link_centavos'];
        if ($sim['resultado'] === 'aprovado_valor_divergente') {
            $valor += 100; // cobrança de valor diferente do pedido (R$ 1,00 a mais)
        }

        // Cartão: paid_amount maior que amount (taxas repassadas). O 3% é só ilustrativo.
        $pago = $sim['metodo'] === 'credit_card' ? (int) round($valor * 1.03) : $valor;

        return [
            'success'        => true,
            'paid'           => true,
            'amount'         => $valor,
            'paid_amount'    => $pago,
            'installments'   => 1,
            'capture_method' => $sim['metodo'],
        ];
    }
}