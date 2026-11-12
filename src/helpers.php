<?php

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/**
 * Ícone SVG em linha (traços no estilo Lucide, https://lucide.dev, licença ISC).
 * Herda a cor do texto (currentColor).
 */
function icone(string $nome, int $tamanho = 16, string $classe = ''): string
{
    static $tracos = [
        'painel'     => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'caixa'      => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'mais'       => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'enviar'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'baixar'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'usuarios'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'sair'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'busca'      => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'clipe'      => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'play'       => '<polygon points="6 3 20 12 6 21 6 3"/>',
        'lixeira'    => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'alerta'     => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'ok'         => '<path d="M20 6 9 17l-5-5"/>',
        'ok-circulo' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'info'       => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'fechar'     => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'esquerda'   => '<path d="m15 18-6-6 6-6"/>',
        'direita'    => '<path d="m9 18 6-6-6-6"/>',
        'menu'       => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>',
        'planilha'   => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/>',
        'imagem'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
        'relogio'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'calendario' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'filtro'     => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
    ];
    $svg = $tracos[$nome] ?? '';
    $cheio = $nome === 'play' ? 'currentColor' : 'none';
    return '<svg class="icone ' . e($classe) . '" width="' . $tamanho . '" height="' . $tamanho . '" viewBox="0 0 24 24" fill="' . $cheio
        . '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $svg . '</svg>';
}

/** "Maria da Silva" -> "MS" */
function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $ini = mb_substr($partes[0] ?? '?', 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '');
    return mb_strtoupper($ini);
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
