<?php
$erro = fn(string $c) => isset($erros[$c]) ? '<span class="erro-campo">' . e($erros[$c]) . '</span>' : '';
$cls = fn(string $c) => isset($erros[$c]) ? 'com-erro' : '';
?>
<header class="pagina-cab">
    <div>
        <nav class="trilha" aria-label="Trilha">
            <a href="/reclamacoes">Reclamações</a><?= icone('direita', 12) ?><span>Apagar todas</span>
        </nav>
        <h1>Apagar todas as reclamações</h1>
    </div>
</header>

<?php if (!$total): ?>
    <section class="cartao vazio">
        <span class="vazio-icone"><?= icone('caixa', 20) ?></span>
        <h3>Não há reclamações para apagar</h3>
        <div class="acoes"><a class="botao" href="/reclamacoes">Voltar</a></div>
    </section>
<?php else: ?>
    <section class="cartao zona-perigo">
        <div class="zona-perigo-cab">
            <?= icone('alerta', 20) ?>
            <div>
                <h2>Esta ação não pode ser desfeita</h2>
                <p>Os dados abaixo serão apagados definitivamente. Usuários e configurações não são afetados, e a numeração dos próximos SACs continua de onde parou.</p>
            </div>
        </div>

        <ul class="lista-perigo">
            <li><span>Reclamações</span><strong><?= numero($total) ?></strong></li>
            <li><span>Fotos e vídeos</span><strong><?= numero($anexos) ?></strong></li>
            <li><span>Valores gastos registrados</span><strong><?= e(moeda($prejuizo)) ?></strong></li>
        </ul>

        <div class="passo">
            <span class="passo-n">1</span>
            <div>
                <strong>Baixe uma cópia</strong>
                <p class="mudo">O CSV pode ser importado de volta em Importar planilha. Fotos e vídeos não fazem parte do arquivo.</p>
                <a class="botao" href="/exportar"><?= icone('baixar') ?>Exportar todas as reclamações</a>
            </div>
        </div>

        <form method="post" action="/reclamacoes/apagar-tudo" class="passo" data-apagar-tudo>
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
            <span class="passo-n">2</span>
            <div class="campos">
                <strong>Confirme a exclusão</strong>
                <?php /* campo de usuário invisível: o gerenciador de senhas preenche este, e não o da frase */ ?>
                <input type="email" name="usuario_atual" value="<?= e(Auth::usuario()['email'] ?? '') ?>" autocomplete="username" class="invisivel" tabindex="-1" aria-hidden="true" readonly>
                <label class="<?= $cls('frase') ?>"><span>Digite <code><?= e($frase) ?></code> para confirmar</span>
                    <input type="search" name="frase" autocomplete="off" autocapitalize="characters" spellcheck="false"
                           data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other"
                           data-frase="<?= e($frase) ?>"><?= $erro('frase') ?>
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
                    <button class="botao perigo-cheio" disabled data-botao-apagar><?= icone('lixeira') ?>Apagar <?= numero($total) ?> reclamações</button>
                </div>
            </div>
        </form>
    </section>
<?php endif ?>
