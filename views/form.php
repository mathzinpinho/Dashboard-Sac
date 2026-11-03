<?php
$novo = empty($r['id']);
$acao = $novo ? '/reclamacoes/nova' : '/reclamacoes/' . $r['id'];
$v = fn(string $c) => e($r[$c] ?? '');
$erro = fn(string $c) => isset($erros[$c]) ? '<span class="erro-campo">' . e($erros[$c]) . '</span>' : '';
$cls = fn(string $c) => isset($erros[$c]) ? 'com-erro' : '';
?>
<div class="cabecalho">
    <div>
        <a class="voltar" href="/reclamacoes">‹ Reclamações</a>
        <h1><?= e($titulo) ?></h1>
        <?php if (!$novo): ?>
            <p class="mudo">
                <?php if (($r['status'] ?? '') === 'finalizado'): ?>
                    <span class="selo selo-final">✓ Finalizado</span>
                    <?= isset($r['tempo_resolucao_h']) ? ' em ' . duracao((float) $r['tempo_resolucao_h']) : '' ?>
                <?php else: ?>
                    <span class="selo selo-aberto">● Aberto</span>
                <?php endif ?>
            </p>
        <?php endif ?>
    </div>
</div>

<?php if ($erros): ?>
    <div class="aviso aviso-erro" role="alert">Confira os campos destacados.</div>
<?php endif ?>

<form method="post" action="<?= e($acao) ?>" class="formulario" id="form-sac">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">

    <section class="cartao">
        <h2>Atendimento</h2>
        <div class="campos c4">
            <label class="<?= $cls('data') ?>">Data *
                <input type="date" name="data" value="<?= $v('data') ?>" required><?= $erro('data') ?>
            </label>
            <label class="<?= $cls('hora') ?>">Hora
                <input type="time" name="hora" value="<?= $v('hora') ?>"><?= $erro('hora') ?>
            </label>
            <label>Código do pedido
                <input name="codigo_pedido" value="<?= $v('codigo_pedido') ?>" inputmode="numeric">
            </label>
            <label class="<?= $cls('tipo') ?>">Tipo *
                <select name="tipo" required>
                    <?php foreach (opcoes('tipos') as $t): ?>
                        <option <?= ($r['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach ?>
                </select><?= $erro('tipo') ?>
            </label>
        </div>
    </section>

    <section class="cartao">
        <h2>Cliente</h2>
        <div class="campos c3">
            <label class="<?= $cls('razao_social') ?>">Razão social
                <input name="razao_social" value="<?= $v('razao_social') ?>" list="lista-clientes" autocomplete="off" id="razao_social"><?= $erro('razao_social') ?>
            </label>
            <label>Cliente (pessoa de contato)
                <input name="cliente" value="<?= $v('cliente') ?>" id="cliente">
            </label>
            <label>Contato (telefone)
                <input name="contato" value="<?= $v('contato') ?>" inputmode="tel" id="contato">
            </label>
        </div>
    </section>

    <section class="cartao">
        <h2>Produto</h2>
        <div class="campos c4">
            <label>Produto
                <input name="produto" value="<?= $v('produto') ?>" placeholder="Ex.: 104 (184) ou 130 - 115">
            </label>
            <label class="<?= $cls('data_fabricacao') ?>">Data de fabricação
                <input type="date" name="data_fabricacao" value="<?= $v('data_fabricacao') ?>"><?= $erro('data_fabricacao') ?>
            </label>
            <label>Lote
                <input name="lote" value="<?= $v('lote') ?>">
            </label>
            <label>Lote - horário
                <input name="lote_horario" value="<?= $v('lote_horario') ?>" placeholder="Ex.: 14:46">
            </label>
        </div>
        <p class="dica-campo">Mais de um produto ou lote: separe com " - " (ex.: 130 - 115 - 200).</p>
    </section>

    <section class="cartao">
        <h2>Reclamação</h2>
        <div class="campos">
            <label class="<?= $cls('descricao') ?>">Descrição do atendimento *
                <input name="descricao" value="<?= $v('descricao') ?>" list="lista-descricoes" autocomplete="off" required><?= $erro('descricao') ?>
            </label>
            <label>Observações
                <textarea name="observacoes" rows="3"><?= $v('observacoes') ?></textarea>
            </label>
        </div>
    </section>

    <section class="cartao">
        <div class="cartao-cab">
            <h2>Solução / retorno</h2>
            <button type="button" class="botao pequeno" data-finalizar-agora>Finalizar agora</button>
        </div>
        <div class="campos c3">
            <label class="span-3">Solução / retorno ao cliente
                <input name="solucao" value="<?= $v('solucao') ?>" list="lista-solucoes" autocomplete="off">
            </label>
            <label class="<?= $cls('data_finalizacao') ?>">Data de finalização
                <input type="date" name="data_finalizacao" value="<?= $v('data_finalizacao') ?>"><?= $erro('data_finalizacao') ?>
            </label>
            <label class="<?= $cls('hora_finalizacao') ?>">Hora de finalização
                <input type="time" name="hora_finalizacao" value="<?= $v('hora_finalizacao') ?>"><?= $erro('hora_finalizacao') ?>
            </label>
        </div>
        <p class="dica-campo">Com a data de finalização preenchida, o SAC passa para <strong>Finalizado</strong> e o tempo de resolução é calculado.</p>
    </section>

    <div class="rodape-form">
        <?php if (!$novo): ?>
            <p class="mudo pequeno">
                Registrado por <?= e($r['criado_por'] ?? '—') ?> em <?= e(date('d/m/Y H:i', strtotime($r['criado_em'] ?? 'now'))) ?>
                <?= ($r['origem'] ?? '') === 'planilha' ? ' (importado da planilha)' : '' ?>
                <?php if (!empty($r['atualizado_por'])): ?>
                    · alterado por <?= e($r['atualizado_por']) ?> em <?= e(date('d/m/Y H:i', strtotime($r['atualizado_em']))) ?>
                <?php endif ?>
            </p>
        <?php endif ?>
        <div class="acoes">
            <a class="botao" href="/reclamacoes">Cancelar</a>
            <button class="botao primario"><?= $novo ? 'Registrar reclamação' : 'Salvar alterações' ?></button>
        </div>
    </div>
</form>

<?php if (!$novo && Auth::admin()): ?>
    <form method="post" action="/reclamacoes/<?= e($r['id']) ?>/excluir" class="excluir" data-confirmar="Excluir o SAC nº <?= e($r['numero']) ?>? Essa ação não pode ser desfeita.">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
        <button class="botao perigo pequeno">Excluir reclamação</button>
    </form>
<?php endif ?>

<datalist id="lista-descricoes">
    <?php foreach ($sug['descricoes'] as $s): ?><option value="<?= e($s) ?>"><?php endforeach ?>
</datalist>
<datalist id="lista-solucoes">
    <?php foreach ($sug['solucoes'] as $s): ?><option value="<?= e($s) ?>"><?php endforeach ?>
</datalist>
<datalist id="lista-clientes">
    <?php foreach ($sug['clientes'] as $nome => $_): ?><option value="<?= e($nome) ?>"><?php endforeach ?>
</datalist>
<script type="application/json" id="dados-clientes"><?= json_encode($sug['clientes'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
