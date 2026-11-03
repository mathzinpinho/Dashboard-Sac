<?php
$u = Auth::usuario();
$rotaAtual = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$ativo = fn(string $prefixo) => ($prefixo === '/' ? $rotaAtual === '/' : str_starts_with($rotaAtual, $prefixo)) ? 'ativo' : '';
$aviso = pegar_aviso();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'SAC') ?> · SAC Casa do Padeiro</title>
    <link rel="icon" href="/assets/img/logo.png">
    <link rel="stylesheet" href="/assets/css/app.css?v=7">
</head>
<body>
<header class="topo">
    <a class="marca" href="/">
        <img src="/assets/img/logo.png" alt="">
        <span>SAC <small>Casa do Padeiro</small></span>
    </a>
    <nav class="menu">
        <a class="<?= $ativo('/') ?>" href="/">Dashboard</a>
        <a class="<?= $ativo('/reclamacoes') ?>" href="/reclamacoes">Reclamações</a>
        <a class="<?= $ativo('/importar') ?>" href="/importar">Importar</a>
        <?php if (Auth::admin()): ?><a class="<?= $ativo('/usuarios') ?>" href="/usuarios">Usuários</a><?php endif ?>
    </nav>
    <div class="usuario">
        <span><?= e($u['nome'] ?? '') ?></span>
        <a href="/sair">Sair</a>
    </div>
</header>

<main class="pagina">
    <?php if ($aviso): ?>
        <div class="aviso aviso-<?= e($aviso[0]) ?>" role="status"><?= e($aviso[1]) ?></div>
    <?php endif ?>
    <?= $conteudo ?>
</main>

<div id="dica" class="dica" role="tooltip" hidden></div>
<script src="/assets/js/app.js?v=3"></script>
</body>
</html>
