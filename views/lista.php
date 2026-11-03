<?php $alerta = opcoes('dias_alerta'); ?>
<div class="cabecalho">
    <div>
        <h1>Reclamações</h1>
        <p class="mudo"><?= numero($total) ?> registro<?= $total === 1 ? '' : 's' ?></p>
    </div>
    <div class="acoes">
        <a class="botao" href="/exportar<?= e(qs(['p' => null])) ?>">Exportar CSV</a>
        <a class="botao primario" href="/reclamacoes/nova">+ Nova reclamação</a>
    </div>
</div>

<form class="filtros" method="get" action="/reclamacoes">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar cliente, produto, lote, pedido..." class="busca">
    <select name="status" data-auto-enviar aria-label="Status">
        <option value="">Todos os status</option>
        <option value="aberto" <?= $f['status'] === 'aberto' ? 'selected' : '' ?>>Em aberto</option>
        <option value="finalizado" <?= $f['status'] === 'finalizado' ? 'selected' : '' ?>>Finalizados</option>
    </select>
    <select name="tipo" data-auto-enviar aria-label="Tipo">
        <option value="">Todos os tipos</option>
        <?php foreach (opcoes('tipos') as $t): ?>
            <option <?= $f['tipo'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach ?>
    </select>
    <div class="intervalo">
        <input type="date" name="de" value="<?= e($f['de']) ?>" aria-label="De">
        <span class="mudo">até</span>
        <input type="date" name="ate" value="<?= e($f['ate']) ?>" aria-label="Até">
    </div>
    <button class="botao">Filtrar</button>
    <?php if (array_filter($f)): ?><a class="limpar" href="/reclamacoes">Limpar</a><?php endif ?>
</form>

<section class="cartao sem-padding">
    <?php if (!$itens): ?>
        <p class="mudo vazio-lista">Nenhuma reclamação encontrada.</p>
    <?php else: ?>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                <tr>
                    <th>Nº</th><th>Data</th><th>Cliente</th><th>Tipo</th><th>Produto</th><th>Lote</th>
                    <th>Reclamação</th><th>Status</th><th class="num">Valor gasto</th><th class="num">Resolução</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($itens as $r):
                    $aberto = ($r['status'] ?? '') === 'aberto';
                    $dias = $aberto && $r['data'] ? (int) floor((time() - strtotime($r['data'])) / 86400) : 0;
                    ?>
                    <tr data-href="/reclamacoes/<?= e($r['id']) ?>">
                        <td class="nowrap">
                            <a href="/reclamacoes/<?= e($r['id']) ?>"><?= e($r['numero']) ?></a>
                            <?php if ($n = count($r['anexos'] ?? [])): ?><span class="clipe" title="<?= $n ?> anexo<?= $n > 1 ? 's' : '' ?>">📎<?= $n ?></span><?php endif ?>
                        </td>
                        <td class="nowrap"><?= e(data_br($r['data'])) ?> <span class="mudo"><?= e($r['hora']) ?></span></td>
                        <td>
                            <?= e($r['razao_social'] ?: $r['cliente']) ?>
                            <?php if ($r['razao_social'] && $r['cliente']): ?><div class="mudo pequeno"><?= e($r['cliente']) ?></div><?php endif ?>
                        </td>
                        <td><?= e($r['tipo']) ?></td>
                        <td><?= e($r['produto']) ?></td>
                        <td><?= e($r['lote']) ?></td>
                        <td class="texto-longo"><?= e($r['descricao']) ?></td>
                        <td>
                            <?php if ($aberto): ?>
                                <span class="selo selo-aberto <?= $dias > $alerta ? 'selo-atrasado' : '' ?>">● Aberto<?= $dias > $alerta ? " · {$dias}d" : '' ?></span>
                            <?php else: ?>
                                <span class="selo selo-final">✓ Finalizado</span>
                            <?php endif ?>
                        </td>
                        <td class="num"><?= isset($r['valor']) ? moeda((float) $r['valor']) : '—' ?></td>
                        <td class="num"><?= isset($r['tempo_resolucao_h']) ? duracao((float) $r['tempo_resolucao_h']) : '—' ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<?php if ($paginas > 1): ?>
    <nav class="paginacao" aria-label="Páginas">
        <?php if ($pagina > 1): ?><a href="<?= e(qs(['p' => $pagina - 1])) ?>">‹ Anterior</a><?php endif ?>
        <span class="mudo">Página <?= $pagina ?> de <?= $paginas ?></span>
        <?php if ($pagina < $paginas): ?><a href="<?= e(qs(['p' => $pagina + 1])) ?>">Próxima ›</a><?php endif ?>
    </nav>
<?php endif ?>

<?php if (Auth::admin() && $total): ?>
    <div class="rodape-lista"><a class="link-perigo" href="/reclamacoes/apagar-tudo">Apagar todas as reclamações…</a></div>
<?php endif ?>
