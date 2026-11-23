'use strict';

const raiz = document.getElementById('conteudo');
const codigo = new URLSearchParams(location.search).get('pedido') ?? '';

function linkLoja(classe) {
  const a = App.el('a', 'Voltar à loja', 'botao ' + classe);
  a.href = 'index.html';
  return a;
}

function falha(mensagem) {
  raiz.replaceChildren(App.el('p', mensagem, 'aviso'), linkLoja('sec'));
}

function linha(rotulo, valor, classe) {
  const l = App.el('div', undefined, 'linha ' + (classe ?? ''));
  l.append(App.el('span', rotulo), App.el('span', valor));
  return l;
}

function acaoDePagamento(pedido) {
  const pag = pedido.pagamento;
  const quadro = App.el('div');

  if (pag?.status === 'pago') {
    const a = App.el('a', 'Ver comprovante do pedido', 'botao');
    a.href = 'resultado.html?order_nsu=' + encodeURIComponent(pedido.codigo);
    quadro.append(App.el('p', 'Este pedido já foi pago.', 'aviso'), a);
    return quadro;
  }

  const destino = pag?.checkout_url ? App.destinoSeguro(pag.checkout_url) : null;
  if (!destino) {
    quadro.append(App.el('p', 'Este pedido não pode mais ser pago. Volte à loja para criar outro.', 'aviso'), linkLoja('sec'));
    return quadro;
  }

  const botao = App.el('button', 'Pagar com InfinitePay');
  botao.type = 'button';
  botao.addEventListener('click', () => { botao.disabled = true; location.href = destino; });
  quadro.append(
    botao,
    App.el('p', 'Você será levado ao ambiente seguro de pagamento (Pix ou cartão). Nenhum dado de cartão é pedido ou guardado por esta loja.', 'suave'),
    linkLoja('sec'),
  );
  return quadro;
}

function mostrar(pedido) {
  const simulado = pedido.pagamento?.modo === 'simulado';
  const selo = App.el('span',
    simulado ? 'SIMULAÇÃO · nenhum dinheiro é cobrado' : 'Pagamento real via InfinitePay',
    'selo-modo ' + (simulado ? 'simulado' : 'real'));

  raiz.replaceChildren(App.el('h1', 'Resumo do pedido'), selo);
  pedido.itens.forEach((i) => raiz.append(linha(
    `${i.nome} × ${i.quantidade}`, App.brl(i.preco_unitario_centavos * i.quantidade))));
  raiz.append(linha('Total', App.brl(pedido.total_centavos), 'total'));

  const cod = App.el('p', undefined, 'suave');
  cod.append('Pedido: ', App.el('span', pedido.codigo, 'codigo'));
  raiz.append(cod, linha('Status', pedido.pagamento?.status ?? pedido.status), acaoDePagamento(pedido));
}

async function iniciar() {
  if (!App.UUID.test(codigo)) return falha('Pedido inválido.');
  const res = await App.api('api/pedidos/' + encodeURIComponent(codigo));
  if (res.status === 404) return falha('Pedido não encontrado.');
  if (res.status !== 200) return falha(App.mensagemErro(res));
  mostrar(res.corpo.dados);
}

iniciar();