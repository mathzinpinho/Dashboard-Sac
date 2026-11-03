// Envio de fotos e vídeos: o arquivo vai direto do navegador para o Vercel Blob,
// com um token temporário gerado pelo PHP em /anexos/token.
import { upload } from './vendor/vercel-blob-client.js';

const secao = document.getElementById('anexos');
if (secao) {
  const campo = secao.querySelector('[data-anexos-campo]');
  const grade = secao.querySelector('[data-anexos-grade]');
  const input = secao.querySelector('[data-anexos-input]');
  const soltar = secao.querySelector('[data-anexos-soltar]');
  const envios = secao.querySelector('[data-anexos-envios]');
  const contador = secao.querySelector('[data-anexos-contador]');
  const form = secao.closest('form');
  const maximo = Number(secao.dataset.max);
  let lista = JSON.parse(campo.value || '[]');
  let pendentes = 0;

  const atualizar = () => {
    campo.value = JSON.stringify(lista);
    contador.textContent = lista.length ? `${lista.length} arquivo${lista.length > 1 ? 's' : ''}` : '';
  };

  const tamanho = (b) => (b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB');

  const nomeSeguro = (nome) => nome
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .toLowerCase().replace(/[^a-z0-9.]+/g, '-').replace(/^-+|-+$/g, '')
    .slice(-80) || 'arquivo';

  // fotos grandes de celular são reduzidas para no máximo 2000px (economiza espaço e tempo de envio)
  async function reduzirFoto(arquivo) {
    if (!/^image\/(jpeg|png|webp|heic|heif)$/.test(arquivo.type) || arquivo.size < 1.5 * 1048576) return arquivo;
    try {
      const img = await createImageBitmap(arquivo);
      const escala = Math.min(1, 2000 / Math.max(img.width, img.height));
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(img.width * escala);
      canvas.height = Math.round(img.height * escala);
      canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
      const blob = await new Promise((ok) => canvas.toBlob(ok, 'image/jpeg', 0.85));
      if (!blob || blob.size >= arquivo.size) return arquivo;
      return new File([blob], arquivo.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
    } catch {
      return arquivo; // navegador não abre o formato (ex.: HEIC fora do Safari): envia o original
    }
  }

  function miniatura(a) {
    const fig = document.createElement('figure');
    fig.className = 'anexo';
    fig.dataset.url = a.url;
    const link = document.createElement('a');
    link.href = a.url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.className = 'anexo-midia';
    if (a.tipo.startsWith('video/')) {
      const v = document.createElement('video');
      v.src = a.url + '#t=0.1';
      v.preload = 'metadata';
      v.muted = true;
      const play = document.createElement('span');
      play.className = 'anexo-play';
      play.textContent = '▶';
      link.append(v, play);
    } else {
      const img = document.createElement('img');
      img.src = a.url;
      img.alt = a.nome;
      link.append(img);
    }
    const cap = document.createElement('figcaption');
    const nome = document.createElement('span');
    nome.className = 'anexo-nome';
    nome.title = a.nome;
    nome.textContent = a.nome;
    const info = document.createElement('span');
    info.className = 'mudo';
    info.textContent = tamanho(a.tamanho) + ' · novo';
    cap.append(nome, info);
    const rem = document.createElement('button');
    rem.type = 'button';
    rem.className = 'anexo-remover';
    rem.dataset.remover = '';
    rem.setAttribute('aria-label', 'Remover ' + a.nome);
    rem.textContent = '×';
    fig.append(link, cap, rem);
    return fig;
  }

  async function enviar(original) {
    const item = document.createElement('li');
    const rotulo = document.createElement('span');
    rotulo.textContent = original.name;
    const barra = document.createElement('progress');
    barra.max = 100;
    barra.value = 0;
    const status = document.createElement('span');
    status.className = 'mudo';
    item.append(rotulo, barra, status);
    envios.append(item);

    if (!/^(image|video)\//.test(original.type)) {
      status.textContent = 'só fotos e vídeos';
      item.classList.add('falhou');
      return;
    }
    pendentes++;
    try {
      status.textContent = 'preparando…';
      const arquivo = await reduzirFoto(original);
      if (arquivo.size > maximo) {
        throw new Error(`maior que ${Math.round(maximo / 1048576)} MB`);
      }
      status.textContent = '';
      const r = await upload(`${secao.dataset.pasta}/${nomeSeguro(arquivo.name)}`, arquivo, {
        access: 'public',
        handleUploadUrl: '/anexos/token',
        headers: { 'x-csrf': secao.dataset.csrf },
        contentType: arquivo.type,
        multipart: arquivo.size > 20 * 1048576,
        onUploadProgress: ({ percentage }) => { barra.value = percentage; },
      });
      const anexo = { url: r.url, nome: original.name, tipo: arquivo.type, tamanho: arquivo.size };
      lista.push(anexo);
      grade.append(miniatura(anexo));
      atualizar();
      item.remove();
    } catch (erro) {
      console.error(erro);
      item.classList.add('falhou');
      status.textContent = 'falhou: ' + (erro.message || 'erro no envio').replace(/^Vercel Blob:\s*/, '');
    } finally {
      pendentes--;
    }
  }

  input.addEventListener('change', () => {
    [...input.files].forEach(enviar);
    input.value = '';
  });

  ['dragenter', 'dragover'].forEach((t) => soltar.addEventListener(t, (ev) => { ev.preventDefault(); soltar.classList.add('sobre'); }));
  ['dragleave', 'drop'].forEach((t) => soltar.addEventListener(t, () => soltar.classList.remove('sobre')));
  soltar.addEventListener('drop', (ev) => {
    ev.preventDefault();
    [...ev.dataTransfer.files].forEach(enviar);
  });

  grade.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-remover]');
    if (!btn) return;
    const fig = btn.closest('.anexo');
    if (!confirm('Remover este anexo? Ele será apagado ao salvar a reclamação.')) return;
    lista = lista.filter((a) => a.url !== fig.dataset.url);
    fig.remove();
    atualizar();
  });

  form.addEventListener('submit', (ev) => {
    if (pendentes > 0) {
      ev.preventDefault();
      alert('Aguarde terminar o envio dos anexos antes de salvar.');
    }
  });

  atualizar();
}
