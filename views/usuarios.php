<?php
$v = fn(string $c) => e($novo[$c] ?? '');
$erro = fn(string $c) => isset($erros[$c]) ? '<span class="erro-campo">' . e($erros[$c]) . '</span>' : '';
$cls = fn(string $c) => isset($erros[$c]) ? 'com-erro' : '';
?>
<div class="cabecalho">
    <div>
        <h1>Usuários</h1>
        <p class="mudo">Cadastre quem vai registrar e acompanhar as reclamações.</p>
    </div>
</div>

<?php if ($erros): ?>
    <div class="aviso aviso-erro" role="alert">Confira os campos destacados.</div>
<?php endif ?>

<section class="cartao">
    <h2>Novo usuário</h2>
    <form method="post" action="/usuarios" class="formulario" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
        <div class="campos c4">
            <label class="<?= $cls('nome') ?>">Nome *
                <input name="nome" value="<?= $v('nome') ?>" required><?= $erro('nome') ?>
            </label>
            <label class="<?= $cls('email') ?>">E-mail *
                <input type="email" name="email" value="<?= $v('email') ?>" required autocomplete="off"><?= $erro('email') ?>
            </label>
            <label class="<?= $cls('senha') ?>">Senha *
                <input type="password" name="senha" minlength="<?= Usuarios::SENHA_MIN ?>" required autocomplete="new-password"><?= $erro('senha') ?>
            </label>
            <label class="<?= $cls('senha2') ?>">Confirmar senha *
                <input type="password" name="senha2" minlength="<?= Usuarios::SENHA_MIN ?>" required autocomplete="new-password"><?= $erro('senha2') ?>
            </label>
        </div>
        <div class="rodape-form">
            <p class="dica-campo">
                O usuário pode registrar, editar e finalizar reclamações. Excluir reclamações e cadastrar usuários continua só com administradores.
                O login é o mesmo do sistema de produção, mas lá ele não tem acesso a nenhum módulo.
            </p>
            <div class="acoes">
                <button class="botao primario">Cadastrar usuário</button>
            </div>
        </div>
    </form>
</section>

<section class="cartao sem-padding">
    <?php if (!$lista): ?>
        <p class="mudo vazio-lista">Nenhum usuário cadastrado pelo SAC ainda. Os usuários do sistema de produção também entram aqui.</p>
    <?php else: ?>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Nome</th><th>E-mail</th><th>Cadastrado em</th><th>Por</th></tr></thead>
                <tbody>
                <?php foreach ($lista as $u):
                    $quando = Usuarios::dataIso($u['criadoEm'] ?? '');
                    ?>
                    <tr>
                        <td><?= e($u['nome'] ?? '') ?></td>
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
