<?php
declare(strict_types=1);

/**
 * Verifica o banco SEM exibir credenciais.
 * Uso: C:\xampp\php\php.exe scripts\check-db.php
 */

use App\Core\Database;

try {
    require __DIR__ . '/../src/bootstrap.php';
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO]  ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$erros = 0;

$linha = static function (string $status, string $mensagem) use (&$erros): void {
    if ($status === 'ERRO') {
        $erros++;
    }
    echo str_pad("[{$status}]", 9) . $mensagem . PHP_EOL;
};

echo 'Verificação do banco de dados' . PHP_EOL . PHP_EOL;

try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    $linha('ERRO', $e->getMessage() . ' Veja o código do erro em logs/php-error.log.');
    exit(1);
}

$linha('OK', 'Conexão PDO estabelecida (versão ' . $pdo->query('SELECT VERSION()')->fetchColumn() . ').');
$linha('OK', 'Banco em uso: ' . $pdo->query('SELECT DATABASE()')->fetchColumn());

// ---- Charset ----
$charset = (string) $pdo->query('SELECT @@character_set_database')->fetchColumn();
$charset === 'utf8mb4'
    ? $linha('OK', 'Charset do banco: utf8mb4')
    : $linha('ERRO', "Charset do banco é {$charset}; esperado utf8mb4.");

// ---- Tabelas ----
$esperadas = ['produtos', 'pedidos', 'itens_pedido', 'pagamentos', 'eventos_webhook', 'simulacoes'];
$existentes = $pdo
    ->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')
    ->fetchAll(PDO::FETCH_COLUMN);

foreach ($esperadas as $tabela) {
    in_array($tabela, $existentes, true)
        ? $linha('OK', "Tabela {$tabela} existe.")
        : $linha('ERRO', "Tabela {$tabela} NÃO existe.");
}

$naoInnoDb = (int) $pdo
    ->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE <> 'InnoDB'")
    ->fetchColumn();
$naoInnoDb === 0
    ? $linha('OK', 'Todas as tabelas usam InnoDB (necessário para chaves estrangeiras e transações).')
    : $linha('ERRO', "{$naoInnoDb} tabela(s) fora do InnoDB.");

$fks = (int) $pdo
    ->query('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()')
    ->fetchColumn();
$fks === 4
    ? $linha('OK', 'Chaves estrangeiras: 4 (itens->pedidos, itens->produtos, pagamentos->pedidos, simulacoes->pagamentos).')
    : $linha('ERRO', "Chaves estrangeiras encontradas: {$fks}; esperado 4.");

// ---- Produtos ----
$produtos = $pdo->query('SELECT id, slug, nome, preco_centavos, ativo FROM produtos ORDER BY id')->fetchAll();
if ($produtos === []) {
    $linha('ERRO', 'Nenhum produto cadastrado.');
} else {
    $linha('OK', count($produtos) . ' produto(s) cadastrado(s):');
    foreach ($produtos as $p) {
        $preco = number_format(((int) $p['preco_centavos']) / 100, 2, ',', '.');
        $linha('INFO', sprintf(
            '  #%d %s | R$ %s | %s',
            (int) $p['id'],
            $p['slug'],
            $preco,
            ((int) $p['ativo'] === 1) ? 'ativo' : 'inativo'
        ));
    }
}

// ---- Testes de integridade (cada um roda numa transação e é desfeito) ----
$rejeita = static function (string $descricao, array $codigosEsperados, callable $acao) use ($pdo, $linha): void {
    $pdo->beginTransaction();
    try {
        $acao();
        $linha('ERRO', "{$descricao}: o banco ACEITOU um dado inválido.");
    } catch (PDOException $e) {
        $codigo = (int) ($e->errorInfo[1] ?? 0);
        in_array($codigo, $codigosEsperados, true)
            ? $linha('OK', "{$descricao}: rejeitado pelo banco (erro {$codigo}).")
            : $linha('ERRO', "{$descricao}: falhou com erro inesperado {$codigo}.");
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
};

echo PHP_EOL;

// 4025 = CHECK no MariaDB, 3819 = CHECK no MySQL 8
$rejeita('Produto com preço abaixo de R$ 1,00', [4025, 3819], function () use ($pdo): void {
    $pdo->exec("INSERT INTO produtos (slug, nome, descricao, preco_centavos) VALUES ('teste-invalido', 'x', 'x', 50)");
});

// 1452 = violação de chave estrangeira
$rejeita('Item apontando para pedido inexistente', [1452], function () use ($pdo): void {
    $pdo->exec('INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario_centavos) VALUES (999999999, 1, 1, 100)');
});

// 1062 = valor duplicado em chave única
$rejeita('Segundo pagamento para o mesmo pedido', [1062], function () use ($pdo): void {
    $pdo->exec("INSERT INTO pedidos (codigo, comprador, email, total_centavos) VALUES (UUID(), 'Teste', 'teste@example.com', 100)");
    $pedidoId = (int) $pdo->lastInsertId();

    $sql = "INSERT INTO pagamentos (pedido_id, modo, order_nsu) VALUES (?, 'simulado', UUID())";
    $pdo->prepare($sql)->execute([$pedidoId]); // este é válido
    $pdo->prepare($sql)->execute([$pedidoId]); // este deve ser rejeitado
});

echo PHP_EOL . ($erros === 0 ? 'Resultado: sem erros.' : "Resultado: {$erros} erro(s) encontrado(s).") . PHP_EOL;
exit($erros === 0 ? 0 : 1);