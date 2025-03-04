<?php

return [

    'label' => 'Navegação da paginação',

    'overview' => '{1} Mostrando 1 resultado|[2,*] Mostrando :first a :last de :total resultados',

    'fields' => [

        'records_per_page' => [

            'label' => 'por página',

            'options' => [
                'all' => 'Todos',
            ],

        ],

    ],

    'actions' => [

        'go_to_page' => [
            'label' => 'Ir para a página :page',
        ],

        'next' => [
            'label' => 'Próximo',
        ],

        'previous' => [
            'label' => 'Anterior',
        ],

    ],

];
