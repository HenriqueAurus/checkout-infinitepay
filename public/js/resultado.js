'use strict';

const raiz = document.getElementById('conteudo');
const params = new URLSearchParams(location.search);
const codigo = params.get('order_nsu') ?? '';
const transacao = params.get('transaction_nsu');
const slug = params.get('slug');
const comprovante = params.get('receipt_url');

const MAX_TENTATIVAS = 4;
const espera = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function linkLoja(classe) {
  const a = App.el('a', 'Voltar à loja', 'botao ' + classe);
  a.href = 'index.html';
  return a;
}

function linha(rotulo, valor, classe) {
  const l = App.el('div', undefined, 'linha ' + (classe ?? ''));
  l.append(App.el('span', rotulo), App.el('span', valor));
  return l;
}

function faixa(texto, classe) {
  return App.el('p', texto, 'faixa ' + classe);
}

function rotuloMetodo(metodo) {
  if (metodo === 'pix') return 'Pix';
  if (metodo === 'credit_card') return 'Cartão de crédito';
  return '—';
}

// O receipt_url vem da URL (dado não confiável): só é exibido se for https do domínio InfinitePay
function linkComprovante() {
  if (!comprovante) return null;
  try {
    const u = new URL(comprovante);
    const confiavel = u.protocol === 'https:'
      && (u.hostname === 'infinitepay.io' || u.hostname.endsWith('.infinitepay.io'));
    if (!confiavel) return null;
    const a = App.el('a', 'Ver comprovante da InfinitePay', 'botao sec');
    a.href = u.href;
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    return a;
  } catch {
    return null;
  }
}

function botaoVerificar() {
  const b = App.el('button', 'Verificar novamente');
  b.type = 'button';
  b.addEventListener('click', () => { b.disabled = true; confirmar(0); });
  return b;
}

function falha(mensagem, permitirNovaTentativa = false) {
  const itens = [App.el('h1', 'Resultado do pagamento'), faixa(mensagem, 'falha')];
  if (permitirNovaTentativa) itens.push(botaoVerificar());
  itens.push(linkLoja('sec'));
  raiz.replaceChildren(...itens);
}

async function buscarPedido() {
  const res = await App.api('api/pedidos/' + encodeURIComponent(codigo));
  if (res.status === 200) return res.corpo.dados;
  falha(res.status === 404 ? 'Pedido não encontrado.' : App.mensagemErro(res));
  return null;
}

function mostrarPedido(pedido, aviso, podeVerificar) {
  const pag = pedido.pagamento;
  const simulado = pag?.modo === 'simulado';
  const selo = App.el('span',
    simulado ? 'SIMULAÇÃO · nenhum dinheiro foi cobrado' : 'Pagamento real via InfinitePay',
    'selo-modo ' + (simulado ? 'simulado' : 'real'));

  raiz.replaceChildren(App.el('h1', 'Resultado do pagamento'), selo, aviso);
  pedido.itens.forEach((i) => raiz.append(linha(
    `${i.nome} × ${i.quantidade}`, App.brl(i.preco_unitario_centavos * i.quantidade))));
  raiz.append(linha('Total do pedido', App.brl(pedido.total_centavos), 'total'));
  if (pag?.valor_pago_centavos != null) raiz.append(linha('Valor pago', App.brl(pag.valor_pago_centavos)));
  if (pag?.metodo) raiz.append(linha('Método', rotuloMetodo(pag.metodo)));
  raiz.append(linha('Status', pag?.status ?? pedido.status));

  const cod = App.el('p', undefined, 'suave');
  cod.append('Pedido: ', App.el('span', pedido.codigo, 'codigo'));
  raiz.append(cod);

  if (pag?.status === 'pago') {
    const comp = linkComprovante();
    if (comp) raiz.append(comp);
  } else if (podeVerificar) {
    raiz.append(botaoVerificar());
  } else {
    const voltar = App.el('a', 'Voltar ao pagamento', 'botao');
    voltar.href = 'pagamento.html?pedido=' + encodeURIComponent(pedido.codigo);
    raiz.append(voltar);
  }
  raiz.append(linkLoja('sec'));
}

// Quem decide se está pago é o backend (payment_check); a URL de retorno sozinha não prova nada
async function confirmar(tentativa) {
  raiz.replaceChildren(
    App.el('h1', 'Resultado do pagamento'),
    App.el('p', 'Confirmando o pagamento com a InfinitePay…', 'suave'));

  const res = await App.api('api/pagamentos/confirmar', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_nsu: codigo, transaction_nsu: transacao, slug }),
  });

  if (res.status === 200 && res.corpo?.dados?.confirmado) {
    const pedido = await buscarPedido();
    if (!pedido) return;
    const repetida = res.corpo.dados.ja_confirmado;
    return mostrarPedido(pedido, faixa(repetida
      ? 'Pagamento confirmado. Esta confirmação já havia sido registrada.'
      : 'Pagamento confirmado! Obrigado pela compra.', 'ok'), false);
  }

  if (res.status === 200) {
    // O provedor ainda não confirmou: tenta algumas vezes antes de desistir
    if (tentativa < MAX_TENTATIVAS) {
      await espera(3000);
      return confirmar(tentativa + 1);
    }
    const pedido = await buscarPedido();
    if (!pedido) return;
    return mostrarPedido(pedido, faixa(
      'A InfinitePay ainda não confirmou este pagamento. Aguarde alguns instantes e verifique novamente.', 'pend'), true);
  }

  falha(App.mensagemErro(res), res.status === 503 || res.status === 0);
}

async function iniciar() {
  if (!App.UUID.test(codigo)) return falha('Pedido inválido.');

  if (!transacao || !slug) {
    // Sem dados de retorno (ex.: "Ver comprovante" da página de pagamento): só consulta o estado
    const pedido = await buscarPedido();
    if (!pedido) return;
    const pago = pedido.pagamento?.status === 'pago';
    return mostrarPedido(pedido, pago
      ? faixa('Pagamento confirmado.', 'ok')
      : faixa('Este pedido ainda não está pago.', 'pend'), false);
  }

  await confirmar(0);
}

iniciar();