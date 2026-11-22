-- Loja fictícia + Checkout InfinitePay (projeto acadêmico)
-- Pode ser executado mais de uma vez sem duplicar produtos.

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS checkout_infinitepay
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE checkout_infinitepay;

-- ---------------------------------------------------------------
-- produtos: catálogo (preços em centavos, nunca ponto flutuante)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS produtos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug            VARCHAR(80)  NOT NULL,
  nome            VARCHAR(120) NOT NULL,
  descricao       VARCHAR(500) NOT NULL,
  imagem_url      VARCHAR(255) NULL,
  preco_centavos  INT UNSIGNED NOT NULL,
  ativo           TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_produtos_slug (slug),
  CONSTRAINT chk_produtos_preco CHECK (preco_centavos >= 100)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- pedidos: "codigo" é o identificador público (UUID) e vira o order_nsu
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedidos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo          CHAR(36)     NOT NULL,
  comprador       VARCHAR(120) NOT NULL,
  email           VARCHAR(160) NOT NULL,
  total_centavos  INT UNSIGNED NOT NULL,
  status          ENUM('pendente','pago','falhou','cancelado') NOT NULL DEFAULT 'pendente',
  criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pedidos_codigo (codigo),
  CONSTRAINT chk_pedidos_total CHECK (total_centavos >= 100)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- itens_pedido: guarda o preço unitário NO MOMENTO da compra
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS itens_pedido (
  id                       BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  pedido_id                BIGINT UNSIGNED   NOT NULL,
  produto_id               INT UNSIGNED      NOT NULL,
  quantidade               SMALLINT UNSIGNED NOT NULL,
  preco_unitario_centavos  INT UNSIGNED      NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_itens_pedido_produto (pedido_id, produto_id),
  CONSTRAINT fk_itens_pedido FOREIGN KEY (pedido_id)
    REFERENCES pedidos (id) ON DELETE CASCADE,
  CONSTRAINT fk_itens_produto FOREIGN KEY (produto_id)
    REFERENCES produtos (id) ON DELETE RESTRICT,
  CONSTRAINT chk_itens_quantidade CHECK (quantidade BETWEEN 1 AND 99),
  CONSTRAINT chk_itens_preco CHECK (preco_unitario_centavos >= 100)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- pagamentos: um por pedido. Identificadores da InfinitePay:
--   order_nsu       = nosso código do pedido
--   slug            = código da fatura (retornado na redirect_url)
--   transaction_nsu = ID único da transação (retornado ao pagar)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagamentos (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pedido_id            BIGINT UNSIGNED NOT NULL,
  provedor             VARCHAR(30)     NOT NULL DEFAULT 'infinitepay',
  modo                 ENUM('real','simulado') NOT NULL,
  order_nsu            CHAR(36)        NOT NULL,
  slug                 VARCHAR(100)    NULL,
  transaction_nsu      VARCHAR(100)    NULL,
  metodo               VARCHAR(20)     NULL,
  status               ENUM('pendente','pago','falhou','cancelado') NOT NULL DEFAULT 'pendente',
  checkout_url         VARCHAR(1000)   NULL,
  receipt_url          VARCHAR(1000)   NULL,
  valor_pago_centavos  INT UNSIGNED    NULL,
  criado_em            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pagamentos_pedido (pedido_id),
  UNIQUE KEY uq_pagamentos_order_nsu (order_nsu),
  UNIQUE KEY uq_pagamentos_transaction (transaction_nsu),
  KEY idx_pagamentos_slug (slug),
  CONSTRAINT fk_pagamentos_pedido FOREIGN KEY (pedido_id)
    REFERENCES pedidos (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- eventos_webhook: registro das notificações recebidas (sem FK de propósito)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS eventos_webhook (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_nsu        VARCHAR(100) NULL,
  transaction_nsu  VARCHAR(100) NULL,
  corpo            TEXT         NOT NULL,
  resultado        VARCHAR(40)  NOT NULL,
  recebido_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_eventos_order_nsu (order_nsu)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Produtos fictícios (as imagens serão criadas na Etapa 5)
-- ---------------------------------------------------------------
INSERT INTO produtos (slug, nome, descricao, imagem_url, preco_centavos, ativo) VALUES
  ('caneca-aurora',  'Caneca Aurora',
   'Caneca de cerâmica de 350 ml com acabamento fosco.',
   'img/caneca-aurora.svg', 4990, 1),
  ('camiseta-orbit', 'Camiseta Orbit',
   'Camiseta de algodão com estampa minimalista.',
   'img/camiseta-orbit.svg', 7990, 1),
  ('produto-teste',  'Produto de teste (R$ 1,00)',
   'Item simbólico para validar a integração real com o valor mínimo.',
   'img/produto-teste.svg', 100, 1)
ON DUPLICATE KEY UPDATE
  nome           = VALUES(nome),
  descricao      = VALUES(descricao),
  imagem_url     = VALUES(imagem_url),
  preco_centavos = VALUES(preco_centavos),
  ativo          = VALUES(ativo);

  -- ---------------------------------------------------------------
-- simulacoes: resultado escolhido no checkout FALSO (só existe no modo simulation)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS simulacoes (
  pagamento_id     BIGINT UNSIGNED NOT NULL,
  resultado        ENUM('aprovado','recusado','pendente','aprovado_valor_divergente') NOT NULL,
  metodo           ENUM('pix','credit_card') NULL,
  transaction_nsu  VARCHAR(100)    NOT NULL,
  slug             VARCHAR(100)    NOT NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pagamento_id),
  UNIQUE KEY uq_simulacoes_transaction (transaction_nsu),
  CONSTRAINT fk_simulacoes_pagamento FOREIGN KEY (pagamento_id)
    REFERENCES pagamentos (id) ON DELETE CASCADE
) ENGINE=InnoDB;