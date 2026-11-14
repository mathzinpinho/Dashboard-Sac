<?php
$sac = Usuarios::ehDoSac($u);
$admin = Usuarios::ehAdmin($u);
$euMesmo = $u['id'] === $eu['id'];
$marcadas = isset($u['telasSac']) && is_array($u['telasSac']) ? $u['telasSac'] : array_keys(Usuarios::TELAS);
$nome = $u['nome'] ?? $u['email'];
$v = fn(string $c) => e($u[$c] ?? '');
$erro = fn(string $c) => isset($erros[$c]) ? '<span class="erro-campo">' . e($erros[$c]) . '</span>' : '';
$cls = fn(string $c) => isset($erros[$c]) ? 'com-erro' : '';
$obr = '<span class="obrigatorio" aria-hidden="true">*</span>';
$criado = Usuarios::dataIso($u['criadoEm'] ?? '');
$adminProducao = !$sac && ($u['cargo'] ?? 'administrador') === 'administrador';
$removido = Usuarios::removido($u);
?>
<header class="pagina-cab">
    <div>
        <nav class="trilha" aria-label="Trilha">
            <a href="/usuarios">Usuários</a><?= icone('direita', 12) ?><span><?= e($nome) ?></span>
        </nav>
        <h1><?= e($nome) ?></h1>
        <p class="pagina-desc">
            <span class="selo <?= $sac ? 'selo-aberto' : 'selo-final' ?>"><?= $sac ? 'Criado pelo SAC' : 'Sistema de produção' ?></span>
            <?php if ($criado): ?>Cadastrado em <?= e(date('d/m/Y', strtotime($criado))) ?><?= !empty($u['criadoPor']) ? ' por ' . e($u['criadoPor']) : '' ?><?php endif ?>
        </p>
    </div>
</header>

<?php if ($erros): ?>
    <div class="aviso aviso-erro" role="alert"><?= icone('alerta') ?><span>Revise os campos destacados.</span></div>
<?php endif ?>

<?php if ($removido): ?>
    <div class="aviso aviso-erro aviso-acao" role="status">
        <?= icone('alerta') ?>
        <span>Este usuário foi removido do SAC e não consegue entrar aqui. O login no sistema de produção continua funcionando.</span>
        <form method="post" action="/usuarios/<?= e($u['id']) ?>/restaurar">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
            <button class="botao pequeno">Restaurar acesso</button>
        </form>
    </div>
<?php elseif (!$sac): ?>
    <div class="aviso aviso-info" role="note"><?= icone('cadeado') ?><span>Cadastro gerenciado pelo sistema de produção. Aqui dá para ajustar o tipo e as telas dentro do SAC; nome, e-mail e senha são alterados por lá.</span></div>
<?php endif ?>

