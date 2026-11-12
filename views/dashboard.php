<?php
$periodo = $_GET['periodo'] ?? '12m';
$atalhos = ['mes' => 'Este mês', '3m' => '3 meses', '12m' => '12 meses', 'ano' => 'Este ano', 'tudo' => 'Tudo'];
$alerta = opcoes('dias_alerta');
$atrasados = count(array_filter($ind['lista_abertos'], fn($r) => ($r['dias_aberto'] ?? 0) > $alerta));
$semValor = 'Nenhum valor gasto lançado no período.';

// filtros da lista de reclamações equivalentes ao período do dashboard
$filtroLista = array_filter(['de' => $de, 'ate' => $ate, 'tipo' => $_GET['tipo'] ?? '']);
$linkLista = fn(array $extra = []) => '/reclamacoes?' . http_build_query(array_merge($filtroLista, $extra));

/** Lista ranqueada com barra horizontal; cada linha abre a lista filtrada. $dinheiro: valores em R$ */
$ranking = function (string $titulo, array $dados, string $vazio = 'Sem registros no período.', bool $dinheiro = false, string $desc = '') use ($linkLista, $ind) {
    $max = $dados ? max($dados) : 0;
    $base = $dinheiro ? max(0.01, $ind['valor_total']) : max(1, $ind['total']); ?>
    <section class="cartao">
        <div class="cartao-cab">
            <div>
                <h2><?= e($titulo) ?></h2>
                <?php if ($desc): ?><span class="cartao-desc"><?= e($desc) ?></span><?php endif ?>
            </div>
        </div>
        <?php if (!$dados): ?>
            <p class="vazio-curto"><?= e($vazio) ?></p>
        <?php else: ?>
            <ol class="ranking<?= $dinheiro ? ' ranking-dinheiro' : '' ?>">
                <?php foreach ($dados as $rotulo => $qtd):
                    $texto = $dinheiro ? moeda((float) $qtd) : $qtd . ' SAC' . ($qtd > 1 ? 's' : '');
                    $pct = round($qtd / $base * 100) . '% ' . ($dinheiro ? 'do prejuízo' : 'do total');
                    ?>
                    <li>
                        <a href="<?= e($linkLista(['q' => $rotulo])) ?>" data-dica="<?= e($rotulo) ?> — <?= e($texto) ?> (<?= $pct ?>)">
                            <span class="r-rotulo"><?= e($rotulo) ?></span>
                            <span class="r-trilho"><span class="r-barra" style="width: <?= $max > 0 ? max(2, round($qtd / $max * 100)) : 2 ?>%"></span></span>
                            <span class="r-valor"><?= $dinheiro ? e(moeda((float) $qtd, false)) : $qtd ?></span>
                        </a>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    </section>
<?php };

/** Gráfico de colunas por mês. $dinheiro: valores em R$ */
$colunas = function (string $titulo, array $serie, bool $dinheiro = false, string $desc = '') use ($linkLista, $semValor) { ?>
    <section class="cartao cartao-grafico">
        <div class="cartao-cab">
            <div>
                <h2><?= e($titulo) ?></h2>
                <?php if ($desc): ?><span class="cartao-desc"><?= e($desc) ?></span><?php endif ?>
            </div>
        </div>
        <?php if (!$serie): ?>
            <p class="vazio-curto"><?= $dinheiro ? e($semValor) : 'Sem registros no período.' ?></p>
        <?php else:
            $max = max($serie) ?: 1;
            // topo do eixo arredondado para 1, 2, 2,5 ou 5 × 10^n
            $passo = 10 ** floor(log10($max));
            $topo = $passo * 10;
            foreach ([1, 2, 2.5, 5] as $m) if ($m * $passo >= $max) { $topo = $m * $passo; break; }
            $topo = max($topo, $dinheiro ? 10 : 5);
            $rot = fn($v) => $dinheiro ? moeda((float) $v, false) : numero($v);
            $pico = array_search(max($serie), $serie, true);
            $ultimo = array_key_last($serie);
            ?>
            <div class="colunas<?= $dinheiro ? ' colunas-dinheiro' : '' ?>" style="--n: <?= count($serie) ?>">
                <div class="eixo-y" aria-hidden="true">
                    <span><?= e($rot($topo)) ?></span><span><?= e($rot($topo / 2)) ?></span><span>0</span>
                </div>
                <div class="plot">
                    <?php foreach ($serie as $mes => $qtd):
                        $texto = $dinheiro ? moeda((float) $qtd) : $qtd . ' SAC' . ($qtd === 1 ? '' : 's');
                        ?>
                        <a class="col" href="<?= e($linkLista(['de' => "$mes-01", 'ate' => date('Y-m-t', strtotime("$mes-01"))])) ?>"
                           data-dica="<?= e(ucfirst(mes_br($mes, false))) ?> — <?= e($texto) ?>">
                            <span class="col-barra" style="height: <?= $qtd ? max(1.5, $qtd / $topo * 100) : 0 ?>%">
                                <?php if ($qtd && ($mes === $pico || $mes === $ultimo)): ?><b><?= e($rot($qtd)) ?></b><?php endif ?>
                            </span>
                            <span class="col-rotulo"><?= e(mes_br($mes)) ?></span>
                        </a>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </section>
<?php };
?>

