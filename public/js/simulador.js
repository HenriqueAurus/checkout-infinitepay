'use strict';

const raiz = document.getElementById('conteudo');
const codigo = new URLSearchParams(location.search).get('pedido') ?? '';

const CENARIOS = [
  { id: 'pix', titulo: 'Pagar com Pix (aprovado)',
    descricao: 'O provedor confirma o pagamento por Pix.',
    corpo: { resultado: 'aprovado', metodo: 'pix' } },
  { id: 'cartao', titulo: 'Pagar com cartão (aprovado)',
    descricao: 'Aprovado no cartão. O valor pago sai 3% maior (taxa ilustrativa), o que não conta como divergência.',
    corpo: { resultado: 'aprovado', metodo: 'credit_card' } },
  { id: 'recusado', titulo: 'Pagamento recusado',
    descricao: 'O provedor não confirma. O pedido continua pendente.',
    corpo: { resultado: 'recusado' } },
  { id: 'pendente', titulo: 'Pagamento pendente',
    descricao: 'O provedor ainda não confirmou. O pedido continua pendente.',
    corpo: { resultado: 'pendente' } },
  { id: 'divergente', titulo: 'Aprovado com valor diferente do pedido',
    descricao: 'O provedor confirma um valor diferente do total. O backend recusa e não marca como pago.',
    corpo: { resultado: 'aprovado_valor_divergente', metodo: 'pix' } },
];

function link(texto, href, classe) {
  const a = App.el('a', texto, 'botao ' + classe);
  a.href = href;
  return a;
}

function falha(mensagem, ...acoes) {
  raiz.replaceChildren(App.el('p', mensagem, 'aviso'), ...acoes);
}

function linha(rotulo, valor, classe) {
  const l = App.el('div', undefined, 'linha ' + (classe ?? ''));
  l.append(App.el('span', rotulo), App.el('span', valor));
  return l;
}

function opcao(cenario, indice) {
  const rotulo = App.el('label', undefined, 'opcao');
  const radio = document.createElement('input');
  radio.type = 'radio';
  radio.name = 'cenario';
  radio.value = cenario.id;
  radio.checked = indice === 0;
  const texto = App.el('span');
  texto.append(App.el('strong', cenario.titulo), App.el('small', cenario.descricao, 'suave'));
  rotulo.append(radio, texto);
  return rotulo;
}

function formulario(pedido) {
  const form = document.createElement('form');
  form.noValidate = true;
  CENARIOS.forEach((c, i) => form.append(opcao(c, i)));

  const erro = App.el('p', '', 'erro');
  erro.setAttribute('role', 'alert');
  const botao = App.el('button', 'Concluir pagamento simulado');
  botao.type = 'submit';
  form.append(erro, botao);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    erro.textContent = '';
    botao.disabled = true;

    const escolhido = CENARIOS.find((c) => c.id === form.elements.cenario.value);
    const res = await App.api('api/simulador/resolver', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ pedido: pedido.codigo, ...escolhido.corpo }),
    });

    const destino = res.status === 200 ? App.destinoSeguro(res.corpo?.dados?.redirect_url ?? '') : null;
    if (destino) {
      location.href = destino;
      return;
    }

    erro.textContent = res.status === 200 ? 'Resposta inesperada do servidor.' : App.mensagemErro(res);
    botao.disabled = false;
  });

  return form;
}

function mostrar(pedido) {
  const pag = pedido.pagamento;

  if (pag?.modo !== 'simulado') {
    return falha('Este pedido não pertence ao modo de simulação. Use a página de pagamento.',
      link('Ir ao pagamento', 'pagamento.html?pedido=' + encodeURIComponent(pedido.codigo), ''));
  }
  if (pag.status === 'pago') {
    return falha('Este pedido já foi pago.',
      link('Ver resultado', 'resultado.html?order_nsu=' + encodeURIComponent(pedido.codigo), ''));
  }
  if (!pag.checkout_url) {
    return falha('Este pedido não possui um checkout ativo.', link('Voltar à loja', 'index.html', 'sec'));
  }

  raiz.replaceChildren(
    App.el('h1', 'Checkout simulado'),
    App.el('p', 'SIMULAÇÃO · esta tela imita o checkout da InfinitePay. Nenhum dinheiro é cobrado e nenhum dado de cartão é pedido.', 'aviso'),
  );
  pedido.itens.forEach((i) => raiz.append(linha(
    `${i.nome} × ${i.quantidade}`, App.brl(i.preco_unitario_centavos * i.quantidade))));
  raiz.append(linha('Total', App.brl(pedido.total_centavos), 'total'));

  const cod = App.el('p', undefined, 'suave');
  cod.append('Pedido: ', App.el('span', pedido.codigo, 'codigo'));
  raiz.append(cod, App.el('h2', 'Escolha o resultado'), formulario(pedido),
    link('Voltar à loja', 'index.html', 'sec'));
}

async function iniciar() {
  if (!App.UUID.test(codigo)) return falha('Pedido inválido.', link('Voltar à loja', 'index.html', 'sec'));
  const res = await App.api('api/pedidos/' + encodeURIComponent(codigo));
  if (res.status === 404) return falha('Pedido não encontrado.', link('Voltar à loja', 'index.html', 'sec'));
  if (res.status !== 200) return falha(App.mensagemErro(res));
  mostrar(res.corpo.dados);
}

iniciar();