<?php
/*
 * Regras do SAC: campos (os mesmos da aba "SAC" da planilha), gravação, filtros e indicadores.
 */

final class Sac
{
    public const COL = 'sac_reclamacoes';

    /** campo => rótulo, na ordem das colunas da planilha */
    public const CAMPOS = [
        'data'             => 'Data',
        'hora'             => 'Hora',
        'codigo_pedido'    => 'Código Pedido',
        'cliente'          => 'Cliente',
        'razao_social'     => 'Razão Social',
        'contato'          => 'Contato',
        'tipo'             => 'Tipo de reclamação',
        'data_fabricacao'  => 'Data de Fabricação',
        'lote'             => 'Lote',
        'lote_horario'     => 'Lote - Horário',
        'produto'          => 'Produto',
        'descricao'        => 'Descrição do Atendimento',
        'solucao'          => 'Solução / Retorno',
        'valor'            => 'Valor gasto (R$)',
        'data_finalizacao' => 'Data de Finalização',
        'hora_finalizacao' => 'Hora de Finalização',
        'observacoes'      => 'Observações',
    ];

    /** Limpa e valida os dados vindos do formulário ou da planilha. Retorna [dados, erros]. */
    public static function preparar(array $in, bool $estrito = true): array
    {
        $d = [];
        foreach (array_keys(self::CAMPOS) as $c) {
            $v = (string) ($in[$c] ?? '');
            // texto que não veio em UTF-8 (ex.: CSV salvo pelo Excel) é convertido; o banco só aceita UTF-8
            if (!mb_check_encoding($v, 'UTF-8')) $v = mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
            $d[$c] = trim($v);
        }
        $erros = [];

        foreach (['data', 'data_fabricacao', 'data_finalizacao'] as $c) {
            if ($d[$c] !== '' && !DateTime::createFromFormat('!Y-m-d', $d[$c])) {
                $erros[$c] = 'Data inválida.';
            }
        }
        foreach (['hora', 'hora_finalizacao'] as $c) {
            if ($d[$c] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $d[$c])) {
                $erros[$c] = 'Hora inválida (use HH:MM).';
            }
        }
        if ($d['data'] === '') $erros['data'] = 'Informe a data da reclamação.';

        // prejuízo da empresa com o atendimento (cupom, reembolso, reenvio...)
        $valor = self::lerValor($d['valor']);
        if ($valor === false) $erros['valor'] = 'Valor inválido (ex.: 150,00).';
        $d['valor'] = $valor === false ? null : $valor;

        if ($estrito) {
            if ($d['cliente'] === '' && $d['razao_social'] === '') $erros['razao_social'] = 'Informe o cliente ou a razão social.';
            if ($d['descricao'] === '') $erros['descricao'] = 'Descreva a reclamação.';
            if (!in_array($d['tipo'], opcoes('tipos'), true)) $erros['tipo'] = 'Escolha o tipo.';
            if ($d['hora_finalizacao'] !== '' && $d['data_finalizacao'] === '') $erros['data_finalizacao'] = 'Informe a data de finalização.';
            if ($d['data_finalizacao'] !== '' && $d['data'] !== '' && $d['data_finalizacao'] < $d['data']) {
                $erros['data_finalizacao'] = 'A finalização não pode ser antes da abertura.';
            }
        }

