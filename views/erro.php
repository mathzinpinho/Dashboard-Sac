<section class="cartao vazio">
    <span class="vazio-icone"><?= icone('alerta', 20) ?></span>
    <h3><?= e($titulo) ?></h3>
    <p><?= e($msg) ?></p>
    <div class="acoes">
        <?php if (!empty($sair)): ?>
            <a class="botao" href="/sair"><?= icone('sair') ?>Sair</a>
        <?php else: ?>
            <a class="botao" href="/">Voltar ao início</a>
        <?php endif ?>
    </div>
</section>
