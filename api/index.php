<?php
/*
 * Ponto de entrada único: todas as páginas passam por aqui (ver vercel.json e router.php).
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$rota = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

try {
    /* ---------- verificação de funcionamento (só sim/não, nunca valores) ---------- */
    if ($rota === '/saude') {
        $banco = true;
        try {
            db()->findOne('usuarios', ['email' => '__verificacao__']);
        } catch (Throwable) {
            $banco = false;
        }
        header('Content-Type: application/json');
        echo json_encode(['banco' => $banco, 'anexos' => Anexos::ativo()]);
        exit;
    }

    /* ---------- login ---------- */
    if ($rota === '/login') {
        $erro = null;
        if ($metodo === 'POST') {
            if (Auth::entrar($_POST['email'] ?? '', $_POST['senha'] ?? '')) redirecionar('/');
            $erro = 'E-mail ou senha incorretos.';
        }
        view('login', ['erro' => $erro, 'email' => $_POST['email'] ?? ''], false);
        exit;
    }
    if ($rota === '/sair') {
        Auth::sair();
        redirecionar('/login');
    }

    $usuario = Auth::usuario();
    if (!$usuario) redirecionar('/login');
    if ($metodo === 'POST') Auth::conferirCsrf();

    /* ---------- dashboard ---------- */
    if ($rota === '/') {
        [$de, $ate] = periodo($_GET['periodo'] ?? '12m', $_GET['de'] ?? '', $_GET['ate'] ?? '');
        $lista = Sac::listar(['de' => $de, 'ate' => $ate, 'tipo' => $_GET['tipo'] ?? '']);
        view('dashboard', [
            'titulo' => 'Dashboard',
            'ind'    => Sac::indicadores($lista, $de, $ate),
            'de'     => $de,
            'ate'    => $ate,
        ]);
        exit;
    }

    /* ---------- lista e exportação ---------- */
    if ($rota === '/reclamacoes' || $rota === '/exportar') {
        $f = [
            'de'     => $_GET['de'] ?? '',
            'ate'    => $_GET['ate'] ?? '',
            'tipo'   => $_GET['tipo'] ?? '',
            'status' => $_GET['status'] ?? '',
            'q'      => $_GET['q'] ?? '',
        ];
        $lista = Sac::listar($f);
        if ($rota === '/exportar') Planilha::exportar($lista);

        $porPagina = 30;
        $pagina = max(1, (int) ($_GET['p'] ?? 1));
        view('lista', [
            'titulo'  => 'Reclamações',
            'f'       => $f,
            'total'   => count($lista),
            'itens'   => array_slice($lista, ($pagina - 1) * $porPagina, $porPagina),
            'pagina'  => $pagina,
            'paginas' => max(1, (int) ceil(count($lista) / $porPagina)),
        ]);
        exit;
    }

    /* ---------- token para o navegador enviar um anexo direto ao Blob ---------- */
    if ($rota === '/anexos/token' && $metodo === 'POST') {
        $evento = json_decode(file_get_contents('php://input'), true) ?: [];
        header('Content-Type: application/json');
        if (($evento['type'] ?? '') !== 'blob.generate-client-token') {
            http_response_code(400);
            echo json_encode(['erro' => 'Evento inválido.']);
            exit;
        }
        echo json_encode([
            'type'        => 'blob.generate-client-token',
            'clientToken' => Anexos::tokenEnvio((string) ($evento['payload']['pathname'] ?? '')),
        ]);
        exit;
    }

    /* ---------- nova reclamação ---------- */
    if ($rota === '/reclamacoes/nova') {
        $r = ['data' => date('Y-m-d'), 'hora' => date('H:i'), 'tipo' => opcoes('tipos')[0], 'anexos' => []];
        $erros = [];
        if ($metodo === 'POST') {
            [$r, $erros] = Sac::preparar($_POST);
            $r['anexos'] = anexosDoFormulario([], $usuario);
            if (!$erros) {
                $id = Sac::criar($r, $usuario);
                avisar('Reclamação registrada.');
                redirecionar('/reclamacoes/' . $id);
            }
        }
        view('form', ['titulo' => 'Nova reclamação', 'r' => $r, 'erros' => $erros, 'sug' => Sac::sugestoes()]);
        exit;
    }

    /* ---------- ver / editar / excluir ---------- */
    if (preg_match('#^/reclamacoes/([A-Za-z0-9]+)(/excluir)?$#', $rota, $m)) {
        $atual = Sac::buscar($m[1]) ?? throw new DomainException('Reclamação não encontrada.');

        if (!empty($m[2])) {
            if ($metodo !== 'POST' || !Auth::admin()) throw new DomainException('Somente administradores podem excluir.');
            Sac::excluir($atual['id']);
            Anexos::excluir(array_column($atual['anexos'] ?? [], 'url'));
            avisar('Reclamação nº ' . $atual['numero'] . ' excluída.');
            redirecionar('/reclamacoes');
        }

        $r = $atual;
        $erros = [];
        if ($metodo === 'POST') {
            [$dados, $erros] = Sac::preparar($_POST);
            $antes = $atual['anexos'] ?? [];
            $dados['anexos'] = anexosDoFormulario($antes, $usuario);
            if (!$erros) {
                Sac::atualizar($atual['id'], $dados, $usuario);
                // arquivos tirados da reclamação saem também do armazenamento
                Anexos::excluir(array_diff(array_column($antes, 'url'), array_column($dados['anexos'], 'url')));
                avisar('Alterações salvas.');
                redirecionar('/reclamacoes/' . $atual['id']);
            }
            $r = array_merge($atual, $dados);
        }
        view('form', ['titulo' => 'SAC nº ' . $atual['numero'], 'r' => $r, 'erros' => $erros, 'sug' => Sac::sugestoes()]);
        exit;
    }

    /* ---------- importação ---------- */
    if ($rota === '/importar') {
        $res = null;
        $erro = null;
        if ($metodo === 'POST' && isset($_POST['linhas'])) {
            // 2º passo: confirma as linhas lidas na prévia (vêm no próprio formulário, sem sessão no servidor)
            $linhas = json_decode(base64_decode($_POST['linhas']), true) ?: [];
            [$ok, $rep, $errosLinhas] = Planilha::importar($linhas, $usuario);
            $res = compact('ok', 'rep', 'errosLinhas');
        } elseif ($metodo === 'POST') {
            // 1º passo: lê o arquivo e mostra a prévia
            $arq = $_FILES['arquivo'] ?? null;
            if (!$arq || $arq['error'] !== UPLOAD_ERR_OK) {
                $erro = 'Selecione o arquivo da planilha.';
            } else {
                try {
                    [$linhas, $mapa] = Planilha::ler($arq['tmp_name'], $arq['name']);
                    $res = ['linhas' => $linhas, 'mapa' => $mapa, 'arquivo' => $arq['name']];
                } catch (RuntimeException $ex) {
                    $erro = $ex->getMessage();
                }
            }
        }
        view('importar', ['titulo' => 'Importar planilha', 'res' => $res, 'erro' => $erro]);
        exit;
    }

    http_response_code(404);
    view('erro', ['titulo' => 'Página não encontrada', 'msg' => 'Esta página não existe.']);
} catch (DomainException $ex) {
    http_response_code(404);
    view('erro', ['titulo' => 'Ops', 'msg' => $ex->getMessage()]);
} catch (Throwable $ex) {
    error_log((string) $ex);
    http_response_code(500);
    // detalhes só no log da Vercel: a mensagem pode conter dados de conexão do banco
    view('erro', ['titulo' => 'Erro', 'msg' => 'Algo deu errado. Tente novamente; se continuar, avise o responsável pelo sistema.']);
}

