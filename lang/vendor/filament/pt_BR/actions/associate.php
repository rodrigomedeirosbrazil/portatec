<?php

return [

    'single' => [

        'label' => 'Associar',

        'modal' => [

            'heading' => 'Associar :label',

            'fields' => [

                'record_id' => [
                    'label' => 'Registro',
                ],

            ],

            'actions' => [

                'associate' => [
                    'label' => 'Associar',
                ],

                'associate_another' => [
                    'label' => 'Associar e associar outro',
                ],

            ],

        ],

        'notifications' => [

            'associated' => [
                'title' => 'Associado',
            ],

        ],

    ],

    'multiple' => [

        'label' => 'Associar selecionados',

        'modal' => [

            'heading' => 'Associar :label selecionados',

            'fields' => [

                'record_ids' => [
                    'label' => 'Registros',
                ],

            ],

            'actions' => [

                'associate' => [
                    'label' => 'Associar',
                ],

                'associate_another' => [
                    'label' => 'Associar e associar outros',
                ],

            ],

        ],

        'notifications' => [

            'associated' => [
                'title' => 'Associados',
            ],

        ],

    ],

];
