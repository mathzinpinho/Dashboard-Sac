<?php
$erro = fn(string $c) => isset($erros[$c]) ? '<span class="erro-campo">' . e($erros[$c]) . '</span>' : '';
$cls = fn(string $c) => isset($erros[$c]) ? 'com-erro' : '';
?>
<div class="cabecalho">
    <div>
        <a class="voltar" href="/reclamacoes">‹ Reclamações</a>
        <h1>Apagar todas as reclamações</h1>
    </div>
</div>

<?php if (!$total): ?>
    <section class="cartao vazio">
        <p>Não há reclamações para apagar.</p>
        <a class="botao" href="/reclamacoes">Voltar</a>
    </section>
<?php else: ?>
    <section class="cartao zona-perigo">
        <h2>⚠ Esta ação não pode ser desfeita</h2>
        <p>Serão apagados <strong>definitivamente</strong>:</p>
        <ul class="lista-perigo">
            <li><strong><?= numero($total) ?></strong> reclamaç<?= $total === 1 ? 'ão' : 'ões' ?>, de todos os períodos</li>
            <?php if ($anexos): ?><li><strong><?= numero($anexos) ?></strong> foto<?= $anexos === 1 ? '' : 's' ?>/vídeo<?= $anexos === 1 ? '' : 's' ?> anexado<?= $anexos === 1 ? '' : 's' ?></li><?php endif ?>
            <?php if ($prejuizo > 0): ?><li>o histórico de <strong><?= e(moeda($prejuizo)) ?></strong> em valores gastos</li><?php endif ?>
        </ul>
        <p class="mudo">Os usuários e a configuração do sistema não são afetados. A numeração dos próximos SACs continua de onde parou.</p>

        <div class="passo">
            <span class="passo-n">1</span>
            <div>
                <strong>Baixe uma cópia antes</strong>
                <p class="mudo pequeno">A planilha exportada pode ser importada de volta em <em>Importar</em>, se precisar. As fotos e os vídeos não vão na planilha.</p>
                <a class="botao" href="/exportar">Exportar CSV de todas as reclamações</a>
            </div>
        </div>

        <form method="post" action="/reclamacoes/apagar-tudo" class="passo" data-apagar-tudo>
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
            <span class="passo-n">2</span>
            <div class="campos">
                <strong>Confirme</strong>
                <label class="<?= $cls('frase') ?>"><span>Digite <code><?= e($frase) ?></code> para confirmar</span>
                    <input name="frase" autocomplete="off" spellcheck="false" data-frase="<?= e($frase) ?>"><?= $erro('frase') ?>
                </label>
                <label class="<?= $cls('senha') ?>">Sua senha
                    <input type="password" name="senha" autocomplete="current-password"><?= $erro('senha') ?>
                </label>
                <label class="marcar <?= $cls('entendo') ?>">
                    <input type="checkbox" name="entendo" value="1">
                    <span>Entendo que as <?= numero($total) ?> reclamações serão apagadas e que isso não pode ser desfeito.</span>
                    <?= $erro('entendo') ?>
                </label>
                <div class="acoes">
                    <a class="botao" href="/reclamacoes">Cancelar</a>
                    <button class="botao perigo-cheio" disabled data-botao-apagar>Apagar <?= numero($total) ?> reclamações</button>
                </div>
            </div>
        </form>
    </section>
<?php endif ?>
