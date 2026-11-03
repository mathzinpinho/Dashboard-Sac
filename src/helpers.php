<?php

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Renderiza views/<nome>.php dentro de views/layout.php */
function view(string $nome, array $dados = [], bool $comLayout = true): void
{
    extract($dados);
    ob_start();
    require RAIZ . "/views/$nome.php";
    $conteudo = ob_get_clean();
    if ($comLayout) {
        require RAIZ . '/views/layout.php';
    } else {
        echo $conteudo;
    }
}

function redirecionar(string $para): never
{
    header('Location: ' . $para, true, 303);
    exit;
}

function https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function cookie(string $nome, string $valor, int $segundos): void
{
    setcookie($nome, $valor, [
        'expires'  => $segundos ? time() + $segundos : time() - 3600,
        'path'     => '/',
        'secure'   => https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Mensagem exibida uma única vez na próxima página (guardada em cookie, sem sessão no servidor) */
function avisar(string $msg, string $tipo = 'ok'): void
{
    cookie('sac_aviso', json_encode([$tipo, $msg]), 60);
}

function pegar_aviso(): ?array
{
    if (empty($_COOKIE['sac_aviso'])) return null;
    cookie('sac_aviso', '', 0);
    $a = json_decode($_COOKIE['sac_aviso'], true);
    return is_array($a) && count($a) === 2 ? $a : null;
}

function data_br(?string $iso): string
{
    if (!$iso) return '';
    $d = DateTime::createFromFormat('Y-m-d', $iso);
    return $d ? $d->format('d/m/Y') : $iso;
}

function mes_br(string $anoMes, bool $curto = true): string
{
    $nomes = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    [$a, $m] = explode('-', $anoMes);
    return $nomes[(int) $m - 1] . ($curto ? '/' . substr($a, 2) : ' ' . $a);
}

/** Converte horas em texto legível: "45 min", "6 h", "3,2 dias" */
function duracao(?float $horas): string
{
    if ($horas === null) return '—';
    if ($horas < 1) return round($horas * 60) . ' min';
    if ($horas < 24) return number_format($horas, $horas < 10 ? 1 : 0, ',', '.') . ' h';
    return number_format($horas / 24, 1, ',', '.') . ' dias';
}

/** 1234.5 -> "R$ 1.234,50" (null -> "—") */
function moeda(?float $v, bool $centavos = true): string
{
    if ($v === null) return '—';
    return 'R$ ' . number_format($v, $centavos ? 2 : 0, ',', '.');
}

function numero(int|float $n): string
{
    return number_format($n, 0, ',', '.');
}

/** Monta a query string mantendo os filtros atuais */
function qs(array $alterar = []): string
{
    $q = array_filter(array_merge($_GET, $alterar), fn($v) => $v !== null && $v !== '');
    return $q ? '?' . http_build_query($q) : '';
}