/**
 * Lista de anexos enviada pelo formulário. Quem enviou e quando é sempre definido aqui
 * (para os anexos novos) ou mantido do registro salvo (para os que já existiam).
 */
function anexosDoFormulario(array $antes, array $usuario): array
{
    // sem armazenamento configurado não dá para conferir a lista: mantém o que já estava salvo
    if (!Anexos::ativo()) return $antes;
    $existentes = array_column($antes, null, 'url');
    $lista = Anexos::limpar((string) ($_POST['anexos'] ?? '[]'));
    foreach ($lista as &$a) {
        $velho = $existentes[$a['url']] ?? null;
        $a['enviado_por'] = $velho['enviado_por'] ?? $usuario['nome'];
        $a['enviado_em'] = $velho['enviado_em'] ?? date('c');
    }
    return $lista;
}

/** Atalhos de período do dashboard -> [de, ate] */
function periodo(string $p, string $de, string $ate): array
{
    $hoje = new DateTimeImmutable('today');
    return match ($p) {
        'mes'   => [$hoje->format('Y-m-01'), $hoje->format('Y-m-d')],
        '3m'    => [$hoje->modify('first day of -2 months')->format('Y-m-d'), $hoje->format('Y-m-d')],
        '12m'   => [$hoje->modify('first day of -11 months')->format('Y-m-d'), $hoje->format('Y-m-d')],
        'ano'   => [$hoje->format('Y-01-01'), $hoje->format('Y-m-d')],
        'tudo'  => ['', ''],
        default => [$de, $ate],
    };
}
