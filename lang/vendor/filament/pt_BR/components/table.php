<?php

return [

    'fields' => [

        'search' => [
            'label' => 'Pesquisar',
            'placeholder' => 'Pesquisar',
        ],

    ],

    'actions' => [

        'filter' => [
            'label' => 'Filtrar',
        ],

        'open_bulk_actions' => [
            'label' => 'Abrir ações',
        ],

        'toggle_columns' => [
            'label' => 'Alternar colunas',
        ],

    ],

    'empty' => [
        'heading' => 'Nenhum registro encontrado',
        'description' => 'Crie um registro para começar.',
    ],

    'filters' => [

        'actions' => [

            'reset' => [
                'label' => 'Limpar filtros',
            ],

        ],

        'multi_select' => [
            'placeholder' => 'Todos',
        ],

        'select' => [
            'placeholder' => 'Todos',
        ],

        'trashed' => [

            'label' => 'Registros excluídos',

            'options' => [
                'with' => 'Mostrar registros excluídos',
                'only' => 'Mostrar apenas registros excluídos',
                'without' => 'Não mostrar registros excluídos',
            ],

        ],

    ],

    'selection_indicator' => [

        'selected_count' => '{1} 1 registro selecionado|[2,*] :count registros selecionados',

        'actions' => [

            'select_all' => [
                'label' => 'Selecionar todos :count',
            ],

            'deselect_all' => [
                'label' => 'Desmarcar todos',
            ],

        ],

    ],

];
