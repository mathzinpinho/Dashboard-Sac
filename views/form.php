<?php
$novo = empty($r['id']);
$acao = $novo ? '/reclamacoes/nova' : '/reclamacoes/' . $r['id'];
$v = fn(string $c) => e($r[$c] ?? '');
$erro = fn(string $c) => isset($erros[$c]) ? '<span class="erro-campo">' . e($erros[$c]) . '</span>' : '';
$cls = fn(string $c) => isset($erros[$c]) ? 'com-erro' : '';
$obr = '<span class="obrigatorio" aria-hidden="true">*</span>';
$finalizado = ($r['status'] ?? '') === 'finalizado';
?>
<header class="pagina-cab">
    <div>
        <nav class="trilha" aria-label="Trilha">
            <a href="/reclamacoes">Reclamações</a><?= icone('direita', 12) ?><span><?= $novo ? 'Nova' : 'Nº ' . e($r['numero']) ?></span>
        </nav>
        <h1><?= $novo ? 'Nova reclamação' : 'SAC nº ' . e($r['numero']) ?></h1>
        <?php if (!$novo): ?>
            <p class="pagina-desc">
                <?php if ($finalizado): ?>
                    <span class="selo selo-final">Finalizado</span>
                    <?= isset($r['tempo_resolucao_h']) ? 'Resolvido em ' . duracao((float) $r['tempo_resolucao_h']) : '' ?>
                <?php else: ?>
                    <span class="selo selo-aberto">Aberto</span>
                    Desde <?= e(data_br($r['data'] ?? '')) ?>
                <?php endif ?>
            </p>
        <?php else: ?>
            <p class="pagina-desc">Campos com <span class="obrigatorio">*</span> são obrigatórios.</p>
        <?php endif ?>
    </div>
    <?php if (!$novo && Auth::admin()): ?>
        <div class="pagina-acoes">
            <form method="post" action="/reclamacoes/<?= e($r['id']) ?>/excluir" data-confirmar="Excluir o SAC nº <?= e($r['numero']) ?>? Essa ação não pode ser desfeita.">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
                <button class="botao perigo"><?= icone('lixeira') ?>Excluir</button>
            </form>
        </div>
    <?php endif ?>
</header>

<?php if ($erros): ?>
    <div class="aviso aviso-erro" role="alert"><?= icone('alerta') ?><span>Revise os campos destacados antes de salvar.</span></div>
<?php endif ?>

