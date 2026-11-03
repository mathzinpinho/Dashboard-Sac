<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
define('RAIZ', dirname(__DIR__));

// .env local (na Vercel as variáveis vêm do painel do projeto)
if (is_file(RAIZ . '/.env')) {
    foreach (file(RAIZ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if ($linha[0] === '#' || !str_contains($linha, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $linha, 2));
        if (getenv($k) === false) putenv("$k=" . trim($v, "\"'"));
    }
}

function env(string $chave, ?string $padrao = null): ?string
{
    $v = getenv($chave);
    return ($v === false || $v === '') ? $padrao : $v;
}

function opcoes(?string $chave = null): mixed
{
    static $o = null;
    $o ??= require RAIZ . '/config/opcoes.php';
    return $chave ? $o[$chave] : $o;
}

require RAIZ . '/src/helpers.php';
require RAIZ . '/src/Db.php';
require RAIZ . '/src/Auth.php';
require RAIZ . '/src/Sac.php';
require RAIZ . '/src/Planilha.php';
