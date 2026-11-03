<?php
/*
 * Listas de opções do SAC (baseadas na planilha "planilha SAC.xlsx").
 * Para incluir/remover uma opção basta editar este arquivo.
 */
return [
    // coluna "Reclamação - Qualidade" da planilha
    'tipos' => ['Qualidade', 'Logística'],

    // sugestões exibidas ao digitar; textos novos digitados também passam a ser sugeridos
    'descricoes' => [
        'Pães fora do padrão (pequenos).',
        'Pães fora do padrão (amassados).',
        'Pães fora do padrão (pequenos e corte).',
        'Pães fora do padrão (pequenos e duros).',
        'Pães enviados sem corte.',
        'Produto entregue etiquetado errado.',
        'Pedido entregue com caixa faltando.',
        'Caixa com pacotes faltando.',
        'Caixas enviadas erradas.',
        'Pedido entregue com validade curta.',
        'Pedido entregue já vencido.',
        'Motorista entregou apenas NF.',
    ],

    'solucoes' => [
        'Disponibilização do cupom no valor da caixa.',
        'Disponibilização do cupom no valor dos pacotes.',
        'Envio da caixa corretamente.',
        'Envio das caixas corretas e recolha das caixas erradas.',
        'Envio da caixa faltante junto ao próximo pedido.',
        'Reembolso realizado no valor da caixa.',
        'Recolher pacotes para análise.',
        'Não obtivemos retorno do cliente.',
    ],

    // SAC aberto há mais dias que isso aparece destacado
    'dias_alerta' => 3,
];
