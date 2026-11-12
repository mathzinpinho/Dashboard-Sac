<?php
$alerta = opcoes('dias_alerta');
$filtrado = (bool) array_filter($f);
$porPagina = 30;
$de_ = ($pagina - 1) * $porPagina + 1;
$ate_ = min($total, $pagina * $porPagina);
?>
<header class="pagina-cab">
    <div>
        <h1>Reclamações</h1>
        <p class="pagina-desc"><?= numero($total) ?> registro<?= $total === 1 ? '' : 's' ?><?= $filtrado ? ' com os filtros aplicados' : '' ?></p>
    </div>
    <div class="pagina-acoes">
        <a class="botao" href="/exportar<?= e(qs(['p' => null])) ?>"><?= icone('baixar') ?>Exportar CSV</a>
        <a class="botao primario" href="/reclamacoes/nova"><?= icone('mais') ?>Nova reclamação</a>
    </div>
</header>

<form class="filtros" method="get" action="/reclamacoes" role="search">
    <div class="busca">
        <?= icone('busca') ?>
        <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por cliente, produto, lote, pedido ou nº" aria-label="Buscar">
    </div>
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
        <input type="date" name="de" value="<?= e($f['de']) ?>" aria-label="Data inicial">
        <span class="mudo">até</span>
        <input type="date" name="ate" value="<?= e($f['ate']) ?>" aria-label="Data final">
    </div>
    <button class="botao"><?= icone('filtro', 14) ?>Filtrar</button>
    <?php if ($filtrado): ?><a class="limpar" href="/reclamacoes">Limpar filtros</a><?php endif ?>
</form>

<section class="cartao sem-padding">
    <?php if (!$itens): ?>
        <div class="vazio">
            <span class="vazio-icone"><?= icone($filtrado ? 'busca' : 'caixa', 20) ?></span>
            <h3><?= $filtrado ? 'Nenhum resultado' : 'Nenhuma reclamação registrada' ?></h3>
            <p><?= $filtrado ? 'Ajuste a busca ou os filtros para encontrar o que procura.' : 'Registre a primeira reclamação ou importe o histórico da planilha.' ?></p>
            <div class="acoes">
                <?php if ($filtrado): ?>
                    <a class="botao" href="/reclamacoes">Limpar filtros</a>
                <?php else: ?>
                    <a class="botao" href="/importar"><?= icone('enviar') ?>Importar planilha</a>
                    <a class="botao primario" href="/reclamacoes/nova"><?= icone('mais') ?>Nova reclamação</a>
                <?php endif ?>
            </div>
        </div>
    <?php else: ?>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                <tr>
                    <th>Nº</th><th>Data</th><th class="col-cliente">Cliente</th><th>Tipo</th><th>Produto</th><th>Lote</th>
                    <th>Reclamação</th><th>Status</th><th class="num">Valor gasto</th><th class="num">Resolução</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($itens as $r):
                    $aberto = ($r['status'] ?? '') === 'aberto';
                    $dias = $aberto && $r['data'] ? (int) floor((time() - strtotime($r['data'])) / 86400) : 0;
                    $n = count($r['anexos'] ?? []);
                    ?>
                    <tr data-href="/reclamacoes/<?= e($r['id']) ?>">
                        <td class="nowrap">
                            <a class="numero-sac" href="/reclamacoes/<?= e($r['id']) ?>"><?= e($r['numero']) ?></a>
                            <?php if ($n): ?><span class="clipe" title="<?= $n ?> anexo<?= $n > 1 ? 's' : '' ?>"><?= icone('clipe', 12) ?><?= $n ?></span><?php endif ?>
                        </td>
                        <td class="nowrap">
                            <?= e(data_br($r['data'])) ?>
                            <?php if ($r['hora']): ?><div class="sub-linha"><?= e($r['hora']) ?></div><?php endif ?>
                        </td>
                        <td>
                            <span class="principal"><?= e($r['razao_social'] ?: $r['cliente']) ?></span>
                            <?php if ($r['razao_social'] && $r['cliente']): ?><div class="sub-linha"><?= e($r['cliente']) ?></div><?php endif ?>
                        </td>
                        <td><?= e($r['tipo']) ?></td>
                        <td><?= e($r['produto']) ?></td>
                        <td><?= e($r['lote']) ?></td>
                        <td class="texto-longo"><?= e($r['descricao']) ?></td>
                        <td>
                            <?php if ($aberto && $dias > $alerta): ?>
                                <span class="selo selo-atrasado" title="Aberto há <?= $dias ?> dias">Atrasado · <?= $dias ?>d</span>
                            <?php elseif ($aberto): ?>
                                <span class="selo selo-aberto">Aberto</span>
                            <?php else: ?>
                                <span class="selo selo-final">Finalizado</span>
                            <?php endif ?>
                        </td>
                        <td class="num"><?= isset($r['valor']) ? moeda((float) $r['valor']) : '<span class="mudo">—</span>' ?></td>
                        <td class="num"><?= isset($r['tempo_resolucao_h']) ? duracao((float) $r['tempo_resolucao_h']) : '<span class="mudo">—</span>' ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="tabela-rodape">
            <span><?= numero($de_) ?>–<?= numero($ate_) ?> de <?= numero($total) ?></span>
            <?php if ($paginas > 1): ?>
                <nav class="paginacao" aria-label="Páginas">
                    <a class="botao pequeno" href="<?= e(qs(['p' => max(1, $pagina - 1)])) ?>"<?= $pagina <= 1 ? ' aria-disabled="true"' : '' ?>><?= icone('esquerda', 14) ?>Anterior</a>
                    <a class="botao pequeno" href="<?= e(qs(['p' => min($paginas, $pagina + 1)])) ?>"<?= $pagina >= $paginas ? ' aria-disabled="true"' : '' ?>>Próxima<?= icone('direita', 14) ?></a>
                </nav>
            <?php endif ?>
        </div>
    <?php endif ?>
</section>

<?php if (Auth::admin() && $total): ?>
    <div class="excluir">
        <a class="link-perigo" href="/reclamacoes/apagar-tudo"><?= icone('lixeira', 14) ?>Apagar todas as reclamações</a>
    </div>
<?php endif ?>
