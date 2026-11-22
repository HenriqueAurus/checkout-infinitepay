<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Uuid;
use App\Repositories\PagamentoRepository;
use App\Repositories\PedidoRepository;
use App\Repositories\ProdutoRepository;
use PDO;
use Throwable;

/** Regras de negócio do início do checkout. */
final class CheckoutService
{
    private const MAX_TOTAL_CENTAVOS = 4294967295; // limite do INT UNSIGNED da coluna

    public function __construct(
        private readonly PDO $pdo,
        private readonly ProdutoRepository $produtos,
        private readonly PedidoRepository $pedidos,
        private readonly PagamentoRepository $pagamentos,
        private readonly PaymentGateway $gateway
    ) {
    }

    /**
     * @param array<string, mixed> $dados corpo JSON da requisição
     * @return array<string, mixed>
     */
    public function iniciar(array $dados): array
    {
        [$produtoId, $quantidade, $comprador, $email] = $this->validar($dados);

        $produto = $this->produtos->buscarAtivoPorId($produtoId);
        if ($produto === null) {
            throw $this->dadosInvalidos(['produto_id' => 'Produto não encontrado ou indisponível.']);
        }

        // O preço vem SEMPRE do banco, nunca do cliente
        $precoUnitario = $produto['preco_centavos'];
        $total = $precoUnitario * $quantidade;
        if ($total > self::MAX_TOTAL_CENTAVOS) {
            throw $this->dadosInvalidos(['quantidade' => 'Quantidade acima do permitido.']);
        }

        $codigo = Uuid::v4();

        // Pedido + item + pagamento: tudo ou nada
        $this->pdo->beginTransaction();
        try {
            $pedidoId = $this->pedidos->criar($codigo, $comprador, $email, $total);
            $this->pedidos->adicionarItem($pedidoId, $produto['id'], $quantidade, $precoUnitario);
            $pagamentoId = $this->pagamentos->criar($pedidoId, $this->gateway->modo(), $codigo);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        // Chamada ao provedor FORA da transação
        try {
            $link = $this->gateway->createCheckoutLink([
                'codigo'         => $codigo,
                'total_centavos' => $total,
                'comprador'      => $comprador,
                'email'          => $email,
                'itens'          => [[
                    'descricao'      => $produto['nome'],
                    'quantidade'     => $quantidade,
                    'preco_centavos' => $precoUnitario,
                ]],
            ]);
        } catch (GatewayException $e) {
            error_log("Gateway falhou ao criar o checkout do pedido {$codigo}: " . $e->getMessage());
            $this->marcarFalha($pedidoId, $pagamentoId);
            throw new HttpException(
                503,
                'gateway_indisponivel',
                'O provedor de pagamento está indisponível no momento. Tente novamente em instantes.'
            );
        }

        // Guarda a URL ANTES de qualquer outra coisa (a API do provedor não permite recuperá-la depois)
        $this->pagamentos->definirCheckout($pagamentoId, $link['url'], $link['slug']);

        return [
            'codigo'         => $codigo,
            'status'         => 'pendente',
            'total_centavos' => $total,
            'itens'          => [[
                'produto_id'              => $produto['id'],
                'nome'                    => $produto['nome'],
                'quantidade'              => $quantidade,
                'preco_unitario_centavos' => $precoUnitario,
            ]],
            'pagamento'      => [
                'modo'         => $this->gateway->modo(),
                'status'       => 'pendente',
                'checkout_url' => $link['url'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $dados
     * @return array{0: int, 1: int, 2: string, 3: string}
     */
    private function validar(array $dados): array
    {
        $erros = [];

        $produtoId = $dados['produto_id'] ?? null;
        if (!is_int($produtoId) || $produtoId < 1) {
            $erros['produto_id'] = 'Informe um produto válido (número inteiro positivo).';
        }

        $quantidade = array_key_exists('quantidade', $dados) ? $dados['quantidade'] : 1;
        if (!is_int($quantidade) || $quantidade < 1 || $quantidade > 99) {
            $erros['quantidade'] = 'A quantidade deve ser um número inteiro entre 1 e 99.';
        }

        $comprador = $dados['comprador'] ?? null;
        if (!is_string($comprador)) {
            $erros['comprador'] = 'Informe o nome do comprador.';
        } else {
            $comprador = trim($comprador);
            $tamanho = mb_strlen($comprador);
            if ($tamanho < 2 || $tamanho > 120 || preg_match('/[\x00-\x1F\x7F]/u', $comprador) === 1) {
                $erros['comprador'] = 'O nome deve ter entre 2 e 120 caracteres, sem caracteres de controle.';
            }
        }

        $email = $dados['email'] ?? null;
        if (!is_string($email)) {
            $erros['email'] = 'Informe um e-mail válido.';
        } else {
            $email = trim($email);
            if (strlen($email) > 160 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $erros['email'] = 'Informe um e-mail válido (até 160 caracteres).';
            }
        }

        if ($erros !== []) {
            throw $this->dadosInvalidos($erros);
        }

        return [$produtoId, $quantidade, $comprador, $email];
    }

    /** @param array<string, string> $campos */
    private function dadosInvalidos(array $campos): HttpException
    {
        return new HttpException(422, 'dados_invalidos', 'Os dados enviados são inválidos.', $campos);
    }

    private function marcarFalha(int $pedidoId, int $pagamentoId): void
    {
        try {
            $this->pagamentos->atualizarStatus($pagamentoId, 'falhou');
            $this->pedidos->atualizarStatus($pedidoId, 'falhou');
        } catch (Throwable $e) {
            error_log('Não foi possível marcar o pedido como falhou: ' . get_class($e));
        }
    }
}