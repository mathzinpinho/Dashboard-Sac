<?php
// Cria um usuário de teste no banco LOCAL (pasta data/), para usar sem o MongoDB.
// Uso: php scripts/criar-usuario.php email senha "Nome" [administrador|operador]
// Em produção não é necessário: o login usa os usuários do sistema de produção.
require dirname(__DIR__) . '/src/bootstrap.php';

if (env('MONGODB_URI')) exit("MONGODB_URI está definido: crie usuários pelo sistema de produção.\n");
if ($argc < 4) exit("Uso: php scripts/criar-usuario.php email senha \"Nome\" [cargo]\n");

$email = mb_strtolower($argv[1]);
if (db()->findOne('usuarios', ['email' => $email])) exit("Já existe um usuário com esse e-mail.\n");

db()->insert('usuarios', [
    'email'     => $email,
    'senhaHash' => password_hash($argv[2], PASSWORD_BCRYPT),
    'nome'      => $argv[3],
    'cargo'     => $argv[4] ?? 'administrador',
]);
echo "Usuário $email criado.\n";