        $d['status'] = $d['data_finalizacao'] !== '' ? 'finalizado' : 'aberto';
        $d['tempo_resolucao_h'] = self::tempoResolucao($d);
        return [$d, $erros];
    }

    /**
     * "R$ 1.234,56", "1234,56", "1234.56" ou número da planilha -> 1234.56.
     * Vazio -> null. Texto que não é valor -> false.
     */
    public static function lerValor(string $v): float|null|false
    {
        $v = trim(str_ireplace('R$', '', $v));
        if ($v === '') return null;
        $v = preg_replace('/\s+/', '', $v);
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);          // formato brasileiro
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) {
            $v = str_replace('.', '', $v);                      // "1.234" = mil duzentos e trinta e quatro
        }
        if (!is_numeric($v) || (float) $v < 0) return false;
        return round((float) $v, 2);
    }

    /** Mesmo cálculo da coluna "Tempo de Resolução" da planilha: (finalização) - (abertura), em horas */
    private static function tempoResolucao(array $d): ?float
    {
        if ($d['data'] === '' || $d['hora'] === '' || $d['data_finalizacao'] === '' || $d['hora_finalizacao'] === '') {
            return null;
        }
        $ini = strtotime("{$d['data']} {$d['hora']}");
        $fim = strtotime("{$d['data_finalizacao']} {$d['hora_finalizacao']}");
        return ($ini && $fim && $fim >= $ini) ? round(($fim - $ini) / 3600, 2) : null;
    }

    public static function criar(array $d, array $usuario, string $origem = 'sistema'): string
    {
        $agora = date('c');
        $d['numero'] = db()->nextSeq('sac');
        $d['origem'] = $origem;
        $d['criado_por'] = $usuario['nome'];
        $d['criado_em'] = $agora;
        $d['atualizado_em'] = $agora;
        return db()->insert(self::COL, $d);
    }

    public static function atualizar(string $id, array $d, array $usuario): void
    {
        $d['atualizado_por'] = $usuario['nome'];
        $d['atualizado_em'] = date('c');
        db()->update(self::COL, $id, $d);
    }

    public static function buscar(string $id): ?array
    {
        return db()->findOne(self::COL, ['id' => $id]);
    }

    public static function excluir(string $id): void
    {
        db()->delete(self::COL, $id);
    }

    /**
     * Apaga TODAS as reclamações e os anexos delas. Fica registrado em "sac_auditoria"
     * quem apagou, quando, quantas eram e o prejuízo somado. A numeração dos SACs continua de onde parou.
     */
    public static function apagarTudo(array $usuario): int
    {
        $todas = db()->find(self::COL);
        $urls = [];
        foreach ($todas as $r) {
            foreach ($r['anexos'] ?? [] as $a) $urls[] = $a['url'];
        }
        $apagadas = db()->deleteAll(self::COL);
        db()->insert('sac_auditoria', [
            'acao'       => 'apagar_todas_reclamacoes',
            'usuario'    => $usuario['nome'],
            'email'      => $usuario['email'],
            'quantidade' => $apagadas,
            'anexos'     => count($urls),
            'prejuizo'   => round(array_sum(array_map(fn($r) => (float) ($r['valor'] ?? 0), $todas)), 2),
            'quando'     => date('c'),
        ]);
        error_log("SAC: {$usuario['email']} apagou todas as reclamações ($apagadas registros, " . count($urls) . ' anexos)');
        Anexos::excluir($urls);
        return $apagadas;
    }

    /** Filtros: de, ate (Y-m-d), tipo, status, q (texto livre) */
    public static function listar(array $f = []): array
    {
        $filtro = [];
        $faixa = array_filter(['$gte' => $f['de'] ?? '', '$lte' => $f['ate'] ?? '']);
        if ($faixa) $filtro['data'] = $faixa;
        if (!empty($f['tipo'])) $filtro['tipo'] = $f['tipo'];
        if (!empty($f['status'])) $filtro['status'] = $f['status'];

        $lista = db()->find(self::COL, $filtro, ['data' => -1, 'hora' => -1, 'numero' => -1]);

        $q = mb_strtolower(trim($f['q'] ?? ''));
        if ($q !== '') {
            $lista = array_values(array_filter($lista, function ($r) use ($q) {
                $texto = implode(' ', [$r['numero'] ?? '', data_br($r['data'] ?? ''), $r['codigo_pedido'] ?? '', $r['cliente'] ?? '', $r['razao_social'] ?? '',
                    $r['produto'] ?? '', $r['lote'] ?? '', $r['descricao'] ?? '', $r['solucao'] ?? '', $r['observacoes'] ?? '']);
                return str_contains(mb_strtolower($texto), $q);
            }));
        }
        return $lista;
    }

    /** Separa "130 - 115 - 200" em itens; "104 (184)" continua um item só, como na planilha */
    private static function itens(?string $v): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\s+-\s+/', (string) $v))));
    }

    private static function ranking(array $contagem, int $limite = 8): array
    {
        arsort($contagem);
        return array_slice($contagem, 0, $limite, true);
    }

    /** Todos os números do dashboard (os mesmos da aba "Dashboard Final" e alguns a mais) */
    public static function indicadores(array $lista, ?string $de, ?string $ate): array
    {
        $tempos = [];
        $porTipo = $porDescricao = $porProduto = $porLote = $porSolucao = $porMes = $porData = [];
        $abertos = [];
        $hoje = date('Y-m-d');
        $valorTotal = 0.0;
        $comValor = 0;
        $valorMes = $valorDescricao = $valorProduto = [];

        foreach ($lista as $r) {
            if (!empty($r['tipo'])) $porTipo[$r['tipo']] = ($porTipo[$r['tipo']] ?? 0) + 1;
            if (!empty($r['descricao'])) $porDescricao[$r['descricao']] = ($porDescricao[$r['descricao']] ?? 0) + 1;
            if (!empty($r['solucao'])) $porSolucao[$r['solucao']] = ($porSolucao[$r['solucao']] ?? 0) + 1;
            foreach (self::itens($r['produto'] ?? '') as $p) $porProduto[$p] = ($porProduto[$p] ?? 0) + 1;
            foreach (self::itens($r['lote'] ?? '') as $l) $porLote[$l] = ($porLote[$l] ?? 0) + 1;
            if (!empty($r['data'])) {
                $m = substr($r['data'], 0, 7);
                $porMes[$m] = ($porMes[$m] ?? 0) + 1;
                $porData[$r['data']] = ($porData[$r['data']] ?? 0) + 1;
            }
            if (isset($r['tempo_resolucao_h'])) $tempos[] = (float) $r['tempo_resolucao_h'];
            if (isset($r['valor']) && $r['valor'] > 0) {
                $v = (float) $r['valor'];
                $valorTotal += $v;
                $comValor++;
                if (!empty($r['data'])) $valorMes[substr($r['data'], 0, 7)] = ($valorMes[substr($r['data'], 0, 7)] ?? 0) + $v;
                if (!empty($r['descricao'])) $valorDescricao[$r['descricao']] = ($valorDescricao[$r['descricao']] ?? 0) + $v;
                // SAC com vários produtos: o valor é dividido igualmente entre eles
                $prods = self::itens($r['produto'] ?? '');
                foreach ($prods as $p) $valorProduto[$p] = ($valorProduto[$p] ?? 0) + $v / count($prods);
            }
            if (($r['status'] ?? '') === 'aberto') {
                $r['dias_aberto'] = $r['data'] ? (int) floor((strtotime($hoje) - strtotime($r['data'])) / 86400) : null;
                $abertos[] = $r;
            }
        }

        // eixo de meses contínuo (meses sem SAC aparecem com zero)
        $meses = $mesesValor = [];
        if ($porMes) {
            $ini = substr($de ?: min(array_keys($porMes)) . '-01', 0, 7);
            $fim = substr($ate ?: max(array_keys($porMes)) . '-01', 0, 7);
            $cursor = new DateTime("$ini-01");
            while ($cursor->format('Y-m') <= $fim && count($meses) < 36) {
                $k = $cursor->format('Y-m');
                $meses[$k] = $porMes[$k] ?? 0;
                $mesesValor[$k] = round($valorMes[$k] ?? 0, 2);
                $cursor->modify('+1 month');
            }
        }

        usort($abertos, fn($a, $b) => strcmp($a['data'] ?? '', $b['data'] ?? ''));
        $total = count($lista);
        $finalizados = $total - count($abertos);

        return [
            'total'         => $total,
            'abertos'       => count($abertos),
            'finalizados'   => $finalizados,
            'pct_final'     => $total ? round($finalizados / $total * 100) : 0,
            'tempo_medio_h' => $tempos ? array_sum($tempos) / count($tempos) : null,
            'tempo_mediana_h' => $tempos ? self::mediana($tempos) : null,
            'produtos'      => count($porProduto),
            'lotes'         => count($porLote),
            'tipo_principal' => $porTipo ? array_key_first(self::ranking($porTipo, 1)) : null,
            'por_tipo'      => self::ranking($porTipo),
            'top_descricao' => self::ranking($porDescricao),
            'top_produto'   => self::ranking($porProduto),
            'top_lote'      => self::ranking($porLote),
            'top_solucao'   => self::ranking($porSolucao, 6),
            'top_datas'     => self::ranking($porData, 6),
            'por_mes'       => $meses,
            'valor_total'   => round($valorTotal, 2),
            'valor_medio'   => $comValor ? $valorTotal / $comValor : null,
            'com_valor'     => $comValor,
            'valor_mes'     => array_sum($mesesValor) > 0 ? $mesesValor : [],
            'valor_descricao' => array_map(fn($v) => round($v, 2), self::ranking($valorDescricao)),
            'valor_produto' => array_map(fn($v) => round($v, 2), self::ranking($valorProduto)),
            'lista_abertos' => $abertos,
        ];
    }

    private static function mediana(array $v): float
    {
        sort($v);
        $n = count($v);
        return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }

    /** Valores já usados, para as sugestões dos campos de texto */
    public static function sugestoes(): array
    {
        $desc = opcoes('descricoes');
        $sol = opcoes('solucoes');
        $clientes = [];
        foreach (db()->find(self::COL) as $r) {
            if (!empty($r['descricao'])) $desc[] = $r['descricao'];
            if (!empty($r['solucao'])) $sol[] = $r['solucao'];
            if (!empty($r['razao_social'])) $clientes[$r['razao_social']] = [$r['cliente'] ?? '', $r['contato'] ?? ''];
        }
        ksort($clientes);
        return [
            'descricoes' => array_values(array_unique($desc)),
            'solucoes'   => array_values(array_unique($sol)),
            'clientes'   => $clientes,
        ];
    }
}
