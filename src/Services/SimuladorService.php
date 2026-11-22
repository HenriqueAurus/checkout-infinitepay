<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\HttpException;
use App\Core\Uuid;
use App\Repositories\PagamentoRepository;
use App\Repositories\SimulacaoRepository;

/**
 * Simula o que o cliente faz no checkout da InfinitePay.
 * NÃO altera o status do pedido nem do pagamento: quem decide é a confirmação (Etapa 4D).
 */
final class SimuladorService
{
    private const RESULTADOS = ['aprovado', 'recusado', 'pendente', 'aprovado_valor_divergente'];
    private const METODOS = ['pix', 'credit_card'];

    public function __construct(
        private readonly PagamentoRepository $pagamentos,
        private readonly SimulacaoRepository $simulacoes
    ) {
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    public function resolver(array $dados): array
    {
        [$codigo, $resultado, $metodo] = $this->validar($dados);

        $pagamento = $this->pagamentos->buscarPorOrderNsu($codigo);
        if ($pagamento === null) {
            throw new HttpException(404, 'pedido_nao_encontrado', 'Pedido não encontrado.');
        }
        if ($pagamento['modo'] !== 'simulado') {
            throw new HttpException(409, 'pedido_nao_simulado', 'Este pedido não pertence ao modo de simulação.');
        }
        if ($pagamento['checkout_url'] === null) {
            throw new HttpException(409, 'pedido_sem_checkout', 'Este pedido não possui um checkout ativo.');
        }
        if ($pagamento['status'] === 'pago') {
            throw new HttpException(409, 'pedido_ja_pago', 'Este pedido já está pago.');
        }

        // Cada tentativa gera identificadores novos, como numa tentativa real
        $transactionNsu = Uuid::v4();
        $slug = 'SIM-' . strtoupper(bin2hex(random_bytes(4)));

        $this->simulacoes->registrar($pagamento['id'], $resultado, $metodo, $transactionNsu, $slug);

        // Imita a redirect_url da InfinitePay (order_nsu, slug, transaction_nsu e capture_method)
        $parametros = array_filter([
            'order_nsu'       => $codigo,
            'slug'            => $slug,
            'transaction_nsu' => $transactionNsu,
            'capture_method'  => $metodo,
        ], static fn (?string $v): bool => $v !== null);

        return [
            'order_nsu'       => $codigo,
            'transaction_nsu' => $transactionNsu,
            'slug'            => $slug,
            'capture_method'  => $metodo,
            'redirect_url'    => rtrim(Env::required('APP_URL'), '/') . '/resultado.html?' . http_build_query($parametros),
        ];
    }

    /**
     * @param array<string, mixed> $dados
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function validar(array $dados): array
    {
        $erros = [];

        $pedido = $dados['pedido'] ?? null;
        if (!is_string($pedido) || !Uuid::valido($pedido)) {
            $erros['pedido'] = 'Informe o código do pedido (UUID).';
        }

        $resultado = $dados['resultado'] ?? null;
        if (!is_string($resultado) || !in_array($resultado, self::RESULTADOS, true)) {
            $erros['resultado'] = 'Use: aprovado, recusado, pendente ou aprovado_valor_divergente.';
        }

        $metodo = $dados['metodo'] ?? null;
        if ($metodo !== null && (!is_string($metodo) || !in_array($metodo, self::METODOS, true))) {
            $erros['metodo'] = 'Use: pix ou credit_card.';
        } elseif ($metodo === null && in_array($resultado, ['aprovado', 'aprovado_valor_divergente'], true)) {
            $erros['metodo'] = 'Informe o método (pix ou credit_card) para pagamentos aprovados.';
        }

        if ($erros !== []) {
            throw new HttpException(422, 'dados_invalidos', 'Os dados enviados são inválidos.', $erros);
        }

        return [$pedido, $resultado, $metodo];
    }
}