<form method="post" action="<?= e($acao) ?>" class="formulario" id="form-sac">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">

    <div class="cartao">
        <section class="secao-form">
            <header>
                <h2>Atendimento</h2>
                <p>Quando e como a reclamação chegou.</p>
            </header>
            <div class="campos c4">
                <label class="<?= $cls('data') ?>"><span>Data<?= $obr ?></span>
                    <input type="date" name="data" value="<?= $v('data') ?>" required><?= $erro('data') ?>
                </label>
                <label class="<?= $cls('hora') ?>">Hora
                    <input type="time" name="hora" value="<?= $v('hora') ?>"><?= $erro('hora') ?>
                </label>
                <label>Código do pedido
                    <input name="codigo_pedido" value="<?= $v('codigo_pedido') ?>" inputmode="numeric">
                </label>
                <label class="<?= $cls('tipo') ?>"><span>Tipo<?= $obr ?></span>
                    <select name="tipo" required>
                        <?php foreach (opcoes('tipos') as $t): ?>
                            <option <?= ($r['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach ?>
                    </select><?= $erro('tipo') ?>
                </label>
            </div>
        </section>

        <section class="secao-form">
            <header>
                <h2>Cliente</h2>
                <p>Ao escolher uma razão social já cadastrada, o contato é preenchido.</p>
            </header>
            <div class="campos c3">
                <label class="<?= $cls('razao_social') ?>">Razão social
                    <input name="razao_social" value="<?= $v('razao_social') ?>" list="lista-clientes" autocomplete="off" id="razao_social"><?= $erro('razao_social') ?>
                </label>
                <label>Pessoa de contato
                    <input name="cliente" value="<?= $v('cliente') ?>" id="cliente">
                </label>
                <label>Telefone
                    <input name="contato" value="<?= $v('contato') ?>" inputmode="tel" id="contato">
                </label>
            </div>
        </section>

        <section class="secao-form">
            <header>
                <h2>Produto</h2>
                <p>Mais de um produto ou lote: separe com " - " (ex.: 130 - 115).</p>
            </header>
            <div class="campos c4">
                <label>Produto
                    <input name="produto" value="<?= $v('produto') ?>" placeholder="104 (184)">
                </label>
                <label class="<?= $cls('data_fabricacao') ?>">Fabricação
                    <input type="date" name="data_fabricacao" value="<?= $v('data_fabricacao') ?>"><?= $erro('data_fabricacao') ?>
                </label>
                <label>Lote
                    <input name="lote" value="<?= $v('lote') ?>">
                </label>
                <label>Horário do lote
                    <input name="lote_horario" value="<?= $v('lote_horario') ?>" placeholder="14:46">
                </label>
            </div>
        </section>

        <section class="secao-form">
            <header>
                <h2>Reclamação</h2>
                <p>O que o cliente relatou.</p>
            </header>
            <div class="campos">
                <label class="<?= $cls('descricao') ?>"><span>Descrição do atendimento<?= $obr ?></span>
                    <input name="descricao" value="<?= $v('descricao') ?>" list="lista-descricoes" autocomplete="off" required><?= $erro('descricao') ?>
                </label>
                <label>Observações
                    <textarea name="observacoes" rows="3"><?= $v('observacoes') ?></textarea>
                </label>
            </div>
        </section>

        <section class="secao-form" id="anexos"
                 data-csrf="<?= e(Auth::csrf()) ?>"
                 data-max="<?= Anexos::TAMANHO_MAX ?>"
                 data-pasta="sac/<?= date('Y/m') ?>">
            <header>
                <h2>Fotos e vídeos</h2>
                <p>Provas enviadas pelo cliente. Até <?= Anexos::TAMANHO_MAX / 1048576 ?> MB por arquivo.</p>
                <p class="pequeno" data-anexos-contador></p>
            </header>
            <div>
                <input type="hidden" name="anexos" value="<?= e(json_encode($r['anexos'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>" data-anexos-campo>

                <div class="anexos-grade" data-anexos-grade>
                    <?php foreach ($r['anexos'] ?? [] as $a): ?>
                        <figure class="anexo" data-url="<?= e($a['url']) ?>">
                            <a href="<?= e($a['url']) ?>" target="_blank" rel="noopener" class="anexo-midia">
                                <?php if (Anexos::ehVideo($a)): ?>
                                    <video src="<?= e($a['url']) ?>#t=0.1" preload="metadata" muted></video>
                                    <span class="anexo-play"><?= icone('play', 14) ?></span>
                                <?php else: ?>
                                    <img src="<?= e($a['url']) ?>" alt="<?= e($a['nome']) ?>" loading="lazy">
                                <?php endif ?>
                            </a>
                            <figcaption>
                                <span class="anexo-nome" title="<?= e($a['nome']) ?>"><?= e($a['nome']) ?></span>
                                <span class="mudo"><?= e(Anexos::tamanho((int) $a['tamanho'])) ?><?= !empty($a['enviado_por']) ? ' · ' . e($a['enviado_por']) : '' ?></span>
                            </figcaption>
                            <button type="button" class="anexo-remover" data-remover aria-label="Remover <?= e($a['nome']) ?>"><?= icone('fechar', 14) ?></button>
                        </figure>
                    <?php endforeach ?>
                </div>

                <?php if (Anexos::ativo()): ?>
                    <label class="soltar" data-anexos-soltar>
                        <input type="file" accept="image/*,video/*" multiple data-anexos-input>
                        <span class="soltar-icone"><?= icone('imagem', 18) ?></span>
                        <strong><em>Escolha os arquivos</em> ou arraste até aqui</strong>
                        <span class="mudo">Fotos e vídeos · fotos grandes são reduzidas antes do envio</span>
                    </label>
                    <ul class="envios" data-anexos-envios></ul>
                <?php else: ?>
                    <p class="vazio-curto">O envio de anexos não está configurado neste ambiente.</p>
                <?php endif ?>
            </div>
        </section>

        <section class="secao-form">
            <header>
                <h2>Solução e custo</h2>
                <p>Com a data de finalização preenchida, o SAC é finalizado e o tempo de resolução é calculado.</p>
                <button type="button" class="botao pequeno" data-finalizar-agora><?= icone('relogio', 14) ?>Finalizar agora</button>
            </header>
            <div class="campos c3">
                <label class="span-3">Solução / retorno ao cliente
                    <input name="solucao" value="<?= $v('solucao') ?>" list="lista-solucoes" autocomplete="off">
                </label>
                <label class="<?= $cls('valor') ?>">Valor gasto (R$)
                    <input name="valor" inputmode="decimal" placeholder="0,00" autocomplete="off"
                           value="<?= isset($r['valor']) && $r['valor'] !== '' && is_numeric($r['valor']) ? e(number_format((float) $r['valor'], 2, ',', '')) : $v('valor') ?>"><?= $erro('valor') ?>
                </label>
                <label class="<?= $cls('data_finalizacao') ?>">Data de finalização
                    <input type="date" name="data_finalizacao" value="<?= $v('data_finalizacao') ?>"><?= $erro('data_finalizacao') ?>
                </label>
                <label class="<?= $cls('hora_finalizacao') ?>">Hora de finalização
                    <input type="time" name="hora_finalizacao" value="<?= $v('hora_finalizacao') ?>"><?= $erro('hora_finalizacao') ?>
                </label>
            </div>
            <p class="dica-campo">Valor gasto: cupom, reembolso, reenvio, frete — quanto a empresa perdeu com o caso.</p>
        </section>
    </div>

    <div class="rodape-form">
        <p class="mudo pequeno">
            <?php if (!$novo): ?>
                Registrado por <?= e($r['criado_por'] ?? '—') ?> em <?= e(date('d/m/Y H:i', strtotime($r['criado_em'] ?? 'now'))) ?><?= ($r['origem'] ?? '') === 'planilha' ? ' (planilha)' : '' ?>
                <?php if (!empty($r['atualizado_por'])): ?>
                    · alterado por <?= e($r['atualizado_por']) ?> em <?= e(date('d/m/Y H:i', strtotime($r['atualizado_em']))) ?>
                <?php endif ?>
            <?php else: ?>
                Os anexos são gravados junto com a reclamação.
            <?php endif ?>
        </p>
        <div class="acoes">
            <a class="botao" href="/reclamacoes">Cancelar</a>
            <button class="botao primario"><?= $novo ? 'Registrar reclamação' : 'Salvar alterações' ?></button>
        </div>
    </div>
</form>

<datalist id="lista-descricoes">
    <?php foreach ($sug['descricoes'] as $s): ?><option value="<?= e($s) ?>"><?php endforeach ?>
</datalist>
<datalist id="lista-solucoes">
    <?php foreach ($sug['solucoes'] as $s): ?><option value="<?= e($s) ?>"><?php endforeach ?>
</datalist>
<datalist id="lista-clientes">
    <?php foreach ($sug['clientes'] as $nome => $_): ?><option value="<?= e($nome) ?>"><?php endforeach ?>
</datalist>
<?php if (Anexos::ativo()): ?><script type="module" src="/assets/js/anexos.js?v=2"></script><?php endif ?>
<script type="application/json" id="dados-clientes"><?= json_encode($sug['clientes'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
