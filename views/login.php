<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0e1219">
    <title>Entrar · SAC Casa do Padeiro</title>
    <link rel="icon" href="/assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="/assets/css/app.css?v=13">
</head>
<body class="tela-login">
<main class="login-caixa">
    <div class="login-marca">
        <img src="/assets/img/logo.png" alt="" width="40" height="40">
        <span>
            <strong>Casa do Padeiro</strong>
            <small>Central de SAC</small>
        </span>
    </div>

    <form class="cartao login" method="post" action="/login">
        <header>
            <h1>Entrar</h1>
            <p>Use o mesmo e-mail e senha do sistema de produção.</p>
        </header>

        <?php if ($erro): ?>
            <div class="aviso aviso-erro" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></div>
        <?php endif ?>

        <label>E-mail
            <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
        </label>
        <label>Senha
            <input type="password" name="senha" required autocomplete="current-password">
        </label>
        <button class="botao primario largo">Entrar</button>
    </form>

    <p class="login-rodape">Problemas para entrar? Fale com um administrador do sistema.</p>
</main>
</body>
</html>
