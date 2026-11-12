<?php
$u = Auth::usuario();
$rotaAtual = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$aviso = pegar_aviso();

// item ativo do menu: "Nova reclamação" tem rota própria dentro de /reclamacoes
$ativo = fn(string $item): bool => match ($item) {
    'dashboard'   => $rotaAtual === '/',
    'nova'        => $rotaAtual === '/reclamacoes/nova',
    'reclamacoes' => str_starts_with($rotaAtual, '/reclamacoes') && $rotaAtual !== '/reclamacoes/nova',
    default       => str_starts_with($rotaAtual, '/' . $item),
};

$menu = [
    'Geral'         => [['dashboard', '/', 'painel', 'Dashboard']],
    'Atendimento'   => [
        ['reclamacoes', '/reclamacoes', 'caixa', 'Reclamações'],
        ['nova', '/reclamacoes/nova', 'mais', 'Nova reclamação'],
    ],
    'Dados'         => [['importar', '/importar', 'enviar', 'Importar planilha']],
];
if (Auth::admin()) {
    $menu['Administração'] = [['usuarios', '/usuarios', 'usuarios', 'Usuários']];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0e1219">
    <title><?= e($titulo ?? 'SAC') ?> · SAC Casa do Padeiro</title>
    <link rel="icon" href="/assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="/assets/css/app.css?v=16">
</head>
<body>
<div class="app">
    <aside class="lateral" id="lateral" aria-label="Navegação principal">
        <a class="lateral-marca" href="/">
            <img src="/assets/img/logo.png" alt="" width="32" height="32">
            <span>
                <strong>Casa do Padeiro</strong>
                <small>Central de SAC</small>
            </span>
        </a>

        <nav class="lateral-nav">
            <?php foreach ($menu as $grupo => $itens): ?>
                <div class="nav-grupo">
                    <span class="nav-titulo"><?= e($grupo) ?></span>
                    <?php foreach ($itens as [$chave, $href, $ic, $rotulo]): $on = $ativo($chave) ?>
                        <a class="nav-item<?= $on ? ' ativo' : '' ?>" href="<?= e($href) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= icone($ic) ?><span><?= e($rotulo) ?></span></a>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
        </nav>

        <div class="lateral-rodape">
            <span class="avatar" aria-hidden="true"><?= e(iniciais($u['nome'] ?? '?')) ?></span>
            <span class="lateral-usuario">
                <strong><?= e($u['nome'] ?? '') ?></strong>
                <small><?= Auth::admin() ? 'Administrador' : 'Usuário' ?></small>
            </span>
            <a class="botao fantasma icone-so" href="/sair" title="Sair" aria-label="Sair"><?= icone('sair') ?></a>
        </div>
    </aside>
    <div class="lateral-fundo" data-fechar-menu hidden></div>

    <div class="conteudo">
        <header class="barra-movel">
            <button class="botao fantasma icone-so" type="button" data-abrir-menu aria-controls="lateral" aria-expanded="false" aria-label="Abrir menu"><?= icone('menu', 18) ?></button>
            <a class="barra-movel-marca" href="/"><img src="/assets/img/logo.png" alt="" width="24" height="24"> Central de SAC</a>
        </header>

        <main class="pagina">
            <?php if ($aviso): ?>
                <div class="aviso aviso-<?= e($aviso[0]) ?>" role="status">
                    <?= icone($aviso[0] === 'erro' ? 'alerta' : 'ok-circulo') ?>
                    <span><?= e($aviso[1]) ?></span>
                </div>
            <?php endif ?>
            <?= $conteudo ?>
        </main>
    </div>
</div>

<script src="/assets/js/app.js?v=6"></script>
</body>
</html>
