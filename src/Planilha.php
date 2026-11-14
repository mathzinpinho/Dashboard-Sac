<?php
/*
 * Importação da planilha de SAC (.xlsx ou .csv) e exportação do relatório em .xlsx no mesmo layout.
 * Lê a aba "SAC" (ou a primeira aba) e reconhece as colunas pelo nome do cabeçalho.
 */

final class Planilha
{
    /** cabeçalho normalizado => campo */
    private const COLUNAS = [
        'data' => 'data',
        'data da reclamacao' => 'data',
        'hora' => 'hora',
        'codigo pedido' => 'codigo_pedido',
        'codigo do pedido' => 'codigo_pedido',
        'pedido' => 'codigo_pedido',
        'cliente' => 'cliente',
        'razao social' => 'razao_social',
        'contato' => 'contato',
        'telefone' => 'contato',
        'reclamacao - qualidade' => 'tipo',
        'tipo' => 'tipo',
        'tipo de reclamacao' => 'tipo',
        'data de fabricacao' => 'data_fabricacao',
        'lote' => 'lote',
        'lote - horario' => 'lote_horario',
        'produto' => 'produto',
        'descricao do atendimento' => 'descricao',
        'descricao' => 'descricao',
        'solucao / retorno' => 'solucao',
        'solucao' => 'solucao',
        'data de finalizacao' => 'data_finalizacao',
        'hora de finalizacao' => 'hora_finalizacao',
        'observacoes' => 'observacoes',
        'valor' => 'valor',
        'valor gasto' => 'valor',
        'valor gasto (r$)' => 'valor',
        'prejuizo' => 'valor',
        'custo' => 'valor',
    ];

    private const CAMPOS_DATA = ['data', 'data_fabricacao', 'data_finalizacao'];
    private const CAMPOS_HORA = ['hora', 'hora_finalizacao', 'lote_horario'];

    private static function normalizar(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c']);
        return preg_replace('/\s+/', ' ', $s);
    }

    /** Lê o arquivo e devolve as linhas como arrays [campo => valor] + colunas reconhecidas */
    public static function ler(string $caminho, string $nomeOriginal): array
    {
        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $linhas = match ($ext) {
            'xlsx'  => self::lerXlsx($caminho),
            'csv'   => self::lerCsv($caminho),
            default => throw new RuntimeException('Envie um arquivo .xlsx ou .csv.'),
        };

        // procura a linha de cabeçalho (a primeira que tenha "Data" e alguma coluna conhecida)
        foreach ($linhas as $i => $linha) {
            $mapa = [];
            foreach ($linha as $col => $valor) {
                $campo = self::COLUNAS[self::normalizar((string) $valor)] ?? null;
                if ($campo && !in_array($campo, $mapa, true)) $mapa[$col] = $campo;
            }
            if (in_array('data', $mapa, true) && count($mapa) >= 3) {
                return [self::converter(array_slice($linhas, $i + 1), $mapa), $mapa];
            }
        }
        throw new RuntimeException('Não encontrei o cabeçalho da planilha (coluna "Data", "Cliente", "Produto"...).');
    }

    private static function converter(array $linhas, array $mapa): array
    {
        $saida = [];
        foreach ($linhas as $linha) {
            $r = [];
            foreach ($mapa as $col => $campo) {
                $v = trim((string) ($linha[$col] ?? ''));
                if (in_array($campo, self::CAMPOS_DATA, true)) $v = self::data($v);
                elseif (in_array($campo, self::CAMPOS_HORA, true)) $v = self::hora($v);
                elseif ($campo === 'tipo') $v = self::tipo($v);
                $r[$campo] = $v;
            }
            // pula linhas vazias (a tabela do Excel vai até a linha 512)
            if (implode('', $r) === '' || ((($r['data'] ?? '') === '') && (($r['descricao'] ?? '') === '') && (($r['razao_social'] ?? '') === ''))) {
                continue;
            }
            $saida[] = $r;
        }
        return $saida;
    }

    /** "Reclamação - Qualidade" -> "Qualidade" */
    private static function tipo(string $v): string
    {
        $v = trim(preg_replace('/^reclama[çc][ãa]o\s*-\s*/iu', '', $v));
        foreach (opcoes('tipos') as $t) {
            if (self::normalizar($t) === self::normalizar($v)) return $t;
        }
        return $v;
    }

