'use strict';

const lista = document.getElementById('produtos');
const aviso = document.getElementById('aviso');
const dlg = document.getElementById('dlg');
const form = document.getElementById('form');
const enviar = document.getElementById('enviar');
const erroGeral = document.getElementById('erro-geral');
let escolhido = null;

function cartao(p) {
  const c = App.el('article', undefined, 'cartao');
  const img = App.el('img');
  img.src = p.imagem_url ?? '';
  img.alt = p.nome;
  img.width = 320;
  img.height = 200;
  const botao = App.el('button', 'Comprar');
  botao.type = 'button';
  botao.addEventListener('click', () => abrir(p));
  c.append(img, App.el('h2', p.nome), App.el('p', p.descricao, 'suave'),
           App.el('p', App.brl(p.preco_centavos), 'preco'), botao);
  return c;
}

function limparErros() {
  form.querySelectorAll('[data-erro]').forEach((n) => { n.textContent = ''; });
  erroGeral.textContent = '';
}

function abrir(produto) {
  escolhido = produto;
  form.reset();
  limparErros();
  document.getElementById('resumo').textContent = `${produto.nome} · ${App.brl(produto.preco_centavos)} por unidade`;
  dlg.showModal();
}

async function carregar() {
  const res = await App.api('api/produtos');
  if (res.status !== 200) {
    aviso.textContent = App.mensagemErro(res);
    aviso.hidden = false;
    return;
  }
  res.corpo.dados.forEach((p) => lista.append(cartao(p)));
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  limparErros();
  enviar.disabled = true;

  const dados = new FormData(form);
  // O preço NÃO é enviado: o backend o lê do banco
  const corpo = {
    produto_id: escolhido.id,
    quantidade: Number.parseInt(dados.get('quantidade'), 10),
    comprador: String(dados.get('comprador')).trim(),
    email: String(dados.get('email')).trim(),
  };

  const res = await App.api('api/checkout', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(corpo),
  });

  if (res.status === 201) {
    location.href = 'pagamento.html?pedido=' + encodeURIComponent(res.corpo.dados.codigo);
    return;
  }

  const detalhes = res.corpo?.erro?.detalhes ?? {};
  let mostrouCampo = false;
  for (const [campo, mensagem] of Object.entries(detalhes)) {
    const alvo = form.querySelector(`[data-erro="${campo}"]`);
    if (alvo) { alvo.textContent = mensagem; mostrouCampo = true; }
  }
  if (!mostrouCampo) erroGeral.textContent = App.mensagemErro(res);
  enviar.disabled = false;
});

document.getElementById('cancelar').addEventListener('click', () => dlg.close());
carregar();