<?php
$v = fn(string $c) => e($novo[$c] ?? '');
$erro = fn(string $c) => isset($erros[$c]) ? '<span class="erro-campo">' . e($erros[$c]) . '</span>' : '';
$cls = fn(string $c) => isset($erros[$c]) ? 'com-erro' : '';
$obr = '<span class="obrigatorio" aria-hidden="true">*</span>';
?>
<header class="pagina-cab">
    <div>
        <h1>Usuários</h1>
        <p class="pagina-desc">Quem pode registrar e acompanhar as reclamações.</p>
    </div>
</header>

<?php if ($erros): ?>
    <div class="aviso aviso-erro" role="alert"><?= icone('alerta') ?><span>Revise os campos destacados.</span></div>
<?php endif ?>

<form method="post" action="/usuarios" class="cartao" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
    <section class="secao-form">
        <header>
            <h2>Novo usuário</h2>
            <p>Pode registrar, editar e finalizar reclamações. Excluir e cadastrar usuários continua restrito a administradores.</p>
        </header>
        <div class="campos c4">
            <label class="<?= $cls('nome') ?>"><span>Nome<?= $obr ?></span>
                <input name="nome" value="<?= $v('nome') ?>" required><?= $erro('nome') ?>
            </label>
            <label class="<?= $cls('email') ?>"><span>E-mail<?= $obr ?></span>
                <input type="email" name="email" value="<?= $v('email') ?>" required autocomplete="off"><?= $erro('email') ?>
            </label>
            <label class="<?= $cls('senha') ?>"><span>Senha<?= $obr ?></span>
                <input type="password" name="senha" minlength="<?= Usuarios::SENHA_MIN ?>" required autocomplete="new-password" placeholder="Mínimo de <?= Usuarios::SENHA_MIN ?> caracteres"><?= $erro('senha') ?>
            </label>
            <label class="<?= $cls('senha2') ?>"><span>Confirmar senha<?= $obr ?></span>
                <input type="password" name="senha2" minlength="<?= Usuarios::SENHA_MIN ?>" required autocomplete="new-password"><?= $erro('senha2') ?>
            </label>
        </div>
        <p class="dica-campo">O login vale também no sistema de produção, mas lá o usuário não acessa nenhum módulo.</p>
    </section>
    <div class="cartao-acoes">
        <button class="botao primario"><?= icone('mais') ?>Cadastrar usuário</button>
    </div>
</form>

<section class="cartao sem-padding">
    <div class="cartao-cab">
        <div>
            <h2>Cadastrados pelo SAC</h2>
            <span class="cartao-desc">Usuários do sistema de produção também têm acesso e não aparecem aqui.</span>
        </div>
    </div>
    <?php if (!$lista): ?>
        <div class="vazio">
            <span class="vazio-icone"><?= icone('usuarios', 20) ?></span>
            <h3>Nenhum usuário cadastrado ainda</h3>
            <p>Os usuários criados pelo formulário acima aparecem nesta lista.</p>
        </div>
    <?php else: ?>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Nome</th><th>E-mail</th><th>Cadastrado em</th><th>Por</th></tr></thead>
                <tbody>
                <?php foreach ($lista as $u):
                    $quando = Usuarios::dataIso($u['criadoEm'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <span class="principal"><?= e($u['nome'] ?? '') ?></span>
                        </td>
                        <td><?= e($u['email'] ?? '') ?></td>
                        <td class="nowrap"><?= $quando ? e(date('d/m/Y H:i', strtotime($quando))) : '—' ?></td>
                        <td><?= e($u['criadoPor'] ?? '—') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