    /** Número de série do Excel ou dd/mm/aaaa -> aaaa-mm-dd. Texto que não é data fica como está. */
    private static function data(string $v): string
    {
        if ($v === '') return '';
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            return gmdate('Y-m-d', (int) round(((float) $v - 25569) * 86400));
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) return substr($v, 0, 10);
        return '';
    }

    /** Fração de dia do Excel (0.4583) ou "11:00" -> "11:00". Textos como "14:46 - 17:59" ficam como estão. */
    private static function hora(string $v): string
    {
        if ($v === '') return '';
        if (is_numeric($v)) {
            $min = (int) round(fmod((float) $v, 1) * 1440) % 1440;
            return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $v, $m)) return sprintf('%02d:%s', $m[1], $m[2]);
        return $v;
    }

    private static function lerCsv(string $caminho): array
    {
        $conteudo = file_get_contents($caminho);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
        if (!mb_check_encoding($conteudo, 'UTF-8')) $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        $primeira = strtok($conteudo, "\n");
        $sep = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';
        $f = fopen('php://temp', 'r+');
        fwrite($f, $conteudo);
        rewind($f);
        $linhas = [];
        while (($l = fgetcsv($f, null, $sep, '"', '')) !== false) $linhas[] = $l;
        fclose($f);
        return $linhas;
    }

    private static function lerXlsx(string $caminho): array
    {
        $zip = new ZipArchive();
        if ($zip->open($caminho) !== true) throw new RuntimeException('Não foi possível abrir o arquivo .xlsx.');

        $textos = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (simplexml_load_string($xml)->si as $si) {
                $t = isset($si->t) ? (string) $si->t : '';
                foreach ($si->r as $r) $t .= (string) $r->t;
                $textos[] = $t;
            }
        }

        $xml = $zip->getFromName(self::caminhoAba($zip));
        $zip->close();
        if ($xml === false) throw new RuntimeException('Aba da planilha não encontrada.');

        $linhas = [];
        foreach (simplexml_load_string($xml)->sheetData->row as $row) {
            $linha = [];
            foreach ($row->c as $c) {
                $col = preg_replace('/\d+/', '', (string) $c['r']);
                $tipo = (string) $c['t'];
                $v = match ($tipo) {
                    's'         => $textos[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default     => (string) $c->v,
                };
                $linha[$col] = $v;
            }
            $linhas[] = $linha;
        }
        return $linhas;
    }

    /** Caminho interno da aba chamada "SAC"; se não existir, a primeira aba */
    private static function caminhoAba(ZipArchive $zip): string
    {
        $wb = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $alvos = [];
        foreach ($rels->Relationship as $rel) $alvos[(string) $rel['Id']] = (string) $rel['Target'];

        $escolhida = null;
        foreach ($wb->sheets->sheet as $s) {
            $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $escolhida ??= $rid;
            if (self::normalizar((string) $s['name']) === 'sac') {
                $escolhida = $rid;
                break;
            }
        }
        $alvo = ltrim($alvos[$escolhida] ?? 'worksheets/sheet1.xml', '/');
        return str_starts_with($alvo, 'xl/') ? $alvo : 'xl/' . $alvo;
    }

    /** Identifica a mesma reclamação para não importar duas vezes */
    public static function chave(array $r): string
    {
        return sha1(implode('|', array_map(fn($c) => self::normalizar((string) ($r[$c] ?? '')),
            ['data', 'hora', 'cliente', 'razao_social', 'produto', 'lote', 'data_fabricacao', 'descricao'])));
    }

    /** Importa as linhas já lidas. Retorna [importadas, repetidas, com_erro]. */
    public static function importar(array $linhas, array $usuario): array
    {
        $existentes = [];
        foreach (db()->find(Sac::COL) as $r) $existentes[Planilha::chave($r)] = true;

        $ok = $rep = 0;
        $erros = [];
        foreach ($linhas as $i => $linha) {
            $chave = self::chave($linha);
            if (isset($existentes[$chave])) {
                $rep++;
                continue;
            }
            [$d, $e] = Sac::preparar($linha, false);
            if ($e) {
                $erros[] = 'Linha ' . ($i + 2) . ': ' . implode(' ', $e);
                continue;
            }
            Sac::criar($d, $usuario, 'planilha');
            $existentes[$chave] = true;
            $ok++;
        }
        return [$ok, $rep, $erros];
    }

    /* ---------------- exportação .xlsx ---------------- */

    // cores do relatório (mesmo visual do catálogo de produtos)
    private const AZUL = '1A2030';
    private const DOURADO = 'CC9550';
    private const CREME = 'FAF6F0';
    private const BRANCO = 'FFFFFF';

    /** colunas centralizadas no detalhamento; as demais ficam à esquerda */
    private const CENTRALIZADAS = ['data', 'hora', 'codigo_pedido', 'contato', 'tipo', 'data_fabricacao', 'lote', 'lote_horario', 'data_finalizacao', 'hora_finalizacao'];
    /** colunas de texto longo (quebram linha) */
    private const LONGAS = ['produto', 'descricao', 'solucao', 'observacoes'];
    private const LARGURAS = [
        'data' => 12, 'hora' => 9, 'codigo_pedido' => 15, 'cliente' => 24, 'razao_social' => 32, 'contato' => 17,
        'tipo' => 14, 'data_fabricacao' => 14, 'lote' => 12, 'lote_horario' => 15, 'produto' => 36, 'descricao' => 45,
        'solucao' => 45, 'valor' => 15, 'data_finalizacao' => 14, 'hora_finalizacao' => 13, 'observacoes' => 38,
    ];

    /**
     * Envia o relatório .xlsx: aba "SAC" com todas as reclamações detalhadas (mesmo layout da planilha,
     * pode ser importada de volta) e aba "Resumo" com os indicadores do dashboard.
     * $f: filtros usados (de, ate, tipo, status, q), exibidos no cabeçalho.
     */
    public static function exportar(array $lista, array $f = []): never
    {
        $x = new Xlsx();
        $info = self::descreverFiltros($f) . '   |   Gerado em: ' . date('d/m/Y, H:i:s');
        self::abaDetalhe($x, $lista, $info);
        self::abaResumo($x, $lista, $f, $info);
        $x->baixar('sac-' . date('Y-m-d') . '.xlsx');
    }

    private static function descreverFiltros(array $f): string
    {
        $de = $f['de'] ?? '';
        $ate = $f['ate'] ?? '';
        $partes = ['Período: ' . ($de || $ate ? (data_br($de) ?: 'Início') . ' a ' . (data_br($ate) ?: 'hoje') : 'Todo o histórico')];
        if (!empty($f['tipo'])) $partes[] = 'Tipo: ' . $f['tipo'];
        if (!empty($f['status'])) $partes[] = 'Status: ' . ($f['status'] === 'finalizado' ? 'Finalizado' : 'Aberto');
        if (trim($f['q'] ?? '') !== '') $partes[] = 'Busca: "' . trim($f['q']) . '"';
        return implode('   |   ', $partes);
    }

    /** Linhas 1 a 3: faixa azul com título, subtítulo e informações (linha 4 é um respiro) */
    private static function faixa(Xlsx $x, string $subtitulo, string $info): array
    {
        $titulo = $x->estilo(['fonte' => ['b' => 1, 'cor' => self::DOURADO, 'tam' => 16], 'fundo' => self::AZUL, 'alinhar' => 'center']);
        $sub = $x->estilo(['fonte' => ['b' => 1, 'cor' => 'EEEEEE', 'tam' => 13], 'fundo' => self::AZUL, 'alinhar' => 'center']);
        $inf = $x->estilo(['fonte' => ['i' => 1, 'cor' => '888888', 'tam' => 10], 'fundo' => self::AZUL, 'alinhar' => 'center']);
        return [
            1 => ['altura' => 36, 'celulas' => [['v' => '🍞  Casa do Padeiro — SAC', 's' => $titulo]], 'estilo' => $titulo],
            2 => ['altura' => 24, 'celulas' => [['v' => $subtitulo, 's' => $sub]], 'estilo' => $sub],
            3 => ['altura' => 20, 'celulas' => [['v' => $info, 's' => $inf]], 'estilo' => $inf],
            4 => ['altura' => 6, 'celulas' => []],
        ];
    }

    /** Preenche o fundo da faixa em todas as colunas e devolve as mesclagens */
    private static function completarFaixa(array &$linhas, int $ultimaCol): array
    {
        foreach ([1, 2, 3] as $n) {
            for ($c = 1; $c <= $ultimaCol; $c++) $linhas[$n]['celulas'][$c] = ['s' => $linhas[$n]['estilo']];
            unset($linhas[$n]['estilo']);
        }
        $fim = Xlsx::coluna($ultimaCol);
        return ["A1:{$fim}1", "A2:{$fim}2", "A3:{$fim}3"];
    }

    private static function abaDetalhe(Xlsx $x, array $lista, string $info): void
    {
        $campos = array_keys(Sac::CAMPOS);
        $colunas = array_merge(['numero' => 'Nº'], Sac::CAMPOS, ['status' => 'Status', 'tempo_resolucao_h' => 'Tempo de Resolução (h)']);
        $chaves = array_keys($colunas);
        $ultima = count($chaves) - 1;
        $colValor = array_search('valor', $chaves, true);
        $colTempo = array_search('tempo_resolucao_h', $chaves, true);

        $total = count($lista);
        $linhas = self::faixa($x, 'Relatório Detalhado de Reclamações', 'Total: ' . numero($total) . ' reclamaç' . ($total === 1 ? 'ão' : 'ões') . '   |   ' . $info);
        $mesclar = self::completarFaixa($linhas, $ultima);

        $cab = $x->estilo(['fonte' => ['b' => 1, 'cor' => '0E1219', 'tam' => 11], 'fundo' => self::DOURADO, 'borda' => 'cabecalho', 'alinhar' => 'center', 'quebrar' => 1]);
        $linhas[5] = ['altura' => 30, 'celulas' => array_map(fn($t) => ['v' => $t, 's' => $cab], array_values($colunas))];

        // estilos das linhas, alternando branco e creme
        $est = [];
        foreach ([self::BRANCO, self::CREME] as $z => $fundo) {
            $base = ['fundo' => $fundo, 'borda' => 'linha', 'fonte' => ['tam' => 10]];
            $est[$z] = [
                'num'        => $x->estilo(['fonte' => ['b' => 1, 'cor' => self::AZUL, 'tam' => 10], 'alinhar' => 'center'] + $base),
                'esquerda'   => $x->estilo($base),
                'longa'      => $x->estilo(['quebrar' => 1] + $base),
                'centro'     => $x->estilo(['alinhar' => 'center'] + $base),
                'data'       => $x->estilo(['alinhar' => 'center', 'formato' => 'dd/mm/yyyy'] + $base),
                'moeda'      => $x->estilo(['alinhar' => 'right', 'formato' => '"R$" #,##0.00'] + $base),
                'horas'      => $x->estilo(['alinhar' => 'center', 'formato' => '#,##0.00'] + $base),
                'aberto'     => $x->estilo(['fonte' => ['b' => 1, 'cor' => 'C0392B', 'tam' => 10], 'alinhar' => 'center'] + $base),
                'finalizado' => $x->estilo(['fonte' => ['b' => 1, 'cor' => '2E7D32', 'tam' => 10], 'alinhar' => 'center'] + $base),
            ];
        }

        $n = 6;
        $somaValor = 0.0;
        $tempos = [];
        foreach ($lista as $i => $r) {
            $s = $est[$i % 2];
            $cel = [['v' => isset($r['numero']) && is_numeric($r['numero']) ? (int) $r['numero'] : ($r['numero'] ?? ''), 's' => $s['num']]];
            foreach ($campos as $c) {
                $v = $r[$c] ?? '';
                if (in_array($c, self::CAMPOS_DATA, true)) {
                    $serie = is_string($v) && $v !== '' ? Xlsx::serieData($v) : null;
                    $cel[] = ['v' => $serie ?? (string) $v, 's' => $s['data']];
                } elseif ($c === 'valor') {
                    $cel[] = ['v' => $v === null || $v === '' ? null : (float) $v, 's' => $s['moeda']];
                    if (is_numeric($v)) $somaValor += (float) $v;
                } else {
                    $estilo = in_array($c, self::LONGAS, true) ? 'longa' : (in_array($c, self::CENTRALIZADAS, true) ? 'centro' : 'esquerda');
                    $cel[] = ['v' => (string) $v, 's' => $s[$estilo]];
                }
            }
            $finalizado = ($r['status'] ?? '') === 'finalizado';
            $cel[] = ['v' => $finalizado ? 'Finalizado' : 'Aberto', 's' => $s[$finalizado ? 'finalizado' : 'aberto']];
            $tempo = isset($r['tempo_resolucao_h']) ? (float) $r['tempo_resolucao_h'] : null;
            if ($tempo !== null) $tempos[] = $tempo;
            $cel[] = ['v' => $tempo, 's' => $s['horas']];
            $linhas[$n++] = ['celulas' => $cel];
        }
        $ultimaLinha = max(5, $n - 1);

        // rodapé (SUBTOTAL respeita o filtro aplicado no Excel)
        $rod = $x->estilo(['fonte' => ['b' => 1, 'cor' => self::DOURADO, 'tam' => 11], 'fundo' => self::AZUL, 'alinhar' => 'center']);
        $rodMoeda = $x->estilo(['fonte' => ['b' => 1, 'cor' => self::DOURADO, 'tam' => 11], 'fundo' => self::AZUL, 'alinhar' => 'right', 'formato' => '"R$" #,##0.00']);
        $rodHoras = $x->estilo(['fonte' => ['b' => 1, 'cor' => self::DOURADO, 'tam' => 11], 'fundo' => self::AZUL, 'alinhar' => 'center', 'formato' => '#,##0.00']);
        $rodape = array_fill(0, $ultima + 1, ['s' => $rod]);
        $rodape[0] = ['v' => 'TOTAL', 's' => $rod];
        $rodape[1] = ['v' => numero($total) . ' reclamaç' . ($total === 1 ? 'ão' : 'ões'), 's' => $rod];
        if ($total) {
            $lv = Xlsx::coluna($colValor);
            $lt = Xlsx::coluna($colTempo);
            $rodape[$colValor - 1] = ['v' => 'Valor gasto:', 's' => $rod];
            $rodape[$colValor] = ['f' => "SUBTOTAL(9,{$lv}6:{$lv}{$ultimaLinha})", 'v' => round($somaValor, 2), 's' => $rodMoeda];
            $rodape[$colTempo - 1] = ['v' => 'Tempo médio (h):', 's' => $rod];
            $rodape[$colTempo] = $tempos
                ? ['f' => "IFERROR(SUBTOTAL(1,{$lt}6:{$lt}{$ultimaLinha}),\"\")", 'v' => round(array_sum($tempos) / count($tempos), 2), 's' => $rodHoras]
                : ['s' => $rodHoras];
        }
        $linhas[$ultimaLinha + 2] = ['altura' => 26, 'celulas' => $rodape];

        $larguras = [8];
        foreach ($campos as $c) $larguras[] = self::LARGURAS[$c] ?? 16;
        array_push($larguras, 13, 16);

        $x->aba('SAC', $linhas, [
            'larguras'       => $larguras,
            'mesclar'        => $mesclar,
            'congelarLinhas' => 5,
            'filtro'         => 'A5:' . Xlsx::coluna($ultima) . $ultimaLinha,
            'paisagem'       => true,
        ]);
    }

    private static function abaResumo(Xlsx $x, array $lista, array $f, string $info): void
    {
        $ind = Sac::indicadores($lista, ($f['de'] ?? '') ?: null, ($f['ate'] ?? '') ?: null);
        $linhas = self::faixa($x, 'Resumo do SAC', $info);
        $mesclar = self::completarFaixa($linhas, 2);

        $cab = $x->estilo(['fonte' => ['b' => 1, 'cor' => '0E1219', 'tam' => 11], 'fundo' => self::DOURADO, 'borda' => 'cabecalho', 'alinhar' => 'center']);
        $cabEsq = $x->estilo(['fonte' => ['b' => 1, 'cor' => '0E1219', 'tam' => 11], 'fundo' => self::DOURADO, 'borda' => 'cabecalho', 'alinhar' => 'left']);
        $est = [];
        foreach ([self::BRANCO, self::CREME] as $z => $fundo) {
            $base = ['fundo' => $fundo, 'borda' => 'linha', 'fonte' => ['tam' => 10]];
            $est[$z] = [
                'rotulo' => $x->estilo(['fonte' => ['b' => 1, 'cor' => self::AZUL, 'tam' => 10], 'quebrar' => 1] + $base),
                'num'    => $x->estilo(['alinhar' => 'center', 'formato' => '#,##0'] + $base),
                'texto'  => $x->estilo(['alinhar' => 'center'] + $base),
                'moeda'  => $x->estilo(['alinhar' => 'right', 'formato' => '"R$" #,##0.00'] + $base),
                'pct'    => $x->estilo(['alinhar' => 'center', 'formato' => '0.0%'] + $base),
            ];
        }

        $n = 5;
        $total = $ind['total'];
        $secao = function (array $titulos, array $itens) use (&$linhas, &$n, $cab, $cabEsq, $est) {
            $linhas[$n++] = ['altura' => 26, 'celulas' => [['v' => $titulos[0], 's' => $cabEsq], ['v' => $titulos[1], 's' => $cab], ['v' => $titulos[2], 's' => $cab]]];
            if (!$itens) $itens = [['Nenhum registro no período', null, 'texto', null]];
            foreach ($itens as $i => [$rotulo, $valor, $tipo, $pct]) {
                $s = $est[$i % 2];
                $linhas[$n++] = ['altura' => 20, 'celulas' => [
                    ['v' => (string) $rotulo, 's' => $s['rotulo']],
                    ['v' => $valor, 's' => $s[$tipo]],
                    ['v' => $pct, 's' => $s['pct']],
                ]];
            }
            $n++; // linha em branco entre as seções
        };
        $pct = fn($v, $de) => $de ? $v / $de : null;
        $ranking = fn(array $r, string $tipo = 'num', ?float $base = null) => array_map(
            fn($k, $v) => [$k, $v, $tipo, $pct($v, $base ?? $total)], array_keys($r), array_values($r));

        $secao(['Indicador', 'Valor', '% do total'], [
            ['Total de reclamações', $total, 'num', null],
            ['Em aberto', $ind['abertos'], 'num', $pct($ind['abertos'], $total)],
            ['Finalizadas', $ind['finalizados'], 'num', $pct($ind['finalizados'], $total)],
            ['Tempo médio de resolução', duracao($ind['tempo_medio_h']), 'texto', null],
            ['Tempo mediano de resolução', duracao($ind['tempo_mediana_h']), 'texto', null],
            ['Valor gasto total', $ind['valor_total'], 'moeda', null],
            ['Valor médio por reclamação com custo', $ind['valor_medio'] === null ? null : round($ind['valor_medio'], 2), 'moeda', null],
            ['Reclamações com custo', $ind['com_valor'], 'num', $pct($ind['com_valor'], $total)],
            ['Produtos diferentes reclamados', $ind['produtos'], 'num', null],
            ['Lotes diferentes reclamados', $ind['lotes'], 'num', null],
            ['Tipo de reclamação mais frequente', $ind['tipo_principal'] ?? '—', 'texto', null],
        ]);
        $secao(['Reclamações por tipo', 'Qtd.', '% do total'], $ranking($ind['por_tipo']));
        $secao(['Produtos mais reclamados', 'Qtd.', '% do total'], $ranking($ind['top_produto']));
        $secao(['Motivos mais frequentes', 'Qtd.', '% do total'], $ranking($ind['top_descricao']));
        $secao(['Soluções aplicadas', 'Qtd.', '% do total'], $ranking($ind['top_solucao']));
        $secao(['Lotes mais reclamados', 'Qtd.', '% do total'], $ranking($ind['top_lote']));
        $secao(['Datas com mais reclamações', 'Qtd.', '% do total'], array_map(
            fn($k, $v) => [data_br($k), $v, 'num', $pct($v, $total)], array_keys($ind['top_datas']), array_values($ind['top_datas'])));
        $secao(['Valor gasto por produto', 'Valor', '% do valor'], $ranking($ind['valor_produto'], 'moeda', $ind['valor_total']));
        $secao(['Valor gasto por motivo', 'Valor', '% do valor'], $ranking($ind['valor_descricao'], 'moeda', $ind['valor_total']));

        $x->aba('Resumo', $linhas, ['larguras' => [55, 18, 14], 'mesclar' => $mesclar]);
    }
}