<form method="post" action="/usuarios/<?= e($u['id']) ?>" class="formulario" autocomplete="off" data-editar-usuario>
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">

    <section class="cartao">
        <div class="secao-form">
            <header>
                <h2>Dados</h2>
                <p>Nome e e-mail usados para entrar no sistema.</p>
            </header>
            <div class="campos c3">
                <label class="<?= $cls('nome') ?>"><span>Nome<?= $sac ? $obr : '' ?></span>
                    <input name="nome" value="<?= $v('nome') ?>" <?= $sac ? 'required' : 'readonly' ?>><?= $erro('nome') ?>
                </label>
                <label class="<?= $cls('email') ?> span-2"><span>E-mail<?= $sac ? $obr : '' ?></span>
                    <input type="email" name="email" value="<?= $v('email') ?>" <?= $sac ? 'required' : 'readonly' ?> autocomplete="off"><?= $erro('email') ?>
                </label>
            </div>
        </div>

        <div class="secao-form">
            <header>
                <h2>Tipo de usuário</h2>
                <p>Administradores veem todas as telas e gerenciam usuários.</p>
            </header>
            <div class="<?= $cls('tipo') ?>">
                <div class="escolha-tipo" role="radiogroup" aria-label="Tipo de usuário">
                    <label class="opcao-tipo">
                        <input type="radio" name="tipo" value="operador" <?= !$admin ? 'checked' : '' ?> <?= $euMesmo && $admin ? 'disabled' : '' ?>>
                        <span><strong>Operador</strong><small>Vê só as telas liberadas abaixo</small></span>
                    </label>
                    <label class="opcao-tipo">
                        <input type="radio" name="tipo" value="administrador" <?= $admin ? 'checked' : '' ?>>
                        <span><strong>Administrador</strong><small>Todas as telas, inclusive Usuários</small></span>
                    </label>
                </div>
                <?= $erro('tipo') ?>
                <?php if ($euMesmo && $admin): ?><p class="dica-campo">Você não pode tirar o seu próprio acesso de administrador.</p><?php endif ?>
                <?php if ($adminProducao): ?><p class="dica-campo">É administrador no sistema de produção. Escolher Operador muda só o acesso dele no SAC; lá ele continua administrador.</p><?php endif ?>
            </div>
        </div>

        <div class="secao-form">
            <header>
                <h2>Telas liberadas</h2>
                <p>O que aparece no menu e pode ser aberto por este usuário.</p>
            </header>
            <div class="lista-telas<?= $admin ? ' desativada' : '' ?>" data-telas>
                <?php foreach (Usuarios::TELAS as $chave => [$rotulo, $desc]): ?>
                    <label class="tela-linha">
                        <span><strong><?= e($rotulo) ?></strong><small><?= e($desc) ?></small></span>
                        <input type="checkbox" role="switch" class="chave" name="telas[]" value="<?= e($chave) ?>" aria-label="<?= e($rotulo) ?>" <?=in_array($chave, $marcadas, true) ? 'checked' : '' ?>>
                    </label>
                <?php endforeach ?>
                <p class="dica-telas" data-dica-admin<?= $admin ? '' : ' hidden' ?>><?= icone('cadeado', 14) ?>Administradores veem todas as telas. As chaves valem se ele voltar a ser Operador.</p>
            </div>
        </div>

        <?php if ($sac): ?>
            <div class="secao-form">
                <header>
                    <h2>Trocar senha</h2>
                    <p>Deixe em branco para manter a senha atual.</p>
                </header>
                <div class="campos c3">
                    <label class="<?= $cls('senha') ?>"><span>Nova senha</span>
                        <input type="password" name="senha" minlength="<?= Usuarios::SENHA_MIN ?>" autocomplete="new-password" placeholder="Mínimo de <?= Usuarios::SENHA_MIN ?> caracteres"><?= $erro('senha') ?>
                    </label>
                    <label class="<?= $cls('senha2') ?>"><span>Confirmar nova senha</span>
                        <input type="password" name="senha2" minlength="<?= Usuarios::SENHA_MIN ?>" autocomplete="new-password"><?= $erro('senha2') ?>
                    </label>
                </div>
            </div>
        <?php endif ?>

        <div class="cartao-acoes">
            <a class="botao" href="/usuarios">Cancelar</a>
            <button class="botao primario"><?= icone('ok') ?>Salvar alterações</button>
        </div>
    </section>
</form>

<?php if (!$euMesmo && !$removido): ?>
    <section class="cartao zona-perigo">
        <div class="zona-perigo-cab excluir-usuario">
            <?= icone('lixeira', 18) ?>
            <div>
                <h2>Excluir usuário</h2>
                <?php if ($sac): ?>
                    <p>Apaga o cadastro de <?= e($nome) ?>. Ele perde o login no SAC. Não dá para desfazer.</p>
                <?php else: ?>
                    <p><?= e($nome) ?> deixa de acessar o SAC. O login no sistema de produção continua funcionando. Dá para desfazer depois.</p>
                <?php endif ?>
            </div>
            <form method="post" action="/usuarios/<?= e($u['id']) ?>/excluir" data-confirmar="<?= $sac
                ? 'Excluir o cadastro de ' . e($nome) . '? Essa ação não pode ser desfeita.'
                : 'Tirar o acesso de ' . e($nome) . ' ao SAC? O login no sistema de produção continua funcionando.' ?>">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
                <button class="botao perigo"><?= icone('lixeira') ?>Excluir usuário</button>
            </form>
        </div>
    </section>
<?php endif ?>