<header class="pagina-cab">
    <div>
        <h1>Dashboard</h1>
        <p class="pagina-desc">
            <?php if ($de || $ate): ?>
                <?= e(data_br($de) ?: 'Início') ?> a <?= e(data_br($ate) ?: 'hoje') ?>
            <?php else: ?>
                Todo o histórico
            <?php endif ?>
            <?= !empty($_GET['tipo']) ? ' · ' . e($_GET['tipo']) : '' ?>
        </p>
    </div>
    <div class="pagina-acoes">
        <a class="botao" href="/exportar<?= e(qs(['periodo' => null, 'de' => $de ?: null, 'ate' => $ate ?: null])) ?>"><?= icone('baixar') ?>Exportar</a>
        <a class="botao primario" href="/reclamacoes/nova"><?= icone('mais') ?>Nova reclamação</a>
    </div>
</header>

<form class="filtros" method="get" action="/">
    <?php /* vem antes dos botões: o botão clicado (também "periodo") chega por último e prevalece */ ?>
    <input type="hidden" name="periodo" value="<?= e($periodo) ?>">
    <div class="segmentado" role="group" aria-label="Período">
        <?php foreach ($atalhos as $k => $rot): ?>
            <button name="periodo" value="<?= $k ?>" class="<?= $periodo === $k ? 'ativo' : '' ?>" <?= $periodo === $k ? 'aria-pressed="true"' : '' ?>><?= $rot ?></button>
        <?php endforeach ?>
    </div>
    <div class="intervalo">
        <input type="date" name="de" value="<?= e($periodo === 'custom' ? $de : '') ?>" aria-label="Data inicial">
        <span class="mudo">até</span>
        <input type="date" name="ate" value="<?= e($periodo === 'custom' ? $ate : '') ?>" aria-label="Data final">
        <button name="periodo" value="custom" class="botao<?= $periodo === 'custom' ? ' primario' : '' ?>">Aplicar</button>
    </div>
    <select name="tipo" aria-label="Tipo de reclamação" data-auto-enviar>
        <option value="">Todos os tipos</option>
        <?php foreach (opcoes('tipos') as $t): ?>
            <option <?= ($_GET['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach ?>
    </select>
</form>

<div class="kpis kpis-principais">
    <a class="kpi" href="<?= e($linkLista()) ?>">
        <span class="kpi-rotulo">Total de SACs</span>
        <strong><?= numero($ind['total']) ?></strong>
        <span class="kpi-sub"><?= $ind['tipo_principal'] ? 'Principal: ' . e($ind['tipo_principal']) : 'Sem registros' ?></span>
    </a>
    <div class="kpi kpi-destaque">
        <span class="kpi-rotulo">Prejuízo</span>
        <strong><?= moeda($ind['valor_total'], $ind['valor_total'] < 10000) ?></strong>
        <span class="kpi-sub">
            <?= $ind['com_valor'] ? 'Média de ' . moeda($ind['valor_medio']) . ' por SAC' : 'Nenhum valor lançado' ?>
        </span>
    </div>
    <a class="kpi" href="<?= e($linkLista(['status' => 'aberto'])) ?>">
        <span class="kpi-rotulo">Em aberto</span>
        <strong><?= numero($ind['abertos']) ?></strong>
        <?php if ($atrasados): ?>
            <span class="kpi-sub alerta"><?= icone('alerta', 12) ?><?= $atrasados ?> há mais de <?= $alerta ?> dias</span>
        <?php else: ?>
            <span class="kpi-sub">Nenhum atrasado</span>
        <?php endif ?>
    </a>
    <a class="kpi" href="<?= e($linkLista(['status' => 'finalizado'])) ?>">
        <span class="kpi-rotulo">Finalizados</span>
        <strong><?= numero($ind['finalizados']) ?></strong>
        <span class="kpi-sub"><?= $ind['pct_final'] ?>% do total</span>
    </a>
</div>

<div class="kpis kpis-secundarios">
    <div class="kpi">
        <span class="kpi-rotulo">Tempo médio de resolução</span>
        <strong><?= duracao($ind['tempo_medio_h']) ?></strong>
        <span class="kpi-sub">Mediana de <?= duracao($ind['tempo_mediana_h']) ?></span>
    </div>
    <div class="kpi">
        <span class="kpi-rotulo">Produtos afetados</span>
        <strong><?= numero($ind['produtos']) ?></strong>
        <span class="kpi-sub">Códigos com reclamação</span>
    </div>
    <div class="kpi">
        <span class="kpi-rotulo">Lotes afetados</span>
        <strong><?= numero($ind['lotes']) ?></strong>
        <span class="kpi-sub">Lotes envolvidos</span>
    </div>
</div>

<?php $colunas('SACs por mês', $ind['por_mes'], false, 'Clique em um mês para ver as reclamações') ?>

<div class="grade">
    <?php $colunas('Prejuízo por mês', $ind['valor_mes'], true) ?>
    <?php $ranking('Prejuízo por reclamação', $ind['valor_descricao'], $semValor, true) ?>
    <?php $ranking('Prejuízo por produto', $ind['valor_produto'], $semValor, true, 'SAC com vários produtos divide o valor') ?>
</div>

<div class="grade">
    <?php $ranking('Principais reclamações', $ind['top_descricao']) ?>
    <?php $ranking('Produtos com mais SACs', $ind['top_produto']) ?>
    <?php $ranking('Lotes com mais SACs', $ind['top_lote']) ?>
    <?php $ranking('Soluções aplicadas', $ind['top_solucao']) ?>
    <?php $ranking('Por tipo', $ind['por_tipo']) ?>
    <?php $ranking('Dias com mais SACs', array_combine(array_map('data_br', array_keys($ind['top_datas'])), $ind['top_datas'])) ?>
</div>

<section class="cartao sem-padding">
    <div class="cartao-cab">
        <div>
            <h2>Em aberto</h2>
            <span class="cartao-desc">Mais antigos primeiro</span>
        </div>
        <a href="<?= e($linkLista(['status' => 'aberto'])) ?>">Ver todos</a>
    </div>
    <?php if (!$ind['lista_abertos']): ?>
        <div class="vazio">
            <span class="vazio-icone"><?= icone('ok-circulo', 20) ?></span>
            <h3>Nenhuma reclamação em aberto</h3>
            <p>Todas as reclamações do período foram finalizadas.</p>
        </div>
    <?php else: ?>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Nº</th><th>Data</th><th>Cliente</th><th>Produto</th><th>Reclamação</th><th class="num">Aberto há</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($ind['lista_abertos'], 0, 10) as $r): ?>
                    <tr data-href="/reclamacoes/<?= e($r['id']) ?>">
                        <td><a class="numero-sac" href="/reclamacoes/<?= e($r['id']) ?>"><?= e($r['numero']) ?></a></td>
                        <td class="nowrap"><?= e(data_br($r['data'])) ?></td>
                        <td class="principal"><?= e($r['razao_social'] ?: $r['cliente']) ?></td>
                        <td><?= e($r['produto']) ?></td>
                        <td class="texto-longo"><?= e($r['descricao']) ?></td>
                        <td class="num<?= ($r['dias_aberto'] ?? 0) > $alerta ? ' alerta' : '' ?>">
                            <?= $r['dias_aberto'] === null ? '—' : $r['dias_aberto'] . ' dia' . ($r['dias_aberto'] === 1 ? '' : 's') ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
