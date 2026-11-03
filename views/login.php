<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · SAC Casa do Padeiro</title>
    <link rel="icon" href="/assets/img/logo.png">
    <link rel="stylesheet" href="/assets/css/app.css?v=2">
</head>
<body class="tela-login">
<form class="cartao login" method="post" action="/login">
    <img src="/assets/img/logo.png" alt="Casa do Padeiro" class="login-logo">
    <h1>SAC</h1>
    <p class="sub">Use o mesmo login do sistema de produção.</p>

    <?php if ($erro): ?><div class="aviso aviso-erro"><?= e($erro) ?></div><?php endif ?>

    <label>E-mail
        <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
    </label>
    <label>Senha
        <input type="password" name="senha" required autocomplete="current-password">
    </label>
    <button class="botao primario largo">Entrar</button>
</form>
</body>
</html>
