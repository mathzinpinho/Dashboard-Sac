// Pequenos comportamentos de interface. Todo o resto é PHP.
(function () {
  // dica (tooltip) dos gráficos
  const dica = document.getElementById('dica');
  if (dica) {
    document.addEventListener('mouseover', (ev) => {
      const alvo = ev.target.closest('[data-dica]');
      if (!alvo) { dica.hidden = true; return; }
      dica.textContent = alvo.dataset.dica;
      dica.hidden = false;
    });
    document.addEventListener('mousemove', (ev) => {
      if (dica.hidden) return;
      const x = Math.min(ev.clientX + 14, window.innerWidth - dica.offsetWidth - 8);
      dica.style.left = x + 'px';
      dica.style.top = (ev.clientY + 16) + 'px';
    });
  }

  // selects que filtram ao mudar
  document.querySelectorAll('[data-auto-enviar]').forEach((s) => {
    s.addEventListener('change', () => s.form.submit());
  });

  // linhas de tabela clicáveis
  document.querySelectorAll('tr[data-href]').forEach((tr) => {
    tr.addEventListener('click', (ev) => {
      if (ev.target.closest('a, button')) return;
      window.location = tr.dataset.href;
    });
  });

  // confirmação antes de excluir
  document.querySelectorAll('form[data-confirmar]').forEach((f) => {
    f.addEventListener('submit', (ev) => {
      if (!confirm(f.dataset.confirmar)) ev.preventDefault();
    });
  });

  // "Finalizar agora" preenche data e hora atuais
  const btnFinalizar = document.querySelector('[data-finalizar-agora]');
  if (btnFinalizar) {
    btnFinalizar.addEventListener('click', () => {
      const agora = new Date();
      const p = (n) => String(n).padStart(2, '0');
      const form = btnFinalizar.closest('form');
      form.data_finalizacao.value = `${agora.getFullYear()}-${p(agora.getMonth() + 1)}-${p(agora.getDate())}`;
      form.hora_finalizacao.value = `${p(agora.getHours())}:${p(agora.getMinutes())}`;
      form.solucao.focus();
    });
  }

  // ao escolher uma razão social já cadastrada, completa cliente e contato
  const dadosClientes = document.getElementById('dados-clientes');
  const razao = document.getElementById('razao_social');
  if (dadosClientes && razao) {
    const clientes = JSON.parse(dadosClientes.textContent || '{}');
    razao.addEventListener('change', () => {
      const c = clientes[razao.value];
      if (!c) return;
      const cliente = document.getElementById('cliente');
      const contato = document.getElementById('contato');
      if (!cliente.value) cliente.value = c[0] || '';
      if (!contato.value) contato.value = c[1] || '';
    });
  }

  // apagar todas: o botão só libera com a frase exata, a senha e a caixa marcada
  const formApagar = document.querySelector('[data-apagar-tudo]');
  if (formApagar) {
    const frase = formApagar.querySelector('[data-frase]');
    const botao = formApagar.querySelector('[data-botao-apagar]');
    frase.addEventListener('input', () => {
      const pos = frase.selectionStart;
      frase.value = frase.value.toUpperCase();
      frase.setSelectionRange(pos, pos);
    });
    const conferir = () => {
      botao.disabled = !(frase.value.trim() === frase.dataset.frase
        && formApagar.senha.value !== ''
        && formApagar.entendo.checked);
    };
    formApagar.addEventListener('input', conferir);
    formApagar.addEventListener('change', conferir);
    formApagar.addEventListener('submit', (ev) => {
      if (!confirm('Última confirmação: apagar TODAS as reclamações agora?')) ev.preventDefault();
    });
    conferir();
  }

  // área de soltar arquivo na importação
  const soltar = document.querySelector('[data-soltar]');
  if (soltar) {
    const input = soltar.querySelector('input[type=file]');
    const nome = soltar.querySelector('[data-nome-arquivo]');
    const mostrar = () => { nome.textContent = input.files[0] ? input.files[0].name : ''; };
    input.addEventListener('change', mostrar);
    ['dragenter', 'dragover'].forEach((t) => soltar.addEventListener(t, (ev) => { ev.preventDefault(); soltar.classList.add('sobre'); }));
    ['dragleave', 'drop'].forEach((t) => soltar.addEventListener(t, () => soltar.classList.remove('sobre')));
    soltar.addEventListener('drop', (ev) => {
      ev.preventDefault();
      input.files = ev.dataTransfer.files;
      mostrar();
    });
  }
})();
