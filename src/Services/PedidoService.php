<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Uuid;
use App\Repositories\PagamentoRepository;
use App\Repositories\PedidoRepository;
use DateTimeImmutable;
use DateTimeZone;

final class PedidoService
{
    public function __construct(
        private readonly PedidoRepository $pedidos,
        private readonly PagamentoRepository $pagamentos
    ) {
    }

    /** @return array<string, mixed> */
    public function consultar(string $codigo): array
    {
        // Código malformado e código inexistente respondem igual (404)
        if (!Uuid::valido($codigo)) {
            throw $this->naoEncontrado();
        }

        $pedido = $this->pedidos->buscarPorCodigo($codigo);
        if ($pedido === null) {
            throw $this->naoEncontrado();
        }

        $pagamento = $this->pagamentos->buscarPorPedidoId($pedido['id']);

        $pagamentoPublico = null;
        if ($pagamento !== null) {
            $linkAtivo = in_array($pagamento['status'], ['pendente', 'falhou'], true);

            $pagamentoPublico = [
                'modo'                => $pagamento['modo'],
                'status'              => $pagamento['status'],
                'metodo'              => $pagamento['metodo'],
                'valor_pago_centavos' => $pagamento['valor_pago_centavos'],
                'checkout_url'        => $linkAtivo ? $pagamento['checkout_url'] : null,
            ];
        }

        return [
            'codigo'         => $pedido['codigo'],
            'status'         => $pedido['status'],
            'total_centavos' => $pedido['total_centavos'],
            'criado_em'      => (new DateTimeImmutable($pedido['criado_em'], new DateTimeZone('-03:00')))->format('c'),
            'itens'          => $this->pedidos->listarItens($pedido['id']),
            'pagamento'      => $pagamentoPublico,
        ];
    }

    private function naoEncontrado(): HttpException
    {
        return new HttpException(404, 'nao_encontrado', 'Recurso não encontrado.');
    }
}