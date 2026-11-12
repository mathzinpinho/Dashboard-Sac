// Gráficos do dashboard: área com curva suave, linha-guia e número em destaque que acompanha o mouse.
// Cada cartão traz os dados em data-grafico (gerado em views/dashboard.php):
//   { formato: 'numero' | 'moeda', unidade: ['SAC', 'SACs'] (opcional), pontos: [{ t: título, v: valor, h: link }] }
(function () {
  const SVG = 'http://www.w3.org/2000/svg';
  const numero = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });
  const moeda = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const formatar = (v, formato) => (formato === 'moeda' ? 'R$ ' + moeda.format(v) : numero.format(v));

  const el = (tag, attrs = {}) => {
    const n = document.createElementNS(SVG, tag);
    for (const [k, v] of Object.entries(attrs)) n.setAttribute(k, v);
    return n;
  };

  // curva monotônica (Fritsch–Carlson): suave e nunca passa abaixo de zero nem acima do pico
  function caminhoSuave(p) {
    if (p.length === 1) return `M${p[0][0]},${p[0][1]}`;
    const n = p.length;
    const d = [];
    const m = [];
    for (let i = 0; i < n - 1; i++) d.push((p[i + 1][1] - p[i][1]) / (p[i + 1][0] - p[i][0]));
    m[0] = d[0];
    m[n - 1] = d[n - 2];
    for (let i = 1; i < n - 1; i++) m[i] = d[i - 1] * d[i] <= 0 ? 0 : (d[i - 1] + d[i]) / 2;
    for (let i = 0; i < n - 1; i++) {
      if (d[i] === 0) { m[i] = 0; m[i + 1] = 0; continue; }
      const a = m[i] / d[i];
      const b = m[i + 1] / d[i];
      const s = a * a + b * b;
      if (s > 9) { const t = 3 / Math.sqrt(s); m[i] = t * a * d[i]; m[i + 1] = t * b * d[i]; }
    }
    let c = `M${p[0][0]},${p[0][1]}`;
    for (let i = 0; i < n - 1; i++) {
      const h = (p[i + 1][0] - p[i][0]) / 3;
      c += ` C${p[i][0] + h},${p[i][1] + m[i] * h} ${p[i + 1][0] - h},${p[i + 1][1] - m[i + 1] * h} ${p[i + 1][0]},${p[i + 1][1]}`;
    }
    return c;
  }

  // teto do eixo um pouco acima do pico, arredondado para 1, 2, 2,5 ou 5 × 10^n
  function teto(max) {
    if (max <= 0) return 1;
    const alvo = max * 1.15;
    const passo = 10 ** Math.floor(Math.log10(alvo));
    for (const m of [1, 2, 2.5, 5, 10]) if (m * passo >= alvo) return m * passo;
    return alvo;
  }

  let seq = 0;

  function montar(cartao) {
    const dados = JSON.parse(cartao.dataset.grafico);
    const pontos = dados.pontos;
    const area = cartao.querySelector('[data-area]');
    const hero = cartao.querySelector('[data-hero]');
    const legenda = cartao.querySelector('[data-hero-legenda]');
    const heroInicial = hero.textContent;
    const legendaInicial = legenda.textContent;
    const idGrad = 'grad-' + (++seq);

    const dica = document.createElement('div');
    dica.className = 'grafico-dica';
    dica.hidden = true;
    area.append(dica);

    let coords = [];
    let atual = -1;
    let svg;

    function desenhar() {
      if (svg) svg.remove();
      const W = area.clientWidth;
      const H = area.clientHeight;
      const topo = 10;
      const base = H - 2;
      const max = teto(Math.max(...pontos.map((p) => p.v)));
      const x = (i) => (pontos.length === 1 ? W / 2 : (i / (pontos.length - 1)) * W);
      const y = (v) => base - (v / max) * (base - topo);
      coords = pontos.map((p, i) => [x(i), y(p.v)]);

      svg = el('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}`, class: 'grafico-svg', 'aria-hidden': 'true' });

      const defs = el('defs');
      const grad = el('linearGradient', { id: idGrad, x1: 0, y1: 0, x2: 0, y2: 1 });
      grad.append(el('stop', { offset: '0%', class: 'grad-topo' }), el('stop', { offset: '100%', class: 'grad-base' }));
      defs.append(grad);
      svg.append(defs);

      // grade discreta: 4 linhas horizontais e verticais a cada ~56px
      const grade = el('g', { class: 'grafico-grade' });
      for (let k = 1; k <= 4; k++) {
        const gy = topo + ((base - topo) * k) / 4;
        grade.append(el('line', { x1: 0, x2: W, y1: gy, y2: gy }));
      }
      const colunas = Math.max(1, Math.round(W / 56));
      for (let k = 1; k < colunas; k++) {
        const gx = (W * k) / colunas;
        grade.append(el('line', { x1: gx, x2: gx, y1: topo, y2: base }));
      }
      svg.append(grade);

      const linha = caminhoSuave(coords);
      if (coords.length > 1) {
        svg.append(el('path', { d: `${linha} L${W},${base} L0,${base} Z`, fill: `url(#${idGrad})`, class: 'grafico-preenchimento' }));
      }
      svg.append(el('path', { d: linha, class: 'grafico-linha' }));
      // um único ponto (período de um dia) não forma curva: mostra o ponto
      if (coords.length === 1) svg.append(el('circle', { class: 'grafico-marca', r: 5, cx: coords[0][0], cy: coords[0][1] }));

      const guia = el('line', { class: 'grafico-guia', y1: topo - 6, y2: base, x1: 0, x2: 0, visibility: 'hidden' });
      const marca = el('circle', { class: 'grafico-marca', r: 5, cx: 0, cy: 0, visibility: 'hidden' });
      svg.append(guia, marca);
      svg.guia = guia;
      svg.marca = marca;

      area.prepend(svg);
      if (atual >= 0) mostrar(atual);
    }

    function mostrar(i) {
      atual = i;
      const [cx, cy] = coords[i];
      const p = pontos[i];
      svg.guia.setAttribute('x1', cx);
      svg.guia.setAttribute('x2', cx);
      svg.guia.setAttribute('visibility', 'visible');
      svg.marca.setAttribute('cx', cx);
      svg.marca.setAttribute('cy', cy);
      svg.marca.setAttribute('visibility', 'visible');

      hero.textContent = formatar(p.v, dados.formato);
      legenda.textContent = p.t;
      cartao.classList.add('em-foco');

      dica.innerHTML = '';
      const t = document.createElement('span');
      t.textContent = p.t;
      const v = document.createElement('strong');
      v.textContent = formatar(p.v, dados.formato) + (dados.unidade ? ' ' + dados.unidade[p.v === 1 ? 0 : 1] : '');
      dica.append(t, v);
      dica.hidden = false;
      // a caixa fica ao lado do ponto, sem sair da área
      const largura = dica.offsetWidth;
      const W = area.clientWidth;
      let left = cx + 12;
      if (left + largura > W) left = cx - largura - 12;
      dica.style.left = Math.max(0, left) + 'px';
      dica.style.top = Math.max(0, Math.min(cy - dica.offsetHeight - 10, area.clientHeight - dica.offsetHeight)) + 'px';
    }

    function esconder() {
      atual = -1;
      if (svg) {
        svg.guia.setAttribute('visibility', 'hidden');
        svg.marca.setAttribute('visibility', 'hidden');
      }
      dica.hidden = true;
      hero.textContent = heroInicial;
      legenda.textContent = legendaInicial;
      cartao.classList.remove('em-foco');
    }

    const indicePorX = (clientX) => {
      const r = area.getBoundingClientRect();
      const px = clientX - r.left;
      if (pontos.length === 1) return 0;
      const i = Math.round((px / r.width) * (pontos.length - 1));
      return Math.max(0, Math.min(pontos.length - 1, i));
    };

    area.addEventListener('pointermove', (ev) => mostrar(indicePorX(ev.clientX)));
    area.addEventListener('pointerdown', (ev) => mostrar(indicePorX(ev.clientX)));
    area.addEventListener('pointerleave', (ev) => { if (ev.pointerType === 'mouse') esconder(); });
    area.addEventListener('click', (ev) => {
      // no mouse, clicar abre as reclamações do ponto; no toque, o primeiro toque só mostra o valor
      if (ev.pointerType === 'touch' || atual < 0) return;
      if (pontos[atual].h) window.location = pontos[atual].h;
    });

    // teclado: setas percorrem os pontos, Enter abre, Esc sai
    area.addEventListener('keydown', (ev) => {
      if (ev.key === 'ArrowRight' || ev.key === 'ArrowLeft') {
        ev.preventDefault();
        const passo = ev.key === 'ArrowRight' ? 1 : -1;
        mostrar(Math.max(0, Math.min(pontos.length - 1, (atual < 0 ? (passo > 0 ? -1 : pontos.length) : atual) + passo)));
      } else if (ev.key === 'Enter' && atual >= 0 && pontos[atual].h) {
        window.location = pontos[atual].h;
      } else if (ev.key === 'Escape') {
        esconder();
      }
    });
    area.addEventListener('blur', esconder);

    desenhar();
    new ResizeObserver(() => desenhar()).observe(area);
  }

  // rankings: o número em destaque do cartão mostra o item sob o mouse
  function rankingReativo(cartao) {
    const hero = cartao.querySelector('[data-hero]');
    const legenda = cartao.querySelector('[data-hero-legenda]');
    const heroInicial = hero.textContent;
    const legendaInicial = legenda.textContent;
    const voltar = () => {
      hero.textContent = heroInicial;
      legenda.textContent = legendaInicial;
      cartao.classList.remove('em-foco');
    };
    cartao.querySelectorAll('[data-item-valor]').forEach((item) => {
      const focar = () => {
        hero.textContent = item.dataset.itemValor;
        legenda.textContent = item.dataset.itemTexto;
        cartao.classList.add('em-foco');
      };
      item.addEventListener('pointerenter', focar);
      item.addEventListener('focus', focar);
      item.addEventListener('blur', voltar);
    });
    cartao.querySelector('.ranking').addEventListener('pointerleave', voltar);
  }

  document.querySelectorAll('[data-grafico]').forEach(montar);
  document.querySelectorAll('[data-ranking-reativo]').forEach(rankingReativo);
})();
