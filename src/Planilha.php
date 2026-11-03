<?php
/*
 * Importação da planilha de SAC (.xlsx ou .csv) e exportação em CSV no mesmo layout.
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

    /** Envia CSV (separado por ";", abre direto no Excel) com as mesmas colunas da planilha */
    public static function exportar(array $lista): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sac-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_merge(['Nº'], array_values(Sac::CAMPOS), ['Status', 'Tempo de Resolução (h)']), ';', '"', '');
        foreach ($lista as $r) {
            $linha = [$r['numero'] ?? ''];
            foreach (array_keys(Sac::CAMPOS) as $c) {
                $v = $r[$c] ?? '';
                $linha[] = in_array($c, self::CAMPOS_DATA, true) ? data_br($v) : $v;
            }
            $linha[] = ($r['status'] ?? '') === 'finalizado' ? 'Finalizado' : 'Aberto';
            $linha[] = isset($r['tempo_resolucao_h']) ? number_format((float) $r['tempo_resolucao_h'], 2, ',', '') : '';
            fputcsv($out, $linha, ';', '"', '');
        }
        fclose($out);
        exit;
    }
}
