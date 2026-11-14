<?php
/*
 * Gerador simples de planilhas .xlsx (sem dependências, usa ZipArchive).
 * Suporta várias abas, estilos (fonte, preenchimento, borda, formato de número, alinhamento),
 * células mescladas, larguras de coluna, altura de linha, painel congelado, autofiltro e fórmulas.
 *
 * Célula: valor simples (string/int/float/null) ou array
 *   ['v' => valor, 's' => idEstilo, 'f' => 'FÓRMULA', 'data' => true (valor 'Y-m-d' vira data do Excel)]
 */

final class Xlsx
{
    private array $abas = [];
    private array $fontes = ['<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'];
    private array $preenchimentos = ['<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>'];
    private array $bordas = ['<border><left/><right/><top/><bottom/><diagonal/></border>'];
    private array $formatos = [];
    private array $xfs = ['<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'];
    private array $cacheXf = [];

    /**
     * Registra um estilo e devolve o id para usar nas células.
     * Opções: fonte [b, i, cor, tam], fundo (RRGGBB), borda ('cabecalho'|'linha'), formato (código), alinhar, quebrar.
     */
    public function estilo(array $o): int
    {
        $chave = json_encode($o);
        if (isset($this->cacheXf[$chave])) return $this->cacheXf[$chave];

        $f = $o['fonte'] ?? [];
        $fonte = '<font>' . (!empty($f['b']) ? '<b/>' : '') . (!empty($f['i']) ? '<i/>' : '')
            . '<sz val="' . ($f['tam'] ?? 10) . '"/>'
            . (isset($f['cor']) ? '<color rgb="FF' . $f['cor'] . '"/>' : '')
            . '<name val="Calibri"/><family val="2"/></font>';
        $fontId = $this->indice($this->fontes, $fonte);

        $fillId = 0;
        if (!empty($o['fundo'])) {
            $fillId = $this->indice($this->preenchimentos,
                '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $o['fundo'] . '"/><bgColor indexed="64"/></patternFill></fill>');
        }

        $borda = match ($o['borda'] ?? null) {
            'cabecalho' => '<border><left style="thin"><color rgb="FFB8860B"/></left><right style="thin"><color rgb="FFB8860B"/></right>'
                . '<top style="medium"><color rgb="FF996600"/></top><bottom style="medium"><color rgb="FF996600"/></bottom><diagonal/></border>',
            'linha' => '<border><left/><right style="thin"><color rgb="FFE8DDD0"/></right><top/>'
                . '<bottom style="thin"><color rgb="FFE0D0C0"/></bottom><diagonal/></border>',
            default => null,
        };
        $borderId = $borda ? $this->indice($this->bordas, $borda) : 0;

        $numFmtId = 0;
        if (!empty($o['formato'])) {
            $numFmtId = array_search($o['formato'], $this->formatos, true);
            if ($numFmtId === false) {
                $numFmtId = 164 + count($this->formatos);
                $this->formatos[$numFmtId] = $o['formato'];
            }
        }

        $alin = '<alignment horizontal="' . ($o['alinhar'] ?? 'left') . '" vertical="center"' . (!empty($o['quebrar']) ? ' wrapText="1"' : '') . '/>';
        $this->xfs[] = '<xf numFmtId="' . $numFmtId . '" fontId="' . $fontId . '" fillId="' . $fillId . '" borderId="' . $borderId
            . '" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $alin . '</xf>';
        return $this->cacheXf[$chave] = count($this->xfs) - 1;
    }

    private function indice(array &$lista, string $xml): int
    {
        $i = array_search($xml, $lista, true);
        if ($i !== false) return $i;
        $lista[] = $xml;
        return count($lista) - 1;
    }

    /**
     * Adiciona uma aba.
     * $linhas: [numLinha => ['celulas' => [col(0..n) => célula], 'altura' => pt]]
     * $opcoes: larguras [], mesclar ['A1:T1'], congelarLinhas int, filtro 'A5:T90', paisagem bool
     */
    public function aba(string $nome, array $linhas, array $opcoes = []): void
    {
        $this->abas[] = ['nome' => mb_substr(preg_replace('#[\\\\/?*\[\]:]#', ' ', $nome), 0, 31), 'linhas' => $linhas, 'op' => $opcoes];
    }

    public static function coluna(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }

    private static function texto(string $v): string
    {
        // remove caracteres de controle que o XML não aceita
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? '';
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** 'Y-m-d' -> número de série do Excel */
    public static function serieData(string $iso): ?int
    {
        $d = DateTime::createFromFormat('!Y-m-d', $iso, new DateTimeZone('UTC'));
        return $d ? (int) round($d->getTimestamp() / 86400) + 25569 : null;
    }

    private function xmlAba(array $aba): string
    {
        $op = $aba['op'];
        $maxCol = 0;
        $maxLin = 1;
        $dados = '';
        ksort($aba['linhas']);
        foreach ($aba['linhas'] as $n => $linha) {
            $maxLin = max($maxLin, $n);
            $attr = isset($linha['altura']) ? ' ht="' . $linha['altura'] . '" customHeight="1"' : '';
            $cels = '';
            foreach ($linha['celulas'] ?? [] as $c => $cel) {
                $maxCol = max($maxCol, $c);
                $cel = is_array($cel) ? $cel : ['v' => $cel];
                $ref = self::coluna($c) . $n;
                $s = isset($cel['s']) ? ' s="' . $cel['s'] . '"' : '';
                $v = $cel['v'] ?? null;
                if (!empty($cel['data']) && is_string($v) && $v !== '') $v = self::serieData($v) ?? $v;

                if (isset($cel['f'])) {
                    $cels .= '<c r="' . $ref . '"' . $s . '><f>' . self::texto($cel['f']) . '</f>'
                        . (is_int($v) || is_float($v) ? '<v>' . $v . '</v>' : '') . '</c>';
                } elseif ($v === null || $v === '') {
                    $cels .= '<c r="' . $ref . '"' . $s . '/>';
                } elseif (is_int($v) || is_float($v)) {
                    $cels .= '<c r="' . $ref . '"' . $s . '><v>' . $v . '</v></c>';
                } else {
                    $cels .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . self::texto((string) $v) . '</t></is></c>';
                }
            }
            $dados .= '<row r="' . $n . '"' . $attr . '>' . $cels . '</row>';
        }

        $paisagem = !empty($op['paisagem']);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<dimension ref="A1:' . self::coluna($maxCol) . $maxLin . '"/>'
            . '<sheetViews><sheetView workbookViewId="0" showGridLines="0">';
        if (!empty($op['congelarLinhas'])) {
            $xml .= '<pane ySplit="' . $op['congelarLinhas'] . '" topLeftCell="A' . ($op['congelarLinhas'] + 1) . '" activePane="bottomLeft" state="frozen"/>'
                . '<selection pane="bottomLeft" activeCell="A' . ($op['congelarLinhas'] + 1) . '" sqref="A' . ($op['congelarLinhas'] + 1) . '"/>';
        }
        $xml .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>';
        if (!empty($op['larguras'])) {
            $xml .= '<cols>';
            foreach ($op['larguras'] as $i => $w) $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>' . $dados . '</sheetData>';
        if (!empty($op['filtro'])) $xml .= '<autoFilter ref="' . $op['filtro'] . '"/>';
        if (!empty($op['mesclar'])) {
            $xml .= '<mergeCells count="' . count($op['mesclar']) . '">';
            foreach ($op['mesclar'] as $m) $xml .= '<mergeCell ref="' . $m . '"/>';
            $xml .= '</mergeCells>';
        }
        $xml .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            . '<pageSetup paperSize="9" orientation="' . ($paisagem ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';
        return $xml;
    }

    private function xmlEstilos(): string
    {
        $fmt = '';
        if ($this->formatos) {
            $fmt = '<numFmts count="' . count($this->formatos) . '">';
            foreach ($this->formatos as $id => $codigo) $fmt .= '<numFmt numFmtId="' . $id . '" formatCode="' . self::texto($codigo) . '"/>';
            $fmt .= '</numFmts>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $fmt
            . '<fonts count="' . count($this->fontes) . '">' . implode('', $this->fontes) . '</fonts>'
            . '<fills count="' . count($this->preenchimentos) . '">' . implode('', $this->preenchimentos) . '</fills>'
            . '<borders count="' . count($this->bordas) . '">' . implode('', $this->bordas) . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($this->xfs) . '">' . implode('', $this->xfs) . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** Gera o arquivo no caminho indicado */
    public function salvar(string $caminho): void
    {
        $zip = new ZipArchive();
        if ($zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível gerar o arquivo .xlsx.');
        }
        $tipos = $abasWb = $rels = '';
        $nomesDefinidos = '';
        foreach ($this->abas as $i => $aba) {
            $n = $i + 1;
            $zip->addFromString("xl/worksheets/sheet$n.xml", $this->xmlAba($aba));
            $tipos .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $abasWb .= '<sheet name="' . self::texto($aba['nome']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            if (!empty($aba['op']['filtro'])) {
                [$a, $b] = explode(':', $aba['op']['filtro']);
                $abs = fn($r) => preg_replace_callback('/^([A-Z]+)(\d+)$/', fn($m) => '$' . $m[1] . '$' . $m[2], $r);
                $nomesDefinidos .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\''
                    . str_replace("'", "''", self::texto($aba['nome'])) . '\'!' . $abs($a) . ':' . $abs($b) . '</definedName>';
            }
        }
        $estilos = count($this->abas) + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . $tipos . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Relatório SAC</dc:title><dc:creator>Dashboard SAC</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
            . '</cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . '<sheets>' . $abasWb . '</sheets>'
            . ($nomesDefinidos ? '<definedNames>' . $nomesDefinidos . '</definedNames>' : '')
            . '<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels
            . '<Relationship Id="rId' . $estilos . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', $this->xmlEstilos());
        $zip->close();
    }

    /** Envia o arquivo para download e encerra */
    public function baixar(string $nomeArquivo): never
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        try {
            $this->salvar($tmp);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
            header('Content-Length: ' . filesize($tmp));
            header('Cache-Control: no-store');
            readfile($tmp);
        } finally {
            @unlink($tmp);
        }
        exit;
    }
}
