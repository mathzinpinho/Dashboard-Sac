// Pequenos comportamentos de interface. Todo o resto é PHP.
(function () {
  // menu lateral no celular
  const abrir = document.querySelector('[data-abrir-menu]');
  const fundo = document.querySelector('[data-fechar-menu]');
  if (abrir && fundo) {
    const alternar = (aberto) => {
      document.body.classList.toggle('menu-aberto', aberto);
      fundo.hidden = !aberto;
      abrir.setAttribute('aria-expanded', String(aberto));
    };
    abrir.addEventListener('click', () => alternar(true));
    fundo.addEventListener('click', () => alternar(false));
    document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') alternar(false); });
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
