<?php
$v = fn(string $c) => e($novo[$c] ?? '');
$erro = fn(string $c) => isset($erros[$c]) ? '<span class="erro-campo">' . e($erros[$c]) . '</span>' : '';
$cls = fn(string $c) => isset($erros[$c]) ? 'com-erro' : '';
$obr = '<span class="obrigatorio" aria-hidden="true">*</span>';
$totalTelas = count(Usuarios::TELAS);
// removidos do SAC (usuários da produção) ficam fora da contagem e só aparecem no filtro "Removidos"
$removidos = count(array_filter($lista, [Usuarios::class, 'removido']));
$ativos = count($lista) - $removidos;
$doSac = count(array_filter($lista, [Usuarios::class, 'ehDoSac']));
?>
<header class="pagina-cab">
    <div>
        <h1>Usuários</h1>
        <p class="pagina-desc">Quem acessa o SAC e quais telas cada um vê.</p>
    </div>
    <div class="pagina-acoes">
        <a class="botao primario" href="#novo-usuario" data-abrir-novo><?= icone('mais') ?>Novo usuário</a>
    </div>
</header>

<?php if ($erros): ?>
    <div class="aviso aviso-erro" role="alert"><?= icone('alerta') ?><span>Revise os campos destacados.</span></div>
<?php endif ?>

<details class="cartao novo-usuario" id="novo-usuario"<?= $erros ? ' open' : '' ?>>
    <summary class="invisivel">Novo usuário</summary>
    <form method="post" action="/usuarios" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>">
        <section class="secao-form">
            <header>
                <h2>Novo usuário</h2>
                <p>Entra como Operador, com todas as telas liberadas. Depois de cadastrar, use Editar para ajustar o tipo e as telas.</p>
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
            <button class="botao" type="button" data-fechar-novo>Cancelar</button>
            <button class="botao primario"><?= icone('mais') ?>Cadastrar usuário</button>
        </div>
    </form>
</details>

<section class="cartao sem-padding" data-lista-usuarios>
    <div class="cartao-cab">
        <div>
            <h2>Todos os usuários <span class="mudo">· <?= $ativos ?></span></h2>
            <span class="cartao-desc">Cadastrados pelo SAC e pelo sistema de produção.</span>
        </div>
    </div>
    <div class="filtros filtros-usuarios">
        <div class="busca">
            <?= icone('busca') ?>
            <input type="search" placeholder="Buscar por nome ou e-mail" aria-label="Buscar usuário" data-busca-usuario>
        </div>
        <div class="segmentado" role="group" aria-label="Origem">
            <button type="button" class="ativo" data-origem="">Todos</button>
            <button type="button" data-origem="sac">SAC <span class="mudo"><?= $doSac ?></span></button>
            <button type="button" data-origem="producao">Produção <span class="mudo"><?= $ativos - $doSac ?></span></button>
            <?php if ($removidos): ?><button type="button" data-origem="removido">Removidos <span class="mudo"><?= $removidos ?></span></button><?php endif ?>
        </div>
    </div>
    <div class="tabela-rolagem">
        <table class="tabela">
            <thead><tr><th>Nome</th><th>E-mail</th><th>Tipo</th><th>Origem</th><th>Telas</th><th><span class="invisivel">Ações</span></th></tr></thead>
            <tbody>
            <?php foreach ($lista as $u):
                $sac = Usuarios::ehDoSac($u);
                $admin = Usuarios::ehAdmin($u);
                $removido = Usuarios::removido($u);
                $n = count(Usuarios::telas($u));
                $nome = $u['nome'] ?? '';
                ?>
                <tr data-href="/usuarios/<?= e($u['id']) ?>" data-origem="<?= $removido ? 'removido' : ($sac ? 'sac' : 'producao') ?>"<?= $removido ? ' hidden' : '' ?> data-texto="<?= e(mb_strtolower($nome . ' ' . ($u['email'] ?? ''))) ?>">
                    <td>
                        <span class="principal"><?= e($nome ?: '—') ?></span>
                        <?php if ($u['id'] === $eu['id']): ?><span class="mudo pequeno"> (você)</span><?php endif ?>
                    </td>
                    <td><?= e($u['email'] ?? '') ?></td>
                    <td><span class="selo <?= $admin ? 'selo-admin' : '' ?>"><?= $admin ? 'Administrador' : 'Operador' ?></span></td>
                    <td><span class="selo <?= $sac ? 'selo-aberto' : 'selo-final' ?>"><?= $sac ? 'SAC' : 'Produção' ?></span></td>
                    <td class="nowrap">
                        <?php if ($removido): ?><span class="alerta">Removido do SAC</span>
                        <?php elseif ($admin): ?>Todas
                        <?php elseif ($n === 0): ?><span class="alerta">Nenhuma</span>
                        <?php else: ?><?= $n === $totalTelas ? 'Todas' : "$n de $totalTelas" ?>
                        <?php endif ?>
                    </td>
                    <td class="num"><a class="botao pequeno" href="/usuarios/<?= e($u['id']) ?>"><?= icone('editar', 14) ?>Editar</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <div class="vazio-curto sem-resultado" data-sem-resultado hidden>Nenhum usuário encontrado.</div>
    </div>
</section>
