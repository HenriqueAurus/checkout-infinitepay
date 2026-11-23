'use strict';

// Funções compartilhadas pelas páginas (formatação, chamadas à API, segurança)
const App = (() => {
  const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

  // Só para EXIBIR valores: o preço real sempre vem do backend
  const brl = (centavos) => moeda.format(centavos / 100);

  // Cria elementos com textContent (nunca innerHTML), evitando XSS
  function el(tag, texto, classe) {
    const n = document.createElement(tag);
    if (texto !== undefined) n.textContent = texto;
    if (classe) n.className = classe;
    return n;
  }

  // Chama a API própria e devolve { status, corpo }; status 0 = sem conexão
  async function api(caminho, opcoes = {}) {
    try {
      const r = await fetch(caminho, opcoes);
      const corpo = await r.json().catch(() => null);
      return { status: r.status, corpo };
    } catch {
      return { status: 0, corpo: null };
    }
  }

  function mensagemErro(res) {
    if (res.status === 0) return 'Não foi possível falar com o servidor. Verifique se o Apache está ativo.';
    return res.corpo?.erro?.mensagem ?? 'Erro inesperado. Tente novamente.';
  }

  // Só segue para este mesmo servidor (simulador) ou para o domínio da InfinitePay
  function destinoSeguro(url) {
    try {
      const u = new URL(url, location.href);
      const mesmaOrigem = u.origin === location.origin;
      const infinitepay = u.protocol === 'https:'
        && (u.hostname === 'infinitepay.io' || u.hostname.endsWith('.infinitepay.io'));
      return (mesmaOrigem || infinitepay) ? u.href : null;
    } catch {
      return null;
    }
  }

  return { brl, el, api, mensagemErro, destinoSeguro, UUID };
})();