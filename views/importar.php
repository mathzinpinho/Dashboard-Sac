<header class="pagina-cab">
    <div>
        <h1>Importar planilha</h1>
        <p class="pagina-desc">Traga o histórico da planilha de SAC (.xlsx ou .csv) para o sistema.</p>
    </div>
</header>

<?php if ($erro): ?><div class="aviso aviso-erro" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></div><?php endif ?>

<?php if (isset($res['ok'])): ?>
    <section class="cartao">
        <div class="cartao-cab">
            <div>
                <h2>Importação concluída</h2>
                <span class="cartao-desc">Reclamações que já estavam no sistema não foram duplicadas.</span>
            </div>
        </div>
        <div class="kpis kpis-3">
            <div class="kpi"><span class="kpi-rotulo">Importadas</span><strong><?= numero($res['ok']) ?></strong></div>
            <div class="kpi"><span class="kpi-rotulo">Já existiam</span><strong><?= numero($res['rep']) ?></strong></div>
            <div class="kpi"><span class="kpi-rotulo">Com erro</span><strong><?= numero(count($res['errosLinhas'])) ?></strong></div>
        </div>
        <?php if ($res['errosLinhas']): ?>
            <ul class="lista-erros"><?php foreach ($res['errosLinhas'] as $l): ?><li><?= e($l) ?></li><?php endforeach ?></ul>
        <?php endif ?>
        <div class="acoes">
            <a class="botao primario" href="/">Ir para o dashboard</a>
            <a class="botao" href="/reclamacoes">Ver reclamações</a>
        </div>
    </section>

<?php elseif (isset($res['linhas'])): ?>
    <section class="cartao sem-padding">
        <div class="cartao-cab">
            <div>
                <h2>Prévia de <?= e($res['arquivo']) ?></h2>
                <span class="cartao-desc">
                    <?= numero(count($res['linhas'])) ?> linhas encontradas · colunas reconhecidas:
                    <?= e(implode(', ', array_map(fn($c) => Sac::CAMPOS[$c], array_values($res['mapa'])))) ?>
                </span>
            </div>
        </div>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Data</th><th>Cliente</th><th>Tipo</th><th>Produto</th><th>Lote</th><th>Reclamação</th><th>Finalização</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($res['linhas'], 0, 10) as $l): ?>
                    <tr>
                        <td class="nowrap"><?= e(data_br($l['data'] ?? '')) ?><?php if (!empty($l['hora'])): ?><div class="sub-linha"><?= e($l['hora']) ?></div><?php endif ?></td>
                        <td class="principal"><?= e(($l['razao_social'] ?? '') ?: ($l['cliente'] ?? '')) ?></td>
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
        <form method="post" action="/importar" class="tabela-rodape">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
            <input type="hidden" name="linhas" value="<?= e(base64_encode(json_encode($res['linhas'], JSON_UNESCAPED_UNICODE))) ?>">
            <span><?= count($res['linhas']) > 10 ? 'Mostrando 10 de ' . numero(count($res['linhas'])) . ' linhas' : '' ?></span>
            <div class="acoes">
                <a class="botao" href="/importar">Escolher outro arquivo</a>
                <button class="botao primario">Importar <?= numero(count($res['linhas'])) ?> linhas</button>
            </div>
        </form>
    </section>

<?php else: ?>
    <section class="cartao">
        <form method="post" action="/importar" enctype="multipart/form-data" class="upload">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
            <label class="soltar" data-soltar>
                <input type="file" name="arquivo" accept=".xlsx,.csv" required>
                <span class="soltar-icone"><?= icone('planilha', 18) ?></span>
                <strong><em>Escolha a planilha</em> ou arraste até aqui</strong>
                <span class="mudo">Lê a aba "SAC" com as colunas Data, Hora, Código Pedido, Cliente, Razão Social, Contato, Produto, Lote, Descrição do Atendimento, Solução / Retorno e Valor gasto.</span>
                <span class="nome-arquivo" data-nome-arquivo></span>
            </label>
            <button class="botao primario">Ver prévia</button>
        </form>
    </section>
<?php endif ?>
