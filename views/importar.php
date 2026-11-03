<div class="cabecalho">
    <div>
        <h1>Importar planilha</h1>
        <p class="mudo">Traz o histórico da planilha de SAC (.xlsx ou .csv) para o sistema.</p>
    </div>
</div>

<?php if ($erro): ?><div class="aviso aviso-erro" role="alert"><?= e($erro) ?></div><?php endif ?>

<?php if (isset($res['ok'])): ?>
    <section class="cartao">
        <h2>Importação concluída</h2>
        <div class="kpis kpis-3">
            <div class="kpi"><span class="kpi-rotulo">Importadas</span><strong><?= $res['ok'] ?></strong></div>
            <div class="kpi"><span class="kpi-rotulo">Já existiam (ignoradas)</span><strong><?= $res['rep'] ?></strong></div>
            <div class="kpi"><span class="kpi-rotulo">Com erro</span><strong><?= count($res['errosLinhas']) ?></strong></div>
        </div>
        <?php if ($res['errosLinhas']): ?>
            <ul class="lista-erros"><?php foreach ($res['errosLinhas'] as $l): ?><li><?= e($l) ?></li><?php endforeach ?></ul>
        <?php endif ?>
        <div class="acoes">
            <a class="botao primario" href="/">Ver dashboard</a>
            <a class="botao" href="/reclamacoes">Ver reclamações</a>
        </div>
    </section>

<?php elseif (isset($res['linhas'])): ?>
    <section class="cartao">
        <div class="cartao-cab">
            <h2>Prévia · <?= e($res['arquivo']) ?></h2>
            <span class="mudo"><?= count($res['linhas']) ?> linhas encontradas</span>
        </div>
        <p class="mudo pequeno">
            Colunas reconhecidas:
            <?= e(implode(', ', array_map(fn($c) => Sac::CAMPOS[$c], array_values($res['mapa'])))) ?>.
            Reclamações que já estão no sistema não são importadas de novo.
        </p>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Data</th><th>Cliente</th><th>Tipo</th><th>Produto</th><th>Lote</th><th>Reclamação</th><th>Finalização</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($res['linhas'], 0, 10) as $l): ?>
                    <tr>
                        <td class="nowrap"><?= e(data_br($l['data'] ?? '')) ?> <span class="mudo"><?= e($l['hora'] ?? '') ?></span></td>
                        <td><?= e(($l['razao_social'] ?? '') ?: ($l['cliente'] ?? '')) ?></td>
                        <td><?= e($l['tipo'] ?? '') ?></td>
                        <td><?= e($l['produto'] ?? '') ?></td>
                        <td><?= e($l['lote'] ?? '') ?></td>
                        <td class="texto-longo"><?= e($l['descricao'] ?? '') ?></td>
                        <td class="nowrap"><?= e(data_br($l['data_finalizacao'] ?? '')) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php if (count($res['linhas']) > 10): ?><p class="mudo pequeno">… e mais <?= count($res['linhas']) - 10 ?> linhas.</p><?php endif ?>

        <form method="post" action="/importar" class="acoes">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
            <input type="hidden" name="linhas" value="<?= e(base64_encode(json_encode($res['linhas'], JSON_UNESCAPED_UNICODE))) ?>">
            <a class="botao" href="/importar">Escolher outro arquivo</a>
            <button class="botao primario">Importar <?= count($res['linhas']) ?> linhas</button>
        </form>
    </section>

<?php else: ?>
    <section class="cartao">
        <form method="post" action="/importar" enctype="multipart/form-data" class="upload">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
            <label class="soltar" data-soltar>
                <input type="file" name="arquivo" accept=".xlsx,.csv" required>
                <strong>Escolha ou arraste a planilha aqui</strong>
                <span class="mudo">Lê a aba "SAC", com as colunas Data, Hora, Código Pedido, Cliente, Razão Social, Contato, Produto, Lote, Descrição do Atendimento, Solução / Retorno…</span>
                <span class="nome-arquivo" data-nome-arquivo></span>
            </label>
            <button class="botao primario">Ver prévia</button>
        </form>
    </section>
<?php endif ?>
